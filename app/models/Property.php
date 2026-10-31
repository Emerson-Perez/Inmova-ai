<?php
class Property
{
    public static function catalogs(): array
    {
        $db = Database::connect();
        return [
            'types' => $db->query('SELECT id,nombre FROM tipos_propiedad WHERE estado=1 ORDER BY nombre')->fetchAll(),
            'modes' => $db->query('SELECT id,nombre FROM modalidades WHERE estado=1 ORDER BY nombre')->fetchAll(),
        ];
    }

    public static function validateFilters(array $input): array
    {
        $filters=[];
        foreach (['q','modalidad_id','moneda','min','max','disponibilidad','publicado'] as $key) {
            if (isset($input[$key]) && !is_string($input[$key])) throw new DomainException('Los filtros deben contener un único valor por campo.');
            $filters[$key]=trim($input[$key] ?? '');
        }
        if (mb_strlen($filters['q'])>160) throw new DomainException('La búsqueda admite hasta 160 caracteres.');
        if ($filters['modalidad_id']!=='' && !filter_var($filters['modalidad_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])) throw new DomainException('Selecciona una modalidad válida.');
        if (!in_array($filters['moneda'],['','PEN','USD'],true)) throw new DomainException('Selecciona una moneda válida.');
        if (!in_array($filters['disponibilidad'],['','disponible','reservado','vendido','alquilado'],true)) throw new DomainException('Selecciona una disponibilidad válida.');
        if (!in_array($filters['publicado'],['','0','1'],true)) throw new DomainException('Selecciona una publicación válida.');
        foreach (['min','max'] as $key) {
            if ($filters[$key]!=='' && (!preg_match('/^[0-9]{1,7}$/D',$filters[$key]) || (int)$filters[$key]>5000000)) throw new DomainException('Los precios deben ser enteros entre 0 y 5 000 000.');
        }
        if (($filters['min']!=='' || $filters['max']!=='') && $filters['moneda']==='') throw new DomainException('Selecciona una moneda para filtrar por precio.');
        if ($filters['min']!=='' && $filters['max']!=='' && (int)$filters['min']>(int)$filters['max']) throw new DomainException('El precio mínimo no puede superar al máximo.');
        return $filters;
    }

    public static function listing(array $user, string $query, array $filters=[]): array
    {
        $filters=self::validateFilters(['q'=>$query]+$filters);
        $query=$filters['q'];
        $sql = "SELECT p.*,t.nombre AS tipo,m.nombre AS modalidad,u.distrito,u.departamento,(SELECT f.ruta FROM propiedad_multimedia f WHERE f.propiedad_id=p.id AND f.tipo='foto' AND f.estado=1 ORDER BY f.es_principal DESC,f.id LIMIT 1) AS foto FROM propiedades p JOIN tipos_propiedad t ON t.id=p.tipo_id JOIN modalidades m ON m.id=p.modalidad_id LEFT JOIN ubicaciones u ON u.id=p.ubicacion_id WHERE p.estado=1";
        $params = [];
        if ($user['rol'] === 'vendedor') {
            $sql .= ' AND p.creado_por=:owner';
            $params['owner'] = $user['id'];
        }
        if ($query !== '') {
            $sql .= ' AND p.titulo LIKE :query';
            $params['query'] = '%' . $query . '%';
        }
        foreach (['modalidad_id','moneda','disponibilidad','publicado'] as $field) {
            if ($filters[$field]!=='') { $sql.=" AND p.$field=:$field"; $params[$field]=$filters[$field]; }
        }
        foreach (['min'=>'>=','max'=>'<='] as $key=>$operator) {
            if ($filters[$key]!=='') { $sql.=" AND p.precio $operator :$key"; $params[$key]=$filters[$key]; }
        }
        $sql .= ' ORDER BY p.id DESC LIMIT 100';
        $st = Database::connect()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $st = Database::connect()->prepare('SELECT p.*,u.departamento,u.provincia,u.distrito,u.direccion FROM propiedades p LEFT JOIN ubicaciones u ON u.id=p.ubicacion_id WHERE p.id=:id AND p.estado=1');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public static function photos(int $id): array
    {
        $st = Database::connect()->prepare("SELECT * FROM propiedad_multimedia WHERE propiedad_id=? AND tipo='foto' AND estado=1 ORDER BY es_principal DESC,id");
        $st->execute([$id]);
        return $st->fetchAll();
    }

    public static function delete(int $id): void
    {
        $db=Database::connect();
        $db->beginTransaction();
        try {
            $st=$db->prepare('SELECT id FROM propiedades WHERE id=? FOR UPDATE'); $st->execute([$id]);
            $st=$db->prepare("SELECT id FROM reservas WHERE propiedad_id=? AND estado_reserva IN ('aprobada','cerrada')"); $st->execute([$id]);
            if ($st->fetch()) throw new DomainException('No puedes eliminar un inmueble con reserva aprobada u operación cerrada.');
            $db->prepare('UPDATE propiedades SET estado=0,publicado=0 WHERE id=?')->execute([$id]);
            $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }

    public static function save(array $data, ?int $id, int $owner, array $uploads = [], array $remove = [], int $primary = 0): void
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            // Bloquear el inmueble mantiene consistente la galería en ediciones simultáneas.
            if ($id) {
                $lock = $db->prepare('SELECT id FROM propiedades WHERE id=? FOR UPDATE');
                $lock->execute([$id]);
            }
            $old = $id ? self::find($id) : null;
            if ($id && !$old) throw new DomainException('El inmueble ya no está activo. Recarga el listado antes de editarlo.');
            if ($old && (int)$data['modalidad_id']!==(int)$old['modalidad_id']) {
                $st=$db->prepare("SELECT id FROM reservas WHERE propiedad_id=? AND estado_reserva='pendiente' LIMIT 1"); $st->execute([$id]);
                if ($st->fetchColumn()) throw new DomainException('Cancela o rechaza las reservas pendientes antes de cambiar la modalidad del inmueble.');
            }
            if ($id) {
                $st=$db->prepare('SELECT c.tipo,c.moneda FROM cierres c JOIN reservas r ON r.id=c.reserva_id WHERE r.propiedad_id=? LIMIT 1'); $st->execute([$id]); $closing=$st->fetch();
                if ($closing && ($data['disponibilidad']!==($closing['tipo']==='venta' ? 'vendido' : 'alquilado') || $data['moneda']!==$closing['moneda'] || $data['modalidad_id'] != $old['modalidad_id'] || !empty($data['publicado']))) throw new DomainException('El inmueble tiene una operación cerrada: conserva su disponibilidad, modalidad y moneda, y déjalo sin publicar.');
            }
            if ($id) {
                $st=$db->prepare("SELECT moneda,monto_reserva FROM reservas WHERE propiedad_id=? AND estado_reserva='aprobada' LIMIT 1"); $st->execute([$id]); $reservation=$st->fetch();
                if ($reservation && ($data['disponibilidad']!=='reservado' || $data['moneda']!==$reservation['moneda'])) throw new DomainException('El inmueble tiene una reserva aprobada: conserva su disponibilidad en Reservado y su moneda. Para liberarlo, cancela la reserva desde Reservas.');
                if ($reservation && (int)$data['modalidad_id']!==(int)$old['modalidad_id']) throw new DomainException('El inmueble tiene una reserva aprobada: conserva su modalidad. Cancela la reserva antes de cambiarla.');
                if ($reservation && (float)$data['precio']<(float)$reservation['monto_reserva']) throw new DomainException('El precio no puede ser menor que el monto de la reserva aprobada.');
            }
            $location = $old['ubicacion_id'] ?? null;
            if ($data['departamento'] !== '') {
                $params = array_intersect_key($data, array_flip(['departamento','provincia','distrito','direccion']));
                if ($location) {
                    $sql = 'UPDATE ubicaciones SET departamento=:departamento,provincia=:provincia,distrito=:distrito,direccion=:direccion WHERE id=:id';
                    $params['id'] = $location;
                } else {
                    $sql = 'INSERT INTO ubicaciones (departamento,provincia,distrito,direccion) VALUES (:departamento,:provincia,:distrito,:direccion)';
                }
                $db->prepare($sql)->execute($params);
                if (!$location) $location = (int)$db->lastInsertId();
            } else {
                $location = null;
            }
            $data['ubicacion_id'] = $location;
            $id = self::saveRecord($data, $id, $owner);
            foreach ($remove as $photoId) {
                $db->prepare("UPDATE propiedad_multimedia SET estado=0,es_principal=0 WHERE id=? AND propiedad_id=? AND tipo='foto'")->execute([$photoId,$id]);
            }
            $photos = self::photos($id);
            if (count($photos) + count($uploads) > 10) throw new RuntimeException('Puedes guardar hasta 10 fotografías por propiedad.');
            foreach ($uploads as $path) {
                $db->prepare("INSERT INTO propiedad_multimedia (propiedad_id,tipo,ruta) VALUES (?,'foto',?)")->execute([$id,$path]);
            }
            $photos = self::photos($id);
            $ids = array_map('intval', array_column($photos, 'id'));
            $selected = in_array($primary, $ids, true) ? $primary : ($ids[0] ?? 0);
            $db->prepare("UPDATE propiedad_multimedia SET es_principal=(id=?) WHERE propiedad_id=? AND tipo='foto' AND estado=1")->execute([$selected,$id]);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    private static function saveRecord(array $data, ?int $id, int $owner): int
    {
        $fields = ['titulo','descripcion','tipo_id','modalidad_id','precio','moneda','area_m2','dormitorios','banos','disponibilidad','publicado','ubicacion_id'];
        $params = array_intersect_key($data, array_flip($fields));
        if ($id !== null) {
            $assignments = implode(',', array_map(fn($f) => "$f=:$f", $fields));
            $sql = "UPDATE propiedades SET $assignments WHERE id=:id";
            $params['id'] = $id;
        } else {
            $sql = 'INSERT INTO propiedades (' . implode(',', $fields) . ',creado_por) VALUES (:' . implode(',:', $fields) . ',:owner)';
            $params['owner'] = $owner;
        }
        Database::connect()->prepare($sql)->execute($params);
        return $id ?? (int)Database::connect()->lastInsertId();
    }
}
