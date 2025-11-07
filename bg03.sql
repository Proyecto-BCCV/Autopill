-- phpMyAdmin SQL Dump - MODIFICADO PARA EV--
-- Volcado de datos para la tabla `alarmas`
--

-- Sin datos de alarmas inicialesRORES DE EJECUCIÓN REPETIDA
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-09-2025 a las 21:44:39
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bg03`
--

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS `bg03`;
USE `bg03`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alarmas`
--

CREATE TABLE IF NOT EXISTS `alarmas` (
  `id_alarma` int(11) NOT NULL,
  `nombre_alarma` varchar(50) NOT NULL,
  `hora_alarma` time NOT NULL,
  `dias_semana` varchar(7) NOT NULL DEFAULT '1111111',
  `id_esp_alarma` int(11) NOT NULL,
  `modificado_por` char(6) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alarmas`
--

INSERT IGNORE INTO `alarmas` (`id_alarma`, `nombre_alarma`, `hora_alarma`, `dias_semana`, `id_esp_alarma`, `modificado_por`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(12, 'Módulo 1', '01:34:00', '0010000', 1008, '269234', '2025-09-10 04:32:55', '2025-09-10 04:32:55'),
(13, 'Módulo 3', '21:09:00', '0011100', 1008, '269234', '2025-09-15 21:36:50', '2025-09-15 21:36:50'),
(14, 'Módulo 4', '05:55:00', '0000011', 1008, '269234', '2025-09-15 21:37:32', '2025-09-15 21:37:32'),
(16, 'Módulo 2', '21:37:00', '1111100', 1008, '269234', '2025-09-15 21:44:45', '2025-09-15 21:44:45'),
(17, 'Módulo 5', '23:37:00', '0110000', 1008, '269234', '2025-09-17 19:39:06', '2025-09-17 20:41:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `autenticacion_google`
--

CREATE TABLE IF NOT EXISTS `autenticacion_google` (
  `id_usuario` char(6) NOT NULL,
  `google_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `autenticacion_local`
--

CREATE TABLE IF NOT EXISTS `autenticacion_local` (
  `id_usuario` char(6) NOT NULL,
  `contrasena_usuario` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `autenticacion_local`
--

-- Sin datos de autenticación local inicial

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `codigos_esp`
--

CREATE TABLE IF NOT EXISTS `codigos_esp` (
  `id_esp` int(11) NOT NULL,
  `id_usuario` char(6) DEFAULT NULL,
  `modulos_conectados_esp` varchar(5) NOT NULL DEFAULT '00000',
  `nombre_esp` varchar(20) DEFAULT NULL,
  `validado_fisicamente` tinyint(1) DEFAULT 0 COMMENT 'Se marca 1 cuando el dispositivo hace primer heartbeat real',
  `primera_conexion` timestamp NULL DEFAULT NULL COMMENT 'Momento del primer heartbeat del dispositivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `codigos_esp`
--

INSERT IGNORE INTO `codigos_esp` (`id_esp`, `id_usuario`, `modulos_conectados_esp`, `nombre_esp`, `validado_fisicamente`, `primera_conexion`) VALUES
(1007, NULL, '00000', 'ESP_pardocuidador', 1, '2025-10-03 19:00:00'),
(1008, NULL, '00000', 'ESP_pardopaciente', 1, '2025-10-03 19:00:00'),
(1009, NULL, '00000', 'ESP_alepandroyi', 1, '2025-10-03 19:00:00'),
(1010, NULL, '00000', 'ESP32_001', 1, '2025-10-03 19:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_usuario`
--

CREATE TABLE IF NOT EXISTS `configuracion_usuario` (
  `id_usuario` char(6) NOT NULL,
  `formato_hora_config` tinyint(1) DEFAULT 0,
  `modo_oscuro_config` tinyint(1) DEFAULT 0,
  `cuidador_flag_config` tinyint(1) DEFAULT 0,
  `notificaciones_config` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion_usuario`
--

-- Sin configuraciones de usuario inicial

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuidadores`
--

CREATE TABLE IF NOT EXISTS `cuidadores` (
  `id` char(6) NOT NULL,
  `paciente_id` char(6) NOT NULL,
  `cuidador_id` char(6) NOT NULL,
  `estado` enum('pendiente','activo','rechazado') DEFAULT 'pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cuidadores`
--

-- Sin relaciones cuidador-paciente inicial

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `esp32_alarm_exec`
--

CREATE TABLE IF NOT EXISTS `esp32_alarm_exec` (
  `id` int(11) NOT NULL,
  `id_esp` int(11) NOT NULL,
  `id_alarma` int(11) NOT NULL,
  `exec_minute` smallint(6) NOT NULL COMMENT 'minute of day (0-1439)',
  `ejecutado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `esp32_eventos`
--

CREATE TABLE IF NOT EXISTS `esp32_eventos` (
  `id` int(11) NOT NULL,
  `id_esp` int(11) DEFAULT NULL,
  `tipo_evento` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `datos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_json`)),
  `timestamp_evento` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `esp32_status`
--

CREATE TABLE IF NOT EXISTS `esp32_status` (
  `id` int(11) NOT NULL,
  `id_esp` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'online',
  `ultimo_heartbeat` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `firmware_version` varchar(50) DEFAULT NULL,
  `uptime_seconds` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos`
--

CREATE TABLE IF NOT EXISTS `modulos` (
  `id_modulo` int(11) NOT NULL,
  `id_usuario` char(6) DEFAULT NULL,
  `numero_modulo` int(11) NOT NULL,
  `nombre_medicamento` varchar(100) DEFAULT NULL,
  `dias_semana` varchar(20) DEFAULT NULL,
  `hora_toma` time DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `modulos`
--

-- Sin datos de módulos inicial

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `id_usuario_destinatario` char(6) NOT NULL,
  `id_usuario_origen` char(6) NOT NULL,
  `tipo_notificacion` varchar(50) NOT NULL,
  `id_alarma` int(11) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `detalles_json` longtext DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--

-- Sin notificaciones inicial

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_reset_tokens`
--

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` char(6) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `password_reset_tokens`
--

INSERT IGNORE INTO `password_reset_tokens` (`id`, `user_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(2, '342466', 'reset_2d0c6308bf77c76922918c960ef0d391aff5c7ca6f98d875558833ad9adf8b9c', '2025-09-17 19:21:10', 1, '2025-09-17 19:17:15'),
(7, '342466', 'reset_317b507a52c1753c45a0bcdb23e407669b465f548216d3defa8eff722dc715c6', '2025-09-17 19:41:59', 1, '2025-09-17 19:41:30'),
(3, '342466', 'reset_61de24eea0662f6bc233da14c6e7cfda0fb97bbac958648daee64ecf8797d7f5', '2025-09-17 19:20:58', 1, '2025-09-17 19:20:48'),
(1, '342466', 'reset_9edbfef3345a6c8de067abfb077fd698d2563f143bfcfa288703639cd811e812', '2025-09-17 19:21:10', 1, '2025-09-17 19:13:58'),
(6, '342466', 'reset_bd6319286e66aa4de2e7516b49e063e27ab1dbb4b4724f04cb8ef8096b71d3ff', '2025-09-17 19:35:20', 1, '2025-09-17 19:35:10'),
(5, '342466', 'reset_c03eda1a64e050f7d9084f1f06eccc0b20dfc8f7cd0e706bfe981544106b87f4', '2025-09-17 19:35:31', 1, '2025-09-17 19:27:38'),
(4, '342466', 'reset_f683077b28db98400711ad779e7b20682a245771fe6703d080ffda83d14cba1b', '2025-09-17 19:29:52', 1, '2025-09-17 19:25:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` char(6) NOT NULL,
  `email_usuario` varchar(50) NOT NULL,
  `nombre_usuario` varchar(20) NOT NULL,
  `url_foto_perfil` varchar(255) DEFAULT NULL,
  `email_verificado` tinyint(1) DEFAULT 0,
  `rol` enum('paciente','cuidador') DEFAULT 'paciente',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `first_login` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

-- Sin usuarios inicial

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `verification_codes`
--

CREATE TABLE IF NOT EXISTS `verification_codes` (
  `id` int(11) NOT NULL,
  `user_id` char(6) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `verification_codes`
--

INSERT IGNORE INTO `verification_codes` (`id`, `user_id`, `code`, `expires_at`, `used`, `created_at`) VALUES
(1, '342466', '998699', '2025-09-17 19:13:58', 1, '2025-09-17 19:03:35'),
(2, '342466', '024978', '2025-09-17 19:21:10', 1, '2025-09-17 19:13:58');

CREATE TABLE IF NOT EXISTS contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    comentario TEXT NOT NULL,
    fecha_envio DATETIME NOT NULL,
    leido BOOLEAN DEFAULT FALSE
);
--
-- Índices para tablas volcadas
--

-- Procedimientos para agregar índices y claves primarias de forma segura
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS AddPrimaryKeyIfNotExists(
    IN table_name VARCHAR(128),
    IN pk_columns VARCHAR(255)
)
BEGIN
    DECLARE pk_count INT DEFAULT 0;
    
    SELECT COUNT(1) INTO pk_count
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
    AND table_name = table_name
    AND constraint_type = 'PRIMARY KEY';
    
    IF pk_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name, '` ADD PRIMARY KEY (', pk_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE IF NOT EXISTS AddIndexIfNotExists(
    IN table_name VARCHAR(128),
    IN index_name VARCHAR(128), 
    IN index_definition TEXT
)
BEGIN
    DECLARE index_count INT DEFAULT 0;
    
    SELECT COUNT(1) INTO index_count
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
    AND table_name = table_name
    AND index_name = index_name;
    
    IF index_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name, '` ADD ', index_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

--
-- Indices de la tabla `alarmas`
--
CALL AddPrimaryKeyIfNotExists('alarmas', '`id_alarma`');
CALL AddIndexIfNotExists('alarmas', 'idx_alarma_esp', 'KEY `idx_alarma_esp` (`id_esp_alarma`)');

--
-- Indices de la tabla `autenticacion_google`
--
CALL AddPrimaryKeyIfNotExists('autenticacion_google', '`id_usuario`');

--
-- Indices de la tabla `autenticacion_local`
--
CALL AddPrimaryKeyIfNotExists('autenticacion_local', '`id_usuario`');

--
-- Indices de la tabla `codigos_esp`
--
CALL AddPrimaryKeyIfNotExists('codigos_esp', '`id_esp`');
CALL AddIndexIfNotExists('codigos_esp', 'idx_esp_usuario', 'KEY `idx_esp_usuario` (`id_usuario`)');

--
-- Indices de la tabla `configuracion_usuario`
--
CALL AddPrimaryKeyIfNotExists('configuracion_usuario', '`id_usuario`');

--
-- Indices de la tabla `cuidadores`
--
CALL AddPrimaryKeyIfNotExists('cuidadores', '`id`');
CALL AddIndexIfNotExists('cuidadores', 'unique_paciente_cuidador', 'UNIQUE KEY `unique_paciente_cuidador` (`paciente_id`,`cuidador_id`)');
CALL AddIndexIfNotExists('cuidadores', 'idx_cuidador', 'KEY `idx_cuidador` (`cuidador_id`)');

--
-- Indices de la tabla `esp32_alarm_exec`
--
CALL AddPrimaryKeyIfNotExists('esp32_alarm_exec', '`id`');
CALL AddIndexIfNotExists('esp32_alarm_exec', 'uniq_alarm_minute', 'UNIQUE KEY `uniq_alarm_minute` (`id_esp`,`id_alarma`,`exec_minute`)');
CALL AddIndexIfNotExists('esp32_alarm_exec', 'idx_exec_id_esp', 'KEY `idx_exec_id_esp` (`id_esp`)');
CALL AddIndexIfNotExists('esp32_alarm_exec', 'idx_exec_id_alarma', 'KEY `idx_exec_id_alarma` (`id_alarma`)');

--
-- Indices de la tabla `esp32_eventos`
--
CALL AddPrimaryKeyIfNotExists('esp32_eventos', '`id`');
CALL AddIndexIfNotExists('esp32_eventos', 'idx_eventos_id_esp', 'KEY `idx_eventos_id_esp` (`id_esp`)');
CALL AddIndexIfNotExists('esp32_eventos', 'idx_eventos_tipo', 'KEY `idx_eventos_tipo` (`tipo_evento`)');
CALL AddIndexIfNotExists('esp32_eventos', 'idx_eventos_timestamp', 'KEY `idx_eventos_timestamp` (`timestamp_evento`)');

--
-- Indices de la tabla `esp32_status`
--
CALL AddPrimaryKeyIfNotExists('esp32_status', '`id`');
CALL AddIndexIfNotExists('esp32_status', 'uniq_status_id_esp', 'UNIQUE KEY `uniq_status_id_esp` (`id_esp`)');
CALL AddIndexIfNotExists('esp32_status', 'idx_status_heartbeat', 'KEY `idx_status_heartbeat` (`ultimo_heartbeat`)');

--
-- Indices de la tabla `modulos`
--
CALL AddPrimaryKeyIfNotExists('modulos', '`id_modulo`');
CALL AddIndexIfNotExists('modulos', 'unique_user_module', 'UNIQUE KEY `unique_user_module` (`id_usuario`,`numero_modulo`)');

--
-- Indices de la tabla `notificaciones`
--
CALL AddPrimaryKeyIfNotExists('notificaciones', '`id_notificacion`');
CALL AddIndexIfNotExists('notificaciones', 'idx_notif_destinatario', 'KEY `idx_notif_destinatario` (`id_usuario_destinatario`)');
CALL AddIndexIfNotExists('notificaciones', 'idx_notif_origen', 'KEY `idx_notif_origen` (`id_usuario_origen`)');
CALL AddIndexIfNotExists('notificaciones', 'idx_notif_alarma', 'KEY `idx_notif_alarma` (`id_alarma`)');

--
-- Indices de la tabla `password_reset_tokens`
--
CALL AddPrimaryKeyIfNotExists('password_reset_tokens', '`id`');
CALL AddIndexIfNotExists('password_reset_tokens', 'unique_token', 'UNIQUE KEY `unique_token` (`token`)');
CALL AddIndexIfNotExists('password_reset_tokens', 'idx_reset_user', 'KEY `idx_reset_user` (`user_id`)');

--
-- Indices de la tabla `usuarios`
--
CALL AddPrimaryKeyIfNotExists('usuarios', '`id_usuario`');
CALL AddIndexIfNotExists('usuarios', 'unique_email_usuario', 'UNIQUE KEY `unique_email_usuario` (`email_usuario`)');

--
-- Indices de la tabla `verification_codes`
--
CALL AddPrimaryKeyIfNotExists('verification_codes', '`id`');
CALL AddIndexIfNotExists('verification_codes', 'idx_verif_user', 'KEY `idx_verif_user` (`user_id`)');

--
-- AUTO_INCREMENT de las tablas volcadas (de forma segura)
--

-- Procedimiento para agregar AUTO_INCREMENT de forma segura
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS SetAutoIncrementIfNeeded(
    IN table_name VARCHAR(128),
    IN column_name VARCHAR(128)
)
BEGIN
    DECLARE ai_count INT DEFAULT 0;
    
    SELECT COUNT(1) INTO ai_count
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = table_name
    AND column_name = column_name
    AND extra LIKE '%auto_increment%';
    
    IF ai_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name, '` MODIFY `', column_name, '` int(11) NOT NULL AUTO_INCREMENT');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

--
-- AUTO_INCREMENT de las tablas que lo necesiten
--
CALL SetAutoIncrementIfNeeded('alarmas', 'id_alarma');
CALL SetAutoIncrementIfNeeded('esp32_alarm_exec', 'id');
CALL SetAutoIncrementIfNeeded('esp32_eventos', 'id');
CALL SetAutoIncrementIfNeeded('esp32_status', 'id');
CALL SetAutoIncrementIfNeeded('modulos', 'id_modulo');
CALL SetAutoIncrementIfNeeded('notificaciones', 'id_notificacion');
CALL SetAutoIncrementIfNeeded('password_reset_tokens', 'id');
CALL SetAutoIncrementIfNeeded('verification_codes', 'id');

-- Limpiar procedimientos temporales
DROP PROCEDURE IF EXISTS AddPrimaryKeyIfNotExists;
DROP PROCEDURE IF EXISTS AddIndexIfNotExists;
DROP PROCEDURE IF EXISTS SetAutoIncrementIfNeeded;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
