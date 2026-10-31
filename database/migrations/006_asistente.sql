USE inmova_ai;
ALTER TABLE conversaciones_ia ADD COLUMN filtros TEXT NULL,
 ADD COLUMN turnos_ia INT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN ultima_ia DATETIME NULL,
 ADD UNIQUE KEY uq_conversacion_sesion (sesion);
ALTER TABLE mensajes_ia ADD COLUMN metadatos TEXT NULL;
CREATE TABLE ia_cuotas (
 dia DATE NOT NULL,
 clave VARCHAR(64) NOT NULL,
 solicitudes INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (dia,clave)
) ENGINE=InnoDB;
