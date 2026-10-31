# Mejoras de seguridad y manejo de errores

Se aplicaron las guías `security-auditor`, `error-handling-patterns` y
`code-review-excellence` al arranque HTTP, las sesiones y los controladores.

## Cambios

- Las excepciones y advertencias inesperadas generan una respuesta 500 en español
  con referencia de soporte. Se descarta la salida parcial del búfer.
- El registro conserva clase, código, ubicación y hasta cinco marcos de llamada,
  sin mensajes de excepción ni argumentos que puedan contener claves o datos personales.
  Se desactiva el registro automático de PHP en el punto de entrada para evitar
  duplicados sin filtrar; `ErrorHandler::report()` escribe explícitamente los metadatos.
- Los controladores utilizan el mismo registro seguro. Guardar propiedades distingue
  errores de validación (422) de fallos inesperados (503).
- Las cookies de sesión utilizan HttpOnly y SameSite=Lax, y Secure cuando Apache
  informa HTTPS. Se habilita el modo estricto de sesión y se impiden IDs en URLs.
- Las páginas protegidas solicitan no almacenar respuestas en caché. Se añaden
  cabeceras contra interpretación de contenido y enmarcado desde otros sitios.
- Cerrar sesión rechaza tokens CSRF inválidos con 403 y elimina la cookie al salir
  correctamente.
- Los datos de una vista no pueden sobrescribir el nombre de la plantilla o layout
  mediante `extract()`.

## Verificación reproducible

- `php tests/runtime_security.php`: salida privada, referencia, HTTP 500, plantilla
  y opciones de cookies, con excepciones y advertencias sintéticas.
- PowerShell 7: `tests/style_http.ps1`: autenticación real, rechazo de ID de sesión
  impuesto, cabeceras, módulos, filtros y cierre de sesión válido e inválido.
- Suites PHP de `tests/`, excepto `integration_fixture.php`, que es soporte CLI.
- `tests/integration_full.ps1`: recorrido comercial HTTP; conserva registros DEMO
  y desactiva sus propias cuentas temporales.

## Límites

Ejecución local del 28/10/2026: sintaxis correcta en 118 archivos PHP,
357 comprobaciones de suites PHP y 22 comprobaciones HTTP superadas.
El recorrido comercial terminó sin errores; evidencia:
`docs/integration/demo_20261028_214430_c6a8ec/resultado.json`.

No es una certificación de seguridad ni una revisión visual de navegador. La
protección Secure se verifica como configuración; el servidor local usa HTTP.
Un proxy inverso con terminación TLS requiere una configuración de confianza
explícita; no se confía en cabeceras de proxy enviadas por visitantes.
El manejador cubre fallos después de su instalación; errores de arranque anteriores,
agotamiento de memoria o respuestas ya enviadas requieren controles del servidor.
Las pruebas de IA usan transporte simulado y no verifican credenciales reales.
