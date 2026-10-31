"""Genera el manual PDF y su versión Markdown desde el mismo contenido revisado."""
from pathlib import Path
from xml.sax.saxutils import escape
from reportlab.pdfgen import canvas
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'output/pdf/manual-usuario-inmova-ai.pdf'
EDITABLE = ROOT / 'docs/MANUAL_USUARIO.md'
BASE = 'http://localhost/inmova-ai/public'
DATE = '27 de octubre de 2026'

# Cada sección ocupa una página para facilitar la consulta y la impresión.
# p: párrafo; h: subtítulo; steps: pasos; table: tabla; note: recordatorio; link: acceso.
SECTIONS = [
('01', 'Ingresar y empezar', 'Para todos los usuarios con cuenta', [
 ('p', 'INMOVA AI reúne el inventario inmobiliario, la atención comercial y la capacitación del equipo. Los módulos disponibles dependen del rol de tu cuenta.'),
 ('h', 'Primer ingreso'),
 ('steps', [
  'En el equipo donde está instalado el proyecto, comprueba que Apache y MySQL estén iniciados en XAMPP. Si no administras el equipo, solicita apoyo al responsable.',
  'Abre la página de acceso e ingresa el correo y la contraseña que te entregó el administrador. Pulsa el botón de ingreso.',
  'Si la cuenta es nueva o se restableció la contraseña, completa el cambio obligatorio antes de entrar a los módulos.',
  'Revisa tu nombre y rol en la barra superior. Entra al panel pulsando INMOVA AI. Usa la barra para abrir cada módulo.'
 ]),
 ('link', ('Abrir acceso al sistema', '/login')),
 ('h', 'Mi contraseña y cierre de sesión'),
 ('p', 'En Mi contraseña, escribe tu contraseña actual y confirma la nueva. Debe contener mayúscula, minúscula y número, y tener entre 10 y 72 bytes; para evitar diferencias con caracteres especiales, usa una contraseña larga de caracteres habituales. Al terminar tu trabajo, pulsa Salir.'),
 ('note', 'Si olvidaste la contraseña, pide al administrador que la restablezca desde Usuarios. No hay recuperación por correo en esta versión. Este manual no contiene credenciales.'),
 ('p', 'La dirección localhost funciona en el equipo que aloja el sistema. Para entrar desde otro equipo se necesita una dirección de red o un despliegue preparado por el responsable técnico.')
]),
('02', 'Roles, panel y navegación', 'Consulta qué puede hacer cada perfil', [
 ('table', (['Perfil', 'Acceso principal'], [
  ['Superadministrador', 'Supervisa la operación, usuarios y cursos; puede gestionar el rol superadministrador.'],
  ['Administrador', 'Gestiona el equipo comercial, aprueba reservas, registra cierres, consulta reportes y administra usuarios y cursos. No gestiona superadministradores.'],
  ['Vendedor', 'Gestiona propiedades creadas por él y consultas asignadas. Opera oportunidades, visitas y reservas según sus permisos. No aprueba reservas ni registra cierres.'],
  ['Instructor', 'Gestiona sus cursos y evaluaciones; revisa prácticas del equipo en el simulador. No tiene acceso al CRM comercial.'],
  ['Practicante', 'Estudia en Academia y practica en el simulador. No gestiona el flujo comercial.'],
  ['Cliente u otro usuario', 'Accede a Academia con sesión y a las funciones públicas; sin acceso al CRM comercial.'],
  ['Visitante sin sesión', 'Consulta el portal y el asistente, y envía consultas de contacto.']
 ])),
 ('h', 'Qué revisar al comenzar el día'),
 ('steps', ['Abre el panel y revisa los indicadores del mes.', 'En Pendientes, atiende seguimientos vencidos, visitas y reservas.', 'En CRM, revisa consultas nuevas o asignadas. Un administrador también debe asignar las consultas sin vendedor.', 'Antes de salir, registra los resultados y las próximas acciones.']),
 ('note', 'Los vendedores tienen tres ámbitos distintos: propiedades que crearon, consultas actualmente asignadas y visitas/reservas a su nombre. Cambiar el vendedor del CRM no cambia automáticamente el responsable de visitas o reservas.'),
 ('link', ('Abrir panel principal', '/dashboard'))
]),
('03', 'Registrar y editar propiedades', 'Administrador y vendedor propietario', [
 ('steps', ['En Propiedades, pulsa Nueva propiedad.', 'Completa el título, tipo, modalidad, precio y moneda. Para una nueva oferta utiliza Disponible.', 'Agrega características y descripción. Si indicas ubicación, completa departamento, provincia y distrito.', 'Selecciona fotografías y, si corresponde, marca Publicar en el portal público. Pulsa Guardar propiedad.', 'Busca el título en el listado. Usa Editar para actualizar el inmueble, elegir imagen principal o retirar fotografías.']),
 ('table', (['Campo', 'Ejemplo de práctica'], [
  ['Título', 'DEMO - Departamento familiar en Miraflores'],
  ['Tipo / Modalidad', 'Departamento / modalidad de venta de departamentos'],
  ['Precio / Moneda', '350000 / Soles (PEN)'],
  ['Área / Dormitorios / Baños', '95.50 / 3 / 2'],
  ['Ubicación', 'Lima / Lima / Miraflores'],
  ['Dirección y descripción', 'Usa datos ficticios e identifica el ejemplo como DEMO.']
 ])),
 ('note', 'El precio admite solo enteros entre 1 y 5000000 tanto en PEN como en USD. Escribe 350000 sin puntos ni comas. El área sí admite decimales. Que el listado muestre .00 no significa que se puedan ingresar centavos.'),
 ('p', 'Fotografías: JPG, PNG o WebP, hasta 10 por inmueble y 2 MB por imagen; máximo 24 megapíxeles y 8000 píxeles por lado. Si el formulario falla, vuelve a seleccionarlas. El servidor puede limitar el tamaño total de la carga.'),
 ('p', 'Eliminar retira la propiedad del listado y de la publicación, conservando el registro. No se permite si tiene una reserva aprobada o una operación cerrada. Usa Reservas y Cierres para registrar esas operaciones y mantener el historial coherente.'),
 ('link', ('Abrir Propiedades', '/properties'))
]),
('04', 'Documentos privados del inmueble', 'Administrador y vendedor que creó la propiedad', [
 ('steps', ['En Propiedades, ubica el inmueble y pulsa Documentos. También puedes entrar desde Editar, en Documentos privados.', 'En Agregar documento PDF, escribe un título claro y selecciona categoría: plano, contrato, ficha técnica u otro.', 'Selecciona un archivo PDF de hasta 2 MB y pulsa Guardar documento. Se admite un archivo por carga y hasta 20 documentos activos por inmueble.', 'Comprueba que aparece en Documentos activos. Pulsa Descargar PDF para consultarlo.', 'Si deja de ser vigente, pulsa Retirar y confirma. Para recuperarlo, entra en Retirados y pulsa Restaurar.']),
 ('table', (['Campo', 'Ejemplo'], [
  ['Título', 'DEMO - Ficha técnica del departamento'],
  ['Categoría', 'Ficha técnica'],
  ['Archivo', 'Un PDF de muestra sin información personal real.']
 ])),
 ('h', 'Acceso y trazabilidad'),
 ('p', 'Estos documentos no aparecen en el portal ni se envían al asistente IA. Un vendedor asignado a una consulta no recibe por esa asignación acceso a los documentos de un inmueble creado por otra persona.'),
 ('p', 'La sección Actividad de documentos muestra las cargas, solicitudes de descarga, retiros y restauraciones. Una solicitud de descarga no acredita que el archivo haya sido leído.'),
 ('note', 'Retirar bloquea la descarga pero conserva el archivo. Restaurar requiere que el archivo siga disponible y que no se supere el límite activo. Desactivar la propiedad conserva sus documentos, pero bloquea el acceso desde este módulo.'),
 ('p', 'Ejemplo existente: la propiedad DEMO #93 conserva su ficha técnica privada después de la venta. Puedes revisarla con tu cuenta de administrador.'),
 ('link', ('Revisar documentos del ejemplo #93', '/properties/documents?property_id=93'))
]),
('05', 'Portal y recepción de consultas', 'Visitantes y equipo comercial', [
 ('h', 'Consultar una propiedad'),
 ('steps', ['Abre Portal público sin necesidad de iniciar sesión.', 'Filtra por título o ubicación, tipo, modalidad, moneda y precio. Selecciona moneda cuando indiques presupuesto.', 'Abre la ficha para revisar características, ubicación, precio y fotografías.']),
 ('h', 'Enviar una consulta'),
 ('steps', ['En la ficha del inmueble, completa nombre, correo o teléfono, y mensaje. Debes indicar al menos un medio de contacto.', 'Marca la autorización de uso de datos para responder la consulta y envía el formulario.', 'Espera la confirmación. El sistema registra el contacto y la consulta en el CRM en etapa Nuevo, inicialmente sin vendedor.']),
 ('table', (['Dato', 'Ejemplo ficticio'], [
  ['Nombre', 'Cliente DEMO - Luis Rojas'],
  ['Correo / Teléfono', 'cliente.demo@example.test / teléfono de prueba'],
  ['Mensaje', 'DEMO: busco un departamento de tres dormitorios y deseo programar una visita.']
 ])),
 ('note', 'El formulario público permite un envío por minuto por sesión. Cada consulta genera un contacto independiente; el sistema no fusiona automáticamente personas repetidas.'),
 ('p', 'Si una ficha dejó de estar disponible, vuelve al catálogo. Una propiedad vendida mediante Cierres se retira del portal. Para revisar una publicación faltante, el administrador debe comprobar su activación, la marca de publicación y los catálogos de tipo y modalidad.'),
 ('link', ('Abrir portal público', '/portal'))
]),
('06', 'CRM y seguimiento de clientes', 'Administrador y vendedor asignado', [
 ('steps', ['En CRM, busca por nombre, correo o teléfono; puedes filtrar por etapa. Abre Gestionar en la consulta.', 'Revisa los datos de contacto, mensaje original y propiedad de interés. Si provino del asistente, también puede incluir la conversación compartida.', 'Como administrador, selecciona un vendedor activo y guarda. Las consultas nuevas necesitan esta asignación para aparecer al vendedor.', 'En Gestión comercial, elige etapa y tipo de seguimiento. Escribe un detalle útil; añade Próxima acción si necesitas atender algo después.', 'Pulsa Guardar cambios. Comprueba que la actividad aparece en Historial de seguimiento.']),
 ('table', (['Etapa', 'Uso habitual'], [
  ['Nuevo / Contactado', 'Consulta recién recibida / primer contacto realizado.'],
  ['Visita programada', 'Existe una visita agendada; al programarla puede actualizarse automáticamente.'],
  ['Negociación', 'Se revisan precio y condiciones con el cliente.'],
  ['Reservado / Cerrado', 'Los módulos Reservas y Cierres registran estas operaciones.'],
  ['Perdido', 'La gestión no continúa; deja la razón en el seguimiento.']
 ])),
 ('h', 'Cómo revisar todo el historial'),
 ('p', 'Abre la consulta y baja a Historial de seguimiento. Cada entrada muestra tipo, autor, fecha, texto y próxima acción cuando existe. También quedan registrados los cambios comerciales de visitas, reservas y cierres. Las secciones superiores enlazan esos expedientes.'),
 ('note', 'Registrar un seguimiento de tipo Mensaje o Correo solo deja constancia de una acción. No envía WhatsApp ni correo. Cambiar una etapa manualmente no crea una reserva ni un cierre.'),
 ('link', ('Abrir CRM', '/crm'))
]),
('07', 'Oportunidades comerciales', 'Administrador y vendedor de la consulta', [
 ('steps', ['Abre una consulta activa en CRM y pulsa Nueva oportunidad.', 'Elige el inmueble elegible. La moneda y el tipo de operación se toman del inmueble.', 'Escribe un monto estimado entero, probabilidad entre 0 y 100, estado y notas. Guarda.', 'En Oportunidades, filtra por cliente/inmueble y estado. Pulsa Gestionar para actualizar la oportunidad.']),
 ('table', (['Campo', 'Ejemplo'], [
  ['Monto estimado', '340000 soles para una posible venta.'],
  ['Probabilidad', '60'],
  ['Estado / Notas', 'Abierta / DEMO: interés confirmado; pendiente de visita.'],
  ['Monto ponderado', '340000 × 60 / 100 = 204000 soles. Es una estimación.']
 ])),
 ('h', 'Estados y reglas'),
 ('p', 'Abierta representa una gestión en curso. Perdida o Cancelada lleva la probabilidad a 0. Ganada se asigna automáticamente al concretar un cierre, con probabilidad 100 y monto final; no se selecciona manualmente ni se edita después.'),
 ('p', 'Solo existe una oportunidad por pareja consulta/inmueble. Para retomar una perdida o cancelada, edita la existente y vuelve a Abierta si sigue siendo elegible. No se puede cambiar su consulta o inmueble una vez creada.'),
 ('p', 'El cierre gana la oportunidad vinculada si existe, pierde las otras abiertas del inmueble y cancela las alternativas abiertas de la consulta ganadora. El sistema no crea oportunidades automáticamente al cerrar.'),
 ('note', 'Los montos abiertos estimados y ponderados no son dinero cobrado. Ventas, rentas mensuales, soles y dólares se presentan por separado. No hay conversión de divisas.'),
 ('link', ('Abrir Oportunidades', '/opportunities'))
]),
('08', 'Centro de pendientes', 'Organiza el trabajo del día', [
 ('p', 'Pendientes reúne próximas acciones del CRM, visitas por regularizar, visitas de los siguientes siete días, reservas pendientes y, para administradores, consultas sin vendedor.'),
 ('steps', ['En CRM, agrega un seguimiento con detalle y una fecha/hora en Próxima acción.', 'Abre Pendientes. Revisa primero los Vencidos y después Por atender hoy.', 'Realiza la acción y márcala completada desde el Centro o desde el historial de la consulta.', 'Si se completó por error, reabre el seguimiento cuando la consulta permita hacerlo.', 'Para reprogramar, completa la acción anterior y crea un nuevo seguimiento con la nueva fecha.']),
 ('table', (['Categoría', 'Qué significa'], [
  ['Vencidos', 'La fecha y hora previstas ya pasaron.'],
  ['Por atender hoy', 'Desde el momento actual hasta antes de medianoche.'],
  ['Después de hoy', 'A partir de la siguiente medianoche.'],
  ['Visitas por regularizar', 'Revisa la visita y registra su estado o resultado real.'],
  ['Reservas pendientes', 'Abre la solicitud y gestiona la decisión con el rol autorizado.']
 ])),
 ('note', 'Todas las horas se interpretan en Lima. La página se actualiza al abrirla o recargarla; no envía avisos de correo, WhatsApp ni notificaciones del navegador.'),
 ('p', 'Completar un pendiente conserva el texto y la fecha original, el responsable y la hora de atención. No cambia por sí mismo la etapa del cliente ni el estado de una visita o reserva.'),
 ('p', 'Los seguimientos de consultas cerradas o perdidas dejan de aparecer en la lista activa. El historial se conserva. La reasignación de una consulta cambia quién puede atender sus seguimientos.'),
 ('link', ('Abrir Pendientes', '/tasks'))
]),
('09', 'Programar y gestionar visitas', 'Administrador y vendedor responsable', [
 ('steps', ['En la consulta del CRM, pulsa Programar visita.', 'El administrador selecciona vendedor activo; el vendedor trabaja a su nombre. Elige modalidad Presencial o Virtual.', 'Indica una fecha y hora futura de Lima y estado inicial Solicitada o Confirmada. Guarda.', 'En Visitas, filtra por día o estado y abre la visita para confirmar, reprogramar o cancelar.', 'Después de la hora programada, registra Realizada y describe el resultado. Si el cliente no asistió o cambió de planes, registra lo ocurrido y el estado apropiado.']),
 ('table', (['Campo', 'Ejemplo'], [
  ['Modalidad', 'Presencial'],
  ['Fecha y hora', 'Un día y hora futuros de Lima; por ejemplo, mañana a las 10:00.'],
  ['Estado inicial', 'Solicitada; después Confirmada.'],
  ['Resultado al realizarse', 'DEMO: revisó la distribución y solicitó negociar el precio.']
 ])),
 ('h', 'Reglas que debes tener en cuenta'),
 ('p', 'No se puede marcar una visita futura como realizada. Tampoco se permiten dos visitas activas del mismo vendedor a la misma hora exacta. Este control no calcula la duración ni el tiempo de traslado.'),
 ('p', 'Al agendar una consulta Nueva o Contactada puede pasar a Visita programada. Cancelar la visita no revierte automáticamente esa etapa; revisa el CRM y registra el siguiente paso.'),
 ('note', 'Si reasignas la consulta, revisa también el responsable de la visita: no se cambia automáticamente. El sistema registra cambios en el historial, pero no envía invitaciones ni avisos externos.'),
 ('link', ('Abrir Visitas', '/visits'))
]),
('10', 'Solicitar y aprobar reservas', 'Vendedor solicita; administrador decide', [
 ('steps', ['Desde la consulta, pulsa Solicitar reserva.', 'Selecciona vendedor si el formulario administrativo lo solicita. Escribe el monto entero en la moneda del inmueble y guarda.', 'En Reservas, abre Gestionar y revisa el inmueble, cliente, monto y estado.', 'Como administrador, aprueba, rechaza o cancela la solicitud, indicando el motivo. Un vendedor solo puede cancelar sus propias pendientes.', 'Después de aprobar, comprueba que propiedad y consulta quedaron en Reservado.']),
 ('table', (['Estado', 'Acción y efecto'], [
  ['Pendiente', 'Solicitud por revisar. No equivale a una aprobación.'],
  ['Aprobada', 'Reserva el inmueble y actualiza la consulta. Permite preparar el cierre.'],
  ['Rechazada / Cancelada', 'Estados finales. Si se retoma el interés, crea una nueva solicitud.'],
  ['Cerrada', 'La operación se concretó desde Cierres.']
 ])),
 ('p', 'El monto va de 1 a 5000000 y no puede superar el precio del inmueble. Ejemplo: precio 350000 PEN y monto de reserva 10000 PEN.'),
 ('p', 'Una consulta no admite dos reservas pendientes o aprobadas al mismo tiempo. Distintas consultas pueden tener solicitudes pendientes del mismo inmueble, pero solo una puede aprobarse mientras esté reservado.'),
 ('p', 'Cancelar una aprobada libera la propiedad si sigue reservada y restaura la etapa anterior de la consulta si todavía está en Reservado. Reasignar la consulta no cambia al vendedor histórico de la reserva.'),
 ('note', 'El módulo registra la solicitud y la decisión; no procesa ni confirma pagos. Una reserva aprobada bloquea eliminar la propiedad, cambiar moneda/disponibilidad y reducir su precio por debajo del monto reservado.'),
 ('link', ('Abrir Reservas', '/reservations'))
]),
('11', 'Registrar el cierre de operación', 'Solo administrador o superadministrador', [
 ('steps', ['Abre una reserva Aprobada y verifica cliente, inmueble, moneda y vendedor.', 'Pulsa Registrar cierre de operación. La modalidad del inmueble determina si es venta o alquiler.', 'Escribe el precio final de venta o la renta mensual. Usa un entero entre 1 y 5000000, no menor al monto de reserva.', 'Indica una fecha entre la solicitud de reserva y hoy, en Lima. Completa las observaciones obligatorias.', 'Revisa todo antes de confirmar. Abre Cierres para comprobar el expediente y sus importes.']),
 ('table', (['Al confirmar', 'Resultado automático'], [
  ['Reserva', 'Cerrada.'],
  ['Consulta CRM', 'Cerrado.'],
  ['Propiedad', 'Vendido o Alquilado; sin publicación en el portal.'],
  ['Oportunidad vinculada, si existe', 'Ganada, probabilidad 100, monto final de la operación.'],
  ['Otras reservas pendientes del inmueble', 'Rechazadas con registro en sus historiales.']
 ])),
 ('note', 'Los cierres no tienen edición ni reapertura desde la aplicación. Revisa monto, moneda, fecha y observaciones antes de guardar. Una consulta cerrada por este flujo no se reabre desde el CRM.'),
 ('p', 'El inmueble cerrado no se elimina ni se vuelve a publicar desde el formulario. Se protege su modalidad, moneda y disponibilidad para preservar el expediente.'),
 ('p', 'Los vendedores pueden revisar los cierres de sus reservas, pero no confirmarlos. Este registro no procesa pagos, no acredita cobros, no calcula comisiones y no genera contratos.'),
 ('link', ('Abrir Cierres', '/closings'))
]),
('12', 'Panel, reportes y exportación', 'Administrador y vendedor según su alcance', [
 ('steps', ['Usa el panel para revisar actividad desde el primer día del mes hasta hoy.', 'En Reportes, selecciona fecha inicial y final. El rango máximo es de 366 días y ambos extremos se incluyen.', 'Consulta indicadores, agrupaciones y detalle de cierres. Verifica que el rango corresponde a la operación que buscas.', 'Pulsa la opción de exportación CSV para descargar el resumen agregado. Ábrelo en Excel u otra hoja de cálculo.']),
 ('table', (['Información', 'Cómo se interpreta'], [
  ['Inventario', 'Estado actual; no depende del rango de fechas.'],
  ['Consultas y reservas', 'Se seleccionan por fecha de creación.'],
  ['Visitas', 'Se seleccionan por fecha programada.'],
  ['Cierres', 'Se seleccionan por fecha de cierre.'],
  ['Estados agrupados', 'Estado actual de los registros seleccionados, no una foto histórica.'],
  ['Importes', 'Separados por venta/alquiler y moneda; alquiler representa renta mensual.']
 ])),
 ('p', 'Los administradores ven el equipo. Un vendedor ve su inventario creado, consultas actualmente asignadas y visitas, reservas y cierres a su nombre. Por eso sus totales pueden ser distintos.'),
 ('p', 'El detalle muestra hasta 20 cierres; los totales y el CSV incluyen todos los correspondientes al alcance y rango. El CSV usa UTF-8 y separador punto y coma, sin datos personales.'),
 ('note', 'No sumes PEN y USD ni precios de venta y rentas mensuales como si fueran el mismo concepto. Los importes son montos pactados y no prueban cobros. Los registros DEMO también afectan los totales.'),
 ('link', ('Abrir Reportes', '/reports'))
]),
('13', 'Administrar usuarios y accesos', 'Administrador o superadministrador', [
 ('steps', ['En Usuarios, abre la creación de una cuenta o Gestionar en una existente.', 'Completa nombres, apellidos, correo, teléfono si corresponde, rol y estado.', 'Para crear o restablecer acceso, escribe una contraseña inicial y su confirmación. Cumple los requisitos de mayúscula, minúscula, número y longitud.', 'Guarda y entrega las credenciales a la persona por un canal adecuado. La aplicación no las envía por correo.', 'El usuario deberá cambiar la contraseña inicial antes de usar los módulos protegidos.']),
 ('h', 'Vendedoras del equipo'),
 ('p', 'Para Alexa Alata, Mayra Solis y Fabiola Fierro, revisa la cuenta ya existente en Usuarios antes de crear otra. Confirma correo, rol Vendedor y estado activo. Si necesitan ingresar, establece una contraseña inicial y pídeles que la cambien al entrar.'),
 ('h', 'Desactivar y reasignar'),
 ('p', 'Desactivar impide el acceso y conserva el historial. No reasigna consultas, visitas ni reservas: el administrador debe revisar cada responsable y completar los cambios necesarios.'),
 ('note', 'No puedes desactivar tu propia cuenta ni cambiar tu propio rol desde este formulario. Para tu contraseña usa Mi contraseña. Un administrador no puede gestionar cuentas ni asignar el rol superadministrador.'),
 ('p', 'Al cambiar correo, rol, estado o contraseña, las sesiones anteriores se invalidan en la siguiente solicitud. Si un usuario informa que salió del sistema después de un cambio administrativo, pídele que vuelva a ingresar.'),
 ('link', ('Abrir Usuarios', '/users'))
]),
('14', 'Academia: estudiar y crear cursos', 'Alumnos, instructores y administradores', [
 ('h', 'Como alumno'),
 ('steps', ['Entra a Academia con sesión y elige un curso activo y publicado.', 'Inscríbete para abrir sus lecciones. Usa Mis cursos para encontrar tus inscripciones.', 'Lee el contenido o abre el enlace de video. Marca cada lección completada al terminar.', 'Si necesitas revisarla, puedes marcarla pendiente mientras las reglas del curso lo permitan. Completar todas las lecciones activas completa el avance de contenidos.']),
 ('h', 'Como instructor o administrador'),
 ('steps', ['En Academia, pulsa Administrar cursos y crea un Nuevo curso.', 'Completa título, descripción e instructor responsable; guarda primero como borrador, sin publicar.', 'Abre Nueva lección, agrega texto y, si corresponde, un enlace HTTPS de video. Define orden y activación; guarda.', 'Con al menos una lección activa, marca Publicar en Academia y guarda el curso.', 'Revisa Alumnos y progreso para consultar las inscripciones. El instructor gestiona solo sus cursos; el administrador puede gestionar todos.']),
 ('note', 'El avance de lecciones es declarado por el alumno; no equivale a aprobar un examen. Los videos se enlazan externamente, no se suben al servidor desde este módulo.'),
 ('p', 'Archivar retira el curso del catálogo y bloquea su estudio, pero conserva inscripciones y progreso. Retirar la última lección activa devuelve el curso a borrador. Después del primer intento de examen, lecciones y evaluación quedan bloqueadas; crea otro curso si necesitas una nueva versión.'),
 ('link', ('Abrir Academia', '/academy'))
]),
('15', 'Exámenes y certificados', 'Evaluación del alumno y gestión docente', [
 ('h', 'Preparar el examen'),
 ('steps', ['Como gestor del curso, abre Gestionar examen final.', 'Guarda la evaluación como borrador y configura la nota mínima de aprobación, en escala de 0 a 20; el valor inicial es 11.', 'Agrega las preguntas y elige la respuesta correcta. Se admiten hasta 50 preguntas, con 2 a 4 opciones cada una.', 'Revisa preguntas y respuestas antes de activar el examen. Después del primer intento ya no se podrá modificar ese contenido.']),
 ('h', 'Rendir como alumno'),
 ('steps', ['Inscríbete y completa todas las lecciones activas.', 'Abre el examen disponible y responde las preguntas. Revisa antes de enviar.', 'Consulta el resultado: muestra tu elección y si fue correcta. El servidor calcula la nota, con igual peso por pregunta.', 'Si apruebas, abre Mis certificados y consulta el documento. Puedes imprimirlo o guardarlo como PDF mediante el navegador.']),
 ('note', 'Hay hasta tres intentos y no existe reinicio de intentos desde esta versión. Después de aprobar no se vuelve a rendir. Los certificados no tienen edición, revocación ni reemisión desde la aplicación.'),
 ('p', 'Cada certificado tiene un código de verificación. El documento completo exige permisos; la verificación pública muestra participante, curso, fecha y código, sin correo ni nota. Un enlace localhost solo será accesible donde se pueda conectar con este servidor.'),
 ('link', ('Abrir Mis certificados', '/academy/certificates')),
 ('link', ('Verificación pública de certificados', '/certificates/verify'))
]),
('16', 'Asistente inmobiliario e IA', 'Visitantes y administradores de configuración', [
 ('h', 'Buscar y solicitar contacto'),
 ('steps', ['Abre Asistente y usa Búsqueda guiada: elige ubicación, tipo, modalidad, moneda y presupuesto; pulsa Buscar opciones. Funciona sin IA.', 'Si la asesoría con IA está activa, escribe tu necesidad y acepta el envío del mensaje y contexto reciente a OpenAI antes de consultar.', 'Revisa las fichas de inmuebles disponibles. El chat orienta, pero no confirma visitas, reservas ni pagos.', 'En Quiero que me contacten, selecciona inmueble o Consulta general, completa tus datos y mensaje, autoriza el contacto y envía. La consulta y el contexto compartido quedan en el CRM.']),
 ('h', 'Configuración administrativa'),
 ('steps', ['Como administrador, abre Configurar IA desde Asistente.', 'Revisa activación, ID del modelo y límites de solicitudes. Introduce la clave solo en el campo privado si necesitas reemplazarla; dejarlo vacío conserva la existente.', 'Pulsa Guardar configuración. Guardar no prueba automáticamente la conexión: se comprueba al enviar una consulta real.']),
 ('note', 'La IA genera consumo en la cuenta del proveedor. Los límites cuentan solicitudes, incluidas fallidas, y no son un tope monetario. No compartas claves ni datos sensibles en el chat.'),
 ('p', 'El asistente utiliza inmuebles publicados y disponibles; no consulta documentos privados. Admite hasta 20 solicitudes IA por conversación y aplica una pausa de 5 segundos entre solicitudes. Nueva conversación inicia otro contexto; los límites diarios siguen aplicándose.'),
 ('link', ('Abrir Asistente', '/assistant'))
]),
('17', 'Simulador de ventas', 'Vendedores, practicantes, instructores y administradores', [
 ('steps', ['Abre Simulador y elige un escenario: primera compra, objeción de precio, alquiler con requisitos o cliente indeciso.', 'Elige Guiada para practicar con un guion fijo sin IA, o Cliente y retroalimentación con IA si está disponible.', 'Si eliges IA, acepta el envío de la práctica al proveedor y usa exclusivamente datos ficticios. Pulsa Comenzar práctica.', 'Lee lo que expresa el cliente y responde como asesor. Realiza al menos tres respuestas; se permite un máximo de ocho.', 'Finaliza y revisa el resultado. Puedes abrir la práctica desde Mi historial de prácticas.']),
 ('table', (['Modalidad', 'Qué obtienes'], [
  ['Guiada', 'Preguntas de guion fijo y lista de autoevaluación; sin nota automática ni llamadas a OpenAI.'],
  ['IA', 'Cliente simulado y retroalimentación; puntaje formativo de 0 a 100 en cinco criterios.']
 ])),
 ('p', 'Los criterios de IA son escucha, necesidades, claridad, objeciones y siguiente paso, hasta 20 puntos cada uno. Lee fortalezas, mejoras y siguiente ejercicio; la valoración no es una certificación oficial.'),
 ('p', 'Instructores y administradores pueden abrir Revisar equipo y leer sus prácticas. Solo el participante puede responder, finalizar o abandonar. Abandonar conserva el historial.'),
 ('note', 'El simulador no modifica propiedades ni clientes del CRM. La modalidad IA comparte límites diarios con el asistente; permite hasta cinco prácticas IA nuevas por usuario y día y aplica pausas entre solicitudes. Si falla el proveedor, no se inventa una respuesta.'),
 ('link', ('Abrir Simulador', '/simulations'))
]),
('18', 'Ejercicio completo con datos DEMO', 'De la publicación a los reportes', [
 ('p', 'Ya existe una venta de ejemplo terminada: Ana Torres, consulta #56, inmueble #93, oportunidad #5, visita #24, reserva #47 y cierre #24. Revisa el historial para ver el recorrido y sus responsables.'),
 ('table', (['Momento', 'Dato / resultado del ejemplo'], [
  ['Publicación', 'Departamento en Miraflores; 350000 PEN; 95.50 m²; 3 dormitorios; 2 baños.'],
  ['Oportunidad', '340000 PEN; 60 % de probabilidad; Abierta.'],
  ['Seguimiento y visita', 'Próxima acción completada; visita presencial Realizada con resultado.'],
  ['Reserva', '10000 PEN; aprobada por administrador.'],
  ['Cierre del 27/10/2026', '335000 PEN; inmueble Vendido y sin publicar; consulta Cerrado.'],
  ['Oportunidad y reportes', 'Ganada por 335000 PEN, 100 %; cierre visible en reportes del día.']
 ])),
 ('link', ('Ver historial del caso completado #56', '/crm/lead?id=56')),
 ('h', 'Caso listo para que practiques'),
 ('p', 'Luis Rojas, consulta #57, inmueble #94, está Contactado y asignado a Alexa Alata. En la prueba del 27/10/2026 quedó sin oportunidad, visita, reserva ni cierre. Si alguien ya avanzó el caso, revisa primero su estado actual.'),
 ('steps', ['Abre la consulta #57 con tu cuenta habitual de administrador o con la de Alexa para las acciones permitidas.', 'Crea una oportunidad de 340000 PEN y 60 %. Registra seguimiento y una visita futura.', 'Al llegar su hora, registra el resultado y continúa a Negociación. Solicita reserva por 10000 PEN.', 'Como administrador, aprueba y registra un cierre por 335000 PEN. Verifica los cambios en CRM, portal y reportes.']),
 ('note', 'Los DEMO se conservan y afectan los reportes. Las cuentas temporales usadas para la prueba están desactivadas; utiliza tu cuenta habitual. La prueba comercial completó 25 comprobaciones, sin llamadas a IA ni pagos.'),
 ('link', ('Continuar consulta de práctica #57', '/crm/lead?id=57'))
]),
('19', 'Soluciones y rutina recomendada', 'Consulta rápida', [
 ('table', (['Situación', 'Qué revisar'], [
  ['No puedo ingresar', 'Correo, contraseña y estado activo. Si se pide cambio inicial, complétalo. Solicita restablecimiento al administrador si hace falta.'],
  ['No veo un módulo o una consulta', 'Confirma tu rol y la asignación. El administrador revisa permisos y responsables.'],
  ['El precio se rechaza', 'Usa entero sin separadores, de 1 a 5000000. Elige PEN o USD.'],
  ['No aparece la propiedad en el portal', 'Comprueba que siga activa y publicada, sus catálogos activos y que no haya salido del portal por cierre.'],
  ['No carga una foto o un PDF', 'Formato, tamaño y límite de archivos. Vuelve a seleccionar el archivo después de corregir el formulario.'],
  ['No puedo registrar la visita realizada', 'Su hora debe haber llegado en Lima. Revisa fecha, estado y responsable.'],
  ['No puedo aprobar o cerrar', 'Se requiere rol administrativo. Para cerrar, la reserva debe estar aprobada y cumplir las reglas de monto/fecha.'],
  ['Sesión expirada o acceso denegado', 'Recarga el formulario o vuelve a ingresar. Pide revisar permisos si persiste.'],
  ['La IA no responde', 'Espera la pausa indicada. El administrador revisa activación, clave, modelo y cuotas. Mientras tanto usa búsqueda guiada.']
 ])),
 ('h', 'Antes de terminar la jornada'),
 ('p', 'Registra cada contacto y resultado; deja una próxima acción concreta. Regulariza visitas, revisa reservas pendientes y comprueba los cierres antes de confirmarlos. Finaliza con Salir.'),
 ('p', 'Para reportar un problema, indica módulo, número del registro, hora y mensaje visible. No incluyas contraseñas ni claves. Este manual describe la versión local revisada el 27/10/2026; funciones y permisos pueden cambiar con futuras actualizaciones.')
]),
]

