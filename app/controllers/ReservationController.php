<?php
class ReservationController extends Controller
{
    private function access(): array { Auth::requireRole('superadmin','admin','vendedor'); return Auth::user(); }
    private function row(array $user): array
    {
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $row=$id ? Reservation::find($id) : null;
        if (!$row) { http_response_code(404); exit('Reserva no encontrada.'); }
        if ($user['rol']==='vendedor' && (int)$row['vendedor_id']!==$user['id']) { http_response_code(403); exit('No tienes permiso para esta reserva.'); }
        return $row;
    }
    private function lead(array $user): array
    {
        $id=filter_var($_GET['lead_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $lead=$id ? Lead::find($id) : null;
        if (!$lead) { http_response_code(404); exit('Consulta no encontrada.'); }
        if ($user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id']) { http_response_code(403); exit('No tienes permiso para esta consulta.'); }
        return $lead;
    }
    private function csrf(): void
    {
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
    }
    public function index(): void
    {
        $user=$this->access(); $state=is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '';
        if (!isset(Reservation::STATES[$state])) $state='';
        $propertyId=null;
        if (isset($_GET['propiedad_id'])) {
            $propertyId=filter_var($_GET['propiedad_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if (!$propertyId) { http_response_code(422); exit('Selecciona un inmueble válido.'); }
        }
        $this->view('reservations/index',['title'=>'Reservas','rows'=>Reservation::listing($user,$state,$propertyId),'state'=>$state,'propertyId'=>$propertyId]);
    }
    private function form(array $lead, array $errors=[], array $old=[]): void
    {
        $this->view('reservations/form',['title'=>'Solicitar reserva','lead'=>$lead,'errors'=>$errors,'old'=>$old,'sellers'=>Lead::sellers()]);
    }
    public function create(): void { $this->form($this->lead($this->access())); }
    public function store(): void
    {
        $user=$this->access(); $this->csrf(); $lead=$this->lead($user);
        $amount=is_string($_POST['monto_reserva'] ?? null) ? trim($_POST['monto_reserva']) : '';
        $seller=$user['rol']==='vendedor' ? $user['id'] : filter_var($_POST['vendedor_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $errors=[];
        if (!preg_match('/^[0-9]{1,7}$/D',$amount) || (int)$amount<1 || (int)$amount>5000000) $errors[]='El monto debe ser entero entre 1 y 5 000 000.';
        if (!$seller) $errors[]='Selecciona un vendedor.';
        $old=['monto_reserva'=>$amount,'vendedor_id'=>$seller];
        if ($errors) { http_response_code(422); $this->form($lead,$errors,$old); return; }
        try { $id=Reservation::create((int)$lead['id'],(int)$seller,(int)$amount,$user); }
        catch (Throwable $error) { ErrorHandler::report($error, 'ReservationController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->form($lead,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar la reserva.'],$old); return; }
        $_SESSION['reservation_success']='Solicitud registrada. Está pendiente de aprobación administrativa.';
        $this->redirect('/reservations/show?id='.$id);
    }
    private function detail(array $row, array $errors=[]): void
    {
        $this->view('reservations/show',['title'=>'Reserva #'.$row['id'],'row'=>$row,'errors'=>$errors,'closing'=>Closing::forReservation((int)$row['id']),'success'=>$_SESSION['reservation_success'] ?? null]);
        unset($_SESSION['reservation_success']);
    }
    public function show(): void { $this->detail($this->row($this->access())); }
    public function transition(): void
    {
        $user=$this->access(); $this->csrf(); $row=$this->row($user);
        $action=is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
        $reason=is_string($_POST['motivo'] ?? null) ? trim($_POST['motivo']) : '';
        if ($user['rol']==='vendedor' && ($action!=='cancelada' || $row['estado_reserva']!=='pendiente')) { http_response_code(403); exit('Esta acción requiere un administrador.'); }
        if (!isset(Reservation::STATES[$action]) || $reason==='' || mb_strlen($reason)>2000) { http_response_code(422); $this->detail($row,['Selecciona una acción válida e indica un motivo de hasta 2000 caracteres.']); return; }
        try { Reservation::transition((int)$row['id'],$action,$reason,$user); }
        catch (Throwable $error) { ErrorHandler::report($error, 'ReservationController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->detail($row,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo actualizar la reserva.']); return; }
        $_SESSION['reservation_success']='Reserva actualizada. El cambio quedó registrado en el historial del CRM.';
        $this->redirect('/reservations/show?id='.(int)$row['id']);
    }
}
