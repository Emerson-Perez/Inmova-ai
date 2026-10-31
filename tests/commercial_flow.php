<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function denied(callable $action, string $label): void {
    try { $action(); } catch (DomainException $error) { check(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
if (!method_exists(Lead::class,'assignProperty')) throw new RuntimeException('FAIL: una consulta general no tiene un flujo para seleccionar el inmueble de sus visitas y reservas.');
$db=Database::connect(); $users=$properties=[]; $lead=$client=null;
try {
    $admin=['id'=>(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' AND u.estado=1 LIMIT 1")->fetchColumn(),'rol'=>'superadmin'];
    if (!$admin['id']) throw new RuntimeException('Falta superadministrador local.');
    $role=$db->query("SELECT id FROM roles WHERE nombre='vendedor'")->fetchColumn();
    foreach ([1,2] as $unused) {
        $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Flujo',?,?)")->execute([$role,bin2hex(random_bytes(10)).'@example.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]); $users[]=(int)$db->lastInsertId();
    }
    $seller=['id'=>$users[0],'rol'=>'vendedor']; $outsider=['id'=>$users[1],'rol'=>'vendedor'];
    $type=$db->query('SELECT id FROM tipos_propiedad WHERE estado=1 LIMIT 1')->fetchColumn();
    $mode=$db->query("SELECT id FROM modalidades WHERE slug='venta-deptos' AND estado=1 LIMIT 1")->fetchColumn();
    $rent=$db->query("SELECT id FROM modalidades WHERE slug='alquiler' AND estado=1 LIMIT 1")->fetchColumn();
    foreach ([[$seller['id'],1],[$outsider['id'],0],[$seller['id'],1]] as [$owner,$published]) {
        $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda,publicado) VALUES (?,?,?,'QA Flujo comercial temporal',10000,'PEN',?)")->execute([$type,$mode,$owner,$published]); $properties[]=(int)$db->lastInsertId();
    }
    $db->exec("INSERT INTO clientes (nombres) VALUES ('QA Consulta general temporal')"); $client=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO leads (cliente_id,vendedor_id,origen,etapa) VALUES (?,?,'agente_ia','contactado')")->execute([$client,$seller['id']]); $lead=(int)$db->lastInsertId();
    $opportunityData=['monto_estimado'=>'10000','probabilidad'=>'50','etapa'=>'abierta','notas'=>'QA'];
    $alternative=Opportunity::save($lead,$properties[0],$opportunityData,null,$seller);
    Lead::assignProperty($lead,$properties[0],$seller);
    check((int)Lead::find($lead)['propiedad_id']===$properties[0],'consulta general puede seleccionar inmueble para continuar el flujo');
    check(count(Lead::history($lead))===2,'selección conserva historial de oportunidad e inmueble');
    Lead::assignProperty($lead,$properties[0],$seller);
    check(count(Lead::history($lead))===2,'reenvío de selección no duplica historial');
    denied(fn()=>Lead::assignProperty($lead,$properties[2],$outsider),'otro vendedor no modifica la consulta');
    denied(fn()=>Lead::assignProperty($lead,$properties[2],['id'=>$seller['id'],'rol'=>'instructor']),'roles no comerciales no seleccionan inmueble');
    denied(fn()=>Lead::assignProperty($lead,$properties[1],$seller),'vendedor no selecciona borrador ajeno');
    $db->prepare("UPDATE propiedades SET disponibilidad='vendido' WHERE id=?")->execute([$properties[2]]);
    denied(fn()=>Lead::assignProperty($lead,$properties[2],$seller),'inmueble vendido no se selecciona para una nueva operación');
    $db->prepare("UPDATE propiedades SET disponibilidad='disponible',estado=0 WHERE id=?")->execute([$properties[2]]);
    denied(fn()=>Lead::assignProperty($lead,$properties[2],$seller),'inmueble inactivo no se selecciona para una nueva operación');
    $db->prepare('UPDATE propiedades SET estado=1 WHERE id=?')->execute([$properties[2]]);
    $visitData=['vendedor_id'=>$seller['id'],'fecha_hora'=>(new DateTimeImmutable('tomorrow',new DateTimeZone('America/Lima')))->format('Y-m-d 15:00:00'),'modalidad'=>'presencial','estado_visita'=>'confirmada','resultado'=>''];
    $visit=Visit::save($visitData,$seller,null,$lead);
    denied(fn()=>Lead::assignProperty($lead,$properties[2],$seller),'visita activa bloquea cambio de inmueble');
    $visitData['estado_visita']='cancelada'; Visit::save($visitData,$seller,$visit,null);
    Lead::assignProperty($lead,$properties[2],$seller);
    check((int)Lead::find($lead)['propiedad_id']===$properties[2] && Lead::find($lead)['etapa']==='contactado','sin visita activa se puede elegir alternativa y corregir etapa de agenda');
    check((int)Visit::find($visit)['propiedad_id']===$properties[0],'visita histórica conserva su inmueble original');
    $won=Opportunity::save($lead,$properties[2],$opportunityData,null,$seller);
    $reservation=Reservation::create($lead,$seller['id'],100,$seller);
    denied(fn()=>Lead::assignProperty($lead,$properties[0],$admin),'reserva pendiente bloquea cambio de inmueble');
    $data=Property::find($properties[2]); $data['departamento']=''; $data['modalidad_id']=$rent;
    denied(fn()=>Property::save($data,$properties[2],$seller['id']),'reserva pendiente impide convertir venta en alquiler');
    $db->prepare('UPDATE leads SET propiedad_id=? WHERE id=?')->execute([$properties[0],$lead]);
    denied(fn()=>Reservation::transition($reservation,'aprobada','QA',$admin),'no aprobar una reserva histórica incompatible con la consulta');
    $db->prepare('UPDATE leads SET propiedad_id=? WHERE id=?')->execute([$properties[2],$lead]);
    Reservation::transition($reservation,'aprobada','QA aprobación',$admin);
    denied(fn()=>Lead::assignProperty($lead,$properties[0],$admin),'reserva aprobada bloquea cambio de inmueble');
    $today=(new DateTimeImmutable('today',new DateTimeZone('America/Lima')))->format('Y-m-d');
    $db->prepare('UPDATE leads SET propiedad_id=? WHERE id=?')->execute([$properties[0],$lead]);
    denied(fn()=>Closing::create($reservation,9000,$today,'QA',$admin),'no cerrar una reserva incompatible con el inmueble de la consulta');
    $db->prepare('UPDATE leads SET propiedad_id=? WHERE id=?')->execute([$properties[2],$lead]);
    $closing=Closing::create($reservation,9000,$today,'QA cierre desde consulta general',$admin);
    check(Closing::find($closing)['tipo']==='venta' && (int)Reservation::find($reservation)['propiedad_id']===$properties[2],'cierre utiliza el inmueble seleccionado y la operación correcta');
    check(Lead::find($lead)['etapa']==='cerrado' && Property::find($properties[2])['disponibilidad']==='vendido' && !Property::find($properties[2])['publicado'],'cierre sincroniza consulta, reserva e inventario');
    check(Opportunity::find($won,$seller)['etapa']==='ganada' && Opportunity::find($alternative,$seller)['etapa']==='cancelada','cierre gana la alternativa elegida y cancela la otra');
    denied(fn()=>Lead::assignProperty($lead,$properties[0],$admin),'consulta cerrada conserva su inmueble');
} finally {
    if ($lead) $db->prepare('DELETE FROM oportunidades WHERE lead_id=?')->execute([$lead]);
    foreach ($properties as $id) {
        $db->prepare('DELETE c FROM cierres c JOIN reservas r ON r.id=c.reserva_id WHERE r.propiedad_id=?')->execute([$id]);
        $db->prepare('DELETE FROM reservas WHERE propiedad_id=?')->execute([$id]);
        $db->prepare('DELETE FROM visitas WHERE propiedad_id=?')->execute([$id]);
    }
    if ($lead) $db->prepare('DELETE FROM leads WHERE id=?')->execute([$lead]);
    foreach ($properties as $id) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$id]);
    if ($client) $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$client]);
    foreach ($users as $id) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
}
