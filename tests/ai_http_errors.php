<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../app/core/AiClient.php';
function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function messageFor(int $status, string $code='', string $type=''): string {
    try {
        AiClient::decodeHttpResponse(json_encode(['error'=>['code'=>$code,'type'=>$type,'message'=>'PRIVATE PROVIDER BODY sk-FAKE-SECRET']],JSON_THROW_ON_ERROR),$status);
    } catch (RuntimeException $error) {
        check(!str_contains($error->getMessage(),'PRIVATE') && !str_contains($error->getMessage(),'sk-FAKE'),'error '.$status.' no expone cuerpo ni credenciales');
        return $error->getMessage();
    }
    throw new RuntimeException('No se rechazó HTTP '.$status);
}
if (!method_exists(AiClient::class,'decodeHttpResponse')) {
    throw new RuntimeException('FAIL: el cliente agrupa autenticación y permisos y no permite verificar el error HTTP sin llamar al proveedor.');
}
check(str_contains(messageFor(401,'invalid_api_key'),'clave de API'),'401 informa clave inválida');
check(str_contains(messageFor(403),'permisos'),'403 informa permisos');
check(str_contains(messageFor(404,'model_not_found'),'modelo'),'404 informa modelo no disponible');
check(str_contains(messageFor(429,'insufficient_quota'),'saldo'),'429 de cuota informa saldo');
check(str_contains(messageFor(429,'credit_balance_exhausted'),'saldo'),'429 de créditos informa saldo');
check(!str_contains(messageFor(429,'rate_limit_exceeded'),'saldo'),'límite temporal no se confunde con saldo');
check(str_contains(messageFor(400),'parámetros'),'400 distingue solicitud incompatible');
check(str_contains(messageFor(503),'disponible'),'503 conserva error de servicio');
try { AiClient::decodeHttpResponse(false,0); throw new LogicException('Faltó error de transporte.'); }
catch (RuntimeException $error) { check(str_contains($error->getMessage(),'conectar'),'fallo de transporte informa conexión'); }
try { AiClient::decodeHttpResponse('<html>invalid</html>',200); throw new LogicException('Aceptó respuesta inválida.'); }
catch (RuntimeException $error) { check(str_contains($error->getMessage(),'inválida'),'respuesta no JSON rechazada'); }
check(AiClient::decodeHttpResponse('{"status":"completed"}',200)['status']==='completed','respuesta HTTP válida se conserva');
check(!str_contains(messageFor(401),'PRIVATE'),'401 sin código también usa mensaje seguro');
