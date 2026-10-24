-- Schema: Clinica Imagen (25 tables, InnoDB / utf8mb4). Seed data is at the end of this file.
-- Apply with phpMyAdmin or `/opt/lampp/bin/mysql`, never the system MySQL client.

CREATE TABLE cuentas (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email          VARCHAR(254) NOT NULL,
  password_hash  CHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, -- bcrypt: app pins PASSWORD_BCRYPT, always 60 chars
  rol            ENUM('paciente','medico','profesional','administrador','recepcionista') NOT NULL,
  ref_id         INT UNSIGNED NOT NULL,              -- polymorphic pointer
  verificado     TINYINT(1) NOT NULL DEFAULT 0,
  activo         TINYINT(1) NOT NULL DEFAULT 1,      -- 0 = desactivada por un administrador; login rejects it
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cuentas_email  (email),
  UNIQUE KEY uq_cuentas_rolref (rol, ref_id),
  KEY         ix_cuentas_rol   (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pacientes (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(60)  NOT NULL,
  apellido  VARCHAR(60)  NOT NULL,
  ci        VARCHAR(15)  NOT NULL,
  direccion VARCHAR(160) NOT NULL,
  numero    VARCHAR(25)  NOT NULL,                    -- phone; VARCHAR, never numeric
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pacientes_ci (ci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- No email / password column here: both live on `cuentas`, never duplicated.

CREATE TABLE medicos (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(60) NOT NULL,
  apellido  VARCHAR(60) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medico_paciente (                        -- admin-assigned link, on top of the organic "medico already
  medico_id   INT UNSIGNED NOT NULL,                  -- created an order for this paciente" connection (via ordenes.creado_por)
  paciente_id INT UNSIGNED NOT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (medico_id, paciente_id),
  CONSTRAINT fk_mp_medico    FOREIGN KEY (medico_id)    REFERENCES medicos(id)    ON DELETE CASCADE,
  CONSTRAINT fk_mp_paciente  FOREIGN KEY (paciente_id)  REFERENCES pacientes(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profesionales (                          -- distinct role: imaging specialist, booked by patients
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(60) NOT NULL,
  apellido        VARCHAR(60) NOT NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE estudios (                               -- catalogue of imaging studies
  clave         VARCHAR(30) NOT NULL,              -- stable key stored by citas / sucursal_estudio / profesional_especializacion; never renamed
  etiqueta      VARCHAR(60) NOT NULL,
  descripcion   VARCHAR(255) NULL,                 -- short public text shown on servicios.html
  autoagendable TINYINT(1)  NOT NULL DEFAULT 0,    -- 1 = a paciente can self-book it; 0 = only a medico can order it
  activo        TINYINT(1)  NOT NULL DEFAULT 1,    -- 0 = not offered for new bookings; existing citas keep the label
  creado_en     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Never deleted (RESTRICT foreign keys below): deactivate instead.

CREATE TABLE profesional_especializacion (            -- M2M: a profesional can have several (matches citas.estudio)
  profesional_id  INT UNSIGNED NOT NULL,
  especializacion VARCHAR(30) NOT NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (profesional_id, especializacion),
  CONSTRAINT fk_pe_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE,
  CONSTRAINT fk_pe_estudio     FOREIGN KEY (especializacion) REFERENCES estudios(clave) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE administradores (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(60) NOT NULL,
  apellido  VARCHAR(60) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recepcionistas (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(60) NOT NULL,
  apellido  VARCHAR(60) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tokens_verificacion (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cuenta_id  INT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,  -- sha256 hex, never the raw token
  expira_en  DATETIME NOT NULL,                       -- issued_at + 24h
  usado_en   DATETIME NULL,                           -- non-null = consumed, single use
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tv_hash   (token_hash),
  KEY         ix_tv_cuenta (cuenta_id),
  CONSTRAINT fk_tv_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tokens_reset (                           -- identical shape, separate lifetime + scope
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cuenta_id  INT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expira_en  DATETIME NOT NULL,                       -- issued_at + 1h
  usado_en   DATETIME NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tr_hash   (token_hash),
  KEY         ix_tr_cuenta (cuenta_id),
  CONSTRAINT fk_tr_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sucursales (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(80)  NOT NULL,
  direccion VARCHAR(160) NULL,                    -- NULL allowed (branch added without an address)
  activo       TINYINT(1)   NOT NULL DEFAULT 1,   -- 0 = not offered for booking and hidden from the public map; existing citas keep the name
  equipamiento VARCHAR(1000) NULL,                -- one item per line
  latitud      DECIMAL(9,6) NULL,                 -- map pin; NULL (either one) = listed but not pinned
  longitud     DECIMAL(9,6) NULL,
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sucursales_nombre (nombre)        -- seeds are name-matched; duplicates must be impossible
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sucursal_horarios (                  -- one row per contiguous shift
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  sucursal_id   INT UNSIGNED     NOT NULL,
  dia_semana    TINYINT UNSIGNED NOT NULL,        -- ISO-8601: 1=lunes .. 7=domingo, == PHP format('N')
  hora_apertura TIME             NOT NULL,
  hora_cierre   TIME             NOT NULL,
  creado_en     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- leftmost prefix (sucursal_id, dia_semana) serves the lookup; no extra KEY needed
  UNIQUE KEY uq_sh_turno (sucursal_id, dia_semana, hora_apertura),
  CONSTRAINT fk_sh_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE,
  CONSTRAINT ck_sh_dia   CHECK (dia_semana BETWEEN 1 AND 7),
  CONSTRAINT ck_sh_rango CHECK (hora_apertura < hora_cierre)   -- MariaDB 10.4.32 enforces this
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- A closed day is the ABSENCE of rows. Overlapping shifts are not constrainable in SQL.

CREATE TABLE profesional_sucursal (               -- M2M; composite PK
  profesional_id INT UNSIGNED NOT NULL,
  sucursal_id    INT UNSIGNED NOT NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (profesional_id, sucursal_id),
  KEY ix_ps_sucursal (sucursal_id),               -- reverse lookup: who works at this sede
  CONSTRAINT fk_ps_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_sucursal    FOREIGN KEY (sucursal_id)    REFERENCES sucursales(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profesional_horarios (               -- same shape as sucursal_horarios; per profesional, not per sede
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
-- No rows at all = unrestricted. Rows but none for a given day = does not work that day.

CREATE TABLE sucursal_estudio (                   -- ALLOWLIST. No rows for a sucursal = unrestricted.
  sucursal_id INT UNSIGNED NOT NULL,
  estudio     VARCHAR(30)  NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sucursal_id, estudio),
  CONSTRAINT fk_se_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE,
  CONSTRAINT fk_se_estudio  FOREIGN KEY (estudio)     REFERENCES estudios(clave)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE citas (                                  -- patient requests a slot; admin confirms/rejects
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id           INT UNSIGNED NULL,               -- NULL = booked by a medico for someone not yet registered; see email_pendiente
  email_pendiente       VARCHAR(254) NULL,               -- set iff paciente_id IS NULL; cleared and linked when that email later registers
  nombre_pendiente      VARCHAR(60)  NULL,                -- set alongside email_pendiente so staff can identify who a pending cita is for by name
  apellido_pendiente    VARCHAR(60)  NULL,
  profesional_id        INT UNSIGNED NOT NULL,          -- chosen by the patient up front, filtered by especializacion
  sucursal_id           INT UNSIGNED NULL,              -- NULL = sede no registrada (dato historico)
  fecha_hora_solicitada DATETIME NOT NULL,
  estudio               VARCHAR(30) NOT NULL,            -- estudios.clave
  estado                ENUM('pendiente','confirmada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  fecha_hora_confirmada DATETIME NULL,                 -- set by admin on confirm; may differ from solicitada
  notas_admin           VARCHAR(255) NULL,
  creado_en             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  slot_activo           TINYINT AS (IF(estado IN ('pendiente','confirmada'), 1, NULL)) STORED,  -- NULL when rejected/cancelled so the slot is freed (NULLs never collide in a UNIQUE)
  PRIMARY KEY (id),
  UNIQUE KEY  uq_citas_slot            (profesional_id, fecha_hora_solicitada, slot_activo),  -- no double booking of a profesional at the same time
  KEY         ix_citas_paciente        (paciente_id),
  KEY         ix_citas_email_pendiente (email_pendiente),
  KEY         ix_citas_profesional     (profesional_id),
  KEY         ix_citas_estado          (estado),
  KEY         ix_citas_sucursal        (sucursal_id),
  CONSTRAINT fk_citas_paciente    FOREIGN KEY (paciente_id)    REFERENCES pacientes(id)     ON DELETE CASCADE,
  CONSTRAINT fk_citas_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE RESTRICT,
  CONSTRAINT fk_citas_sucursal    FOREIGN KEY (sucursal_id)    REFERENCES sucursales(id)    ON DELETE RESTRICT,
  CONSTRAINT fk_citas_estudio     FOREIGN KEY (estudio)        REFERENCES estudios(clave)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resultados (                             -- one imaging study result per row; admin-authored
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id     INT UNSIGNED NOT NULL,
  cita_id         INT UNSIGNED NULL,                   -- optional link back to the originating cita
  nombre_estudio  VARCHAR(120) NOT NULL,
  fecha_estudio   DATE NOT NULL,
  observaciones   TEXT NULL,
  informe_archivo         VARCHAR(120) NULL,           -- optional PDF report: generated filename under storage/resultados/
  informe_nombre_original VARCHAR(255) NULL,           -- original filename, display only
  creado_por      INT UNSIGNED NOT NULL,               -- cuentas.id of the admin who uploaded it
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY         ix_resultados_paciente (paciente_id),
  KEY         ix_resultados_cita     (cita_id),
  CONSTRAINT fk_resultados_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_resultados_cita     FOREIGN KEY (cita_id)     REFERENCES citas(id)     ON DELETE SET NULL,
  CONSTRAINT fk_resultados_creador  FOREIGN KEY (creado_por)  REFERENCES cuentas(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resultado_imagenes (                     -- 1..N images per resultado; files live under storage/
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  resultado_id     INT UNSIGNED NOT NULL,
  nombre_archivo   VARCHAR(120) NOT NULL,              -- generated filename on disk, never the client's raw name
  nombre_original  VARCHAR(255) NOT NULL,               -- original filename, for display only, HTML-escaped
  creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY         ix_resultado_imagenes_resultado (resultado_id),
  CONSTRAINT fk_resultado_imagenes_resultado FOREIGN KEY (resultado_id) REFERENCES resultados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notas_clinicas (                         -- append-only clinical record: the app never updates or deletes rows
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id INT UNSIGNED NOT NULL,
  cuenta_id   INT UNSIGNED NOT NULL,                  -- author (medico or profesional account)
  texto       TEXT NOT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notas_paciente (paciente_id, creado_en),
  CONSTRAINT fk_notas_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_notas_cuenta   FOREIGN KEY (cuenta_id)   REFERENCES cuentas(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ordenes (                              -- optional clinical order attached to a cita booked by a medico
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id INT UNSIGNED NULL,      -- NULL until the cita's own paciente_id resolves; see citas.email_pendiente
  cita_id     INT UNSIGNED NULL,      -- the cita created alongside it, if any
  creado_por  INT UNSIGNED NOT NULL,  -- cuentas.id of the medico who filled it
  tipo        ENUM('estudio','alineadores') NOT NULL, -- 'estudio' details live in orden_estudio / orden_estudio_selecciones
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ordenes_paciente (paciente_id),
  KEY ix_ordenes_cita (cita_id),
  CONSTRAINT fk_ordenes_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ordenes_cita     FOREIGN KEY (cita_id)     REFERENCES citas(id)     ON DELETE SET NULL,
  CONSTRAINT fk_ordenes_creador  FOREIGN KEY (creado_por)  REFERENCES cuentas(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orden_estudio (                          -- 1:1 with an ordenes row of tipo 'estudio': its single-valued fields
  orden_id                    INT UNSIGNED NOT NULL,
  opt_indicacion              VARCHAR(200) NULL,
  telerradio_frontal_analisis BOOLEAN NOT NULL DEFAULT FALSE,
  estudio_cefalo_compu_otros  VARCHAR(120) NULL,
  fotografia_interes          VARCHAR(200) NULL,
  ortodoncia_marca            VARCHAR(120) NULL,
  ortodoncia_info_clinica     TEXT NULL,
  tomo_elementos_sueltos      VARCHAR(200) NULL,
  interes_estudio_tomo        TEXT NULL,
  implante_marca              VARCHAR(120) NULL,
  implante_ubicacion          VARCHAR(120) NULL,
  implante_fecha_cirugia      DATE NULL,
  eco_interes                 VARCHAR(200) NULL,
  solo_escaneo_interes        VARCHAR(200) NULL,
  protector_bucal_color       VARCHAR(120) NULL,
  PRIMARY KEY (orden_id),
  CONSTRAINT fk_orden_estudio_orden FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orden_estudio_selecciones (              -- multi-valued checkbox fields: one row per checked option
  orden_id INT UNSIGNED NOT NULL,
  campo    VARCHAR(40)  NOT NULL,                     -- key of ORDEN_CAMPOS_ESTUDIO (e.g. 'radio_intra')
  opcion   VARCHAR(100) NOT NULL,                     -- one of that field's whitelisted options
  PRIMARY KEY (orden_id, campo, opcion),
  CONSTRAINT fk_orden_estudio_selecciones_orden FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE eventos (                                -- audit log + attempt throttling (login, reset, registro)
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

CREATE TABLE mensajes_contacto (
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

-- The 5 estudios (catalogue). Must come
-- first: sucursal_estudio rows below reference estudios.clave.
INSERT INTO estudios (clave, etiqueta, descripcion, autoagendable) VALUES
  ('placa',       'Placa',       'Diagnostico basico de la estructura dental con una toma rapida y de muy baja radiacion.', 1),
  ('radiografia', 'Radiografia', 'Imagen detallada de dientes y huesos para planificar o controlar un tratamiento.', 1),
  ('alineadores', 'Alineadores', 'Estudio previo para planificar el tratamiento con alineadores transparentes.', 0),
  ('tomografia',  'Tomografia',  'Imagen tridimensional de la boca y los maxilares para casos que requieren mayor precision.', 0),
  ('modelo_3d',   'Modelo 3D',   'Escaneo digital de la boca que genera un modelo tridimensional de la dentadura.', 0);

-- Insertion order fixes ids 1..11:
-- 1 Montevideo Shopping, 2 Nuevo Centro, 3 Caudillos, 4 Atlantida,
-- 5 Carrasco, 6 Lagomar, 7 Punta del Este, 8 Las Piedras, 9 Colonia,
-- 10 Libertad, 11 Durazno.
INSERT INTO sucursales (nombre, direccion, latitud, longitud) VALUES
  ('Montevideo Shopping', 'Luis A. de Herrera 1248, oficina 321, Montevideo', -34.903500, -56.136200),
  ('Nuevo Centro',        'Bv. Artigas 3126, piso 11 apto. 1103, Montevideo', -34.889000, -56.167000),
  ('Caudillos',           'Bv. Artigas 1443 esq. Rivera, oficina 121, Montevideo', -34.898300, -56.167200),
  ('Atlantida',           'Calle 22 esquina 7, Atlantida, Canelones', -34.776500, -55.759500),
  ('Carrasco',            'Portal de las Americas, local 109, Av. de las Americas', -34.890500, -56.054000),
  ('Lagomar',             'Av. Giannattasio 236 y Av. Secco Garcia, Lagomar Norte', -34.819800, -55.958300),
  ('Punta del Este',      'Avda. Roosvelt 1246 esq. Camacho, piso 16 apto. 1605, Ed. More Atlantico, Maldonado', -34.942000, -54.933000),
  ('Las Piedras',         'Gral. Leandro Gomez 618, Las Piedras, Canelones', -34.730000, -56.219000),
  ('Colonia',             '18 de Julio esq. Lavalleja, Paseo Imagen (entrada por 18 de Julio), Colonia del Sacramento', -34.471000, -57.844000),
  ('Libertad',            'Gral. Jose Artigas 815, Libertad, San Jose', -34.633000, -56.615000),
  ('Durazno',             'Manuel Oribe 680, entre Arrospide y Dr. Luis Morquio, Durazno', -33.381000, -56.523000);

-- 72 shift rows. dia_semana is ISO-8601 (1=lunes .. 6=sabado); no branch has
-- a dia_semana=7 row, so Sunday is closed everywhere by the absence rule.

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (1, 1, '08:00:00', '20:00:00'),
  (1, 2, '08:00:00', '20:00:00'),
  (1, 3, '08:00:00', '20:00:00'),
  (1, 4, '08:00:00', '20:00:00'),
  (1, 5, '08:00:00', '20:00:00'),
  (1, 6, '08:00:00', '13:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (2, 1, '08:00:00', '20:00:00'),
  (2, 2, '08:00:00', '20:00:00'),
  (2, 3, '08:00:00', '20:00:00'),
  (2, 4, '08:00:00', '20:00:00'),
  (2, 5, '08:00:00', '20:00:00'),
  (2, 6, '09:00:00', '14:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (3, 1, '08:00:00', '20:00:00'),
  (3, 2, '08:00:00', '20:00:00'),
  (3, 3, '08:00:00', '20:00:00'),
  (3, 4, '08:00:00', '20:00:00'),
  (3, 5, '08:00:00', '20:00:00'),
  (3, 6, '09:00:00', '14:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (4, 1, '08:00:00', '14:00:00'),
  (4, 2, '08:00:00', '16:00:00'),
  (4, 4, '08:00:00', '16:00:00'),
  (4, 5, '08:00:00', '16:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (5, 1, '08:00:00', '20:00:00'),
  (5, 2, '08:00:00', '20:00:00'),
  (5, 3, '08:00:00', '20:00:00'),
  (5, 4, '08:00:00', '20:00:00'),
  (5, 5, '08:00:00', '20:00:00'),
  (5, 6, '09:00:00', '14:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (6, 1, '08:00:00', '20:00:00'),
  (6, 2, '08:00:00', '20:00:00'),
  (6, 3, '08:00:00', '20:00:00'),
  (6, 4, '08:00:00', '20:00:00'),
  (6, 5, '08:00:00', '20:00:00'),
  (6, 6, '09:00:00', '14:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (7, 1, '08:00:00', '20:00:00'),
  (7, 2, '08:00:00', '20:00:00'),
  (7, 3, '08:00:00', '20:00:00'),
  (7, 4, '08:00:00', '20:00:00'),
  (7, 5, '08:00:00', '20:00:00'),
  (7, 6, '09:00:00', '13:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (8, 1, '08:30:00', '12:30:00'),
  (8, 1, '15:00:00', '19:00:00'),
  (8, 2, '08:30:00', '12:30:00'),
  (8, 2, '15:00:00', '19:00:00'),
  (8, 3, '08:30:00', '12:30:00'),
  (8, 3, '15:00:00', '19:00:00'),
  (8, 4, '08:30:00', '12:30:00'),
  (8, 4, '15:00:00', '19:00:00'),
  (8, 5, '08:30:00', '12:30:00'),
  (8, 5, '15:00:00', '19:00:00'),
  (8, 6, '08:00:00', '14:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (9, 1, '08:00:00', '20:00:00'),
  (9, 2, '08:00:00', '20:00:00'),
  (9, 3, '08:00:00', '20:00:00'),
  (9, 4, '08:00:00', '16:00:00'),
  (9, 5, '08:00:00', '16:00:00'),
  (9, 6, '09:00:00', '13:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (10, 1, '14:00:00', '18:30:00'),
  (10, 2, '09:00:00', '13:00:00'),
  (10, 3, '09:00:00', '12:00:00'),
  (10, 3, '14:00:00', '18:30:00'),
  (10, 4, '17:00:00', '19:00:00'),
  (10, 5, '09:00:00', '13:00:00');

INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (11, 1, '11:00:00', '19:00:00'),
  (11, 2, '08:00:00', '12:00:00'),
  (11, 2, '14:00:00', '18:00:00'),
  (11, 3, '11:00:00', '19:00:00'),
  (11, 4, '08:00:00', '12:00:00'),
  (11, 4, '14:00:00', '18:00:00'),
  (11, 5, '08:00:00', '12:00:00'),
  (11, 5, '14:00:00', '18:00:00'),
  (11, 6, '09:00:00', '13:00:00');

-- Restriction row: Atlantida performs
-- only radiografia. Zero rows for any other sucursal means unrestricted.
INSERT INTO sucursal_estudio (sucursal_id, estudio)
SELECT id, 'radiografia' FROM sucursales WHERE nombre = 'Atlantida';

-- The 4 profesionales, with the estudios each can perform. Every estudio is
-- covered by at least one profesional so all of them can be booked.
INSERT INTO profesionales (nombre, apellido) VALUES
  ('Laura',       'Fernandez'),
  ('Martin',      'Gomez'),
  ('Ana',         'Perez'),
  ('Profesional', 'Default');

INSERT INTO profesional_especializacion (profesional_id, especializacion)
SELECT p.id, e.clave FROM profesionales p JOIN estudios e
WHERE (p.nombre = 'Laura'       AND e.clave IN ('placa','radiografia'))
   OR (p.nombre = 'Martin'      AND e.clave IN ('radiografia','tomografia'))
   OR (p.nombre = 'Ana'         AND e.clave IN ('placa','alineadores','modelo_3d'))
   OR (p.nombre = 'Profesional' AND p.apellido = 'Default');

-- Profesional assignments — 3 sucursales each, matched by name.
INSERT INTO profesional_sucursal (profesional_id, sucursal_id)
SELECT p.id, s.id FROM profesionales p JOIN sucursales s
WHERE (p.nombre = 'Laura'  AND p.apellido = 'Fernandez' AND s.nombre IN ('Montevideo Shopping','Carrasco','Las Piedras'))
   OR (p.nombre = 'Martin' AND p.apellido = 'Gomez'     AND s.nombre IN ('Nuevo Centro','Atlantida','Colonia'))
   OR (p.nombre = 'Ana'    AND p.apellido = 'Perez'     AND s.nombre IN ('Caudillos','Punta del Este','Durazno'))
   OR (p.nombre = 'Profesional' AND p.apellido = 'Default' AND s.nombre IN ('Lagomar','Libertad','Montevideo Shopping'));
