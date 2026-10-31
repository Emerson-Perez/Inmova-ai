<?php
class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) $this->redirect('/dashboard');
        $this->view('auth/login', ['error' => $_SESSION['flash_error'] ?? null]);
        unset($_SESSION['flash_error']);
    }

    public function login(): void
    {
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) {
            http_response_code(403);
            exit('Sesión expirada. Recarga la página.');
        }
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $pass  = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '' || !Auth::attempt($email, $pass)) {
            $_SESSION['flash_error'] = 'Correo o contraseña incorrectos.';
            $this->redirect('/login');
        }
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página antes de cerrar sesión.'); }
        Auth::logout();
        $this->redirect('/login');
    }
}
