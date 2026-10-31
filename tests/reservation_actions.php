<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('BASE_URL','/inmova-ai/public');
require __DIR__.'/../app/models/Reservation.php';
function e(?string $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function renderReservations(string $role, string $status, int $seller=7): string {
    $user=['id'=>7,'rol'=>$role]; $state=''; $propertyId=99;
    $rows=[['id'=>12,'propiedad_id'=>99,'vendedor_id'=>$seller,'estado_reserva'=>$status,'cliente'=>'QA','titulo'=>'QA inmueble','vendedor'=>'QA vendedor','moneda'=>'PEN','monto_reserva'=>100]];
    ob_start(); require __DIR__.'/../app/views/reservations/index.php'; return ob_get_clean();
}
set_error_handler(function ($severity,$message,$file,$line) { throw new ErrorException($message,0,$severity,$file,$line); });
foreach (['admin','superadmin'] as $role) {
    foreach (['pendiente','aprobada'] as $state) {
        $html=renderReservations($role,$state);
        check(str_contains($html,'/reservations/show?id=12#modificar-estado'),$role.' puede abrir el cambio de estado de '.$state);
    }
}
check(str_contains(renderReservations('vendedor','pendiente'),'#modificar-estado'),'vendedor puede modificar su solicitud pendiente');
check(str_contains(renderReservations('vendedor','aprobada'),'#modificar-estado'),'boton visible para vendedor con reserva aprobada');
check(str_contains(renderReservations('vendedor','aprobada'),'Solo un administrador'),'reserva aprobada informa la restriccion del vendedor');
foreach (['cancelada','rechazada','cerrada'] as $state) {
    check(str_contains(renderReservations('admin',$state),'#modificar-estado'),'boton visible para reserva '.$state);
}
check(str_contains(renderReservations('admin','aprobada'),'name="propiedad_id" value="99"'),'filtro por estado conserva el inmueble seleccionado');
