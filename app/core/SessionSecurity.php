<?php
class SessionSecurity
{
    public static function cookieOptions(array $server): array
    {
        $https=strtolower((string)($server['HTTPS'] ?? ''));
        return ['httponly'=>true,'secure'=>$https!=='' && $https!=='off' && $https!=='0','samesite'=>'Lax'];
    }

    public static function configure(array $server): void
    {
        ini_set('session.use_strict_mode','1');
        ini_set('session.use_only_cookies','1');
        ini_set('session.use_trans_sid','0');
        session_set_cookie_params(self::cookieOptions($server));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
    }
}
