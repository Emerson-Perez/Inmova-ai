<?php
class AssistantController extends Controller
{
    private function token(): string
    {
        if (!isset($_SESSION['assistant_token'])) $_SESSION['assistant_token']=bin2hex(random_bytes(32));
        return $_SESSION['assistant_token'];
    }
    private function csrf(): void
    {
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        header('Cache-Control: no-store');
    }
    private function requestKey(): void
    {
        $key=is_string($_POST['request_key'] ?? null) ? $_POST['request_key'] : '';
        if (!preg_match('/^[a-f0-9]{32}$/D',$key)) { http_response_code(422); exit('Recarga el asistente antes de enviar otra solicitud.'); }
        if (in_array($key,$_SESSION['assistant_requests'] ?? [],true)) $this->redirect('/assistant');
        $_SESSION['assistant_requests'][]=$key;
        $_SESSION['assistant_requests']=array_slice($_SESSION['assistant_requests'],-40);
    }
    private function fail(Throwable $error): void
    {
        ErrorHandler::report($error, 'AssistantController');
        $_SESSION['assistant_error']=$error instanceof DomainException || $error instanceof RuntimeException && !($error instanceof PDOException) ? $error->getMessage() : 'No se pudo completar la solicitud. Inténtalo de nuevo.';
        $this->redirect('/assistant');
    }
    public function index(): void
    {
        header('Cache-Control: no-store'); header('Referrer-Policy: same-origin');
        $row=Assistant::owned($this->token()); $filters=$row && $row['filtros'] ? json_decode($row['filtros'],true) : Assistant::defaults();
        if (!is_array($filters)) $filters=Assistant::defaults();
        $messages=$row ? Assistant::messages((int)$row['id']) : [];
        foreach ($messages as &$message) {
            $metadata=json_decode($message['metadatos'] ?? '',true) ?: [];
            $message['source']=$metadata['source'] ?? 'sistema';
            $message['cards']=Assistant::cards(is_array($metadata['property_ids'] ?? null) ? $metadata['property_ids'] : []);
        }
        unset($message);
        try { $results=Assistant::search($filters); } catch (DomainException $error) { $filters=Assistant::defaults(); $results=Assistant::search($filters); }
        $this->view('assistant/index',['title'=>'Asistente inmobiliario','conversation'=>$row,'messages'=>$messages,'filters'=>$filters,'properties'=>$results['properties'],'ready'=>AiConfig::ready(),'requestKey'=>bin2hex(random_bytes(16)),'error'=>$_SESSION['assistant_error'] ?? null,'success'=>$_SESSION['assistant_success'] ?? null]+Property::catalogs());
        unset($_SESSION['assistant_error'],$_SESSION['assistant_success']);
    }
    public function guided(): void
    {
        $this->csrf(); $this->requestKey();
        try {
            $filters=[]; foreach (Assistant::defaults() as $key=>$value) $filters[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
            $filters=Assistant::validateFilters($filters); $results=Assistant::search($filters); Assistant::open($this->token());
            $input='Búsqueda guiada: '.json_encode($filters,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $reply=$results['total'] ? 'La búsqueda guiada encontró '.$results['total'].' inmueble(s) disponible(s). Se muestran hasta seis opciones; puedes abrir la ficha o solicitar contacto.' : 'No hay inmuebles disponibles con esos filtros. Puedes ajustar la búsqueda o dejar una consulta general al equipo.';
            Assistant::record($this->token(),$input,$reply,$filters,array_column($results['properties'],'id'),'catalogo');
        } catch (Throwable $error) { $this->fail($error); }
        $this->redirect('/assistant#conversacion');
    }
    public function message(): void
    {
        $this->csrf(); $this->requestKey();
        $text=is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
        try {
            $config=AiConfig::load();
            if (!AiConfig::ready($config)) throw new DomainException('La IA todavía no está activada. Puedes usar la búsqueda guiada.');
            if ($text==='' || mb_strlen($text)>1000) throw new DomainException('Escribe un mensaje de hasta 1000 caracteres.');
            if (($_POST['ai_consent'] ?? '')!=='1') throw new DomainException('Confirma el envío de tu mensaje a OpenAI antes de usar la IA.');
            $row=Assistant::open($this->token());
            $filters=$row['filtros'] ? json_decode($row['filtros'],true) : Assistant::defaults();
            $filters=Assistant::validateFilters(is_array($filters) ? $filters : Assistant::defaults());
            $history=Assistant::messages((int)$row['id']);
            $context=Assistant::search($filters)['properties'];
            // Releer solo datos públicos vigentes; nunca incluir cuentas, clientes ni historial del CRM.
            $context=Assistant::cards(array_column($context,'id'));
            foreach ($context as &$property) $property['descripcion']=mb_substr($property['descripcion'] ?? '',0,600); unset($property);
            $history[]=['emisor'=>'cliente','contenido'=>$text];
            $ipHash=hash_hmac('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown',$config['api_key']);
            Assistant::reserveAi($this->token(),$ipHash,$config);
            $answer=(new AiClient($config))->interpret($history,Property::catalogs(),$filters,$context);
            $nextFilters=$answer['intent']==='search' ? $answer['filters'] : $filters;
            $results=Assistant::search($nextFilters);
            $reply=$answer['reply'];
            if ($answer['intent']==='search') $reply.="\n\nResultados actuales: ".$results['total'].' inmueble(s) disponible(s), hasta seis en pantalla.';
            if ($answer['intent']==='handoff') $reply.="\n\nCompleta el formulario de contacto para enviar tu consulta al equipo.";
            Assistant::record($this->token(),$text,$reply,$nextFilters,array_column($results['properties'],'id'),'openai');
        } catch (Throwable $error) { $this->fail($error); }
        $this->redirect('/assistant#conversacion');
    }
    public function handoff(): void
    {
        $this->csrf(); $this->requestKey(); $data=[];
        foreach (['nombres','email','telefono','mensaje'] as $key) $data[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        try {
            if ($data['nombres']==='' || mb_strlen($data['nombres'])>80) throw new DomainException('Ingresa tu nombre de hasta 80 caracteres.');
            if ($data['email']!=='' && (mb_strlen($data['email'])>120 || !filter_var($data['email'],FILTER_VALIDATE_EMAIL))) throw new DomainException('Ingresa un correo válido.');
            if ($data['telefono']!=='' && !preg_match('/^[+0-9() .-]{6,20}$/D',$data['telefono'])) throw new DomainException('Ingresa un teléfono válido.');
            if ($data['email']==='' && $data['telefono']==='') throw new DomainException('Indica al menos un correo o teléfono.');
            if ($data['mensaje']==='' || mb_strlen($data['mensaje'])>2000) throw new DomainException('Escribe una consulta de hasta 2000 caracteres.');
            if (($_POST['consent'] ?? '')!=='1') throw new DomainException('Autoriza el contacto y el envío de esta conversación al equipo.');
            $raw=is_string($_POST['property_id'] ?? null) ? $_POST['property_id'] : '';
            $propertyId=$raw==='' ? null : filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($propertyId===false) throw new DomainException('Elige un inmueble válido o Consulta general.');
            $row=Assistant::open($this->token());
            if (!$row['lead_id'] && time()-(int)($_SESSION['last_inquiry'] ?? 0)<60) throw new DomainException('Espera un minuto entre consultas de contacto.');
            $lead=Assistant::handoff($this->token(),$propertyId,$data); $_SESSION['last_inquiry']=time();
            $_SESSION['assistant_success']='Consulta registrada con referencia #'.$lead.'. El equipo podrá contactarte. No se envió una notificación automática.';
        } catch (Throwable $error) { $this->fail($error); }
        $this->redirect('/assistant#contacto');
    }
    public function reset(): void
    {
        $this->csrf(); Assistant::close($this->token());
        $_SESSION['assistant_token']=bin2hex(random_bytes(32)); $_SESSION['assistant_requests']=[];
        $this->redirect('/assistant');
    }
    public function settings(): void
    {
        Auth::requireRole('superadmin','admin'); $this->configForm();
    }
    private function configForm(array $errors=[]): void
    {
        header('Cache-Control: no-store'); $config=AiConfig::load();
        $config['has_key']=$config['api_key']!==''; unset($config['api_key']);
        $this->view('assistant/settings',['title'=>'Configurar asistente IA','config'=>$config,'ready'=>AiConfig::ready(),'envKey'=>(bool)getenv('OPENAI_API_KEY'),'envEnabled'=>getenv('INMOVA_AI_ENABLED')!==false,'errors'=>$errors,'success'=>$_SESSION['ai_settings_success'] ?? null]); unset($_SESSION['ai_settings_success']);
    }
    public function saveSettings(): void
    {
        Auth::requireRole('superadmin','admin'); $this->csrf();
        $data=['enabled'=>isset($_POST['enabled']),'model'=>is_string($_POST['model'] ?? null) ? trim($_POST['model']) : '', 'daily_limit'=>filter_var($_POST['daily_limit'] ?? '',FILTER_VALIDATE_INT) ?: 0, 'ip_limit'=>filter_var($_POST['ip_limit'] ?? '',FILTER_VALIDATE_INT) ?: 0];
        $key=is_string($_POST['api_key'] ?? null) ? $_POST['api_key'] : '';
        try { AiConfig::save($data,$key,isset($_POST['remove_key'])); }
        catch (Throwable $error) { ErrorHandler::report($error, 'AssistantController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->configForm([$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar la configuración.']); return; }
        $_SESSION['ai_settings_success']='Configuración guardada. Las variables de entorno, si existen, prevalecen sobre el formulario.';
        $this->redirect('/assistant/settings');
    }
}
