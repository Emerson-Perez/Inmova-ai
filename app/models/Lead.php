<?php
class Lead
{
    public const STAGES = ['nuevo'=>'Nuevo','contactado'=>'Contactado','visita_programada'=>'Visita programada','negociacion'=>'Negociación','reservado'=>'Reservado','cerrado'=>'Cerrado','perdido'=>'Perdido'];
    public const TYPES = ['llamada'=>'Llamada','mensaje'=>'Mensaje','correo'=>'Correo','visita'=>'Visita','nota'=>'Nota'];

    public static function capture(int $propertyId, array $data): bool
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $st = $db->prepare('SELECT p.id FROM propiedades p JOIN tipos_propiedad t ON t.id=p.tipo_id JOIN modalidades m ON m.id=p.modalidad_id WHERE p.id=? AND p.estado=1 AND p.publicado=1 AND t.estado=1 AND m.estado=1 FOR UPDATE');
            $st->execute([$propertyId]);
            if (!$st->fetch()) { $db->rollBack(); return false; }
            $db->prepare('INSERT INTO clientes (nombres,email,telefono) VALUES (?,?,?)')->execute([$data['nombres'],$data['email'] ?: null,$data['telefono'] ?: null]);
            $clientId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO leads (cliente_id,propiedad_id,origen,notas) VALUES (?,?,'portal',?)")->execute([$clientId,$propertyId,$data['mensaje']]);
            $db->commit();
            return true;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }

    public static function listing(array $user, string $stage, string $query): array
    {
        $sql = 'SELECT l.id,l.etapa,l.created_at,c.nombres,c.email,c.telefono,p.titulo,u.nombres AS vendedor FROM leads l JOIN clientes c ON c.id=l.cliente_id LEFT JOIN propiedades p ON p.id=l.propiedad_id LEFT JOIN usuarios u ON u.id=l.vendedor_id WHERE l.estado=1';
        $params = [];
        if ($user['rol'] === 'vendedor') { $sql .= ' AND l.vendedor_id=:owner'; $params['owner']=$user['id']; }
        if ($stage !== '') { $sql .= ' AND l.etapa=:stage'; $params['stage']=$stage; }
        if ($query !== '') {
            $sql .= ' AND (c.nombres LIKE :name OR c.email LIKE :email OR c.telefono LIKE :phone)';
            foreach (['name','email','phone'] as $key) $params[$key]='%'.$query.'%';
        }
        $st = Database::connect()->prepare($sql.' ORDER BY l.id DESC LIMIT 100');
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $st = Database::connect()->prepare('SELECT l.*,c.nombres,c.apellidos,c.email,c.telefono,p.titulo,p.moneda FROM leads l JOIN clientes c ON c.id=l.cliente_id LEFT JOIN propiedades p ON p.id=l.propiedad_id WHERE l.id=? AND l.estado=1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function assignProperty(int $id, int $propertyId, array $user): void
    {
        if (!in_array($user['rol'],['superadmin','admin','vendedor'],true)) throw new DomainException('No tienes permiso para seleccionar el inmueble de esta consulta.');
        $db=Database::connect(); $db->beginTransaction();
        try {
            // Mantener el orden propiedad → consulta de visitas, reservas y cierres.
            $st=$db->prepare('SELECT p.*,t.estado AS tipo_activo,m.estado AS modalidad_activa FROM propiedades p JOIN tipos_propiedad t ON t.id=p.tipo_id JOIN modalidades m ON m.id=p.modalidad_id WHERE p.id=? FOR UPDATE');
            $st->execute([$propertyId]); $property=$st->fetch();
            $st=$db->prepare('SELECT * FROM leads WHERE id=? AND estado=1 FOR UPDATE'); $st->execute([$id]); $lead=$st->fetch();
            if (!$lead || ($user['rol']==='vendedor' && (int)$lead['vendedor_id']!==$user['id'])) throw new DomainException('No tienes permiso para modificar esta consulta.');
            if ((int)$lead['propiedad_id']===$propertyId && $property) { $db->commit(); return; }
            if (in_array($lead['etapa'],['cerrado','perdido'],true)) throw new DomainException('Una consulta cerrada o perdida no puede cambiar de inmueble.');
            if (!$property || !$property['estado'] || !$property['tipo_activo'] || !$property['modalidad_activa'] || !in_array($property['disponibilidad'],['disponible','reservado'],true)) throw new DomainException('Selecciona un inmueble activo disponible o reservado, con tipo y modalidad activos.');
            if ($user['rol']==='vendedor' && !$property['publicado'] && (int)$property['creado_por']!==$user['id']) throw new DomainException('Solo puedes seleccionar inmuebles publicados o propios.');
            // El bloqueo de la consulta serializa estas lecturas con las mutaciones.
            // No bloquear visitas después de la consulta: evitaría invertir su orden.
            $st=$db->prepare("SELECT id FROM reservas WHERE lead_id=? AND estado_reserva IN ('pendiente','aprobada','cerrada') LIMIT 1"); $st->execute([$id]);
            if ($st->fetchColumn()) throw new DomainException('Cancela primero las reservas pendientes o aprobadas. Una operación cerrada conserva su inmueble.');
            $st=$db->prepare("SELECT id FROM visitas WHERE lead_id=? AND estado_visita IN ('solicitada','confirmada') LIMIT 1"); $st->execute([$id]);
            if ($st->fetchColumn()) throw new DomainException('Cancela o registra el resultado de las visitas activas antes de cambiar el inmueble.');
            $stage=$lead['etapa']==='visita_programada' ? 'contactado' : $lead['etapa'];
            $db->prepare('UPDATE leads SET propiedad_id=?,etapa=? WHERE id=?')->execute([$propertyId,$stage,$id]);
            $detail='Inmueble de trabajo: '.($lead['propiedad_id'] ? '#'.$lead['propiedad_id'] : 'sin inmueble').' → #'.$propertyId.' · '.$property['titulo'];
            if ($stage!==$lead['etapa']) $detail.="\nEtapa: Visita programada → Contactado; no quedan visitas activas para el nuevo inmueble.";
            $db->prepare("INSERT INTO seguimientos (lead_id,usuario_id,tipo,detalle) VALUES (?,?,'nota',?)")->execute([$id,$user['id'],$detail]);
            $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }

    public static function sellers(): array
    {
        return Database::connect()->query("SELECT u.id,CONCAT(u.nombres,' ',u.apellidos) AS nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.estado=1 AND r.estado=1 AND r.nombre='vendedor' ORDER BY u.nombres")->fetchAll();
    }

    public static function history(int $id): array
    {
        $st = Database::connect()->prepare('SELECT s.*,CONCAT(u.nombres,\' \',u.apellidos) AS autor FROM seguimientos s JOIN usuarios u ON u.id=s.usuario_id WHERE s.lead_id=? ORDER BY s.id DESC');
        $st->execute([$id]);
        return $st->fetchAll();
    }

    public static function update(int $id, array $user, string $stage, ?int $seller, string $detail, string $type, ?string $next): bool
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $st = $db->prepare('SELECT * FROM leads WHERE id=? AND estado=1 FOR UPDATE');
            $st->execute([$id]);
            $old = $st->fetch();
            if (!$old || ($user['rol']==='vendedor' && (int)$old['vendedor_id']!==$user['id'])) { $db->rollBack(); return false; }
            if (!isset(self::STAGES[$stage])) throw new DomainException('Selecciona una etapa válida.');
            // El bloqueo del lead serializa estas comprobaciones con reservas y cierres.
            $st=$db->prepare("SELECT id FROM reservas WHERE lead_id=? AND estado_reserva='aprobada' LIMIT 1"); $st->execute([$id]);
            $approved=(bool)$st->fetchColumn();
            $st=$db->prepare('SELECT c.id FROM cierres c JOIN reservas r ON r.id=c.reserva_id WHERE r.lead_id=? LIMIT 1'); $st->execute([$id]);
            $closed=(bool)$st->fetchColumn();
            if ($stage==='cerrado' && !$closed) throw new DomainException('Registra el cierre desde una reserva aprobada para cerrar esta consulta.');
            if ($stage==='reservado' && !$approved) throw new DomainException('Aprueba una reserva para pasar esta consulta a Reservado.');
            if ($approved && $stage!=='reservado') throw new DomainException('Cancela primero la reserva aprobada antes de cambiar la etapa de esta consulta.');
            if ($closed && $stage!=='cerrado') throw new DomainException('Esta consulta tiene una operación cerrada y debe conservar la etapa Cerrado.');
            if ($user['rol']==='vendedor') $seller = $old['vendedor_id'] ? (int)$old['vendedor_id'] : null;
            $changes = [];
            if ($old['etapa'] !== $stage) $changes[] = 'Etapa: '.self::STAGES[$old['etapa']].' → '.self::STAGES[$stage];
            if ((int)$old['vendedor_id'] !== (int)$seller) $changes[] = 'Asignación de vendedor: '.($seller ?? 'sin asignar');
            $db->prepare('UPDATE leads SET etapa=?,vendedor_id=? WHERE id=?')->execute([$stage,$seller,$id]);
            if ($changes) $db->prepare("INSERT INTO seguimientos (lead_id,usuario_id,tipo,detalle) VALUES (?,?,'nota',?)")->execute([$id,$user['id'],implode("\n",$changes)]);
            if ($detail !== '') $db->prepare('INSERT INTO seguimientos (lead_id,usuario_id,tipo,detalle,proxima_accion) VALUES (?,?,?,?,?)')->execute([$id,$user['id'],$type,$detail,$next]);
            $db->commit();
            return true;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
}
