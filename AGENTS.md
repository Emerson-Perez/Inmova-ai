# INMOVA: instrucciones de desarrollo

## Contexto

Aplicación PHP con MVC propio, PDO y MySQL, ejecutada localmente con XAMPP en Windows. Mantener el stack y los patrones existentes salvo que la tarea justifique un cambio. No adoptar ejemplos npm, PostgreSQL, SaaS o frameworks de las skills como requisitos del proyecto.

## Skills del proyecto

Las diecisiete skills seleccionadas se encuentran en `.agents/skills/`. Leer el `SKILL.md` correspondiente cuando la tarea lo requiera, y únicamente sus referencias necesarias:

| Tarea | Skill |
| --- | --- |
| Implementación o refactorización PHP | `php-pro` |
| Arquitectura, límites de módulos y casos de uso | `software-architecture` |
| Esquema, relaciones, índices o migraciones | `database-design` |
| Autenticación, autorización, entradas o archivos privados | `backend-security-coder` |
| Pruebas funcionales de navegador | `webapp-testing` |
| Fallos reproducibles o comportamiento inesperado | `systematic-debugging` |
| Reglas de negocio nuevas o correcciones con regresión repetible | `test-driven-development` |
| Verificación y comunicación de resultados | `verification-before-completion` |
| Diseño visual, formularios y experiencia móvil | `ui-ux-pro-max` |
| Accesibilidad, teclado, foco y contraste | `accessibility-compliance-accessibility-audit` |
| Rendimiento SQL y planes de ejecución | `sql-optimization-patterns` |
| Preparación de despliegues y reversión | `deployment-procedures` |
| Evaluación de calidad y regresiones del asistente IA | `llm-evaluation` |
| Recuperación de conocimiento y respuestas sobre documentación | `rag-implementation` |
| Auditoría de seguridad, amenazas y controles de acceso | `security-auditor` |
| Manejo de fallos, excepciones y mensajes seguros | `error-handling-patterns` |
| Revisión de cambios y hallazgos priorizados | `code-review-excellence` |

Son guías para el agente de desarrollo, no funcionalidades ejecutadas por PHP ni instrucciones del asistente público. La selección no autoriza por sí sola despliegues, servicios de pago, envío de datos privados o cambios de stack.

Al usar las nuevas áreas: conservar PHP/Bootstrap para UI; verificar accesibilidad con evidencia automática y manual sin afirmar conformidad total por un escaneo; medir SQL antes de optimizar y adaptar EXPLAIN al motor instalado. Los ejemplos de despliegue deben ajustarse a PHP/Apache/MySQL. Evaluaciones IA y RAG requieren implementar su infraestructura; no indexar documentos privados ni enviarlos a proveedores sin autorización y control de acceso por usuario. Las skills referenciadas por otra skill solo están disponibles si se instalaron explícitamente.

## Reglas de negocio y seguridad

- Reservado requiere reserva aprobada y Cerrado requiere cierre registrado. No introducir caminos alternativos desde CRM.
- Mantener transacciones y orden de bloqueos coherentes entre propiedades, reservas y consultas. Probar conflictos de negocio cuando cambien estos flujos.
- Verificar permisos por registro y rol; no confiar en IDs, campos ocultos ni restricciones de la interfaz.
- Mantener documentos privados fuera de acceso público. No leer ni mostrar claves de `app/config/ai.local.json`.
- Usar datos ficticios en pruebas; no alterar cuentas ni registros del usuario para facilitar una prueba.
- Mantener fechas comerciales en America/Lima y separar monedas y operaciones en reportes.

## Verificación

PHP CLI: `C:\xampp\php\php.exe`. Revisar los scripts antes de ejecutarlos y elegir comprobaciones proporcionales al cambio.

- Reglas comerciales: `tests/business_audit.php` y `tests/reservations.php`.
- Selección de inmueble y recorrido desde consulta general: `tests/commercial_flow.php`. Mantener correspondencia de inmueble entre consulta, reserva y cierre, conservando los inmuebles originales de los registros históricos.
- Dashboard por roles, errores y escape HTML: `tests/dashboard.php`.
- Regresiones de validación, visitas e inmuebles: `tests/regressions.php`.
- Conflictos reales entre procesos PHP: `tests/concurrency.php`. Requiere `proc_open` y acceso de diagnóstico a `information_schema.innodb_trx`; solo usa fixtures propios y nunca se invoca desde HTTP.
- Otros módulos: sus suites PHP en `tests/`; `integration_fixture.php` es soporte CLI, no una suite independiente.
- E2E HTTP: `tests/integration_full.ps1`, con PowerShell 7, Apache y MySQL activos. Conserva casos DEMO, fotografías y documentos, afecta reportes locales y desactiva sus cuentas temporales al terminar. Informar esos efectos al ejecutarlo.
- Las pruebas de navegador de `webapp-testing` requieren revisar las dependencias y usar una base/cuentas de prueba aisladas. El E2E HTTP existente no es una prueba visual.
- Las suites IA usan transporte simulado; no presentar sus resultados como validación de conexión real, calidad de modelo ni consumo de pago.

Informar comandos ejecutados, resultados y límites reales. No volver a ejecutar pruebas costosas sin un cambio o una incertidumbre que lo justifique.
