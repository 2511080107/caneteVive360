USE [CANETE-VIVE-360];

CREATE TABLE DISTRITO (
    id_distrito BIGINT IDENTITY(1,1) PRIMARY KEY,
    nombre VARCHAR(80),
    descripcion TEXT
);

CREATE TABLE CATEGORIA (
    id_categoria BIGINT IDENTITY(1,1) PRIMARY KEY,
    nombre VARCHAR(60)
);

CREATE TABLE TURISTA (
    id_turista BIGINT IDENTITY(1,1) PRIMARY KEY,
    nombres VARCHAR(100),
    apellidos VARCHAR(100),
    correo VARCHAR(120) UNIQUE
);

CREATE TABLE ATRACTIVO (
    id_atractivo BIGINT IDENTITY(1,1) PRIMARY KEY,
    id_distrito BIGINT,
    id_categoria BIGINT,
    nombre VARCHAR(120),
    descripcion TEXT,
    direccion VARCHAR(180),
    latitud NUMERIC(10,7),
    longitud NUMERIC(10,7),

    FOREIGN KEY (id_distrito)
        REFERENCES DISTRITO(id_distrito),

    FOREIGN KEY (id_categoria)
        REFERENCES CATEGORIA(id_categoria)
);

CREATE TABLE SERVICIO (
    id_servicio BIGINT IDENTITY(1,1) PRIMARY KEY,
    id_distrito BIGINT,
    nombre VARCHAR(120),
    tipo_servicio VARCHAR(40),
    telefono VARCHAR(20),
    direccion VARCHAR(180),

    FOREIGN KEY (id_distrito)
        REFERENCES DISTRITO(id_distrito)
);

CREATE TABLE RUTA (
    id_ruta BIGINT IDENTITY(1,1) PRIMARY KEY,
    nombre VARCHAR(120),
    descripcion TEXT,
    duracion_horas NUMERIC(4,1)
);

CREATE TABLE DETALLE_RUTA (
    id_detalle BIGINT IDENTITY(1,1) PRIMARY KEY,
    id_ruta BIGINT,
    id_atractivo BIGINT,
    orden_visita INTEGER,

    FOREIGN KEY (id_ruta)
        REFERENCES RUTA(id_ruta),

    FOREIGN KEY (id_atractivo)
        REFERENCES ATRACTIVO(id_atractivo)
);

CREATE TABLE VISITA (
    id_visita BIGINT IDENTITY(1,1) PRIMARY KEY,
    id_turista BIGINT,
    id_atractivo BIGINT,
    fecha_visita DATE,
    comentario TEXT,

    FOREIGN KEY (id_turista)
        REFERENCES TURISTA(id_turista),

    FOREIGN KEY (id_atractivo)
        REFERENCES ATRACTIVO(id_atractivo)
);

--- DB A USAR ---

USE canetevive360;

CREATE TABLE distrito (
    id_distrito BIGINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80),
    descripcion TEXT
);

CREATE TABLE categoria (
    id_categoria BIGINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60)
);

CREATE TABLE turista (
    id_turista BIGINT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100),
    apellidos VARCHAR(100),
    correo VARCHAR(120) UNIQUE
);

CREATE TABLE servicio (
    id_servicio BIGINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120),
    telefono VARCHAR(20),
    direccion VARCHAR(180)
) ;

CREATE TABLE atractivo (
    id_atractivo BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_distrito BIGINT,
    id_categoria BIGINT,
    nombre VARCHAR(120),
    descripcion TEXT,
    direccion VARCHAR(180),
    latitud DECIMAL(10,7),
    longitud DECIMAL(10,7),
    CONSTRAINT fk_atractivo_distrito FOREIGN KEY (id_distrito) REFERENCES distrito(id_distrito),
    CONSTRAINT fk_atractivo_categoria FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria)
);

CREATE TABLE ruta (
    id_ruta BIGINT AUTO_INCREMENT PRIMARY KEY,
    duracion_horas DECIMAL(4,1),
    id_atractivo BIGINT,
    orden_visita INT,
    CONSTRAINT fk_ruta_atractivo FOREIGN KEY (id_atractivo) REFERENCES atractivo(id_atractivo)
);

CREATE TABLE detalle_ruta (
    id_detalle BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_ruta BIGINT,
    id_servicio BIGINT,
    id_turista BIGINT,
    CONSTRAINT fk_detalle_ruta FOREIGN KEY (id_ruta) REFERENCES ruta(id_ruta),
    CONSTRAINT fk_detalle_servicio FOREIGN KEY (id_servicio) REFERENCES servicio(id_servicio),
    CONSTRAINT fk_detalle_turista FOREIGN KEY (id_turista) REFERENCES turista(id_turista)
);