-- Cambio de email pendiente: la dirección pedida espera en email_nuevo hasta confirmarse; se elimina email_anterior (modelo anterior).
ALTER TABLE cuentas ADD COLUMN IF NOT EXISTS email_nuevo VARCHAR(254) NULL AFTER verificado;
ALTER TABLE cuentas DROP COLUMN IF EXISTS email_anterior;
