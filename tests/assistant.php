<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function checkAssistant(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function rejectAssistant(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException|RuntimeException $error) { checkAssistant(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
function aiResponse(array $data): array { return ['status'=>'completed','output'=>[['type'=>'message','role'=>'assistant','content'=>[['type'=>'output_text','text'=>json_encode($data,JSON_THROW_ON_ERROR)]]]]]; }
$db=Database::connect(); $properties=[]; $tokens=[]; $leadId=null; $clientId=null; $sellerId=null; $quotaDay='2099-01-01'; $cleanQuota=false;
try {
    $adminId=(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' LIMIT 1")->fetchColumn();
    if (!$adminId) throw new RuntimeException('Se requiere superadministrador local.');
    $admin=['id'=>$adminId,'rol'=>'superadmin'];
    $type=(int)$db->query('SELECT id FROM tipos_propiedad WHERE estado=1 LIMIT 1')->fetchColumn();
    $mode=(int)$db->query('SELECT id FROM modalidades WHERE estado=1 LIMIT 1')->fetchColumn();
    foreach ([[1,1,'disponible'],[0,1,'disponible'],[1,0,'disponible'],[1,1,'reservado']] as [$published,$active,$availability]) {
        $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda,publicado,estado,disponibilidad) VALUES (?,?,?,'__ASSISTANT_QA__',1000,'PEN',?,?,?)")->execute([$type,$mode,$adminId,$published,$active,$availability]); $properties[]=(int)$db->lastInsertId();
    }
    $filters=Assistant::defaults(); $filters['q']='__ASSISTANT_QA__'; $filters['moneda']='PEN'; $filters['max']='1000';
    $results=Assistant::search($filters);
    checkAssistant($results['total']===1 && (int)$results['properties'][0]['id']===$properties[0],'solo inmuebles activos publicados y disponibles');
    checkAssistant(count(Assistant::cards($properties))===1,'tarjetas revalidan visibilidad y disponibilidad');
    $bad=$filters; $bad['moneda']=''; rejectAssistant(fn()=>Assistant::validateFilters($bad),'no filtrar precio sin moneda');
    $bad=$filters; $bad['max']='5000001'; rejectAssistant(fn()=>Assistant::validateFilters($bad),'máximo cinco millones');
    $bad=$filters; $bad['max']='1.5'; rejectAssistant(fn()=>Assistant::validateFilters($bad),'presupuesto solo entero');
    $token=bin2hex(random_bytes(32)); $tokens[]=$token; $one=Assistant::open($token);
    $second=bin2hex(random_bytes(32)); $tokens[]=$second; $two=Assistant::open($second);
    Assistant::record($token,'Consulta guiada','Resultado guiado',$filters,[$properties[0]],'catalogo');
    checkAssistant(count(Assistant::messages((int)$one['id']))===2 && !Assistant::messages((int)$two['id']),'conversaciones aisladas por sesión');
    $config=['enabled'=>true,'api_key'=>'qa-not-a-real-key','model'=>'qa-test-model','daily_limit'=>2,'ip_limit'=>1];
    $base=['reply'=>'Voy a buscar opciones.','intent'=>'search','filters'=>['q'=>'__ASSISTANT_QA__','tipo_id'=>$type,'modalidad_id'=>$mode,'moneda'=>'PEN','min'=>null,'max'=>1000]];
    $captured=null;
    $transport=function ($payload) use (&$captured,$base) { $captured=$payload; return aiResponse($base); };
    $answer=(new AiClient($config,$transport))->interpret([['emisor'=>'cliente','contenido'=>'Mi correo qa@example.test y teléfono 999123456']],Property::catalogs(),$filters,Assistant::cards([$properties[0]]));
    checkAssistant($answer['filters']['max']==='1000','respuesta estructurada validada');
    $packet=json_encode($captured,JSON_THROW_ON_ERROR);
    checkAssistant($captured['store']===false && $captured['text']['format']['strict']===true,'solicitud usa JSON schema y store false');
    checkAssistant(!str_contains($packet,'qa@example.test') && !str_contains($packet,'999123456') && !str_contains($packet,$config['api_key']),'datos de contacto evidentes y clave fuera del cuerpo enviado');
    $invalid=$base; $invalid['filters']['tipo_id']=999999;
    rejectAssistant(fn()=>(new AiClient($config,fn($p)=>aiResponse($invalid)))->interpret([],Property::catalogs(),$filters,[]),'IDs inventados por modelo rechazados');
    rejectAssistant(fn()=>(new AiClient($config,fn($p)=>['status'=>'incomplete']))->interpret([],Property::catalogs(),$filters,[]),'respuesta incompleta del proveedor controlada');
    rejectAssistant(fn()=>(new AiClient($config,fn($p)=>['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'refusal']]]]]))->interpret([],Property::catalogs(),$filters,[]),'rechazo del proveedor controlado');
    $st=$db->prepare('SELECT COUNT(*) FROM ia_cuotas WHERE dia=?'); $st->execute([$quotaDay]);
    if ((int)$st->fetchColumn()!==0) throw new RuntimeException('La fecha reservada para cuotas de prueba ya tiene datos.'); $cleanQuota=true;
    $now=new DateTimeImmutable($quotaDay.' 10:00:00',new DateTimeZone('America/Lima')); $hash=hash('sha256','qa-ip1');
    Assistant::reserveAi($token,$hash,$config,$now);
    $wide=$config; $wide['daily_limit']=5; $wide['ip_limit']=5;
    rejectAssistant(fn()=>Assistant::reserveAi($token,$hash,$wide,$now->modify('+1 second')),'pausa entre mensajes controlada');
    rejectAssistant(fn()=>Assistant::reserveAi($token,$hash,$config,$now->modify('+6 seconds')),'límite diario por IP');
    Assistant::reserveAi($second,hash('sha256','qa-ip2'),$config,$now->modify('+6 seconds'));
    rejectAssistant(fn()=>Assistant::reserveAi($second,hash('sha256','qa-ip3'),$config,$now->modify('+12 seconds')),'límite diario global');
    $st=$db->prepare("SELECT solicitudes FROM ia_cuotas WHERE dia=? AND clave='global'"); $st->execute([$quotaDay]);
    checkAssistant((int)$st->fetchColumn()===2,'fallos de cuota revierten incrementos parciales');
    $contact=['nombres'=>'QA Asistente','email'=>'qa-assistant@inmova.test','telefono'=>'','mensaje'=>'Consulta de prueba'];
    rejectAssistant(fn()=>Assistant::handoff($token,$properties[1],$contact),'no derivar inmueble borrador');
    $leadId=Assistant::handoff($token,$properties[0],$contact); $lead=Lead::find($leadId); $clientId=(int)$lead['cliente_id'];
    checkAssistant($lead['origen']==='agente_ia' && Assistant::owned($token)['estado']==='derivada','derivación registra lead y vincula conversación');
    checkAssistant(Assistant::handoff($token,$properties[0],$contact)===$leadId,'derivación idempotente');
    checkAssistant(count(Assistant::forLead($leadId,$admin))===3,'CRM autorizado recupera conversación');
    rejectAssistant(fn()=>Assistant::forLead($leadId,['id'=>$adminId,'rol'=>'vendedor']),'vendedor sin asignación no lee conversación');
    rejectAssistant(fn()=>Assistant::record($token,'Otro','Otro',$filters,[],'catalogo'),'conversación derivada no admite más mensajes');
    Assistant::close($second); checkAssistant(Assistant::owned($second)['estado']==='cerrada','cierre de conversación');
    checkAssistant(!AiConfig::ready(['enabled'=>false,'api_key'=>'','model'=>'']),'sin configuración no se anuncia IA activa');
    echo 'Pruebas del cliente IA usan transporte simulado; no hubo llamadas a OpenAI.'.PHP_EOL;
} finally {
    foreach ($tokens as $token) $db->prepare('DELETE FROM conversaciones_ia WHERE sesion=?')->execute([$token]);
    if ($leadId) $db->prepare('DELETE FROM leads WHERE id=?')->execute([$leadId]);
    if ($clientId) $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$clientId]);
    foreach ($properties as $id) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$id]);
    if ($cleanQuota) $db->prepare('DELETE FROM ia_cuotas WHERE dia=?')->execute([$quotaDay]);
}
