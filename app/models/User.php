<?php
// Modelo de usuarios. Todas las consultas usan PDO preparado.
class User
{
    public static function findByEmail(string $email): ?array
    {
        $sql = 'SELECT u.*, r.nombre AS rol, r.estado AS rol_estado
                FROM usuarios u JOIN roles r ON r.id = u.rol_id
                WHERE u.email = :email LIMIT 1';
        $st = Database::connect()->prepare($sql);
        $st->execute(['email' => $email]);
        return $st->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $st=Database::connect()->prepare('SELECT u.*,r.nombre AS rol,r.estado AS rol_estado FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=?');
        $st->execute([$id]); return $st->fetch() ?: null;
    }

    public static function listing(array $actor, string $query): array
    {
        $sql='SELECT u.id,u.nombres,u.apellidos,u.email,u.estado,u.ultimo_acceso,u.password_change_required,r.nombre AS rol FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE 1=1';
        $params=[];
        if ($actor['rol']!=='superadmin') $sql.=" AND r.nombre<>'superadmin'";
        if ($query!=='') {
            $sql.=' AND (u.nombres LIKE ? OR u.apellidos LIKE ? OR u.email LIKE ?)';
            $params=array_fill(0,3,'%'.$query.'%');
        }
        $st=Database::connect()->prepare($sql.' ORDER BY u.estado DESC,u.nombres,u.id LIMIT 100'); $st->execute($params); return $st->fetchAll();
    }

    public static function roles(array $actor): array
    {
        $sql='SELECT id,nombre FROM roles WHERE estado=1'.($actor['rol']==='superadmin' ? '' : " AND nombre<>'superadmin'").' ORDER BY id';
        return Database::connect()->query($sql)->fetchAll();
    }

    public static function passwordError(string $password): ?string
    {
        if (str_contains($password,"\0")) return 'La contraseña no admite bytes nulos.';
        if (strlen($password)<10 || strlen($password)>72 || !preg_match('/[a-z]/',$password) || !preg_match('/[A-Z]/',$password) || !preg_match('/[0-9]/',$password)) return 'La contraseña debe tener entre 10 y 72 bytes e incluir mayúscula, minúscula y número.';
        return null;
    }

    public static function save(array $data, ?int $id, array $actor, string $password): int
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            // Serializar cambios de rol para proteger la última cuenta de superadministración.
            $db->query('SELECT id FROM roles ORDER BY id FOR UPDATE')->fetchAll();
            $current=self::find($actor['id']);
            if (!$current || !$current['estado'] || !$current['rol_estado'] || !in_array($current['rol'],['superadmin','admin'],true)) throw new DomainException('Tu cuenta no tiene permisos de administración.');
            if (isset($actor['auth_version']) && (int)$current['auth_version']!==(int)$actor['auth_version']) throw new DomainException('Tu sesión cambió. Ingresa nuevamente.');
            $old=$id ? self::find($id) : null;
            if ($id && !$old) throw new DomainException('Usuario no encontrado.');
            if ($old && $old['rol']==='superadmin' && $current['rol']!=='superadmin') throw new DomainException('Solo un superadministrador puede modificar esta cuenta.');
            $roles=self::roles(['rol'=>$current['rol']]); $role=null;
            foreach ($roles as $option) if ((int)$option['id']===(int)$data['rol_id']) $role=$option;
            if (!$role) throw new DomainException('Selecciona un rol activo permitido.');
            if ($id===$actor['id'] && ((int)$data['estado']!==1 || (int)$old['rol_id']!==(int)$data['rol_id'] || $password!=='')) throw new DomainException('No puedes desactivar tu propia cuenta, cambiar tu rol o restablecerte la contraseña aquí. Usa Cambiar mi contraseña.');
            if ($old && $old['rol']==='superadmin' && $old['estado'] && (!$data['estado'] || $role['nombre']!=='superadmin')) {
                $count=(int)$db->query("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.estado=1 AND r.nombre='superadmin' AND r.estado=1")->fetchColumn();
                if ($count<=1) throw new DomainException('Debe conservarse al menos un superadministrador activo.');
            }
            if (!$id || $password!=='') { if ($error=self::passwordError($password)) throw new DomainException($error); }
            $st=$db->prepare('SELECT id FROM usuarios WHERE email=? AND id<>?'); $st->execute([$data['email'],$id ?? 0]);
            if ($st->fetch()) throw new DomainException('Ya existe una cuenta con ese correo.');
            $params=[$data['rol_id'],$data['nombres'],$data['apellidos'],$data['email'],$data['telefono'] ?: null,$data['estado']];
            if ($old) {
                $sql='UPDATE usuarios SET rol_id=?,nombres=?,apellidos=?,email=?,telefono=?,estado=?';
                if ($password!=='') { $sql.=',password_hash=?,password_change_required=1'; $params[]=password_hash($password,PASSWORD_DEFAULT); }
                if ($password!=='' || (int)$old['rol_id']!==(int)$data['rol_id'] || (int)$old['estado']!==(int)$data['estado'] || $old['email']!==$data['email']) $sql.=',auth_version=auth_version+1';
                $sql.=' WHERE id=?'; $params[]=$id;
            } else {
                $sql='INSERT INTO usuarios (rol_id,nombres,apellidos,email,telefono,estado,password_hash,password_change_required) VALUES (?,?,?,?,?,?,?,1)'; $params[]=password_hash($password,PASSWORD_DEFAULT);
            }
            $db->prepare($sql)->execute($params); $id=$id ?? (int)$db->lastInsertId();
            $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }

    public static function changePassword(int $id, int $version, string $currentPassword, string $newPassword): void
    {
        if ($error=self::passwordError($newPassword)) throw new DomainException($error);
        $db=Database::connect(); $db->beginTransaction();
        try {
            $st=$db->prepare('SELECT * FROM usuarios WHERE id=? FOR UPDATE'); $st->execute([$id]); $row=$st->fetch();
            if (!$row || !$row['estado'] || (int)$row['auth_version']!==$version || !password_verify($currentPassword,$row['password_hash'])) throw new DomainException('La contraseña actual no es correcta o tu sesión cambió.');
            if (password_verify($newPassword,$row['password_hash'])) throw new DomainException('Elige una contraseña diferente a la actual.');
            $db->prepare('UPDATE usuarios SET password_hash=?,auth_version=auth_version+1,password_change_required=0 WHERE id=?')->execute([password_hash($newPassword,PASSWORD_DEFAULT),$id]);
            $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }

    public static function touchLogin(int $id): void
    {
        $st = Database::connect()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id');
        $st->execute(['id' => $id]);
    }
}