NAVY = colors.HexColor('#142C40')
TEAL = colors.HexColor('#087F8C')
INK = colors.HexColor('#263B4B')
MUTED = colors.HexColor('#526775')
PALE = colors.HexColor('#EDF6F7')
LINE = colors.HexColor('#D7E2E8')
FONT = Path('C:/Windows/Fonts')
pdfmetrics.registerFont(TTFont('Manual', str(FONT/'arial.ttf')))
pdfmetrics.registerFont(TTFont('Manual-Bold', str(FONT/'arialbd.ttf')))
pdfmetrics.registerFontFamily('Manual', normal='Manual', bold='Manual-Bold', italic='Manual', boldItalic='Manual-Bold')
styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name='BodyM', fontName='Manual', fontSize=10.2, leading=14.4, textColor=INK, spaceAfter=9))
styles.add(ParagraphStyle(name='SubM', fontName='Manual-Bold', fontSize=11.2, leading=15, textColor=NAVY, spaceBefore=8, spaceAfter=7, keepWithNext=True))
styles.add(ParagraphStyle(name='TitleM', fontName='Manual-Bold', fontSize=23, leading=28, textColor=NAVY, spaceAfter=8))
styles.add(ParagraphStyle(name='LabelM', fontName='Manual-Bold', fontSize=9, leading=12, textColor=TEAL, spaceAfter=8))
styles.add(ParagraphStyle(name='CellM', fontName='Manual', fontSize=9.4, leading=12.8, textColor=INK))
styles.add(ParagraphStyle(name='HeadM', parent=styles['CellM'], fontName='Manual-Bold', textColor=colors.white))
styles.add(ParagraphStyle(name='NoteM', parent=styles['BodyM'], fontSize=9.6, leading=13.2, spaceAfter=0))
styles.add(ParagraphStyle(name='SmallM', fontName='Manual', fontSize=9, leading=13, textColor=MUTED))

