<?php
class CertificateController extends Controller
{
    private function code(): string { return is_string($_GET['code'] ?? null) ? strtoupper(trim($_GET['code'])) : ''; }
    public function index(): void
    {
        Auth::requireLogin(); header('Cache-Control: no-store'); $this->view('certificates/index',['title'=>'Mis certificados','certificates'=>Assessment::myCertificates(Auth::user()['id'])]);
    }
    public function show(): void
    {
        Auth::requireLogin(); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
        $certificate=Assessment::certificateByCode($this->code());
        if (!$certificate) { http_response_code(404); exit('Certificado no encontrado.'); }
        $course=Academy::course((int)$certificate['curso_id']);
        if ((int)$certificate['usuario_id']!==Auth::user()['id'] && !Academy::canManage($course,Auth::user())) { http_response_code(403); exit('No tienes permiso para ver este certificado.'); }
        $this->view('certificates/document',['certificate'=>$certificate,'title'=>'Certificado de aprobación'],'certificate');
    }
    public function verify(): void
    {
        header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow');
        $code=$this->code(); $certificate=null;
        if ($code!=='' && strlen($code)<=40) $certificate=Assessment::certificateByCode($code);
        if ($code!=='' && !$certificate) http_response_code(404);
        $this->view('certificates/verify',['title'=>'Verificar certificado','code'=>$code,'certificate'=>$certificate]);
    }
}
