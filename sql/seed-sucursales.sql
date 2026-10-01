-- Seed: 11 sucursales, 72 weekly shift rows, 1 estudio restriction,
-- 12 profesional assignments (design.md Seed Data).
-- Apply with `/opt/lampp/bin/mysql`, never the system MySQL client — branch
-- names are seeded unaccented and must land with the project's charset
-- defaults (utf8mb4).
-- Run once, after sql/schema.sql, against an empty
-- sucursales/sucursal_horarios/sucursal_estudio/profesional_sucursal set.

-- Insertion order fixes ids 1..11 exactly as design.md documents them:
-- 1 Montevideo Shopping, 2 Nuevo Centro, 3 Caudillos, 4 Atlantida,
-- 5 Carrasco, 6 Lagomar, 7 Punta del Este, 8 Las Piedras, 9 Colonia,
-- 10 Libertad, 11 Durazno.
INSERT INTO sucursales (nombre, direccion) VALUES
  ('Montevideo Shopping', 'Luis A. de Herrera 1248, oficina 321, Montevideo'),
  ('Nuevo Centro',        'Bv. Artigas 3126, piso 11 apto. 1103, Montevideo'),
  ('Caudillos',           'Bv. Artigas 1443 esq. Rivera, oficina 121, Montevideo'),
  ('Atlantida',           'Calle 22 esquina 7, Atlantida, Canelones'),
  ('Carrasco',            'Portal de las Americas, local 109, Av. de las Americas'),
  ('Lagomar',             'Av. Giannattasio 236 y Av. Secco Garcia, Lagomar Norte'),
  ('Punta del Este',      'Avda. Roosvelt 1246 esq. Camacho, piso 16 apto. 1605, Ed. More Atlantico, Maldonado'),
  ('Las Piedras',         'Gral. Leandro Gomez 618, Las Piedras, Canelones'),
  ('Colonia',             '18 de Julio esq. Lavalleja, Paseo Imagen (entrada por 18 de Julio), Colonia del Sacramento'),
  ('Libertad',            'Gral. Jose Artigas 815, Libertad, San Jose'),
  ('Durazno',             'Manuel Oribe 680, entre Arrospide y Dr. Luis Morquio, Durazno');

-- 72 shift rows. dia_semana is ISO-8601 (1=lunes .. 6=sabado); no branch has
-- a dia_semana=7 row, so Sunday is closed everywhere by the absence rule.

-- 1 Montevideo Shopping — 1-5: 08:00-20:00 · 6: 08:00-13:00 (6 rows)
INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (1, 1, '08:00:00', '20:00:00'),
  (1, 2, '08:00:00', '20:00:00'),
  (1, 3, '08:00:00', '20:00:00'),
  (1, 4, '08:00:00', '20:00:00'),
  (1, 5, '08:00:00', '20:00:00'),
  (1, 6, '08:00:00', '13:00:00');

-- 2 Nuevo Centro — 1-5: 08:00-20:00 · 6: 09:00-14:00 (6 rows)
INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (2, 1, '08:00:00', '20:00:00'),
  (2, 2, '08:00:00', '20:00:00'),
  (2, 3, '08:00:00', '20:00:00'),
  (2, 4, '08:00:00', '20:00:00'),
  (2, 5, '08:00:00', '20:00:00'),
  (2, 6, '09:00:00', '14:00:00');

-- 3 Caudillos — 1-5: 08:00-20:00 · 6: 09:00-14:00 (6 rows)
INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (3, 1, '08:00:00', '20:00:00'),
  (3, 2, '08:00:00', '20:00:00'),
  (3, 3, '08:00:00', '20:00:00'),
  (3, 4, '08:00:00', '20:00:00'),
  (3, 5, '08:00:00', '20:00:00'),
  (3, 6, '09:00:00', '14:00:00');

-- 4 Atlantida — 1: 08:00-14:00 · 2,4,5: 08:00-16:00 · sin miercoles, sin sabado (4 rows)
INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (4, 1, '08:00:00', '14:00:00'),
  (4, 2, '08:00:00', '16:00:00'),
  (4, 4, '08:00:00', '16:00:00'),
  (4, 5, '08:00:00', '16:00:00');

