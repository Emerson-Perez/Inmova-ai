<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
// Regresión de reglas comerciales. Solo elimina sus propios datos temporales.
function check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function rejected(callable $action, string $label): void {
    try { $action(); } catch (DomainException $error) { check(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
spl_autoload_register(function ($class) {
    foreach (['core', 'models'] as $dir) {
        $path = __DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($path)) { require $path; return; }
    }
});
$db = Database::connect();
$property = $client = $seller = $lead = null;
try {
    $admin = ['id'=>(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' AND u.estado=1 LIMIT 1")->fetchColumn(), 'rol'=>'superadmin'];
    if (!$admin['id']) throw new RuntimeException('Se requiere superadministrador activo.');
    $role = $db->query("SELECT id FROM roles WHERE nombre='vendedor'")->fetchColumn();
    $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Auditoria',?,?)")->execute([$role,bin2hex(random_bytes(10)).'@example.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
    $seller = (int)$db->lastInsertId();
    $db->exec("INSERT INTO clientes (nombres) VALUES ('QA Auditoria temporal')");
    $client = (int)$db->lastInsertId();
    $type = $db->query('SELECT id FROM tipos_propiedad WHERE estado=1 LIMIT 1')->fetchColumn();
    $mode = $db->query("SELECT id FROM modalidades WHERE slug='venta-deptos' AND estado=1 LIMIT 1")->fetchColumn();
    $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda) VALUES (?,?,?,'QA Auditoria temporal',10000,'PEN')")->execute([$type,$mode,$admin['id']]);
    $property = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO leads (cliente_id,propiedad_id,vendedor_id,etapa) VALUES (?,?,?,'nuevo')")->execute([$client,$property,$seller]);
    $lead = (int)$db->lastInsertId();
    rejected(fn()=>Lead::update($lead,$admin,'cerrado',$seller,'','nota',null),'no cerrar sin operación');
    rejected(fn()=>Lead::update($lead,$admin,'reservado',$seller,'','nota',null),'no reservar sin aprobación');
    check(Lead::find($lead)['etapa']==='nuevo' && count(Lead::history($lead))===0,'rechazos conservan etapa e historial');
    Lead::update($lead,$admin,'negociacion',$seller,'','nota',null);
    $reservation = Reservation::create($lead,$seller,100,$admin);
    Reservation::transition($reservation,'aprobada','Auditoria',$admin);
    foreach (['perdido','nuevo','negociacion','cerrado'] as $stage) rejected(fn()=>Lead::update($lead,$admin,$stage,$seller,'','nota',null),'reserva aprobada bloquea etapa '.$stage);
    check(Lead::update($lead,$admin,'reservado',$seller,'Seguimiento permitido','nota',null),'permite seguimiento sin cambiar reserva');
    check(Lead::find($lead)['etapa']==='reservado' && Reservation::find($reservation)['estado_reserva']==='aprobada' && Property::find($property)['disponibilidad']==='reservado','estados de reserva siguen coherentes');
    Reservation::transition($reservation,'cancelada','Auditoria',$admin);
    check(Lead::find($lead)['etapa']==='negociacion' && Property::find($property)['disponibilidad']==='disponible','cancelar restaura etapa y disponibilidad');
    $data=['vendedor_id'=>$seller,'modalidad'=>'presencial','fecha_hora'=>(new DateTimeImmutable('tomorrow',new DateTimeZone('America/Lima')))->format('Y-m-d 15:00:00'),'estado_visita'=>'confirmada','resultado'=>''];
    $visit=Visit::save($data,$admin,null,$lead);
    check($visit>0,'consulta abierta permite visita');
    Lead::update($lead,$admin,'perdido',$seller,'','nota',null);
    rejected(fn()=>Visit::save($data,$admin,null,$lead),'consulta perdida rechaza visita nueva');
    $data['estado_visita']='cancelada';
    check(Visit::save($data,$admin,$visit,null)===$visit,'permite regularizar visita histórica de consulta perdida');
    Lead::update($lead,$admin,'negociacion',$seller,'','nota',null);
    foreach (['vendido','alquilado'] as $availability) {
        $db->prepare('UPDATE propiedades SET disponibilidad=? WHERE id=?')->execute([$availability,$property]);
        rejected(fn()=>Visit::save($data,$admin,null,$lead),'inmueble '.$availability.' rechaza visita nueva');
    }
    $db->prepare("UPDATE propiedades SET disponibilidad='disponible' WHERE id=?")->execute([$property]);
    $reservation=Reservation::create($lead,$seller,100,$admin);
    Reservation::transition($reservation,'aprobada','Auditoria',$admin);
    Closing::create($reservation,10000,(new DateTimeImmutable('today',new DateTimeZone('America/Lima')))->format('Y-m-d'),'Auditoria',$admin);
    check(Lead::update($lead,$admin,'cerrado',$seller,'Seguimiento posterior','nota',null),'permite seguimiento después del cierre');
    rejected(fn()=>Visit::save($data,$admin,null,$lead),'consulta cerrada rechaza visita nueva');
} finally {
    if ($property) {
        $db->prepare('DELETE c FROM cierres c JOIN reservas r ON r.id=c.reserva_id WHERE r.propiedad_id=?')->execute([$property]);
        $db->prepare('DELETE FROM visitas WHERE propiedad_id=?')->execute([$property]);
        $db->prepare('DELETE FROM reservas WHERE propiedad_id=?')->execute([$property]);
    }
    if ($lead) $db->prepare('DELETE FROM leads WHERE id=?')->execute([$lead]);
    if ($property) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$property]);
    if ($client) $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$client]);
    if ($seller) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$seller]);
}
