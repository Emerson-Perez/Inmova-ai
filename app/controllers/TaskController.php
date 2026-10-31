<?php
class TaskController extends Controller
{
    public function index(): void
    {
        Auth::requireRole('superadmin','admin','vendedor'); header('Cache-Control: no-store'); $user=Auth::user();
        $filter=is_string($_GET['filter'] ?? null) ? $_GET['filter'] : 'pendientes';
        if (!isset(Task::FILTERS[$filter])) $filter='pendientes';
        $now=new DateTimeImmutable('now',new DateTimeZone('America/Lima'));
        $this->view('tasks/index',['title'=>'Centro de pendientes','summary'=>Task::summary($user,$now),'tasks'=>Task::listing($user,$filter,$now),'visitsOverdue'=>Task::visits($user,true,$now),'visitsSoon'=>Task::visits($user,false,$now),'unassigned'=>Task::unassigned($user),'reservations'=>Task::reservations($user),'filter'=>$filter,'clock'=>Task::clock($now),'success'=>$_SESSION['task_success'] ?? null,'error'=>$_SESSION['task_error'] ?? null]);
        unset($_SESSION['task_success'],$_SESSION['task_error']);
    }
    public function resolve(): void
    {
        Auth::requireRole('superadmin','admin','vendedor'); header('Cache-Control: no-store');
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $task=$id ? Task::find($id,Auth::user()) : null;
        if (!$task) { http_response_code(404); exit('Pendiente no encontrado o sin acceso.'); }
        if (!in_array($_POST['done'] ?? null,['0','1'],true)) { http_response_code(422); exit('Selecciona un estado válido.'); }
        $done=$_POST['done']==='1'; $from=($_POST['from'] ?? '')==='crm' ? 'crm' : 'tasks';
        try { $lead=Task::resolve($id,$done,Auth::user()); }
        catch (Throwable $error) { ErrorHandler::report($error, 'TaskController'); $_SESSION['task_error']=$error instanceof DomainException ? $error->getMessage() : 'No se pudo actualizar el pendiente.'; $this->redirect('/tasks'); }
        if ($from==='crm') { $_SESSION['crm_success']=$done ? 'Pendiente completado.' : 'Pendiente reabierto.'; $this->redirect('/crm/lead?id='.$lead); }
        $_SESSION['task_success']=$done ? 'Pendiente completado. El cambio se registró en el historial del CRM.' : 'Pendiente reabierto.';
        $this->redirect('/tasks?filter='.($done ? 'pendientes' : 'completadas'));
    }
}
