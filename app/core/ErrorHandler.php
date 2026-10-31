<?php
/** Error boundary with redacted diagnostics; never logs exception messages or arguments. */
class ErrorHandler
{
    private static int $bufferFloor=0;
    private static bool $installed=false;

    public static function install(): void
    {
        if (self::$installed) return;
        self::$installed=true;
        ini_set('display_errors','0');
        // Evitar que PHP duplique el error con mensajes/argumentos privados.
        // report() registra explícitamente metadatos seguros, incluidos los fatales.
        ini_set('log_errors','0');
        self::$bufferFloor=ob_get_level();
        ob_start();
        set_exception_handler([self::class,'handle']);
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new ErrorException($message,0,$severity,$file,$line);
        });
        register_shutdown_function(function (): void {
            $last=error_get_last();
            if ($last && in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)) {
                self::handle(new ErrorException('Unexpected fatal error',0,$last['type'],$last['file'],$last['line']));
            }
        });
    }

    public static function report(Throwable $error, string $context='request'): string
    {
        $reference=bin2hex(random_bytes(8));
        $frames=[];
        foreach (array_slice($error->getTrace(),0,5) as $frame) {
            $frames[]=['file'=>basename($frame['file'] ?? ''),'line'=>(int)($frame['line'] ?? 0),'class'=>$frame['class'] ?? '', 'function'=>$frame['function'] ?? ''];
        }
        $code=(string)$error->getCode();
        $entry=['reference'=>$reference,'context'=>preg_replace('/[^a-zA-Z0-9_.-]/','',$context),'exception'=>get_class($error),'code'=>preg_match('/^[A-Z0-9_-]{1,32}$/iD',$code) ? $code : null,'file'=>basename($error->getFile()),'line'=>$error->getLine(),'trace'=>$frames];
        @error_log(json_encode($entry,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE));
        return $reference;
    }

    public static function handle(Throwable $error): void
    {
        $reference=self::report($error);
        while (ob_get_level()>self::$bufferFloor) ob_end_clean();
        if (!headers_sent()) {
            header_remove('Content-Length'); header_remove('Content-Disposition'); header_remove('Location');
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            $home=htmlspecialchars((defined('BASE_URL') ? BASE_URL : '').'/dashboard',ENT_QUOTES,'UTF-8');
            require __DIR__.'/../views/errors/unexpected.php';
        }
        exit(1);
    }
}
