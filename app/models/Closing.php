<?php
class Closing
{
    public const TYPES=['venta'=>'Venta','alquiler'=>'Alquiler'];

    private const SELECT = "SELECT c.*,r.propiedad_id,r.cliente_id,r.vendedor_id,r.lead_id,p.titulo,cl.nombres AS cliente,CONCAT(v.nombres,' ',v.apellidos) AS vendedor,CONCAT(a.nombres,' ',a.apellidos) AS autor FROM cierres c JOIN reservas r ON r.id=c.reserva_id JOIN propiedades p ON p.id=r.propiedad_id JOIN clientes cl ON cl.id=r.cliente_id JOIN usuarios v ON v.id=r.vendedor_id JOIN usuarios a ON a.id=c.registrado_por";

    public static function listing(array $user): array
    {
        $st=Database::connect()->prepare(self::SELECT.($user['rol']==='vendedor' ? ' WHERE r.vendedor_id=?' : '').' ORDER BY c.fecha_cierre DESC,c.id DESC LIMIT 100');
        $st->execute($user['rol']==='vendedor' ? [$user['id']] : []); return $st->fetchAll();
    }
    public static function forReservation(int $id): ?array
    {
        $st=Database::connect()->prepare(self::SELECT.' WHERE c.reserva_id=?'); $st->execute([$id]); return $st->fetch() ?: null;
    }
    public static function find(int $id): ?array
    {
        $st=Database::connect()->prepare(self::SELECT.' WHERE c.id=?'); $st->execute([$id]); return $st->fetch() ?: null;
    }
    public static function operation(int $property): ?string
    {
        $st=Database::connect()->prepare('SELECT m.slug FROM propiedades p JOIN modalidades m ON m.id=p.modalidad_id WHERE p.id=?'); $st->execute([$property]);
        return match ($st->fetchColumn()) { 'terrenos','venta-deptos'=>'venta', 'alquiler'=>'alquiler', default=>null };
    }
    public static function create(int $reservationId, int $amount, string $day, string $notes, array $user): int
    {
        if (!in_array($user['rol'],['admin','superadmin'],true)) throw new DomainException('Solo un administrador puede confirmar un cierre.');
        if (str_contains($day,"\0")) throw new DomainException('Indica una fecha de cierre válida.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$day,new DateTimeZone('America/Lima'));
        $today=new DateTimeImmutable('today',new DateTimeZone('America/Lima'));
        if (!$date || $date->format('Y-m-d')!==$day || $date>$today) throw new DomainException('Indica una fecha de cierre válida, hasta hoy (Lima).');
        if ($amount<1 || $amount>5000000 || trim($notes)==='' || mb_strlen($notes)>4000) throw new DomainException('Indica un monto entero entre 1 y 5 000 000 y observaciones de hasta 4000 caracteres.');
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=Reservation::find($reservationId);
            if (!$row) throw new DomainException('Reserva no encontrada.');
            $st=$db->prepare('SELECT * FROM propiedades WHERE id=? FOR UPDATE'); $st->execute([$row['propiedad_id']]); $property=$st->fetch();
            $st=$db->prepare('SELECT * FROM reservas WHERE id=? FOR UPDATE'); $st->execute([$reservationId]); $row=$st->fetch();
            if ($row['estado_reserva']!=='aprobada' || !$property['estado'] || $property['disponibilidad']!=='reservado' || self::forReservation($reservationId)) throw new DomainException('El cierre requiere una reserva aprobada y un inmueble activo en estado Reservado.');
            $st=$db->prepare("SELECT c.id FROM cierres c JOIN reservas r ON r.id=c.reserva_id WHERE r.propiedad_id=?"); $st->execute([$property['id']]);
            if ($st->fetch()) throw new DomainException('Este inmueble ya tiene una operación cerrada.');
            $type=self::operation((int)$property['id']);
            if (!$type) throw new DomainException('La modalidad del inmueble no corresponde a una venta o alquiler compatible.');
            if ($row['moneda']!==$property['moneda'] || $amount<(float)$row['monto_reserva']) throw new DomainException('La moneda debe coincidir y el monto final no puede ser menor al monto de reserva.');
            if ($day<substr($row['created_at'],0,10)) throw new DomainException('El cierre no puede ser anterior a la solicitud de reserva.');
            if ($row['lead_id']) {
                $st=$db->prepare('SELECT * FROM leads WHERE id=? FOR UPDATE'); $st->execute([$row['lead_id']]); $lead=$st->fetch();
                if (!$lead || !$lead['estado'] || in_array($lead['etapa'],['cerrado','perdido'],true)) throw new DomainException('La consulta debe estar activa y no cerrada ni perdida.');
                if ((int)$lead['propiedad_id']!==(int)$row['propiedad_id']) throw new DomainException('La consulta y la reserva deben corresponder al mismo inmueble para registrar el cierre.');
            }
            $db->prepare('INSERT INTO cierres (reserva_id,registrado_por,tipo,monto_final,moneda,fecha_cierre,observaciones) VALUES (?,?,?,?,?,?,?)')->execute([$reservationId,$user['id'],$type,$amount,$row['moneda'],$day,trim($notes)]);
            $id=(int)$db->lastInsertId();
            $db->prepare("UPDATE reservas SET estado_reserva='cerrada' WHERE id=?")->execute([$reservationId]);
            $db->prepare('UPDATE propiedades SET disponibilidad=?,publicado=0 WHERE id=?')->execute([$type==='venta' ? 'vendido' : 'alquilado',$property['id']]);
            if ($row['lead_id']) {
                $db->prepare("UPDATE leads SET etapa='cerrado' WHERE id=?")->execute([$row['lead_id']]);
                self::history((int)$row['lead_id'],$user['id'],'Cierre #'.$id.' · '.self::TYPES[$type].' · '.$row['moneda'].' '.$amount.' · '.$day."\n".$notes);
            }
            // Las otras solicitudes pendientes ya no pueden convertirse en reservas.
            $st=$db->prepare("SELECT id,lead_id FROM reservas WHERE propiedad_id=? AND estado_reserva='pendiente' FOR UPDATE"); $st->execute([$property['id']]);
            foreach ($st->fetchAll() as $pending) {
                $db->prepare("UPDATE reservas SET estado_reserva='rechazada' WHERE id=?")->execute([$pending['id']]);
                if ($pending['lead_id']) self::history((int)$pending['lead_id'],$user['id'],'Reserva #'.$pending['id'].' rechazada: inmueble con operación cerrada #'.$id.'.');
            }
            Opportunity::onClosing((int)$property['id'],$row['lead_id'] ? (int)$row['lead_id'] : null,$id,$amount,$row['moneda'],$type,$user['id']);
            $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    private static function history(int $leadId, int $author, string $text): void
    {
        Database::connect()->prepare("INSERT INTO seguimientos (lead_id,usuario_id,tipo,detalle) VALUES (?,?,'nota',?)")->execute([$leadId,$author,$text]);
    }
}
