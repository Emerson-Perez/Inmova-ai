<?php
class SimulationController extends Controller
{
    private function access(): array
    {
        Auth::requireRole('superadmin','admin','instructor','vendedor','practicante'); header('Cache-Control: no-store'); return Auth::user();
    }
    private function csrf(): void
    {
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
    }
    private function row(array $user, bool $owner=false): array
    {
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $row=$id ? Simulation::find($id,$user) : null;
        if (!$row) { http_response_code(404); exit('Práctica no encontrada o sin acceso.'); }
        if ($owner && (int)$row['usuario_id']!==$user['id']) { http_response_code(403); exit('Solo el participante puede responder y finalizar su práctica.'); }
        return $row;
    }
    private function requestKey(int $id): ?string
    {
        $key=is_string($_POST['request_key'] ?? null) ? $_POST['request_key'] : '';
        if (!preg_match('/^[a-f0-9]{32}$/D',$key)) throw new DomainException('Recarga la página antes de continuar.');
        if (in_array($key,$_SESSION['simulation_requests'] ?? [],true)) return null;
        $_SESSION['simulation_requests'][]=$key; $_SESSION['simulation_requests']=array_slice($_SESSION['simulation_requests'],-60);
        return $key;
    }
    public function index(): void
    {
        $user=$this->access(); $team=($_GET['scope'] ?? '')==='equipo';
        if ($team && !Simulation::supervisor($user)) { http_response_code(403); exit('Solo instructores y administradores revisan las prácticas del equipo.'); }
        $this->view('simulations/index',['title'=>'Simulador de ventas','rows'=>Simulation::listing($user,$team),'team'=>$team,'scenarios'=>Simulation::scenarios(),'ready'=>AiConfig::ready(),'requestKey'=>bin2hex(random_bytes(16)),'error'=>$_SESSION['simulation_error'] ?? null]); unset($_SESSION['simulation_error']);
    }
    public function start(): void
    {
        $user=$this->access(); $this->csrf();
        try {
            $key=$this->requestKey(0);
            if ($key===null) { $id=$_SESSION['last_started_simulation'] ?? null; $this->redirect($id ? '/simulations/show?id='.(int)$id : '/simulations'); }
            $scenario=is_string($_POST['scenario'] ?? null) ? $_POST['scenario'] : ''; $mode=is_string($_POST['mode'] ?? null) ? $_POST['mode'] : '';
            if ($mode==='ia' && ($_POST['consent'] ?? '')!=='1') throw new DomainException('Autoriza el envío de esta práctica a OpenAI para elegir la modalidad IA.');
            $id=Simulation::start($scenario,$mode,$user,AiConfig::ready()); $_SESSION['last_started_simulation']=$id;
        } catch (Throwable $error) { ErrorHandler::report($error, 'SimulationController'); $_SESSION['simulation_error']=$error instanceof DomainException ? $error->getMessage() : 'No se pudo iniciar la práctica.'; $this->redirect('/simulations'); }
        $this->redirect('/simulations/show?id='.$id);
    }
    public function show(): void
    {
        $user=$this->access(); $row=$this->row($user);
        $this->view('simulations/show',['title'=>$row['escenario'],'simulation'=>$row,'script'=>json_decode($row['guion'] ?? '',true) ?: [],'messages'=>Simulation::transcript($row),'feedback'=>json_decode($row['retroalimentacion'] ?? '',true) ?: [],'owner'=>(int)$row['usuario_id']===$user['id'],'ready'=>AiConfig::ready(),'requestKey'=>bin2hex(random_bytes(16)),'error'=>$_SESSION['simulation_error'] ?? null,'success'=>$_SESSION['simulation_success'] ?? null]); unset($_SESSION['simulation_error'],$_SESSION['simulation_success']);
    }
    public function message(): void
    {
        $user=$this->access(); $this->csrf(); $row=$this->row($user,true); $key=null;
        try {
            $key=$this->requestKey((int)$row['id']); if ($key===null) $this->redirect('/simulations/show?id='.(int)$row['id']);
            $text=is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
            if ($text==='' || mb_strlen($text)>1000) throw new DomainException('Escribe una respuesta de hasta 1000 caracteres.');
            if ($row['modo']==='guiada') Simulation::guidedTurn((int)$row['id'],$text,$key,$user);
            else {
                $config=AiConfig::load(); if (!AiConfig::ready($config)) throw new DomainException('La IA no está disponible. Puedes iniciar una práctica guiada.');
                $hash=hash_hmac('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown',$config['api_key']);
                $claimed=Simulation::claim((int)$row['id'],'message',$key,$user,$config,$hash);
                if ($claimed) {
                    $reply=(new SimulationCoach(new AiClient($config)))->reply($claimed,$text);
                    Simulation::finishTurn((int)$row['id'],$key,$text,$reply,$config['model'],$user);
                }
            }
        } catch (Throwable $error) {
            if ($key) Simulation::release((int)$row['id'],$key,$user);
            ErrorHandler::report($error, 'SimulationController'); $_SESSION['simulation_error']=$error instanceof DomainException || ($error instanceof RuntimeException && !($error instanceof PDOException)) ? $error->getMessage() : 'No se pudo guardar la respuesta. Inténtalo de nuevo.';
        }
        $this->redirect('/simulations/show?id='.(int)$row['id']);
    }
    public function finish(): void
    {
        $user=$this->access(); $this->csrf(); $row=$this->row($user,true); $key=null;
        try {
            $key=$this->requestKey((int)$row['id']); if ($key===null) $this->redirect('/simulations/show?id='.(int)$row['id']);
            if ($row['modo']==='guiada') Simulation::finishGuided((int)$row['id'],$user);
            else {
                $config=AiConfig::load(); if (!AiConfig::ready($config)) throw new DomainException('La IA no está disponible. Inténtalo más tarde.');
                $hash=hash_hmac('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown',$config['api_key']);
                $claimed=Simulation::claim((int)$row['id'],'evaluate',$key,$user,$config,$hash);
                if ($claimed) {
                    $feedback=(new SimulationCoach(new AiClient($config)))->evaluate($claimed);
                    Simulation::finishEvaluation((int)$row['id'],$key,$feedback,$config['model'],$user);
                }
            }
            $_SESSION['simulation_success']='Práctica finalizada. El historial quedó guardado.';
        } catch (Throwable $error) {
            if ($key) Simulation::release((int)$row['id'],$key,$user);
            ErrorHandler::report($error, 'SimulationController'); $_SESSION['simulation_error']=$error instanceof DomainException || ($error instanceof RuntimeException && !($error instanceof PDOException)) ? $error->getMessage() : 'No se pudo finalizar la práctica.';
        }
        $this->redirect('/simulations/show?id='.(int)$row['id']);
    }
    public function abandon(): void
    {
        $user=$this->access(); $this->csrf(); $row=$this->row($user,true); Simulation::abandon((int)$row['id'],$user); $this->redirect('/simulations/show?id='.(int)$row['id']);
    }
}
