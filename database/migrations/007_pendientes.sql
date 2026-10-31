USE inmova_ai;
ALTER TABLE seguimientos ADD COLUMN tarea_completada_at DATETIME NULL,
 ADD COLUMN tarea_completada_por INT UNSIGNED NULL,
 ADD CONSTRAINT fk_seguimiento_tarea_usuario FOREIGN KEY (tarea_completada_por) REFERENCES usuarios(id),
 ADD INDEX idx_seguimientos_pendientes (tarea_completada_at,proxima_accion);
