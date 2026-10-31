<?php
class OpportunityController extends Controller
{
    private function access(): array { Auth::requireRole('superadmin','admin','vendedor'); header('Cache-Control: no-store'); return Auth::user(); }
    private function lead(array $user): array
    {
        $id=filter_var($_GET['lead_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $lead=$id ? Lead::find($id) : null;
        if (!$lead) { http_response_code(404); exit('Consulta no encontrada.'); }
        if ($user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id']) { http_response_code(403); exit('No tienes permiso para esta consulta.'); }
        return $lead;
    }
    private function row(array $user): array
    {
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $row=$id ? Opportunity::find($id,$user) : null;
        if (!$row) { http_response_code(404); exit('Oportunidad no encontrada o sin acceso.'); }
        return $row;
    }
    public function index(): void
    {
        $user=$this->access(); $stage=is_string($_GET['etapa'] ?? null) ? $_GET['etapa'] : ''; if (!isset(Opportunity::STAGES[$stage])) $stage='';
        $query=is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,160) : '';
        $this->view('opportunities/index',['title'=>'Oportunidades comerciales','rows'=>Opportunity::listing($user,$stage,$query),'summary'=>Opportunity::summary($user),'stage'=>$stage,'query'=>$query]);
    }
    private function form(array $lead, array $row, array $errors=[]): void
    {
        $this->view('opportunities/form',['title'=>isset($row['id']) ? 'Gestionar oportunidad' : 'Nueva oportunidad','lead'=>$lead,'row'=>$row,'errors'=>$errors,'properties'=>isset($row['id']) ? [] : Opportunity::properties($lead,Auth::user()),'success'=>$_SESSION['opportunity_success'] ?? null]); unset($_SESSION['opportunity_success']);
    }
    public function create(): void
    {
        $user=$this->access(); $lead=$this->lead($user); $this->form($lead,['propiedad_id'=>$lead['propiedad_id'],'probabilidad'=>'50','etapa'=>'abierta']);
    }
    public function edit(): void
    {
        $row=$this->row($this->access()); $this->form(Lead::find((int)$row['lead_id']),$row);
    }
    public function save(): void
    {
        $user=$this->access();
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        $old=isset($_GET['id']) ? $this->row($user) : null;
        $lead=$old ? Lead::find((int)$old['lead_id']) : $this->lead($user);
        $property=$old ? (int)$old['propiedad_id'] : (filter_var($_POST['propiedad_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 0);
        $data=[]; foreach (['monto_estimado','probabilidad','etapa','notas'] as $key) $data[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $view=$old && $old['etapa']==='ganada' ? $old : $data+($old ?? ['propiedad_id'=>$property]);
        if (!$old || $old['etapa']!=='ganada') if (!in_array($data['etapa'],['abierta','perdida','cancelada'],true)) $view['etapa']=$old['etapa'] ?? 'abierta';
        if (!$property) { http_response_code(422); $this->form($lead,$view,['Selecciona un inmueble.']); return; }
        try { $id=Opportunity::save((int)$lead['id'],$property,$data,$old ? (int)$old['id'] : null,$user); }
        catch (Throwable $error) { ErrorHandler::report($error, 'OpportunityController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->form($lead,$view,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar la oportunidad.']); return; }
        $_SESSION['opportunity_success']='Oportunidad guardada. El cambio quedó registrado en el historial del CRM.';
        $this->redirect('/opportunities/edit?id='.$id);
    }
}
