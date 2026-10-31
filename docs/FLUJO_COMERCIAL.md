# Flujo comercial coherente

## Selección del inmueble de trabajo

En **CRM → Gestionar consulta → Inmueble para visitas y reservas**, selecciona el inmueble que el cliente desea trabajar y pulsa **Guardar inmueble**. Una consulta general sin inmueble puede continuar por esta vía. Las oportunidades siguen representando alternativas; crear una oportunidad no sustituye automáticamente el inmueble del CRM.

Los administradores pueden seleccionar inmuebles elegibles del equipo. Los vendedores solo modifican sus consultas asignadas y pueden elegir inmuebles publicados o propios, activos, disponibles o reservados y con catálogos activos. Solicitar una reserva sigue requiriendo disponibilidad Disponible; un inmueble Reservado puede visitarse, pero no recibir otra reserva desde este formulario.

## Cambios protegidos

- Una visita Solicitada o Confirmada impide cambiar el inmueble: primero cancélala o registra su resultado cuando corresponda.
- Una reserva Pendiente o Aprobada impide cambiar el inmueble. Debe cancelarse por el flujo autorizado.
- Una consulta Cerrada o Perdida no cambia de inmueble. Un cierre registrado conserva su operación.
- Las visitas y reservas históricas mantienen su inmueble original; CRM muestra su título para evitar confundirlo con la selección actual.
- Al elegir otro inmueble, si la consulta estaba en Visita programada y ya no tiene visitas activas, pasa a Contactado. Otras etapas comerciales no avanzan automáticamente.
- Reenviar la misma selección no duplica el historial.
- Una reserva pendiente bloquea el cambio de modalidad de su inmueble, evitando convertir una solicitud de venta en alquiler antes de aprobarla. Las reservas aprobadas ya protegían esta modalidad.
- La aprobación y el cierre comprueban que consulta y reserva correspondan al mismo inmueble. Una solicitud concurrente que leyó una selección anterior debe recargarse; no crea una reserva sobre el inmueble equivocado.

Reservado sigue dependiendo de la aprobación administrativa y Cerrado del cierre registrado. No se exige una visita para todas las reservas ni se convierte automáticamente una visita realizada en una negociación: esas decisiones comerciales mantienen su funcionamiento previo.

## Verificación del 28/10/2026

125 comprobaciones satisfactorias en las suites `commercial_flow`, `business_audit`, `reservations`, `opportunities`, `regressions`, `concurrency` y `tasks`. El nuevo recorrido de consulta general se verificó con registros propios temporales, desde la selección de inmueble hasta el cierre y la resolución de oportunidades. La prueba concurrente comprueba expresamente el cambio de inmueble durante una solicitud de reserva.

E2E HTTP: 39 comprobaciones satisfactorias en `docs/integration/demo_20261028_195642_1e5f21/resultado.json`. Se comprobó la selección desde el formulario, el rechazo a otro vendedor, los bloqueos por visita/reserva y el recorrido hasta cierre y reportes. La ejecución conserva casos DEMO y desactiva sus cuentas temporales al terminar. No se reparan ni reasignan registros históricos del usuario automáticamente. No se hicieron llamadas al proveedor IA ni revisión visual en navegador.
