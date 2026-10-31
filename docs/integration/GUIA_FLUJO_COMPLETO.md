# Guía del flujo comercial de INMOVA AI

Prueba realizada el 27 de octubre de 2026, a las 19:56, hora de Lima. Resultado: **25 comprobaciones correctas de 25**.

Se registraron datos ficticios mediante los formularios HTTP reales del sistema, con sesiones, permisos y tokens CSRF. Solo la cuenta administrativa temporal de arranque se creó desde el soporte CLI. No se modificaron las contraseñas de los usuarios existentes. Las tres cuentas temporales de esta ejecución quedaron desactivadas al finalizar; sus referencias se conservan en el historial.

Todos los nombres de inmuebles comienzan por `DEMO demo_20261028_005521_1a0b97`. Los teléfonos, clientes, direcciones, mensajes e imágenes de estos casos son ficticios. La operación aparece en reportes y afecta sus totales; es una demostración, no acredita una venta ni un cobro real. Esta prueba cubre el flujo comercial de venta; no ejecuta llamadas a IA ni ejercicios de Academia.

## 1. Revisa el caso ya terminado

Ingresa con tu cuenta habitual de administrador y abre [la consulta de Ana Torres, #56](http://localhost/inmova-ai/public/crm/lead?id=56). El historial reúne las acciones del recorrido.

| Paso | Datos registrados | Resultado |
|---|---|---|
| Propiedad #93 | Departamento en venta, Miraflores, Lima. S/ 350 000, 95.50 m², 3 dormitorios, 2 baños. Imagen sintética y ficha PDF privada. | Inicialmente disponible y publicado. |
| Consulta #56 desde el portal | Cliente DEMO - Ana Torres; teléfono ficticio 000000001; correo de prueba terminado en `example.test`; interés en comprar vivienda. | Cliente y consulta nuevos. |
| Asignación y contacto | Vendedora DEMO Integración; llamada sobre necesidades y presupuesto. | Consulta Contactado. |
| Oportunidad #5 | Monto estimado 340000, probabilidad 60 %, estado Abierta. | Oportunidad vinculada al inmueble y a la consulta. |
| Seguimiento | “DEMO: enviar ficha y confirmar los detalles de la visita.” Próxima acción programada y luego completada. | Acción visible en pendientes; conserva su registro de finalización. |
| Visita #24 | Presencial, 27/10/2026 a las 19:56, hora de Lima. Solicitada → Confirmada → Realizada, con resultado. | Visita realizada después de llegar su hora real. |
| Negociación | Nota de acuerdo por 335000 soles. | Consulta en Negociación. |
| Reserva #47 | Monto 10000; solicitada por vendedora y aprobada por administrador. | Inmueble y consulta Reservado. |
| Cierre #24 | Monto final 335000; fecha 27/10/2026; observaciones marcadas DEMO. | Reserva cerrada, consulta Cerrado, inmueble Vendido y sin publicación. Oportunidad Ganada por S/ 335 000, al 100 %. |
| Reportes | Rango del 27/10/2026 al 27/10/2026. | Operación visible y exportación CSV disponible. |

Accesos para revisar:

- [Historial completo de Ana](http://localhost/inmova-ai/public/crm/lead?id=56).
- [Reserva #47](http://localhost/inmova-ai/public/reservations/show?id=47).
- [Cierre #24](http://localhost/inmova-ai/public/closings/show?id=24).
- [Documentos privados de la propiedad #93](http://localhost/inmova-ai/public/properties/documents?property_id=93).
- [Reportes de la fecha de la prueba](http://localhost/inmova-ai/public/reports?from=2026-10-27&to=2026-10-27).

El enlace público del inmueble vendido devuelve 404 porque dejó de estar publicado. El administrador conserva acceso al expediente y al PDF privado.

## 2. Continúa tú el caso abierto

Ya están creados el inmueble #94 y [la consulta de Luis Rojas, #57](http://localhost/inmova-ai/public/crm/lead?id=57). Está en **Contactado**, asignada a **Alexa Alata**, sin oportunidad, visita, reserva ni cierre. Puedes continuar con una cuenta de administrador o con la cuenta de Alexa para las acciones que permite su rol. La aprobación y el cierre requieren administrador.

1. **Revisa la propiedad.** En el [portal del inmueble #94](http://localhost/inmova-ai/public/portal/property?id=94) verás el departamento DEMO, disponible por S/ 350 000. La consulta ya existe: no hace falta volver a enviarla para esta práctica.
2. **Crea la oportunidad.** En la consulta #57, pulsa **Nueva oportunidad**. Selecciona el inmueble #94, escribe `340000` en monto estimado, `60` en probabilidad, estado **Abierta**, y en notas: “DEMO: interés confirmado; pendiente de visita”. Guarda.
3. **Registra seguimiento.** En **Gestión comercial**, usa etapa **Contactado**, tipo **Llamada**, detalle “DEMO: enviar ficha y confirmar disponibilidad para visita”, y una próxima acción futura en hora de Lima. Guarda. Comprueba que aparece en **Centro de pendientes** y márcala completada cuando simules haberla hecho.
4. **Programa visita.** Pulsa **Programar visita**. Usa modalidad **Presencial**, una fecha y hora futura de Lima, y estado **Solicitada**. Guarda y luego edita a **Confirmada**. Cuando llegue la hora, cámbiala a **Realizada** y escribe “DEMO: cliente interesado; solicita negociar precio”. El sistema rechaza marcar como realizada una visita cuya hora todavía no llegó.
5. **Registra negociación.** Vuelve al CRM, elige etapa **Negociación**, tipo **Nota**, y detalle “DEMO: se acuerda precio final de 335000 soles”. Guarda.
6. **Solicita reserva.** En la consulta, pulsa **Solicitar reserva**. Escribe `10000` en monto de reserva y asigna Alexa si el formulario administrativo solicita vendedor. Guarda. El monto se registra en soles porque esa es la moneda del inmueble.
7. **Aprueba como administrador.** Abre la reserva, elige **Aprobada** y registra el motivo “DEMO: solicitud revisada para práctica”. Verifica que inmueble y consulta pasan a **Reservado**.
8. **Registra el cierre como administrador.** Desde la reserva aprobada, abre la opción de cierre. Escribe `335000` como monto final, usa una fecha válida igual o posterior a la de la reserva y no futura, y observaciones “DEMO: cierre ficticio de práctica”. Confirma una sola vez.
9. **Comprueba los resultados.** La propiedad debe quedar **Vendido**, la consulta **Cerrado**, la reserva **Cerrada** y la oportunidad **Ganada**, con monto 335000 y probabilidad 100 %. Revisa el historial y filtra Reportes por la fecha de tu cierre. El inmueble debe desaparecer del portal.

En precios y montos utiliza enteros sin separadores: escribe `350000`, no `350.000` ni `350000.50`. El área admite decimales. El máximo de precio de inmueble es 5000000 en la moneda elegida.

## 3. Evidencia y repetición técnica

La ejecución `demo_20261028_005521_1a0b97` tiene sus evidencias en la carpeta del mismo nombre dentro de `docs/integration`:

- `resultado.json`: identificadores y las 25 comprobaciones aprobadas.
- `comprobaciones.json`: listado de resultados.
- `estado.json`: estado final de los registros, incluidas las tres cuentas temporales desactivadas. Es una captura de esta ejecución; no se actualiza al practicar después.

Se verificaron, entre otras reglas, los precios inválidos, la separación entre vendedores, la privacidad del PDF, el bloqueo de visitas realizadas antes de tiempo, los permisos de aprobación y cierre, el rechazo de duplicados y la sincronización de estados.

Para repetir desde PowerShell 7 con Apache y MySQL activos y la base migrada:

```powershell
Set-Location C:\xampp\htdocs\inmova-ai
& ./tests/integration_full.ps1
```

Cada ejecución crea dos inmuebles nuevos y conserva los casos DEMO en la base local. Ejecuta esta prueba únicamente en un entorno de desarrollo donde quieras conservar datos de demostración. La imagen y el PDF de muestra ya están preparados; pueden regenerarse con `tests/create_integration_assets.py` usando Python con Pillow y ReportLab.

Las dos ejecuciones iniciales se interrumpieron al ajustar el cliente de pruebas (reutilización de archivo multipart y conversión del tipo de cabecera HTTP). Sus propiedades incompletas quedaron desactivadas y fuera del portal; sus cuentas temporales también quedaron desactivadas. La ejecución identificada arriba completó el recorrido sin errores.
