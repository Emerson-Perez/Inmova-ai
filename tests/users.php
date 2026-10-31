<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ob_start();
session_start();
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function checkUser(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function deniedUser(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException $error) { checkUser(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
$db=Database::connect(); $ids=[];
try {
    $adminId=(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' AND u.estado=1 LIMIT 1")->fetchColumn();
    if (!$adminId) throw new RuntimeException('Se requiere superadministrador local.');
    $admin=['id'=>$adminId,'rol'=>'superadmin'];
    $roles=$db->query('SELECT id,nombre FROM roles')->fetchAll(); $roles=array_column($roles,'id','nombre');
    $password='QaTempAccess123';
    $data=['rol_id'=>$roles['vendedor'],'nombres'=>'QA','apellidos'=>'Accesos','email'=>bin2hex(random_bytes(8)).'@inmova.test','telefono'=>'999999999','estado'=>1];
    $id=User::save($data,null,$admin,$password); $ids[]=$id;
    $saved=User::find($id);
    checkUser(password_verify($password,$saved['password_hash']) && (int)$saved['password_change_required']===1,'nueva cuenta guarda hash y exige cambio');
    deniedUser(fn()=>User::save($data,null,$admin,$password),'correo duplicado rechazado');
    checkUser(Auth::attempt($data['email'],$password),'login de cuenta nueva');
    checkUser(Auth::user()['password_change_required']===true,'sesión recuerda cambio obligatorio');
    $version=Auth::user()['auth_version'];
    deniedUser(fn()=>User::changePassword($id,$version,'incorrecta','QaNewAccess456'),'cambio exige contraseña actual');
    deniedUser(fn()=>User::changePassword($id,$version,$password,'short'),'contraseña débil rechazada');
    User::changePassword($id,$version,$password,'QaNewAccess456');
    Auth::refresh(); checkUser(!Auth::check(),'cambio invalida sesión anterior');
    checkUser(!Auth::attempt($data['email'],$password) && Auth::attempt($data['email'],'QaNewAccess456'),'solo funciona contraseña nueva');
    checkUser(!Auth::user()['password_change_required'],'cambio propio completa cambio obligatorio');
    $data['estado']=0; User::save($data,$id,$admin,'');
    Auth::refresh(); checkUser(!Auth::check() && !Auth::attempt($data['email'],'QaNewAccess456'),'desactivación revoca sesión y login');
    $data['estado']=1; User::save($data,$id,$admin,'');
    checkUser(Auth::attempt($data['email'],'QaNewAccess456'),'reactivación conserva contraseña');
    $data['rol_id']=$roles['cliente']; User::save($data,$id,$admin,'');
    Auth::refresh(); checkUser(!Auth::check(),'cambio de rol invalida sesión');
    checkUser(Auth::attempt($data['email'],'QaNewAccess456') && Auth::user()['rol']==='cliente','rol se actualiza al ingresar');
    User::save($data,$id,$admin,'QaResetAccess789');
    Auth::refresh(); checkUser(!Auth::check(),'restablecimiento invalida sesiones');
    checkUser(Auth::attempt($data['email'],'QaResetAccess789') && Auth::user()['password_change_required'],'restablecimiento exige cambiar contraseña');
    $limited=$data; $limited['email']=bin2hex(random_bytes(8)).'@inmova.test'; $limited['rol_id']=$roles['admin'];
    $limitedId=User::save($limited,null,$admin,$password); $ids[]=$limitedId;
    $actor=['id'=>$limitedId,'rol'=>'admin'];
    $promote=$data; $promote['rol_id']=$roles['superadmin'];
    deniedUser(fn()=>User::save($promote,$id,$actor,''),'admin no crea superadministradores');
    $realAdmin=User::find($adminId);
    deniedUser(fn()=>User::save($realAdmin,$adminId,$actor,''),'admin no modifica superadministrador');
    $disabled=$limited; $disabled['estado']=0;
    deniedUser(fn()=>User::save($disabled,$limitedId,$actor,''),'no desactivar cuenta propia');
    $demote=$limited; $demote['rol_id']=$roles['vendedor'];
    deniedUser(fn()=>User::save($demote,$limitedId,$actor,''),'no cambiar rol propio');
    deniedUser(fn()=>User::save($limited,$limitedId,$actor,'QaOwnReset123'),'restablecimiento propio requiere flujo de contraseña');
    checkUser(!in_array('superadmin',array_column(User::roles($actor),'nombre'),true),'roles restringidos para admin');
} finally {
    foreach ($ids as $id) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
    $_SESSION=[]; session_destroy(); ob_end_flush();
}