def para(text, style='BodyM'):
    return Paragraph(escape(text), styles[style])

def link(label, path):
    return Paragraph(f'<link href="{BASE+path}" color="#087F8C"><u>{escape(label)}</u></link>', styles['BodyM'])

def block(kind, content):
    if kind == 'p': return [para(content)]
    if kind == 'h': return [para(content, 'SubM')]
    if kind == 'link': return [link(*content)]
    if kind == 'steps':
        items=[]
        for i, item in enumerate(content, 1):
            items.append(Paragraph(f'<b>{i}.</b> {escape(item)}', styles['BodyM']))
        return items
    if kind == 'note':
        t=Table([[para('Recuerda: '+content, 'NoteM')]], colWidths=[483])
        t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),PALE),('BOX',(0,0),(-1,-1),0.6,LINE),('LEFTPADDING',(0,0),(-1,-1),11),('RIGHTPADDING',(0,0),(-1,-1),11),('TOPPADDING',(0,0),(-1,-1),10),('BOTTOMPADDING',(0,0),(-1,-1),10)]))
        return [t, Spacer(1,10)]
    if kind == 'table':
        headers, rows=content
        data=[[para(h,'HeadM') for h in headers]]+[[para(c,'CellM') for c in row] for row in rows]
        table=Table(data,colWidths=[139,344],repeatRows=1,hAlign='LEFT')
        table.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),NAVY),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,colors.HexColor('#F3F6F8')]),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),9),('RIGHTPADDING',(0,0),(-1,-1),9),('TOPPADDING',(0,0),(-1,-1),8),('BOTTOMPADDING',(0,0),(-1,-1),8),('LINEBELOW',(0,-1),(-1,-1),0.5,LINE)]))
        return [table, Spacer(1,11)]
    raise ValueError(kind)

