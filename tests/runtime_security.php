<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('BASE_URL','/inmova-ai/public');
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function e(?string $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
$failures=0;
function check(bool $ok, string $label): void {
    global $failures;
    if (!$ok) $failures++;
    echo ($ok ? 'OK: ' : 'FAIL: ').$label.PHP_EOL;
}
class RuntimeViewProbe extends Controller {
    public function render(): string {
        ob_start();
        $this->view('auth/login',['view'=>'portal/not-found','layout'=>'main','title'=>'QA']);
        return ob_get_clean();
    }
}
$_SESSION=[]; $_SERVER['REQUEST_URI']=BASE_URL.'/login';
check(str_contains((new RuntimeViewProbe())->render(),'login-shell'),'datos de la vista no sustituyen el nombre de la plantilla');
function childError(string $operation): array {
    $handler=realpath(__DIR__.'/../app/core').'/ErrorHandler.php';
    $code='<?php define("BASE_URL","/inmova-ai/public"); if(is_file('.var_export($handler,true).')) { require '.var_export($handler,true).'; ErrorHandler::install(); } register_shutdown_function(function(){fwrite(STDERR,"TEST_HTTP_STATUS=".(string)http_response_code());}); echo "PRIVATE_PARTIAL_OUTPUT"; '.$operation;
    $script=tempnam(__DIR__.'/../tmp','runtime-test-');
    try {
        file_put_contents($script,$code);
        $pipes=[]; $process=proc_open([PHP_BINARY,'-d','display_errors=1',$script],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('No se pudo ejecutar el proceso aislado.');
        fclose($pipes[0]); $body=stream_get_contents($pipes[1]); $log=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $status=proc_close($process);
        return [$body,$log,$status];
    } finally { if (is_file($script)) unlink($script); }
}
foreach (['excepción'=>'throw new RuntimeException("PRIVATE_API_KEY_AND_SQL");','advertencia'=>'trigger_error("PRIVATE_API_KEY_AND_SQL",E_USER_WARNING);'] as $kind=>$operation) {
    [$body,$log,$status]=childError($operation);
    check(!str_contains($body.$log,'PRIVATE_API_KEY_AND_SQL'),'mensaje privado no se expone en '.$kind);
    check(!str_contains($body,'PRIVATE_PARTIAL_OUTPUT'),'salida parcial descartada en '.$kind);
    preg_match('/data-error-reference="([a-f0-9]{16})"/',$body,$match);
    check(isset($match[1]) && str_contains($log,$match[1]),'referencia visible coincide con el registro en '.$kind);
    check($status!==0,'fallo termina con código de error en '.$kind);
    check(str_contains($log,'TEST_HTTP_STATUS=500'),'respuesta establece estado 500 en '.$kind);
}
if (class_exists('SessionSecurity')) {
    $http=SessionSecurity::cookieOptions(['HTTPS'=>'off']);
    $https=SessionSecurity::cookieOptions(['HTTPS'=>'on']);
    $proxy=SessionSecurity::cookieOptions(['HTTP_X_FORWARDED_PROTO'=>'https']);
    check($http['httponly'] && $http['samesite']==='Lax','cookie limita acceso desde scripts y peticiones entre sitios');
    check($https['secure'] && !$http['secure'],'cookie Secure depende de HTTPS real');
    check(!$proxy['secure'],'cabecera de proxy arbitraria no se acepta como prueba de HTTPS');
} else check(false,'configuración segura de sesión disponible');
exit($failures ? 1 : 0);
