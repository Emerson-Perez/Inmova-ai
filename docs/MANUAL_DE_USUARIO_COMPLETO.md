# Manual de Usuario - INMOVA AI
**Plataforma Integral Inmobiliaria, CRM, Academia y Asistente con IA**

---

## 1. Estado y Levantamiento del Sistema

El sistema **INMOVA AI** está desarrollado en PHP nativo con arquitectura MVC modular y base de datos relacional MySQL 8. Se ejecuta de forma local a través de la suite **XAMPP**.

### Verificación de Servicios
Para que la aplicación funcione en su totalidad, los dos servicios principales de XAMPP deben estar activos:
- **Apache (HTTP Server):** Puerto estándar `80` (o `443` para SSL).
- **MySQL (Database Server):** Puerto estándar `3306`.

### Enlaces de Acceso Local
- **Portal Inmobiliario Público:** [http://localhost/inmova-ai/public/](http://localhost/inmova-ai/public/)
- **Acceso Administrativo / Login:** [http://localhost/inmova-ai/public/login](http://localhost/inmova-ai/public/login)
- **Panel Principal (Dashboard):** [http://localhost/inmova-ai/public/dashboard](http://localhost/inmova-ai/public/dashboard)
- **Gestión de Base de Datos (phpMyAdmin):** [http://localhost/phpmyadmin](http://localhost/phpmyadmin)

### Credenciales de Acceso Inicial
| Rol | Correo Electrónico | Contraseña Inicial |
| :--- | :--- | :--- |
| **Superadministrador** | `admin@inmova.test` | `Admin123*` |

> Al ingresar por primera vez con una cuenta provisional o tras un restablecimiento administrativo, el sistema exigirá actualizar la contraseña cumpliendo los requisitos mínimos de seguridad (entre 10 y 72 caracteres, con mayúsculas, minúsculas y números).

---

## 2. Acceso al Sistema e Identificación

### Procedimiento de Ingreso
1. Ingrese a [http://localhost/inmova-ai/public/login](http://localhost/inmova-ai/public/login).
2. Escriba su **Correo electrónico** registrado y su **Contraseña**.
3. Haga clic en **Ingresar**.
4. Una vez validada la sesión, será redirigido automáticamente a su panel de control correspondiente según el rol asignado.
5. Para cerrar sesión de forma segura, haga clic en el botón **Salir** situado en el extremo superior derecho del menú de navegación.

---

## 3. Matriz de Roles y Niveles de Permiso

El sistema cuenta con un control de acceso basado en roles (**RBAC**):

| Rol | Permisos y Alcance Operativo |
| :--- | :--- |
| **Superadministrador** | Control total de la plataforma, parametrización de catálogos, gestión de todos los usuarios (incluyendo otros administradores y superadministradores), cursos y ajustes de IA. |
| **Administrador** | Supervisión comercial global, asignación de leads y vendedores, aprobación de reservas, registro de cierres de venta/alquiler, emisión de reportes gerenciales y administración de usuarios del equipo. |
| **Vendedor** | Creación y administración de sus inmuebles asignados, gestión de leads y oportunidades asignadas, registro de notas de seguimiento, programación de visitas y solicitud de reservas. |
| **Instructor** | Creación y edición de cursos en Academia, estructuración de lecciones, diseño de bancos de preguntas para exámenes finales y revisión de simulaciones de venta formativas. |
| **Practicante** | Acceso de entrenamiento: estudio de cursos en Academia y prácticas activas en el simulador de ventas interactivo. |
| **Cliente / Usuario Público** | Exploración del catálogo público de propiedades, filtros avanzados, consultas con el Asistente Inteligente y envío de formularios de contacto. |

---

## 4. Panel de Control Principal (Dashboard)

El panel centraliza las actividades prioritarias del día organizadas en tres bloques fundamentales:

### 1. Centro de Atención del Día (`/tasks`)
Indicadores rápidos con alerta visual para evitar descuidos comerciales:
- **Seguimientos vencidos (Rojo):** Acciones programadas cuya fecha límite ya pasó.
- **Acciones para hoy (Amarillo):** Llamadas, correos o tareas previstas para la jornada.
- **Visitas por regularizar / próximas (Azul):** Citas pendientes de confirmación o de registro de resultado post-visita.
- **Reservas pendientes (Verde):** Solicitudes en espera de aprobación por la administración.
- **Consultas sin asignar:** Leads ingresados por portal o IA que requieren asignación a un asesor.

### 2. Acciones Rápidas
Botones directos para acceder a cada submódulo operativo con un solo clic:
- **Gestión Inmobiliaria:** Propiedades, Documentos, Portal público.
- **Embudo Comercial:** CRM, Oportunidades, Visitas, Reservas y Cierres.
- **Inteligencia y Métricas:** Asistente IA, Configuración IA, Reportes.
- **Capacitación:** Academia, Certificados, Simulador de Ventas.

### 3. Resumen de Actividad Mensual
Consolidado numérico del mes en curso: nuevos prospectos, propiedades activas, citas coordinadas y montos totales de operaciones formalizadas (desglosadas en Soles y Dólares).

---

## 5. Portal Inmobiliario Público

El portal está disponible sin necesidad de iniciar sesión en [http://localhost/inmova-ai/public/portal](http://localhost/inmova-ai/public/portal).

### Funcionalidades del Portal
- **Buscador Multicriterio:** Permite buscar por texto libre (distrito, provincia, nombre del edificio), tipo de propiedad (Casa, Departamento, Terreno), modalidad (Venta o Alquiler) y rango de precio en moneda específica (PEN / USD).
- **Ficha Técnica del Inmueble:** Cada tarjeta ofrece fotografías de alta resolución, precio oficial, metraje en m², cantidad de dormitorios, baños y ubicación.
- **Formulario de Consulta Directa:** Los interesados pueden registrar su nombre, teléfono, correo y mensaje. Este formulario genera automáticamente un nuevo registro en el CRM con origen `portal`.

---

## 6. Gestión de Propiedades y Documentos

### Alta y Edición de Inmuebles (`/properties`)
1. Haga clic en **+ Nueva propiedad**.
2. Complete la información esencial:
   - **Título comercial:** Nombre atractivo y representativo.
   - **Tipo y Modalidad:** Selección de catálogos activos.
   - **Precio y Moneda:** Valor entero sin decimales (máximo 5,000,000).
   - **Ubicación:** Departamento, Provincia, Distrito y Dirección exacta.
   - **Características técnicas:** Área en m², habitaciones y baños.
   - **Galería Multimedia:** Carga de hasta 10 fotos (JPG/PNG/WebP, hasta 2 MB c/u).
   - **Estado de publicación:** Marcar casilla *Publicar en el portal público* para visibilidad inmediata.

### Gestión de Documentos Privados (`/properties/documents`)
- Permite subir documentación confidencial protegida: planos, minutas, partidas registrales, contratos y fichas técnicas.
- Archivos en formato PDF (hasta 2 MB c/u) con un máximo de 20 documentos por propiedad.
- Almacenamiento seguro fuera del alcance de URL públicas directas (`app/storage/documents`), con auditoría de descargas y versiones.

---

## 7. CRM Comercial y Embudo de Ventas

### Gestión de Prospectos (`/crm`)
Cada contacto interesado se transforma en un **Lead** con trazabilidad completa:
1. **Asignación:** El administrador asigna la consulta a un vendedor responsable.
2. **Etapas del Prospecto:**
   - `Nuevo` -> `Contactado` -> `Visita Programada` -> `Negociación` -> `Reservado` -> `Cerrado` (o `Perdido`).
3. **Registro de Seguimiento:** Registro de llamadas, mensajes de WhatsApp, correos y acuerdos pactados.
4. **Próxima Acción:** Permite programar fecha y hora para la siguiente llamada o gestión, alimentando automáticamente el **Centro de Pendientes**.

### Cartera de Oportunidades (`/opportunities`)
- Permite asociar un inmueble específico al lead con un monto estimado y una probabilidad de éxito (0% a 100%).
- Calcula automáticamente el valor ponderado de la cartera comercial para proyecciones de ingresos de la inmobiliaria.

---

## 8. Flujo Operativo Completo (Ciclo Comercial)

1. **Visitas (`/visits`):**
   - Se programa en fecha y hora definida (hora de Lima).
   - Permite modalidad presencial o virtual con enlace.
   - Tras realizarse la visita, el asesor registra el resultado y las impresiones del cliente.
2. **Reservas (`/reservations`):**
   - El asesor ingresa la solicitud indicando el monto acordado.
   - El Administrador valida la transacción y hace clic en **Aprobar Reserva**, pasando el inmueble a estado `Reservado`.
3. **Cierres de Operación (`/closings`):**
   - El Administrador formaliza la venta o contrato de arrendamiento registrando el precio final pactado y la fecha de firma.
   - De forma automática y atómica:
     - La reserva pasa a `Cerrada`.
     - El inmueble pasa a `Vendido` o `Alquilado`.
     - El lead concluye en etapa `Cerrado`.
     - Las oportunidades en curso se marcan como `Ganada`.

---

## 9. Módulos de Inteligencia y Capacitación

### Asistente Inmobiliario con IA (`/assistant`)
- **Búsqueda Guiada:** Disponible en todo momento sin requerir conexión externa; filtra el catálogo por presupuesto y ubicación.
- **Modo Conversacional con OpenAI:** Asistente conversacional activable desde `/assistant/settings` ingresando la API Key. Permite a los visitantes consultar en lenguaje natural, filtrando solo inmuebles disponibles y derivando el contacto al CRM con todo el historial de la conversación.

### Academia y Certificación (`/academy`)
- Catálogo de cursos técnicos y comerciales para el equipo de ventas.
- Los instructores pueden publicar lecciones con lecturas, enlaces a videoconferencias y exámenes de opción múltiple.
- Al aprobar la evaluación final con nota superior a la mínima requerida (ej. 11/20), se emite un **Certificado Digital con código único de verificación** verificable en `/certificates/verify`.

### Simulador de Ventas Comercial (`/simulations`)
- Entrenador interactivo para asesores y practicantes.
- Ofrece 4 escenarios: *Primera compra*, *Objeción de precio*, *Alquiler con requisitos estrictos* y *Cliente indeciso*.
- Incluye modalidad guiada y modalidad con retroalimentación automatizada con IA evaluando escucha activa, detección de necesidades, manejo de objeciones y cierre.

---

## 10. Resumen de Rutina Diaria Recomendada

1. **08:30 AM - Apertura:** Iniciar sesión, revisar el Dashboard y atender primero los **Seguimientos Vencidos** y **Visitas del Día**.
2. **10:00 AM - Prospección y CRM:** Revisar leads nuevos derivados por el portal y el asistente IA, asignarlos y realizar el primer contacto.
3. **02:00 PM - Actualización de Visitas:** Cargar los resultados de las visitas realizadas en la mañana y reprogramar si hubo postergaciones.
4. **04:30 PM - Negociación y Reservas:** Enviar solicitudes de reserva de clientes que hayan dejado arras o depósitos para revisión del Administrador.
5. **06:00 PM - Cierre de Jornada:** Dejar registrada la *Próxima Acción* de cada lead y cerrar sesión de manera segura.
