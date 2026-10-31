# QA en navegador

Suite: `tests/browser_qa.py`, basada en la skill `webapp-testing`.
Ejecuta Chrome headless mediante Python Playwright contra
`http://localhost/inmova-ai/public` con Apache y MySQL activos.

## Ejecución

Usar Python con Playwright instalado. La suite también admite la dependencia
local en `tmp/qa-python`. Requiere Chrome en
`C:/Program Files/Google/Chrome/Application/chrome.exe` y PHP en
`C:/xampp/php/php.exe`. Ejecutar desde la raíz:

```text
python tests/browser_qa.py
```

La cuenta administradora inicial se crea con el fixture CLI revisado. Las
operaciones comerciales posteriores se realizan mediante formularios y controles
del navegador. Las consultas del fixture comprueban los estados persistidos.
Se usan contextos separados para administrador, vendedor, otro vendedor y visitante.

## Cobertura

- Login obligatorio, credenciales inválidas, cambio de contraseña inicial y logout.
- Restricción de usuarios y de consultas ajenas para vendedores.
- Propiedad publicada con fotografía, filtros combinados y numeración.
- Vista de escritorio y móvil de 390 × 844, menú y desplazamiento de tabla.
- Consulta pública, asignación de vendedor e historial CRM.
- Visita confirmada, rechazo de realización anticipada y realización a su hora real.
- Reserva pendiente, permisos de aprobación y sincronización de estados.
- Cierre y retiro del portal, sin posibilidad de nuevas visitas desde el CRM cerrado.
- Carga de doce módulos para vendedor y captura de excepciones JavaScript.

## Hallazgo corregido

Un encabezado accesible de la tabla de propiedades, posicionado de forma absoluta,
escapaba del contenedor desplazable y ampliaba la página móvil a 625 píxeles.
Establecer `position: relative` en `.table-responsive` conserva el texto accesible
y contiene el desplazamiento. Se reprodujo el fallo y se verificó un ancho final
de 390 píxeles, además del acceso mediante desplazamiento a las acciones de la tabla.

## Evidencia y efectos

Resultado final del 28/10/2026 (Lima): **37 comprobaciones de navegador
superadas, cero fallos y cero excepciones JavaScript**. Evidencia en
`output/browser-qa/demo_20261029_003205_9b1ae2/resultado.json` y cuatro capturas.
También pasaron las diez comprobaciones de `php tests/layout.php`.
Se revisaron visualmente las capturas móvil y del cierre. Los nombres de carpetas
usan UTC, por eso la ejecución figura con fecha del día siguiente.

Cada ejecución guarda `resultado.json` y capturas de páginas con datos propios en
`output/browser-qa/demo_.../`, protegido del acceso HTTP por la configuración existente.
Las contraseñas son aleatorias, no se guardan en la evidencia y las cuentas QA se
desactivan al terminar. Los casos comerciales se conservan como DEMO y pueden afectar
reportes locales. Las propiedades de pruebas incompletas sin consultas se ocultan.

No se realizan llamadas al proveedor IA ni se prueban cobros o mensajes externos.
Esta suite no cubre todos los roles, navegadores, dispositivos, accesibilidad ni
carga concurrente. Las capturas no constituyen una certificación de accesibilidad.
