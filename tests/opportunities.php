<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function checkOpportunity(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function denyOpportunity(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException $error) { checkOpportunity(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
$db=Database::connect(); $users=[]; $properties=[]; $leads=[]; $client=null;
try {
    $adminId=(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' AND u.estado=1 LIMIT 1")->fetchColumn();
    if (!$adminId) throw new RuntimeException('Se requiere superadministrador local.');
    $admin=['id'=>$adminId,'rol'=>'superadmin'];
    $role=(int)$db->query("SELECT id FROM roles WHERE nombre='vendedor'")->fetchColumn();
    foreach ([1,2] as $unused) {
        $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Oportunidades',?,?)")->execute([$role,bin2hex(random_bytes(8)).'@inmova.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]); $users[]=(int)$db->lastInsertId();
    }
    $one=['id'=>$users[0],'rol'=>'vendedor']; $two=['id'=>$users[1],'rol'=>'vendedor'];
    $db->exec("INSERT INTO clientes (nombres) VALUES ('QA Oportunidad temporal')"); $client=(int)$db->lastInsertId();
    $type=(int)$db->query('SELECT id FROM tipos_propiedad WHERE estado=1 LIMIT 1')->fetchColumn();
    $sale=(int)$db->query("SELECT id FROM modalidades WHERE slug='terrenos'")->fetchColumn(); $rent=(int)$db->query("SELECT id FROM modalidades WHERE slug='alquiler'")->fetchColumn();
    foreach ([[$sale,'PEN',$users[0],1],[$rent,'USD',$users[1],1],[$sale,'USD',$users[1],0]] as [$mode,$currency,$owner,$published]) {
        $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda,publicado) VALUES (?,?,?,'QA Oportunidad temporal',10000,?,?)")->execute([$type,$mode,$owner,$currency,$published]); $properties[]=(int)$db->lastInsertId();
    }
    foreach ($users as $seller) { $db->prepare('INSERT INTO leads (cliente_id,propiedad_id,vendedor_id) VALUES (?,?,?)')->execute([$client,$properties[0],$seller]); $leads[]=(int)$db->lastInsertId(); }
    $data=['monto_estimado'=>'1000','probabilidad'=>'50','etapa'=>'abierta','notas'=>'QA negociación'];
    $first=Opportunity::save($leads[0],$properties[0],$data,null,$one);
    checkOpportunity(Opportunity::find($first,$one)['moneda']==='PEN','moneda tomada del inmueble');
    denyOpportunity(fn()=>Opportunity::save($leads[0],$properties[0],$data,null,$one),'no duplicar consulta/inmueble');
    denyOpportunity(fn()=>Opportunity::save($leads[0],$properties[0],$data,$first,$two),'vendedor no modifica oportunidad ajena');
    denyOpportunity(fn()=>Opportunity::save($leads[0],$properties[2],$data,null,$one),'vendedor no vincula borrador ajeno');
    $bad=$data; $bad['probabilidad']='101'; denyOpportunity(fn()=>Opportunity::save($leads[0],$properties[0],$bad,$first,$one),'probabilidad máxima 100');
    $bad=$data; $bad['monto_estimado']='1000.5'; denyOpportunity(fn()=>Opportunity::save($leads[0],$properties[0],$bad,$first,$one),'monto solo entero');
    $bad=$data; $bad['monto_estimado']='5000001'; denyOpportunity(fn()=>Opportunity::save($leads[0],$properties[0],$bad,$first,$one),'monto máximo cinco millones');
    $bad=$data; $bad['etapa']='ganada'; denyOpportunity(fn()=>Opportunity::save($leads[0],$properties[0],$bad,$first,$one),'ganada requiere cierre real');
    $count=count(Lead::history($leads[0])); Opportunity::save($leads[0],$properties[0],$data,$first,$one);
    checkOpportunity(count(Lead::history($leads[0]))===$count,'guardar sin cambios no duplica historial');
    $altData=$data; $altData['monto_estimado']='2000'; $altData['probabilidad']='25';
    $alternative=Opportunity::save($leads[0],$properties[1],$altData,null,$one);
    $competitor=Opportunity::save($leads[1],$properties[0],$data,null,$two);
    $summary=Opportunity::summary($one);
    checkOpportunity(count($summary['amounts'])===2 && $summary['counts']['abierta']===2,'separar moneda y operación; cartera aislada por vendedor');
    foreach ($summary['amounts'] as $row) checkOpportunity((float)$row['ponderado']===500.0,'monto ponderado correcto');
    $lost=$data; $lost['etapa']='perdida'; Opportunity::save($leads[0],$properties[0],$lost,$first,$one);
    checkOpportunity((int)Opportunity::find($first,$one)['probabilidad']===0,'perdida fija probabilidad cero');
    Opportunity::save($leads[0],$properties[0],$data,$first,$one);
    $db->prepare('UPDATE leads SET vendedor_id=? WHERE id=?')->execute([$users[1],$leads[0]]);
    checkOpportunity(Opportunity::find($first,$one)===null && Opportunity::find($first,$two)!==null,'reasignación de consulta cambia acceso');
    $db->prepare('UPDATE leads SET vendedor_id=? WHERE id=?')->execute([$users[0],$leads[0]]);
    $reservation=Reservation::create($leads[0],$users[0],100,$admin); Reservation::transition($reservation,'aprobada','QA',$admin);
    $today=(new DateTimeImmutable('today',new DateTimeZone('America/Lima')))->format('Y-m-d');
    $closing=Closing::create($reservation,900,$today,'QA cierre de oportunidad',$admin);
    $won=Opportunity::find($first,$one);
    checkOpportunity($won['etapa']==='ganada' && (int)$won['probabilidad']===100 && (float)$won['monto_estimado']===900.0 && (int)$won['cierre_id']===$closing,'cierre marca ganada con monto final real');
    checkOpportunity(Opportunity::find($alternative,$one)['etapa']==='cancelada','cierre cancela otras alternativas de la consulta');
    checkOpportunity(Opportunity::find($competitor,$two)['etapa']==='perdida','cierre marca perdida la competencia por el mismo inmueble');
    checkOpportunity(Opportunity::summary($one)['amounts']===[],'oportunidades resueltas salen del estimado abierto');
    denyOpportunity(fn()=>Opportunity::save($leads[0],$properties[0],$data,$first,$one),'ganada no se edita');
    denyOpportunity(fn()=>Opportunity::save($leads[0],$properties[1],$altData,$alternative,$one),'consulta cerrada no reabre oportunidades');
    denyOpportunity(fn()=>Opportunity::summary(['id'=>$users[0],'rol'=>'instructor']),'instructor sin acceso comercial');
} finally {
    foreach ($leads as $id) $db->prepare('DELETE FROM oportunidades WHERE lead_id=?')->execute([$id]);
    foreach ($properties as $id) {
        $db->prepare('DELETE c FROM cierres c JOIN reservas r ON r.id=c.reserva_id WHERE r.propiedad_id=?')->execute([$id]);
        $db->prepare('DELETE FROM reservas WHERE propiedad_id=?')->execute([$id]);
    }
    foreach ($leads as $id) $db->prepare('DELETE FROM leads WHERE id=?')->execute([$id]);
    foreach ($properties as $id) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$id]);
    if ($client) $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$client]);
    foreach ($users as $id) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
}
