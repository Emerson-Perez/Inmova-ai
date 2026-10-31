<?php
class Reservation
{
    public const STATES=['pendiente'=>'Pendiente','aprobada'=>'Aprobada','rechazada'=>'Rechazada','cancelada'=>'Cancelada','cerrada'=>'Cerrada'];

    public static function listing(array $user, string $state, ?int $propertyId=null): array
    {
        $sql="SELECT r.*,c.nombres AS cliente,p.titulo,CONCAT(u.nombres,' ',u.apellidos) AS vendedor FROM reservas r JOIN clientes c ON c.id=r.cliente_id JOIN propiedades p ON p.id=r.propiedad_id JOIN usuarios u ON u.id=r.vendedor_id WHERE 1=1";
        $params=[];
        if ($user['rol']==='vendedor') { $sql.=' AND r.vendedor_id=?'; $params[]=$user['id']; }
        if ($state!=='') { $sql.=' AND r.estado_reserva=?'; $params[]=$state; }
        if ($propertyId!==null) { $sql.=' AND r.propiedad_id=?'; $params[]=$propertyId; }
        $st=Database::connect()->prepare($sql.' ORDER BY r.id DESC LIMIT 100'); $st->execute($params);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $st=Database::connect()->prepare("SELECT r.*,p.titulo,c.nombres AS cliente,CONCAT(u.nombres,' ',u.apellidos) AS vendedor,CONCAT(a.nombres,' ',a.apellidos) AS aprobador FROM reservas r JOIN propiedades p ON p.id=r.propiedad_id JOIN clientes c ON c.id=r.cliente_id JOIN usuarios u ON u.id=r.vendedor_id LEFT JOIN usuarios a ON a.id=r.aprobada_por WHERE r.id=?");
        $st->execute([$id]); return $st->fetch() ?: null;
    }

    public static function forLead(int $id): array
    {
        $st=Database::connect()->prepare('SELECT r.*,p.titulo FROM reservas r JOIN propiedades p ON p.id=r.propiedad_id WHERE r.lead_id=? ORDER BY r.id DESC'); $st->execute([$id]); return $st->fetchAll();
    }

    private static function history(int $leadId, int $author, string $text): void
    {
        Database::connect()->prepare("INSERT INTO seguimientos (lead_id,usuario_id,tipo,detalle) VALUES (?,?,'nota',?)")->execute([$leadId,$author,$text]);
    }

