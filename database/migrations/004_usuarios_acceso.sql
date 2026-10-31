USE inmova_ai;
ALTER TABLE usuarios ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1,
 ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 0;
