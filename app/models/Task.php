<?php
class Task
{
    public const FILTERS=['pendientes'=>'Todos los pendientes','vencidas'=>'Vencidos','hoy'=>'Por atender hoy','proximas'=>'Después de hoy','completadas'=>'Completados'];
    private static function access(array $user): void
    {
        if (!in_array($user['rol'],['superadmin','admin','vendedor'],true)) throw new DomainException('No tienes permiso para consultar pendientes.');
    }
    public static function clock(?DateTimeImmutable $now=null): array
    {
        $now=($now ?? new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->setTimezone(new DateTimeZone('America/Lima'));
        return ['now'=>$now->format('Y-m-d H:i:s'),'tomorrow'=>$now->modify('tomorrow')->setTime(0,0)->format('Y-m-d H:i:s'),'week'=>$now->modify('+7 days')->format('Y-m-d H:i:s')];
    }
    public static function urgency(string $date, array $clock): string
    {
        return $date<$clock['now'] ? 'Vencido' : ($date<$clock['tomorrow'] ? 'Hoy' : 'Próximo');
    }
    public static function summary(array $user, ?DateTimeImmutable $now=null): array
    {
        self::access($user); $clock=self::clock($now); $owner=$user['rol']==='vendedor' ? [$user['id']] : [];
        $db=Database::connect();
        $st=$db->prepare("SELECT COALESCE(SUM(s.proxima_accion<?),0) AS overdue,COALESCE(SUM(s.proxima_accion>=? AND s.proxima_accion<?),0) AS today,COALESCE(SUM(s.proxima_accion>=?),0) AS upcoming FROM seguimientos s JOIN leads l ON l.id=s.lead_id WHERE s.proxima_accion IS NOT NULL AND s.tarea_completada_at IS NULL AND l.estado=1 AND l.etapa NOT IN ('cerrado','perdido')".($owner ? ' AND l.vendedor_id=?' : ''));
        $st->execute(array_merge([$clock['now'],$clock['now'],$clock['tomorrow'],$clock['tomorrow']],$owner)); $result=array_map('intval',$st->fetch());
        $st=$db->prepare("SELECT COALESCE(SUM(fecha_hora<?),0) AS visitsOverdue,COALESCE(SUM(fecha_hora>=? AND fecha_hora<?),0) AS visitsSoon FROM visitas WHERE estado_visita IN ('solicitada','confirmada')".($owner ? ' AND vendedor_id=?' : ''));
        $st->execute(array_merge([$clock['now'],$clock['now'],$clock['week']],$owner)); $result+=array_map('intval',$st->fetch());
        $st=$db->prepare("SELECT COUNT(*) FROM reservas WHERE estado_reserva='pendiente'".($owner ? ' AND vendedor_id=?' : '')); $st->execute($owner); $result['reservations']=(int)$st->fetchColumn();
        $result['unassigned']=$owner ? 0 : (int)$db->query("SELECT COUNT(*) FROM leads WHERE estado=1 AND vendedor_id IS NULL AND etapa NOT IN ('cerrado','perdido')")->fetchColumn();
        return $result;
    }
    public static function listing(array $user, string $filter, ?DateTimeImmutable $now=null): array
    {
        self::access($user); if (!isset(self::FILTERS[$filter])) throw new DomainException('Filtro de pendientes inválido.');
        $clock=self::clock($now); $params=[];
        $sql="SELECT s.*,l.etapa,c.nombres AS cliente,c.telefono,c.email,p.titulo,CONCAT(v.nombres,' ',v.apellidos) AS vendedor,CONCAT(a.nombres,' ',a.apellidos) AS completada_por FROM seguimientos s JOIN leads l ON l.id=s.lead_id JOIN clientes c ON c.id=l.cliente_id LEFT JOIN propiedades p ON p.id=l.propiedad_id LEFT JOIN usuarios v ON v.id=l.vendedor_id LEFT JOIN usuarios a ON a.id=s.tarea_completada_por WHERE l.estado=1 AND s.proxima_accion IS NOT NULL";
        if ($user['rol']==='vendedor') { $sql.=' AND l.vendedor_id=?'; $params[]=$user['id']; }
        if ($filter==='completadas') $sql.=' AND s.tarea_completada_at IS NOT NULL';
        else {
            $sql.=" AND s.tarea_completada_at IS NULL AND l.etapa NOT IN ('cerrado','perdido')";
            if ($filter==='vencidas') { $sql.=' AND s.proxima_accion<?'; $params[]=$clock['now']; }
            if ($filter==='hoy') { $sql.=' AND s.proxima_accion>=? AND s.proxima_accion<?'; $params[]=$clock['now']; $params[]=$clock['tomorrow']; }
            if ($filter==='proximas') { $sql.=' AND s.proxima_accion>=?'; $params[]=$clock['tomorrow']; }
        }
        $st=Database::connect()->prepare($sql.($filter==='completadas' ? ' ORDER BY s.tarea_completada_at DESC,s.id DESC' : ' ORDER BY s.proxima_accion,s.id').' LIMIT 100'); $st->execute($params); return $st->fetchAll();
    }
    public static function visits(array $user, bool $overdue, ?DateTimeImmutable $now=null): array
    {
        self::access($user); $clock=self::clock($now);
        $sql="SELECT v.id,v.fecha_hora,v.modalidad,v.estado_visita,c.nombres AS cliente,p.titulo,CONCAT(u.nombres,' ',u.apellidos) AS vendedor FROM visitas v JOIN clientes c ON c.id=v.cliente_id JOIN propiedades p ON p.id=v.propiedad_id LEFT JOIN usuarios u ON u.id=v.vendedor_id WHERE v.estado_visita IN ('solicitada','confirmada') AND ";
        $sql.=$overdue ? 'v.fecha_hora<?' : 'v.fecha_hora>=? AND v.fecha_hora<?';
        $params=$overdue ? [$clock['now']] : [$clock['now'],$clock['week']];
        if ($user['rol']==='vendedor') { $sql.=' AND v.vendedor_id=?'; $params[]=$user['id']; }
        $st=Database::connect()->prepare($sql.' ORDER BY v.fecha_hora,v.id LIMIT 20'); $st->execute($params); return $st->fetchAll();
    }
    public static function unassigned(array $user): array
    {
        self::access($user); if ($user['rol']==='vendedor') return [];
        return Database::connect()->query("SELECT l.id,l.created_at,c.nombres AS cliente FROM leads l JOIN clientes c ON c.id=l.cliente_id WHERE l.estado=1 AND l.vendedor_id IS NULL AND l.etapa NOT IN ('cerrado','perdido') ORDER BY l.id LIMIT 20")->fetchAll();
    }
    public static function reservations(array $user): array
    {
        self::access($user); $sql="SELECT r.id,r.created_at,c.nombres AS cliente,p.titulo FROM reservas r JOIN clientes c ON c.id=r.cliente_id JOIN propiedades p ON p.id=r.propiedad_id WHERE r.estado_reserva='pendiente'"; $params=[];
        if ($user['rol']==='vendedor') { $sql.=' AND r.vendedor_id=?'; $params[]=$user['id']; }
        $st=Database::connect()->prepare($sql.' ORDER BY r.id LIMIT 20'); $st->execute($params); return $st->fetchAll();
    }
    public static function find(int $id, array $user): ?array
    {
        self::access($user); $sql='SELECT s.*,l.vendedor_id FROM seguimientos s JOIN leads l ON l.id=s.lead_id WHERE s.id=? AND l.estado=1 AND s.proxima_accion IS NOT NULL'; $params=[$id];
        if ($user['rol']==='vendedor') { $sql.=' AND l.vendedor_id=?'; $params[]=$user['id']; }
        $st=Database::connect()->prepare($sql); $st->execute($params); return $st->fetch() ?: null;
    }
    public static function resolve(int $id, bool $done, array $user, ?DateTimeImmutable $now=null): int
    {
        self::access($user); $db=Database::connect(); $db->beginTransaction();
        try {
            $st=$db->prepare('SELECT lead_id FROM seguimientos WHERE id=? AND proxima_accion IS NOT NULL'); $st->execute([$id]); $leadId=$st->fetchColumn();
            if (!$leadId) throw new DomainException('Pendiente no encontrado.');
            $st=$db->prepare('SELECT * FROM leads WHERE id=? FOR UPDATE'); $st->execute([$leadId]); $lead=$st->fetch();
            if (!$lead || !$lead['estado'] || ($user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id'])) throw new DomainException('No tienes permiso para atender este pendiente.');
            $st=$db->prepare('SELECT * FROM seguimientos WHERE id=? FOR UPDATE'); $st->execute([$id]); $task=$st->fetch();
            if (!$task || !$task['proxima_accion']) throw new DomainException('Pendiente no encontrado.');
            if (!$done && in_array($lead['etapa'],['cerrado','perdido'],true)) throw new DomainException('No puedes reabrir pendientes de una consulta cerrada o perdida.');
            if ((bool)$task['tarea_completada_at']===$done) { $db->commit(); return (int)$leadId; }
            $db->prepare('UPDATE seguimientos SET tarea_completada_at=?,tarea_completada_por=? WHERE id=?')->execute([$done ? self::clock($now)['now'] : null,$done ? $user['id'] : null,$id]);
            $db->prepare("INSERT INTO seguimientos (lead_id,usuario_id,tipo,detalle) VALUES (?,?,'nota',?)")->execute([$leadId,$user['id'],'Pendiente del seguimiento #'.$id.($done ? ' marcado como completado.' : ' reabierto.')]);
            $db->commit(); return (int)$leadId;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
}
