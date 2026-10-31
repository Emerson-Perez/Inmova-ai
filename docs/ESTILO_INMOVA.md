# Identidad visual de INMOVA

## Tema Vibrante

La variante actual usa azul eléctrico, cian y detalles dorados. `public/assets/css/theme-vibrant.css` se carga después de los estilos base y cambia la paleta, la cabecera, el menú activo, los indicadores, botones, tablas, portal y acceso. Los componentes mantienen sus puntos de adaptación móvil y no añaden animaciones ni dependencias. Los indicadores reciben acentos diferenciados; los estados conservan su significado y sus etiquetas.

El layout versiona también esta hoja para evitar caché obsoleta. Se verificaron las plantillas, la navegación y la carga HTTP del tema; la revisión visual de navegador sigue pendiente, según el alcance descrito al final del documento.

La interfaz comparte un sistema visual basado en azul marino, verde petróleo y superficies claras. Se conserva PHP y Bootstrap 5.3.3, sin fuentes remotas ni nuevas dependencias de frontend.

## Archivos

- `app/views/layouts/main.php`: barra lateral por rol, cabecera, menú de cuenta, navegación pública y acceso directo al contenido por teclado.
- `app/views/layouts/symbols.php`: iconos SVG locales reutilizables y decorativos.
- `public/assets/css/app.css`: variables de diseño, tarjetas, botones, tablas, formularios, dashboard, portal, login y puntos de adaptación móvil.
- `public/assets/js/app.js`: apertura/cierre del menú móvil, Escape, control del foco y menú de cuenta.
- `app/views/dashboard/index.php`, `app/views/auth/login.php` y `app/views/portal/index.php`: composición adaptada al nuevo sistema visual.

Las hojas CSS y JavaScript incluyen una versión basada en la fecha de modificación para evitar que el navegador conserve una versión anterior. Las tablas mantienen desplazamiento dentro de su contenedor; el menú lateral se convierte en panel móvil por debajo de 992 px. Sin JavaScript, la navegación permanece disponible en el flujo del documento.

## Accesibilidad y alcance

Se incorporaron foco visible, enlace para saltar al contenido, atributos del menú móvil, aislamiento del panel cerrado mediante `inert`, Escape y contención del foco del panel abierto. Se respeta `prefers-reduced-motion`. Se midieron combinaciones principales de texto/fondo, sin afirmar conformidad WCAG de todo el sistema.

No se cambian autorizaciones ni reglas comerciales. Las rutas se conservan y cerrar sesión sigue usando POST y CSRF. El nuevo menú de cuenta incluye Mi contraseña y Cerrar sesión.

## Verificación

- `tests/dashboard.php`: ocho comprobaciones de datos, roles y estados de error del dashboard.
- `tests/layout.php`: diez comprobaciones de navegación por rol, módulo activo, controles móviles, escape HTML, acceso al contenido y cierre de sesión.
- `tests/style_http.ps1`: acceso real con cuenta temporal propia y carga de trece módulos adicionales con el layout compartido. Desactiva la cuenta al terminar; no crea registros comerciales.
- Plantillas modificadas verificadas con `php -l`; JavaScript verificado con `node --check`.
- Portal, login y recursos CSS/JS respondieron HTTP 200.

La revisión visual en Chrome no pudo completarse porque expiró la autorización de la herramienta de control de la ventana. La adaptación móvil, el foco y el comportamiento con lector de pantalla aún requieren verificación en navegador; las pruebas de HTML/HTTP no sustituyen esa revisión.
