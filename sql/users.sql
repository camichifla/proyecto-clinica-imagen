-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 16, 2026 at 03:56 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `users_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `CI` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `surname` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('patient','admin') NOT NULL DEFAULT 'patient'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(20),
    consulta VARCHAR(200) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_respuesta DATETIME,
    ip_address VARCHAR(45),
    estado ENUM('nuevo', 'leido', 'respondido', 'archivado') DEFAULT 'nuevo',
    respuesta LONGTEXT,
    INDEX idx_email (email),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD UNIQUE KEY `CI` (`CI`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `email_2` (`email`);

CREATE TABLE `agenda` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `paciente_ci` int(11) unsigned NOT NULL,
  `estudio` varchar(150) NOT NULL,
  `medico` varchar(150) NOT NULL,
  `sucursal` varchar(150) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `estado` enum('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_paciente_ci` (`paciente_ci`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_hora` (`fecha_hora`),
  CONSTRAINT `fk_agenda_paciente` FOREIGN KEY (`paciente_ci`) REFERENCES `users` (`CI`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citas_canceladas` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cita_id` int(11) unsigned NOT NULL,
  `paciente_ci` int(11) unsigned NOT NULL,
  `estudio` varchar(150) NOT NULL,
  `medico` varchar(150) NOT NULL,
  `sucursal` varchar(150) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `estado_anterior` enum('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente',
  `motivo_cancelacion` varchar(100) NOT NULL DEFAULT 'cancelada_por_paciente',
  `cancelada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_paciente_ci` (`paciente_ci`),
  KEY `idx_cita_id` (`cita_id`),
  KEY `idx_cancelada_en` (`cancelada_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
