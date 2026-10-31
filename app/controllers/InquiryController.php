<?php
class InquiryController extends Controller
{
    public function store(): void
    {
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $property = $id ? PublicProperty::find($id) : null;
        if (!$property) { http_response_code(404); $this->view('portal/not-found',['title'=>'Inmueble no disponible']); return; }
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        $data = [];
        foreach (['nombres','email','telefono','mensaje'] as $key) $data[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $errors = [];
        if ($data['nombres']==='' || mb_strlen($data['nombres'])>80) $errors[]='Ingresa tu nombre (hasta 80 caracteres).';
        if ($data['email']!=='' && (mb_strlen($data['email'])>120 || !filter_var($data['email'],FILTER_VALIDATE_EMAIL))) $errors[]='Ingresa un correo válido.';
        if ($data['telefono']!=='' && !preg_match('/^[+0-9() .-]{6,20}$/D',$data['telefono'])) $errors[]='Ingresa un teléfono válido de 6 a 20 caracteres.';
        if ($data['email']==='' && $data['telefono']==='') $errors[]='Indica al menos un correo o teléfono para contactarte.';
        if ($data['mensaje']==='' || mb_strlen($data['mensaje'])>2000) $errors[]='Escribe un mensaje de hasta 2000 caracteres.';
        if (($_POST['consent'] ?? '') !== '1') $errors[]='Confirma que podemos usar tus datos para responder esta consulta.';
        if (time() - (int)($_SESSION['last_inquiry'] ?? 0) < 60) $errors[]='Tu consulta anterior ya fue enviada. Espera un minuto antes de enviar otra.';
        if ($errors) { http_response_code(422); $this->render($property,$data,$errors); return; }
        try { $saved = Lead::capture((int)$property['id'],$data); }
        catch (Throwable $error) { ErrorHandler::report($error, 'InquiryController'); http_response_code(503); $this->render($property,$data,['No se pudo guardar tu consulta. Inténtalo de nuevo.']); return; }
        if (!$saved) { http_response_code(404); $this->view('portal/not-found',['title'=>'Inmueble no disponible']); return; }
        $_SESSION['last_inquiry']=time();
        $_SESSION['inquiry_success']=(int)$property['id'];
        $this->redirect('/portal/property?id='.(int)$property['id'].'#consulta');
    }

    private function render(array $property, array $data, array $errors): void
    {
        $this->view('portal/show',['title'=>$property['titulo'],'property'=>$property,'photos'=>Property::photos((int)$property['id']),'inquiry'=>$data,'inquiryErrors'=>$errors]);
    }
}
