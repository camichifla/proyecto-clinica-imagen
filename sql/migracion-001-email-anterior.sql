-- Pending email change: previous email kept until the new one is confirmed.
ALTER TABLE cuentas ADD COLUMN IF NOT EXISTS email_anterior VARCHAR(254) NULL AFTER verificado;
