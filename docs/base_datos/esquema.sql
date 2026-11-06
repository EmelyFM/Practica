-- Esquema exportado de la base de datos real `gestion_reservas_vehiculos` (MySQL 5.5.25-log).
-- Solo estructura: no contiene datos ni credenciales.
-- No incluye DROP: ejecutarlo no borra información existente.

CREATE DATABASE IF NOT EXISTS `gestion_reservas_vehiculos` DEFAULT CHARACTER SET utf8;
USE `gestion_reservas_vehiculos`;

CREATE TABLE IF NOT EXISTS `roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_usuario` varchar(50) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  UNIQUE KEY `correo` (`correo`),
  KEY `id_rol` (`id_rol`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `empleados` (
  `id_empleado` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido_paterno` varchar(80) NOT NULL,
  `apellido_materno` varchar(80) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `puesto` varchar(80) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_empleado`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `empleados_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `vehiculos` (
  `id_vehiculo` int(11) NOT NULL AUTO_INCREMENT,
  `placa` varchar(20) NOT NULL,
  `marca` varchar(80) NOT NULL,
  `modelo` varchar(80) NOT NULL,
  `anio` int(11) NOT NULL,
  `tipo_vehiculo` varchar(80) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `capacidad_carga` decimal(10,2) DEFAULT NULL,
  `kilometraje` int(11) DEFAULT '0',
  `estado` enum('Disponible','Mantenimiento','Fuera de servicio') DEFAULT 'Disponible',
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `observaciones` text,
  PRIMARY KEY (`id_vehiculo`),
  UNIQUE KEY `placa` (`placa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `reservas` (
  `id_reserva` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_reserva` varchar(30) NOT NULL,
  `id_vehiculo` int(11) NOT NULL,
  `id_empleado` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `motivo` varchar(250) NOT NULL,
  `estado` enum('Pendiente','Confirmada','En uso','Finalizada','Cancelada') DEFAULT 'Pendiente',
  `fecha_devolucion` datetime DEFAULT NULL,
  `estado_devolucion` enum('Pendiente','Devuelto','Con observaciones') DEFAULT 'Pendiente',
  `observaciones` text,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reserva`),
  UNIQUE KEY `codigo_reserva` (`codigo_reserva`),
  KEY `id_vehiculo` (`id_vehiculo`),
  KEY `id_empleado` (`id_empleado`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `reservas_ibfk_1` FOREIGN KEY (`id_vehiculo`) REFERENCES `vehiculos` (`id_vehiculo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `reservas_ibfk_2` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `reservas_ibfk_3` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mantenimientos` (
  `id_mantenimiento` int(11) NOT NULL AUTO_INCREMENT,
  `id_vehiculo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo_mantenimiento` enum('Preventivo','Correctivo') NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT '0.00',
  `estado` enum('Programado','En proceso','Finalizado','Cancelado') DEFAULT 'Programado',
  `observaciones` text,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mantenimiento`),
  KEY `id_vehiculo` (`id_vehiculo`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `mantenimientos_ibfk_1` FOREIGN KEY (`id_vehiculo`) REFERENCES `vehiculos` (`id_vehiculo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `mantenimientos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `bitacora` (
  `id_bitacora` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(80) NOT NULL,
  `datos_anteriores` longtext,
  `datos_nuevos` longtext,
  `descripcion` text,
  PRIMARY KEY (`id_bitacora`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `bitacora_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

