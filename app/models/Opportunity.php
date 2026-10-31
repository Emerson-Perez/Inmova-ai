<?php
class Opportunity
{
    public const STAGES=['abierta'=>'Abierta','ganada'=>'Ganada','perdida'=>'Perdida','cancelada'=>'Cancelada'];
    private const FROM=" FROM oportunidades o JOIN leads l ON l.id=o.lead_id JOIN clientes c ON c.id=l.cliente_id JOIN propiedades p ON p.id=o.propiedad_id LEFT JOIN usuarios u ON u.id=l.vendedor_id";
    private static function access(array $user): void
    {
        if (!in_array($user['rol'],['superadmin','admin','vendedor'],true)) throw new DomainException('No tienes permiso para gestionar oportunidades.');
    }
    public static function listing(array $user, string $stage='', string $query=''): array
    {
        self::access($user); $where='o.estado=1 AND l.estado=1'; $params=[];
        if ($user['rol']==='vendedor') { $where.=' AND l.vendedor_id=?'; $params[]=$user['id']; }
        if ($stage!=='') { $where.=' AND o.etapa=?'; $params[]=$stage; }
        if ($query!=='') { $where.=' AND (c.nombres LIKE ? OR p.titulo LIKE ?)'; $params[]='%'.$query.'%'; $params[]='%'.$query.'%'; }
        $st=Database::connect()->prepare("SELECT o.*,c.nombres AS cliente,p.titulo,p.disponibilidad,p.estado AS propiedad_activa,l.etapa AS lead_etapa,CONCAT(u.nombres,' ',u.apellidos) AS vendedor".self::FROM.' WHERE '.$where.' ORDER BY o.updated_at DESC,o.id DESC LIMIT 100'); $st->execute($params); return $st->fetchAll();
    }
    public static function summary(array $user): array
    {
        self::access($user); $scope='o.estado=1 AND l.estado=1'; $params=[];
        if ($user['rol']==='vendedor') { $scope.=' AND l.vendedor_id=?'; $params[]=$user['id']; }
        $db=Database::connect(); $st=$db->prepare('SELECT o.etapa,COUNT(*) AS total'.self::FROM.' WHERE '.$scope.' GROUP BY o.etapa'); $st->execute($params); $counts=array_map('intval',array_column($st->fetchAll(),'total','etapa'));
        $st=$db->prepare("SELECT o.tipo,o.moneda,COUNT(*) AS total,SUM(o.monto_estimado) AS monto,SUM(o.monto_estimado*o.probabilidad/100) AS ponderado".self::FROM." WHERE $scope AND o.etapa='abierta' AND l.etapa NOT IN ('cerrado','perdido') AND p.estado=1 AND p.disponibilidad IN ('disponible','reservado') GROUP BY o.tipo,o.moneda ORDER BY o.tipo,o.moneda"); $st->execute($params);
        return ['counts'=>$counts,'amounts'=>$st->fetchAll()];
    }
    public static function find(int $id, array $user): ?array
    {
        self::access($user); $params=[$id]; $where='o.id=? AND o.estado=1 AND l.estado=1';
        if ($user['rol']==='vendedor') { $where.=' AND l.vendedor_id=?'; $params[]=$user['id']; }
        $st=Database::connect()->prepare("SELECT o.*,c.nombres AS cliente,p.titulo,p.disponibilidad,p.estado AS propiedad_activa,l.etapa AS lead_etapa,CONCAT(u.nombres,' ',u.apellidos) AS vendedor".self::FROM.' WHERE '.$where); $st->execute($params); return $st->fetch() ?: null;
    }
    public static function forLead(int $id, array $user): array
    {
        self::access($user); $lead=Lead::find($id);
        if (!$lead || ($user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id'])) throw new DomainException('No tienes permiso para esta consulta.');
        $st=Database::connect()->prepare('SELECT o.*,p.titulo FROM oportunidades o JOIN propiedades p ON p.id=o.propiedad_id WHERE o.lead_id=? AND o.estado=1 ORDER BY o.id DESC'); $st->execute([$id]); return $st->fetchAll();
    }
    public static function properties(array $lead, array $user): array
    {
        self::access($user);
        if ($user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id']) throw new DomainException('No tienes permiso para esta consulta.');
        $sql="SELECT p.id,p.titulo,p.precio,p.moneda,p.disponibilidad FROM propiedades p JOIN modalidades m ON m.id=p.modalidad_id JOIN tipos_propiedad t ON t.id=p.tipo_id WHERE p.estado=1 AND p.disponibilidad IN ('disponible','reservado') AND m.estado=1 AND t.estado=1 AND m.slug IN ('terrenos','venta-deptos','alquiler')"; $params=[];
        if ($user['rol']==='vendedor') { $sql.=' AND (p.publicado=1 OR p.creado_por=? OR p.id=?)'; $params=[$user['id'],$lead['propiedad_id'] ?? 0]; }
        $params[]=$lead['propiedad_id'] ?? 0;
        $st=Database::connect()->prepare($sql.' ORDER BY (p.id=?) DESC,p.id DESC LIMIT 100'); $st->execute($params); return $st->fetchAll();
    }
    private static function history(int $lead, int $actor, string $text): void
    {
        Database::connect()->prepare("INSERT INTO seguimientos (lead_id,usuario_id,tipo,detalle) VALUES (?,?,'nota',?)")->execute([$lead,$actor,$text]);
    }
    public static function save(int $leadId, int $propertyId, array $data, ?int $id, array $user): int
    {
        self::access($user);
        if (!preg_match('/^[0-9]{1,7}$/D',$data['monto_estimado']) || (int)$data['monto_estimado']<1 || (int)$data['monto_estimado']>5000000) throw new DomainException('El monto estimado debe ser entero entre 1 y 5 000 000.');
        if (!preg_match('/^[0-9]{1,3}$/D',$data['probabilidad']) || (int)$data['probabilidad']>100) throw new DomainException('La probabilidad debe ser un entero de 0 a 100.');
        if (!in_array($data['etapa'],['abierta','perdida','cancelada'],true)) throw new DomainException('Las oportunidades ganadas se registran automáticamente al cerrar una operación.');
        if (mb_strlen($data['notas'])>4000) throw new DomainException('Las notas admiten hasta 4000 caracteres.');
        $db=Database::connect(); $db->beginTransaction();
        try {
            // Mismo orden de bloqueo que cierres: propiedad, consulta, oportunidad.
            $st=$db->prepare('SELECT p.*,m.slug,m.estado AS modalidad_activa,t.estado AS tipo_activo FROM propiedades p JOIN modalidades m ON m.id=p.modalidad_id JOIN tipos_propiedad t ON t.id=p.tipo_id WHERE p.id=? FOR UPDATE'); $st->execute([$propertyId]); $property=$st->fetch();
            $st=$db->prepare('SELECT * FROM leads WHERE id=? FOR UPDATE'); $st->execute([$leadId]); $lead=$st->fetch();
            if (!$lead || !$lead['estado'] || ($user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id'])) throw new DomainException('No tienes permiso para gestionar esta consulta.');
            $old=null;
            if ($id) {
                $st=$db->prepare('SELECT * FROM oportunidades WHERE id=? AND estado=1 FOR UPDATE'); $st->execute([$id]); $old=$st->fetch();
                if (!$old || (int)$old['lead_id']!==$leadId || (int)$old['propiedad_id']!==$propertyId) throw new DomainException('La oportunidad no corresponde a esta consulta e inmueble.');
                if ($old['etapa']==='ganada' || $old['cierre_id']) throw new DomainException('Una oportunidad ganada se conserva como registro del cierre y no se edita.');
            }
            if (!$property) throw new DomainException('Inmueble no encontrado.');
            if (!$id || $data['etapa']==='abierta') {
                if (!$property['estado'] || !in_array($property['disponibilidad'],['disponible','reservado'],true) || !$property['modalidad_activa'] || !$property['tipo_activo'] || in_array($lead['etapa'],['cerrado','perdido'],true)) throw new DomainException('Para abrir una oportunidad necesitas una consulta abierta y un inmueble activo disponible o reservado.');
            }
            if (!$id && $user['rol']==='vendedor' && !$property['publicado'] && (int)$property['creado_por']!==$user['id'] && (int)($lead['propiedad_id'] ?? 0)!==$propertyId) throw new DomainException('Elige un inmueble publicado, propio o asociado a esta consulta.');
            $type=match($property['slug']) { 'terrenos','venta-deptos'=>'venta','alquiler'=>'alquiler',default=>null };
            if (!$type) throw new DomainException('El inmueble no tiene una modalidad de venta o alquiler compatible.');
            if (!$id) {
                $st=$db->prepare('SELECT id FROM oportunidades WHERE lead_id=? AND propiedad_id=?'); $st->execute([$leadId,$propertyId]);
                if ($st->fetch()) throw new DomainException('Esta consulta ya tiene una oportunidad para ese inmueble. Edítala desde su listado.');
            }
            $probability=$data['etapa']==='abierta' ? (int)$data['probabilidad'] : 0;
            $currency=$old['moneda'] ?? $property['moneda']; $type=$old['tipo'] ?? $type;
            $params=[(int)$data['monto_estimado'],$probability,$currency,$type,$data['etapa'],$data['notas']];
            if ($id) { $sql='UPDATE oportunidades SET monto_estimado=?,probabilidad=?,moneda=?,tipo=?,etapa=?,notas=? WHERE id=?'; $params[]=$id; }
            else { $sql='INSERT INTO oportunidades (monto_estimado,probabilidad,moneda,tipo,etapa,notas,lead_id,propiedad_id) VALUES (?,?,?,?,?,?,?,?)'; $params[]=$leadId; $params[]=$propertyId; }
            $db->prepare($sql)->execute($params); $id=$id ?? (int)$db->lastInsertId();
            $changed=!$old || (float)$old['monto_estimado']!==(float)$data['monto_estimado'] || (int)$old['probabilidad']!==$probability || $old['etapa']!==$data['etapa'] || ($old['notas'] ?? '')!==$data['notas'];
            if ($changed) self::history($leadId,$user['id'],(!$old ? 'Oportunidad creada' : 'Oportunidad actualizada').' #'.$id.' · '.self::STAGES[$data['etapa']].' · '.$currency.' '.$data['monto_estimado'].' · Probabilidad '.$probability.'%'.($data['notas']!=='' ? "\n".$data['notas'] : ''));
            $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    // Se ejecuta dentro de la transacción de cierre, después de actualizar el inmueble y la consulta.
    public static function onClosing(int $property, ?int $lead, int $closing, int $amount, string $currency, string $type, int $actor): void
    {
        $db=Database::connect();
        $st=$db->prepare("SELECT * FROM oportunidades WHERE estado=1 AND ((propiedad_id=? AND etapa='abierta') OR (lead_id=? AND etapa='abierta') OR (lead_id=? AND propiedad_id=?)) ORDER BY id FOR UPDATE"); $st->execute([$property,$lead ?? 0,$lead ?? 0,$property]);
        foreach ($st->fetchAll() as $row) {
            $won=$lead && (int)$row['lead_id']===$lead && (int)$row['propiedad_id']===$property;
            $stage=$won ? 'ganada' : ((int)$row['propiedad_id']===$property ? 'perdida' : 'cancelada');
            if ($won) $db->prepare("UPDATE oportunidades SET etapa='ganada',probabilidad=100,monto_estimado=?,moneda=?,tipo=?,cierre_id=? WHERE id=?")->execute([$amount,$currency,$type,$closing,$row['id']]);
            else $db->prepare('UPDATE oportunidades SET etapa=?,probabilidad=0 WHERE id=?')->execute([$stage,$row['id']]);
            self::history((int)$row['lead_id'],$actor,'Oportunidad #'.$row['id'].' → '.self::STAGES[$stage].' por cierre de operación #'.$closing.'.');
        }
    }
}
