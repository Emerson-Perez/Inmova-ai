<?php
class Simulation
{
    public const MAX_TURNS=8;
    public const STATES=['en_curso'=>'En curso','finalizada'=>'Práctica guiada finalizada','evaluada'=>'Evaluada con IA','abandonada'=>'Abandonada'];
    public const RUBRIC=['escucha'=>'Escucha y empatía','necesidades'=>'Identificación de necesidades','claridad'=>'Claridad de la propuesta','objeciones'=>'Manejo de objeciones','siguiente_paso'=>'Acuerdo de siguiente paso'];
    public static function allowed(array $user): bool { return in_array($user['rol'],['superadmin','admin','instructor','vendedor','practicante'],true); }
    public static function supervisor(array $user): bool { return in_array($user['rol'],['superadmin','admin','instructor'],true); }
    public static function scenarios(): array
    {
        $questions=['¿Qué necesitarías saber de mí antes de recomendar una opción?','¿Cómo se ajusta tu propuesta a lo que te he contado?','Me preocupa el precio. ¿Cómo me ayudarías a comparar opciones?','¿Qué información falta confirmar antes de tomar una decisión?','No quiero decidir con prisa. ¿Cómo continuaríamos?','¿Qué me recomiendas revisar durante una visita?','¿Puedes resumir lo que hemos acordado sin prometer condiciones no confirmadas?','¿Cuál sería el siguiente paso concreto y cuándo lo haríamos?'];
        return [
            'primer_comprador'=>['titulo'=>'Primera compra de departamento','briefing'=>'Cliente ficticio que busca un departamento en Lima. Presupuesto referencial: PEN 350 000. Practica preguntas sobre necesidades y aclara qué datos deben verificarse con el equipo.','persona'=>'Primera compra, necesita dos dormitorios y teme asumir un compromiso sin entender los pasos. No des por aprobada financiación ni condiciones legales.','inicio'=>'Hola, busco mi primer departamento y no sé por dónde empezar. ¿Me puedes orientar?','preguntas'=>$questions],
            'precio'=>['titulo'=>'Objeción por precio de un terreno','briefing'=>'Terreno ficticio ofrecido en PEN 90 000 en Chiclayo. El cliente compara otra opción de PEN 80 000. Practica comparación de valor sin inventar descuentos.','persona'=>'Cliente que insiste en una comparación de precio. Valora la ubicación y quiere saber qué documentos y características conviene verificar antes de continuar.','inicio'=>'Vi otro terreno más barato. ¿Por qué debería considerar este de 90 000 soles?','preguntas'=>$questions],
            'alquiler'=>['titulo'=>'Alquiler con requisitos específicos','briefing'=>'Departamento ficticio en Miraflores con renta referencial de USD 900 al mes. El cliente necesita mudarse pronto y tiene una mascota. Confirma requisitos sin prometer aprobaciones.','persona'=>'Cliente que busca alquiler, pregunta por mascota, fecha de mudanza y requisitos. Esos detalles no están confirmados y deben consultarse al responsable del inmueble.','inicio'=>'Me interesa alquilar, pero tengo una mascota y necesito mudarme pronto. ¿Qué opciones tengo?','preguntas'=>$questions],
            'indeciso'=>['titulo'=>'Cliente indeciso y programación de visita','briefing'=>'Casa ficticia en Arequipa por PEN 450 000. El cliente lleva semanas comparando. Busca acordar un siguiente paso realista sin ejercer presión.','persona'=>'Cliente indeciso que necesita tres dormitorios y solo puede visitar los fines de semana. Responde a preguntas claras y no acepta que se confirme una visita sin revisar disponibilidad.','inicio'=>'La casa me interesa, pero prefiero seguir mirando. Todavía no estoy convencido.','preguntas'=>$questions],
        ];
    }
    public static function listing(array $user, bool $team=false): array
    {
        if (!self::allowed($user) || ($team && !self::supervisor($user))) throw new DomainException('No tienes permiso para revisar estas prácticas.');
        $st=Database::connect()->prepare("SELECT s.id,s.usuario_id,s.escenario,s.modo,s.estado,s.turnos,s.puntaje,s.created_at,CONCAT(u.nombres,' ',u.apellidos) AS participante FROM simulaciones_ventas s JOIN usuarios u ON u.id=s.usuario_id".($team ? '' : ' WHERE s.usuario_id=?').' ORDER BY s.id DESC LIMIT 100'); $st->execute($team ? [] : [$user['id']]); return $st->fetchAll();
    }
    public static function find(int $id, array $user): ?array
    {
        if (!self::allowed($user)) throw new DomainException('No tienes acceso al simulador.');
        $st=Database::connect()->prepare("SELECT s.*,CONCAT(u.nombres,' ',u.apellidos) AS participante FROM simulaciones_ventas s JOIN usuarios u ON u.id=s.usuario_id WHERE s.id=?".(!self::supervisor($user) ? ' AND s.usuario_id=?' : '')); $st->execute(self::supervisor($user) ? [$id] : [$id,$user['id']]); return $st->fetch() ?: null;
    }
    public static function transcript(array $row): array { $data=json_decode($row['transcripcion'] ?? '',true); return is_array($data) ? $data : []; }
    private static function now(?DateTimeImmutable $now=null): DateTimeImmutable { return ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('America/Lima')); }
    private static function lock(int $id, array $user): array
    {
        $st=Database::connect()->prepare('SELECT * FROM simulaciones_ventas WHERE id=? FOR UPDATE'); $st->execute([$id]); $row=$st->fetch();
        if (!self::allowed($user) || !$row || (int)$row['usuario_id']!==$user['id']) throw new DomainException('Solo el participante puede responder o finalizar su práctica.');
        return $row;
    }
    public static function start(string $scenario, string $mode, array $user, bool $ready, ?DateTimeImmutable $now=null): int
    {
        if (!self::allowed($user) || !isset(self::scenarios()[$scenario]) || !in_array($mode,['guiada','ia'],true)) throw new DomainException('Selecciona un escenario y una modalidad válidos.');
        if ($mode==='ia' && !$ready) throw new DomainException('La IA no está disponible. Puedes practicar en modalidad guiada.');
        $db=Database::connect(); $db->beginTransaction();
        try {
            $st=$db->prepare('SELECT id FROM usuarios WHERE id=? AND estado=1 FOR UPDATE'); $st->execute([$user['id']]);
            if (!$st->fetch()) throw new DomainException('La cuenta ya no está activa.');
            $now=self::now($now);
            if ($mode==='ia') {
                $st=$db->prepare("SELECT COUNT(*) FROM simulaciones_ventas WHERE usuario_id=? AND modo='ia' AND created_at>=? AND created_at<?"); $st->execute([$user['id'],$now->format('Y-m-d').' 00:00:00',$now->modify('tomorrow')->format('Y-m-d').' 00:00:00']);
                if ((int)$st->fetchColumn()>=5) throw new DomainException('Puedes iniciar hasta cinco prácticas con IA por día. La modalidad guiada sigue disponible.');
            }
            $script=self::scenarios()[$scenario];
            $messages=[['role'=>'cliente','content'=>$script['inicio'],'source'=>'guion']];
            $db->prepare('INSERT INTO simulaciones_ventas (usuario_id,escenario,modo,guion,transcripcion,created_at) VALUES (?,?,?,?,?,?)')->execute([$user['id'],$script['titulo'],$mode,json_encode($script,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($messages,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$now->format('Y-m-d H:i:s')]);
            $id=(int)$db->lastInsertId(); $db->commit(); return $id;
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    private static function messageError(string $text): void
    {
        if (trim($text)==='' || mb_strlen($text)>1000) throw new DomainException('Escribe una respuesta de hasta 1000 caracteres.');
    }
    public static function guidedTurn(int $id, string $text, string $key, array $user): void
    {
        self::messageError($text);
        if (!preg_match('/^[a-f0-9]{32}$/D',$key)) throw new DomainException('Recarga la práctica antes de responder.');
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::lock($id,$user);
            if ($row['ultima_solicitud']===$key) { $db->commit(); return; }
            if ($row['modo']!=='guiada' || $row['estado']!=='en_curso' || (int)$row['turnos']>=self::MAX_TURNS) throw new DomainException('Esta práctica ya no admite más respuestas.');
            $script=json_decode($row['guion'],true,512,JSON_THROW_ON_ERROR); $messages=self::transcript($row);
            $messages[]=['role'=>'vendedor','content'=>trim($text),'source'=>'usuario'];
            $reply=$script['preguntas'][(int)$row['turnos']] ?? 'Resume el siguiente paso que propondrías al cliente.';
            if ((int)$row['turnos']===self::MAX_TURNS-1) $reply='Gracias. Con esto podemos cerrar la práctica y revisar lo que trabajamos.';
            $messages[]=['role'=>'cliente','content'=>$reply,'source'=>'guion'];
            $db->prepare('UPDATE simulaciones_ventas SET transcripcion=?,turnos=turnos+1,ultima_solicitud=? WHERE id=?')->execute([json_encode($messages,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$key,$id]); $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function claim(int $id, string $kind, string $key, array $user, array $config, string $ipHash, ?DateTimeImmutable $now=null): ?array
    {
        if (!in_array($kind,['message','evaluate'],true) || !preg_match('/^[a-f0-9]{32}$/D',$key)) throw new DomainException('Recarga la práctica antes de continuar.');
        $existing=self::find($id,$user);
        if (!$existing || (int)$existing['usuario_id']!==$user['id']) throw new DomainException('Solo el participante puede responder o finalizar su práctica.');
        if ($existing['ultima_solicitud']===$key) return null;
        $db=Database::connect(); $db->beginTransaction();
        try {
            $now=self::now($now); AiQuota::take($db,$ipHash,$config,$now); $row=self::lock($id,$user);
            if ($row['ultima_solicitud']===$key) { $db->rollBack(); return null; }
            if ($row['modo']!=='ia' || $row['estado']!=='en_curso') throw new DomainException('La práctica ya no está disponible para esa acción.');
            if ($kind==='message' && (int)$row['turnos']>=self::MAX_TURNS) throw new DomainException('Llegaste a ocho respuestas. Finaliza y solicita retroalimentación.');
            if ($kind==='evaluate' && (int)$row['turnos']<3) throw new DomainException('Responde al menos tres veces antes de solicitar retroalimentación.');
            if ($row['solicitud_pendiente'] && $row['solicitud_at'] && $now->getTimestamp()-(new DateTimeImmutable($row['solicitud_at'],new DateTimeZone('America/Lima')))->getTimestamp()<60) throw new DomainException('Hay una solicitud en curso. Espera a que termine o recarga la página.');
            if ($row['ultima_ia'] && $now->getTimestamp()-(new DateTimeImmutable($row['ultima_ia'],new DateTimeZone('America/Lima')))->getTimestamp()<5) throw new DomainException('Espera unos segundos antes de enviar otra solicitud.');
            $db->prepare('UPDATE simulaciones_ventas SET solicitud_pendiente=?,solicitud_tipo=?,solicitud_at=?,ultima_ia=? WHERE id=?')->execute([$key,$kind,$now->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s'),$id]);
            $db->commit(); return $row;
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
    }
    public static function finishTurn(int $id, string $key, string $text, string $reply, string $model, array $user): void
    {
        self::messageError($text);
        if (trim($reply)==='' || mb_strlen($reply)>2000) throw new DomainException('La respuesta del cliente simulado no es válida.');
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::claimed($id,$key,'message',$user); $messages=self::transcript($row);
            $messages[]=['role'=>'vendedor','content'=>trim($text),'source'=>'usuario'];
            $messages[]=['role'=>'cliente','content'=>$reply,'source'=>'openai','model'=>$model];
            $db->prepare('UPDATE simulaciones_ventas SET transcripcion=?,turnos=turnos+1,ultima_solicitud=?,solicitud_pendiente=NULL,solicitud_tipo=NULL,solicitud_at=NULL WHERE id=?')->execute([json_encode($messages,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$key,$id]); $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    private static function claimed(int $id, string $key, string $kind, array $user): array
    {
        $row=self::lock($id,$user);
        if ($row['estado']!=='en_curso' || $row['solicitud_tipo']!==$kind || !is_string($row['solicitud_pendiente']) || !hash_equals($row['solicitud_pendiente'],$key)) throw new DomainException('La solicitud cambió o la práctica ya terminó. Recarga la página.');
        return $row;
    }
    public static function finishEvaluation(int $id, string $key, array $feedback, string $model, array $user): void
    {
        $feedback=SimulationCoach::validateFeedback($feedback); $db=Database::connect(); $db->beginTransaction();
        try {
            self::claimed($id,$key,'evaluate',$user);
            $db->prepare("UPDATE simulaciones_ventas SET estado='evaluada',puntaje=?,retroalimentacion=?,modelo_evaluacion=?,evaluada_at=?,ultima_solicitud=?,solicitud_pendiente=NULL,solicitud_tipo=NULL,solicitud_at=NULL WHERE id=?")->execute([$feedback['puntaje'],json_encode($feedback,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$model,self::now()->format('Y-m-d H:i:s'),$key,$id]); $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function release(int $id, string $key, array $user): void
    {
        Database::connect()->prepare('UPDATE simulaciones_ventas SET solicitud_pendiente=NULL,solicitud_tipo=NULL,solicitud_at=NULL WHERE id=? AND usuario_id=? AND solicitud_pendiente=?')->execute([$id,$user['id'],$key]);
    }
    public static function finishGuided(int $id, array $user): void
    {
        $db=Database::connect(); $db->beginTransaction();
        try {
            $row=self::lock($id,$user);
            if ($row['estado']==='finalizada') { $db->commit(); return; }
            if ($row['modo']!=='guiada' || $row['estado']!=='en_curso' || (int)$row['turnos']<3) throw new DomainException('Completa al menos tres respuestas antes de finalizar la práctica guiada.');
            $db->prepare("UPDATE simulaciones_ventas SET estado='finalizada',retroalimentacion=?,evaluada_at=? WHERE id=?")->execute([json_encode(['checklist'=>array_values(self::RUBRIC)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),self::now()->format('Y-m-d H:i:s'),$id]); $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function abandon(int $id, array $user): void
    {
        if (!self::allowed($user)) throw new DomainException('No tienes acceso al simulador.');
        Database::connect()->prepare("UPDATE simulaciones_ventas SET estado='abandonada',solicitud_pendiente=NULL,solicitud_tipo=NULL,solicitud_at=NULL WHERE id=? AND usuario_id=? AND estado='en_curso'")->execute([$id,$user['id']]);
    }
}