def frame(c, doc):
    w,h=doc.pagesize
    if doc.page==1:
        c.setFillColor(NAVY); c.rect(0,0,w,h,fill=1,stroke=0)
        c.setFillColor(TEAL); c.rect(0,0,13,h,fill=1,stroke=0)
        c.setFillColor(colors.white); c.setFont('Manual-Bold',13); c.drawString(57,h-72,'INMOVA AI')
        c.setFillColor(colors.HexColor('#87D8D7')); c.setFont('Manual',10); c.drawString(57,64,'MANUAL DE USUARIO  |  EDICIÓN LOCAL 1.0')
        c.setFillColor(colors.HexColor('#D6E7ED')); c.drawString(57,45,DATE)
        return
    c.setStrokeColor(LINE); c.line(56,h-45,w-56,h-45); c.line(56,44,w-56,44)
    c.setFillColor(TEAL); c.setFont('Manual-Bold',9); c.drawString(56,h-33,'INMOVA AI')
    c.setFillColor(MUTED); c.setFont('Manual',8); c.drawRightString(w-56,h-33,'Manual de usuario | Versión 1.0')
    c.drawString(56,29,'Edición local - 27/10/2026'); c.drawRightString(w-56,29,f'{doc.page} / {len(SECTIONS)+2}')

