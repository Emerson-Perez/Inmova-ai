# Correcciones y verificación — 28/10/2026

## Resultado

Se corrigieron los defectos reproducidos en la revisión de reglas comerciales, permisos HTTP, entradas malformadas y concurrencia. Las 259 comprobaciones iniciales pasaban; los defectos nuevos estaban fuera de esa cobertura. No se afirma ausencia de todos los errores posibles.

## Cambios

| Defecto reproducido | Corrección |
| --- | --- |
| Apache permitía ejecutar `tests/dashboard.php` y leer documentación y resultados internos sin sesión | `.htaccess` deniega acceso a `tests/`, `docs/`, `output/`, `tmp/` y `.agents/`. Las pruebas PHP también exigen ejecución CLI. Se desactiva el listado de directorios en `public/`. |
| Reactivar o reprogramar una visita existente eludía la elegibilidad de consultas e inmuebles | `Visit::save` revalida la elegibilidad al reactivar, cambiar fecha o responsable. Consulta cerrada/perdida e inmueble inactivo/vendido/alquilado impiden la nueva programación. Se conserva la regularización de visitas históricas. También se validan fechas, estados, modalidad y resultado dentro del modelo. |
| Se podía cambiar venta por alquiler con reserva aprobada | `Property::save` conserva la modalidad mientras exista una reserva aprobada; requiere cancelarla antes de cambiarla. |
| Una edición iniciada antes de desactivar el inmueble podía seguir escribiendo | El modelo comprueba que el inmueble siga activo después de adquirir su bloqueo. |
| Fechas con bytes nulos provocaban `ValueError` | CRM, visitas, reportes y cierres las tratan como fechas inválidas, sin error fatal. |
| Contraseña con byte nulo llegaba hasta `password_hash` y provocaba un error | Se rechaza en `User::passwordError`, antes de generar el hash. |
| Una carga fotográfica incompleta emitía advertencias por claves ausentes | Se valida la ausencia del archivo temporal antes de utilizarlo. |
| Dos solicitudes simultáneas podían crear dos reservas activas del mismo lead | La consulta de reservas existentes usa una lectura actual con bloqueo, después de serializar propiedad y consulta. |
| Dos restauraciones simultáneas podían superar 20 documentos activos | El límite se comprueba con una lectura actual bajo el bloqueo del inmueble, evitando una instantánea anterior a la espera. |
| Completar una lección mientras era retirada podía guardar progreso sobre su versión anterior | Se localiza el curso antes de iniciar la transacción; tras bloquearlo, se relee el estado vigente de la lección y se valida antes de guardar. |

## Evidencia

- `tests/regressions.php`: nueve casos que fallaron antes de sus correcciones y ahora pasan; advierte también ante errores PHP. Solo crea y elimina sus propios registros temporales.
- `tests/concurrency.php`: tres escenarios con procesos PHP independientes y barreras comprobadas en InnoDB. Se observaron reservas duplicadas, 21 documentos activos y progreso sobre una lección retirada antes de aplicar sus correcciones. Después, cada escenario respeta el resultado esperado. Sus archivos y registros temporales se eliminan al finalizar.
- Suites PHP: **271 comprobaciones aprobadas en 14 suites**. Logs en `output/audit/`. La prueba de documentos detectó un error introducido al añadir su restricción CLI dentro de un fixture; se reparó y se volvió a ejecutar esa suite y su comprobación de sintaxis. Las demás suites mantuvieron resultados válidos sobre el mismo código.
- Sintaxis: **108 archivos PHP** de `app/`, `public/` y `tests/` verificados. El único fallo detectado fue el fixture mencionado, cuya sintaxis se volvió a verificar después de corregirlo.
- E2E HTTP: **34 comprobaciones aprobadas**. Evidencia en `docs/integration/demo_20261028_164921_195d46/resultado.json`, con estado final en `estado.json`. Incluye protección HTTP de carpetas, fechas malformadas, consulta pública, oportunidad, seguimiento, visita realizada, reserva, cierre, reportes y los nuevos rechazos de modalidad/reactivación.
- Se comprobó por HTTP que pruebas, documentos de desarrollo, resultados y manifiestos de skills devuelven 403. No se solicitaron claves ni contenidos privados para comprobar esas restricciones.

## Datos y límites

El E2E conserva sus casos DEMO, fotos y documento privado; afecta el inventario y los reportes locales. Sus tres cuentas temporales quedaron desactivadas. Las pruebas de regresión y concurrencia limpian sus fixtures. No se repararon automáticamente posibles incoherencias históricas ajenas a las pruebas.

No se hicieron llamadas reales al proveedor de IA ni despliegues. Playwright no está instalado en el runtime Python revisado; no se ejecutaron pruebas visuales de navegador. El recorrido fue por HTTP y base de datos; las pruebas concurrentes cubren los tres escenarios descritos, no una prueba general de carga ni todas las interacciones posibles.

Las restricciones `.htaccess` se verificaron en Apache/XAMPP. En otro servidor se debe configurar un document root que apunte únicamente a `public/`, y mantener privadas las demás carpetas.
