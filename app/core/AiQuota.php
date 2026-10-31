<?php
class AiQuota
{
    // El llamador confirma o revierte esta reserva junto con su propia operación.
    public static function take(PDO $db, string $ipHash, array $config, DateTimeImmutable $now): void
    {
        if (!$db->inTransaction()) throw new LogicException('La cuota requiere una transacción.');
        if (!preg_match('/^[a-f0-9]{64}$/D',$ipHash)) throw new DomainException('No se pudo validar la solicitud.');
        $day=$now->setTimezone(new DateTimeZone('America/Lima'))->format('Y-m-d');
        foreach (['global'=>$config['daily_limit'],$ipHash=>$config['ip_limit']] as $key=>$limit) {
            $db->prepare('INSERT INTO ia_cuotas (dia,clave) VALUES (?,?) ON DUPLICATE KEY UPDATE clave=VALUES(clave)')->execute([$day,$key]);
            $st=$db->prepare('SELECT solicitudes FROM ia_cuotas WHERE dia=? AND clave=? FOR UPDATE'); $st->execute([$day,$key]);
            if ((int)$st->fetchColumn()>=$limit) throw new DomainException('Se alcanzó el límite diario de IA. Puedes usar la modalidad guiada.');
            $db->prepare('UPDATE ia_cuotas SET solicitudes=solicitudes+1 WHERE dia=? AND clave=?')->execute([$day,$key]);
        }
    }
}
