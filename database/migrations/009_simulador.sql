USE inmova_ai;
ALTER TABLE simulaciones_ventas
 ADD COLUMN modo ENUM('guiada','ia') NOT NULL DEFAULT 'guiada',
 ADD COLUMN estado ENUM('en_curso','finalizada','evaluada','abandonada') NOT NULL DEFAULT 'en_curso',
 ADD COLUMN guion MEDIUMTEXT NULL,
 ADD COLUMN turnos TINYINT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN solicitud_pendiente CHAR(32) NULL,
 ADD COLUMN solicitud_tipo VARCHAR(16) NULL,
 ADD COLUMN solicitud_at DATETIME NULL,
 ADD COLUMN ultima_solicitud CHAR(32) NULL,
 ADD COLUMN ultima_ia DATETIME NULL,
 ADD COLUMN evaluada_at DATETIME NULL,
 ADD COLUMN modelo_evaluacion VARCHAR(100) NULL,
 ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 ADD INDEX idx_simulacion_usuario (usuario_id,estado,created_at);
UPDATE simulaciones_ventas SET estado=IF(puntaje IS NULL,'finalizada','evaluada');
