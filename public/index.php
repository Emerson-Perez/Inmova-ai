<?php
declare(strict_types=1);

// ---- Punto de entrada único: todo request pasa por aquí ----
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'));

require_once __DIR__.'/../app/core/ErrorHandler.php';
require_once __DIR__.'/../app/core/SessionSecurity.php';
ErrorHandler::install();
SessionSecurity::configure($_SERVER);
session_start();

// Autoload simple: busca la clase en core, controllers y models
spl_autoload_register(function (string $class): void {
    foreach (['core', 'controllers', 'models'] as $dir) {
        $file = __DIR__ . "/../app/{$dir}/{$class}.php";
        if (is_file($file)) { require $file; return; }
    }
});

// Helper para escapar salida HTML (previene XSS)
Auth::refresh();
function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

// ---- Rutas ----
$router = new Router();
$router->get('/',          'PortalController@index');
$router->get('/portal', 'PortalController@index');
$router->get('/portal/property', 'PortalController@show');
$router->post('/portal/inquiry', 'InquiryController@store');
$router->get('/crm', 'CrmController@index');
$router->get('/crm/lead', 'CrmController@show');
$router->post('/crm/update', 'CrmController@update');
$router->post('/crm/property', 'CrmController@assignProperty');
$router->get('/visits', 'VisitController@index');
$router->get('/visits/create', 'VisitController@create');
$router->get('/visits/edit', 'VisitController@edit');
$router->post('/visits/save', 'VisitController@save');
$router->get('/reservations', 'ReservationController@index');
$router->get('/reservations/create', 'ReservationController@create');
$router->post('/reservations/store', 'ReservationController@store');
$router->get('/reservations/show', 'ReservationController@show');
$router->post('/reservations/transition', 'ReservationController@transition');
$router->get('/closings', 'ClosingController@index');
$router->get('/closings/create', 'ClosingController@create');
$router->post('/closings/store', 'ClosingController@store');
$router->get('/closings/show', 'ClosingController@show');
$router->get('/reports', 'ReportController@index');
$router->get('/reports/export', 'ReportController@export');
$router->get('/users', 'UserController@index');
$router->get('/users/create', 'UserController@create');
$router->get('/users/edit', 'UserController@edit');
$router->post('/users/save', 'UserController@save');
$router->get('/account/password', 'AccountController@show');
$router->post('/account/password', 'AccountController@update');
$router->get('/academy', 'AcademyController@index');
$router->get('/academy/manage', 'AcademyController@manage');
$router->get('/academy/course', 'AcademyController@show');
$router->get('/academy/course/edit', 'AcademyController@editCourse');
$router->post('/academy/course/save', 'AcademyController@saveCourse');
$router->post('/academy/enroll', 'AcademyController@enroll');
$router->get('/academy/lesson', 'AcademyController@lesson');
$router->get('/academy/lesson/edit', 'AcademyController@editLesson');
$router->post('/academy/lesson/save', 'AcademyController@saveLesson');
$router->post('/academy/complete', 'AcademyController@complete');
$router->get('/academy/exam/manage', 'ExamController@manage');
$router->post('/academy/exam/save', 'ExamController@save');
$router->post('/academy/question/save', 'ExamController@question');
$router->post('/academy/question/delete', 'ExamController@deleteQuestion');
$router->get('/academy/exam', 'ExamController@take');
$router->post('/academy/exam/submit', 'ExamController@submit');
$router->get('/academy/exam/result', 'ExamController@result');
$router->get('/academy/certificates', 'CertificateController@index');
$router->get('/academy/certificate', 'CertificateController@show');
$router->get('/certificates/verify', 'CertificateController@verify');
$router->get('/assistant', 'AssistantController@index');
$router->post('/assistant/guided', 'AssistantController@guided');
$router->post('/assistant/message', 'AssistantController@message');
$router->post('/assistant/handoff', 'AssistantController@handoff');
$router->post('/assistant/reset', 'AssistantController@reset');
$router->get('/assistant/settings', 'AssistantController@settings');
$router->post('/assistant/settings', 'AssistantController@saveSettings');
$router->get('/tasks', 'TaskController@index');
$router->post('/tasks/resolve', 'TaskController@resolve');
$router->get('/opportunities', 'OpportunityController@index');
$router->get('/opportunities/create', 'OpportunityController@create');
$router->get('/opportunities/edit', 'OpportunityController@edit');
$router->post('/opportunities/save', 'OpportunityController@save');
$router->get('/simulations', 'SimulationController@index');
$router->post('/simulations/start', 'SimulationController@start');
$router->get('/simulations/show', 'SimulationController@show');
$router->post('/simulations/message', 'SimulationController@message');
$router->post('/simulations/finish', 'SimulationController@finish');
$router->post('/simulations/abandon', 'SimulationController@abandon');
$router->get('/login',     'AuthController@showLogin');
$router->post('/login',    'AuthController@login');
$router->post('/logout',   'AuthController@logout');
$router->get('/dashboard', 'DashboardController@index');

$router->get('/properties', 'PropertyController@index');
$router->get('/properties/create', 'PropertyController@create');
$router->get('/properties/edit', 'PropertyController@edit');
$router->post('/properties/save', 'PropertyController@save');
$router->post('/properties/delete', 'PropertyController@delete');
$router->get('/properties/documents', 'DocumentController@index');
$router->post('/properties/documents/upload', 'DocumentController@upload');
$router->post('/properties/documents/status', 'DocumentController@status');
$router->get('/properties/documents/download', 'DocumentController@download');

$router->dispatch();
