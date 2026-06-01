CREATE DATABASE IF NOT EXISTS clinica_imagen_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinica_imagen_db;

-- 1. Tabla de Roles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE
);

-- 2. Tabla de Usuarios (Global para Login y Perfiles)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telefono VARCHAR(20),
    rol_id INT NOT NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT
);

-- 3. Tabla de Odontólogos Referenciantes
CREATE TABLE odontologos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    matricula VARCHAR(30) NOT NULL UNIQUE,
    clinica_procedencia VARCHAR(100)
);

-- 4. Tabla de Órdenes Médicas (Para pacientes referenciados)
CREATE TABLE ordenes_medicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    odontologo_id INT NOT NULL,
    estudio_autorizado VARCHAR(100) NOT NULL, -- Ej: 'Tomografía Dental' o 'Modelos 3D (DAM)'
    fecha_emision DATE NOT NULL,
    token_referencia VARCHAR(64) UNIQUE,
    FOREIGN KEY (paciente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (odontologo_id) REFERENCES odontologos(id) ON DELETE RESTRICT
);

-- 5. Tabla de Sucursales
CREATE TABLE sucursales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(150) NOT NULL,
    telefono VARCHAR(20)
);

-- 6. Gestión de Recursos (Equipos por Sucursal)
CREATE TABLE equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_equipo VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL, -- Ej: 'Radiografía Intraoral', 'Tomógrafo'
    sucursal_id INT NOT NULL,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE
);

-- 7. Catálogo de Estudios y Reglas de Reserva
CREATE TABLE estudios_catalogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_estudio VARCHAR(100) NOT NULL,
    requiere_orden BOOLEAN DEFAULT FALSE, -- TRUE: Tomografías, DAM, DOE / FALSE: Placa, Radiografía
    precio DECIMAL(10,2) NOT NULL
);

-- 8. Tabla Central de Citas / Agenda
CREATE TABLE citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    sucursal_id INT NOT NULL,
    estudio_id INT NOT NULL,
    fecha_hora DATETIME NOT NULL,
    estado ENUM('Pendiente', 'Confirmada', 'Cancelada', 'Realizada') DEFAULT 'Pendiente',
    orden_id INT NULL, 
    notas_tecnico TEXT NULL,
    informe_pdf VARCHAR(255) NULL, -- Ruta al archivo en el servidor
    FOREIGN KEY (paciente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE RESTRICT,
    FOREIGN KEY (estudio_id) REFERENCES estudios_catalogo(id) ON DELETE RESTRICT,
    FOREIGN KEY (orden_id) REFERENCES ordenes_medicas(id) ON DELETE SET NULL
);

-- Inserción de Datos 
INSERT INTO roles (nombre_rol) VALUES ('Administrador'), ('Recepcionista'), ('Tecnico'), ('Paciente');
INSERT INTO estudios_catalogo (nombre_estudio, requiere_orden, precio) VALUES 
('Placa Dental Simple', FALSE, 1200.00),
('Radiografía Panorámica', FALSE, 2500.00),
('Tomografía Dental Axial', TRUE, 5800.00),
('Modelado 3D (DAM / DOE)', TRUE, 7500.00);