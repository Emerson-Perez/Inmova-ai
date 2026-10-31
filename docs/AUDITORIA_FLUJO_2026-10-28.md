# Auditoría del flujo de negocio — 28/10/2026

## Correcciones verificadas el 28/10/2026

Los hallazgos siguientes describen el estado anterior. Se corrigieron las reglas de `Lead::update`, la elegibilidad de visitas nuevas y el dashboard. Reservado requiere reserva aprobada; Cerrado requiere cierre registrado; una reserva aprobada impide cambiar la etapa desde CRM. Se pueden conservar esas etapas al agregar seguimientos. Las visitas nuevas rechazan consultas cerradas/perdidas e inmuebles vendidos/alquilados, sin impedir regularizar visitas históricas. El formulario CRM explica el flujo y no ofrece etapas automáticas que todavía no corresponden.

Validación posterior: 259 comprobaciones PHP satisfactorias (236 existentes, 17 de reglas comerciales y 6 de dashboard), y 29 comprobaciones E2E HTTP. Evidencia: `docs/integration/demo_20261028_155822_9c11d0/resultado.json`. Las cuentas temporales del E2E quedaron desactivadas; se conservaron sus casos DEMO según el comportamiento del script. No se repararon automáticamente posibles inconsistencias históricas anteriores.

`tests/business_audit.php` ahora es una prueba de regresión: exige que las brechas se rechacen, comprueba la conservación de estados y verifica seguimientos y visitas históricas válidos. `tests/dashboard.php` verifica roles, contadores cero, errores visibles y escape HTML. Ambos terminan con código cero al pasar. El alcance continúa siendo HTTP/BD y renderizado HTML, sin validación visual de navegador ni concurrencia simultánea.

## Dictamen

El recorrido comercial normal funciona en el entorno local, pero el flujo no está completamente protegido contra cambios contradictorios desde CRM. Corregir las reglas de etapas antes de considerarlo validado para producción.

## Verificación ejecutada

- 10 suites PHP existentes: 236 comprobaciones satisfactorias (academia, evaluaciones, asistente, documentos, oportunidades, reportes, reservas/cierres, simulador, pendientes y usuarios). Logs en `output/audit/`.
- Sintaxis PHP: 105 archivos sin errores, incluyendo la nueva reproducción de auditoría.
- E2E HTTP real con Apache/MySQL: alta de vendedor, cambio obligatorio de contraseña, inmueble con fotografía y ubicación, documento PDF privado, publicación, consulta pública, asignación CRM, oportunidad, seguimiento y atención de pendiente, visita confirmada y realizada, negociación, solicitud y aprobación de reserva, cierre y reportes CSV.
- Incluyó rechazos de precios inválidos, acceso de vendedor ajeno, visita realizada anticipadamente, reserva duplicada, aprobación/cierre por vendedor y cierre duplicado.
- Evidencia: `docs/integration/demo_20261028_154935_7f903c/resultado.json`, `comprobaciones.json` y `estado.json`.

## Hallazgos

### Alta: etapas terminales y reserva se pueden establecer sin operación

`app/models/Lead.php:66-74` admite cualquier etapa válida del formulario. El control actual impide reabrir una operación que ya tiene cierre, pero no impide entrar manualmente en Cerrado sin cierre ni en Reservado sin reserva aprobada. `app/views/crm/show.php:9` ofrece ambas etapas al usuario.

Reproducido: consulta nueva → Cerrado sin reserva ni cierre; consulta nueva → Reservado mientras el inmueble sigue Disponible y no existen reservas. Esto puede ocultar pendientes y excluir oportunidades del cálculo comercial sin una operación real.

Recomendación: derivar Reservado y Cerrado exclusivamente de las transacciones de reservas y cierres; permitir conservar esos valores al guardar seguimientos, pero rechazar su asignación manual incompatible.

### Alta: reserva aprobada y consulta perdida pueden coexistir

`Lead::update` no verifica reservas aprobadas antes de modificar la etapa. Reproducido: aprobar reserva → cambiar consulta a Perdido; la reserva sigue Aprobada y el inmueble Reservado. El cierre rechaza consultas perdidas, por lo que el proceso requiere intervención manual para continuar.

Recomendación: bloquear cambios incompatibles mientras exista reserva aprobada y exigir su cancelación por el flujo autorizado antes de perder la consulta. Aplicar la regla dentro de la transacción y revisar el orden de bloqueos respecto de reservas y cierres.

### Media: agenda acepta nuevas visitas para consultas perdidas

`app/models/Visit.php:53` exige inmueble activo, pero no valida que la consulta esté comercialmente abierta ni que el inmueble sea elegible por disponibilidad. Reproducida una visita futura confirmada para una consulta Perdida.

Recomendación: impedir nuevas visitas en consultas cerradas/perdidas; definir también la regla para inmuebles vendidos/alquilados. Conservar el registro de resultados de visitas históricas cuando corresponda.

### Media: dashboard sin indicadores operativos

`DashboardController` obtiene resumen comercial y pendientes, pero `app/views/dashboard/index.php` solo muestra el texto “Paso 1 completado” y cuatro tarjetas estáticas. No presenta los indicadores ni los errores de carga que recibe. El panel no permite supervisar el flujo descrito en el README.

Recomendación: mostrar indicadores y pendientes según rol, con enlaces a los módulos y estados de error visibles.

## Reproducción adicional

Ejecutar `C:\xampp\php\php.exe tests/business_audit.php`. Es un diagnóstico de las brechas actuales, no una prueba de aceptación: imprime `BRECHA` cuando consigue reproducirlas. Crea registros temporales propios y los elimina en `finally`. La ejecución de esta auditoría completó las cuatro reproducciones y la limpieza.

## Alcance y datos conservados

El E2E es por HTTP y comprueba persistencia en MySQL; no constituye una prueba visual de navegador, de carga ni de concurrencia simultánea. Academia, certificados, simulador y asistente se verificaron mediante sus suites PHP, no mediante recorridos HTTP completos de cada módulo. Las llamadas al proveedor IA se simulan en esas suites; no se validó calidad ni conexión real con el proveedor.

El script E2E existente conserva datos DEMO: una venta cerrada y un caso abierto para practicar, fotografías y documento. Estos registros afectan el inventario y los reportes locales. Las cuentas temporales se desactivan al finalizar; el caso abierto puede quedar asignado a un vendedor existente según la selección del script. No se alteraron las reglas de aplicación ni las credenciales existentes. Los defectos descritos quedan pendientes de corrección.
