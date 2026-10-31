USE inmova_ai;
ALTER TABLE reservas MODIFY estado_reserva ENUM('pendiente','aprobada','rechazada','cancelada','cerrada') NOT NULL DEFAULT 'pendiente';
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
