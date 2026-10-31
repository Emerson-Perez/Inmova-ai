<?php
class Assistant
{
    public static function defaults(): array { return ['q'=>'','tipo_id'=>'','modalidad_id'=>'','moneda'=>'','min'=>'','max'=>'']; }
    public static function validateFilters(array $data, ?array $catalogs=null): array
    {
        $catalogs=$catalogs ?? Property::catalogs(); $filters=self::defaults();
        foreach ($filters as $key=>$unused) {
            if (!array_key_exists($key,$data) || !is_string($data[$key])) throw new DomainException('Los filtros tienen un formato inválido.');
            $filters[$key]=trim($data[$key]);
        }
        if (mb_strlen($filters['q'])>160) throw new DomainException('La ubicación o título admite hasta 160 caracteres.');
        foreach (['tipo_id'=>'types','modalidad_id'=>'modes'] as $key=>$catalog) if ($filters[$key]!=='' && !in_array($filters[$key],array_map('strval',array_column($catalogs[$catalog],'id')),true)) throw new DomainException('Selecciona un tipo y una modalidad válidos.');
        if (!in_array($filters['moneda'],['','PEN','USD'],true)) throw new DomainException('Selecciona una moneda válida.');
        foreach (['min','max'] as $key) if ($filters[$key]!=='' && (!preg_match('/^[0-9]{1,7}$/D',$filters[$key]) || (int)$filters[$key]>5000000)) throw new DomainException('El presupuesto debe ser entero, entre 0 y 5 000 000.');
        if (($filters['min']!=='' || $filters['max']!=='') && $filters['moneda']==='') throw new DomainException('Indica soles o dólares para filtrar por presupuesto.');
        if ($filters['min']!=='' && $filters['max']!=='' && (int)$filters['min']>(int)$filters['max']) throw new DomainException('El presupuesto mínimo no puede superar al máximo.');
        return $filters;
    }
    public static function open(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new DomainException('Sesión del asistente inválida.');
        $db=Database::connect();
        $db->prepare('INSERT INTO conversaciones_ia (sesion) VALUES (?) ON DUPLICATE KEY UPDATE sesion=VALUES(sesion)')->execute([$token]);
        return self::owned($token);
    }
    public static function owned(string $token, bool $lock=false): ?array
    {
        $st=Database::connect()->prepare('SELECT * FROM conversaciones_ia WHERE sesion=?'.($lock ? ' FOR UPDATE' : '')); $st->execute([$token]); return $st->fetch() ?: null;
    }
    public static function messages(int $conversation): array
    {
        $st=Database::connect()->prepare('SELECT id,emisor,contenido,metadatos,created_at FROM mensajes_ia WHERE conversacion_id=? ORDER BY id LIMIT 60'); $st->execute([$conversation]); return $st->fetchAll();
    }
    public static function search(array $filters): array
    {
        $results=PublicProperty::search(self::validateFilters($filters),1,true);
        $results['properties']=array_slice($results['properties'],0,6); return $results;
    }
    public static function cards(array $ids): array
    {
        $result=[];
        foreach (array_slice(array_unique($ids),0,6) as $id) {
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) continue;
            $property=PublicProperty::find((int)$id);
            if ($property && $property['disponibilidad']==='disponible') $result[]=$property;
        }
        return $result;
    }
    public static function record(string $token, string $input, string $reply, array $filters, array $ids, string $source): void
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::owned($token,true);
            if (!$row || $row['estado']!=='activa') throw new DomainException('Esta conversación ya fue derivada o cerrada. Inicia una nueva para continuar.');
            $st=$db->prepare('SELECT COUNT(*) FROM mensajes_ia WHERE conversacion_id=?'); $st->execute([$row['id']]);
            if ((int)$st->fetchColumn()>56) throw new DomainException('La conversación alcanzó su límite. Inicia una nueva para continuar.');
            $st=$db->prepare('INSERT INTO mensajes_ia (conversacion_id,emisor,contenido,metadatos) VALUES (?,?,?,?)');
            $st->execute([$row['id'],'cliente',$input,json_encode(['source'=>$source],JSON_THROW_ON_ERROR)]);
            $st->execute([$row['id'],'ia',$reply,json_encode(['source'=>$source,'property_ids'=>array_map('intval',$ids)],JSON_THROW_ON_ERROR)]);
            $db->prepare('UPDATE conversaciones_ia SET filtros=?,updated_at=NOW() WHERE id=?')->execute([json_encode($filters,JSON_THROW_ON_ERROR),$row['id']]); $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function reserveAi(string $token, string $ipHash, array $config, ?DateTimeImmutable $now=null): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$ipHash)) throw new DomainException('No se pudo validar la solicitud.');
        $now=$now ?? new DateTimeImmutable('now',new DateTimeZone('America/Lima')); $day=$now->format('Y-m-d');
        $db=Database::connect(); $db->beginTransaction();
        try {
            AiQuota::take($db,$ipHash,$config,$now);
            $row=self::owned($token,true);
            if (!$row || $row['estado']!=='activa' || (int)$row['turnos_ia']>=20) throw new DomainException('Esta conversación ya no admite mensajes de IA. Puedes iniciar una nueva o contactar al equipo.');
            $st=$db->prepare('SELECT COUNT(*) FROM mensajes_ia WHERE conversacion_id=?'); $st->execute([$row['id']]);
            if ((int)$st->fetchColumn()>56) throw new DomainException('La conversación alcanzó su límite. Inicia una nueva antes de enviar más mensajes.');
            if ($row['ultima_ia'] && $now->getTimestamp()-(new DateTimeImmutable($row['ultima_ia'],new DateTimeZone('America/Lima')))->getTimestamp()<5) throw new DomainException('Espera unos segundos antes de enviar otro mensaje.');
            $db->prepare('UPDATE conversaciones_ia SET turnos_ia=turnos_ia+1,ultima_ia=? WHERE id=?')->execute([$now->format('Y-m-d H:i:s'),$row['id']]); $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function handoff(string $token, ?int $propertyId, array $data): int
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::owned($token,true);
            if (!$row) throw new DomainException('Conversación no encontrada.');
            if ($row['lead_id']) { $db->commit(); return (int)$row['lead_id']; }
            if ($row['estado']!=='activa') throw new DomainException('La conversación está cerrada.');
            if ($propertyId) {
                $st=$db->prepare("SELECT p.id FROM propiedades p JOIN tipos_propiedad t ON t.id=p.tipo_id JOIN modalidades m ON m.id=p.modalidad_id WHERE p.id=? AND p.estado=1 AND p.publicado=1 AND p.disponibilidad='disponible' AND t.estado=1 AND m.estado=1 FOR UPDATE"); $st->execute([$propertyId]);
                if (!$st->fetch()) throw new DomainException('El inmueble seleccionado ya no está disponible. Elige otro o envía una consulta general.');
            }
            $db->prepare('INSERT INTO clientes (nombres,email,telefono) VALUES (?,?,?)')->execute([$data['nombres'],$data['email']==='' ? null : $data['email'],$data['telefono']==='' ? null : $data['telefono']]); $client=(int)$db->lastInsertId();
            $db->prepare("INSERT INTO leads (cliente_id,propiedad_id,origen,notas) VALUES (?,?,'agente_ia',?)")->execute([$client,$propertyId,$data['mensaje']]); $lead=(int)$db->lastInsertId();
            $db->prepare("UPDATE conversaciones_ia SET cliente_id=?,lead_id=?,estado='derivada' WHERE id=?")->execute([$client,$lead,$row['id']]);
            $db->prepare("INSERT INTO mensajes_ia (conversacion_id,emisor,contenido,metadatos) VALUES (?,'ia',?,?)")->execute([$row['id'],'Tu consulta fue registrada. El equipo podrá contactarte por los datos indicados.',json_encode(['source'=>'sistema'],JSON_THROW_ON_ERROR)]);
            $db->commit(); return $lead;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function forLead(int $leadId, array $user): array
    {
        $lead=Lead::find($leadId);
        if (!$lead || (!in_array($user['rol'],['admin','superadmin'],true) && !($user['rol']==='vendedor' && (int)$lead['vendedor_id']===$user['id']))) throw new DomainException('No tienes acceso a esta conversación.');
        $st=Database::connect()->prepare('SELECT id FROM conversaciones_ia WHERE lead_id=?'); $st->execute([$leadId]); $id=$st->fetchColumn(); return $id ? self::messages((int)$id) : [];
    }
    public static function close(string $token): void
    {
        Database::connect()->prepare("UPDATE conversaciones_ia SET estado='cerrada' WHERE sesion=? AND estado='activa'")->execute([$token]);
    }
}
