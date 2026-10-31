<?php
class SessionSecurity
{
    public static function cookieOptions(array $server): array
    {
        $https = strtolower((string)($server['HTTPS'] ?? ''));
        $proto = strtolower((string)($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $isSecure = ($https !== '' && $https !== 'off' && $https !== '0') || $proto === 'https';
        return ['httponly' => true, 'secure' => $isSecure, 'samesite' => 'Lax'];
    }

    public static function configure(array $server): void
    {
        // En entornos serverless/cloud (Vercel/AWS Lambda), la raíz es de solo lectura y únicamente /tmp permite guardar sesiones:
        if (DIRECTORY_SEPARATOR === '/' && is_dir('/tmp') && is_writable('/tmp')) {
            ini_set('session.save_path', '/tmp');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_set_cookie_params(self::cookieOptions($server));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
    }
}
