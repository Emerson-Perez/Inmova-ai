<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function checkAcademy(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function rejectAcademy(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException $error) { checkAcademy(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
$db=Database::connect(); $users=[]; $courses=[];
try {
    $adminId=(int)$db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='superadmin' LIMIT 1")->fetchColumn();
    if (!$adminId) throw new RuntimeException('Se requiere superadministrador local.');
    $admin=['id'=>$adminId,'rol'=>'superadmin'];
    foreach (['instructor','instructor','practicante','practicante'] as $role) {
        $st=$db->prepare('SELECT id FROM roles WHERE nombre=?'); $st->execute([$role]); $roleId=$st->fetchColumn();
        $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Academia',?,?)")->execute([$roleId,bin2hex(random_bytes(8)).'@inmova.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
        $users[]=(int)$db->lastInsertId();
    }
    $teacher=['id'=>$users[0],'rol'=>'instructor']; $otherTeacher=['id'=>$users[1],'rol'=>'instructor']; $student=['id'=>$users[2],'rol'=>'practicante'];
    $data=['titulo'=>'QA Academia temporal','descripcion'=>'Curso de prueba','instructor_id'=>$teacher['id'],'publicado'=>0,'estado'=>1];
    $course=Academy::saveCourse($data,null,$teacher); $courses[]=$course;
    $data['publicado']=1;
    rejectAcademy(fn()=>Academy::saveCourse($data,$course,$teacher),'curso sin lecciones no se publica');
    rejectAcademy(fn()=>Academy::enroll($course,$student['id']),'no inscribirse en borrador');
    rejectAcademy(fn()=>Academy::saveCourse($data,$course,$otherTeacher),'instructor no edita curso ajeno');
    rejectAcademy(fn()=>Academy::saveCourse($data,null,$student),'alumno no crea cursos');
    $lessonData=['titulo'=>'Lección 1','contenido'=>'<script>Contenido de prueba</script>','video_url'=>'','orden'=>1,'estado'=>1];
    $first=Academy::saveLesson($course,null,$lessonData,$teacher);
    $lessonData['titulo']='Lección 2'; $lessonData['orden']=2; $second=Academy::saveLesson($course,null,$lessonData,$teacher);
    rejectAcademy(fn()=>Academy::saveLesson($course,$first,$lessonData,$otherTeacher),'instructor no altera lección ajena');
    Academy::saveCourse($data,$course,$teacher);
    checkAcademy((bool)Academy::course($course)['publicado'],'curso con lecciones se publica');
    Academy::enroll($course,$student['id']); Academy::enroll($course,$student['id']);
    $enrollment=Academy::enrollment($course,$student['id']);
    $st=$db->prepare('SELECT COUNT(*) FROM inscripciones WHERE curso_id=? AND usuario_id=?'); $st->execute([$course,$student['id']]);
    checkAcademy((int)$st->fetchColumn()===1,'inscripción idempotente');
    rejectAcademy(fn()=>Academy::complete($first,$users[3],true),'no registrar progreso sin inscripción');
    Academy::complete($first,$student['id'],true);
    checkAcademy(Academy::progress(Academy::lessons($course,(int)$enrollment['id']))['percent']===50,'primera lección da 50 por ciento');
    Academy::complete($second,$student['id'],true);
    checkAcademy(Academy::enrollment($course,$student['id'])['estado']==='completada','todas las lecciones completan inscripción');
    Academy::complete($first,$student['id'],false);
    checkAcademy(Academy::enrollment($course,$student['id'])['estado']==='activa','se puede volver a pendiente');
    Academy::complete($first,$student['id'],true);
    $lessonData['titulo']='Lección nueva'; $third=Academy::saveLesson($course,null,$lessonData,$teacher);
    checkAcademy(Academy::enrollment($course,$student['id'])['estado']==='activa','agregar lección reabre progreso');
    $lessonData['estado']=0; Academy::saveLesson($course,$third,$lessonData,$teacher);
    checkAcademy(Academy::enrollment($course,$student['id'])['estado']==='completada','retirar lección recalcula progreso');
    Academy::enroll($course,$users[3]);
    $other=Academy::enrollment($course,$users[3]);
    checkAcademy(Academy::progress(Academy::lessons($course,(int)$other['id']))['done']===0,'progreso aislado por alumno');
    checkAcademy(count(Academy::students($course))===2,'instructor puede consultar alumnos inscritos');
    $data['estado']=0; Academy::saveCourse($data,$course,$teacher);
    checkAcademy(!Academy::course($course)['publicado'] && (bool)Academy::enrollment($course,$student['id']),'archivar conserva inscripción y retira publicación');
    rejectAcademy(fn()=>Academy::complete($first,$student['id'],false),'no modificar progreso en curso archivado');
    $data['estado']=1; $data['publicado']=1; Academy::saveCourse($data,$course,$teacher);
    Academy::saveLesson($course,$first,$lessonData,$teacher); Academy::saveLesson($course,$second,$lessonData,$teacher);
    checkAcademy(!Academy::course($course)['publicado'],'retirar última lección vuelve a borrador');
    rejectAcademy(fn()=>Academy::courses($student,true),'alumno no accede al catálogo de gestión');
} finally {
    foreach ($courses as $courseId) {
        $db->prepare('DELETE FROM inscripciones WHERE curso_id=?')->execute([$courseId]);
        $db->prepare('DELETE FROM cursos WHERE id=?')->execute([$courseId]);
    }
    foreach ($users as $userId) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$userId]);
}