-- 5 Carrasco — 1-5: 08:00-20:00 · 6: 09:00-14:00 (6 rows)
INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (5, 1, '08:00:00', '20:00:00'),
  (5, 2, '08:00:00', '20:00:00'),
  (5, 3, '08:00:00', '20:00:00'),
  (5, 4, '08:00:00', '20:00:00'),
  (5, 5, '08:00:00', '20:00:00'),
  (5, 6, '09:00:00', '14:00:00');

-- 6 Lagomar — 1-5: 08:00-20:00 · 6: 09:00-14:00 (6 rows)
INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (6, 1, '08:00:00', '20:00:00'),
  (6, 2, '08:00:00', '20:00:00'),
  (6, 3, '08:00:00', '20:00:00'),
  (6, 4, '08:00:00', '20:00:00'),
  (6, 5, '08:00:00', '20:00:00'),
  (6, 6, '09:00:00', '14:00:00');

-- 7 Punta del Este — 1-5: 08:00-20:00 · 6: 09:00-13:00 (6 rows)
INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (7, 1, '08:00:00', '20:00:00'),
  (7, 2, '08:00:00', '20:00:00'),
  (7, 3, '08:00:00', '20:00:00'),
  (7, 4, '08:00:00', '20:00:00'),
  (7, 5, '08:00:00', '20:00:00'),
  (7, 6, '09:00:00', '13:00:00');

-- 8 Las Piedras — 1-5: split 08:30-12:30 + 15:00-19:00 · 6: 08:00-14:00 (11 rows)
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

-- 9 Colonia — 1-3: 08:00-20:00 · 4-5: 08:00-16:00 · 6: 09:00-13:00 (6 rows)
INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (9, 1, '08:00:00', '20:00:00'),
  (9, 2, '08:00:00', '20:00:00'),
  (9, 3, '08:00:00', '20:00:00'),
  (9, 4, '08:00:00', '16:00:00'),
  (9, 5, '08:00:00', '16:00:00'),
  (9, 6, '09:00:00', '13:00:00');

-- 10 Libertad — 1: 14:00-18:30 · 2: 09:00-13:00 · 3: split 09:00-12:00 + 14:00-18:30
--             · 4: 17:00-19:00 · 5: 09:00-13:00 · sin sabado (6 rows)
INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES
  (10, 1, '14:00:00', '18:30:00'),
  (10, 2, '09:00:00', '13:00:00'),
  (10, 3, '09:00:00', '12:00:00'),
  (10, 3, '14:00:00', '18:30:00'),
  (10, 4, '17:00:00', '19:00:00'),
  (10, 5, '09:00:00', '13:00:00');

-- 11 Durazno — 1,3: 11:00-19:00 · 2,4,5: split 08:00-12:00 + 14:00-18:00
--            · 6: 09:00-13:00 (9 rows)
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

-- Restriction row — exactly one (design.md Decision 3): Atlantida performs
-- only radiografia. Zero rows for any other sucursal means unrestricted.
INSERT INTO sucursal_estudio (sucursal_id, estudio)
SELECT id, 'radiografia' FROM sucursales WHERE nombre = 'Atlantida';

-- Profesional assignments — 3 sucursales each for the 4 existing rows.
-- profesionales.id values are not hardcoded; matched by name because the
-- rows pre-exist with unknown ids.
INSERT INTO profesional_sucursal (profesional_id, sucursal_id)
SELECT p.id, s.id FROM profesionales p JOIN sucursales s
WHERE (p.nombre = 'Laura'  AND p.apellido = 'Fernandez' AND s.nombre IN ('Montevideo Shopping','Carrasco','Las Piedras'))
   OR (p.nombre = 'Martin' AND p.apellido = 'Gomez'     AND s.nombre IN ('Nuevo Centro','Atlantida','Colonia'))
   OR (p.nombre = 'Ana'    AND p.apellido = 'Perez'     AND s.nombre IN ('Caudillos','Punta del Este','Durazno'))
   OR (p.nombre = 'Profesional' AND p.apellido = 'Default' AND s.nombre IN ('Lagomar','Libertad','Montevideo Shopping'));
