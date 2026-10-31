<?php
class Academy
{
    public static function canManage(array $course, array $user): bool
    {
        return in_array($user['rol'],['admin','superadmin'],true) || ($user['rol']==='instructor' && (int)$course['instructor_id']===$user['id']);
    }
    public static function course(int $id): ?array
    {
        $st=Database::connect()->prepare("SELECT c.*,CONCAT(u.nombres,' ',u.apellidos) AS instructor FROM cursos c JOIN usuarios u ON u.id=c.instructor_id WHERE c.id=?");
        $st->execute([$id]); return $st->fetch() ?: null;
    }
    public static function courses(array $user, bool $manage=false, bool $mine=false): array
    {
        $sql="SELECT c.*,i.estado AS inscripcion,CONCAT(u.nombres,' ',u.apellidos) AS instructor,(SELECT COUNT(*) FROM lecciones l WHERE l.curso_id=c.id AND l.estado=1) AS lecciones FROM cursos c JOIN usuarios u ON u.id=c.instructor_id LEFT JOIN inscripciones i ON i.curso_id=c.id AND i.usuario_id=? AND i.estado<>'abandonada' WHERE ";
        $params=[$user['id']];
        if ($manage) {
            if (!in_array($user['rol'],['admin','superadmin','instructor'],true)) throw new DomainException('No tienes permiso para gestionar cursos.');
            $sql.=$user['rol']==='instructor' ? 'c.instructor_id=?' : '1=1';
            if ($user['rol']==='instructor') $params[]=$user['id'];
        } else $sql.='c.estado=1 AND c.publicado=1'.($mine ? ' AND i.id IS NOT NULL' : '');
        $st=Database::connect()->prepare($sql.' ORDER BY c.id DESC LIMIT 100'); $st->execute($params); return $st->fetchAll();
    }
    public static function instructors(): array
    {
        return Database::connect()->query("SELECT u.id,CONCAT(u.nombres,' ',u.apellidos) AS nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.estado=1 AND r.estado=1 AND r.nombre IN ('instructor','admin','superadmin') ORDER BY u.nombres")->fetchAll();
    }
    public static function enrollment(int $course, int $user): ?array
    {
        $st=Database::connect()->prepare("SELECT * FROM inscripciones WHERE curso_id=? AND usuario_id=? AND estado<>'abandonada'"); $st->execute([$course,$user]); return $st->fetch() ?: null;
    }
    public static function students(int $course): array
    {
        $st=Database::connect()->prepare("SELECT i.id,i.estado,u.nombres,u.apellidos,u.email,ce.codigo AS certificado_codigo,(SELECT MAX(a.id) FROM evaluacion_intentos a WHERE a.inscripcion_id=i.id) AS intento_id,(SELECT COUNT(*) FROM progreso_cursos p JOIN lecciones l ON l.id=p.leccion_id WHERE p.inscripcion_id=i.id AND p.completada=1 AND l.curso_id=i.curso_id AND l.estado=1) AS completadas FROM inscripciones i JOIN usuarios u ON u.id=i.usuario_id LEFT JOIN certificados ce ON ce.inscripcion_id=i.id WHERE i.curso_id=? AND i.estado<>'abandonada' ORDER BY i.id DESC LIMIT 100");
        $st->execute([$course]); return $st->fetchAll();
    }
    public static function lessons(int $course, ?int $enrollment=null, bool $manage=false): array
    {
        $st=Database::connect()->prepare('SELECT l.*,COALESCE(p.completada,0) AS completada FROM lecciones l LEFT JOIN progreso_cursos p ON p.leccion_id=l.id AND p.inscripcion_id=? WHERE l.curso_id=?'.($manage ? '' : ' AND l.estado=1').' ORDER BY l.orden,l.id');
        $st->execute([$enrollment ?? 0,$course]); return $st->fetchAll();
    }
    public static function lesson(int $id): ?array
    {
        $st=Database::connect()->prepare('SELECT * FROM lecciones WHERE id=?'); $st->execute([$id]); return $st->fetch() ?: null;
    }
    public static function progress(array $lessons): array
    {
        $active=array_filter($lessons,fn($l)=>(bool)$l['estado']);
        $done=count(array_filter($active,fn($l)=>(bool)$l['completada'])); $total=count($active);
        return ['done'=>$done,'total'=>$total,'percent'=>$total ? (int)floor($done/$total*100) : 0];
    }
    private static function lockCourse(int $id): ?array
    {
        $st=Database::connect()->prepare('SELECT * FROM cursos WHERE id=? FOR UPDATE'); $st->execute([$id]); return $st->fetch() ?: null;
    }
    private static function sync(int $course): void
    {
        $db=Database::connect(); $st=$db->prepare('SELECT COUNT(*) FROM lecciones WHERE curso_id=? AND estado=1'); $st->execute([$course]); $total=(int)$st->fetchColumn();
        $db->prepare("UPDATE inscripciones i SET estado=CASE WHEN ?>0 AND (SELECT COUNT(*) FROM progreso_cursos p JOIN lecciones l ON l.id=p.leccion_id WHERE p.inscripcion_id=i.id AND p.completada=1 AND l.curso_id=i.curso_id AND l.estado=1)>=? THEN 'completada' ELSE 'activa' END WHERE curso_id=? AND estado<>'abandonada'")->execute([$total,$total,$course]);
    }
    public static function saveCourse(array $data, ?int $id, array $user): int
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            if (!in_array($user['rol'],['superadmin','admin','instructor'],true)) throw new DomainException('No tienes permiso para gestionar cursos.');
            $old=$id ? self::lockCourse($id) : null;
            if ($id && (!$old || !self::canManage($old,$user))) throw new DomainException('No tienes permiso para editar este curso.');
            if ($user['rol']==='instructor') $data['instructor_id']=$user['id'];
            if (!in_array((int)$data['instructor_id'],array_map('intval',array_column(self::instructors(),'id')),true)) throw new DomainException('Selecciona un instructor activo.');
            if (!$data['estado']) $data['publicado']=0;
            if ($data['publicado']) {
                $lessons=$id ? self::lessons($id) : [];
                if (!$lessons) throw new DomainException('Agrega al menos una lección activa antes de publicar el curso. Primero guárdalo como borrador.');
            }
            $params=[$data['instructor_id'],$data['titulo'],$data['descripcion'],$data['publicado'],$data['estado']];
            if ($id) { $sql='UPDATE cursos SET instructor_id=?,titulo=?,descripcion=?,publicado=?,estado=? WHERE id=?'; $params[]=$id; }
            else $sql='INSERT INTO cursos (instructor_id,titulo,descripcion,publicado,estado) VALUES (?,?,?,?,?)';
            $db->prepare($sql)->execute($params); $id=$id ?? (int)$db->lastInsertId(); $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function saveLesson(int $course, ?int $id, array $data, array $user): int
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::lockCourse($course);
            if (!$row || !self::canManage($row,$user)) throw new DomainException('No tienes permiso para editar las lecciones de este curso.');
            if (Assessment::hasAttempts($course)) throw new DomainException('Las lecciones quedan bloqueadas después del primer intento de examen. Crea otro curso para una nueva versión.');
            $old=$id ? self::lesson($id) : null;
            if ($id && (!$old || (int)$old['curso_id']!==$course)) throw new DomainException('Lección no encontrada en este curso.');
            $params=[$data['titulo'],$data['contenido'],$data['video_url'] ?: null,$data['orden'],$data['estado']];
            if ($id) { $sql='UPDATE lecciones SET titulo=?,contenido=?,video_url=?,orden=?,estado=? WHERE id=?'; $params[]=$id; }
            else { $sql='INSERT INTO lecciones (titulo,contenido,video_url,orden,estado,curso_id) VALUES (?,?,?,?,?,?)'; $params[]=$course; }
            $db->prepare($sql)->execute($params); $id=$id ?? (int)$db->lastInsertId();
            if (!self::lessons($course)) $db->prepare('UPDATE cursos SET publicado=0 WHERE id=?')->execute([$course]);
            self::sync($course); $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function enroll(int $course, int $user): void
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::lockCourse($course);
            if (!$row || !$row['estado'] || !$row['publicado']) throw new DomainException('El curso ya no está disponible para inscripción.');
            $db->prepare("INSERT INTO inscripciones (curso_id,usuario_id) VALUES (?,?) ON DUPLICATE KEY UPDATE estado=IF(estado='abandonada','activa',estado)")->execute([$course,$user]);
            self::sync($course); $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function complete(int $lesson, int $user, bool $done): int
    {
        // Buscar el curso antes de iniciar la instantánea transaccional.
        // Después del bloqueo, todas las lecturas deben ver los cambios ya confirmados.
        $initial=self::lesson($lesson);
        $db=Database::connect(); $db->beginTransaction();
        try {
            $course=$initial ? self::lockCourse((int)$initial['curso_id']) : null;
            $row=self::lesson($lesson);
            $enrolled=$course ? self::enrollment((int)$course['id'],$user) : null;
            if (!$row || !$row['estado'] || !$course || !$course['estado'] || !$course['publicado'] || !$enrolled) throw new DomainException('Debes estar inscrito en un curso publicado para registrar tu progreso.');
            if (!$done && Assessment::certificate((int)$enrolled['id'])) throw new DomainException('Las lecciones de un curso certificado se conservan completadas.');
            $db->prepare('INSERT INTO progreso_cursos (inscripcion_id,leccion_id,completada) VALUES (?,?,?) ON DUPLICATE KEY UPDATE completada=VALUES(completada)')->execute([$enrolled['id'],$lesson,(int)$done]);
            self::sync((int)$course['id']); $db->commit(); return (int)$course['id'];
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
}
