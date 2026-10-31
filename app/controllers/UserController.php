<?php
class UserController extends Controller
{
    private function access(): array { Auth::requireRole('superadmin','admin'); header('Cache-Control: no-store'); return Auth::user(); }
    private function existing(array $actor): array
    {
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $account=$id ? User::find($id) : null;
        if (!$account) { http_response_code(404); exit('Usuario no encontrado.'); }
        if ($account['rol']==='superadmin' && $actor['rol']!=='superadmin') { http_response_code(403); exit('Solo un superadministrador puede gestionar esta cuenta.'); }
        return $account;
    }
    public function index(): void
    {
        $actor=$this->access(); $query=is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,160) : '';
        $this->view('users/index',['title'=>'Usuarios y accesos','accounts'=>User::listing($actor,$query),'query'=>$query,'success'=>$_SESSION['user_success'] ?? null]); unset($_SESSION['user_success']);
    }
    private function form(array $account, array $errors=[]): void
    {
        $this->view('users/form',['title'=>isset($account['id']) ? 'Editar usuario' : 'Nuevo usuario','account'=>$account,'roles'=>User::roles(Auth::user()),'errors'=>$errors]);
    }
    public function create(): void
    {
        $actor=$this->access(); $default=null;
        foreach (User::roles($actor) as $role) if ($role['nombre']==='vendedor') $default=$role['id'];
        $this->form(['estado'=>1,'rol_id'=>$default]);
    }
    public function edit(): void { $this->form($this->existing($this->access())); }
    public function save(): void
    {
        $actor=$this->access();
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        $old=isset($_GET['id']) ? $this->existing($actor) : null; $data=[];
        foreach (['nombres','apellidos','email','telefono','rol_id','estado'] as $key) $data[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $password=is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm=is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
        $errors=[];
        foreach (['nombres','apellidos'] as $key) if ($data[$key]==='' || mb_strlen($data[$key])>80) $errors[]='Nombres y apellidos son obligatorios, con hasta 80 caracteres cada uno.';
        if (mb_strlen($data['email'])>120 || !filter_var($data['email'],FILTER_VALIDATE_EMAIL)) $errors[]='Ingresa un correo válido de hasta 120 caracteres.';
        if ($data['telefono']!=='' && !preg_match('/^[+0-9() .-]{6,20}$/D',$data['telefono'])) $errors[]='Ingresa un teléfono válido de 6 a 20 caracteres.';
        if (!preg_match('/^[1-9][0-9]{0,2}$/D',$data['rol_id'])) $errors[]='Selecciona un rol válido.';
        if (!in_array($data['estado'],['0','1'],true)) $errors[]='Selecciona Activo o Inactivo.';
        if (!$old || $password!=='' || $confirm!=='') {
            if ($error=User::passwordError($password)) $errors[]=$error;
            if ($password!==$confirm) $errors[]='Las contraseñas no coinciden.';
        }
        $view=$data+($old ? ['id'=>$old['id']] : []);
        if ($errors) { http_response_code(422); $this->form($view,$errors); return; }
        try { User::save($data,$old ? (int)$old['id'] : null,$actor,$password); }
        catch (Throwable $error) { ErrorHandler::report($error, 'UserController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->form($view,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar el usuario. Verifica si el correo ya está en uso.']); return; }
        $_SESSION['user_success']=!$old ? 'Usuario creado. Deberá cambiar su contraseña al ingresar.' : ($password!=='' ? 'Contraseña restablecida. Las sesiones anteriores se invalidaron y deberá cambiarla al ingresar.' : 'Usuario actualizado.');
        $this->redirect('/users');
    }
}
