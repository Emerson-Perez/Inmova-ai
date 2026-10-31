<?php
class AccountController extends Controller
{
    public function show(): void { Auth::requireLogin(); $this->form(); }
    private function form(array $errors=[]): void
    {
        header('Cache-Control: no-store');
        $this->view('account/password',['title'=>'Cambiar mi contraseña','errors'=>$errors,'success'=>$_SESSION['password_success'] ?? null]); unset($_SESSION['password_success']);
    }
    public function update(): void
    {
        Auth::requireLogin();
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
        $current=is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $new=is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirm=is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
        $errors=[];
        if ($error=User::passwordError($new)) $errors[]=$error;
        if ($new!==$confirm) $errors[]='Las contraseñas no coinciden.';
        if ($current==='') $errors[]='Ingresa tu contraseña actual.';
        if ($errors) { http_response_code(422); $this->form($errors); return; }
        $user=Auth::user();
        try { User::changePassword($user['id'],$user['auth_version'],$current,$new); }
        catch (Throwable $error) { http_response_code($error instanceof DomainException ? 422 : 503); $this->form([$error instanceof DomainException ? $error->getMessage() : 'No se pudo cambiar la contraseña.']); return; }
        $_SESSION['user']['auth_version']++; $_SESSION['user']['password_change_required']=false;
        session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
        $_SESSION['password_success']='Contraseña actualizada. Las otras sesiones de esta cuenta se cerrarán en su siguiente solicitud.';
        $this->redirect('/account/password');
    }
}
