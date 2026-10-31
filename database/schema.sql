-- INMOVA AI · Esquema de base de datos (MySQL 8)
CREATE DATABASE IF NOT EXISTS inmova_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inmova_ai;

-- ========== ACCESO ==========
CREATE TABLE roles (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL UNIQUE,
  descripcion VARCHAR(160) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rol_id TINYINT UNSIGNED NOT NULL,
  nombres VARCHAR(80) NOT NULL,
  apellidos VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  telefono VARCHAR(20) NULL,
  password_hash VARCHAR(255) NOT NULL,
  auth_version INT UNSIGNED NOT NULL DEFAULT 1,
  password_change_required TINYINT(1) NOT NULL DEFAULT 0,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ========== CATÁLOGO ==========
CREATE TABLE tipos_propiedad (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL UNIQUE,
  estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE modalidades (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL UNIQUE,
  slug VARCHAR(40) NOT NULL UNIQUE,
  estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE ubicaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  departamento VARCHAR(60) NOT NULL,
  provincia VARCHAR(60) NOT NULL,
  distrito VARCHAR(60) NOT NULL,
  direccion VARCHAR(200) NULL,
  latitud DECIMAL(10,7) NULL,
  longitud DECIMAL(10,7) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE propiedades (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_id TINYINT UNSIGNED NOT NULL,
  modalidad_id TINYINT UNSIGNED NOT NULL,
  ubicacion_id INT UNSIGNED NULL,
  creado_por INT UNSIGNED NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  descripcion TEXT NULL,
  precio DECIMAL(12,2) NOT NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'PEN',
  area_m2 DECIMAL(10,2) NULL,
  dormitorios TINYINT UNSIGNED NULL,
  banos TINYINT UNSIGNED NULL,
  disponibilidad ENUM('disponible','reservado','vendido','alquilado') NOT NULL DEFAULT 'disponible',
  publicado TINYINT(1) NOT NULL DEFAULT 0,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tipo_id) REFERENCES tipos_propiedad(id),
  FOREIGN KEY (modalidad_id) REFERENCES modalidades(id),
  FOREIGN KEY (ubicacion_id) REFERENCES ubicaciones(id),
  FOREIGN KEY (creado_por) REFERENCES usuarios(id),
  INDEX idx_busqueda (modalidad_id, publicado, precio, area_m2)
) ENGINE=InnoDB;

CREATE TABLE propiedad_multimedia (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propiedad_id INT UNSIGNED NOT NULL,
  tipo ENUM('foto','video','plano','documento','panoramica360') NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  es_principal TINYINT(1) NOT NULL DEFAULT 0,
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== CRM ==========
CREATE TABLE clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NULL,
  nombres VARCHAR(80) NOT NULL,
  apellidos VARCHAR(80) NULL,
  email VARCHAR(120) NULL,
  telefono VARCHAR(20) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  propiedad_id INT UNSIGNED NULL,
  vendedor_id INT UNSIGNED NULL,
  origen ENUM('portal','agente_ia','whatsapp','referido','otro') NOT NULL DEFAULT 'portal',
  etapa ENUM('nuevo','contactado','visita_programada','negociacion','reservado','cerrado','perdido') NOT NULL DEFAULT 'nuevo',
  presupuesto DECIMAL(12,2) NULL,
  notas TEXT NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (propiedad_id) REFERENCES propiedades(id),
  FOREIGN KEY (vendedor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE oportunidades (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NOT NULL,
  propiedad_id INT UNSIGNED NOT NULL,
  monto_estimado DECIMAL(12,2) NULL,
  probabilidad TINYINT UNSIGNED NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'PEN',
  tipo ENUM('venta','alquiler') NOT NULL DEFAULT 'venta',
  etapa ENUM('abierta','ganada','perdida','cancelada') NOT NULL DEFAULT 'abierta',
  notas TEXT NULL,
  cierre_id INT UNSIGNED NULL,
  UNIQUE KEY uq_oportunidad_lead_propiedad (lead_id,propiedad_id),
  INDEX idx_oportunidades_etapa (estado,etapa),
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id),
  FOREIGN KEY (propiedad_id) REFERENCES propiedades(id)
) ENGINE=InnoDB;

CREATE TABLE seguimientos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  tipo ENUM('llamada','mensaje','correo','visita','nota') NOT NULL DEFAULT 'nota',
  detalle TEXT NOT NULL,
  proxima_accion DATETIME NULL,
  tarea_completada_at DATETIME NULL,
  tarea_completada_por INT UNSIGNED NULL,
  FOREIGN KEY (tarea_completada_por) REFERENCES usuarios(id),
  INDEX idx_seguimientos_pendientes (tarea_completada_at,proxima_accion),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE visitas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NULL,
  propiedad_id INT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  vendedor_id INT UNSIGNED NULL,
  modalidad ENUM('presencial','virtual') NOT NULL DEFAULT 'presencial',
  fecha_hora DATETIME NOT NULL,
  estado_visita ENUM('solicitada','confirmada','realizada','cancelada') NOT NULL DEFAULT 'solicitada',
  resultado TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id),
  INDEX idx_visitas_agenda (vendedor_id, fecha_hora, estado_visita),
  FOREIGN KEY (propiedad_id) REFERENCES propiedades(id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (vendedor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE reservas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'PEN',
  etapa_anterior VARCHAR(40) NULL,
  propiedad_id INT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  vendedor_id INT UNSIGNED NOT NULL,
  monto_reserva DECIMAL(12,2) NULL,
  aprobada_por INT UNSIGNED NULL,
  estado_reserva ENUM('pendiente','aprobada','rechazada','cancelada','cerrada') NOT NULL DEFAULT 'pendiente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id),
  INDEX idx_reservas_estado (propiedad_id,estado_reserva),
  FOREIGN KEY (propiedad_id) REFERENCES propiedades(id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (vendedor_id) REFERENCES usuarios(id),
  FOREIGN KEY (aprobada_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ========== AGENTE IA ==========
CREATE TABLE cierres (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reserva_id INT UNSIGNED NOT NULL UNIQUE,
 registrado_por INT UNSIGNED NOT NULL,
 tipo ENUM('venta','alquiler') NOT NULL,
 monto_final DECIMAL(12,2) NOT NULL,
 moneda CHAR(3) NOT NULL,
 fecha_cierre DATE NOT NULL,
 observaciones TEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (reserva_id) REFERENCES reservas(id),
 FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

ALTER TABLE oportunidades ADD CONSTRAINT fk_oportunidad_cierre FOREIGN KEY (cierre_id) REFERENCES cierres(id);

CREATE TABLE conversaciones_ia (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NULL,
  lead_id INT UNSIGNED NULL,
  sesion VARCHAR(64) NOT NULL,
  filtros TEXT NULL,
  turnos_ia INT UNSIGNED NOT NULL DEFAULT 0,
  ultima_ia DATETIME NULL,
  UNIQUE KEY uq_conversacion_sesion (sesion),
  derivada_a INT UNSIGNED NULL,
  estado ENUM('activa','derivada','cerrada') NOT NULL DEFAULT 'activa',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (lead_id) REFERENCES leads(id),
  FOREIGN KEY (derivada_a) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE mensajes_ia (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversacion_id INT UNSIGNED NOT NULL,
  emisor ENUM('cliente','ia','vendedor') NOT NULL,
  contenido TEXT NOT NULL,
  metadatos TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversacion_id) REFERENCES conversaciones_ia(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========== ACADEMY (E-LEARNING) ==========
CREATE TABLE ia_cuotas (
 dia DATE NOT NULL,
 clave VARCHAR(64) NOT NULL,
 solicitudes INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (dia,clave)
) ENGINE=InnoDB;

CREATE TABLE cursos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  instructor_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  descripcion TEXT NULL,
  publicado TINYINT(1) NOT NULL DEFAULT 0,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (instructor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE lecciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  curso_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  contenido TEXT NULL,
  video_url VARCHAR(255) NULL,
  material_ruta VARCHAR(255) NULL,
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE evaluaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  curso_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  nota_minima DECIMAL(4,2) NOT NULL DEFAULT 11.00,
  es_final TINYINT(1) NOT NULL DEFAULT 0,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE preguntas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  evaluacion_id INT UNSIGNED NOT NULL,
  enunciado TEXT NOT NULL,
  opcion_a VARCHAR(255) NOT NULL,
  opcion_b VARCHAR(255) NOT NULL,
  opcion_c VARCHAR(255) NULL,
  opcion_d VARCHAR(255) NULL,
  correcta CHAR(1) NOT NULL,
  FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inscripciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  curso_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  estado ENUM('activa','completada','abandonada') NOT NULL DEFAULT 'activa',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inscripcion (curso_id, usuario_id),
  FOREIGN KEY (curso_id) REFERENCES cursos(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE evaluacion_intentos (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 evaluacion_id INT UNSIGNED NOT NULL,
 inscripcion_id INT UNSIGNED NOT NULL,
 submission_key CHAR(32) NOT NULL UNIQUE,
 nota DECIMAL(4,2) NOT NULL,
 nota_minima DECIMAL(4,2) NOT NULL,
 aprobada TINYINT(1) NOT NULL,
 detalle LONGTEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id),
 FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id),
 INDEX idx_intentos_alumno (evaluacion_id,inscripcion_id)
) ENGINE=InnoDB;

CREATE TABLE respuestas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  intento_id INT UNSIGNED NULL,
  inscripcion_id INT UNSIGNED NOT NULL,
  pregunta_id INT UNSIGNED NOT NULL,
  opcion_elegida CHAR(1) NOT NULL,
  es_correcta TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (intento_id) REFERENCES evaluacion_intentos(id),
  UNIQUE KEY uq_respuesta_intento (intento_id,pregunta_id),
  FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE,
  FOREIGN KEY (pregunta_id) REFERENCES preguntas(id)
) ENGINE=InnoDB;

CREATE TABLE progreso_cursos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inscripcion_id INT UNSIGNED NOT NULL,
  leccion_id INT UNSIGNED NOT NULL,
  completada TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_progreso (inscripcion_id, leccion_id),
  FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE,
  FOREIGN KEY (leccion_id) REFERENCES lecciones(id)
) ENGINE=InnoDB;

CREATE TABLE certificados (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  intento_id INT UNSIGNED NULL UNIQUE,
  nombre_alumno VARCHAR(161) NULL,
  titulo_curso VARCHAR(160) NULL,
  nombre_instructor VARCHAR(161) NULL,
  inscripcion_id INT UNSIGNED NOT NULL UNIQUE,
  codigo VARCHAR(40) NOT NULL UNIQUE,
  nota_final DECIMAL(4,2) NULL,
  emitido_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (intento_id) REFERENCES evaluacion_intentos(id),
  FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id)
) ENGINE=InnoDB;

CREATE TABLE simulaciones_ventas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  escenario VARCHAR(160) NOT NULL,
  modo ENUM('guiada','ia') NOT NULL DEFAULT 'guiada',
  estado ENUM('en_curso','finalizada','evaluada','abandonada') NOT NULL DEFAULT 'en_curso',
  guion MEDIUMTEXT NULL,
  turnos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  solicitud_pendiente CHAR(32) NULL,
  solicitud_tipo VARCHAR(16) NULL,
  solicitud_at DATETIME NULL,
  ultima_solicitud CHAR(32) NULL,
  ultima_ia DATETIME NULL,
  evaluada_at DATETIME NULL,
  modelo_evaluacion VARCHAR(100) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_simulacion_usuario (usuario_id,estado,created_at),
  transcripcion MEDIUMTEXT NULL,
  puntaje DECIMAL(5,2) NULL,
  retroalimentacion TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE propiedad_documentos (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 propiedad_id INT UNSIGNED NOT NULL,
 titulo VARCHAR(120) NOT NULL,
 categoria ENUM('plano','contrato','ficha','otro') NOT NULL,
 nombre_original VARCHAR(180) NOT NULL,
 storage_name VARCHAR(36) NOT NULL UNIQUE,
 tamano INT UNSIGNED NOT NULL,
 subido_por INT UNSIGNED NOT NULL,
 estado TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (propiedad_id) REFERENCES propiedades(id),
 FOREIGN KEY (subido_por) REFERENCES usuarios(id),
 INDEX idx_documentos_propiedad (propiedad_id,estado)
) ENGINE=InnoDB;
CREATE TABLE documento_eventos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 documento_id INT UNSIGNED NOT NULL,
 usuario_id INT UNSIGNED NOT NULL,
 accion ENUM('subido','descargado','retirado','restaurado') NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (documento_id) REFERENCES propiedad_documentos(id),
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
 INDEX idx_documento_eventos (documento_id,id)
) ENGINE=InnoDB;

