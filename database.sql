CREATE DATABASE IF NOT EXISTS clinica_imagen_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinica_imagen_db;

-- ==========================================
-- 1. TABLA DE ROLES
-- ==========================================
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE
);

-- Roles oficiales y consistentes para el sistema
INSERT INTO roles (nombre_rol) VALUES 
('Socio'), 
('Empleado_Admin'), 
('Empleado_Recepcion'), 
('Empleado_Tecnico');

-- ==========================================
-- 2. TABLA DE USUARIOS (Global para Auth y Perfiles)
-- ==========================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    -- Campos NULL porque el registro inicial web es simplificado (CI + Pass + Rol)
    nombre VARCHAR(50) NULL,
    apellido VARCHAR(50) NULL,
    email VARCHAR(100) NULL UNIQUE,
    telefono VARCHAR(20) NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT,
    INDEX idx_cedula (cedula) -- Optimiza el Login de la app
);

-- ==========================================
-- 3. TABLA DE ODONTÓLOGOS REFERENCIANTES (Externos)
-- ==========================================
CREATE TABLE odontologos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    matricula VARCHAR(30) NOT NULL UNIQUE,
    clinica_procedencia VARCHAR(100) NULL
);

-- ==========================================
-- 4. TABLA DE SUCURSALES
-- ==========================================
CREATE TABLE sucursales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NULL
);

-- ==========================================
-- 5. CATÁLOGO DE ESTUDIOS
-- ==========================================
CREATE TABLE estudios_catalogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_estudio VARCHAR(100) NOT NULL,
    requiere_orden BOOLEAN DEFAULT FALSE, -- TRUE para Tomografías/3D, FALSE para Placas
    precio DECIMAL(10,2) NOT NULL
);

-- Inserción del catálogo base de la clínica
INSERT INTO estudios_catalogo (nombre_estudio, requiere_orden, precio) VALUES 
('Placa Dental Simple', FALSE, 1200.00),
('Radiografía Panorámica', FALSE, 2500.00),
('Tomografía Dental Axial', TRUE, 5800.00),
('Modelado 3D (DAM / DOE)', TRUE, 7500.00);

-- ==========================================
-- 6. GESTIÓN DE RECURSOS (Equipos tecnológicos)
-- ==========================================
CREATE TABLE equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_equipo VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL, -- Ej: 'Radiógrafo', 'Tomógrafo'
    sucursal_id INT NOT NULL,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE
);

-- ==========================================
-- 7. TABLA DE ÓRDENES MÉDICAS
-- ==========================================
CREATE TABLE ordenes_medicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    odontologo_id INT NOT NULL,
    estudio_id INT NOT NULL, -- Vincula al catálogo real de estudios
    fecha_emision DATE NOT NULL,
    token_referencia VARCHAR(64) NOT NULL UNIQUE,
    FOREIGN KEY (paciente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (odontologo_id) REFERENCES odontologos(id) ON DELETE RESTRICT,
    FOREIGN KEY (estudio_id) REFERENCES estudios_catalogo(id) ON DELETE RESTRICT
);

-- ==========================================
-- 8. TABLA CENTRAL DE CITAS / AGENDA
-- ==========================================
CREATE TABLE citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    sucursal_id INT NOT NULL,
    estudio_id INT NOT NULL,
    equipo_id INT NULL, -- Vincula el equipo físico asignado de la sucursal
    orden_id INT NULL,  -- Obligatorio si el estudio requiere orden (requiere_orden = TRUE)
    fecha_hora DATETIME NOT NULL,
    estado ENUM('Pendiente', 'Confirmada', 'Cancelada', 'Realizada') DEFAULT 'Pendiente',
    notas_tecnico TEXT NULL,
    informe_pdf VARCHAR(255) NULL, -- Ruta del archivo guardado en el servidor backend
    
    -- Definición de Claves Foráneas (Foreign Keys)
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE SET NULL ON UPDATE CASCADE
);