-- Pending email change: the requested address waits in email_nuevo until confirmed; email_anterior (apply-now model) is dropped.
ALTER TABLE cuentas ADD COLUMN IF NOT EXISTS email_nuevo VARCHAR(254) NULL AFTER verificado;
ALTER TABLE cuentas DROP COLUMN IF EXISTS email_anterior;
