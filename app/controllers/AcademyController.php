<?php
class AcademyController extends Controller
{
    private function access(bool $manage=false): array
    {
        if ($manage) Auth::requireRole('superadmin','admin','instructor'); else Auth::requireLogin();
        header('Cache-Control: no-store'); return Auth::user();
    }
    private function id(string $key): ?int { return filter_var($_GET[$key] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null; }
    private function course(string $key, array $user, bool $manage=false): array
    {
        $row=($id=$this->id($key)) ? Academy::course($id) : null;
        if (!$row || (!$manage && (!$row['estado'] || !$row['publicado']) && !Academy::canManage($row,$user))) { http_response_code(404); exit('Curso no disponible.'); }
        if ($manage && !Academy::canManage($row,$user)) { http_response_code(403); exit('No tienes permiso para gestionar este curso.'); }
        return $row;
    }
    private function csrf(): void
    {
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) { http_response_code(403); exit('Sesión expirada. Recarga la página.'); }
    }
    private function message(): array
    {
        $data=['success'=>$_SESSION['academy_success'] ?? null,'error'=>$_SESSION['academy_error'] ?? null]; unset($_SESSION['academy_success'],$_SESSION['academy_error']); return $data;
    }
    public function index(): void
    {
        $user=$this->access(); $mine=($_GET['mine'] ?? '')==='1';
        $this->view('academy/index',['title'=>'Academia','courses'=>Academy::courses($user,false,$mine),'manage'=>false,'mine'=>$mine]+$this->message());
    }
    public function manage(): void
    {
        $user=$this->access(true); $this->view('academy/index',['title'=>'Administrar cursos','courses'=>Academy::courses($user,true),'manage'=>true,'mine'=>false]+$this->message());
    }
    public function show(): void
    {
        $user=$this->access(); $course=$this->course('id',$user); $enrollment=Academy::enrollment((int)$course['id'],$user['id']);
        $lessons=Academy::lessons((int)$course['id'],$enrollment ? (int)$enrollment['id'] : null);
        $this->view('academy/course',['title'=>$course['titulo'],'course'=>$course,'enrollment'=>$enrollment,'lessons'=>$lessons,'progress'=>Academy::progress($lessons),'exam'=>Assessment::exam((int)$course['id']),'certificate'=>$enrollment ? Assessment::certificate((int)$enrollment['id']) : null]+$this->message());
    }
    private function courseForm(array $course, array $errors=[]): void
    {
        $this->view('academy/course-form',['title'=>isset($course['id']) ? 'Editar curso' : 'Nuevo curso','course'=>$course,'errors'=>$errors,'instructors'=>Academy::instructors(),'lessons'=>isset($course['id']) ? Academy::lessons((int)$course['id'],null,true) : [],'students'=>isset($course['id']) ? Academy::students((int)$course['id']) : []]+$this->message());
    }
    public function editCourse(): void
    {
        $user=$this->access(true); $this->courseForm(isset($_GET['id']) ? $this->course('id',$user,true) : ['instructor_id'=>$user['id'],'estado'=>1,'publicado'=>0]);
    }
    public function saveCourse(): void
    {
        $user=$this->access(true); $this->csrf(); $old=isset($_GET['id']) ? $this->course('id',$user,true) : null;
        $data=[]; foreach (['titulo','descripcion'] as $key) $data[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $data['instructor_id']=$user['rol']==='instructor' ? $user['id'] : (filter_var($_POST['instructor_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 0);
        $data['publicado']=isset($_POST['publicado']) ? 1 : 0; $data['estado']=isset($_POST['estado']) ? 1 : 0;
        $errors=[];
        if ($data['titulo']==='' || mb_strlen($data['titulo'])>160) $errors[]='El título es obligatorio y admite hasta 160 caracteres.';
        if (strlen($data['descripcion'])>60000) $errors[]='La descripción es demasiado extensa.';
        $view=$data+($old ? ['id'=>$old['id']] : []);
        if ($errors) { http_response_code(422); $this->courseForm($view,$errors); return; }
        try { $id=Academy::saveCourse($data,$old ? (int)$old['id'] : null,$user); }
        catch (Throwable $error) { ErrorHandler::report($error, 'AcademyController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->courseForm($view,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar el curso.']); return; }
        $_SESSION['academy_success']='Curso guardado.';
        // Si un administrador reasigna el curso, sigue teniendo acceso a gestionarlo.
        $this->redirect('/academy/course/edit?id='.$id);
    }
    public function enroll(): void
    {
        $user=$this->access(); $this->csrf(); $course=$this->course('id',$user);
        try { Academy::enroll((int)$course['id'],$user['id']); $_SESSION['academy_success']='Inscripción registrada. Ya puedes estudiar las lecciones.'; }
        catch (Throwable $error) { ErrorHandler::report($error, 'AcademyController'); $_SESSION['academy_error']=$error instanceof DomainException ? $error->getMessage() : 'No se pudo registrar la inscripción.'; }
        $this->redirect('/academy/course?id='.(int)$course['id']);
    }
    private function lessonForm(array $course, array $lesson, array $errors=[]): void
    {
        $this->view('academy/lesson-form',['title'=>isset($lesson['id']) ? 'Editar lección' : 'Nueva lección','course'=>$course,'lesson'=>$lesson,'errors'=>$errors]);
    }
    public function editLesson(): void
    {
        $user=$this->access(true); $course=$this->course('course_id',$user,true); $lesson=['estado'=>1,'orden'=>0];
        if (isset($_GET['id'])) {
            $lesson=($id=$this->id('id')) ? Academy::lesson($id) : null;
            if (!$lesson || (int)$lesson['curso_id']!==(int)$course['id']) { http_response_code(404); exit('Lección no encontrada.'); }
        }
        $this->lessonForm($course,$lesson);
    }
    public function saveLesson(): void
    {
        $user=$this->access(true); $this->csrf(); $course=$this->course('course_id',$user,true); $id=null;
        if (isset($_GET['id'])) {
            $id=$this->id('id'); $old=$id ? Academy::lesson($id) : null;
            if (!$old || (int)$old['curso_id']!==(int)$course['id']) { http_response_code(404); exit('Lección no encontrada.'); }
        }
        $data=[]; foreach (['titulo','contenido','video_url','orden'] as $key) $data[$key]=is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $data['estado']=isset($_POST['estado']) ? 1 : 0; $errors=[];
        if ($data['titulo']==='' || mb_strlen($data['titulo'])>160) $errors[]='El título es obligatorio y admite hasta 160 caracteres.';
        if (strlen($data['contenido'])>60000) $errors[]='El contenido es demasiado extenso.';
        if ($data['contenido']==='' && $data['video_url']==='') $errors[]='Agrega contenido de lectura o un enlace de video.';
        if ($data['video_url']!=='' && (strlen($data['video_url'])>255 || !filter_var($data['video_url'],FILTER_VALIDATE_URL) || parse_url($data['video_url'],PHP_URL_SCHEME)!=='https')) $errors[]='El enlace de video debe ser una URL HTTPS válida de hasta 255 caracteres.';
        if (!preg_match('/^[0-9]{1,5}$/D',$data['orden']) || (int)$data['orden']>65535) $errors[]='El orden debe ser un entero entre 0 y 65535.';
        $view=$data+($id ? ['id'=>$id] : []);
        if ($errors) { http_response_code(422); $this->lessonForm($course,$view,$errors); return; }
        try { Academy::saveLesson((int)$course['id'],$id,$data,$user); }
        catch (Throwable $error) { ErrorHandler::report($error, 'AcademyController'); http_response_code($error instanceof DomainException ? 422 : 503); $this->lessonForm($course,$view,[$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar la lección.']); return; }
        $_SESSION['academy_success']='Lección guardada. El progreso se actualizó según las lecciones activas. Si no quedan lecciones activas, el curso vuelve a borrador.';
        $this->redirect('/academy/course/edit?id='.(int)$course['id']);
    }
    public function lesson(): void
    {
        $user=$this->access(); $row=($id=$this->id('id')) ? Academy::lesson($id) : null;
        if (!$row || !$row['estado']) { http_response_code(404); exit('Lección no disponible.'); }
        $_GET['course_id']=$row['curso_id']; $course=$this->course('course_id',$user);
        $enrollment=Academy::enrollment((int)$course['id'],$user['id']);
        if (!$enrollment && !Academy::canManage($course,$user)) { http_response_code(403); exit('Inscríbete en el curso para acceder a esta lección.'); }
        $lessons=Academy::lessons((int)$course['id'],$enrollment ? (int)$enrollment['id'] : null); $done=false;
        foreach ($lessons as $item) if ((int)$item['id']===(int)$row['id']) $done=(bool)$item['completada'];
        $this->view('academy/lesson',['title'=>$row['titulo'],'lesson'=>$row,'course'=>$course,'enrollment'=>$enrollment,'done'=>$done,'lessons'=>$lessons]+$this->message());
    }
    public function complete(): void
    {
        $user=$this->access(); $this->csrf(); $id=$this->id('id'); $row=$id ? Academy::lesson($id) : null;
        if (!$row) { http_response_code(404); exit('Lección no encontrada.'); }
        if (!in_array($_POST['done'] ?? null,['0','1'],true)) { http_response_code(422); exit('Selecciona un estado válido.'); }
        try { Academy::complete($id,$user['id'],$_POST['done']==='1'); $_SESSION['academy_success']='Progreso actualizado.'; }
        catch (Throwable $error) { ErrorHandler::report($error, 'AcademyController'); $_SESSION['academy_error']=$error instanceof DomainException ? $error->getMessage() : 'No se pudo guardar tu progreso.'; }
        $this->redirect('/academy/lesson?id='.$id);
    }
}
