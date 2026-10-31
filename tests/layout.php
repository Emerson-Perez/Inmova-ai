<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('BASE_URL','/inmova-ai/public');
require __DIR__.'/../app/core/Csrf.php';
require __DIR__.'/../app/models/Simulation.php';
function e(?string $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function renderLayout(?string $role, string $path='/dashboard'): string {
    $user=$role ? ['id'=>1,'nombre'=>'QA <script>','rol'=>$role] : null;
    $content='<h1>Contenido de prueba</h1>'; $title='Prueba <script>';
    $_SERVER['REQUEST_URI']=BASE_URL.$path;
    ob_start(); require __DIR__.'/../app/views/layouts/main.php'; return ob_get_clean();
}
set_error_handler(function ($severity,$message,$file,$line) { throw new ErrorException($message,0,$severity,$file,$line); });
$admin=renderLayout('admin','/visits/edit?id=1');
check(str_contains($admin,'id="app-sidebar"') && str_contains($admin,'Usuarios y accesos'),'administrador conserva navegación y gestión de usuarios');
check(str_contains($admin,'href="'.BASE_URL.'/visits" aria-current="page"'),'subruta identifica el módulo activo');
check(str_contains($admin,'aria-controls="app-sidebar"') && str_contains($admin,'aria-expanded="false"'),'menú móvil enlaza el control con la barra lateral');
check(str_contains($admin,'href="#main-content"') && str_contains($admin,'id="main-content"'),'atajo de teclado apunta al contenido');
check(str_contains($admin,'action="'.BASE_URL.'/logout"') && str_contains($admin,'name="csrf"'),'cerrar sesión conserva POST y protección CSRF');
check(!str_contains($admin,'QA <script>') && str_contains($admin,'QA &lt;script&gt;') && str_contains($admin,'Prueba &lt;script&gt;'),'nombre y título conservan escape HTML');
$seller=renderLayout('vendedor');
check(str_contains($seller,'Consultas CRM') && !str_contains($seller,'Usuarios y accesos'),'vendedor conserva módulos comerciales sin administración');
$student=renderLayout('practicante');
check(!str_contains($student,'Consultas CRM') && str_contains($student,'Simulador de ventas'),'practicante conserva aprendizaje sin módulos comerciales');
$certificates=renderLayout('admin','/academy/certificate?code=demo');
check(substr_count($certificates,'aria-current="page"')===1 && str_contains($certificates,'href="'.BASE_URL.'/academy/certificates" aria-current="page"'),'documento de certificado activa solamente Mis certificados');
$public=renderLayout(null,'/portal');
check(!str_contains($public,'id="app-sidebar"') && str_contains($public,'Navegación pública') && str_contains($public,'Acceso al sistema'),'visitante recibe navegación pública');
restore_error_handler();
