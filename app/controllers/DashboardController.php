<?php
class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $user=Auth::user(); $summary=null; $period=null; $reportError=null; $pendingSummary=null; $pendingError=null;
        if (in_array($user['rol'],['superadmin','admin','vendedor'],true)) {
            try { $period=Report::period(); $summary=Report::summary($user,$period); }
            catch (Throwable $error) { ErrorHandler::report($error, 'DashboardController'); $reportError='No se pudieron cargar los indicadores.'; }
            try { $pendingSummary=Task::summary($user); }
            catch (Throwable $error) { ErrorHandler::report($error, 'DashboardController'); $pendingError='No se pudieron cargar los pendientes.'; }
        }
        $this->view('dashboard/index', ['title' => 'Panel principal','summary'=>$summary,'period'=>$period,'reportError'=>$reportError,'pendingSummary'=>$pendingSummary,'pendingError'=>$pendingError]);
    }
}
