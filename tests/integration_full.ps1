param([string]$BaseUrl='http://localhost/inmova-ai/public')
$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
Set-Location $projectRoot
$php='C:\xampp\php\php.exe'
$run='demo_'+[DateTime]::UtcNow.ToString('yyyyMMdd_HHmmss')+'_'+[guid]::NewGuid().ToString('N').Substring(0,6)
$checks=[Collections.Generic.List[object]]::new()
$resultsDir=Join-Path $projectRoot "docs/integration/$run"
New-Item -ItemType Directory -Force $resultsDir | Out-Null
$checkpoint=Join-Path $resultsDir 'estado.json'
function Fixture([string]$Action,[hashtable]$Extra=@{}) {
    $payload=@{run=$run}; foreach ($key in $Extra.Keys) {$payload[$key]=$Extra[$key]}
    $json=$payload | ConvertTo-Json -Compress -Depth 8
    $output=$json | & $php (Join-Path $PSScriptRoot 'integration_fixture.php') $Action
    if ($LASTEXITCODE -ne 0) {throw 'Falló el soporte de datos de la integración.'}
    return ($output | ConvertFrom-Json)
}
function Check([string]$Name,[bool]$Passed) {
    $checks.Add([pscustomobject]@{prueba=$Name;correcta=$Passed})
    if (!$Passed) {throw "Falló la integración: $Name"}
    Write-Output "OK: $Name" | Write-Host
}
function Http([string]$Path,$Session=$null,$Body=$null,[int]$Expected=200,[bool]$Multipart=$false) {
    $params=@{Uri=$BaseUrl+$Path;UseBasicParsing=$true;SkipHttpErrorCheck=$true;TimeoutSec=30}
    if ($Session) {$params.WebSession=$Session}
    if ($null -ne $Body) {$params.Method='Post'; if ($Multipart) {$params.Form=$Body} else {$params.Body=$Body}}
    $response=Invoke-WebRequest @params
    if ([int]$response.StatusCode -ne $Expected) {throw "HTTP inesperado en $Path`: $($response.StatusCode), esperado $Expected"}
    if ($response.Content -is [string] -and $response.Content -match 'PHP Warning|<b>Warning</b>|Fatal error|<b>Notice</b>') {throw "Diagnóstico PHP visible en $Path"}
    return $response
}
function Token($Page) {
    $value=[regex]::Match($Page.Content,'name="csrf" value="([^"]+)"').Groups[1].Value
    if (!$value) {throw 'No se encontró el token del formulario.'}; return $value
}
function Login([string]$Email,[string]$Password) {
    $session=[Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $page=Http '/login' $session
    $page=Http '/login' $session @{csrf=(Token $page);email=$Email;password=$Password}
    if ($page.Content -match 'Correo o contraseña incorrectos') {throw 'No se pudo ingresar con una cuenta DEMO.'}
    return $session
}
function SaveSnapshot {
    $snapshot=Fixture 'snapshot'
    $snapshot | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $checkpoint -Encoding utf8
    return $snapshot
}
function NewPropertyBody([string]$Title,[string]$Price) {
    return @{csrf=$vendorToken;titulo=$Title;descripcion='DEMO: datos ficticios para aprender el flujo completo. No corresponde a un inmueble real.';tipo_id=[string]$metadata.types.Departamento;modalidad_id=[string]$metadata.modes.'venta-deptos';precio=$Price;moneda='PEN';area_m2='95.50';dormitorios='3';banos='2';disponibilidad='disponible';publicado='1';departamento='Lima';provincia='Lima';distrito='Miraflores';direccion='Av. Demostración 123 (DEMO)'}
}
$bootstrapCreated=$false
try {
    $bootstrapPassword='DmA1!'+[guid]::NewGuid().ToString('N')
    $metadata=Fixture 'bootstrap' @{password=$bootstrapPassword}; $bootstrapCreated=$true
    $admin=Login $metadata.admin_email $bootstrapPassword
    $adminToken=Token (Http '/users/create' $admin)
    foreach ($privatePath in @('/../tests/dashboard.php','/../output/audit/dashboard.log','/../docs/skills-installation.json','/../.agents/skills/php-pro/SKILL.md','/../tmp/')) {
        $null=Http $privatePath $null $null 403
    }
    Check 'Apache bloquea scripts de prueba y carpetas internas' $true
    $null=Http '/visits?fecha=2026-10%00-28' $admin
    $null=Http '/reports?from=2026-10%00-01&to=2026-10-28' $admin $null 422
    Check 'Fechas malformadas no provocan errores de PHP' $true
    $temporaryPassword='DmV1!'+[guid]::NewGuid().ToString('N')
    $vendorPassword='DmV2!'+[guid]::NewGuid().ToString('N')
    $vendorEmail="vendedora.$run@inmova.test"
    $null=Http '/users/save' $admin @{csrf=$adminToken;nombres='Vendedora DEMO';apellidos='Integración';email=$vendorEmail;telefono='';rol_id=[string]$metadata.roles.vendedor;estado='1';password=$temporaryPassword;password_confirmation=$temporaryPassword}
    $vendor=Login $vendorEmail $temporaryPassword
    $page=Http '/account/password' $vendor
    Check 'Cuenta nueva exige cambiar contraseña' ($page.Content -match 'Debes cambiar tu contraseña inicial')
    $null=Http '/account/password' $vendor @{csrf=(Token $page);current_password=$temporaryPassword;new_password=$vendorPassword;password_confirmation=$vendorPassword}
    $vendorToken=Token (Http '/properties/create' $vendor)
    Check 'Cambio de contraseña habilita acceso al módulo de propiedades' $true
    $snapshot=SaveSnapshot; $vendorId=[int]($snapshot.users | Where-Object email -eq $vendorEmail).id
    $mainTitle="DEMO $run | Venta completada Miraflores"
    $practiceTitle="DEMO $run | Practicar flujo Miraflores"
    $body=NewPropertyBody $mainTitle '350000.50'; $null=Http '/properties/save' $vendor $body 422
    $body.precio='5000001'; $null=Http '/properties/save' $vendor $body 422
    Check 'Precios decimales y superiores a cinco millones rechazados' $true
    $body=NewPropertyBody $mainTitle '350000'; $body['photos[]']=Get-Item (Join-Path $projectRoot 'docs/integration/imagen-demo.png')
    $null=Http '/properties/save' $vendor $body 200 $true
    $practicePhoto=Join-Path $resultsDir 'imagen-practica.png'
    Copy-Item -LiteralPath (Join-Path $projectRoot 'docs/integration/imagen-demo.png') -Destination $practicePhoto
    $body=NewPropertyBody $practiceTitle '350000'; $body['photos[]']=Get-Item $practicePhoto
    $null=Http '/properties/save' $vendor $body 200 $true
    $snapshot=SaveSnapshot; $mainProperty=[int]($snapshot.properties | Where-Object titulo -eq $mainTitle).id; $practiceProperty=[int]($snapshot.properties | Where-Object titulo -eq $practiceTitle).id
    Check 'Dos propiedades con ubicación y fotografía principal' ($mainProperty -gt 0 -and $practiceProperty -gt 0 -and @($snapshot.photos).Count -eq 2)
    $null=Http "/properties/documents/upload?property_id=$mainProperty" $vendor @{csrf=$vendorToken;titulo='DEMO - Ficha técnica';categoria='ficha';document=Get-Item (Join-Path $projectRoot 'output/pdf/ficha-inmueble-demo.pdf')} 200 $true
    $snapshot=SaveSnapshot; $document=[int]$snapshot.documents[0].id
    $download=Http "/properties/documents/download?id=$document" $vendor
    Check 'PDF privado descargable como adjunto' ([bool]($download.Headers['Content-Disposition'] -match 'attachment'))
    $visitor=[Microsoft.PowerShell.Commands.WebRequestSession]::new(); $page=Http "/portal/property?id=$mainProperty" $visitor
    Check 'Inmueble visible en portal sin iniciar sesión' ($page.Content -match [regex]::Escape($mainTitle))
    $null=Http "/portal/inquiry?id=$mainProperty" $visitor @{csrf=(Token $page);nombres='Cliente DEMO - Ana Torres';email="ana.$run@example.test";telefono='000000001';mensaje='DEMO: busco vivienda para mi familia. Quisiera conocer este departamento y evaluar una compra.';consent='1'}
    $visitorPractice=[Microsoft.PowerShell.Commands.WebRequestSession]::new(); $page=Http "/portal/property?id=$practiceProperty" $visitorPractice
    $null=Http "/portal/inquiry?id=$practiceProperty" $visitorPractice @{csrf=(Token $page);nombres='Cliente DEMO - Luis Rojas';email="luis.$run@example.test";telefono='000000002';mensaje='DEMO PARA PRACTICAR: me interesa este departamento. Continuar desde CRM con oportunidad, visita, reserva y cierre.';consent='1'}
    $snapshot=SaveSnapshot; $mainLead=[int]($snapshot.leads | Where-Object propiedad_id -eq $mainProperty).id; $practiceLead=[int]($snapshot.leads | Where-Object propiedad_id -eq $practiceProperty).id
    Check 'Portal crea clientes y consultas en etapa Nuevo' (@($snapshot.leads).Count -eq 2 -and @($snapshot.leads | Where-Object etapa -ne 'nuevo').Count -eq 0)
    $propertyForm=Http "/crm/lead?id=$mainLead" $admin
    Check 'CRM permite seleccionar el inmueble para visitas y reservas' ($propertyForm.Content -match 'working_property_id')
    $null=Http "/crm/property?id=$mainLead" $admin @{csrf=$adminToken;propiedad_id=[string]$practiceProperty}
    $snapshot=SaveSnapshot
    Check 'Selección de inmueble se guarda desde el formulario CRM' ([int]($snapshot.leads | Where-Object id -eq $mainLead).propiedad_id -eq $practiceProperty)
    $null=Http "/crm/property?id=$mainLead" $admin @{csrf=$adminToken;propiedad_id=[string]$mainProperty}
    $null=Http "/crm/update?id=$mainLead" $admin @{csrf=$adminToken;etapa='contactado';vendedor_id=[string]$vendorId;tipo='nota';detalle='QA validación';proxima_accion=('2026-10'+[char]0+'-28T15:00')} 422
    Check 'Seguimiento rechaza fecha con byte nulo sin guardar cambios' $true
    foreach ($invalidStage in @('reservado','cerrado')) {
        $null=Http "/crm/update?id=$mainLead" $admin @{csrf=$adminToken;etapa=$invalidStage;vendedor_id=[string]$vendorId;tipo='nota';detalle='';proxima_accion=''} 422
    }
    Check 'CRM rechaza Reservado y Cerrado sin operación correspondiente' $true
    $dashboard=Http '/dashboard' $admin
    Check 'Dashboard muestra indicadores y pendientes operativos' ($dashboard.Content -match 'Actividad comercial' -and $dashboard.Content -match 'Pendientes de atención' -and $dashboard.Content -match 'Operaciones cerradas')
    $null=Http "/crm/update?id=$mainLead" $admin @{csrf=$adminToken;etapa='contactado';vendedor_id=[string]$vendorId;tipo='llamada';detalle='DEMO: se contactó a Ana, confirmó tres dormitorios y presupuesto de 350000 soles.';proxima_accion=''}
    $practiceSeller=$vendorId; $practiceSellerName='Vendedora DEMO Integración'
    if (@($metadata.practice_seller).Count -gt 0) {$practiceSeller=[int]$metadata.practice_seller[0].id; $practiceSellerName=$metadata.practice_seller[0].nombre}
    $null=Http "/crm/update?id=$practiceLead" $admin @{csrf=$adminToken;etapa='contactado';vendedor_id=[string]$practiceSeller;tipo='nota';detalle='DEMO: caso abierto para que el usuario practique el flujo. Registrar oportunidad y programar visita.';proxima_accion=''}
    $page=Http "/crm/lead?id=$mainLead" $vendor
    Check 'Vendedora accede a la consulta asignada' ($page.Content -match 'Cliente DEMO - Ana Torres')
    $null=Http '/users/save' $admin @{csrf=$adminToken;nombres='Vendedor DEMO';apellidos='Sin asignación';email="externo.$run@inmova.test";telefono='';rol_id=[string]$metadata.roles.vendedor;estado='1';password=$temporaryPassword;password_confirmation=$temporaryPassword}
    $outsider=Login "externo.$run@inmova.test" $temporaryPassword
    $page=Http '/account/password' $outsider
    $null=Http '/account/password' $outsider @{csrf=(Token $page);current_password=$temporaryPassword;new_password=$vendorPassword;password_confirmation=$vendorPassword}
    $null=Http "/crm/lead?id=$mainLead" $outsider $null 403
    $null=Http "/properties/documents/download?id=$document" $outsider $null 404
    Check 'Vendedor sin asignación no accede al CRM ni a documentos ajenos' $true
    $null=Http "/crm/property?id=$mainLead" $outsider @{csrf=(Token (Http '/properties' $outsider));propiedad_id=[string]$practiceProperty} 403
    Check 'Otro vendedor no cambia el inmueble de una consulta ajena' $true
    $null=Http "/opportunities/save?lead_id=$mainLead" $vendor @{csrf=$vendorToken;propiedad_id=[string]$mainProperty;monto_estimado='340000';probabilidad='60';etapa='abierta';notas='DEMO: interés confirmado, pendiente de visita y negociación.'}
    $snapshot=SaveSnapshot; $opportunity=[int]($snapshot.opportunities | Where-Object lead_id -eq $mainLead).id
    Check 'Oportunidad creada con monto y probabilidad' ($opportunity -gt 0)
    $now=[DateTimeOffset]::UtcNow.ToOffset([TimeSpan]::FromHours(-5))
    $taskAt=$now.AddHours(2).ToString('yyyy-MM-ddTHH:mm')
    $null=Http "/crm/update?id=$mainLead" $vendor @{csrf=$vendorToken;etapa='contactado';tipo='llamada';detalle='DEMO: enviar ficha y confirmar los detalles de la visita.';proxima_accion=$taskAt}
    $snapshot=SaveSnapshot; $task=[int]($snapshot.tasks | Where-Object lead_id -eq $mainLead | Select-Object -Last 1).id
    $pending=Http '/tasks' $vendor
    Check 'Próxima acción aparece en Centro de pendientes' ($task -gt 0 -and $pending.Content -match 'enviar ficha')
    $null=Http "/tasks/resolve?id=$task" $vendor @{csrf=$vendorToken;done='1';from='crm'}
    $snapshot=SaveSnapshot
    Check 'Seguimiento completado conserva trazabilidad' ([bool]($snapshot.tasks | Where-Object id -eq $task).tarea_completada_at)
    $now=[DateTimeOffset]::UtcNow.ToOffset([TimeSpan]::FromHours(-5))
    $visitAt=$now.AddMinutes(1); if (60-$now.Second -lt 15) {$visitAt=$now.AddMinutes(2)}
    $visitDate=$visitAt.ToString('yyyy-MM-ddTHH:mm'); $visitInstant=[DateTimeOffset]::ParseExact($visitDate+'-05:00','yyyy-MM-ddTHH:mmzzz',[Globalization.CultureInfo]::InvariantCulture)
    $visitBody=@{csrf=$vendorToken;modalidad='presencial';fecha_hora=$visitDate;estado_visita='solicitada';resultado='DEMO: visita solicitada para revisar distribución y entorno.'}
    $null=Http "/visits/save?lead_id=$mainLead" $vendor $visitBody
    $snapshot=SaveSnapshot; $visit=[int]($snapshot.visits | Where-Object lead_id -eq $mainLead).id
    $visitBody.estado_visita='confirmada'; $visitBody.resultado='DEMO: visita confirmada con el cliente ficticio.'
    $null=Http "/visits/save?id=$visit" $vendor $visitBody
    $null=Http "/crm/property?id=$mainLead" $vendor @{csrf=$vendorToken;propiedad_id=[string]$practiceProperty} 422
    Check 'Visita activa bloquea el cambio de inmueble desde HTTP' $true
    $visitBody.estado_visita='realizada'; $visitBody.resultado='DEMO: intento anticipado que debe rechazarse.'
    $null=Http "/visits/save?id=$visit" $vendor $visitBody 422
    Check 'Visita futura no puede marcarse realizada antes de su hora' $true
    Write-Host "Visita DEMO confirmada para $visitDate (Lima). Se esperará a su hora real."
    # Trabajo independiente durante la espera: catálogo, exportación y caso abierto para practicar.
    $null=Http "/portal/property?id=$practiceProperty" $visitorPractice
    $reportDate=$now.ToString('yyyy-MM-dd')
    $csv=Http "/reports/export?from=$reportDate&to=$reportDate" $admin
    Check 'Reporte del día exporta CSV' ([bool]($csv.Headers['Content-Type'] -match 'text/csv'))
    $practice=Http "/crm/lead?id=$practiceLead" $admin
    Check 'Caso abierto listo para practicar con vendedor asignado' ($practice.Content -match 'Luis Rojas')
    while ([DateTimeOffset]::UtcNow -lt $visitInstant.ToUniversalTime().AddSeconds(1)) {Start-Sleep -Seconds 3}
    $visitBody.resultado='DEMO: visita realizada. Ana valoró la distribución y solicitó negociar el precio final.'
    $null=Http "/visits/save?id=$visit" $vendor $visitBody
    Check 'Visita realizada con resultado registrado' $true
    $null=Http "/crm/update?id=$mainLead" $vendor @{csrf=$vendorToken;etapa='negociacion';tipo='nota';detalle='DEMO: después de la visita se negocia una venta por 335000 soles.';proxima_accion=''}
    $null=Http "/reservations/store?lead_id=$mainLead" $vendor @{csrf=$vendorToken;monto_reserva='10000'}
    $snapshot=SaveSnapshot; $reservation=[int]($snapshot.reservations | Where-Object lead_id -eq $mainLead).id
    $null=Http "/crm/property?id=$mainLead" $vendor @{csrf=$vendorToken;propiedad_id=[string]$practiceProperty} 422
    Check 'Reserva pendiente bloquea el cambio de inmueble desde HTTP' $true
    $null=Http "/reservations/store?lead_id=$mainLead" $vendor @{csrf=$vendorToken;monto_reserva='10000'} 422
    $null=Http "/reservations/transition?id=$reservation" $vendor @{csrf=$vendorToken;accion='aprobada';motivo='DEMO: vendedor no debe aprobar.'} 403
    Check 'Reserva duplicada y aprobación por vendedor bloqueadas' $true
    $null=Http "/reservations/transition?id=$reservation" $admin @{csrf=$adminToken;accion='aprobada';motivo='DEMO: administrador revisa la solicitud y aprueba la reserva ficticia.'}
    $snapshot=SaveSnapshot
    Check 'Aprobación sincroniza inmueble y consulta a Reservado' (($snapshot.properties | Where-Object id -eq $mainProperty).disponibilidad -eq 'reservado' -and ($snapshot.leads | Where-Object id -eq $mainLead).etapa -eq 'reservado')
    $null=Http "/crm/update?id=$mainLead" $vendor @{csrf=$vendorToken;etapa='perdido';tipo='nota';detalle='';proxima_accion=''} 422
    Check 'CRM impide perder una consulta con reserva aprobada' $true
    $editBody=NewPropertyBody $mainTitle '350000'; $editBody.disponibilidad='reservado'; $editBody.modalidad_id=[string]$metadata.modes.alquiler
    $null=Http "/properties/save?id=$mainProperty" $vendor $editBody 422
    Check 'Reserva aprobada impide cambiar venta por alquiler' $true
    $null=Http "/closings/store?reserva_id=$reservation" $vendor @{csrf=$vendorToken;monto_final='335000';fecha_cierre=$reportDate;observaciones='DEMO: vendedor no confirma cierres.'} 403
    $closingBody=@{csrf=$adminToken;monto_final='335000';fecha_cierre=$reportDate;observaciones='DEMO - PRUEBA DE INTEGRACIÓN: venta ficticia completada tras consulta, oportunidad, seguimiento, visita y reserva. No acredita cobros ni operación real.'}
    $null=Http "/closings/store?reserva_id=$reservation" $admin $closingBody
    $snapshot=SaveSnapshot; $closing=[int]$snapshot.closings[0].id
    Check 'Cierre actualiza propiedad Vendido y la retira del portal' (($snapshot.properties | Where-Object id -eq $mainProperty).disponibilidad -eq 'vendido' -and [int]($snapshot.properties | Where-Object id -eq $mainProperty).publicado -eq 0)
    Check 'Reserva y consulta quedan cerradas' (($snapshot.reservations | Where-Object id -eq $reservation).estado_reserva -eq 'cerrada' -and ($snapshot.leads | Where-Object id -eq $mainLead).etapa -eq 'cerrado')
    $visitBody.estado_visita='confirmada'; $visitBody.fecha_hora=$visitInstant.AddDays(1).ToString('yyyy-MM-ddTHH:mm'); $visitBody.resultado=''
    $null=Http "/visits/save?lead_id=$mainLead" $vendor $visitBody 422
    Check 'Agenda rechaza visita nueva después del cierre' $true
    $null=Http "/visits/save?id=$visit" $vendor $visitBody 422
    Check 'Agenda impide reactivar visita histórica después del cierre' $true
    $won=$snapshot.opportunities | Where-Object id -eq $opportunity
    Check 'Oportunidad ganada adopta monto final y probabilidad 100' ($won.etapa -eq 'ganada' -and [decimal]$won.monto_estimado -eq 335000 -and [int]$won.probabilidad -eq 100)
    $null=Http "/portal/property?id=$mainProperty" $visitor $null 404
    $null=Http "/closings/store?reserva_id=$reservation" $admin $closingBody 422
    Check 'Inmueble cerrado oculto al visitante y cierre duplicado bloqueado' $true
    $history=Http "/crm/lead?id=$mainLead" $admin
    Check 'Historial muestra seguimiento, visita, reserva y cierre' ($history.Content -match 'Visita actualizada' -and $history.Content -match 'Reserva' -and $history.Content -match 'Cierre #')
    $report=Http "/reports?from=$reportDate&to=$reportDate" $admin
    Check 'Operación aparece en reportes del día' ($report.Content -match [regex]::Escape($mainTitle))
    $null=Http "/properties/documents/download?id=$document" $admin
    Check 'Documentación privada se conserva tras el cierre' $true
    $result=[pscustomobject]@{run=$run;completed_at=([DateTimeOffset]::UtcNow.ToOffset([TimeSpan]::FromHours(-5)).ToString('yyyy-MM-dd HH:mm:ss'));base_url=$BaseUrl;main_property_id=$mainProperty;main_lead_id=$mainLead;opportunity_id=$opportunity;visit_id=$visit;reservation_id=$reservation;closing_id=$closing;document_id=$document;practice_property_id=$practiceProperty;practice_lead_id=$practiceLead;practice_seller=$practiceSellerName;visit_datetime=$visitDate;checks=$checks}
    $result | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath (Join-Path $resultsDir 'resultado.json') -Encoding utf8
    Write-Host "RESULTADO: $resultsDir"
} finally {
    if ($bootstrapCreated) { $null=Fixture 'secure_accounts'; $null=SaveSnapshot }
    $checks | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath (Join-Path $resultsDir 'comprobaciones.json') -Encoding utf8
    $bootstrapPassword=$null; $temporaryPassword=$null; $vendorPassword=$null
}
