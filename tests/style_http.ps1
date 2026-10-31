param([string]$BaseUrl='http://localhost/inmova-ai/public')
$ErrorActionPreference='Stop'
$php='C:\xampp\php\php.exe'
$root=Split-Path $PSScriptRoot -Parent
$run='demo_'+[DateTime]::UtcNow.ToString('yyyyMMdd_HHmmss')+'_'+[guid]::NewGuid().ToString('N').Substring(0,6)
$password='QaStyle1!'+[guid]::NewGuid().ToString('N')
$created=$false
function Fixture([string]$Action,[hashtable]$Extra=@{}) {
    $payload=@{run=$run}; foreach($key in $Extra.Keys) { $payload[$key]=$Extra[$key] }
    $result=($payload | ConvertTo-Json -Compress) | & $php (Join-Path $PSScriptRoot 'integration_fixture.php') $Action
    if($LASTEXITCODE -ne 0) { throw 'Falló el fixture HTTP.' }
    return ($result | ConvertFrom-Json)
}
function Page([string]$Path,$Body=$null,[int]$Expected=200) {
    $params=@{Uri=$BaseUrl+$Path;WebSession=$session;SkipHttpErrorCheck=$true;TimeoutSec=30}
    if($null -ne $Body) { $params.Method='Post'; $params.Body=$Body }
    $response=Invoke-WebRequest @params
    if([int]$response.StatusCode -ne $Expected) { throw "HTTP incorrecto: $Path ($($response.StatusCode))" }
    if($response.Content -match '<b>Warning</b>|Fatal error|<b>Notice</b>') { throw "Diagnóstico PHP: $Path" }
    return $response
}
try {
    $fixture=Fixture 'bootstrap' @{password=$password}; $created=$true
    $session=[Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $forged='11111111111111111111111111111111'
    $session.Cookies.Add([uri]$BaseUrl,[Net.Cookie]::new('PHPSESSID',$forged,'/'))
    $login=Page '/login'
    $cookie=$session.Cookies.GetCookies([uri]$BaseUrl) | Where-Object Name -eq 'PHPSESSID' | Select-Object -First 1
    if(!$cookie -or $cookie.Value -eq $forged) { throw 'Se aceptó un identificador de sesión no inicializado.' }
    Write-Output 'OK: sesión rechaza identificadores impuestos por el visitante'
    if(!$cookie.HttpOnly -or $login.Headers['X-Content-Type-Options'] -ne 'nosniff' -or $login.Headers['X-Frame-Options'] -ne 'SAMEORIGIN') { throw 'Faltan protecciones de cookie o cabeceras.' }
    Write-Output 'OK: cookie HttpOnly y cabeceras de seguridad presentes'
    $csrf=[regex]::Match($login.Content,'name="csrf" value="([^"]+)"').Groups[1].Value
    if(!$csrf -or $login.Content -notmatch 'login-shell') { throw 'No se recibió el acceso rediseñado.' }
    $dashboard=Page '/login' @{csrf=$csrf;email=$fixture.admin_email;password=$password}
    if($dashboard.Content -notmatch 'dashboard-hero' -or $dashboard.Content -notmatch 'app-sidebar') { throw 'El acceso no mostró el dashboard rediseñado.' }
    Write-Output 'OK: acceso real y dashboard rediseñados'
    if($dashboard.Headers['Cache-Control'] -notmatch 'no-store') { throw 'El panel privado permite almacenamiento en caché.' }
    Write-Output 'OK: panel privado prohíbe almacenamiento en caché'
    foreach($path in @('/properties','/crm','/opportunities','/visits','/reservations','/closings','/reports','/tasks','/academy','/simulations','/users','/portal','/assistant')) {
        $page=Page $path
        if($page.Content -notmatch 'app-sidebar' -or $page.Content -notmatch '/assets/css/app.css\?v=' -or $page.Content -notmatch '/assets/css/theme-vibrant.css\?v=') { throw "Falta el layout o tema compartido: $path" }
        Write-Output "OK: layout y recursos en $path"
    }
    $sale=[string]$fixture.modes.'venta-deptos'
    $filtered=Page "/properties?modalidad_id=$sale&moneda=PEN&min=0&max=5000000&disponibilidad=disponible&publicado=0"
    foreach($field in @('modalidad_id','moneda','disponibilidad','publicado')) {
        $expectedValue=@{modalidad_id=$sale;moneda='PEN';disponibilidad='disponible';publicado='0'}[$field]
        $select=[regex]::Match($filtered.Content,'(?s)<select[^>]*name="'+$field+'"[^>]*>(.*?)</select>').Groups[1].Value
        if($select -notmatch ('value="'+[regex]::Escape($expectedValue)+'" selected')) { throw "No conserva filtro: $field" }
    }
    Write-Output 'OK: formulario conserva modalidad, moneda, disponibilidad y publicación Borrador'
    $invalid=Page '/properties?moneda=PEN&min=200&max=100' $null 422
    if($invalid.Content -notmatch 'El precio mínimo no puede superar al máximo') { throw 'No se mostró el error de rango.' }
    $null=Page '/properties?min=100' $null 422
    $null=Page '/properties?publicado[]=1' $null 422
    Write-Output 'OK: filtros inválidos devuelven 422 y errores de validación'
    $clean=Page '/properties'
    if($clean.Content -match 'value="PEN" selected|value="0" selected') { throw 'Los filtros persisten al limpiar.' }
    Write-Output 'OK: limpiar restablece los filtros'
    $null=Page '/logout' @{csrf='invalid-token'} 403
    $stillLoggedIn=Page '/dashboard'
    if($stillLoggedIn.Content -notmatch 'app-sidebar') { throw 'Un cierre inválido cambió la sesión.' }
    Write-Output 'OK: cierre de sesión sin CSRF devuelve 403 y conserva la sesión'
    $null=Page '/logout' @{csrf=$csrf}
    $afterLogout=Page '/dashboard'
    if($afterLogout.Content -notmatch 'login-shell' -or $afterLogout.Content -match 'id="app-sidebar"') { throw 'El cierre de sesión no revocó el acceso.' }
    Write-Output 'OK: cierre válido revoca el acceso y vuelve al login'
} finally {
    if($created) {
        $null=Fixture 'secure_accounts'
        $snapshot=Fixture 'snapshot'
        if(@($snapshot.users | Where-Object estado -ne 0).Count) { throw 'La cuenta de prueba sigue activa.' }
    }
    $password=$null
}
