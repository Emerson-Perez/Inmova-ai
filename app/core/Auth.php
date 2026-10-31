<?php
// Autenticación y control de acceso por roles.
class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if (!$user || !$user['estado'] || !$user['rol_estado'] || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);           // evita fijación de sesión
        $_SESSION['user'] = [
            'id'     => (int) $user['id'],
            'nombre' => $user['nombres'] . ' ' . $user['apellidos'],
            'email'  => $user['email'],
            'rol'    => $user['rol'],
            'auth_version' => (int)$user['auth_version'],
            'password_change_required' => (bool)$user['password_change_required'],
        ];
        User::touchLogin((int) $user['id']);
        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function refresh(): void
    {
        if (!isset($_SESSION['user'])) return;
        $user=User::find((int)$_SESSION['user']['id']);
        if (!$user || !$user['estado'] || !$user['rol_estado'] || (int)$user['auth_version']!==(int)($_SESSION['user']['auth_version'] ?? 1)) {
            unset($_SESSION['user']);
            session_regenerate_id(true);
            $_SESSION['flash_error']='Tu sesión terminó porque cambiaron los accesos de la cuenta. Ingresa nuevamente.';
            return;
        }
        $_SESSION['user']=['id'=>(int)$user['id'],'nombre'=>$user['nombres'].' '.$user['apellidos'],'email'=>$user['email'],'rol'=>$user['rol'],'auth_version'=>(int)$user['auth_version'],'password_change_required'=>(bool)$user['password_change_required']];
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function requireLogin(): void
    {
        header('Cache-Control: private, no-store');
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        if (!empty($_SESSION['user']['password_change_required']) && parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)!==BASE_URL.'/account/password') {
            header('Location: '.BASE_URL.'/account/password'); exit;
        }
    }

    // Uso: Auth::requireRole('superadmin', 'admin');
    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!in_array($_SESSION['user']['rol'], $roles, true)) {
            http_response_code(403);
            exit('403 · No tienes permiso para acceder a esta sección.');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status()===PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params=session_get_cookie_params();
                setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$params['path'],'domain'=>$params['domain'],'secure'=>$params['secure'],'httponly'=>$params['httponly'],'samesite'=>$params['samesite'] ?? 'Lax']);
            }
            session_destroy();
        }
    }
}
