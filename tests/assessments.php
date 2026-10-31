<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function checkAssessment(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function rejectAssessment(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException $error) { checkAssessment(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
$db=Database::connect(); $users=[]; $courseId=null;
try {
    foreach (['instructor','instructor','practicante','practicante','practicante'] as $role) {
        $st=$db->prepare('SELECT id FROM roles WHERE nombre=?'); $st->execute([$role]);
        $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Evaluacion',?,?)")->execute([$st->fetchColumn(),bin2hex(random_bytes(8)).'@inmova.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
        $users[]=(int)$db->lastInsertId();
    }
    $teacher=['id'=>$users[0],'rol'=>'instructor']; $other=['id'=>$users[1],'rol'=>'instructor'];
    $data=['titulo'=>'QA Examen temporal','descripcion'=>'Curso de prueba','instructor_id'=>$teacher['id'],'publicado'=>0,'estado'=>1];
    $courseId=Academy::saveCourse($data,null,$teacher);
    $lessonData=['titulo'=>'Lección','contenido'=>'Lectura','video_url'=>'','orden'=>1,'estado'=>1];
    $lesson=Academy::saveLesson($courseId,null,$lessonData,$teacher);
    $data['publicado']=1; Academy::saveCourse($data,$courseId,$teacher);
    $examData=['titulo'=>'Examen final','nota_minima'=>'13.33','estado'=>0];
    $examId=Assessment::saveExam($courseId,$examData,$teacher);
    $examData['estado']=1;
    rejectAssessment(fn()=>Assessment::saveExam($courseId,$examData,$teacher),'examen sin preguntas no se activa');
    rejectAssessment(fn()=>Assessment::saveExam($courseId,$examData,$other),'instructor ajeno no modifica examen');
    $questionData=['enunciado'=>'Pregunta de prueba','opcion_a'=>'A','opcion_b'=>'B','opcion_c'=>'0','opcion_d'=>'','correcta'=>'a'];
    $first=Assessment::saveQuestion($courseId,null,$questionData,$teacher);
    $questionData['correcta']='b'; $second=Assessment::saveQuestion($courseId,null,$questionData,$teacher);
    $questionData['correcta']='c'; $third=Assessment::saveQuestion($courseId,null,$questionData,$teacher);
    Assessment::saveExam($courseId,$examData,$teacher); $exam=Assessment::exam($courseId);
    foreach ([$users[2],$users[3],$users[4]] as $student) Academy::enroll($courseId,$student);
    $correct=[$first=>'a',$second=>'b',$third=>'c']; $wrong=[$first=>'b',$second=>'a',$third=>'a'];
    rejectAssessment(fn()=>Assessment::submit($courseId,$users[2],$correct,(int)$exam['version'],bin2hex(random_bytes(16))),'no rendir sin completar lecciones');
    Academy::complete($lesson,$users[2],true); Academy::complete($lesson,$users[3],true);
    $staleVersion=(int)$exam['version']; Assessment::saveExam($courseId,$examData,$teacher); $exam=Assessment::exam($courseId);
    rejectAssessment(fn()=>Assessment::submit($courseId,$users[2],$correct,$staleVersion,bin2hex(random_bytes(16))),'formulario de versión anterior rechazado');
    rejectAssessment(fn()=>Assessment::submit($courseId,$users[2],[$first=>'a'],(int)$exam['version'],bin2hex(random_bytes(16))),'respuestas incompletas rechazadas sin consumir intento');
    checkAssessment(!array_key_exists('correcta',Assessment::questions($examId)[0]),'claves correctas no se incluyen en preguntas del alumno');
    $key=bin2hex(random_bytes(16)); $failed=Assessment::submit($courseId,$users[2],$wrong,(int)$exam['version'],$key);
    checkAssessment(!(bool)Assessment::attempt($failed)['aprobada'],'intento incorrecto no aprueba');
    checkAssessment(Assessment::submit($courseId,$users[2],$wrong,(int)$exam['version'],$key)===$failed,'reenvío de formulario no consume otro intento');
    $enrollment=Academy::enrollment($courseId,$users[2]);
    checkAssessment(count(Assessment::attempts($examId,(int)$enrollment['id']))===1 && !Assessment::certificate((int)$enrollment['id']),'fallar no genera certificado');
    rejectAssessment(fn()=>Assessment::saveExam($courseId,$examData,$teacher),'examen bloqueado tras primer intento');
    rejectAssessment(fn()=>Assessment::saveQuestion($courseId,$first,$questionData,$teacher),'preguntas bloqueadas tras primer intento');
    rejectAssessment(fn()=>Assessment::deleteQuestion($courseId,$first,$teacher),'no borrar preguntas de intentos existentes');
    rejectAssessment(fn()=>Academy::saveLesson($courseId,$lesson,$lessonData,$teacher),'contenido formativo bloqueado tras primer intento');
    $twoRight=$correct; $twoRight[$first]='b';
    $passed=Assessment::submit($courseId,$users[2],$twoRight,(int)$exam['version'],bin2hex(random_bytes(16)));
    $result=Assessment::attempt($passed); $certificate=Assessment::certificate((int)$enrollment['id']);
    checkAssessment((float)$result['nota']===13.33 && (bool)$result['aprobada'],'nota redondeada y umbral exacto correctos');
    checkAssessment($certificate && (int)$certificate['intento_id']===$passed && strlen($certificate['codigo'])===36,'aprobación genera certificado único y verificable');
    checkAssessment((int)Assessment::certificateByCode($certificate['codigo'])['id']===(int)$certificate['id'],'verificación por código recupera certificado');
    $detail=json_decode($result['detalle'],true,512,JSON_THROW_ON_ERROR);
    checkAssessment(!array_key_exists('correcta',$detail[0]),'resultado histórico no contiene claves de respuesta');
    rejectAssessment(fn()=>Assessment::submit($courseId,$users[2],$correct,(int)$exam['version'],bin2hex(random_bytes(16))),'no repetir examen aprobado');
    rejectAssessment(fn()=>Academy::complete($lesson,$users[2],false),'no deshacer progreso certificado');
    for ($i=0;$i<3;$i++) Assessment::submit($courseId,$users[3],$wrong,(int)$exam['version'],bin2hex(random_bytes(16)));
    rejectAssessment(fn()=>Assessment::submit($courseId,$users[3],$correct,(int)$exam['version'],bin2hex(random_bytes(16))),'límite de tres intentos');
    $secondEnrollment=Academy::enrollment($courseId,$users[3]);
    checkAssessment(!Assessment::certificate((int)$secondEnrollment['id']),'intentos fallidos no afectan certificado ajeno');
    $data['titulo']='Título actualizado'; Academy::saveCourse($data,$courseId,$teacher);
    $db->prepare("UPDATE usuarios SET nombres='Nombre actualizado' WHERE id=?")->execute([$users[2]]);
    $snapshot=Assessment::certificateByCode($certificate['codigo']);
    checkAssessment($snapshot['titulo_curso']==='QA Examen temporal' && $snapshot['nombre_alumno']==='QA Evaluacion','certificado conserva datos de emisión');
    $data['estado']=0; Academy::saveCourse($data,$courseId,$teacher);
    checkAssessment((bool)Assessment::certificateByCode($certificate['codigo']),'archivar curso conserva verificación del certificado');
    rejectAssessment(fn()=>Assessment::submit($courseId,$users[4],$correct,(int)$exam['version'],bin2hex(random_bytes(16))),'curso archivado no admite exámenes');
} finally {
    if ($courseId) {
        $db->prepare('DELETE c FROM certificados c JOIN inscripciones i ON i.id=c.inscripcion_id WHERE i.curso_id=?')->execute([$courseId]);
        $db->prepare('DELETE r FROM respuestas r JOIN inscripciones i ON i.id=r.inscripcion_id WHERE i.curso_id=?')->execute([$courseId]);
        $db->prepare('DELETE a FROM evaluacion_intentos a JOIN evaluaciones e ON e.id=a.evaluacion_id WHERE e.curso_id=?')->execute([$courseId]);
        $db->prepare('DELETE FROM inscripciones WHERE curso_id=?')->execute([$courseId]);
        $db->prepare('DELETE p FROM preguntas p JOIN evaluaciones e ON e.id=p.evaluacion_id WHERE e.curso_id=?')->execute([$courseId]);
        $db->prepare('DELETE FROM cursos WHERE id=?')->execute([$courseId]);
    }
    foreach ($users as $id) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
}
