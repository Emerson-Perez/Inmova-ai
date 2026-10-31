<?php
class Report
{
    public static function period(mixed $from=null, mixed $to=null): array
    {
        $now=new DateTimeImmutable('today',new DateTimeZone('America/Lima'));
        $from=$from ?? $now->format('Y-m-01'); $to=$to ?? $now->format('Y-m-d');
        if (!is_string($from) || !is_string($to)) throw new DomainException('Indica fechas válidas.');
        if (str_contains($from,"\0") || str_contains($to,"\0")) throw new DomainException('Indica fechas válidas.');
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$from,new DateTimeZone('America/Lima'));
        $end=DateTimeImmutable::createFromFormat('!Y-m-d',$to,new DateTimeZone('America/Lima'));
        if (!$start || !$end || $start->format('Y-m-d')!==$from || $end->format('Y-m-d')!==$to || $start>$end) throw new DomainException('Elige un rango válido: Desde no puede superar Hasta.');
        if ($start->diff($end)->days>365) throw new DomainException('Consulta hasta 366 días por reporte.');
        return ['from'=>$from,'to'=>$to,'until'=>$end->modify('+1 day')->format('Y-m-d')];
    }

    private static function grouped(string $table, string $field, string $where, array $params): array
    {
        $st=Database::connect()->prepare("SELECT $field AS label,COUNT(*) AS total FROM $table WHERE $where GROUP BY $field");
        $st->execute($params); return array_map('intval',array_column($st->fetchAll(),'total','label'));
    }

    public static function summary(array $user, array $period): array
    {
        if (!in_array($user['rol'],['admin','superadmin','vendedor'],true)) throw new DomainException('No tienes permiso para consultar reportes.');
        $seller=$user['rol']==='vendedor';
        $owner=$seller ? [$user['id']] : [];
        $dates=[$period['from'],$period['until']];
        $db=Database::connect(); $db->beginTransaction();
        try {
            $inventory=self::grouped('propiedades','disponibilidad','estado=1'.($seller ? ' AND creado_por=?' : ''),$owner);
            $leads=self::grouped('leads','etapa','estado=1 AND created_at>=? AND created_at<?'.($seller ? ' AND vendedor_id=?' : ''),array_merge($dates,$owner));
            $visits=self::grouped('visitas','estado_visita','fecha_hora>=? AND fecha_hora<?'.($seller ? ' AND vendedor_id=?' : ''),array_merge($dates,$owner));
            $reservations=self::grouped('reservas','estado_reserva','created_at>=? AND created_at<?'.($seller ? ' AND vendedor_id=?' : ''),array_merge($dates,$owner));
            $scope='c.fecha_cierre>=? AND c.fecha_cierre<?'.($seller ? ' AND r.vendedor_id=?' : '');
            $st=$db->prepare("SELECT c.tipo,c.moneda,COUNT(*) AS total,SUM(c.monto_final) AS monto FROM cierres c JOIN reservas r ON r.id=c.reserva_id WHERE $scope GROUP BY c.tipo,c.moneda ORDER BY c.tipo,c.moneda");
            $st->execute(array_merge($dates,$owner)); $amounts=$st->fetchAll();
            $st=$db->prepare("SELECT c.id,c.tipo,c.moneda,c.monto_final,c.fecha_cierre,p.titulo,cl.nombres AS cliente FROM cierres c JOIN reservas r ON r.id=c.reserva_id JOIN propiedades p ON p.id=r.propiedad_id JOIN clientes cl ON cl.id=r.cliente_id WHERE $scope ORDER BY c.fecha_cierre DESC,c.id DESC LIMIT 20");
            $st->execute(array_merge($dates,$owner)); $recent=$st->fetchAll();
            $db->commit();
            return ['inventory'=>$inventory,'leads'=>$leads,'visits'=>$visits,'reservations'=>$reservations,'amounts'=>$amounts,'recent'=>$recent,'totals'=>['Propiedades activas'=>array_sum($inventory),'Consultas recibidas'=>array_sum($leads),'Visitas en el período'=>array_sum($visits),'Reservas solicitadas'=>array_sum($reservations),'Operaciones cerradas'=>array_sum(array_map('intval',array_column($amounts,'total')))]];
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }

    public static function csvRows(array $report, array $period): array
    {
        $rows=[['Categoría','Indicador','Cantidad','Moneda','Monto pactado','Desde','Hasta']];
        $categories=[
            'inventory'=>['Inventario actual',['disponible'=>'Disponible','reservado'=>'Reservado','vendido'=>'Vendido','alquilado'=>'Alquilado']],
            'leads'=>['Consultas recibidas',Lead::STAGES],
            'visits'=>['Visitas programadas',Visit::STATES],
            'reservations'=>['Reservas solicitadas',Reservation::STATES],
        ];
        foreach ($categories as $key=>[$category,$labels]) foreach ($labels as $value=>$label) $rows[]=[$category,$label,$report[$key][$value] ?? 0,'','',$key==='inventory' ? '' : $period['from'],$key==='inventory' ? '' : $period['to']];
        foreach ($report['amounts'] as $row) $rows[]=['Cierres',Closing::TYPES[$row['tipo']].($row['tipo']==='alquiler' ? ' - renta mensual' : ''),(int)$row['total'],$row['moneda'],$row['monto'],$period['from'],$period['to']];
        return $rows;
    }
}
