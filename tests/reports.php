<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function checkReport(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function invalidPeriod(mixed $from, mixed $to): void {
    try { Report::period($from,$to); } catch (DomainException $error) { checkReport(true,'rango inválido rechazado'); return; }
    throw new RuntimeException('FAIL: rango inválido aceptado');
}
$db=Database::connect(); $users=[]; $properties=[]; $leads=[]; $clientId=null;
try {
    $adminId=(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' LIMIT 1")->fetchColumn();
    if (!$adminId) throw new RuntimeException('Se requiere un superadministrador local.');
    $admin=['id'=>$adminId,'rol'=>'superadmin'];
    $period=Report::period('2001-02-03','2001-02-03');
    $baseline=Report::summary($admin,$period);
    $outside=Report::period('2001-02-04','2001-02-04');
    $outsideBaseline=Report::summary($admin,$outside);
    $role=(int)$db->query("SELECT id FROM roles WHERE nombre='vendedor'")->fetchColumn();
    $type=(int)$db->query('SELECT id FROM tipos_propiedad LIMIT 1')->fetchColumn();
    $modes=$db->query("SELECT id,slug FROM modalidades WHERE slug IN ('terrenos','alquiler')")->fetchAll();
    $modes=array_column($modes,'id','slug');
    $db->exec("INSERT INTO clientes (nombres) VALUES ('QA Reporte temporal')"); $clientId=(int)$db->lastInsertId();
    foreach ([1,2] as $number) {
        $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Reporte',?,?)")->execute([$role,bin2hex(random_bytes(8)).'@inmova.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
        $users[]=(int)$db->lastInsertId(); $sellerId=end($users);
        foreach (['venta','alquiler'] as $operation) foreach (['PEN','USD'] as $currency) {
            $amount=$number*1000;
            $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda,disponibilidad) VALUES (?,?,?,'QA Reporte temporal',?,?,?)")->execute([$type,$modes[$operation==='venta' ? 'terrenos' : 'alquiler'],$sellerId,$amount,$currency,$operation==='venta' ? 'vendido' : 'alquilado']);
            $properties[]=(int)$db->lastInsertId(); $propertyId=end($properties);
            $db->prepare("INSERT INTO leads (cliente_id,propiedad_id,vendedor_id,etapa,created_at) VALUES (?,?,?,'cerrado','2001-02-03 23:59:59')")->execute([$clientId,$propertyId,$sellerId]);
            $leads[]=(int)$db->lastInsertId(); $leadId=end($leads);
            $db->prepare("INSERT INTO visitas (lead_id,propiedad_id,cliente_id,vendedor_id,fecha_hora,estado_visita) VALUES (?,?,?,?,'2001-02-03 23:59:00','realizada')")->execute([$leadId,$propertyId,$clientId,$sellerId]);
            $db->prepare("INSERT INTO reservas (lead_id,propiedad_id,cliente_id,vendedor_id,monto_reserva,moneda,estado_reserva,created_at) VALUES (?,?,?,?,100,?,'cerrada','2001-02-03 23:59:59')")->execute([$leadId,$propertyId,$clientId,$sellerId,$currency]);
            $reservationId=(int)$db->lastInsertId();
            $db->prepare("INSERT INTO cierres (reserva_id,registrado_por,tipo,monto_final,moneda,fecha_cierre,observaciones) VALUES (?,?,?,?,?,'2001-02-03','QA Reporte temporal')")->execute([$reservationId,$adminId,$operation,$amount,$currency]);
        }
    }
    foreach ($users as $index=>$sellerId) {
        $report=Report::summary(['id'=>$sellerId,'rol'=>'vendedor'],$period);
        checkReport($report['totals']['Consultas recibidas']===4 && $report['totals']['Visitas en el período']===4 && $report['totals']['Reservas solicitadas']===4 && $report['totals']['Operaciones cerradas']===4,'vendedor solo ve sus registros; fin del día incluido');
        checkReport($report['totals']['Propiedades activas']===4 && count($report['recent'])===4,'inventario y cierres limitados al vendedor');
        checkReport(count($report['amounts'])===4,'venta/alquiler y PEN/USD se agrupan por separado');
        foreach ($report['amounts'] as $row) checkReport((int)$row['total']===1 && (float)$row['monto']===(float)(($index+1)*1000),'total por operación y moneda correcto');
        $none=Report::summary(['id'=>$sellerId,'rol'=>'vendedor'],$outside);
        checkReport($none['totals']['Operaciones cerradas']===0 && $none['totals']['Consultas recibidas']===0 && $none['totals']['Propiedades activas']===4,'rango excluye actividad pero conserva inventario actual');
        $csv=Report::csvRows($report,$period);
        checkReport(count(array_filter($csv,fn($row)=>$row[0]==='Cierres'))===4,'CSV conserva las cuatro agrupaciones monetarias');
    }
    $all=Report::summary($admin,$period);
    foreach ($all['totals'] as $key=>$total) checkReport($total-$baseline['totals'][$key]===8,'administrador incluye ambos vendedores: '.$key);
    $outsideReport=Report::summary($admin,$outside);
    checkReport($outsideReport['totals']['Operaciones cerradas']===$outsideBaseline['totals']['Operaciones cerradas'],'cierres fuera del rango excluidos');
    invalidPeriod('2026-02-30','2026-03-01'); invalidPeriod('2026-10-28','2026-10-01'); invalidPeriod('2020-01-01','2026-01-01'); invalidPeriod(['2026-10-01'],'2026-10-27');
    try { Report::summary(['id'=>$users[0],'rol'=>'cliente'],$period); throw new RuntimeException('FAIL: cliente accede a reporte'); } catch (DomainException $error) { checkReport(true,'rol cliente sin acceso'); }
} finally {
    foreach ($properties as $id) {
        $db->prepare('DELETE c FROM cierres c JOIN reservas r ON r.id=c.reserva_id WHERE r.propiedad_id=?')->execute([$id]);
        $db->prepare('DELETE FROM reservas WHERE propiedad_id=?')->execute([$id]);
        $db->prepare('DELETE FROM visitas WHERE propiedad_id=?')->execute([$id]);
    }
    foreach ($leads as $id) $db->prepare('DELETE FROM leads WHERE id=?')->execute([$id]);
    foreach ($properties as $id) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$id]);
    if ($clientId) $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$clientId]);
    foreach ($users as $id) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
}
