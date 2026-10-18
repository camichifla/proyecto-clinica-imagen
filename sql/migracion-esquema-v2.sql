-- Migration: upgrades a clinica_imagen database created from the previous schema
-- (schema.sql + seed-sucursales.sql) to the current sql/schema.sql, keeping all data.
-- New installs do not need it: deploy/debian-setup.sh loads sql/schema.sql on an empty database.
-- Idempotent (MariaDB IF NOT EXISTS / INSERT IGNORE): safe to run twice.
--   mariadb clinica_imagen < sql/migracion-esquema-v2.sql
-- Back up first: deploy/operador.sh option 4 -> 3.

-- 1. Study catalogue: must exist before the ENUM columns become foreign keys to it.
CREATE TABLE IF NOT EXISTS estudios (                               -- catalogue of imaging studies
  clave         VARCHAR(30) NOT NULL,              -- stable key stored by citas / sucursal_estudio / profesional_especializacion; never renamed
  etiqueta      VARCHAR(60) NOT NULL,
  descripcion   VARCHAR(255) NULL,                 -- short public text shown on servicios.html
  autoagendable TINYINT(1)  NOT NULL DEFAULT 0,    -- 1 = a paciente can self-book it; 0 = only a medico can order it
  activo        TINYINT(1)  NOT NULL DEFAULT 1,    -- 0 = not offered for new bookings; existing citas keep the label
  creado_en     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO estudios (clave, etiqueta, descripcion, autoagendable) VALUES
  ('placa',       'Placa',       'Diagnostico basico de la estructura dental con una toma rapida y de muy baja radiacion.', 1),
  ('radiografia', 'Radiografia', 'Imagen detallada de dientes y huesos para planificar o controlar un tratamiento.', 1),
  ('alineadores', 'Alineadores', 'Estudio previo para planificar el tratamiento con alineadores transparentes.', 0),
  ('tomografia',  'Tomografia',  'Imagen tridimensional de la boca y los maxilares para casos que requieren mayor precision.', 0),
  ('modelo_3d',   'Modelo 3D',   'Escaneo digital de la boca que genera un modelo tridimensional de la dentadura.', 0);

-- 2. New role.
ALTER TABLE cuentas MODIFY rol ENUM('paciente','medico','profesional','administrador','recepcionista') NOT NULL;

CREATE TABLE IF NOT EXISTS recepcionistas (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(60) NOT NULL,
  apellido  VARCHAR(60) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ENUM estudio columns -> VARCHAR keys into estudios.
ALTER TABLE profesional_especializacion
  MODIFY especializacion VARCHAR(30) NOT NULL,
  ADD CONSTRAINT fk_pe_estudio FOREIGN KEY IF NOT EXISTS (especializacion) REFERENCES estudios(clave) ON DELETE RESTRICT;

ALTER TABLE sucursal_estudio
  MODIFY estudio VARCHAR(30) NOT NULL,
  ADD CONSTRAINT fk_se_estudio FOREIGN KEY IF NOT EXISTS (estudio) REFERENCES estudios(clave) ON DELETE RESTRICT;

-- 4. citas: study key + no double booking. If the UNIQUE key fails, two active citas share a
-- profesional and time: cancel or move one of them and run this file again.
ALTER TABLE citas
  MODIFY estudio VARCHAR(30) NOT NULL,
  ADD COLUMN IF NOT EXISTS slot_activo TINYINT AS (IF(estado IN ('pendiente','confirmada'), 1, NULL)) STORED,
  ADD UNIQUE KEY IF NOT EXISTS uq_citas_slot (profesional_id, fecha_hora_solicitada, slot_activo),
  ADD CONSTRAINT fk_citas_estudio FOREIGN KEY IF NOT EXISTS (estudio) REFERENCES estudios(clave) ON DELETE RESTRICT;

-- 5. Sedes: activation flag, equipment and map pin.
ALTER TABLE sucursales
  ADD COLUMN IF NOT EXISTS activo       TINYINT(1)   NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS equipamiento VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS latitud      DECIMAL(9,6) NULL,
  ADD COLUMN IF NOT EXISTS longitud     DECIMAL(9,6) NULL;

UPDATE sucursales SET latitud = -34.903500, longitud = -56.136200 WHERE nombre = 'Montevideo Shopping' AND latitud IS NULL;
UPDATE sucursales SET latitud = -34.889000, longitud = -56.167000 WHERE nombre = 'Nuevo Centro' AND latitud IS NULL;
UPDATE sucursales SET latitud = -34.898300, longitud = -56.167200 WHERE nombre = 'Caudillos' AND latitud IS NULL;
UPDATE sucursales SET latitud = -34.776500, longitud = -55.759500 WHERE nombre = 'Atlantida' AND latitud IS NULL;
UPDATE sucursales SET latitud = -34.890500, longitud = -56.054000 WHERE nombre = 'Carrasco' AND latitud IS NULL;
UPDATE sucursales SET latitud = -34.819800, longitud = -55.958300 WHERE nombre = 'Lagomar' AND latitud IS NULL;
UPDATE sucursales SET latitud = -34.942000, longitud = -54.933000 WHERE nombre = 'Punta del Este' AND latitud IS NULL;
UPDATE sucursales SET latitud = -34.730000, longitud = -56.219000 WHERE nombre = 'Las Piedras' AND latitud IS NULL;
UPDATE sucursales SET latitud = -34.471000, longitud = -57.844000 WHERE nombre = 'Colonia' AND latitud IS NULL;
UPDATE sucursales SET latitud = -34.633000, longitud = -56.615000 WHERE nombre = 'Libertad' AND latitud IS NULL;
UPDATE sucursales SET latitud = -33.381000, longitud = -56.523000 WHERE nombre = 'Durazno' AND latitud IS NULL;

-- 6. Optional PDF report on resultados.
ALTER TABLE resultados
  ADD COLUMN IF NOT EXISTS informe_archivo         VARCHAR(120) NULL AFTER observaciones,
  ADD COLUMN IF NOT EXISTS informe_nombre_original VARCHAR(255) NULL AFTER informe_archivo;

-- 7. New tables.
CREATE TABLE IF NOT EXISTS profesional_horarios (               -- same shape as sucursal_horarios; per profesional, not per sede
  id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  profesional_id INT UNSIGNED     NOT NULL,
  dia_semana     TINYINT UNSIGNED NOT NULL,        -- ISO-8601: 1=lunes .. 7=domingo
  hora_apertura  TIME             NOT NULL,
  hora_cierre    TIME             NOT NULL,
  creado_en      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_phor_turno (profesional_id, dia_semana, hora_apertura),
  CONSTRAINT fk_phor_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE,
  CONSTRAINT ck_phor_dia   CHECK (dia_semana BETWEEN 1 AND 7),
  CONSTRAINT ck_phor_rango CHECK (hora_apertura < hora_cierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notas_clinicas (                         -- append-only clinical record: the app never updates or deletes rows
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id INT UNSIGNED NOT NULL,
  cuenta_id   INT UNSIGNED NOT NULL,                  -- author (medico or profesional account)
  texto       VARCHAR(2000) NOT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notas_paciente (paciente_id, creado_en),
  CONSTRAINT fk_notas_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_notas_cuenta   FOREIGN KEY (cuenta_id)   REFERENCES cuentas(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos (                                -- audit log + attempt throttling (login, reset, registro)
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo      VARCHAR(40)  NOT NULL,
  cuenta_id INT UNSIGNED NULL,                        -- no FK: the record outlives the cuenta
  email     VARCHAR(254) NULL,
  ip        VARCHAR(45)  NOT NULL,
  detalle   VARCHAR(255) NULL,                        -- ids only, never clinical data or passwords
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_eventos_tipo_email (tipo, email, creado_en),
  KEY ix_eventos_tipo_ip    (tipo, ip, creado_en),
  KEY ix_eventos_cuenta     (cuenta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensajes_contacto (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(80)   NOT NULL,
  email        VARCHAR(254)  NOT NULL,
  telefono     VARCHAR(25)   NULL,
  mensaje      VARCHAR(2000) NOT NULL,                -- plain text; always rendered as text, never HTML
  atendido     TINYINT(1)    NOT NULL DEFAULT 0,
  atendido_por INT UNSIGNED  NULL,                    -- cuentas.id of the staff member who marked it
  atendido_en  DATETIME      NULL,
  creado_en    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mensajes_contacto_creado (creado_en),
  CONSTRAINT fk_mensajes_contacto_cuenta FOREIGN KEY (atendido_por) REFERENCES cuentas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Seeded profesionales, their estudios and sedes (anything that already exists is skipped).
INSERT INTO profesionales (nombre, apellido)
SELECT v.nombre, v.apellido
  FROM (SELECT 'Laura' AS nombre, 'Fernandez' AS apellido UNION ALL SELECT 'Martin', 'Gomez'
        UNION ALL SELECT 'Ana', 'Perez' UNION ALL SELECT 'Profesional', 'Default') v
 WHERE NOT EXISTS (SELECT 1 FROM profesionales p WHERE p.nombre = v.nombre AND p.apellido = v.apellido);

INSERT IGNORE INTO profesional_especializacion (profesional_id, especializacion)
SELECT p.id, e.clave FROM profesionales p JOIN estudios e
WHERE (p.nombre = 'Laura'       AND e.clave IN ('placa','radiografia'))
   OR (p.nombre = 'Martin'      AND e.clave IN ('radiografia','tomografia'))
   OR (p.nombre = 'Ana'         AND e.clave IN ('placa','alineadores','modelo_3d'))
   OR (p.nombre = 'Profesional' AND p.apellido = 'Default');

INSERT IGNORE INTO profesional_sucursal (profesional_id, sucursal_id)
SELECT p.id, s.id FROM profesionales p JOIN sucursales s
WHERE (p.nombre = 'Laura'  AND p.apellido = 'Fernandez' AND s.nombre IN ('Montevideo Shopping','Carrasco','Las Piedras'))
   OR (p.nombre = 'Martin' AND p.apellido = 'Gomez'     AND s.nombre IN ('Nuevo Centro','Atlantida','Colonia'))
   OR (p.nombre = 'Ana'    AND p.apellido = 'Perez'     AND s.nombre IN ('Caudillos','Punta del Este','Durazno'))
   OR (p.nombre = 'Profesional' AND p.apellido = 'Default' AND s.nombre IN ('Lagomar','Libertad','Montevideo Shopping'));
