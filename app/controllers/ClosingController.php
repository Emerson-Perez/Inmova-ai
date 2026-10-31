<?php
class ClosingController extends Controller
{
    private function access(): array { Auth::requireRole('superadmin','admin','vendedor'); return Auth::user(); }
    private function reservation(): array
    {
        $id=filter_var($_GET['reserva_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $row=$id ? Reservation::find($id) : null;
        if (!$row) { http_response_code(404); exit('Reserva no encontrada.'); }
        return $row;
    }
    public function index(): void
    {
        $user=$this->access(); $this->view('closings/index',['title'=>'Operaciones cerradas','rows'=>Closing::listing($user)]);
    }
    public function create(): void
    {
        Auth::requireRole('superadmin','admin'); $row=$this->reservation();
        if ($closing=Closing::forReservation((int)$row['id'])) $this->redirect('/closings/show?id='.(int)$closing['id']);
        if ($row['estado_reserva']!=='aprobada') { http_response_code(422); exit('Selecciona una reserva aprobada para registrar un cierre.'); }
        $this->form($row);
    }
    private function form(array $row, array $errors=[], array $old=[]): void
    {
        $this->view('closings/form',['title'=>'Registrar cierre','row'=>$row,'errors'=>$errors,'old'=>$old,'operation'=>Closing::operation((int)$row['propiedad_id']),'today'=>(new DateTimeImmutable('today',new DateTimeZone('America/Lima')))->format('Y-m-d')]);
    }
    public function store(): void
    {
        Auth::requireRole('superadmin','admin');
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        $row=$this->reservation(); $data=[];
        foreach (['monto_final','fecha_cierre','observaciones'] as $key) $data[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        if (!preg_match('/^[0-9]{1,7}$/D',$data['monto_final']) || (int)$data['monto_final']<1 || (int)$data['monto_final']>5000000) { http_response_code(422); $this->form($row,['El monto final debe ser entero entre 1 y 5 000 000.'],$data); return; }
        try { $id=Closing::create((int)$row['id'],(int)$data['monto_final'],$data['fecha_cierre'],$data['observaciones'],Auth::user()); }
        catch (Throwable $error) { ErrorHandler::report($error, 'ClosingController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->form($row,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo registrar el cierre.'],$data); return; }
        $_SESSION['closing_success']='Operación cerrada. El inmueble y la consulta fueron actualizados.';
        $this->redirect('/closings/show?id='.$id);
    }
    public function show(): void
    {
        $user=$this->access(); $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $row=$id ? Closing::find($id) : null;
        if (!$row) { http_response_code(404); exit('Cierre no encontrado.'); }
        if ($user['rol']==='vendedor' && (int)$row['vendedor_id']!==$user['id']) { http_response_code(403); exit('No tienes permiso para este cierre.'); }
        $this->view('closings/show',['title'=>'Cierre #'.$row['id'],'row'=>$row,'success'=>$_SESSION['closing_success'] ?? null]); unset($_SESSION['closing_success']);
    }
}
