USE inmova_ai;
ALTER TABLE reservas
 ADD COLUMN lead_id INT UNSIGNED NULL,
 ADD COLUMN moneda CHAR(3) NOT NULL DEFAULT 'PEN',
 ADD COLUMN etapa_anterior VARCHAR(40) NULL,
 ADD CONSTRAINT fk_reservas_lead FOREIGN KEY (lead_id) REFERENCES leads(id),
 ADD INDEX idx_reservas_estado (propiedad_id,estado_reserva);
UPDATE reservas r JOIN propiedades p ON p.id=r.propiedad_id SET r.moneda=p.moneda;
