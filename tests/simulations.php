<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function checkSimulation(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function rejectSimulation(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException|RuntimeException $error) { checkSimulation(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
function simulationResponse(array $data): array { return ['status'=>'completed','output'=>[['type'=>'message','role'=>'assistant','content'=>[['type'=>'output_text','text'=>json_encode($data,JSON_THROW_ON_ERROR)]]]]]; }
$db=Database::connect(); $users=[]; $day='2032-07-01'; $cleanQuota=false;
try {
    foreach (['vendedor','practicante','instructor','cliente'] as $role) {
        $st=$db->prepare('SELECT id FROM roles WHERE nombre=?'); $st->execute([$role]);
        $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Simulador',?,?)")->execute([$st->fetchColumn(),bin2hex(random_bytes(8)).'@inmova.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]); $users[]=(int)$db->lastInsertId();
    }
    $seller=['id'=>$users[0],'rol'=>'vendedor']; $other=['id'=>$users[1],'rol'=>'practicante']; $teacher=['id'=>$users[2],'rol'=>'instructor']; $client=['id'=>$users[3],'rol'=>'cliente'];
    rejectSimulation(fn()=>Simulation::start('precio','guiada',$client,false),'cliente sin acceso al entrenamiento');
    rejectSimulation(fn()=>Simulation::start('inexistente','guiada',$seller,false),'escenario inválido rechazado');
    rejectSimulation(fn()=>Simulation::start('precio','ia',$seller,false),'IA desactivada no inicia práctica IA');
    $guided=Simulation::start('precio','guiada',$seller,false);
    checkSimulation(count(Simulation::transcript(Simulation::find($guided,$seller)))===1,'inicio por guion sin llamadas a IA');
    checkSimulation(Simulation::find($guided,$other)===null && Simulation::find($guided,$teacher)!==null,'privacidad del participante y lectura de instructor');
    rejectSimulation(fn()=>Simulation::guidedTurn($guided,'Hola',bin2hex(random_bytes(16)),$teacher),'supervisor no responde por participante');
    rejectSimulation(fn()=>Simulation::listing($seller,true),'vendedor no revisa equipo');
    rejectSimulation(fn()=>Simulation::finishGuided($guided,$seller),'mínimo tres respuestas para finalizar');
    $key=bin2hex(random_bytes(16)); Simulation::guidedTurn($guided,'¿Qué es lo que más te importa comparar?',$key,$seller); Simulation::guidedTurn($guided,'Reenvío',$key,$seller);
    checkSimulation((int)Simulation::find($guided,$seller)['turnos']===1,'reenvío guiado no duplica turno');
    foreach ([2,3] as $unused) Simulation::guidedTurn($guided,'Confirmaría los datos con el equipo antes de prometer condiciones.',bin2hex(random_bytes(16)),$seller);
    Simulation::finishGuided($guided,$seller); $finished=Simulation::find($guided,$seller);
    checkSimulation($finished['estado']==='finalizada' && $finished['puntaje']===null,'guiada finaliza con autoevaluación y sin nota inventada');
    rejectSimulation(fn()=>Simulation::guidedTurn($guided,'Otra respuesta',bin2hex(random_bytes(16)),$seller),'historial finalizado no admite mensajes');
    $abandoned=Simulation::start('indeciso','guiada',$seller,false); Simulation::abandon($abandoned,$seller);
    checkSimulation(Simulation::find($abandoned,$seller)['estado']==='abandonada','abandono conserva historial');
    $now=new DateTimeImmutable($day.' 10:00:00',new DateTimeZone('America/Lima'));
    $st=$db->prepare('SELECT COUNT(*) FROM ia_cuotas WHERE dia=?'); $st->execute([$day]);
    if ((int)$st->fetchColumn()!==0) throw new RuntimeException('La fecha de pruebas ya tiene cuotas.'); $cleanQuota=true;
    $config=['enabled'=>true,'api_key'=>'qa-not-a-real-key','model'=>'qa-test-model','daily_limit'=>100,'ip_limit'=>100]; $hash=hash('sha256','qa-simulation-ip');
    $ai=Simulation::start('primer_comprador','ia',$seller,true,$now);
    rejectSimulation(fn()=>Simulation::claim($ai,'evaluate',bin2hex(random_bytes(16)),$seller,$config,$hash,$now),'no evaluar antes de tres respuestas');
    $captured=null;
    $replyCoach=new SimulationCoach(new AiClient($config,function ($payload) use (&$captured) { $captured=$payload; return simulationResponse(['reply'=>'Entiendo. ¿Qué opciones podríamos comparar?']); }));
    for ($i=0;$i<3;$i++) {
        $key=bin2hex(random_bytes(16)); $at=$now->modify('+'.($i*10).' seconds');
        $claimed=Simulation::claim($ai,'message',$key,$seller,$config,$hash,$at);
        if ($i===0) rejectSimulation(fn()=>Simulation::claim($ai,'message',bin2hex(random_bytes(16)),$seller,$config,$hash,$at->modify('+6 seconds')),'solicitud simultánea bloqueada');
        $text='Preguntaría por tus necesidades. Mi correo es qa@example.test y teléfono 999123456.';
        $reply=$replyCoach->reply($claimed,$text); Simulation::finishTurn($ai,$key,$text,$reply,$config['model'],$seller);
        checkSimulation(Simulation::claim($ai,'message',$key,$seller,$config,$hash,$at)===null,'reenvío IA no consume solicitud adicional');
    }
    $packet=json_encode($captured,JSON_THROW_ON_ERROR);
    checkSimulation(!str_contains($packet,'qa@example.test') && !str_contains($packet,'999123456') && !str_contains($packet,$config['api_key']) && $captured['store']===false,'datos de contacto evidentes omitidos y store false');
    $feedback=['scores'=>['escucha'=>17,'necesidades'=>16,'claridad'=>15,'objeciones'=>14,'siguiente_paso'=>18],'fortalezas'=>['Hizo preguntas antes de proponer.'],'mejoras'=>['Acordar una fecha concreta.'],'siguiente_practica'=>'Practicar un resumen del siguiente paso.','puntaje'=>100];
    checkSimulation(SimulationCoach::validateFeedback($feedback)['puntaje']===80,'nota total se calcula en servidor y no confía en total del modelo');
    $invalid=$feedback; $invalid['scores']['escucha']=21; rejectSimulation(fn()=>SimulationCoach::validateFeedback($invalid),'rúbrica fuera de rango rechazada');
    $evalKey=bin2hex(random_bytes(16)); $claimed=Simulation::claim($ai,'evaluate',$evalKey,$seller,$config,$hash,$now->modify('+40 seconds'));
    $coach=new SimulationCoach(new AiClient($config,fn($payload)=>simulationResponse($feedback)));
    Simulation::finishEvaluation($ai,$evalKey,$coach->evaluate($claimed),$config['model'],$seller); $evaluated=Simulation::find($ai,$seller);
    checkSimulation($evaluated['estado']==='evaluada' && (float)$evaluated['puntaje']===80.0 && $evaluated['modelo_evaluacion']==='qa-test-model','retroalimentación guarda modelo, puntaje y resultado');
    rejectSimulation(fn()=>Simulation::claim($ai,'message',bin2hex(random_bytes(16)),$seller,$config,$hash,$now->modify('+50 seconds')),'práctica evaluada no cambia');
    $retry=Simulation::start('alquiler','ia',$seller,true,$now); $key=bin2hex(random_bytes(16));
    Simulation::claim($retry,'message',$key,$seller,$config,$hash,$now->modify('+60 seconds')); Simulation::release($retry,$key,$seller);
    checkSimulation(Simulation::find($retry,$seller)['solicitud_pendiente']===null && (int)Simulation::find($retry,$seller)['turnos']===0,'fallo del proveedor libera solicitud y no agrega turno');
    $oldKey=bin2hex(random_bytes(16)); Simulation::claim($retry,'message',$oldKey,$seller,$config,$hash,$now->modify('+70 seconds'));
    $newKey=bin2hex(random_bytes(16)); Simulation::claim($retry,'message',$newKey,$seller,$config,$hash,$now->modify('+131 seconds'));
    rejectSimulation(fn()=>Simulation::finishTurn($retry,$oldKey,'Respuesta','Tardía',$config['model'],$seller),'respuesta tardía no sobrescribe una solicitud nueva');
    Simulation::release($retry,$newKey,$seller);
    for ($i=0;$i<3;$i++) Simulation::start('precio','ia',$seller,true,$now);
    rejectSimulation(fn()=>Simulation::start('precio','ia',$seller,true,$now),'máximo cinco prácticas IA diarias');
    $cap=Simulation::start('precio','guiada',$other,false);
    for ($i=0;$i<Simulation::MAX_TURNS;$i++) Simulation::guidedTurn($cap,'Practicaré una propuesta clara.',bin2hex(random_bytes(16)),$other);
    rejectSimulation(fn()=>Simulation::guidedTurn($cap,'Otra',bin2hex(random_bytes(16)),$other),'máximo ocho respuestas por práctica');
    echo 'Proveedor simulado: no se hicieron llamadas a OpenAI ni se modificó su configuración.'.PHP_EOL;
} finally {
    foreach ($users as $id) { $db->prepare('DELETE FROM simulaciones_ventas WHERE usuario_id=?')->execute([$id]); $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]); }
    if ($cleanQuota) $db->prepare('DELETE FROM ia_cuotas WHERE dia=?')->execute([$day]);
}
