-- Migration v3: notas_clinicas.texto becomes TEXT so it can hold the AES-256-GCM
-- ciphertext ("v1:" + base64) of a 2000-char note. Existing plaintext rows keep working
-- (the app reads rows without the "v1:" prefix as legacy plaintext).
-- Requires APP_KEY in includes/config.php before new notes are written.
--   mariadb clinica_imagen < sql/migracion-esquema-v3.sql
-- Back up first: deploy/operador.sh option 4 -> 3.

ALTER TABLE notas_clinicas MODIFY texto TEXT NOT NULL;

-- sucursales.equipamiento holds one item per line, so 255 chars is too tight.
ALTER TABLE sucursales MODIFY equipamiento VARCHAR(1000) NULL;
