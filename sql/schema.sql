CREATE TABLE cuentas (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email          VARCHAR(254) NOT NULL,
  -- bcrypt: la app fija PASSWORD_BCRYPT, siempre 60 caracteres.
  password_hash  CHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  rol            ENUM('paciente','medico','profesional','administrador','recepcionista') NOT NULL,
  ref_id         INT UNSIGNED NOT NULL,
  verificado     TINYINT(1) NOT NULL DEFAULT 0,
  email_nuevo    VARCHAR(254) NULL,
  activo         TINYINT(1) NOT NULL DEFAULT 1,
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
  numero    VARCHAR(25)  NOT NULL,
  email_anterior VARCHAR(254) NULL,
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pacientes_ci (ci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medicos (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(60) NOT NULL,
  apellido  VARCHAR(60) NOT NULL,
  ci VARCHAR(15) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_medicos_ci (ci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medico_paciente (
  medico_id   INT UNSIGNED NOT NULL,
  paciente_id INT UNSIGNED NOT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (medico_id, paciente_id),
  CONSTRAINT fk_mp_medico    FOREIGN KEY (medico_id)    REFERENCES medicos(id)    ON DELETE CASCADE,
  CONSTRAINT fk_mp_paciente  FOREIGN KEY (paciente_id)  REFERENCES pacientes(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profesionales (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(60) NOT NULL,
  apellido        VARCHAR(60) NOT NULL,
  ci VARCHAR(15) NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profesionales_ci (ci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE estudios (
  clave         VARCHAR(30) NOT NULL,
  etiqueta      VARCHAR(60) NOT NULL,
  descripcion   VARCHAR(255) NULL,
  autoagendable TINYINT(1)  NOT NULL DEFAULT 0,
  activo        TINYINT(1)  NOT NULL DEFAULT 1,
  creado_en     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profesional_especializacion (
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
  ci VARCHAR(15) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_administradores_ci (ci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sucursales (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(80)  NOT NULL,
  direccion VARCHAR(160) NULL,
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  equipamiento VARCHAR(1000) NULL,
  latitud      DECIMAL(9,6) NULL,
  longitud     DECIMAL(9,6) NULL,
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sucursales_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recepcionistas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(60) NOT NULL,
  apellido    VARCHAR(60) NOT NULL,
  ci VARCHAR(15) NULL,
  sucursal_id INT UNSIGNED NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recepcionistas_ci (ci),
  KEY ix_recepcionistas_sucursal (sucursal_id),
  CONSTRAINT fk_recepcionistas_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tokens_verificacion (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cuenta_id  INT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expira_en  DATETIME NOT NULL,
  usado_en   DATETIME NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tv_hash   (token_hash),
  KEY         ix_tv_cuenta (cuenta_id),
  CONSTRAINT fk_tv_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tokens_reset (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cuenta_id  INT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expira_en  DATETIME NOT NULL,
  usado_en   DATETIME NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tr_hash   (token_hash),
  KEY         ix_tr_cuenta (cuenta_id),
  CONSTRAINT fk_tr_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un día cerrado es la AUSENCIA de filas. Los turnos solapados no se pueden restringir en SQL.
CREATE TABLE sucursal_horarios (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  sucursal_id   INT UNSIGNED     NOT NULL,
  dia_semana    TINYINT UNSIGNED NOT NULL,
  hora_apertura TIME             NOT NULL,
  hora_cierre   TIME             NOT NULL,
  creado_en     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- El prefijo izquierdo (sucursal_id, dia_semana) atiende la búsqueda; no hace falta otro KEY.
  UNIQUE KEY uq_sh_turno (sucursal_id, dia_semana, hora_apertura),
  CONSTRAINT fk_sh_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE,
  CONSTRAINT ck_sh_dia   CHECK (dia_semana BETWEEN 1 AND 7),
  CONSTRAINT ck_sh_rango CHECK (hora_apertura < hora_cierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profesional_sucursal (
  profesional_id INT UNSIGNED NOT NULL,
  sucursal_id    INT UNSIGNED NOT NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (profesional_id, sucursal_id),
  KEY ix_ps_sucursal (sucursal_id),
  CONSTRAINT fk_ps_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_sucursal    FOREIGN KEY (sucursal_id)    REFERENCES sucursales(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profesional_horarios (
  id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  profesional_id INT UNSIGNED     NOT NULL,
  dia_semana     TINYINT UNSIGNED NOT NULL,
  hora_apertura  TIME             NOT NULL,
  hora_cierre    TIME             NOT NULL,
  creado_en      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_phor_turno (profesional_id, dia_semana, hora_apertura),
  CONSTRAINT fk_phor_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE,
  CONSTRAINT ck_phor_dia   CHECK (dia_semana BETWEEN 1 AND 7),
  CONSTRAINT ck_phor_rango CHECK (hora_apertura < hora_cierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lista blanca: sin filas para una sucursal = sin restricción.
CREATE TABLE sucursal_estudio (
  sucursal_id INT UNSIGNED NOT NULL,
  estudio     VARCHAR(30)  NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sucursal_id, estudio),
  CONSTRAINT fk_se_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE,
  CONSTRAINT fk_se_estudio  FOREIGN KEY (estudio)     REFERENCES estudios(clave)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE citas (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id           INT UNSIGNED NULL,
  email_pendiente       VARCHAR(254) NULL,
  nombre_pendiente      VARCHAR(60)  NULL,
  apellido_pendiente    VARCHAR(60)  NULL,
  profesional_id        INT UNSIGNED NOT NULL,
  sucursal_id           INT UNSIGNED NULL,
  fecha_hora_solicitada DATETIME NOT NULL,
  estudio               VARCHAR(30) NOT NULL,
  estado                ENUM('pendiente','confirmada','rechazada','cancelada','realizada') NOT NULL DEFAULT 'pendiente',
  fecha_hora_confirmada DATETIME NULL,
  notas_admin           VARCHAR(255) NULL,
  creado_en             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  slot_activo           TINYINT AS (IF(estado IN ('pendiente','confirmada'), 1, NULL)) STORED,
  PRIMARY KEY (id),
  -- slot_activo es NULL cuando la cita se rechaza o cancela, y libera el turno (los NULL no chocan en un UNIQUE).
  UNIQUE KEY  uq_citas_slot            (profesional_id, fecha_hora_solicitada, slot_activo),
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

CREATE TABLE resultados (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id     INT UNSIGNED NOT NULL,
  cita_id         INT UNSIGNED NULL,
  nombre_estudio  VARCHAR(120) NOT NULL,
  fecha_estudio   DATE NOT NULL,
  observaciones   TEXT NULL,
  informe_archivo         VARCHAR(120) NULL,
  informe_nombre_original VARCHAR(255) NULL,
  creado_por      INT UNSIGNED NOT NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY         ix_resultados_paciente (paciente_id),
  KEY         ix_resultados_cita     (cita_id),
  CONSTRAINT fk_resultados_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_resultados_cita     FOREIGN KEY (cita_id)     REFERENCES citas(id)     ON DELETE SET NULL,
  CONSTRAINT fk_resultados_creador  FOREIGN KEY (creado_por)  REFERENCES cuentas(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resultado_imagenes (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  resultado_id     INT UNSIGNED NOT NULL,
  nombre_archivo   VARCHAR(120) NOT NULL,
  nombre_original  VARCHAR(255) NOT NULL,
  creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY         ix_resultado_imagenes_resultado (resultado_id),
  CONSTRAINT fk_resultado_imagenes_resultado FOREIGN KEY (resultado_id) REFERENCES resultados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notas_clinicas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id INT UNSIGNED NOT NULL,
  cuenta_id   INT UNSIGNED NOT NULL,
  texto       TEXT NOT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notas_paciente (paciente_id, creado_en),
  CONSTRAINT fk_notas_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_notas_cuenta   FOREIGN KEY (cuenta_id)   REFERENCES cuentas(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ordenes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id INT UNSIGNED NULL,
  cita_id     INT UNSIGNED NULL,
  creado_por  INT UNSIGNED NOT NULL,
  tipo        ENUM('estudio','alineadores') NOT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ordenes_paciente (paciente_id),
  KEY ix_ordenes_cita (cita_id),
  CONSTRAINT fk_ordenes_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ordenes_cita     FOREIGN KEY (cita_id)     REFERENCES citas(id)     ON DELETE SET NULL,
  CONSTRAINT fk_ordenes_creador  FOREIGN KEY (creado_por)  REFERENCES cuentas(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orden_estudio (
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

CREATE TABLE orden_estudio_selecciones (
  orden_id INT UNSIGNED NOT NULL,
  campo    VARCHAR(40)  NOT NULL,
  opcion   VARCHAR(100) NOT NULL,
  PRIMARY KEY (orden_id, campo, opcion),
  CONSTRAINT fk_orden_estudio_selecciones_orden FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE eventos (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo      VARCHAR(40)  NOT NULL,
  cuenta_id INT UNSIGNED NULL,
  email     VARCHAR(254) NULL,
  ip        VARCHAR(45)  NOT NULL,
  detalle   VARCHAR(255) NULL,
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
  mensaje      VARCHAR(2000) NOT NULL,
  creado_en    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mensajes_contacto_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Va primero: las filas de sucursal_estudio posteriores referencian estudios.clave.
INSERT INTO estudios (clave, etiqueta, descripcion, autoagendable) VALUES
  ('placa',       'Placa',       'Diagnostico basico de la estructura dental con una toma rapida y de muy baja radiacion.', 1),
  ('radiografia', 'Radiografia', 'Imagen detallada de dientes y huesos para planificar o controlar un tratamiento.', 1),
  ('alineadores', 'Alineadores', 'Estudio previo para planificar el tratamiento con alineadores transparentes.', 0),
  ('tomografia',  'Tomografia',  'Imagen tridimensional de la boca y los maxilares para casos que requieren mayor precision.', 0),
  ('modelo_3d',   'Modelo 3D',   'Escaneo digital de la boca que genera un modelo tridimensional de la dentadura.', 0);

-- El orden de inserción fija los ids 1..11: los datos de horarios y profesionales se enlazan por nombre.
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

INSERT INTO sucursal_estudio (sucursal_id, estudio)
SELECT id, 'radiografia' FROM sucursales WHERE nombre = 'Atlantida';

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

INSERT INTO profesional_sucursal (profesional_id, sucursal_id)
SELECT p.id, s.id FROM profesionales p JOIN sucursales s
WHERE (p.nombre = 'Laura'  AND p.apellido = 'Fernandez' AND s.nombre IN ('Montevideo Shopping','Carrasco','Las Piedras'))
   OR (p.nombre = 'Martin' AND p.apellido = 'Gomez'     AND s.nombre IN ('Nuevo Centro','Atlantida','Colonia'))
   OR (p.nombre = 'Ana'    AND p.apellido = 'Perez'     AND s.nombre IN ('Caudillos','Punta del Este','Durazno'))
   OR (p.nombre = 'Profesional' AND p.apellido = 'Default' AND s.nombre IN ('Lagomar','Libertad','Montevideo Shopping'));
