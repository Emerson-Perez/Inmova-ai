USE inmova_ai;
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
