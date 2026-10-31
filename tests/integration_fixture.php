<?php
// Soporte CLI: crea únicamente la cuenta de arranque de la prueba y lee sus registros DEMO.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../app/core/Database.php';
$data=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$run=$data['run'] ?? '';
if (!is_string($run) || !preg_match('/^demo_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}$/D',$run)) throw new RuntimeException('Identificador de prueba inválido.');
$db=Database::connect();
function rows(PDO $db, string $sql, array $params=[]): array { $st=$db->prepare($sql); $st->execute($params); return $st->fetchAll(); }
$emails=['admin.'.$run.'@inmova.test','vendedora.'.$run.'@inmova.test','externo.'.$run.'@inmova.test'];
$clients=['ana.'.$run.'@example.test','luis.'.$run.'@example.test'];
$titles=['DEMO '.$run.' | Venta completada Miraflores','DEMO '.$run.' | Practicar flujo Miraflores'];
$action=$argv[1] ?? '';
if ($action==='bootstrap') {
    $password=$data['password'] ?? '';
    if (!is_string($password) || strlen($password)<16) throw new RuntimeException('Falta contraseña temporal segura.');
    $role=$db->query("SELECT id FROM roles WHERE nombre='admin' AND estado=1")->fetchColumn();
    if (!$role) throw new RuntimeException('No hay rol administrador activo.');
    $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'Administrador DEMO','Integración',?,?)")->execute([$role,$emails[0],password_hash($password,PASSWORD_DEFAULT)]);
    $result=['admin_id'=>(int)$db->lastInsertId(),'admin_email'=>$emails[0],
        'roles'=>array_column(rows($db,'SELECT id,nombre FROM roles WHERE estado=1'),'id','nombre'),
        'types'=>array_column(rows($db,'SELECT id,nombre FROM tipos_propiedad WHERE estado=1'),'id','nombre'),
        'modes'=>array_column(rows($db,'SELECT id,slug FROM modalidades WHERE estado=1'),'id','slug'),
        'practice_seller'=>rows($db,"SELECT u.id,CONCAT(u.nombres,' ',u.apellidos) AS nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.estado=1 AND r.estado=1 AND r.nombre='vendedor' AND u.email NOT LIKE '%@example.test' ORDER BY (u.nombres='Alexa' AND u.apellidos='Alata') DESC,u.id LIMIT 1")];
} elseif ($action==='snapshot') {
    $result=[
        'users'=>rows($db,'SELECT u.id,u.nombres,u.apellidos,u.email,u.estado,u.password_change_required,r.nombre AS rol FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.email IN (?,?,?)',$emails),
        'properties'=>rows($db,'SELECT id,titulo,precio,moneda,disponibilidad,publicado,creado_por FROM propiedades WHERE titulo IN (?,?) ORDER BY id',$titles),
        'leads'=>rows($db,'SELECT l.id,l.cliente_id,l.propiedad_id,l.vendedor_id,l.etapa,c.nombres,c.email FROM leads l JOIN clientes c ON c.id=l.cliente_id WHERE c.email IN (?,?) ORDER BY l.id',$clients),
        'opportunities'=>rows($db,'SELECT o.id,o.lead_id,o.propiedad_id,o.etapa,o.monto_estimado,o.probabilidad,o.moneda,o.cierre_id FROM oportunidades o JOIN leads l ON l.id=o.lead_id JOIN clientes c ON c.id=l.cliente_id WHERE c.email IN (?,?) ORDER BY o.id',$clients),
        'visits'=>rows($db,'SELECT v.id,v.lead_id,v.fecha_hora,v.estado_visita,v.resultado FROM visitas v JOIN clientes c ON c.id=v.cliente_id WHERE c.email IN (?,?) ORDER BY v.id',$clients),
        'reservations'=>rows($db,'SELECT r.id,r.lead_id,r.estado_reserva,r.monto_reserva,r.moneda FROM reservas r JOIN clientes c ON c.id=r.cliente_id WHERE c.email IN (?,?) ORDER BY r.id',$clients),
        'closings'=>rows($db,'SELECT ce.id,ce.reserva_id,ce.monto_final,ce.moneda,ce.fecha_cierre FROM cierres ce JOIN reservas r ON r.id=ce.reserva_id JOIN clientes c ON c.id=r.cliente_id WHERE c.email IN (?,?) ORDER BY ce.id',$clients),
        'tasks'=>rows($db,'SELECT s.id,s.lead_id,s.detalle,s.proxima_accion,s.tarea_completada_at FROM seguimientos s JOIN leads l ON l.id=s.lead_id JOIN clientes c ON c.id=l.cliente_id WHERE c.email IN (?,?) AND s.proxima_accion IS NOT NULL ORDER BY s.id',$clients),
        'documents'=>rows($db,'SELECT d.id,d.propiedad_id,d.titulo,d.estado,d.storage_name FROM propiedad_documentos d JOIN propiedades p ON p.id=d.propiedad_id WHERE p.titulo IN (?,?) ORDER BY d.id',$titles),
        'photos'=>rows($db,"SELECT m.id,m.propiedad_id,m.es_principal,m.ruta FROM propiedad_multimedia m JOIN propiedades p ON p.id=m.propiedad_id WHERE p.titulo IN (?,?) AND m.tipo='foto' AND m.estado=1 ORDER BY m.id",$titles),
    ];
} elseif ($action==='hide_incomplete_properties') {
    $leadCount=rows($db,'SELECT COUNT(*) AS total FROM leads l JOIN propiedades p ON p.id=l.propiedad_id WHERE p.titulo IN (?,?)',$titles);
    if ((int)$leadCount[0]['total']!==0) throw new RuntimeException('El caso ya tiene consultas; requiere revisión antes de ocultarlo.');
    $db->prepare('UPDATE propiedades SET estado=0,publicado=0 WHERE titulo IN (?,?)')->execute($titles);
    $result=['hidden'=>true];
} elseif ($action==='secure_accounts') {
    // Solo cuentas creadas por esta ejecución; los registros comerciales de demostración se conservan.
    $db->prepare('UPDATE usuarios SET estado=0,auth_version=auth_version+1 WHERE email IN (?,?,?)')->execute($emails);
    $result=['secured'=>true];
} else throw new RuntimeException('Acción de prueba no válida.');
$result['now_lima']=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
