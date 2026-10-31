<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function checkTask(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function denyTask(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException $error) { checkTask(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
$db=Database::connect(); $users=[]; $leads=[]; $properties=[]; $client=null;
try {
    $adminId=(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' LIMIT 1")->fetchColumn();
    if (!$adminId) throw new RuntimeException('Se requiere superadministrador local.');
    $admin=['id'=>$adminId,'rol'=>'superadmin']; $now=new DateTimeImmutable('2030-06-15 10:00:00',new DateTimeZone('America/Lima'));
    $baseline=Task::summary($admin,$now);
    $role=(int)$db->query("SELECT id FROM roles WHERE nombre='vendedor'")->fetchColumn();
    foreach ([1,2] as $unused) {
        $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Pendientes',?,?)")->execute([$role,bin2hex(random_bytes(8)).'@inmova.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]); $users[]=(int)$db->lastInsertId();
    }
    $one=['id'=>$users[0],'rol'=>'vendedor']; $two=['id'=>$users[1],'rol'=>'vendedor'];
    $db->exec("INSERT INTO clientes (nombres) VALUES ('QA Pendientes temporal')"); $client=(int)$db->lastInsertId();
    $type=(int)$db->query('SELECT id FROM tipos_propiedad LIMIT 1')->fetchColumn(); $mode=(int)$db->query('SELECT id FROM modalidades LIMIT 1')->fetchColumn();
    foreach ($users as $seller) {
        $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio) VALUES (?,?,?,'QA Pendientes temporal',10000)")->execute([$type,$mode,$seller]); $properties[]=(int)$db->lastInsertId();
        $db->prepare("INSERT INTO leads (cliente_id,propiedad_id,vendedor_id,etapa) VALUES (?,?,?,'contactado')")->execute([$client,end($properties),$seller]); $leads[]=(int)$db->lastInsertId();
    }
    $dates=['2030-06-15 09:59:59','2030-06-15 10:00:00','2030-06-15 23:59:59','2030-06-16 00:00:00']; $tasks=[];
    foreach ($dates as $date) {
        $db->prepare("INSERT INTO seguimientos (lead_id,usuario_id,tipo,detalle,proxima_accion) VALUES (?,?,'llamada','QA seguimiento original',?)")->execute([$leads[0],$users[0],$date]); $tasks[]=(int)$db->lastInsertId();
    }
    $db->prepare("INSERT INTO seguimientos (lead_id,usuario_id,detalle,proxima_accion,tarea_completada_at,tarea_completada_por) VALUES (?,?,'QA completado','2030-06-14 12:00:00','2030-06-14 11:00:00',?)")->execute([$leads[0],$users[0],$users[0]]);
    $db->prepare("INSERT INTO seguimientos (lead_id,usuario_id,detalle,proxima_accion) VALUES (?,?,'QA otro vendedor','2030-06-15 09:00:00')")->execute([$leads[1],$users[1]]);
    foreach (['cerrado','perdido'] as $stage) {
        $db->prepare('INSERT INTO leads (cliente_id,vendedor_id,etapa) VALUES (?,?,?)')->execute([$client,$users[0],$stage]); $leads[]=(int)$db->lastInsertId();
        $db->prepare("INSERT INTO seguimientos (lead_id,usuario_id,detalle,proxima_accion) VALUES (?,?,'QA cerrado','2030-06-14 12:00:00')")->execute([end($leads),$users[0]]);
    }
    $db->prepare('INSERT INTO leads (cliente_id) VALUES (?)')->execute([$client]); $leads[]=(int)$db->lastInsertId();
    foreach ([['2030-06-15 09:00:00','solicitada'],['2030-06-15 10:00:00','confirmada'],['2030-06-22 10:00:00','confirmada'],['2030-06-16 10:00:00','cancelada'],['2030-06-14 10:00:00','realizada']] as [$date,$state]) $db->prepare('INSERT INTO visitas (lead_id,propiedad_id,cliente_id,vendedor_id,fecha_hora,estado_visita) VALUES (?,?,?,?,?,?)')->execute([$leads[0],$properties[0],$client,$users[0],$date,$state]);
    $db->prepare("INSERT INTO reservas (lead_id,propiedad_id,cliente_id,vendedor_id,monto_reserva) VALUES (?,?,?,?,100)")->execute([$leads[0],$properties[0],$client,$users[0]]);
    $summary=Task::summary($one,$now);
    checkTask($summary['overdue']===1 && $summary['today']===2 && $summary['upcoming']===1,'límites horarios de vencidos, hoy y mañana');
    checkTask($summary['visitsOverdue']===1 && $summary['visitsSoon']===1 && $summary['reservations']===1,'visitas canceladas/realizadas y extremo de siete días excluidos');
    checkTask($summary['unassigned']===0 && Task::unassigned($one)===[],'vendedor no ve consultas sin asignación');
    checkTask(count(Task::listing($one,'pendientes',$now))===4 && count(Task::listing($one,'completadas',$now))===1,'cerrados/perdidos no generan pendientes y completados separados');
    checkTask(count(Task::listing($two,'pendientes',$now))===1 && Task::find($tasks[0],$two)===null,'seguimientos aislados por vendedor actual');
    checkTask(Task::summary($admin,$now)['unassigned']===$baseline['unassigned']+1,'administrador ve consultas sin asignar');
    denyTask(fn()=>Task::resolve($tasks[0],true,$two,$now),'vendedor no completa tarea ajena');
    Task::resolve($tasks[0],true,$one,$now); $saved=Task::find($tasks[0],$one);
    checkTask($saved['detalle']==='QA seguimiento original' && $saved['proxima_accion']===$dates[0] && $saved['tarea_completada_at']==='2030-06-15 10:00:00' && (int)$saved['tarea_completada_por']===$users[0],'completar conserva seguimiento y registra responsable/hora');
    $historyCount=count(Lead::history($leads[0])); Task::resolve($tasks[0],true,$one,$now);
    checkTask(count(Lead::history($leads[0]))===$historyCount,'completar dos veces es idempotente');
    Task::resolve($tasks[0],false,$one,$now); checkTask(Task::summary($one,$now)['overdue']===1,'reabrir devuelve pendiente al centro');
    $db->prepare('UPDATE leads SET vendedor_id=? WHERE id=?')->execute([$users[1],$leads[0]]);
    denyTask(fn()=>Task::resolve($tasks[0],true,$one,$now),'reasignación revoca permisos anteriores');
    Task::resolve($tasks[0],true,$two,$now);
    $db->prepare("UPDATE leads SET etapa='cerrado' WHERE id=?")->execute([$leads[0]]);
    denyTask(fn()=>Task::resolve($tasks[0],false,$two,$now),'no reabrir pendientes de consulta cerrada');
    checkTask(Task::clock(new DateTimeImmutable('2030-06-15 15:00:00',new DateTimeZone('UTC')))['now']==='2030-06-15 10:00:00','fechas se interpretan en hora de Lima');
    denyTask(fn()=>Task::summary(['id'=>$users[0],'rol'=>'instructor'],$now),'instructor sin acceso a pendientes comerciales');
} finally {
    foreach ($properties as $id) { $db->prepare('DELETE FROM reservas WHERE propiedad_id=?')->execute([$id]); $db->prepare('DELETE FROM visitas WHERE propiedad_id=?')->execute([$id]); }
    foreach ($leads as $id) $db->prepare('DELETE FROM leads WHERE id=?')->execute([$id]);
    foreach ($properties as $id) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$id]);
    if ($client) $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$client]);
    foreach ($users as $id) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
}
