<?php
class Visit
{
    public const STATES=['solicitada'=>'Solicitada','confirmada'=>'Confirmada','realizada'=>'Realizada','cancelada'=>'Cancelada'];
    public const MODES=['presencial'=>'Presencial','virtual'=>'Virtual'];

    public static function listing(array $user, string $state, string $day): array
    {
        $sql='SELECT v.*,c.nombres AS cliente,p.titulo,CONCAT(u.nombres,\' \',u.apellidos) AS vendedor FROM visitas v JOIN clientes c ON c.id=v.cliente_id JOIN propiedades p ON p.id=v.propiedad_id LEFT JOIN usuarios u ON u.id=v.vendedor_id WHERE 1=1';
        $params=[];
        if ($user['rol']==='vendedor') { $sql.=' AND v.vendedor_id=?'; $params[]=$user['id']; }
        if ($state!=='') { $sql.=' AND v.estado_visita=?'; $params[]=$state; }
        if ($day!=='') { $sql.=' AND v.fecha_hora>=? AND v.fecha_hora<DATE_ADD(?,INTERVAL 1 DAY)'; $params[]=$day; $params[]=$day; }
        $st=Database::connect()->prepare($sql.' ORDER BY v.fecha_hora DESC,v.id DESC LIMIT 100');
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $st=Database::connect()->prepare('SELECT v.*,c.nombres AS cliente,p.titulo FROM visitas v JOIN clientes c ON c.id=v.cliente_id JOIN propiedades p ON p.id=v.propiedad_id WHERE v.id=?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function forLead(int $id): array
    {
        $st=Database::connect()->prepare('SELECT v.id,v.vendedor_id,v.fecha_hora,v.modalidad,v.estado_visita,v.propiedad_id,p.titulo FROM visitas v JOIN propiedades p ON p.id=v.propiedad_id WHERE v.lead_id=? ORDER BY v.fecha_hora DESC');
        $st->execute([$id]);
        return $st->fetchAll();
    }

    public static function save(array $data, array $user, ?int $id, ?int $leadId): int
    {
        if (!isset(self::STATES[$data['estado_visita']],self::MODES[$data['modalidad']])) throw new DomainException('Selecciona un estado y una modalidad válidos.');
        $date=str_contains($data['fecha_hora'],"\0") ? false : DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$data['fecha_hora'],new DateTimeZone('America/Lima'));
        if (!$date || $date->format('Y-m-d H:i:s')!==$data['fecha_hora']) throw new DomainException('Ingresa una fecha y hora válidas.');
        $now=new DateTimeImmutable('now',new DateTimeZone('America/Lima'));
        if ($data['estado_visita']==='realizada' && ($date>$now || trim($data['resultado'])==='')) throw new DomainException('Para registrar una visita realizada debe haber llegado su hora y debes indicar el resultado.');
        if (mb_strlen($data['resultado'])>4000) throw new DomainException('El resultado admite hasta 4000 caracteres.');
        $db=Database::connect();
        $db->beginTransaction();
        try {
            // Un bloqueo por vendedor serializa la comprobación de horarios.
            $st=$db->prepare("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.estado=1 AND r.estado=1 AND r.nombre='vendedor' FOR UPDATE");
            $st->execute([$data['vendedor_id']]);
            if (!$st->fetch()) throw new DomainException('Selecciona un vendedor activo.');
            // Propiedad antes que visita/consulta: mismo orden que reservas y cierres.
            $candidate=$id ? self::find($id) : Lead::find($leadId ?? 0);
            $st=$db->prepare('SELECT * FROM propiedades WHERE id=? FOR UPDATE');
            $st->execute([$candidate['propiedad_id'] ?? 0]); $property=$st->fetch();
            $old=null;
            if ($id) {
                $st=$db->prepare('SELECT * FROM visitas WHERE id=? FOR UPDATE'); $st->execute([$id]); $old=$st->fetch();
                if (!$old || ($user['rol']==='vendedor' && (int)$old['vendedor_id']!==$user['id'])) throw new DomainException('La visita ya no está disponible para tu usuario.');
                $leadId=$old['lead_id'] ? (int)$old['lead_id'] : null;
            }
            $lead=null;
            if ($leadId) {
                $st=$db->prepare('SELECT * FROM leads WHERE id=? AND estado=1 FOR UPDATE'); $st->execute([$leadId]); $lead=$st->fetch();
                if (!$lead || (!$id && $user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id'])) throw new DomainException('No puedes programar una visita para esta consulta.');
            }
            $active=in_array($data['estado_visita'],['solicitada','confirmada'],true);
            $wasActive=$old && in_array($old['estado_visita'],['solicitada','confirmada'],true);
            $rescheduling=$old && $active && (!$wasActive || $old['fecha_hora']!==$data['fecha_hora'] || (int)$old['vendedor_id']!==(int)$data['vendedor_id']);
            if (!$id && !$active) throw new DomainException('Una visita nueva debe estar solicitada o confirmada.');
            if ((!$old || $old['fecha_hora']!==$data['fecha_hora'] || ($active && !$wasActive)) && $date<=$now) throw new DomainException('La nueva fecha de visita debe ser futura.');
            if (!$id || $rescheduling) {
                if ((!$id && !$lead) || !$property || !$property['estado'] || ($lead && (int)$property['id']!==(int)$lead['propiedad_id'])) throw new DomainException('La consulta debe tener un inmueble activo.');
                if ($lead && in_array($lead['etapa'],['cerrado','perdido'],true)) throw new DomainException('No puedes programar visitas para una consulta cerrada o perdida.');
                if (!in_array($property['disponibilidad'],['disponible','reservado'],true)) throw new DomainException('No puedes programar visitas para un inmueble vendido o alquilado.');
            }
            if ($user['rol']==='vendedor' && $data['vendedor_id']!==$user['id']) throw new DomainException('Solo puedes programar visitas a tu nombre.');
            if (in_array($data['estado_visita'],['solicitada','confirmada'],true)) {
                $st=$db->prepare("SELECT id FROM visitas WHERE vendedor_id=? AND fecha_hora=? AND estado_visita IN ('solicitada','confirmada') AND id<>?");
                $st->execute([$data['vendedor_id'],$data['fecha_hora'],$id ?? 0]);
                if ($st->fetch()) throw new DomainException('El vendedor ya tiene otra visita en esa fecha y hora.');
            }
            if ($id) {
                $db->prepare('UPDATE visitas SET vendedor_id=?,modalidad=?,fecha_hora=?,estado_visita=?,resultado=? WHERE id=?')->execute([$data['vendedor_id'],$data['modalidad'],$data['fecha_hora'],$data['estado_visita'],$data['resultado'] ?: null,$id]);
            } else {
                $db->prepare('INSERT INTO visitas (lead_id,propiedad_id,cliente_id,vendedor_id,modalidad,fecha_hora,estado_visita,resultado) VALUES (?,?,?,?,?,?,?,?)')->execute([$leadId,$lead['propiedad_id'],$lead['cliente_id'],$data['vendedor_id'],$data['modalidad'],$data['fecha_hora'],$data['estado_visita'],$data['resultado'] ?: null]);
                $id=(int)$db->lastInsertId();
                if (in_array($lead['etapa'],['nuevo','contactado'],true)) $db->prepare("UPDATE leads SET etapa='visita_programada' WHERE id=?")->execute([$leadId]);
            }
            if ($leadId) {
                $detail=($old ? 'Visita actualizada' : 'Visita programada').' #'.$id.' · '.$data['fecha_hora'].' (Lima) · '.self::MODES[$data['modalidad']].' · '.self::STATES[$data['estado_visita']].' · Vendedor #'.$data['vendedor_id'];
                if ($data['resultado']!=='') $detail.="\nResultado: ".$data['resultado'];
                $db->prepare("INSERT INTO seguimientos (lead_id,usuario_id,tipo,detalle) VALUES (?,?,'visita',?)")->execute([$leadId,$user['id'],$detail]);
            }
            $db->commit();
            return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
}
