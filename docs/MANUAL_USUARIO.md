# Manual de usuario — INMOVA AI

**Versión:** 1.0 · **Fecha:** 30 de octubre de 2026  
**Dirigido a:** administradores, vendedores, instructores y participantes.  
Describe las pantallas y reglas implementadas en esta versión del proyecto.

## Índice

1. [Acceso y navegación](#1-acceso-y-navegación)
2. [Usuarios y permisos](#2-usuarios-y-permisos)
3. [Guía rápida del flujo comercial](#3-guía-rápida-del-flujo-comercial)
4. [Propiedades](#4-propiedades)
5. [Documentos privados](#5-documentos-privados)
6. [Portal y consultas de clientes](#6-portal-y-consultas-de-clientes)
7. [Consultas CRM](#7-consultas-crm)
8. [Oportunidades](#8-oportunidades)
9. [Agenda de visitas](#9-agenda-de-visitas)
10. [Reservas y cambios de estado](#10-reservas-y-cambios-de-estado)
11. [Cierres](#11-cierres)
12. [Pendientes y reportes](#12-pendientes-y-reportes)
13. [Asistente inmobiliario](#13-asistente-inmobiliario)
14. [Academia y simulador](#14-academia-y-simulador)
15. [Problemas frecuentes](#15-problemas-frecuentes)
16. [Rutina de trabajo](#16-rutina-de-trabajo)

## 1. Acceso y navegación

En la instalación local, abre `http://localhost/inmova-ai/public/login`.
Si utilizas otro equipo o un servidor publicado, solicita la dirección al administrador:
`localhost` solo corresponde al equipo donde está instalado el sistema.

1. Introduce tu correo y contraseña.
2. Pulsa **Ingresar a mi cuenta**.
3. Si la cuenta es nueva o se restableció su contraseña, completa el cambio solicitado.
4. Al ingresar, utiliza el menú lateral para abrir los módulos autorizados.
5. En un celular, pulsa el botón de menú para mostrar las opciones.

El panel **Vista general** presenta accesos e indicadores según tu rol. Para actualizar
tu contraseña, abre el menú de tu usuario en la esquina superior derecha y selecciona
**Mi contraseña**. Para salir, abre ese mismo menú y pulsa **Cerrar sesión**.

**Importante:** los números de la columna **N.º** indican la posición de una fila en
el listado actual. Pueden cambiar al filtrar. La referencia **#** del detalle identifica
el registro y debe utilizarse al pedir soporte.

## 2. Usuarios y permisos

| Perfil | Uso principal |
| --- | --- |
| Superadministrador / Administrador | Gestión comercial, usuarios, aprobación de reservas, registro de cierres y configuración de IA. |
| Vendedor | Propiedades propias, consultas asignadas, oportunidades, visitas y reservas a su nombre. No aprueba reservas ni registra cierres. |
| Instructor | Gestión de contenido formativo según permisos, aprendizaje y simulador. |
| Practicante | Formación, certificados y simulador. |
| Cliente u otro usuario autenticado | Funciones de aprendizaje y accesos habilitados para su cuenta; sin gestión comercial. |
| Visitante sin sesión | Catálogo público, consulta sobre inmuebles y asistente público. |

El menú y el alcance de los registros dependen del rol. Un vendedor no ve todo el
inventario privado ni todas las consultas del equipo.

### Crear una cuenta — administrador

1. Abre **Usuarios y accesos** y la opción para crear un usuario.
2. Completa nombres, apellidos y correo de acceso; el teléfono es opcional.
3. Selecciona el rol y el estado **Activo**.
4. Escribe y confirma una contraseña inicial de 10 a 72 bytes con mayúscula,
   minúscula y número. Evita caracteres multibyte si necesitas calcular ese límite.
5. Pulsa **Guardar usuario** y entrega las credenciales por un medio privado.

El usuario deberá cambiar la contraseña inicial. Desactivar una cuenta conserva su
historial; no reasigna automáticamente sus consultas o visitas. Cambiar sus accesos
puede finalizar las sesiones que ya tenía abiertas. La pantalla de acceso no ofrece
recuperación automática por correo: solicita ayuda al administrador si no puedes entrar.

## 3. Guía rápida del flujo comercial

Ejemplo ficticio: un departamento en Santa Anita por **PEN 350 000**.

| Paso | Responsable | Acción | Resultado esperado |
| --- | --- | --- | --- |
| 1 | Vendedor o administrador | Crear la propiedad, dejarla disponible y publicarla. | Ficha visible en el portal. |
| 2 | Visitante | Enviar una consulta desde la ficha. | Consulta nueva en CRM. |
| 3 | Administrador | Asignar vendedor en CRM. | El vendedor puede atenderla. |
| 4 | Vendedor | Contactar, registrar seguimiento y confirmar el inmueble de trabajo. | Historial y siguiente acción registrados. |
| 5 | Vendedor | Programar y gestionar una visita. | Agenda actualizada con estado y resultado. |
| 6 | Vendedor | Solicitar una reserva, por ejemplo PEN 5 000. | Reserva pendiente; todavía no bloquea el inmueble. |
| 7 | Administrador | Aprobar la reserva indicando el motivo. | Propiedad y consulta pasan a Reservado. |
| 8 | Administrador | Registrar el cierre con monto, fecha y observaciones. | Operación cerrada, inmueble vendido o alquilado y retirado del portal. |

Este es un recorrido recomendado de trabajo. La reserva, su aprobación y el cierre
son operaciones distintas: no se sustituyen cambiando manualmente la etapa del CRM.
Los registros de reserva y cierre **no procesan cobros ni generan contratos**.

## 4. Propiedades

### Registrar un inmueble

1. Abre **Propiedades → Nueva propiedad**.
2. Completa **Título**, **Tipo de propiedad**, **Modalidad**, **Precio** y **Moneda**.
3. Para un inmueble nuevo sin operación registrada, utiliza **Disponible**.
4. Agrega área, dormitorios, baños y descripción cuando corresponda.
5. Si introduces una ubicación, completa departamento, provincia y distrito.
   La dirección es opcional.
6. Selecciona las fotografías.
7. Marca **Publicar en el portal público** si deseas mostrar la ficha a visitantes.
8. Pulsa **Guardar propiedad** y comprueba el mensaje de confirmación.

El precio admite enteros entre **1 y 5 000 000**, en PEN o USD. Las fotografías
admiten JPG, PNG o WebP: hasta diez por inmueble, dos MB por archivo y 24 megapíxeles.
La primera será la principal; al editar puedes cambiarla o retirar imágenes.
Si un formulario es rechazado, vuelve a seleccionar los archivos antes de enviarlo.

No fuerces **Reservado**, **Vendido** o **Alquilado** para simular una operación:
esos estados deben corresponder con las reservas y cierres del sistema.

### Buscar y editar

Combina título, modalidad, disponibilidad, publicación, moneda y precio mínimo/máximo.
Si indicas un precio, selecciona moneda. Pulsa **Buscar propiedades**; **Limpiar**
restablece los filtros. Los extremos del rango están incluidos.

Pulsa **Editar** junto al inmueble, modifica los datos permitidos y guarda.
En celular, desliza horizontalmente la tabla para alcanzar las acciones de la derecha.
Los listados pueden limitarse a cien registros: filtra si no encuentras un inmueble.

### Eliminar del listado

**Eliminar** retira el inmueble del listado activo y del portal; no equivale a borrar
su historial comercial de la base de datos.

| Situación | Qué hacer |
| --- | --- |
| Sin reserva aprobada ni operación cerrada | Pulsa **Eliminar** y confirma. |
| Con reserva aprobada | Abre **Ver reservas**. Un administrador debe cancelar la reserva con motivo; después vuelve a Propiedades y elimina el inmueble. |
| Con operación cerrada | La eliminación está bloqueada para conservar la trazabilidad. Revisa el detalle del cierre con el administrador. |

Esta versión **no incluye botones para archivar propiedades cerradas ni anular cierres**.
Mostrar **Modificar estado** en una reserva cerrada no habilita su cancelación.
No es necesario retirar un inmueble vendido del portal manualmente: el cierre ya lo retira.

## 5. Documentos privados

1. En la fila de una propiedad, pulsa **Documentos**.
2. Introduce título, categoría y un archivo PDF.
3. Pulsa **Guardar documento**.
4. Usa **Descargar PDF** para obtener un documento activo.
5. Usa **Retirar** para impedir su descarga; puedes recuperarlo desde **Retirados → Restaurar**.

Se admite un PDF por carga, hasta dos MB y veinte documentos activos por inmueble.
Retirar conserva el archivo. El historial registra la actividad.
Solo administradores y el vendedor propietario del inmueble pueden gestionarlos.
No se publican en el portal ni se envían al asistente IA.

## 6. Portal y consultas de clientes

Abre **Explorar portal** o **Ver portal**. Busca inmuebles y abre la ficha de interés.
Para solicitar información:

1. Localiza **Consultar por este inmueble**.
2. Completa nombre, mensaje y al menos un medio de contacto: correo o teléfono.
3. Marca la autorización de uso de datos.
4. Envía el formulario y espera la confirmación de recepción.

La consulta se guarda en CRM para el equipo. El envío no confirma una visita, reserva
ni pago. Si el inmueble deja de estar publicado o disponible para consulta pública,
su ficha puede dejar de mostrarse.

## 7. Consultas CRM

1. Abre **Consultas CRM** y entra al detalle de la consulta.
2. Revisa contacto, mensaje recibido y origen.
3. Si eres administrador, selecciona **Asignar vendedor** en Gestión comercial.
4. Actualiza la etapa permitida y el tipo de seguimiento.
5. En **Agregar seguimiento**, escribe lo conversado y los acuerdos.
6. Si hay una tarea futura, completa **Próxima acción (hora de Lima)**.
7. Pulsa **Guardar cambios** y revisa el historial.

Las etapas son Nuevo, Contactado, Visita programada, Negociación, Reservado, Cerrado
y Perdido. **Reservado** se obtiene mediante aprobación de reserva y **Cerrado**
mediante registro de cierre. No puedes usar el CRM para saltar esos controles.

### Elegir el inmueble de trabajo

En **Inmueble para visitas y reservas**, selecciona **Inmueble de trabajo** y pulsa
**Guardar inmueble**. Esto permite trabajar una consulta general recibida del asistente.

Si hay visitas activas o reservas pendientes/aprobadas, resuélvelas antes de cambiarlo.
Las consultas finalizadas conservan el inmueble y los registros históricos no se
reescriben al seleccionar otro. Las alternativas de una oportunidad no sustituyen
automáticamente esta selección.

## 8. Oportunidades

Desde el detalle CRM, utiliza la sección de oportunidades para crear una alternativa
comercial. Selecciona inmueble, monto estimado, probabilidad, estado y notas;
pulsa **Guardar oportunidad**. Consulta los registros desde **Oportunidades**.

El monto es entero entre 1 y 5 000 000; en alquiler representa renta mensual.
La moneda procede del inmueble. La probabilidad va de 0 a 100.
Puedes trabajar estados Abierta, Perdida o Cancelada. Una oportunidad **Ganada**
se registra mediante el cierre, adopta los datos finales correspondientes y no es editable.
Una estimación o probabilidad no acredita ingresos recibidos.

## 9. Agenda de visitas

1. En una consulta abierta con inmueble seleccionado, pulsa **Programar visita**.
2. Indica fecha y hora de Lima, vendedor responsable y modalidad Presencial o Virtual.
3. Selecciona Solicitada o Confirmada y agrega observaciones.
4. Pulsa **Guardar visita**.
5. Después, abre **Agenda de visitas → Gestionar** para actualizarla.
6. Al realizarse, elige **Realizada**, registra el resultado y guarda.
   Si no tendrá lugar, utiliza **Cancelada** según corresponda.

No puedes marcar una visita como realizada antes de su hora. El resultado es
obligatorio para una visita realizada. Se bloquean citas del mismo vendedor a la
misma hora; el sistema no calcula duración ni envía notificaciones automáticas.
Una consulta cerrada no admite nuevas visitas ni reactivación de visitas históricas.

## 10. Reservas y cambios de estado

### Solicitar

En CRM, abre la consulta y pulsa **Solicitar reserva**. Completa el monto y vendedor,
y envía la solicitud. El vendedor opera a su propio nombre. El monto debe ser entero,
positivo y no superar el precio del inmueble ni 5 000 000; se conserva su moneda.

La solicitud queda **Pendiente**. No bloquea el inmueble hasta su aprobación.
No se admite otra solicitud activa para la misma consulta.

### Modificar estado

1. Abre **Reservas**, o **Propiedades → Ver reservas** para ver las del inmueble.
2. Pulsa **Modificar estado** junto a la reserva.
3. Si tienes permiso y hay una transición disponible, selecciona la acción.
4. Escribe el **Motivo / observación**.
5. Pulsa **Aplicar cambio**, confirma y comprueba el nuevo estado.

| Estado actual | Acciones permitidas |
| --- | --- |
| Pendiente | Administrador: Aprobar, Rechazar o Cancelar. Vendedor responsable: Cancelar. |
| Aprobada | Solo administrador: Cancelar. Para completar la operación, utiliza Registrar cierre de operación. |
| Cancelada / Rechazada / Cerrada | No admite cambios de estado. El botón abre una explicación. |

Al **aprobar**, el inmueble y la consulta pasan a Reservado. No puede aprobarse una
segunda reserva del mismo inmueble mientras exista otra aprobada.
Al **cancelar una aprobada**, se libera el inmueble si sigue reservado y se restaura
la etapa previa de la consulta cuando corresponde. El motivo queda en el historial CRM.
Una reserva finalizada no se reactiva; si procede, se debe iniciar una nueva solicitud.

## 11. Cierres

Esta operación está reservada a administradores y requiere una **reserva aprobada**.

1. Abre **Reservas → Ver detalle** de la reserva aprobada.
2. Pulsa **Registrar cierre de operación**.
3. Introduce el precio final de venta o la renta mensual pactada, según la modalidad.
4. Revisa fecha de cierre y completa las observaciones.
5. Comprueba que inmueble, cliente, vendedor y moneda sean correctos.
6. Pulsa **Confirmar cierre** y acepta la confirmación únicamente si los datos son correctos.

El monto final no puede ser menor que la reserva. La fecha no puede ser futura ni
anterior a la creación de la reserva. Al cerrar, la reserva y consulta quedan cerradas;
el inmueble pasa a Vendido o Alquilado y se retira del portal. Otras solicitudes
pendientes del inmueble se rechazan. Puedes consultar la operación desde **Cierres**.

**Revisa antes de confirmar:** esta versión no ofrece edición, reapertura ni anulación
de cierres. Un cierre tampoco acredita cobros ni genera contratos. Si detectas un error,
conserva su referencia y solicita revisión al responsable; no cambies otros estados
para intentar ocultarlo.

## 12. Pendientes y reportes

### Pendientes

El **Centro de pendientes** reúne seguimientos, visitas y reservas por atender.
Para crear un seguimiento programado, completa detalle y próxima acción desde CRM.
En Pendientes, filtra por la categoría disponible, abre la consulta y utiliza
**Marcar completado** después de atenderla. Cuando esté permitido, **Reabrir pendiente**
lo devuelve al trabajo activo. El historial se conserva.

### Reportes

1. Abre **Reportes** y selecciona **Desde** y **Hasta**.
2. Pulsa **Consultar**.
3. Revisa los resultados, separando monedas y ventas de alquileres.
4. Si está disponible, pulsa **Exportar resumen CSV**.

El rango incluye ambas fechas y admite hasta 366 días; inicialmente muestra el mes
actual hasta hoy. El inventario refleja el estado actual independientemente del rango.
Consultas y reservas se agrupan por creación; visitas por fecha programada y cierres
por fecha de cierre. Los estados son actuales: no es una reconstrucción histórica.
Los registros DEMO conservados de pruebas también pueden afectar los resultados.

## 13. Asistente inmobiliario

### Buscar sin IA

En **Asistente IA → Búsqueda guiada**, escribe por ejemplo **Santa Anita**,
selecciona tipo, modalidad, moneda y presupuesto, y pulsa **Buscar opciones**.
Si indicas presupuesto, selecciona moneda. Se muestran hasta seis coincidencias
de inmuebles publicados y disponibles; abre **Ver ficha** para conocer el detalle.

### Conversar con IA

Si está habilitada, escribe tu consulta en **Tu mensaje**, acepta el envío a OpenAI
y pulsa **Consultar con IA**. Ejemplo: «Busco un departamento en Santa Anita,
hasta 350000 soles». No compartas claves ni documentos privados en el chat.

La disponibilidad de este servicio depende de una configuración válida y del proveedor.
Si falla, utiliza la búsqueda guiada. El chat orienta; no confirma operaciones comerciales.

### Solicitar contacto

En **Quiero que me contacten**, elige un inmueble o **Consulta general**, introduce
nombre, consulta y al menos correo o teléfono. Acepta la autorización y pulsa
**Enviar consulta al equipo**. La consulta y el contexto autorizado se comparten
con el CRM. Si ya enviaste la conversación, utiliza **Nueva conversación** para empezar otra.

Un administrador dispone de **Configurar IA**. Guardar esa configuración no prueba
automáticamente la conexión; requiere revisión por el responsable. No publiques claves
en capturas o solicitudes de soporte. Los límites de solicitudes no son límites monetarios.

## 14. Academia y simulador

### Estudiar y obtener certificados

1. Abre **Academia**, elige un curso y pulsa **Inscribirme al curso**.
2. Abre sus lecciones y registra su finalización con el control de la lección.
3. Revisa el progreso e ingresa a **Ir al examen final** cuando esté habilitado.
4. Completa el examen y consulta el resultado.
5. Completar las lecciones y aprobar el examen permite obtener el certificado
   conforme a los requisitos del curso. Revisa **Mis certificados**.

Administradores e instructores autorizados disponen de gestión de cursos, lecciones
y exámenes. Usa **Gestionar contenido** cuando aparezca; los permisos dependen del curso.

### Practicar ventas

En **Simulador de ventas**, selecciona escenario y modalidad y pulsa **Comenzar práctica**.
La modalidad **Guiada** funciona sin IA, utiliza un guion fijo y termina con
autoevaluación sin nota automática. La modalidad IA requiere configuración y consentimiento.
Utiliza datos ficticios: las prácticas no modifican el CRM ni el inventario real.

Se admiten hasta ocho respuestas y al menos tres para finalizar. Los supervisores
autorizados pueden revisar el historial del equipo. La modalidad IA puede consumir
solicitudes y comparte límites con el asistente.

## 15. Problemas frecuentes

| Mensaje o situación | Acción recomendada |
| --- | --- |
| Correo o contraseña incorrectos | Revisa el correo y solicita al administrador verificar cuenta, estado y contraseña. |
| No tienes permiso | Confirma tu rol y la asignación del registro; no uses la cuenta de otra persona. |
| No veo una propiedad o consulta | Limpia filtros y revisa el alcance de tu rol. El vendedor ve sus registros autorizados. |
| No puedo eliminar: reserva aprobada | Ver reservas → Modificar estado. El administrador cancela con motivo y luego elimina la propiedad del listado. |
| No puedo eliminar: operación cerrada | Es una protección del historial. No hay anulación o archivado de cierres implementado en esta versión. |
| Modificar estado solo muestra una explicación | La reserva está finalizada o tu rol no permite esa acción. El botón siempre es visible, pero no amplía los permisos. |
| No puedo cambiar de inmueble en CRM | Resuelve visitas activas y reservas pendientes/aprobadas; una consulta finalizada conserva su inmueble. |
| No puedo aprobar otra reserva | Comprueba disponibilidad y si ya existe una aprobada para el inmueble. |
| No puedo marcar visita realizada | Espera a su hora de Lima y completa el resultado. |
| Error de precio | Utiliza enteros del rango admitido y selecciona moneda. |
| OpenAI rechaza la clave o no responde | Utiliza Búsqueda guiada y solicita revisión de Configurar IA al administrador. |
| Sesión expirada | Recarga la página, verifica el acceso y vuelve a completar el formulario. |
| Error con referencia de soporte | Anota referencia, hora, módulo y pasos. Revisa el estado del registro antes de repetir para evitar duplicados. |

Al pedir soporte, comparte la referencia del registro y el resultado esperado.
Oculta contraseñas, claves y datos personales que no sean necesarios.

## 16. Rutina de trabajo

**Al iniciar el día:** revisa Vista general y Pendientes; atiende consultas nuevas,
seguimientos vencidos y visitas del día. El administrador debe revisar también
consultas sin asignar y solicitudes de reserva.

**Después de cada atención:** registra el resultado en CRM y programa la siguiente
acción. Actualiza visitas cuando corresponda y comprueba la confirmación de guardado.

**Antes de cerrar una venta o alquiler:** comprueba inmueble, cliente, vendedor,
moneda, reserva aprobada, monto, fecha y observaciones. El cierre es definitivo en esta versión.

**Al terminar:** revisa tareas pendientes y cierra sesión desde el menú de tu usuario.
