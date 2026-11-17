CREATE DATABASE IF NOT EXISTS taller_mecanico_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE taller_mecanico_v2;

CREATE TABLE empleados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('empleado', 'administrador') NOT NULL DEFAULT 'empleado',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_edicion DATETIME NULL,
    fecha_baja DATETIME NULL,
    usuario_alta_id INT UNSIGNED NULL,
    usuario_edicion_id INT UNSIGNED NULL,
    usuario_baja_id INT UNSIGNED NULL
) ENGINE=InnoDB;

CREATE TABLE clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    apellido VARCHAR(80) NOT NULL,
    dni VARCHAR(20) NOT NULL UNIQUE,
    telefono VARCHAR(40) NULL,
    email VARCHAR(160) NULL,
    direccion VARCHAR(200) NULL,
    ciudad VARCHAR(100) NULL,
    provincia VARCHAR(100) NULL,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_edicion DATETIME NULL,
    fecha_baja DATETIME NULL,
    usuario_alta_id INT UNSIGNED NULL,
    usuario_edicion_id INT UNSIGNED NULL,
    usuario_baja_id INT UNSIGNED NULL
) ENGINE=InnoDB;

CREATE TABLE vehiculos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    patente VARCHAR(20) NOT NULL UNIQUE,
    marca VARCHAR(80) NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    version VARCHAR(80) NULL,
    tipo VARCHAR(80) NULL,
    anio SMALLINT UNSIGNED NULL,
    kilometraje INT UNSIGNED NULL,
    color VARCHAR(50) NULL,
    combustible VARCHAR(40) NULL,
    transmision VARCHAR(40) NULL,
    motor VARCHAR(80) NULL,
    numero_motor VARCHAR(80) NULL,
    vin VARCHAR(80) NULL,
    imagen_principal VARCHAR(255) NULL,
    descripcion_ingreso TEXT NULL,
    observaciones TEXT NULL,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_edicion DATETIME NULL,
    fecha_baja DATETIME NULL,
    usuario_alta_id INT UNSIGNED NULL,
    usuario_edicion_id INT UNSIGNED NULL,
    usuario_baja_id INT UNSIGNED NULL,
    CONSTRAINT fk_vehiculo_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB;

CREATE TABLE ordenes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT UNSIGNED NOT NULL,
    empleado_id INT UNSIGNED NULL,
    diagnostico TEXT NOT NULL,
    motivo_ingreso TEXT NULL,
    observacion_extra TEXT NULL,
    reparacion TEXT NULL,
    estado ENUM('ingresado', 'en_reparacion', 'finalizado') NOT NULL DEFAULT 'ingresado',
    presupuesto DECIMAL(12,2) NULL,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_edicion DATETIME NULL,
    fecha_finalizacion DATETIME NULL,
    fecha_baja DATETIME NULL,
    usuario_alta_id INT UNSIGNED NULL,
    usuario_edicion_id INT UNSIGNED NULL,
    usuario_baja_id INT UNSIGNED NULL,
    CONSTRAINT fk_orden_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id),
    CONSTRAINT fk_orden_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id)
) ENGINE=InnoDB;

CREATE TABLE presupuesto_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orden_id INT UNSIGNED NOT NULL,
    tipo ENUM('pieza', 'mano_obra') NOT NULL,
    descripcion VARCHAR(160) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
    precio DECIMAL(12,2) NOT NULL,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL,
    usuario_alta_id INT UNSIGNED NULL,
    CONSTRAINT fk_presupuesto_orden FOREIGN KEY (orden_id) REFERENCES ordenes(id)
) ENGINE=InnoDB;

CREATE TABLE revision_imagenes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orden_id INT UNSIGNED NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL,
    usuario_alta_id INT UNSIGNED NULL,
    usuario_baja_id INT UNSIGNED NULL,
    CONSTRAINT fk_revision_imagen_orden FOREIGN KEY (orden_id) REFERENCES ordenes(id)
) ENGINE=InnoDB;

CREATE TABLE auditoria (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT UNSIGNED NULL,
    entidad VARCHAR(40) NOT NULL,
    entidad_id INT UNSIGNED NOT NULL,
    accion ENUM('alta', 'modificacion', 'baja') NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id)
) ENGINE=InnoDB;
