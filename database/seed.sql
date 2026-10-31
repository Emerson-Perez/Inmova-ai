USE inmova_ai;
INSERT INTO roles (nombre, descripcion) VALUES
 ('superadmin','Superadministrador'),
 ('admin','Administrador inmobiliario'),
 ('vendedor','Vendedor'),
 ('cliente','Cliente'),
 ('instructor','Instructor'),
 ('practicante','Practicante vendedor');
INSERT INTO tipos_propiedad (nombre) VALUES ('Terreno'),('Departamento'),('Casa');
INSERT INTO modalidades (nombre, slug) VALUES
 ('Venta de terrenos','terrenos'),
 ('Venta de departamentos','venta-deptos'),
 ('Alquiler de departamentos','alquiler');
-- Usuario inicial: admin@inmova.test / Admin123*  (cámbiala después de ingresar)
INSERT INTO usuarios (rol_id, nombres, apellidos, email, password_hash) VALUES
 (1,'Super','Admin','admin@inmova.test','$2y$10$T9KSN7foNdJdVUKj5FjbB.UwF36CtN1c0s59//uZ5xuCWxjlkgNeu');