def build_pdf():
    OUT.parent.mkdir(parents=True,exist_ok=True)
    doc=SimpleDocTemplate(str(OUT),pagesize=(595.28,841.89),leftMargin=56,rightMargin=56,topMargin=66,bottomMargin=61,title='INMOVA AI - Manual de usuario',author='INMOVA AI',pageCompression=1)
    story=[Spacer(1,125)]
    cover=ParagraphStyle('Cover',fontName='Manual-Bold',fontSize=39,leading=45,textColor=colors.white,spaceAfter=24)
    desc=ParagraphStyle('Desc',fontName='Manual',fontSize=15,leading=22,textColor=colors.HexColor('#D6E7ED'))
    story += [Paragraph('Manual<br/>de usuario',cover),Paragraph('Gestiona propiedades, atiende clientes<br/>y completa el flujo comercial.',desc),Spacer(1,32)]
    for line in ['OPERACIÓN: propiedades, CRM, visitas, reservas y cierres', 'CONTROL: oportunidades, pendientes, usuarios y reportes', 'FORMACIÓN: Academia, certificados y simulador']:
        story.append(Paragraph(escape(line),ParagraphStyle('CoverLine',fontName='Manual',fontSize=10,leading=16,textColor=colors.HexColor('#A9DCDC'),spaceAfter=9)))
    story += [Spacer(1,30), Paragraph('Instrucciones por perfil, ejemplos para rellenar formularios<br/>y un caso DEMO de inicio a fin.',desc),PageBreak()]
    story += [para('CONSULTA RÁPIDA','LabelM'),para('Contenido del manual','TitleM'),para('Empieza por acceso y roles. Para la operación diaria sigue las secciones 03 a 12; para capacitación, las secciones 14 a 17.')]
    toc=[]
    for index,(num,title,scope,blocks) in enumerate(SECTIONS,3):
        toc.append([para(num,'CellM'),para(title,'CellM'),para(str(index),'CellM')])
    table=Table(toc,colWidths=[32,413,38])
    table.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6),('LINEBELOW',(0,0),(-1,-1),0.4,LINE)]))
    story += [table,Spacer(1,18),para('Los enlaces del documento abren el sistema instalado en este equipo. Si trabajas desde otra dirección, usa los mismos nombres de menú en tu instalación.','SmallM'),PageBreak()]
    for idx,(num,title,scope,blocks) in enumerate(SECTIONS):
        story += [para(f'{num} / {scope.upper()}','LabelM'),para(title,'TitleM'),Spacer(1,6)]
        for kind,content in blocks: story.extend(block(kind,content))
        if idx<len(SECTIONS)-1: story.append(PageBreak())
    doc.build(story,onFirstPage=frame,onLaterPages=frame)