    public static function create(int $leadId, int $seller, int $amount, array $user): int
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            $lead=Lead::find($leadId);
            if (!$lead || !$lead['propiedad_id']) throw new DomainException('La consulta debe tener un inmueble.');
            $st=$db->prepare('SELECT * FROM propiedades WHERE id=? FOR UPDATE'); $st->execute([$lead['propiedad_id']]); $property=$st->fetch();
            $st=$db->prepare('SELECT * FROM leads WHERE id=? AND estado=1 FOR UPDATE'); $st->execute([$leadId]); $lead=$st->fetch();
            if (!$lead || ($user['rol']==='vendedor' && ((int)$lead['vendedor_id']!==$user['id'] || $seller!==$user['id']))) throw new DomainException('No tienes permiso para reservar desde esta consulta.');
            if (!$property || !$property['estado'] || $property['disponibilidad']!=='disponible') throw new DomainException('El inmueble debe estar activo y disponible para solicitar una reserva.');
            if ((int)$lead['propiedad_id']!==(int)$property['id']) throw new DomainException('El inmueble de la consulta cambió. Recarga el CRM antes de solicitar la reserva.');
            if (in_array($lead['etapa'],['cerrado','perdido'],true)) throw new DomainException('No puedes reservar desde una consulta cerrada o perdida.');
            if ($amount<1 || $amount>5000000 || $amount>(float)$property['precio']) throw new DomainException('El monto debe ser entero, mayor que cero y no superar el precio del inmueble ni 5 000 000.');
            $st=$db->prepare("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.estado=1 AND r.estado=1 AND r.nombre='vendedor'"); $st->execute([$seller]);
            if (!$st->fetch()) throw new DomainException('Selecciona un vendedor activo.');
            $st=$db->prepare("SELECT id FROM reservas WHERE lead_id=? AND estado_reserva IN ('pendiente','aprobada') FOR UPDATE"); $st->execute([$leadId]);
            if ($st->fetch()) throw new DomainException('Esta consulta ya tiene una reserva pendiente o aprobada.');
            $db->prepare('INSERT INTO reservas (lead_id,propiedad_id,cliente_id,vendedor_id,monto_reserva,moneda) VALUES (?,?,?,?,?,?)')->execute([$leadId,$property['id'],$lead['cliente_id'],$seller,$amount,$property['moneda']]);
            $id=(int)$db->lastInsertId();
            self::history($leadId,$user['id'],'Reserva solicitada #'.$id.' · '.$property['moneda'].' '.$amount.' · Vendedor #'.$seller);
            $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }

    public static function transition(int $id, string $action, string $reason, array $user): void
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::find($id);
            if (!$row) throw new DomainException('Reserva no encontrada.');
            $st=$db->prepare('SELECT * FROM propiedades WHERE id=? FOR UPDATE'); $st->execute([$row['propiedad_id']]); $property=$st->fetch();
            $st=$db->prepare('SELECT * FROM reservas WHERE id=? FOR UPDATE'); $st->execute([$id]); $row=$st->fetch();
            $admin=in_array($user['rol'],['superadmin','admin'],true);
            if (!$admin && ($user['rol']!=='vendedor' || (int)$row['vendedor_id']!==$user['id'] || $action!=='cancelada' || $row['estado_reserva']!=='pendiente')) throw new DomainException('Solo un administrador puede aprobar, rechazar o cancelar reservas aprobadas.');
            $allowed=$row['estado_reserva']==='pendiente' ? ['aprobada','rechazada','cancelada'] : ($row['estado_reserva']==='aprobada' ? ['cancelada'] : []);
            if (!in_array($action,$allowed,true)) throw new DomainException('La reserva ya cambió de estado o la acción no está permitida.');
            $lead=null;
            if ($row['lead_id']) { $st=$db->prepare('SELECT * FROM leads WHERE id=? FOR UPDATE'); $st->execute([$row['lead_id']]); $lead=$st->fetch(); }
            if ($action==='aprobada') {
                if (!$property['estado'] || $property['disponibilidad']!=='disponible') throw new DomainException('El inmueble ya no está disponible para aprobar esta reserva.');
                $st=$db->prepare("SELECT id FROM reservas WHERE propiedad_id=? AND estado_reserva='aprobada' AND id<>?"); $st->execute([$property['id'],$id]);
                if ($st->fetch()) throw new DomainException('Ya existe otra reserva aprobada para el inmueble.');
                if ($row['moneda']!==$property['moneda'] || (float)$row['monto_reserva']>(float)$property['precio']) throw new DomainException('El precio o la moneda del inmueble cambiaron. Cancela esta solicitud y crea una nueva.');
                if ($lead && (!$lead['estado'] || in_array($lead['etapa'],['cerrado','perdido'],true))) throw new DomainException('La consulta está cerrada, perdida o inactiva.');
                if ($lead && (int)$lead['propiedad_id']!==(int)$row['propiedad_id']) throw new DomainException('El inmueble de la consulta no coincide con la reserva. Cancela la solicitud y revisa el CRM.');
                $db->prepare("UPDATE propiedades SET disponibilidad='reservado' WHERE id=?")->execute([$property['id']]);
                $db->prepare('UPDATE reservas SET aprobada_por=?,etapa_anterior=? WHERE id=?')->execute([$user['id'],$lead['etapa'] ?? null,$id]);
                if ($lead) $db->prepare("UPDATE leads SET etapa='reservado' WHERE id=?")->execute([$lead['id']]);
            }
            if ($action==='cancelada' && $row['estado_reserva']==='aprobada') {
                if ($property['disponibilidad']==='reservado') $db->prepare("UPDATE propiedades SET disponibilidad='disponible' WHERE id=?")->execute([$property['id']]);
                if ($lead && $lead['etapa']==='reservado') $db->prepare('UPDATE leads SET etapa=? WHERE id=?')->execute([isset(Lead::STAGES[$row['etapa_anterior'] ?? '']) && $row['etapa_anterior']!=='reservado' ? $row['etapa_anterior'] : 'negociacion',$lead['id']]);
            }
            $db->prepare('UPDATE reservas SET estado_reserva=? WHERE id=?')->execute([$action,$id]);
            if ($row['lead_id']) self::history((int)$row['lead_id'],$user['id'],'Reserva #'.$id.' · '.self::STATES[$row['estado_reserva']].' → '.self::STATES[$action]."\n".$reason);
            $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
}
