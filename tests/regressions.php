<?php
// Datos propios y limpieza garantizada; las advertencias también hacen fallar la prueba.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
set_error_handler(function ($severity,$message,$file,$line) { throw new ErrorException($message,0,$severity,$file,$line); });
$failures=[];
function verify(bool $ok, string $label): void {
    global $failures;
    echo ($ok ? 'OK: ' : 'FAIL: ').$label.PHP_EOL;
    if (!$ok) $failures[]=$label;
}
function denied(callable $action, string $label): void {
    try { $action(); verify(false,$label); }
    catch (DomainException $error) { verify(true,$label); }
    catch (Throwable $error) { verify(false,$label.' ('.get_class($error).')'); }
}
$db=Database::connect(); $property=$client=$seller=$lead=null;
try {
    verify(User::passwordError("QaPassword123\0hidden")!==null,'contraseña con byte nulo se rechaza antes del hash');
    denied(fn()=>Report::period("2026-10\0-01",'2026-10-28'),'fecha de reporte malformada produce error de validación');
    denied(fn()=>Closing::create(0,100,"2026-10\0-28",'QA',['id'=>0,'rol'=>'admin']),'fecha de cierre malformada produce error de validación');
    $_FILES=['photos'=>['error'=>[UPLOAD_ERR_OK]]];
    try { PropertyPhotos::receive(); verify(false,'carga de foto incompleta rechazada sin advertencias'); }
    catch (RuntimeException $error) { verify(true,'carga de foto incompleta rechazada sin advertencias'); }
    catch (Throwable $error) { verify(false,'carga de foto incompleta emite '.get_class($error)); }
    $_FILES=[];
    $admin=['id'=>(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' AND u.estado=1 LIMIT 1")->fetchColumn(),'rol'=>'superadmin'];
    if (!$admin['id']) throw new RuntimeException('Falta superadministrador local.');
    $role=$db->query("SELECT id FROM roles WHERE nombre='vendedor'")->fetchColumn();
    $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Regresion',?,?)")->execute([$role,bin2hex(random_bytes(10)).'@example.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
    $seller=(int)$db->lastInsertId();
    $db->exec("INSERT INTO clientes (nombres) VALUES ('QA regresion temporal')"); $client=(int)$db->lastInsertId();
    $type=$db->query('SELECT id FROM tipos_propiedad WHERE estado=1 LIMIT 1')->fetchColumn();
    $mode=$db->query("SELECT id FROM modalidades WHERE slug='venta-deptos' AND estado=1 LIMIT 1")->fetchColumn();
    $rent=$db->query("SELECT id FROM modalidades WHERE slug='alquiler' AND estado=1 LIMIT 1")->fetchColumn();
    $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda) VALUES (?,?,?,'QA regresion temporal',10000,'PEN')")->execute([$type,$mode,$admin['id']]); $property=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO leads (cliente_id,propiedad_id,vendedor_id,etapa) VALUES (?,?,?,'negociacion')")->execute([$client,$property,$seller]); $lead=(int)$db->lastInsertId();
    $reservation=Reservation::create($lead,$seller,100,$admin);
    Reservation::transition($reservation,'aprobada','QA',$admin);
    $data=Property::find($property); $data['modalidad_id']=$rent; $data['departamento']='';
    denied(fn()=>Property::save($data,$property,$admin['id']),'reserva aprobada protege modalidad de operación');
    $db->prepare('UPDATE propiedades SET modalidad_id=? WHERE id=?')->execute([$mode,$property]);
    Reservation::transition($reservation,'cancelada','QA',$admin);
    $visitData=['vendedor_id'=>$seller,'fecha_hora'=>(new DateTimeImmutable('tomorrow',new DateTimeZone('America/Lima')))->format('Y-m-d 15:00:00'),'modalidad'=>'presencial','estado_visita'=>'confirmada','resultado'=>''];
    $visit=Visit::save($visitData,$admin,null,$lead);
    $cancelled=$visitData; $cancelled['estado_visita']='cancelada';
    Visit::save($cancelled,$admin,$visit,null);
    Lead::update($lead,$admin,'perdido',$seller,'','nota',null);
    denied(fn()=>Visit::save($visitData,$admin,$visit,null),'consulta perdida impide reactivar visita antigua');
    Visit::save($cancelled,$admin,$visit,null);
    Lead::update($lead,$admin,'negociacion',$seller,'','nota',null);
    foreach (['vendido','alquilado'] as $availability) {
        $db->prepare('UPDATE propiedades SET disponibilidad=? WHERE id=?')->execute([$availability,$property]);
        denied(fn()=>Visit::save($visitData,$admin,$visit,null),'inmueble '.$availability.' impide reactivar visita antigua');
        Visit::save($cancelled,$admin,$visit,null);
    }
    $db->prepare("UPDATE propiedades SET disponibilidad='disponible' WHERE id=?")->execute([$property]);
    $data=Property::find($property); $data['departamento']='';
    $db->prepare('UPDATE propiedades SET estado=0 WHERE id=?')->execute([$property]);
    denied(fn()=>Property::save($data,$property,$admin['id']),'edición obsoleta de inmueble desactivado rechazada');
} finally {
    $_FILES=[];
    if ($property) {
        $db->prepare('DELETE FROM visitas WHERE propiedad_id=?')->execute([$property]);
        $db->prepare('DELETE FROM reservas WHERE propiedad_id=?')->execute([$property]);
    }
    if ($lead) $db->prepare('DELETE FROM leads WHERE id=?')->execute([$lead]);
    if ($property) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$property]);
    if ($client) $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$client]);
    if ($seller) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$seller]);
    restore_error_handler();
}
exit($failures ? 1 : 0);
