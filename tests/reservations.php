<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
// Ejecutar con PHP CLI sobre la base local configurada. Solo limpia los registros creados aquí.
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function rejected(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException $error) { check(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
$db=Database::connect(); $propertyId=null; $clientId=null; $sellerId=null; $leads=[]; $propertyIds=[];
try {
    $adminId=(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' LIMIT 1")->fetchColumn();
    if (!$adminId) throw new RuntimeException('Se requiere un superadministrador local.');
    $admin=['id'=>$adminId,'rol'=>'superadmin'];
    $role=(int)$db->query("SELECT id FROM roles WHERE nombre='vendedor'")->fetchColumn();
    $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Reservas',?,?)")->execute([$role,bin2hex(random_bytes(8)).'@inmova.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
    $sellerId=(int)$db->lastInsertId(); $seller=['id'=>$sellerId,'rol'=>'vendedor'];
    $db->exec("INSERT INTO clientes (nombres) VALUES ('QA Reserva temporal')"); $clientId=(int)$db->lastInsertId();
    $type=(int)$db->query('SELECT id FROM tipos_propiedad LIMIT 1')->fetchColumn();
    $mode=(int)$db->query("SELECT id FROM modalidades WHERE slug='terrenos' LIMIT 1")->fetchColumn();
    $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda) VALUES (?,?,?,'QA Reserva temporal',10000,'USD')")->execute([$type,$mode,$adminId]); $propertyId=(int)$db->lastInsertId();
    $propertyIds[]=$propertyId;
    foreach (range(1,2) as $unused) {
        $db->prepare("INSERT INTO leads (cliente_id,propiedad_id,vendedor_id,etapa) VALUES (?,?,?,'negociacion')")->execute([$clientId,$propertyId,$sellerId]); $leads[]=(int)$db->lastInsertId();
    }
    rejected(fn()=>Reservation::create($leads[0],$sellerId,10001,$admin),'monto no supera el precio');
    $first=Reservation::create($leads[0],$sellerId,100,$seller);
    check(Reservation::find($first)['moneda']==='USD','moneda del inmueble conservada');
    rejected(fn()=>Reservation::create($leads[0],$sellerId,100,$seller),'no duplicar solicitud activa del mismo lead');
    $second=Reservation::create($leads[1],$sellerId,100,$admin);
    $filtered=Reservation::listing($admin,'',$propertyId);
    check(count($filtered)===2 && count(array_filter($filtered,fn($row)=>(int)$row['propiedad_id']!==$propertyId))===0,'listado filtra reservas por inmueble');
    check(Reservation::listing(['id'=>0,'rol'=>'vendedor'],'',$propertyId)===[],'filtro de inmueble conserva permisos del vendedor');
    rejected(fn()=>Reservation::transition($first,'aprobada','QA',$seller),'vendedor no aprueba');
    Reservation::transition($first,'aprobada','QA aprobación',$admin);
    check(Property::find($propertyId)['disponibilidad']==='reservado' && Lead::find($leads[0])['etapa']==='reservado','aprobación actualiza inmueble y CRM');
    rejected(fn()=>Reservation::transition($second,'aprobada','QA',$admin),'segunda aprobación bloqueada');
    rejected(fn()=>Property::delete($propertyId),'eliminación de inmueble reservado bloqueada');
    $data=Property::find($propertyId); $data['disponibilidad']='disponible'; $data['departamento']='';
    rejected(fn()=>Property::save($data,$propertyId,$adminId),'edición no libera reserva aprobada');
    rejected(fn()=>Reservation::transition($first,'cancelada','QA',$seller),'vendedor no cancela aprobada');
    Reservation::transition($first,'cancelada','QA cancelación',$admin);
    check(Property::find($propertyId)['disponibilidad']==='disponible' && Lead::find($leads[0])['etapa']==='negociacion','cancelación libera y restaura etapa anterior');
    rejected(fn()=>Reservation::transition($first,'aprobada','QA',$admin),'reserva cancelada no se reactiva');
    Reservation::transition($second,'rechazada','QA rechazo',$admin);
    check(Reservation::find($second)['estado_reserva']==='rechazada','rechazo registrado');
    $third=Reservation::create($leads[1],$sellerId,100,$seller);
    Reservation::transition($third,'cancelada','QA cancelación pendiente',$seller);
    check(Reservation::find($third)['estado_reserva']==='cancelada','vendedor cancela su pendiente');
    check(count(Lead::history($leads[0]))>=3,'historial de cambios conservado');
    Property::delete($propertyId);
    check(Property::find($propertyId)===null,'cancelar reservas permite eliminar el inmueble del listado');
    $db->prepare('UPDATE propiedades SET estado=1 WHERE id=?')->execute([$propertyId]);
    foreach (['venta','alquiler'] as $operation) {
        if ($operation==='alquiler') {
            $rentMode=(int)$db->query("SELECT id FROM modalidades WHERE slug='alquiler'")->fetchColumn();
            $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda,publicado) VALUES (?,?,?,'QA Cierre alquiler',10000,'PEN',1)")->execute([$type,$rentMode,$adminId]); $propertyId=(int)$db->lastInsertId(); $propertyIds[]=$propertyId;
        }
        $caseLeads=[];
        foreach (range(1,2) as $unused) {
            $db->prepare("INSERT INTO leads (cliente_id,propiedad_id,vendedor_id,etapa) VALUES (?,?,?,'negociacion')")->execute([$clientId,$propertyId,$sellerId]); $caseLeads[]=(int)$db->lastInsertId(); $leads[]=end($caseLeads);
        }
        $approved=Reservation::create($caseLeads[0],$sellerId,100,$admin);
        $pending=Reservation::create($caseLeads[1],$sellerId,100,$admin);
        $today=(new DateTimeImmutable('today',new DateTimeZone('America/Lima')))->format('Y-m-d');
        rejected(fn()=>Closing::create($approved,10000,$today,'QA',$admin),'no cerrar reserva pendiente');
        Reservation::transition($approved,'aprobada','QA',$admin);
        rejected(fn()=>Closing::create($approved,10000,$today,'QA',$seller),'vendedor no confirma cierre');
        rejected(fn()=>Closing::create($approved,99,$today,'QA',$admin),'cierre no inferior a reserva');
        rejected(fn()=>Closing::create($approved,10000,'2099-01-01','QA',$admin),'cierre no futuro');
        $closing=Closing::create($approved,10000,$today,'QA cierre '.$operation,$admin);
        $record=Closing::find($closing);
        check($record['tipo']===$operation && $record['moneda']===($operation==='venta' ? 'USD' : 'PEN'),'cierre '.$operation.' guarda tipo y moneda');
        check(Reservation::find($approved)['estado_reserva']==='cerrada' && Lead::find($caseLeads[0])['etapa']==='cerrado','cierre actualiza reserva y CRM');
        $p=Property::find($propertyId);
        check($p['disponibilidad']===($operation==='venta' ? 'vendido' : 'alquilado') && !$p['publicado'],'cierre actualiza inmueble y retira del portal');
        check(Reservation::find($pending)['estado_reserva']==='rechazada','otras solicitudes pendientes rechazadas');
        rejected(fn()=>Closing::create($approved,10000,$today,'QA',$admin),'cierre duplicado bloqueado');
        rejected(fn()=>Reservation::transition($approved,'cancelada','QA',$admin),'reserva cerrada no se cancela');
        rejected(fn()=>Property::delete($propertyId),'inmueble cerrado no se elimina');
        $p['departamento']=''; $p['disponibilidad']='disponible';
        rejected(fn()=>Property::save($p,$propertyId,$adminId),'inmueble cerrado no vuelve a disponible');
        rejected(fn()=>Lead::update($caseLeads[0],$admin,'nuevo',$sellerId,'','nota',null),'consulta cerrada no se reabre');
    }
} finally {
    foreach ($propertyIds as $cleanupId) {
        $db->prepare('DELETE c FROM cierres c JOIN reservas r ON r.id=c.reserva_id WHERE r.propiedad_id=?')->execute([$cleanupId]);
        $db->prepare('DELETE FROM reservas WHERE propiedad_id=?')->execute([$cleanupId]);
    }
    foreach ($leads as $leadId) $db->prepare('DELETE FROM leads WHERE id=?')->execute([$leadId]);
    foreach ($propertyIds as $cleanupId) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$cleanupId]);
    if ($clientId) $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$clientId]);
    if ($sellerId) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$sellerId]);
}
