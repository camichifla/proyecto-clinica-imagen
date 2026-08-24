-- Migration: crea la tabla `historial`
-- Esta tabla guarda las observaciones y las evoluciones que el
-- personal técnico o administrativo agrega al historial de un paciente.
CREATE TABLE IF NOT EXISTS `historial` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `paciente_ci` INT UNSIGNED NOT NULL,
  `tipo` ENUM('observacion','evolucion') NOT NULL DEFAULT 'observacion',
  `nota` TEXT NOT NULL,
  `tecnico` VARCHAR(150) NOT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_paciente` (`paciente_ci`),
  CONSTRAINT `fk_historial_paciente` FOREIGN KEY (`paciente_ci`) REFERENCES `users` (`CI`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
