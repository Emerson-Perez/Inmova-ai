<?php
class ReportController extends Controller
{
    public function index(): void { $this->render(false); }
    public function export(): void { $this->render(true); }
    private function render(bool $export): void
    {
        Auth::requireRole('superadmin','admin','vendedor');
        $errors=[]; $report=null;
        $from=is_string($_GET['from'] ?? null) ? $_GET['from'] : '';
        $to=is_string($_GET['to'] ?? null) ? $_GET['to'] : '';
        try {
            $period=Report::period($_GET['from'] ?? null,$_GET['to'] ?? null);
            $from=$period['from']; $to=$period['to'];
            $report=Report::summary(Auth::user(),$period);
        } catch (DomainException $error) { http_response_code(422); $errors[]=$error->getMessage(); }
        catch (Throwable $error) { ErrorHandler::report($error, 'ReportController'); http_response_code(503); $errors[]='No se pudo generar el reporte. Inténtalo de nuevo.'; }
        if ($export && $report) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="inmova-reporte-'.$from.'-'.$to.'.csv"');
            header('Cache-Control: no-store');
            $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
            foreach (Report::csvRows($report,$period) as $row) fputcsv($out,$row,';', '"', '');
            fclose($out); return;
        }
        header('Cache-Control: no-store');
        $this->view('reports/index',['title'=>'Indicadores y reportes','report'=>$report,'errors'=>$errors,'from'=>$from,'to'=>$to]);
    }
}
