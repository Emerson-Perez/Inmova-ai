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
$db=Database::connect(); $db->beginTransaction();
try {
    $admin=['id'=>(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' AND u.estado=1 LIMIT 1")->fetchColumn(),'rol'=>'superadmin'];
    if (!$admin['id']) throw new RuntimeException('Falta administrador local.');
    $role=$db->query("SELECT id FROM roles WHERE nombre='vendedor'")->fetchColumn();
    $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Filtros',?,?)")->execute([$role,bin2hex(random_bytes(10)).'@example.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
    $seller=['id'=>(int)$db->lastInsertId(),'rol'=>'vendedor'];
    $mode=(string)$db->query("SELECT id FROM modalidades WHERE slug='venta-deptos' AND estado=1 LIMIT 1")->fetchColumn();
    $rent=(string)$db->query("SELECT id FROM modalidades WHERE slug='alquiler' AND estado=1 LIMIT 1")->fetchColumn();
    $type=$db->query('SELECT id FROM tipos_propiedad WHERE estado=1 LIMIT 1')->fetchColumn();
    $marker='QA FILTROS '.bin2hex(random_bytes(6)); $ids=[];
    foreach ([[$mode,100000,'PEN','disponible',1,$seller['id'],1],[$mode,200000,'PEN','reservado',0,$seller['id'],1],[$rent,1500,'PEN','disponible',1,$admin['id'],1],[$mode,100000,'USD','vendido',0,$seller['id'],1],[$rent,2000,'PEN','disponible',1,$seller['id'],0]] as [$m,$price,$currency,$availability,$published,$owner,$active]) {
        $db->prepare('INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda,disponibilidad,publicado,estado) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$type,$m,$owner,$marker,$price,$currency,$availability,$published,$active]);
        $ids[]=(int)$db->lastInsertId();
    }
    $find=fn($filters=[],$actor=null)=>array_map('intval',array_column(Property::listing($actor ?? $admin,$marker,$filters),'id'));
    $same=function ($actual,$expected) { sort($actual); sort($expected); return $actual===$expected; };
    check($same($find(['modalidad_id'=>$rent]),[$ids[2]]),'modalidad filtra e ignora inmuebles desactivados');
    check($same($find(['min'=>'100000','max'=>'100000','moneda'=>'PEN']),[$ids[0]]),'rango inclusivo y moneda separan soles de dólares');
    check($same($find(['disponibilidad'=>'reservado']),[$ids[1]]),'disponibilidad filtra reservas');
    check($same($find(['publicado'=>'0']),[$ids[1],$ids[3]]),'publicación Borrador conserva valor cero');
    check($same($find(['publicado'=>'1']),[$ids[0],$ids[2]]),'publicación Publicada filtra correctamente');
    check($same($find(['modalidad_id'=>$mode,'min'=>'90000','max'=>'110000','moneda'=>'PEN','disponibilidad'=>'disponible','publicado'=>'1']),[$ids[0]]),'todos los filtros se combinan con título');
    check($same($find([],$seller),[$ids[0],$ids[1],$ids[3]]),'vendedor solo obtiene sus propiedades');
    check($find(['modalidad_id'=>$rent],$seller)===[],'filtros no conceden acceso al inventario ajeno');
    check($same($find(),array_slice($ids,0,4)),'limpiar filtros recupera inventario activo visible');
    foreach ([['min'=>'1'],['min'=>'200','max'=>'100','moneda'=>'PEN'],['min'=>'1.50','moneda'=>'PEN'],['max'=>'5000001','moneda'=>'PEN'],['publicado'=>'2'],['disponibilidad'=>'otro'],['modalidad_id'=>'1 OR 1=1'],['moneda'=>'EUR'],['min'=>['1']]] as $invalid) denied(fn()=>$find($invalid),'filtros inválidos rechazados: '.json_encode($invalid));
} finally { $db->rollBack(); }
