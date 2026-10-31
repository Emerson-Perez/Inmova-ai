<?php
class Assessment
{
    public const MAX_ATTEMPTS=3;
    public static function exam(int $course): ?array
    {
        $st=Database::connect()->prepare('SELECT * FROM evaluaciones WHERE curso_id=? AND es_final=1 ORDER BY id LIMIT 1'); $st->execute([$course]); return $st->fetch() ?: null;
    }
    public static function questions(int $exam, bool $keys=false): array
    {
        $st=Database::connect()->prepare('SELECT id,evaluacion_id,enunciado,opcion_a,opcion_b,opcion_c,opcion_d'.($keys ? ',correcta' : '').' FROM preguntas WHERE evaluacion_id=? ORDER BY id'); $st->execute([$exam]); return $st->fetchAll();
    }
    public static function hasAttempts(int $course): bool
    {
        $st=Database::connect()->prepare('SELECT a.id FROM evaluacion_intentos a JOIN evaluaciones e ON e.id=a.evaluacion_id WHERE e.curso_id=? LIMIT 1'); $st->execute([$course]); return (bool)$st->fetchColumn();
    }
    public static function attempts(int $exam, int $enrollment): array
    {
        $st=Database::connect()->prepare('SELECT id,nota,nota_minima,aprobada,created_at FROM evaluacion_intentos WHERE evaluacion_id=? AND inscripcion_id=? ORDER BY id'); $st->execute([$exam,$enrollment]); return $st->fetchAll();
    }
    public static function attempt(int $id): ?array
    {
        $st=Database::connect()->prepare('SELECT a.*,i.usuario_id,e.curso_id FROM evaluacion_intentos a JOIN inscripciones i ON i.id=a.inscripcion_id JOIN evaluaciones e ON e.id=a.evaluacion_id WHERE a.id=?'); $st->execute([$id]); return $st->fetch() ?: null;
    }
    public static function certificate(int $enrollment): ?array
    {
        $st=Database::connect()->prepare('SELECT * FROM certificados WHERE inscripcion_id=?'); $st->execute([$enrollment]); return $st->fetch() ?: null;
    }
    public static function certificateByCode(string $code): ?array
    {
        $st=Database::connect()->prepare('SELECT c.*,i.usuario_id,i.curso_id FROM certificados c JOIN inscripciones i ON i.id=c.inscripcion_id WHERE c.codigo=?'); $st->execute([$code]); return $st->fetch() ?: null;
    }
    public static function myCertificates(int $user): array
    {
        $st=Database::connect()->prepare('SELECT c.* FROM certificados c JOIN inscripciones i ON i.id=c.inscripcion_id WHERE i.usuario_id=? ORDER BY c.id DESC LIMIT 100'); $st->execute([$user]); return $st->fetchAll();
    }
    private static function lock(int $course, array $user): array
    {
        $st=Database::connect()->prepare('SELECT * FROM cursos WHERE id=? FOR UPDATE'); $st->execute([$course]); $row=$st->fetch();
        if (!$row || !Academy::canManage($row,$user)) throw new DomainException('No tienes permiso para gestionar este examen.');
        if (self::hasAttempts($course)) throw new DomainException('El examen y las lecciones no se pueden modificar después del primer intento. Crea otro curso para una nueva versión.');
        return $row;
    }
    public static function saveExam(int $course, array $data, array $user): int
    {
        if ($data['titulo']==='' || mb_strlen($data['titulo'])>160 || !is_numeric($data['nota_minima']) || (float)$data['nota_minima']<=0 || (float)$data['nota_minima']>20) throw new DomainException('Indica un título y una nota mínima mayor que 0 y hasta 20.');
        $db=Database::connect(); $db->beginTransaction();
        try {
            self::lock($course,$user); $old=self::exam($course);
            if ($data['estado'] && (!$old || !self::questions((int)$old['id']))) throw new DomainException('Agrega al menos una pregunta antes de activar el examen. Primero guárdalo como borrador.');
            if ($old) { $db->prepare('UPDATE evaluaciones SET titulo=?,nota_minima=?,estado=?,version=version+1 WHERE id=?')->execute([$data['titulo'],$data['nota_minima'],$data['estado'],$old['id']]); $id=(int)$old['id']; }
            else { $db->prepare('INSERT INTO evaluaciones (curso_id,titulo,nota_minima,es_final,estado) VALUES (?,?,?,1,?)')->execute([$course,$data['titulo'],$data['nota_minima'],$data['estado']]); $id=(int)$db->lastInsertId(); }
            $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function saveQuestion(int $course, ?int $id, array $data, array $user): int
    {
        if ($data['enunciado']==='' || mb_strlen($data['enunciado'])>4000) throw new DomainException('El enunciado es obligatorio y admite hasta 4000 caracteres.');
        foreach (['a','b','c','d'] as $key) if (mb_strlen($data['opcion_'.$key])>255 || (in_array($key,['a','b'],true) && $data['opcion_'.$key]==='')) throw new DomainException('Completa al menos las opciones A y B, de hasta 255 caracteres cada una.');
        if (!in_array($data['correcta'],['a','b','c','d'],true) || $data['opcion_'.$data['correcta']]==='') throw new DomainException('La respuesta correcta debe corresponder a una opción con contenido.');
        $db=Database::connect(); $db->beginTransaction();
        try {
            self::lock($course,$user); $exam=self::exam($course);
            if (!$exam) throw new DomainException('Primero guarda el examen.');
            if ($id) { $st=$db->prepare('SELECT id FROM preguntas WHERE id=? AND evaluacion_id=?'); $st->execute([$id,$exam['id']]); if (!$st->fetch()) throw new DomainException('Pregunta no encontrada.'); }
            else if (count(self::questions((int)$exam['id']))>=50) throw new DomainException('El examen admite hasta 50 preguntas.');
            $params=[$data['enunciado'],$data['opcion_a'],$data['opcion_b'],$data['opcion_c']==='' ? null : $data['opcion_c'],$data['opcion_d']==='' ? null : $data['opcion_d'],$data['correcta']];
            if ($id) { $sql='UPDATE preguntas SET enunciado=?,opcion_a=?,opcion_b=?,opcion_c=?,opcion_d=?,correcta=? WHERE id=?'; $params[]=$id; }
            else { $sql='INSERT INTO preguntas (enunciado,opcion_a,opcion_b,opcion_c,opcion_d,correcta,evaluacion_id) VALUES (?,?,?,?,?,?,?)'; $params[]=$exam['id']; }
            $db->prepare($sql)->execute($params); $id=$id ?? (int)$db->lastInsertId();
            $db->prepare('UPDATE evaluaciones SET version=version+1 WHERE id=?')->execute([$exam['id']]); $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function deleteQuestion(int $course, int $id, array $user): void
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            self::lock($course,$user); $exam=self::exam($course);
            if (!$exam) throw new DomainException('Examen no encontrado.');
            $db->prepare('DELETE FROM preguntas WHERE id=? AND evaluacion_id=?')->execute([$id,$exam['id']]);
            $state=self::questions((int)$exam['id']) ? $exam['estado'] : 0;
            $db->prepare('UPDATE evaluaciones SET estado=?,version=version+1 WHERE id=?')->execute([$state,$exam['id']]); $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function submit(int $course, int $user, array $answers, int $version, string $key): int
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$key)) throw new DomainException('Recarga el examen e inténtalo de nuevo.');
        $db=Database::connect(); $db->beginTransaction();
        try {
            $st=$db->prepare('SELECT * FROM cursos WHERE id=? FOR UPDATE'); $st->execute([$course]); $row=$st->fetch();
            $exam=self::exam($course); $enrollment=Academy::enrollment($course,$user);
            if (!$row || !$row['estado'] || !$row['publicado'] || !$exam || !$exam['estado'] || !$enrollment) throw new DomainException('Necesitas una inscripción en un curso publicado con examen activo.');
            $st=$db->prepare('SELECT * FROM evaluacion_intentos WHERE submission_key=?'); $st->execute([$key]); $duplicate=$st->fetch();
            if ($duplicate) {
                if ((int)$duplicate['inscripcion_id']!==(int)$enrollment['id'] || (int)$duplicate['evaluacion_id']!==(int)$exam['id']) throw new DomainException('Solicitud de examen inválida.');
                $db->commit(); return (int)$duplicate['id'];
            }
            $progress=Academy::progress(Academy::lessons($course,(int)$enrollment['id']));
            if (!$progress['total'] || $progress['percent']!==100) throw new DomainException('Completa todas las lecciones activas antes de rendir el examen.');
            if ((int)$exam['version']!==$version) throw new DomainException('El examen cambió. Recarga la página antes de responder.');
            $attempts=self::attempts((int)$exam['id'],(int)$enrollment['id']);
            if (count($attempts)>=self::MAX_ATTEMPTS || array_filter($attempts,fn($a)=>(bool)$a['aprobada'])) throw new DomainException('Ya aprobaste el examen o alcanzaste el límite de tres intentos.');
            $questions=self::questions((int)$exam['id'],true);
            if (!$questions || count($questions)!==count($answers)) throw new DomainException('Responde todas las preguntas del examen.');
            $correct=0; $detail=[];
            foreach ($questions as $question) {
                $chosen=$answers[$question['id']] ?? null;
                if (!is_string($chosen) || !in_array($chosen,['a','b','c','d'],true) || ($question['opcion_'.$chosen] ?? '')==='') throw new DomainException('Selecciona una opción válida para cada pregunta.');
                $right=$chosen===$question['correcta']; if ($right) $correct++;
                unset($question['correcta']);
                $detail[]=$question+['elegida'=>$chosen,'es_correcta'=>$right];
            }
            $grade=round($correct*20/count($questions),2); $passed=$grade>=(float)$exam['nota_minima'];
            $db->prepare('INSERT INTO evaluacion_intentos (evaluacion_id,inscripcion_id,submission_key,nota,nota_minima,aprobada,detalle) VALUES (?,?,?,?,?,?,?)')->execute([$exam['id'],$enrollment['id'],$key,$grade,$exam['nota_minima'],(int)$passed,json_encode($detail,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $id=(int)$db->lastInsertId();
            foreach ($detail as $question) $db->prepare('INSERT INTO respuestas (intento_id,inscripcion_id,pregunta_id,opcion_elegida,es_correcta) VALUES (?,?,?,?,?)')->execute([$id,$enrollment['id'],$question['id'],$question['elegida'],(int)$question['es_correcta']]);
            if ($passed && !self::certificate((int)$enrollment['id'])) {
                $student=User::find($user); $teacher=User::find((int)$row['instructor_id']);
                $db->prepare('INSERT INTO certificados (inscripcion_id,codigo,nota_final,intento_id,nombre_alumno,titulo_curso,nombre_instructor) VALUES (?,?,?,?,?,?,?)')->execute([$enrollment['id'],'INM-'.strtoupper(bin2hex(random_bytes(16))),$grade,$id,$student['nombres'].' '.$student['apellidos'],$row['titulo'],$teacher['nombres'].' '.$teacher['apellidos']]);
            }
            $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
}
