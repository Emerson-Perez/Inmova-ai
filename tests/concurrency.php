<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
$db=Database::connect();
if (($argv[1] ?? '')==='worker') {
    $input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
    $db->exec('SET SESSION innodb_lock_wait_timeout=15');
    echo json_encode(['connection'=>(int)$db->query('SELECT CONNECTION_ID()')->fetchColumn()]),PHP_EOL; flush();
    try {
        if ($input['mode']==='reservation') Reservation::create($input['lead'],$input['seller'],100,$input['admin']);
        elseif ($input['mode']==='document') PropertyDocument::setActive($input['document'],true,$input['admin']);
        else Academy::complete($input['lesson'],$input['student'],true);
        echo json_encode(['result'=>'saved']),PHP_EOL;
    } catch (DomainException $error) { echo json_encode(['result'=>'rejected']),PHP_EOL; }
    exit;
}
function race(PDO $db, int $property, array $payloads, string $table='propiedades', ?callable $change=null): array {
    if (!in_array($table,['propiedades','cursos'],true)) throw new RuntimeException('Barrera inválida.');
    $children=[];
    $db->beginTransaction();
    $st=$db->prepare("SELECT id FROM $table WHERE id=? FOR UPDATE"); $st->execute([$property]);
    try {
        foreach ($payloads as $payload) {
            $pipes=[];
            $process=proc_open([PHP_BINARY,__FILE__,'worker'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
            if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar el proceso de prueba.');
            fwrite($pipes[0],json_encode($payload,JSON_THROW_ON_ERROR)); fclose($pipes[0]);
            $ready=json_decode(fgets($pipes[1]),true,512,JSON_THROW_ON_ERROR);
            $children[]=['process'=>$process,'pipes'=>$pipes,'connection'=>$ready['connection']];
        }
        // Esperar evidencia de que ambas transacciones leyeron y esperan el bloqueo.
        $ids=implode(',',array_map('intval',array_column($children,'connection')));
        $deadline=microtime(true)+10;
        do {
            $waiting=(int)$db->query("SELECT COUNT(*) FROM information_schema.innodb_trx WHERE trx_mysql_thread_id IN ($ids) AND trx_state='LOCK WAIT'")->fetchColumn();
            if ($waiting===count($payloads)) break;
            usleep(100000);
        } while (microtime(true)<$deadline);
        if ($waiting!==count($payloads)) throw new RuntimeException('No se alcanzó la barrera concurrente; prueba inconclusa.');
        if ($change) $change();
        $db->commit();
        $results=[];
        foreach ($children as &$child) {
            $output=stream_get_contents($child['pipes'][1]); $errors=stream_get_contents($child['pipes'][2]);
            fclose($child['pipes'][1]); fclose($child['pipes'][2]);
            $code=proc_close($child['process']); $child['process']=null;
            if ($code!==0 || $errors!=='') throw new RuntimeException('Falló el proceso concurrente: '.$errors);
            $results[]=json_decode(trim($output),true,512,JSON_THROW_ON_ERROR)['result'];
        }
        unset($child);
        sort($results); return $results;
    } finally {
        if ($db->inTransaction()) $db->rollBack();
        foreach ($children as $child) if (is_resource($child['process'])) {
            foreach ([1,2] as $pipe) if (is_resource($child['pipes'][$pipe])) fclose($child['pipes'][$pipe]);
            proc_close($child['process']);
        }
    }
}
$property=$alternate=$seller=$client=$lead=$course=null; $files=[]; $failed=false;
try {
    $admin=['id'=>(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' AND u.estado=1 LIMIT 1")->fetchColumn(),'rol'=>'superadmin'];
    if (!$admin['id']) throw new RuntimeException('Falta superadministrador local.');
    $role=$db->query("SELECT id FROM roles WHERE nombre='vendedor'")->fetchColumn();
    $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Concurrencia',?,?)")->execute([$role,bin2hex(random_bytes(10)).'@example.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]); $seller=(int)$db->lastInsertId();
    $db->exec("INSERT INTO clientes (nombres) VALUES ('QA concurrencia temporal')"); $client=(int)$db->lastInsertId();
    $type=$db->query('SELECT id FROM tipos_propiedad WHERE estado=1 LIMIT 1')->fetchColumn();
    $mode=$db->query("SELECT id FROM modalidades WHERE slug='venta-deptos' AND estado=1 LIMIT 1")->fetchColumn();
    $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda) VALUES (?,?,?,'QA concurrencia temporal',10000,'PEN')")->execute([$type,$mode,$admin['id']]); $property=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO leads (cliente_id,propiedad_id,vendedor_id,etapa) VALUES (?,?,?,'negociacion')")->execute([$client,$property,$seller]); $lead=(int)$db->lastInsertId();
    $payload=['mode'=>'reservation','lead'=>$lead,'seller'=>$seller,'admin'=>$admin];
    $results=race($db,$property,[$payload,$payload]);
    $ok=$results===['rejected','saved']; $failed=$failed || !$ok;
    echo ($ok ? 'OK: ' : 'FAIL: ').'dos solicitudes simultáneas dejan una sola reserva activa ('.implode(',',$results).')'.PHP_EOL;
    $db->prepare('DELETE FROM reservas WHERE propiedad_id=?')->execute([$property]);
    $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio,moneda) VALUES (?,?,?,'QA concurrencia alternativa temporal',10000,'PEN')")->execute([$type,$mode,$admin['id']]); $alternate=(int)$db->lastInsertId();
    $results=race($db,$property,[$payload],'propiedades',function () use ($db,$alternate,$lead) {
        $db->prepare('UPDATE leads SET propiedad_id=? WHERE id=?')->execute([$alternate,$lead]);
    });
    $ok=$results===['rejected']; $failed=$failed || !$ok;
    echo ($ok ? 'OK: ' : 'FAIL: ').'cambio concurrente de inmueble no crea reserva sobre la propiedad anterior ('.implode(',',$results).')'.PHP_EOL;
    $db->prepare('UPDATE leads SET propiedad_id=? WHERE id=?')->execute([$property,$lead]);
    $documents=[];
    for ($i=0;$i<21;$i++) {
        $name=bin2hex(random_bytes(16)).'.pdf';
        if ($i>=19) { $files[]=DocumentStorage::path($name); file_put_contents(end($files),"%PDF-1.4\nQA"); }
        $db->prepare("INSERT INTO propiedad_documentos (propiedad_id,titulo,categoria,nombre_original,storage_name,tamano,subido_por,estado) VALUES (?,'QA','otro','QA.pdf',?,12,?,?)")->execute([$property,$name,$admin['id'],(int)($i<19)]);
        if ($i>=19) $documents[]=(int)$db->lastInsertId();
    }
    $results=race($db,$property,array_map(fn($id)=>['mode'=>'document','document'=>$id,'admin'=>$admin],$documents));
    $ok=$results===['rejected','saved'] && PropertyDocument::countActive($property)===20; $failed=$failed || !$ok;
    echo ($ok ? 'OK: ' : 'FAIL: ').'restauraciones simultáneas respetan máximo de 20 documentos ('.implode(',',$results).')'.PHP_EOL;
    $courseData=['instructor_id'=>$admin['id'],'titulo'=>'QA concurrencia temporal','descripcion'=>'QA','estado'=>1,'publicado'=>0];
    $course=Academy::saveCourse($courseData,null,$admin);
    $lessonData=['titulo'=>'QA','contenido'=>'QA','video_url'=>'','orden'=>0,'estado'=>1];
    $lesson=Academy::saveLesson($course,null,$lessonData,$admin);
    Academy::saveLesson($course,null,$lessonData,$admin);
    $courseData['publicado']=1; Academy::saveCourse($courseData,$course,$admin); Academy::enroll($course,$seller);
    $results=race($db,$course,[['mode'=>'progress','lesson'=>$lesson,'student'=>$seller]],'cursos',function () use ($db,$lesson) {
        $db->prepare('UPDATE lecciones SET estado=0 WHERE id=?')->execute([$lesson]);
    });
    $ok=$results===['rejected']; $failed=$failed || !$ok;
    echo ($ok ? 'OK: ' : 'FAIL: ').'lección retirada durante la espera no acepta progreso ('.implode(',',$results).')'.PHP_EOL;
} finally {
    if ($course) {
        $db->prepare('DELETE p FROM progreso_cursos p JOIN inscripciones i ON i.id=p.inscripcion_id WHERE i.curso_id=?')->execute([$course]);
        $db->prepare('DELETE FROM inscripciones WHERE curso_id=?')->execute([$course]);
        $db->prepare('DELETE FROM lecciones WHERE curso_id=?')->execute([$course]);
        $db->prepare('DELETE FROM cursos WHERE id=?')->execute([$course]);
    }
    if ($property) {
        $db->prepare('DELETE e FROM documento_eventos e JOIN propiedad_documentos d ON d.id=e.documento_id WHERE d.propiedad_id=?')->execute([$property]);
        $db->prepare('DELETE FROM propiedad_documentos WHERE propiedad_id=?')->execute([$property]);
        $db->prepare('DELETE FROM reservas WHERE propiedad_id=?')->execute([$property]);
    }
    if ($lead) $db->prepare('DELETE FROM leads WHERE id=?')->execute([$lead]);
    if ($property) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$property]);
    if ($alternate) $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$alternate]);
    if ($client) $db->prepare('DELETE FROM clientes WHERE id=?')->execute([$client]);
    if ($seller) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$seller]);
    foreach ($files as $file) if (is_file($file)) unlink($file);
}
exit($failed ? 1 : 0);
