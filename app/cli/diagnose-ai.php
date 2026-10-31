<?php
// Diagnóstico explícito de metadatos: no genera respuestas ni envía conversaciones.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../core/AiConfig.php';
$config=AiConfig::load();
if (!extension_loaded('curl') || $config['api_key']==='' || $config['model']==='') {
    echo json_encode(['status'=>'configuration_incomplete']),PHP_EOL; exit(1);
}
$curl=curl_init('https://api.openai.com/v1/models/'.rawurlencode($config['model']));
curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$config['api_key']]]);
$body=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); $errno=curl_errno($curl); curl_close($curl);
$decoded=$body!==false ? json_decode($body,true) : null;
$known=['invalid_api_key','model_not_found','insufficient_permissions','ip_not_authorized','unsupported_country_region_territory','insufficient_quota','credit_balance_exhausted','rate_limit_exceeded'];
$code=is_array($decoded) ? ($decoded['error']['code'] ?? null) : null;
// Nunca imprimir el cuerpo, el mensaje del proveedor, la clave ni las cabeceras.
echo json_encode(['model'=>$config['model'],'http_status'=>$status,'curl_errno'=>$errno,'error_code'=>in_array($code,$known,true) ? $code : null,'metadata_access'=>$status===200,'note'=>'Model metadata access does not validate Responses API permissions or structured output.'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
unset($config,$body,$decoded);
exit($status===200 ? 0 : 1);
