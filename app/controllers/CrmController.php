<?php
class CrmController extends Controller
{
    private function access(): array
    {
        Auth::requireRole('superadmin','admin','vendedor');
        return Auth::user();
    }

    private function existing(array $user): array
    {
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $lead=$id ? Lead::find($id) : null;
        if (!$lead) { http_response_code(404); exit('Consulta no encontrada.'); }
        if ($user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id']) { http_response_code(403); exit('No tienes permiso para acceder a esta consulta.'); }
        return $lead;
    }

    public function index(): void
    {
        $user=$this->access();
        $stage=is_string($_GET['etapa'] ?? null) ? $_GET['etapa'] : '';
        if (!isset(Lead::STAGES[$stage])) $stage='';
        $query=is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,160) : '';
        $this->view('crm/index',['title'=>'CRM · Consultas','leads'=>Lead::listing($user,$stage,$query),'stage'=>$stage,'query'=>$query]);
    }

    public function show(): void { $this->render($this->existing($this->access())); }

    private function render(array $lead, array $errors=[], array $old=[]): void
    {
        $conversation=Assistant::forLead((int)$lead['id'],Auth::user());
        $this->view('crm/show',['title'=>'Consulta #'.$lead['id'],'lead'=>$lead,'errors'=>$errors,'old'=>$old,'sellers'=>Lead::sellers(),'history'=>Lead::history((int)$lead['id']),'visits'=>Visit::forLead((int)$lead['id']),'reservations'=>Reservation::forLead((int)$lead['id']),'conversation'=>$conversation,'opportunities'=>Opportunity::forLead((int)$lead['id'],Auth::user()),'propertyOptions'=>in_array($lead['etapa'],['cerrado','perdido'],true) ? [] : Opportunity::properties($lead,Auth::user()),'currentProperty'=>$lead['propiedad_id'] ? Property::find((int)$lead['propiedad_id']) : null,'success'=>$_SESSION['crm_success'] ?? null]);
        unset($_SESSION['crm_success']);
    }

    public function update(): void
    {
        $user=$this->access();
        $lead=$this->existing($user);
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        $data=[];
        foreach (['etapa','vendedor_id','detalle','tipo','proxima_accion'] as $key) $data[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $errors=[];
        if (!isset(Lead::STAGES[$data['etapa']])) $errors[]='Selecciona una etapa válida.';
        $seller=null;
        if ($user['rol']!=='vendedor' && $data['vendedor_id']!=='') {
            $seller=filter_var($data['vendedor_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if (!$seller || !in_array($seller,array_map('intval',array_column(Lead::sellers(),'id')),true)) $errors[]='Selecciona un vendedor activo.';
        }
        if (!isset(Lead::TYPES[$data['tipo']])) $errors[]='Selecciona un tipo de seguimiento válido.';
        if (mb_strlen($data['detalle'])>4000) $errors[]='El seguimiento admite hasta 4000 caracteres.';
        $next=null;
        if ($data['proxima_accion']!=='') {
            $date=str_contains($data['proxima_accion'],"\0") ? false : DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$data['proxima_accion'],new DateTimeZone('America/Lima'));
            if (!$date || $date->format('Y-m-d\TH:i')!==$data['proxima_accion']) $errors[]='Ingresa una fecha válida.';
            else $next=$date->format('Y-m-d H:i:s');
            if ($data['detalle']==='') $errors[]='Agrega el detalle del seguimiento para programar la próxima acción.';
        }
        if ($errors) { http_response_code(422); $this->render($lead,$errors,$data); return; }
        try { $saved=Lead::update((int)$lead['id'],$user,$data['etapa'],$seller,$data['detalle'],$data['tipo'],$next); }
        catch (Throwable $error) { ErrorHandler::report($error, 'CrmController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->render($lead,[$error instanceof DomainException ? $error->getMessage() : 'No se pudieron guardar los cambios. Inténtalo de nuevo.'],$data); return; }
        if (!$saved) { http_response_code(403); exit('La consulta ya no está disponible para tu usuario.'); }
        $_SESSION['crm_success']='Consulta actualizada.';
        $this->redirect('/crm/lead?id='.(int)$lead['id']);
    }

    public function assignProperty(): void
    {
        $user=$this->access(); $lead=$this->existing($user);
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        $property=filter_var($_POST['propiedad_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$property) { http_response_code(422); $this->render($lead,['Selecciona un inmueble válido.']); return; }
        try { Lead::assignProperty((int)$lead['id'],$property,$user); }
        catch (Throwable $error) { ErrorHandler::report($error, 'CrmController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->render($lead,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo seleccionar el inmueble. Inténtalo nuevamente.']); return; }
        $_SESSION['crm_success']='Inmueble de trabajo actualizado. Las visitas y reservas históricas conservan su inmueble original.';
        $this->redirect('/crm/lead?id='.(int)$lead['id']);
    }
}
