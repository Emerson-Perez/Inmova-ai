<?php
class PublicProperty
{
    private const FROM = ' FROM propiedades p JOIN tipos_propiedad t ON t.id=p.tipo_id JOIN modalidades m ON m.id=p.modalidad_id LEFT JOIN ubicaciones u ON u.id=p.ubicacion_id';
    private const VISIBLE = ' p.estado=1 AND p.publicado=1 AND t.estado=1 AND m.estado=1';

    public static function search(array $filters, int $page, bool $availableOnly=false): array
    {
        $where = self::VISIBLE;
        if ($availableOnly) $where.=" AND p.disponibilidad='disponible'";
        $params = [];
        if ($filters['q'] !== '') {
            $where .= ' AND (p.titulo LIKE :title OR u.distrito LIKE :district OR u.departamento LIKE :department)';
            foreach (['title','district','department'] as $key) $params[$key] = '%' . $filters['q'] . '%';
        }
        foreach (['tipo_id','modalidad_id','moneda'] as $field) {
            if ($filters[$field] !== '') {
                $where .= " AND p.$field=:$field";
                $params[$field] = $filters[$field];
            }
        }
        foreach (['min' => '>=', 'max' => '<='] as $key => $operator) {
            if ($filters[$key] !== '') {
                $where .= " AND p.precio $operator :$key";
                $params[$key] = $filters[$key];
            }
        }
        $db = Database::connect();
        $count = $db->prepare('SELECT COUNT(*)' . self::FROM . ' WHERE ' . $where);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / 12));
        $page = min($page, $pages);
        $offset = ($page - 1) * 12;
        $sql = "SELECT p.id,p.titulo,p.precio,p.moneda,p.area_m2,p.dormitorios,p.banos,p.disponibilidad,t.nombre AS tipo,m.nombre AS modalidad,u.distrito,u.departamento,(SELECT f.ruta FROM propiedad_multimedia f WHERE f.propiedad_id=p.id AND f.tipo='foto' AND f.estado=1 ORDER BY f.es_principal DESC,f.id LIMIT 1) AS foto" . self::FROM . " WHERE $where ORDER BY p.id DESC LIMIT 12 OFFSET $offset";
        $st = $db->prepare($sql);
        $st->execute($params);
        return ['properties' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function find(int $id): ?array
    {
        $st = Database::connect()->prepare('SELECT p.id,p.titulo,p.descripcion,p.precio,p.moneda,p.area_m2,p.dormitorios,p.banos,p.disponibilidad,t.nombre AS tipo,m.nombre AS modalidad,u.departamento,u.provincia,u.distrito,u.direccion' . self::FROM . ' WHERE ' . self::VISIBLE . ' AND p.id=:id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }
}
