-- Schema: Clinica Imagen — full consolidated schema (19 tables).
-- Single source of truth: the only SQL file that creates these tables. All
-- tables InnoDB / utf8mb4. All start empty — no data, see
-- sql/seed-sucursales.sql for the one seed script this schema expects.
-- Apply with phpMyAdmin or `/opt/lampp/bin/mysql`, never the system MySQL client.

CREATE TABLE cuentas (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email          VARCHAR(254) NOT NULL,
  password_hash  CHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, -- bcrypt: app pins PASSWORD_BCRYPT, always 60 chars
  rol            ENUM('paciente','medico','profesional','administrador') NOT NULL,
  ref_id         INT UNSIGNED NOT NULL,              -- polymorphic pointer; see design Decision 1
  verificado     TINYINT(1) NOT NULL DEFAULT 0,
  activo         TINYINT(1) NOT NULL DEFAULT 1,      -- 0 = desactivada por un administrador; login rejects it
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cuentas_email  (email),
  UNIQUE KEY uq_cuentas_rolref (rol, ref_id),
  KEY         ix_cuentas_rol   (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pacientes (                              -- only role with confirmed fields
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

CREATE TABLE medicos (                                -- renamed from the original "profesional" role/panel
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
-- No email/password/account-creation flow yet for either medicos or
-- profesionales: rows are seeded manually until an admin panel exists.

CREATE TABLE profesional_especializacion (            -- M2M: a profesional can have several (matches citas.estudio)
  profesional_id  INT UNSIGNED NOT NULL,
  especializacion ENUM('placa','radiografia','alineadores') NOT NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (profesional_id, especializacion),
  CONSTRAINT fk_pe_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE administradores (
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
  direccion VARCHAR(160) NULL,                    -- seeded for all 11 sedes (re-scraped 2026-09-13); NULL stays allowed for a future branch added without one
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sucursales_nombre (nombre)        -- seeds are name-matched; duplicates must be impossible
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sucursal_horarios (                  -- one row per contiguous shift; see design.md Decision 1
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
-- A closed day is the ABSENCE of rows. Overlapping shifts are not constrainable in SQL; see design.md Decision 2.

CREATE TABLE profesional_sucursal (               -- M2M; composite PK by design.md Decision 4
  profesional_id INT UNSIGNED NOT NULL,
  sucursal_id    INT UNSIGNED NOT NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (profesional_id, sucursal_id),
  KEY ix_ps_sucursal (sucursal_id),               -- reverse lookup: who works at this sede
  CONSTRAINT fk_ps_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_sucursal    FOREIGN KEY (sucursal_id)    REFERENCES sucursales(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sucursal_estudio (                   -- ALLOWLIST. No rows for a sucursal = unrestricted.
  sucursal_id INT UNSIGNED NOT NULL,
  estudio     ENUM('placa','radiografia','alineadores') NOT NULL,  -- same value set as citas.estudio
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sucursal_id, estudio),
  CONSTRAINT fk_se_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE
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
  estudio               ENUM('placa','radiografia','alineadores') NOT NULL,  -- was free-text `motivo`; now a fixed choice
  estado                ENUM('pendiente','confirmada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  fecha_hora_confirmada DATETIME NULL,                 -- set by admin on confirm; may differ from solicitada
  notas_admin           VARCHAR(255) NULL,
  creado_en             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY         ix_citas_paciente        (paciente_id),
  KEY         ix_citas_email_pendiente (email_pendiente),
  KEY         ix_citas_profesional     (profesional_id),
  KEY         ix_citas_estado          (estado),
  KEY         ix_citas_sucursal        (sucursal_id),
  CONSTRAINT fk_citas_paciente    FOREIGN KEY (paciente_id)    REFERENCES pacientes(id)     ON DELETE CASCADE,
  CONSTRAINT fk_citas_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE RESTRICT,
  CONSTRAINT fk_citas_sucursal    FOREIGN KEY (sucursal_id)    REFERENCES sucursales(id)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resultados (                             -- one imaging study result per row; admin-authored
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id     INT UNSIGNED NOT NULL,
  cita_id         INT UNSIGNED NULL,                   -- optional link back to the originating cita
  nombre_estudio  VARCHAR(120) NOT NULL,
  fecha_estudio   DATE NOT NULL,
  observaciones   TEXT NULL,
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

CREATE TABLE ordenes (                                -- optional clinical order attached to a cita booked by a medico
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