def build_markdown():
    lines=['# INMOVA AI - Manual de usuario','',f'Versión 1.0 | {DATE} | Instalación local','', 'Guía de operación basada en las pantallas y permisos actuales del proyecto.','', '## Contenido','']
    for num,title,scope,blocks in SECTIONS: lines.append(f'- {num}. {title}')
    for num,title,scope,blocks in SECTIONS:
        lines += ['',f'## {num}. {title}','',f'*{scope}*','']
        for kind,content in blocks:
            if kind=='p': lines += [content,'']
            elif kind=='h': lines += [f'### {content}','']
            elif kind=='steps': lines += [f'{i}. {item}' for i,item in enumerate(content,1)]+['']
            elif kind=='note': lines += ['> **Recuerda:** '+content,'']
            elif kind=='link': lines += [f'[{content[0]}]({BASE+content[1]})','']
            elif kind=='table':
                headers,rows=content
                lines += ['| '+' | '.join(headers)+' |','| '+' | '.join(['---']*len(headers))+' |']
                lines += ['| '+' | '.join(row)+' |' for row in rows]+['']
    EDITABLE.write_text('\n'.join(lines),encoding='utf-8')

if __name__=='__main__':
    build_markdown()
    build_pdf()
    print(f'PDF: {OUT}')
    print(f'Editable: {EDITABLE}')
