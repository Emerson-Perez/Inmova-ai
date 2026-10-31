<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('BASE_URL','/inmova-ai/public');
function e(?string $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function renderDashboard(string $role, bool $failure=false): string {
    $user=['nombre'=>'QA <script>','rol'=>$role];
    $summary=$failure ? null : ['totals'=>['Operaciones cerradas'=>3]];
    $period=['from'=>'2026-10-01','to'=>'2026-10-28'];
    $pendingSummary=$failure ? null : array_fill_keys(['overdue','today','upcoming','visitsOverdue','visitsSoon','reservations','unassigned'],0);
    $reportError=$failure ? 'No se pudieron cargar los indicadores.' : null;
    $pendingError=$failure ? 'No se pudieron cargar los pendientes.' : null;
    ob_start(); require __DIR__.'/../app/views/dashboard/index.php'; return ob_get_clean();
}
$admin=renderDashboard('admin');
check(str_contains($admin,'Operaciones cerradas') && str_contains($admin,'Consultas sin vendedor'),'administrador ve actividad y asignaciones pendientes');
check(str_contains($admin,'Seguimientos para hoy') && str_contains($admin,'>0</strong>'),'pendientes vacíos muestran cero');
$seller=renderDashboard('vendedor');
check(str_contains($seller,'Actividad comercial') && !str_contains($seller,'Consultas sin vendedor'),'vendedor ve su actividad sin asignaciones globales');
$student=renderDashboard('practicante');
check(!str_contains($student,'Operaciones cerradas') && !str_contains($student,'Pendientes de atención') && str_contains($student,'Academia'),'otros roles reciben accesos sin datos comerciales');
$failure=renderDashboard('admin',true);
check(str_contains($failure,'No se pudieron cargar los indicadores.') && str_contains($failure,'No se pudieron cargar los pendientes.'),'errores de consultas visibles en el panel');
check(!str_contains($admin,'QA <script>') && str_contains($admin,'QA &lt;script&gt;'),'nombre del usuario escapado');
function renderWithoutDashboardData(?array $user=null): string {
    set_error_handler(function ($severity,$message,$file,$line) { throw new ErrorException($message,0,$severity,$file,$line); });
    ob_start();
    try {
        require __DIR__.'/../app/views/dashboard/index.php';
        return ob_get_contents();
    } finally {
        ob_end_clean();
        restore_error_handler();
    }
}
$empty=renderWithoutDashboardData();
check(str_contains($empty,'Bienvenido, Usuario') && !str_contains($empty,'Actividad comercial'),'vista sin datos no emite variables indefinidas ni muestra indicadores');
$partial=renderWithoutDashboardData(['nombre'=>'QA','rol'=>'admin']);
check(str_contains($partial,'Bienvenido, QA') && !str_contains($partial,'Actividad comercial'),'administrador sin resúmenes no emite variables indefinidas');
