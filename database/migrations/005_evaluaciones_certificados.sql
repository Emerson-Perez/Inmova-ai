USE inmova_ai;
ALTER TABLE evaluaciones ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1;
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
ALTER TABLE respuestas ADD COLUMN intento_id INT UNSIGNED NULL,
 ADD CONSTRAINT fk_respuestas_intento FOREIGN KEY (intento_id) REFERENCES evaluacion_intentos(id),
 ADD UNIQUE KEY uq_respuesta_intento (intento_id,pregunta_id);
ALTER TABLE certificados ADD COLUMN intento_id INT UNSIGNED NULL UNIQUE,
 ADD COLUMN nombre_alumno VARCHAR(161) NULL,
 ADD COLUMN titulo_curso VARCHAR(160) NULL,
 ADD COLUMN nombre_instructor VARCHAR(161) NULL,
 ADD CONSTRAINT fk_certificado_intento FOREIGN KEY (intento_id) REFERENCES evaluacion_intentos(id);
UPDATE certificados ce JOIN inscripciones i ON i.id=ce.inscripcion_id JOIN usuarios u ON u.id=i.usuario_id JOIN cursos c ON c.id=i.curso_id JOIN usuarios t ON t.id=c.instructor_id
 SET ce.nombre_alumno=CONCAT(u.nombres,' ',u.apellidos),ce.titulo_curso=c.titulo,ce.nombre_instructor=CONCAT(t.nombres,' ',t.apellidos);
