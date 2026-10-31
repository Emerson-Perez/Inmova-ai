<?php
class DocumentController extends Controller
{
    private function access(): array
    {
        Auth::requireRole('superadmin','admin','vendedor'); header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff'); return Auth::user();
    }
    private function property(array $user): array
    {
        $id=filter_var($_GET['property_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Inmueble no encontrado.'); }
        try { return PropertyDocument::property($id,$user); }
        catch (DomainException $error) { http_response_code(404); exit('Inmueble no encontrado o sin acceso a sus documentos.'); }
    }
    private function csrf(): void
    {
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
    }
    private function render(array $property, bool $active, array $errors=[], array $old=[]): void
    {
        $user=Auth::user(); $id=(int)$property['id'];
        $this->view('documents/index',['title'=>'Documentos privados','property'=>$property,'active'=>$active,'documents'=>PropertyDocument::listing($id,$active,$user),'activeCount'=>PropertyDocument::countActive($id),'history'=>PropertyDocument::history($id,$user),'errors'=>$errors,'old'=>$old,'success'=>$_SESSION['document_success'] ?? null]); unset($_SESSION['document_success']);
    }
    public function index(): void
    {
        $user=$this->access(); $this->render($this->property($user),($_GET['state'] ?? '')!=='retirados');
    }
    public function upload(): void
    {
        $user=$this->access(); $this->csrf(); $property=$this->property($user);
        $title=is_string($_POST['titulo'] ?? null) ? trim($_POST['titulo']) : '';
        $category=is_string($_POST['categoria'] ?? null) ? $_POST['categoria'] : '';
        $file=is_array($_FILES['document'] ?? null) ? $_FILES['document'] : [];
        try { PropertyDocument::upload((int)$property['id'],$title,$category,$file,$user); }
        catch (Throwable $error) { ErrorHandler::report($error, 'DocumentController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->render($property,true,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar el documento. Inténtalo de nuevo.'],['titulo'=>$title,'categoria'=>$category]); return; }
        $_SESSION['document_success']='Documento guardado en el almacenamiento privado.';
        $this->redirect('/properties/documents?property_id='.(int)$property['id']);
    }
    public function status(): void
    {
        $user=$this->access(); $this->csrf();
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $row=$id ? PropertyDocument::find($id,$user,false) : null;
        if (!$row) { http_response_code(404); exit('Documento no encontrado o sin acceso.'); }
        if (!in_array($_POST['active'] ?? null,['0','1'],true)) { http_response_code(422); exit('Selecciona un estado válido.'); }
        $active=$_POST['active']==='1'; $property=PropertyDocument::property((int)$row['propiedad_id'],$user);
        try { PropertyDocument::setActive($id,$active,$user); }
        catch (Throwable $error) { ErrorHandler::report($error, 'DocumentController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->render($property,!$active,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo actualizar el documento.']); return; }
        $_SESSION['document_success']=$active ? 'Documento restaurado.' : 'Documento retirado. El archivo se conserva y puedes restaurarlo.';
        $this->redirect('/properties/documents?property_id='.(int)$row['propiedad_id'].'&state='.($active ? 'activos' : 'retirados'));
    }
    public function download(): void
    {
        $user=$this->access(); $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Documento no encontrado.'); }
        try { $row=PropertyDocument::download($id,$user); }
        catch (DomainException $error) { http_response_code(404); exit('Documento no encontrado, retirado o sin acceso.'); }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="documento-'.$id.'.pdf"; filename*=UTF-8\'\''.rawurlencode($row['nombre_original']));
        header('Content-Security-Policy: sandbox'); header('Pragma: no-cache');
        header('Content-Length: '.filesize($row['path']));
        session_write_close(); readfile($row['path']);
    }
}
