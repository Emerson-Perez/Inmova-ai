<?php
class VisitController extends Controller
{
    private function access(): array { Auth::requireRole('superadmin','admin','vendedor'); return Auth::user(); }

    private function visit(array $user): array
    {
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $visit=$id ? Visit::find($id) : null;
        if (!$visit) { http_response_code(404); exit('Visita no encontrada.'); }
        if ($user['rol']==='vendedor' && (int)$visit['vendedor_id']!==$user['id']) { http_response_code(403); exit('No tienes permiso para esta visita.'); }
        return $visit;
    }

    private function lead(array $user): array
    {
        $id=filter_var($_GET['lead_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $lead=$id ? Lead::find($id) : null;
        if (!$lead) { http_response_code(404); exit('Consulta no encontrada.'); }
        if ($user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id']) { http_response_code(403); exit('No tienes permiso para esta consulta.'); }
        return $lead;
    }

    public function index(): void
    {
        $user=$this->access();
        $state=is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '';
        if (!isset(Visit::STATES[$state])) $state='';
        $day=is_string($_GET['fecha'] ?? null) ? $_GET['fecha'] : '';
        $date=str_contains($day,"\0") ? false : DateTimeImmutable::createFromFormat('!Y-m-d',$day);
        if (!$date || $date->format('Y-m-d')!==$day) $day='';
        $this->view('visits/index',['title'=>'Agenda de visitas','visits'=>Visit::listing($user,$state,$day),'state'=>$state,'day'=>$day]);
    }

    public function create(): void
    {
        $user=$this->access(); $lead=$this->lead($user);
        $this->form(['lead_id'=>$lead['id'],'cliente'=>$lead['nombres'],'titulo'=>$lead['titulo'],'vendedor_id'=>$user['rol']==='vendedor' ? $user['id'] : $lead['vendedor_id'],'modalidad'=>'presencial','estado_visita'=>'solicitada']);
    }

    public function edit(): void { $this->form($this->visit($this->access())); }

    private function form(array $visit, array $errors=[]): void
    {
        $this->view('visits/form',['title'=>isset($visit['id']) ? 'Gestionar visita' : 'Programar visita','visit'=>$visit,'errors'=>$errors,'sellers'=>Lead::sellers(),'success'=>$_SESSION['visit_success'] ?? null]);
        unset($_SESSION['visit_success']);
    }

    public function save(): void
    {
        $user=$this->access();
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        $old=isset($_GET['id']) ? $this->visit($user) : null;
        $lead=$old ? null : $this->lead($user);
        $data=[];
        foreach (['vendedor_id','modalidad','fecha_hora','estado_visita','resultado'] as $key) $data[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $errors=[];
        $seller=$user['rol']==='vendedor' ? $user['id'] : filter_var($data['vendedor_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$seller || !in_array($seller,array_map('intval',array_column(Lead::sellers(),'id')),true)) $errors[]='Selecciona un vendedor activo.';
        $data['vendedor_id']=$seller ?: 0;
        if (!isset(Visit::MODES[$data['modalidad']])) $errors[]='Selecciona una modalidad válida.';
        if (!isset(Visit::STATES[$data['estado_visita']]) || (!$old && !in_array($data['estado_visita'],['solicitada','confirmada'],true))) $errors[]='Selecciona un estado válido.';
        $date=str_contains($data['fecha_hora'],"\0") ? false : DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$data['fecha_hora'],new DateTimeZone('America/Lima'));
        $now=new DateTimeImmutable('now',new DateTimeZone('America/Lima'));
        if (!$date || $date->format('Y-m-d\TH:i')!==$data['fecha_hora']) $errors[]='Ingresa una fecha y hora válidas.';
        else {
            if ((!$old || $date->format('Y-m-d H:i:s')!==$old['fecha_hora']) && $date<=$now) $errors[]='La nueva fecha de visita debe ser futura.';
            if ($data['estado_visita']==='realizada' && $date>$now) $errors[]='Una visita futura aún no puede marcarse como realizada.';
        }
        if (mb_strlen($data['resultado'])>4000) $errors[]='El resultado admite hasta 4000 caracteres.';
        if ($data['estado_visita']==='realizada' && $data['resultado']==='') $errors[]='Registra el resultado de la visita realizada.';
        $view=$data+($old ?? ['lead_id'=>$lead['id'],'cliente'=>$lead['nombres'],'titulo'=>$lead['titulo']]);
        if ($errors) { http_response_code(422); $this->form($view,$errors); return; }
        $data['fecha_hora']=$date->format('Y-m-d H:i:s');
        try { $id=Visit::save($data,$user,$old ? (int)$old['id'] : null,$lead ? (int)$lead['id'] : null); }
        catch (Throwable $error) { ErrorHandler::report($error, 'VisitController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->form($view,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar la visita. Inténtalo de nuevo.']); return; }
        $_SESSION['visit_success']='Visita guardada. Los cambios se registraron en el historial de la consulta.';
        $this->redirect('/visits/edit?id='.$id);
    }
}
