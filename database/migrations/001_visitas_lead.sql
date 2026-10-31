USE inmova_ai;
ALTER TABLE visitas ADD COLUMN lead_id INT UNSIGNED NULL,
 ADD CONSTRAINT fk_visitas_lead FOREIGN KEY (lead_id) REFERENCES leads(id),
 ADD INDEX idx_visitas_agenda (vendedor_id, fecha_hora, estado_visita);
