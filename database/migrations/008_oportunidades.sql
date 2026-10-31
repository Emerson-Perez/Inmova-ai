USE inmova_ai;
ALTER TABLE oportunidades
 ADD COLUMN moneda CHAR(3) NOT NULL DEFAULT 'PEN',
 ADD COLUMN tipo ENUM('venta','alquiler') NOT NULL DEFAULT 'venta',
 ADD COLUMN etapa ENUM('abierta','ganada','perdida','cancelada') NOT NULL DEFAULT 'abierta',
 ADD COLUMN notas TEXT NULL,
 ADD COLUMN cierre_id INT UNSIGNED NULL,
 ADD CONSTRAINT fk_oportunidad_cierre FOREIGN KEY (cierre_id) REFERENCES cierres(id),
 ADD UNIQUE KEY uq_oportunidad_lead_propiedad (lead_id,propiedad_id),
 ADD INDEX idx_oportunidades_etapa (estado,etapa);
UPDATE oportunidades o JOIN propiedades p ON p.id=o.propiedad_id JOIN modalidades m ON m.id=p.modalidad_id
 SET o.moneda=p.moneda,o.tipo=IF(m.slug='alquiler','alquiler','venta');
