<?php
class PropertyDocument
{
    public const CATEGORIES=['plano'=>'Plano','contrato'=>'Contrato','ficha'=>'Ficha técnica','otro'=>'Otro documento'];
    public const ACTIONS=['subido'=>'Carga','descargado'=>'Solicitud de descarga','retirado'=>'Retiro','restaurado'=>'Restauración'];
    public const MAX_ACTIVE=20;
    private static function access(array $user): void
    {
        if (!in_array($user['rol'],['superadmin','admin','vendedor'],true)) throw new DomainException('No tienes permiso para documentos de inmuebles.');
    }
    public static function property(int $id, array $user, bool $lock=false): array
    {
        self::access($user);
        $st=Database::connect()->prepare('SELECT id,titulo,creado_por FROM propiedades WHERE id=? AND estado=1'.($lock ? ' FOR UPDATE' : '')); $st->execute([$id]); $row=$st->fetch();
        if (!$row || ($user['rol']==='vendedor' && (int)$row['creado_por']!==$user['id'])) throw new DomainException('Inmueble no encontrado o sin permiso para sus documentos.');
        return $row;
    }
    public static function listing(int $property, bool $active, array $user): array
    {
        self::property($property,$user);
        $st=Database::connect()->prepare("SELECT d.*,CONCAT(u.nombres,' ',u.apellidos) AS autor FROM propiedad_documentos d JOIN usuarios u ON u.id=d.subido_por WHERE d.propiedad_id=? AND d.estado=? ORDER BY d.id DESC LIMIT 100"); $st->execute([$property,(int)$active]); return $st->fetchAll();
    }
    public static function countActive(int $property, bool $current=false): int
    {
        // Tras esperar el bloqueo del inmueble, no reutilizar una instantánea anterior.
        if ($current) {
            $st=Database::connect()->prepare('SELECT id FROM propiedad_documentos WHERE propiedad_id=? AND estado=1 FOR UPDATE');
            $st->execute([$property]); return count($st->fetchAll());
        }
        $st=Database::connect()->prepare('SELECT COUNT(*) FROM propiedad_documentos WHERE propiedad_id=? AND estado=1'); $st->execute([$property]); return (int)$st->fetchColumn();
    }
    public static function find(int $id, array $user, bool $onlyActive=true): ?array
    {
        self::access($user); $sql='SELECT d.* FROM propiedad_documentos d JOIN propiedades p ON p.id=d.propiedad_id WHERE d.id=? AND p.estado=1'.($onlyActive ? ' AND d.estado=1' : ''); $params=[$id];
        if ($user['rol']==='vendedor') { $sql.=' AND p.creado_por=?'; $params[]=$user['id']; }
        $st=Database::connect()->prepare($sql); $st->execute($params); return $st->fetch() ?: null;
    }
    private static function event(int $document, int $user, string $action): void
    {
        Database::connect()->prepare('INSERT INTO documento_eventos (documento_id,usuario_id,accion) VALUES (?,?,?)')->execute([$document,$user,$action]);
    }
    public static function upload(int $property, string $title, string $category, array $file, array $user): int
    {
        if (trim($title)==='' || mb_strlen($title)>120) throw new DomainException('El título es obligatorio y admite hasta 120 caracteres.');
        if (!isset(self::CATEGORIES[$category])) throw new DomainException('Selecciona una categoría válida.');
        $db=Database::connect(); $db->beginTransaction(); $stored=null;
        try {
            self::property($property,$user,true);
            if (self::countActive($property)>=self::MAX_ACTIVE) throw new DomainException('El inmueble admite hasta 20 documentos activos. Retira uno antes de cargar otro.');
            $stored=DocumentStorage::receive($file);
            $db->prepare('INSERT INTO propiedad_documentos (propiedad_id,titulo,categoria,nombre_original,storage_name,tamano,subido_por) VALUES (?,?,?,?,?,?,?)')->execute([$property,trim($title),$category,$stored['nombre_original'],$stored['storage_name'],$stored['tamano'],$user['id']]); $id=(int)$db->lastInsertId();
            self::event($id,$user['id'],'subido'); $db->commit(); return $id;
        } catch (Throwable $error) {
            $db->rollBack(); if ($stored) DocumentStorage::discard($stored['storage_name']); throw $error;
        }
    }
    public static function setActive(int $id, bool $active, array $user): int
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::find($id,$user,false);
            if (!$row) throw new DomainException('Documento no encontrado o sin acceso.');
            self::property((int)$row['propiedad_id'],$user,true);
            $st=$db->prepare('SELECT * FROM propiedad_documentos WHERE id=? FOR UPDATE'); $st->execute([$id]); $row=$st->fetch();
            if ((bool)$row['estado']===$active) { $db->commit(); return (int)$row['propiedad_id']; }
            if ($active) {
                if (self::countActive((int)$row['propiedad_id'],true)>=self::MAX_ACTIVE) throw new DomainException('El inmueble ya tiene 20 documentos activos.');
                if (!is_readable(DocumentStorage::path($row['storage_name']))) throw new DomainException('El archivo no está disponible para restauración.');
            }
            $db->prepare('UPDATE propiedad_documentos SET estado=? WHERE id=?')->execute([(int)$active,$id]); self::event($id,$user['id'],$active ? 'restaurado' : 'retirado');
            $db->commit(); return (int)$row['propiedad_id'];
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function download(int $id, array $user): array
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::find($id,$user);
            if (!$row) throw new DomainException('Documento no encontrado o sin acceso.');
            self::property((int)$row['propiedad_id'],$user,true);
            $st=$db->prepare('SELECT estado FROM propiedad_documentos WHERE id=? FOR UPDATE'); $st->execute([$id]);
            if (!(bool)$st->fetchColumn()) throw new DomainException('El documento fue retirado.');
            $path=DocumentStorage::path($row['storage_name']);
            if (!is_file($path) || !is_readable($path)) throw new DomainException('El archivo no está disponible.');
            self::event($id,$user['id'],'descargado'); $db->commit(); return $row+['path'=>$path];
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function history(int $property, array $user): array
    {
        self::property($property,$user);
        $st=Database::connect()->prepare("SELECT e.accion,e.created_at,d.titulo,CONCAT(u.nombres,' ',u.apellidos) AS autor FROM documento_eventos e JOIN propiedad_documentos d ON d.id=e.documento_id JOIN usuarios u ON u.id=e.usuario_id WHERE d.propiedad_id=? ORDER BY e.id DESC LIMIT 30"); $st->execute([$property]); return $st->fetchAll();
    }
}
