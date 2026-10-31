<?php
class ExamController extends Controller
{
    private function course(bool $manage=false): array
    {
        if ($manage) Auth::requireRole('superadmin','admin','instructor'); else Auth::requireLogin();
        header('Cache-Control: no-store');
        $id=filter_var($_GET['course_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $course=$id ? Academy::course($id) : null;
        if (!$course || (!$manage && (!$course['estado'] || !$course['publicado']))) { http_response_code(404); exit('Curso no disponible.'); }
        if ($manage && !Academy::canManage($course,Auth::user())) { http_response_code(403); exit('No puedes gestionar el examen de este curso.'); }
        return $course;
    }
    private function csrf(): void
    {
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
    }
    private function manager(array $course, array $errors=[], array $oldExam=[], array $oldQuestion=[]): void
    {
        $exam=Assessment::exam((int)$course['id']);
        $this->view('exams/manage',['title'=>'Examen final','course'=>$course,'exam'=>$exam,'questions'=>$exam ? Assessment::questions((int)$exam['id'],true) : [],'locked'=>Assessment::hasAttempts((int)$course['id']),'errors'=>$errors,'oldExam'=>$oldExam,'oldQuestion'=>$oldQuestion,'success'=>$_SESSION['exam_success'] ?? null]); unset($_SESSION['exam_success']);
    }
    public function manage(): void { $this->manager($this->course(true)); }
    public function save(): void
    {
        $course=$this->course(true); $this->csrf();
        $data=['titulo'=>is_string($_POST['titulo'] ?? null) ? trim($_POST['titulo']) : '', 'nota_minima'=>is_string($_POST['nota_minima'] ?? null) ? trim($_POST['nota_minima']) : '', 'estado'=>isset($_POST['estado']) ? 1 : 0];
        if (!preg_match('/^[0-9]{1,2}(\.[0-9]{1,2})?$/D',$data['nota_minima'])) { http_response_code(422); $this->manager($course,['La nota mínima debe ser un número con hasta dos decimales.'],$data); return; }
        try { Assessment::saveExam((int)$course['id'],$data,Auth::user()); }
        catch (Throwable $error) { ErrorHandler::report($error, 'ExamController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->manager($course,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar el examen.'],$data); return; }
        $_SESSION['exam_success']='Examen guardado.'; $this->redirect('/academy/exam/manage?course_id='.(int)$course['id']);
    }
    public function question(): void
    {
        $course=$this->course(true); $this->csrf(); $data=[];
        foreach (['enunciado','opcion_a','opcion_b','opcion_c','opcion_d','correcta'] as $key) $data[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $id=isset($_GET['id']) ? filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) : null;
        if ($id===false) { http_response_code(404); exit('Pregunta no encontrada.'); }
        try { Assessment::saveQuestion((int)$course['id'],$id,$data,Auth::user()); }
        catch (Throwable $error) { ErrorHandler::report($error, 'ExamController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->manager($course,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar la pregunta.'],[],$data+['id'=>$id]); return; }
        $_SESSION['exam_success']='Pregunta guardada.'; $this->redirect('/academy/exam/manage?course_id='.(int)$course['id']);
    }
    public function deleteQuestion(): void
    {
        $course=$this->course(true); $this->csrf(); $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Pregunta no encontrada.'); }
        try { Assessment::deleteQuestion((int)$course['id'],$id,Auth::user()); }
        catch (Throwable $error) { ErrorHandler::report($error, 'ExamController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->manager($course,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo retirar la pregunta.']); return; }
        $_SESSION['exam_success']='Pregunta retirada. Si era la última, el examen quedó en borrador.'; $this->redirect('/academy/exam/manage?course_id='.(int)$course['id']);
    }
    private function student(array $course, array $errors=[], array $answers=[]): void
    {
        $exam=Assessment::exam((int)$course['id']); $enrollment=Academy::enrollment((int)$course['id'],Auth::user()['id']);
        if (!$exam || !$exam['estado']) { http_response_code(404); exit('Examen no disponible.'); }
        if (!$enrollment) { http_response_code(403); exit('Inscríbete en el curso antes de rendir su examen.'); }
        $progress=Academy::progress(Academy::lessons((int)$course['id'],(int)$enrollment['id']));
        $attempts=Assessment::attempts((int)$exam['id'],(int)$enrollment['id']);
        $canSubmit=$progress['percent']===100 && $progress['total']>0 && count($attempts)<Assessment::MAX_ATTEMPTS && !array_filter($attempts,fn($a)=>(bool)$a['aprobada']);
        $this->view('exams/take',['title'=>$exam['titulo'],'course'=>$course,'exam'=>$exam,'progress'=>$progress,'attempts'=>$attempts,'canSubmit'=>$canSubmit,'questions'=>$canSubmit ? Assessment::questions((int)$exam['id']) : [],'errors'=>$errors,'answers'=>$answers,'submissionKey'=>bin2hex(random_bytes(16)),'certificate'=>Assessment::certificate((int)$enrollment['id'])]);
    }
    public function take(): void { $this->student($this->course()); }
    public function submit(): void
    {
        $course=$this->course(); $this->csrf();
        $answers=is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
        $version=filter_var($_POST['version'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 0;
        $key=is_string($_POST['submission_key'] ?? null) ? $_POST['submission_key'] : '';
        try { $id=Assessment::submit((int)$course['id'],Auth::user()['id'],$answers,$version,$key); }
        catch (Throwable $error) { ErrorHandler::report($error, 'ExamController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->student($course,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo registrar el intento.'],$answers); return; }
        $this->redirect('/academy/exam/result?id='.$id);
    }
    public function result(): void
    {
        Auth::requireLogin(); header('Cache-Control: no-store');
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $attempt=$id ? Assessment::attempt($id) : null;
        if (!$attempt) { http_response_code(404); exit('Intento no encontrado.'); }
        $course=Academy::course((int)$attempt['curso_id']);
        if ((int)$attempt['usuario_id']!==Auth::user()['id'] && !Academy::canManage($course,Auth::user())) { http_response_code(403); exit('No tienes permiso para ver este resultado.'); }
        $this->view('exams/result',['title'=>'Resultado del examen','attempt'=>$attempt,'course'=>$course,'detail'=>json_decode($attempt['detalle'],true,512,JSON_THROW_ON_ERROR),'certificate'=>Assessment::certificate((int)$attempt['inscripcion_id'])]);
    }
}
