/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.20-11.8.9-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: naser_sgi_prueba
-- ------------------------------------------------------
-- Server version	11.8.9-MariaDB-ubu2404

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `actividad`
--

DROP TABLE IF EXISTS `actividad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `actividad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(80) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_a_user` (`usuario_id`),
  KEY `idx_a_fecha` (`fecha`),
  CONSTRAINT `fk_a_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `actividad`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `actividad` WRITE;
/*!40000 ALTER TABLE `actividad` DISABLE KEYS */;
INSERT INTO `actividad` VALUES
(1,1,'login','Inicio de sesión','2026-09-03 13:29:01'),
(2,1,'eliminar_carpeta','Carpeta 1 y 0 subcarpetas','2026-09-03 13:36:39'),
(3,1,'eliminar_carpeta','Carpeta 10 y 0 subcarpetas','2026-09-03 14:43:56'),
(4,1,'eliminar_carpeta','Carpeta 2 y 0 subcarpetas','2026-09-03 14:44:00'),
(5,1,'eliminar_carpeta','Carpeta 11 y 0 subcarpetas','2026-09-03 14:44:03'),
(6,1,'eliminar_carpeta','Carpeta 12 y 0 subcarpetas','2026-09-03 14:44:07'),
(7,1,'eliminar_carpeta','Carpeta 13 y 0 subcarpetas','2026-09-03 14:44:11'),
(8,1,'login','Inicio de sesión','2026-09-04 12:30:41'),
(9,1,'crear_carpeta','56','2026-09-04 12:31:37'),
(10,1,'eliminar_carpeta','Carpeta 16 y 0 subcarpetas','2026-09-04 12:31:44'),
(11,1,'eliminar_carpeta','Carpeta 15 y 0 subcarpetas','2026-09-04 12:32:24'),
(12,1,'eliminar_carpeta','Carpeta 14 y 0 subcarpetas','2026-09-04 12:32:26'),
(13,1,'eliminar_carpeta','Carpeta 17 y 0 subcarpetas','2026-09-04 12:32:31'),
(14,1,'eliminar_carpeta','Carpeta 19 y 0 subcarpetas','2026-09-04 12:50:06'),
(15,1,'eliminar_carpeta','Carpeta 18 y 0 subcarpetas','2026-09-04 12:50:09'),
(16,1,'eliminar_carpeta','Carpeta 3 y 0 subcarpetas','2026-09-04 12:50:13'),
(17,1,'eliminar_carpeta','Carpeta 4 y 0 subcarpetas','2026-09-04 12:50:15'),
(18,1,'eliminar_carpeta','Carpeta 5 y 0 subcarpetas','2026-09-04 12:50:18'),
(19,1,'eliminar_carpeta','Carpeta 6 y 0 subcarpetas','2026-09-04 12:50:21'),
(20,1,'eliminar_carpeta','Carpeta 7 y 0 subcarpetas','2026-09-04 12:50:23'),
(21,1,'eliminar_carpeta','Carpeta 8 y 0 subcarpetas','2026-09-04 12:50:26'),
(22,1,'eliminar_carpeta','Carpeta 9 y 0 subcarpetas','2026-09-04 12:50:29'),
(23,1,'carga_zip','Sector 1: 361 cargados, 25 omitidos','2026-09-04 12:51:45'),
(24,1,'eliminar_carpeta','Carpeta 20 y 28 subcarpetas','2026-09-04 12:52:16'),
(25,1,'carga_zip','Sector 9: 361 cargados, 25 omitidos','2026-09-04 12:53:03'),
(26,1,'eliminar_carpeta','Carpeta 49 y 28 subcarpetas','2026-09-04 12:57:30'),
(27,1,'carga_zip','Sector 9: 361 cargados, 25 omitidos','2026-09-04 13:21:16'),
(28,1,'eliminar_carpeta','Carpeta 78 y 28 subcarpetas','2026-09-04 13:22:59'),
(29,1,'carga_zip','Sector 9: 361 cargados, 25 omitidos','2026-09-04 13:25:42'),
(30,1,'carga_zip','Sector 9: 6 cargados, 0 omitidos','2026-09-04 13:26:51'),
(31,1,'login','Inicio de sesión','2026-09-04 13:38:19'),
(32,1,'eliminar_carpeta','Carpeta 137 y 0 subcarpetas','2026-09-04 13:38:37'),
(33,1,'eliminar_carpeta','Carpeta 138 y 0 subcarpetas','2026-09-04 13:38:41'),
(34,1,'eliminar_carpeta','Carpeta 139 y 0 subcarpetas','2026-09-04 13:38:44'),
(35,1,'eliminar_carpeta','Carpeta 140 y 0 subcarpetas','2026-09-04 13:38:47'),
(36,1,'eliminar_carpeta','Carpeta 141 y 0 subcarpetas','2026-09-04 13:38:50'),
(37,1,'eliminar_carpeta','Carpeta 142 y 0 subcarpetas','2026-09-04 13:38:55'),
(38,1,'editar_carpeta','07 - Checklists','2026-09-04 13:38:57'),
(39,1,'eliminar_carpeta','Carpeta 143 y 0 subcarpetas','2026-09-04 13:39:01'),
(40,1,'eliminar_carpeta','Carpeta 144 y 0 subcarpetas','2026-09-04 13:39:05'),
(41,1,'eliminar_carpeta','Carpeta 145 y 0 subcarpetas','2026-09-04 13:39:08'),
(42,1,'carga_zip','Sector 9: 31 cargados, 1 omitidos','2026-09-04 13:54:27'),
(43,1,'login','Inicio de sesión','2026-09-04 14:33:47'),
(44,1,'carga_zip','Sector 9: 0 cargados, 6 omitidos','2026-09-04 14:34:07'),
(45,1,'login','Inicio de sesión','2026-09-04 15:05:53'),
(46,1,'eliminar_carpeta','Carpeta 147 y 0 subcarpetas','2026-09-04 15:09:52'),
(47,1,'eliminar_carpeta','Carpeta 148 y 0 subcarpetas','2026-09-04 15:09:56'),
(48,1,'eliminar_carpeta','Carpeta 149 y 0 subcarpetas','2026-09-04 15:09:59'),
(49,1,'eliminar_carpeta','Carpeta 150 y 0 subcarpetas','2026-09-04 15:10:02'),
(50,1,'eliminar_carpeta','Carpeta 151 y 0 subcarpetas','2026-09-04 15:10:05'),
(51,1,'eliminar_carpeta','Carpeta 152 y 0 subcarpetas','2026-09-04 15:10:08'),
(52,1,'eliminar_carpeta','Carpeta 153 y 0 subcarpetas','2026-09-04 15:10:12'),
(53,1,'eliminar_carpeta','Carpeta 154 y 0 subcarpetas','2026-09-04 15:10:16'),
(54,1,'eliminar_carpeta','Carpeta 155 y 0 subcarpetas','2026-09-04 15:10:20'),
(55,1,'carga_zip','Sector 9: 429 cargados, 11 omitidos','2026-09-04 15:11:08'),
(56,1,'carga_zip','Sector 9: 0 cargados, 440 omitidos','2026-09-04 15:11:14'),
(57,1,'login','Inicio de sesión','2026-09-07 12:17:58'),
(58,1,'eliminar_carpeta','Carpeta 107 y 28 subcarpetas','2026-09-07 12:21:03'),
(59,1,'eliminar_carpeta','Carpeta 146 y 0 subcarpetas','2026-09-07 12:21:37'),
(60,1,'eliminar_carpeta','Carpeta 136 y 0 subcarpetas','2026-09-07 12:21:41'),
(61,1,'eliminar_carpeta','Carpeta 156 y 173 subcarpetas','2026-09-07 12:21:49'),
(62,1,'carga_zip','Sector 1: 361 cargados, 25 omitidos','2026-09-07 14:49:37'),
(63,1,'eliminar_carpeta','Carpeta 331 y 5 subcarpetas','2026-09-07 14:50:21'),
(64,1,'eliminar_carpeta','Carpeta 330 y 0 subcarpetas','2026-09-07 14:50:25'),
(65,1,'eliminar_carpeta','Carpeta 337 y 28 subcarpetas','2026-09-07 14:51:45'),
(66,1,'carga_zip','Sector 9: 361 cargados, 25 omitidos','2026-09-07 14:52:15'),
(67,1,'eliminar_carpeta','Carpeta 366 y 28 subcarpetas','2026-09-07 15:27:15'),
(68,1,'login','Inicio de sesión','2026-09-08 12:25:45'),
(69,1,'finanzas_indicador','aDAD','2026-09-08 15:36:19'),
(70,1,'finanzas_eliminar','finanzas_indicadores #1','2026-09-08 15:36:22'),
(71,1,'login','Inicio de sesión','2026-09-09 13:12:03'),
(72,1,'login','Inicio de sesión','2026-09-09 13:12:03'),
(73,1,'compras_guardar','Compra #1','2026-09-09 13:12:34'),
(74,1,'compras_eliminar','Compra #1','2026-09-09 13:20:28'),
(75,1,'finanzas_indicador','aDAD','2026-09-09 13:21:53'),
(76,1,'finanzas_eliminar','finanzas_indicadores #2','2026-09-09 13:26:09'),
(77,1,'finanzas_indicador','aDAD','2026-09-09 13:26:22'),
(78,1,'finanzas_eliminar','finanzas_indicadores #3','2026-09-09 13:26:29'),
(79,1,'finanzas_eliminar','finanzas_indicadores #3','2026-09-09 13:44:56'),
(80,1,'finanzas_trabajador','Silvio Oksman','2026-09-09 13:45:51'),
(81,1,'finanzas_eliminar','finanzas_trabajadores #1','2026-09-09 13:46:16'),
(82,1,'login','Inicio de sesión','2026-09-14 12:24:46'),
(83,1,'login','Inicio de sesión','2026-09-15 13:04:39');
/*!40000 ALTER TABLE `actividad` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `carpetas`
--

DROP TABLE IF EXISTS `carpetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `carpetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `carpeta_padre_id` int(11) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_c_padre` (`carpeta_padre_id`),
  KEY `idx_c_sector_padre` (`sector_id`,`carpeta_padre_id`),
  CONSTRAINT `fk_c_padre` FOREIGN KEY (`carpeta_padre_id`) REFERENCES `carpetas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_c_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=424 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carpetas`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `carpetas` WRITE;
/*!40000 ALTER TABLE `carpetas` DISABLE KEYS */;
INSERT INTO `carpetas` VALUES
(395,9,'1 SG Documentos',NULL,999,1),
(396,9,'1 Política',395,999,1),
(397,9,'MG-SN-01 Manual de Gestión',395,999,1),
(398,9,'MS-SN-01 Manual de Seguridad e Higiene',395,999,1),
(399,9,'PG-SN-01 Control de información documentada',395,999,1),
(400,9,'PG-SN-02 Gestión de las personas',395,999,1),
(401,9,'PGSN02-F1 Perfil de puesto',400,999,1),
(402,9,'PG-SN-03 Gestión de compras',395,999,1),
(403,9,'PG-SN-04 Evaluación de desempeño  y Mejora',395,999,1),
(404,9,'PG-SN-05 Legislación Aplicable',395,999,1),
(405,9,'PG-SN-06 Gestión Ambiental',395,999,1),
(406,9,'PG-SN-07 Gestión del Riesgo',395,999,1),
(407,9,'PG-SN-08 Respuesta ante emergencias',395,999,1),
(408,9,'PG-SN-13 Comunicaciones',407,999,1),
(409,9,'PG-SN-09 Gestión de Residuos',395,999,1),
(410,9,'PG-SN-10 Investigación de incidentes',395,999,1),
(411,9,'PG-SN-11 Cotizaciones',395,999,1),
(412,9,'PG-SN-12 Bienes y propiedad del cliente',395,999,1),
(413,9,'PO-SN-01 Mantenimiento de equipos',395,999,1),
(414,9,'PO-SN-02 Procedimiento Slick Line',395,999,1),
(415,9,'Instructivos de trabajo SL',414,999,1),
(416,9,'Manual de procedimientos',414,999,1),
(417,9,'PO-SN-03 Servicio de Well Testing',395,999,1),
(418,9,'PO-SN-04 Planificación del servicio',395,999,1),
(419,9,'PO-SN-05 Manejo del cambio',395,999,1),
(420,9,'PO-SN-06 Uso de vehículos',395,999,1),
(421,9,'PO-SN-07 Trabajo en altura',395,999,1),
(422,9,'PO-SN-08 Control y trazabilidad',395,999,1),
(423,9,'Z Documentos editables',395,999,1);
/*!40000 ALTER TABLE `carpetas` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `compras`
--

DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `sector` varchar(50) NOT NULL,
  `descripcion` text NOT NULL,
  `cantidad` int(11) DEFAULT 1,
  `prioridad` enum('Normal','Urgente','Critico') DEFAULT 'Normal',
  `proveedor` varchar(100) DEFAULT 'Pendiente',
  `etapa` int(11) DEFAULT 1,
  `estado_logistico` varchar(255) DEFAULT 'Pedido cargado',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `sector_id` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `compras` WRITE;
/*!40000 ALTER TABLE `compras` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `compras_documentos`
--

DROP TABLE IF EXISTS `compras_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras_documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compra_id` int(11) NOT NULL,
  `etapa` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_compra_doc` (`compra_id`),
  CONSTRAINT `fk_compra_doc` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras_documentos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `compras_documentos` WRITE;
/*!40000 ALTER TABLE `compras_documentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras_documentos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `compras_encuesta`
--

DROP TABLE IF EXISTS `compras_encuesta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras_encuesta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compra_id` int(11) NOT NULL,
  `calidad` varchar(50) NOT NULL,
  `estado_fisico` varchar(50) NOT NULL,
  `cumplimiento_entrega` varchar(50) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_inspeccion` timestamp NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_compra_enc` (`compra_id`),
  CONSTRAINT `fk_compra_enc` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras_encuesta`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `compras_encuesta` WRITE;
/*!40000 ALTER TABLE `compras_encuesta` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras_encuesta` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `documento_versiones`
--

DROP TABLE IF EXISTS `documento_versiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documento_versiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `documento_id` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dv_doc` (`documento_id`),
  KEY `fk_dv_user` (`usuario_id`),
  CONSTRAINT `fk_dv_doc` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dv_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documento_versiones`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `documento_versiones` WRITE;
/*!40000 ALTER TABLE `documento_versiones` DISABLE KEYS */;
/*!40000 ALTER TABLE `documento_versiones` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `documentos`
--

DROP TABLE IF EXISTS `documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `carpeta_id` int(11) DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('documentacion','procedimiento','formulario','registro','checklist','certificado','otro') NOT NULL DEFAULT 'documentacion',
  `archivo` varchar(255) DEFAULT NULL,
  `nombre_original` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_actualizacion` date NOT NULL,
  `estado` enum('borrador','revision','aprobado','obsoleto') NOT NULL DEFAULT 'aprobado',
  `version` varchar(20) NOT NULL DEFAULT '1.0',
  `fecha_vencimiento` date DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_d_carpeta` (`carpeta_id`),
  KEY `fk_d_usuario` (`creado_por`),
  KEY `idx_d_sector_carpeta` (`sector_id`,`carpeta_id`),
  KEY `idx_d_titulo` (`titulo`),
  CONSTRAINT `fk_d_carpeta` FOREIGN KEY (`carpeta_id`) REFERENCES `carpetas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_d_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_d_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2999 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `documentos` WRITE;
/*!40000 ALTER TABLE `documentos` DISABLE KEYS */;
INSERT INTO `documentos` VALUES
(2638,9,396,'PE-01 Política de Calidad Ambiente Seguridad y Salud','','documentacion','sgi/1 SG Documentos/1 Política/PE-01 Política de Calidad Ambiente Seguridad y Salud.pdf','PE-01 Política de Calidad Ambiente Seguridad y Salud.pdf',1,'2025-10-08','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2639,9,395,'Copia de PGSN01-F1 Listado maestro de documentos','','documentacion','sgi/1 SG Documentos/Copia de PGSN01-F1 Listado maestro de documentos.xlsx','Copia de PGSN01-F1 Listado maestro de documentos.xlsx',1,'2026-08-19','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2640,9,397,'MG-SN-01 Manual de gestión','','documentacion','sgi/1 SG Documentos/MG-SN-01 Manual de Gestión/MG-SN-01 Manual de gestión.pdf','MG-SN-01 Manual de gestión.pdf',1,'2025-12-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2641,9,397,'MGSN01-A1 Mapa de procesos operaciones','','documentacion','sgi/1 SG Documentos/MG-SN-01 Manual de Gestión/MGSN01-A1 Mapa de procesos operaciones.pdf','MGSN01-A1 Mapa de procesos operaciones.pdf',1,'2025-10-09','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2642,9,397,'MGSN01-A2 Organigrama','','documentacion','sgi/1 SG Documentos/MG-SN-01 Manual de Gestión/MGSN01-A2 Organigrama.pdf','MGSN01-A2 Organigrama.pdf',1,'2026-01-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2643,9,397,'MGSN01-A3 Análisis de contexto','','documentacion','sgi/1 SG Documentos/MG-SN-01 Manual de Gestión/MGSN01-A3 Análisis de contexto.pdf','MGSN01-A3 Análisis de contexto.pdf',1,'2025-10-09','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2644,9,397,'MGSN01-A4 Objetivos e Indicadores','','documentacion','sgi/1 SG Documentos/MG-SN-01 Manual de Gestión/MGSN01-A4 Objetivos e Indicadores.pdf','MGSN01-A4 Objetivos e Indicadores.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2645,9,397,'MGSN01-A5 Matriz Riesgos y Oportunidades','','documentacion','sgi/1 SG Documentos/MG-SN-01 Manual de Gestión/MGSN01-A5 Matriz Riesgos y Oportunidades.pdf','MGSN01-A5 Matriz Riesgos y Oportunidades.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2646,9,397,'MGSN01-A6 Reglamento de ética','','documentacion','sgi/1 SG Documentos/MG-SN-01 Manual de Gestión/MGSN01-A6 Reglamento de ética.pdf','MGSN01-A6 Reglamento de ética.pdf',1,'2025-08-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2647,9,398,'MS-SN-01 Manual de Seguridad e Higiene','','documentacion','sgi/1 SG Documentos/MS-SN-01 Manual de Seguridad e Higiene/MS-SN-01 Manual de Seguridad e Higiene.pdf','MS-SN-01 Manual de Seguridad e Higiene.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:54'),
(2648,9,399,'PG-SN-01 Control de información documentada','','documentacion','sgi/1 SG Documentos/PG-SN-01 Control de información documentada/PG-SN-01 Control de información documentada.pdf','PG-SN-01 Control de información documentada.pdf',1,'2025-10-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2649,9,399,'PGSN01-F1 Listado maestro de documentos','','documentacion','sgi/1 SG Documentos/PG-SN-01 Control de información documentada/PGSN01-F1 Listado maestro de documentos.pdf','PGSN01-F1 Listado maestro de documentos.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2650,9,399,'PGSN01-F2 Control de distribución de documentos','','documentacion','sgi/1 SG Documentos/PG-SN-01 Control de información documentada/PGSN01-F2 Control de distribución de documentos.pdf','PGSN01-F2 Control de distribución de documentos.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2651,9,400,'PG-SN-02 Gestión de las personas','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PG-SN-02 Gestión de las personas.pdf','PG-SN-02 Gestión de las personas.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2652,9,400,'PGSN02-A1 Reglamento interno','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-A1 Reglamento interno.pdf','PGSN02-A1 Reglamento interno.pdf',1,'2025-08-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2653,9,401,'Perfil de puesto','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/Perfil de puesto.docx','Perfil de puesto.docx',1,'2024-12-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2654,9,401,'PGSN02-F1 Perfil de puesto','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/PGSN02-F1 Perfil de puesto.docx','PGSN02-F1 Perfil de puesto.docx',1,'2025-07-08','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2655,9,401,'PGSN02-F1 Perfil de puesto','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/PGSN02-F1 Perfil de puesto.xlsx','PGSN02-F1 Perfil de puesto.xlsx',1,'2025-07-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2656,9,401,'PGSN02-F14 - Gerente de operaciones','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/PGSN02-F14 - Gerente de operaciones.docx','PGSN02-F14 - Gerente de operaciones.docx',1,'2025-05-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2657,9,401,'PGSN02-F14 - Operador de Slick Line','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/PGSN02-F14 - Operador de Slick Line.docx','PGSN02-F14 - Operador de Slick Line.docx',1,'2024-07-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2658,9,401,'PGSN02-F14 - Referente HSE','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/PGSN02-F14 - Referente HSE.docx','PGSN02-F14 - Referente HSE.docx',1,'2024-07-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2659,9,401,'PGSN02-F14 - Representante Técnico','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/PGSN02-F14 - Representante Técnico.docx','PGSN02-F14 - Representante Técnico.docx',1,'2026-06-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2660,9,401,'PGSN02-F14 - Representante Técnico','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/PGSN02-F14 - Representante Técnico.pdf','PGSN02-F14 - Representante Técnico.pdf',1,'2026-06-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2661,9,401,'PGSN02-F14 - Responsable de Sistema de Gestión','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/PGSN02-F14 - Responsable de Sistema de Gestión.docx','PGSN02-F14 - Responsable de Sistema de Gestión.docx',1,'2024-07-10','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2662,9,401,'PGSN02-F14 - Supervisor de Operaciones','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto/PGSN02-F14 - Supervisor de Operaciones.docx','PGSN02-F14 - Supervisor de Operaciones.docx',1,'2024-07-10','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2663,9,400,'PGSN02-F1 Perfil de puesto','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F1 Perfil de puesto.pdf','PGSN02-F1 Perfil de puesto.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2664,9,400,'PGSN02-F10 Sanción disciplinaria','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F10 Sanción disciplinaria.pdf','PGSN02-F10 Sanción disciplinaria.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2665,9,400,'PGSN02-F11 Plan de carrera','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F11 Plan de carrera.pdf','PGSN02-F11 Plan de carrera.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2666,9,400,'PGSN02-F12 Evaluación de desempeño del personal','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F12 Evaluación de desempeño del personal.pdf','PGSN02-F12 Evaluación de desempeño del personal.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2667,9,400,'PGSN02-F2 Entrevista al personal ingresante','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F2 Entrevista al personal ingresante.pdf','PGSN02-F2 Entrevista al personal ingresante.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2668,9,400,'PGSN02-F3 Informe medico laboral','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F3 Informe medico laboral.pdf','PGSN02-F3 Informe medico laboral.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2669,9,400,'PGSN02-F4 Listado de trabajadores','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F4 Listado de trabajadores.pdf','PGSN02-F4 Listado de trabajadores.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2670,9,400,'PGSN02-F5 Formulario de ingreso','','formulario','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F5 Formulario de ingreso.pdf','PGSN02-F5 Formulario de ingreso.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2671,9,400,'PGSN02-F6 Registro de entrega de EPP','','registro','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F6 Registro de entrega de EPP.pdf','PGSN02-F6 Registro de entrega de EPP.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2672,9,400,'PGSN02-F7 Registro de formación','','registro','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F7 Registro de formación.pdf','PGSN02-F7 Registro de formación.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2673,9,400,'PGSN02-F8 Diagrama de trabajo','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F8 Diagrama de trabajo.pdf','PGSN02-F8 Diagrama de trabajo.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2674,9,400,'PGSN02-F9 Comunicación de licencias y vacaciones','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/PGSN02-F9 Comunicación de licencias y vacaciones.pdf','PGSN02-F9 Comunicación de licencias y vacaciones.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2675,9,400,'Recepción de CV','','documentacion','sgi/1 SG Documentos/PG-SN-02 Gestión de las personas/Recepción de CV.pdf','Recepción de CV.pdf',1,'2025-06-18','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2676,9,402,'PG-SN-03 Gestión de compras','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PG-SN-03 Gestión de compras.pdf','PG-SN-03 Gestión de compras.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2677,9,402,'PGSN03-F1 Pedido de Materiales y servicios','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PGSN03-F1 Pedido de Materiales y servicios.pdf','PGSN03-F1 Pedido de Materiales y servicios.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2678,9,402,'PGSN03-F2 Listado de proveedores, productos y servicios','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PGSN03-F2 Listado de proveedores, productos y servicios.pdf','PGSN03-F2 Listado de proveedores, productos y servicios.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2679,9,402,'PGSN03-F3 Alta de proveedores','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PGSN03-F3 Alta de proveedores.docx','PGSN03-F3 Alta de proveedores.docx',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2680,9,402,'PGSN03-F3 Alta de proveedores','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PGSN03-F3 Alta de proveedores.pdf','PGSN03-F3 Alta de proveedores.pdf',1,'2025-06-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2681,9,402,'PGSN03-F3 Alta de proveedores','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PGSN03-F3 Alta de proveedores.xlsx','PGSN03-F3 Alta de proveedores.xlsx',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2682,9,402,'PGSN03-F4 Evaluación de proveedores - copia','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PGSN03-F4 Evaluación de proveedores - copia.pdf','PGSN03-F4 Evaluación de proveedores - copia.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2683,9,402,'PGSN03-F4 Evaluación de proveedores','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PGSN03-F4 Evaluación de proveedores.pdf','PGSN03-F4 Evaluación de proveedores.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2684,9,402,'PGSN03-F5 Seguimiento de proveedores','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PGSN03-F5 Seguimiento de proveedores.pdf','PGSN03-F5 Seguimiento de proveedores.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2685,9,402,'PGSN03-F6 Entrega de materiales','','documentacion','sgi/1 SG Documentos/PG-SN-03 Gestión de compras/PGSN03-F6 Entrega de materiales.pdf','PGSN03-F6 Entrega de materiales.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2686,9,403,'PG-SN-04 Evaluación de desempeño y mejora','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PG-SN-04 Evaluación de desempeño y mejora.pdf','PG-SN-04 Evaluación de desempeño y mejora.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2687,9,403,'PGSN04-A1 No Conformidades y Acciones Correctivas','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-A1 No Conformidades y Acciones Correctivas.pdf','PGSN04-A1 No Conformidades y Acciones Correctivas.pdf',1,'2025-04-03','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2688,9,403,'PGSN04-F1 Indicadores de Gestión','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F1 Indicadores de Gestión.pdf','PGSN04-F1 Indicadores de Gestión.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2689,9,403,'PGSN04-F10 Visita Gerencial','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F10 Visita Gerencial.pdf','PGSN04-F10 Visita Gerencial.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2690,9,403,'PGSN04-F11 Revisión por la dirección','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F11 Revisión por la dirección.pdf','PGSN04-F11 Revisión por la dirección.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2691,9,403,'PGSN04-F2 Plan anual de auditorias','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F2 Plan anual de auditorias.pdf','PGSN04-F2 Plan anual de auditorias.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2692,9,403,'PGSN04-F3 Programa de auditoría','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F3 Programa de auditoría.pdf','PGSN04-F3 Programa de auditoría.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2693,9,403,'PGSN04-F4 Informe de auditoría','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F4 Informe de auditoría.pdf','PGSN04-F4 Informe de auditoría.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2694,9,403,'PGSN04-F5 No Conformidad','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F5 No Conformidad.pdf','PGSN04-F5 No Conformidad.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2695,9,403,'PGSN04-F6 Listado de No Conformidades','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F6 Listado de No Conformidades.pdf','PGSN04-F6 Listado de No Conformidades.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2696,9,403,'PGSN04-F7 Diagrama Causa-Efecto','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F7 Diagrama Causa-Efecto.pdf','PGSN04-F7 Diagrama Causa-Efecto.pdf',1,'2026-01-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2697,9,403,'PGSN04-F8 Minuta de Reunión','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F8 Minuta de Reunión.pdf','PGSN04-F8 Minuta de Reunión.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2698,9,403,'PGSN04-F9 Plan de Mejora','','documentacion','sgi/1 SG Documentos/PG-SN-04 Evaluación de desempeño  y Mejora/PGSN04-F9 Plan de Mejora.pdf','PGSN04-F9 Plan de Mejora.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2699,9,404,'PG-SN-05 Legislación Aplicable','','documentacion','sgi/1 SG Documentos/PG-SN-05 Legislación Aplicable/PG-SN-05 Legislación Aplicable.pdf','PG-SN-05 Legislación Aplicable.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2700,9,404,'PGSN05-F1 Matriz de requisitos legales','','documentacion','sgi/1 SG Documentos/PG-SN-05 Legislación Aplicable/PGSN05-F1 Matriz de requisitos legales.pdf','PGSN05-F1 Matriz de requisitos legales.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2701,9,405,'PG-SN-06 Gestion Ambiental','','documentacion','sgi/1 SG Documentos/PG-SN-06 Gestión Ambiental/PG-SN-06 Gestion Ambiental.pdf','PG-SN-06 Gestion Ambiental.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2702,9,405,'PGSN06-A1 Plan de gestión ambiental','','documentacion','sgi/1 SG Documentos/PG-SN-06 Gestión Ambiental/PGSN06-A1 Plan de gestión ambiental.pdf','PGSN06-A1 Plan de gestión ambiental.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2703,9,405,'PGSN06-F1 - Identificación de aspectos y evaluación de impactos','','documentacion','sgi/1 SG Documentos/PG-SN-06 Gestión Ambiental/PGSN06-F1 - Identificación de aspectos y evaluación de impactos.pdf','PGSN06-F1 - Identificación de aspectos y evaluación de impactos.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2704,9,406,'PG-SN-07 Gestion del Riesgo','','documentacion','sgi/1 SG Documentos/PG-SN-07 Gestión del Riesgo/PG-SN-07 Gestion del Riesgo.pdf','PG-SN-07 Gestion del Riesgo.pdf',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2705,9,406,'PGSN07-A1 Observaciones Preventivas','','documentacion','sgi/1 SG Documentos/PG-SN-07 Gestión del Riesgo/PGSN07-A1 Observaciones Preventivas.pdf','PGSN07-A1 Observaciones Preventivas.pdf',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2706,9,406,'PGSN07-A2 Gestión de EPP','','documentacion','sgi/1 SG Documentos/PG-SN-07 Gestión del Riesgo/PGSN07-A2 Gestión de EPP.pdf','PGSN07-A2 Gestión de EPP.pdf',1,'2026-03-31','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2707,9,406,'PGSN07-F1 Identificación de peligros y control de riesgos','','documentacion','sgi/1 SG Documentos/PG-SN-07 Gestión del Riesgo/PGSN07-F1 Identificación de peligros y control de riesgos.pdf','PGSN07-F1 Identificación de peligros y control de riesgos.pdf',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2708,9,406,'PGSN07-F2 Programa de gestión de HSEQ','','documentacion','sgi/1 SG Documentos/PG-SN-07 Gestión del Riesgo/PGSN07-F2 Programa de gestión de HSEQ.pdf','PGSN07-F2 Programa de gestión de HSEQ.pdf',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2709,9,406,'Tarjeta modelo de observación preventiva','','documentacion','sgi/1 SG Documentos/PG-SN-07 Gestión del Riesgo/Tarjeta modelo de observación preventiva.pdf','Tarjeta modelo de observación preventiva.pdf',1,'2026-06-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2710,9,407,'PG-SN-08 Respuestas ante emergencias','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PG-SN-08 Respuestas ante emergencias.pdf','PG-SN-08 Respuestas ante emergencias.pdf',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2711,9,408,'PG-SN-13 Comunicaciones','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PG-SN-13 Comunicaciones/PG-SN-13 Comunicaciones.pdf','PG-SN-13 Comunicaciones.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2712,9,408,'PGSN13-F1 Comunicación Interna','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PG-SN-13 Comunicaciones/PGSN13-F1 Comunicación Interna.pdf','PGSN13-F1 Comunicación Interna.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2713,9,408,'PGSN13-F2 Boletín Informativo','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PG-SN-13 Comunicaciones/PGSN13-F2 Boletín Informativo.pdf','PGSN13-F2 Boletín Informativo.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2714,9,408,'PGSN13-F3 Inducción al personal ingresante','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PG-SN-13 Comunicaciones/PGSN13-F3 Inducción al personal ingresante.pdf','PGSN13-F3 Inducción al personal ingresante.pdf',1,'2025-11-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2715,9,407,'PGSN08-A1 Plan de emergencias en base','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PGSN08-A1 Plan de emergencias en base.pdf','PGSN08-A1 Plan de emergencias en base.pdf',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2716,9,407,'PGSN08-A2 Plan de emergencia en yacimiento','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PGSN08-A2 Plan de emergencia en yacimiento.pdf','PGSN08-A2 Plan de emergencia en yacimiento.pdf',1,'2025-10-22','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2717,9,407,'PGSN08-F1 Planificación e informe de simulacro','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PGSN08-F1 Planificación e informe de simulacro.pdf','PGSN08-F1 Planificación e informe de simulacro.pdf',1,'2025-10-22','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2718,9,407,'PGSN08-F2 Roles ante emergencias','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PGSN08-F2 Roles ante emergencias.pdf','PGSN08-F2 Roles ante emergencias.pdf',1,'2025-11-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2719,9,407,'PGSN08-F3 Plano de evacuacion de base','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/PGSN08-F3 Plano de evacuacion de base.pdf','PGSN08-F3 Plano de evacuacion de base.pdf',1,'2025-05-08','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2720,9,407,'Plano de evacuación Salón de eventos','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/Plano de evacuación Salón de eventos.docx','Plano de evacuación Salón de eventos.docx',1,'2026-05-12','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2721,9,407,'Plano de evacuación Salón de eventos','','documentacion','sgi/1 SG Documentos/PG-SN-08 Respuesta ante emergencias/Plano de evacuación Salón de eventos.pdf','Plano de evacuación Salón de eventos.pdf',1,'2026-05-12','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2722,9,409,'PG-SN-09 Gestión de Residuos','','documentacion','sgi/1 SG Documentos/PG-SN-09 Gestión de Residuos/PG-SN-09 Gestión de Residuos.pdf','PG-SN-09 Gestión de Residuos.pdf',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2723,9,409,'PGSN09-F1 Manejo de residuos','','documentacion','sgi/1 SG Documentos/PG-SN-09 Gestión de Residuos/PGSN09-F1 Manejo de residuos.pdf','PGSN09-F1 Manejo de residuos.pdf',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2724,9,410,'PG-SN-10 Investigacion de incidentes','','documentacion','sgi/1 SG Documentos/PG-SN-10 Investigación de incidentes/PG-SN-10 Investigacion de incidentes.pdf','PG-SN-10 Investigacion de incidentes.pdf',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2725,9,410,'PGSN10-F1 Informe Tecnico','','documentacion','sgi/1 SG Documentos/PG-SN-10 Investigación de incidentes/PGSN10-F1 Informe Tecnico.pdf','PGSN10-F1 Informe Tecnico.pdf',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2726,9,410,'PGSN10-F2 Listado de incidentes','','documentacion','sgi/1 SG Documentos/PG-SN-10 Investigación de incidentes/PGSN10-F2 Listado de incidentes.pdf','PGSN10-F2 Listado de incidentes.pdf',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2727,9,410,'PGSN10-F3 Declaración ante incidente','','documentacion','sgi/1 SG Documentos/PG-SN-10 Investigación de incidentes/PGSN10-F3 Declaración ante incidente.pdf','PGSN10-F3 Declaración ante incidente.pdf',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2728,9,410,'PGSN10-F4 Alerta de HSEQ','','documentacion','sgi/1 SG Documentos/PG-SN-10 Investigación de incidentes/PGSN10-F4 Alerta de HSEQ.pdf','PGSN10-F4 Alerta de HSEQ.pdf',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2729,9,411,'Cotizaciones','','documentacion','sgi/1 SG Documentos/PG-SN-11 Cotizaciones/Cotizaciones.pdf','Cotizaciones.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2730,9,411,'PGSN11-F1 Propuesta Tecnica de Servicio','','documentacion','sgi/1 SG Documentos/PG-SN-11 Cotizaciones/PGSN11-F1 Propuesta Tecnica de Servicio.pdf','PGSN11-F1 Propuesta Tecnica de Servicio.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2731,9,411,'PGSN11-F2 Propuesta Economica de Servicio','','documentacion','sgi/1 SG Documentos/PG-SN-11 Cotizaciones/PGSN11-F2 Propuesta Economica de Servicio.pdf','PGSN11-F2 Propuesta Economica de Servicio.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2732,9,412,'PG-SN-12 Bienes y propiedad del cliente','','documentacion','sgi/1 SG Documentos/PG-SN-12 Bienes y propiedad del cliente/PG-SN-12 Bienes y propiedad del cliente.pdf','PG-SN-12 Bienes y propiedad del cliente.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2733,9,412,'PGSN12-F1 Ingreso y Egreso de locación','','documentacion','sgi/1 SG Documentos/PG-SN-12 Bienes y propiedad del cliente/PGSN12-F1 Ingreso y Egreso de locación.pdf','PGSN12-F1 Ingreso y Egreso de locación.pdf',1,'2026-08-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2734,9,395,'PGSN01-F1 Listado maestro de documentos','','documentacion','sgi/1 SG Documentos/PGSN01-F1 Listado maestro de documentos.xlsx','PGSN01-F1 Listado maestro de documentos.xlsx',1,'2026-08-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2735,9,413,'PO-SN-01 Mantenimiento de equipos','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/PO-SN-01 Mantenimiento de equipos.docx','PO-SN-01 Mantenimiento de equipos.docx',1,'2025-10-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2736,9,413,'PO-SN-01 Mantenimiento de equipos','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/PO-SN-01 Mantenimiento de equipos.pdf','PO-SN-01 Mantenimiento de equipos.pdf',1,'2025-10-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2737,9,413,'POSN01-A1 Prueba de hermeticidad de BOP','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-A1 Prueba de hermeticidad de BOP.pdf','POSN01-A1 Prueba de hermeticidad de BOP.pdf',1,'2024-09-25','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2738,9,413,'POSN01-F1 Utilización del alambre','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F1 Utilización del alambre.pdf','POSN01-F1 Utilización del alambre.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2739,9,413,'POSN01-F10 Mantenimiento de equipo de izaje y guinche','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F10 Mantenimiento de equipo de izaje y guinche.pdf','POSN01-F10 Mantenimiento de equipo de izaje y guinche.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2740,9,413,'POSN01-F11 Mantenimiento de polea de reenvío','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F11 Mantenimiento de polea de reenvío.pdf','POSN01-F11 Mantenimiento de polea de reenvío.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2741,9,413,'POSN01-F12 Control de apertura y cierre de BOP','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F12 Control de apertura y cierre de BOP.pdf','POSN01-F12 Control de apertura y cierre de BOP.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2742,9,413,'POSN01-F13 Inspeccion de Herramientas Mano','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F13 Inspeccion de Herramientas Mano.pdf','POSN01-F13 Inspeccion de Herramientas Mano.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2743,9,413,'POSN01-F14 Inspección de extintores','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F14 Inspección de extintores.pdf','POSN01-F14 Inspección de extintores.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2744,9,413,'POSN01-F15 Plan de mantenimiento','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F15 Plan de mantenimiento.xlsx','POSN01-F15 Plan de mantenimiento.xlsx',1,'2026-06-02','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2745,9,413,'POSN01-F16 Estado de vehículos y equipos','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F16 Estado de vehículos y equipos.pdf','POSN01-F16 Estado de vehículos y equipos.pdf',1,'2026-08-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2746,9,413,'POSN01-F3 Chequeo Vehicular','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F3 Chequeo Vehicular.pdf','POSN01-F3 Chequeo Vehicular.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2747,9,413,'POSN01-F4 Registro de inspección operativa','','registro','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F4 Registro de inspección operativa.pdf','POSN01-F4 Registro de inspección operativa.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2748,9,413,'POSN01-F4 Registro de inspección operativa','','registro','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F4 Registro de inspección operativa.xlsx','POSN01-F4 Registro de inspección operativa.xlsx',1,'2021-03-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2749,9,413,'POSN01-F5 Inspeccion de elementos de izaje','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F5 Inspeccion de elementos de izaje.docx','POSN01-F5 Inspeccion de elementos de izaje.docx',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2750,9,413,'POSN01-F5 Inspeccion de elementos de izaje','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F5 Inspeccion de elementos de izaje.pdf','POSN01-F5 Inspeccion de elementos de izaje.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2751,9,413,'POSN01-F6 Control de elementos criticos','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F6 Control de elementos criticos.docx','POSN01-F6 Control de elementos criticos.docx',1,'2021-06-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2752,9,413,'POSN01-F7 Control de equipo de slick line','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F7 Control de equipo de slick line.xlsx','POSN01-F7 Control de equipo de slick line.xlsx',1,'2021-06-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2753,9,413,'POSN01-F8 Chequeo de hidrogrua','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F8 Chequeo de hidrogrua.docx','POSN01-F8 Chequeo de hidrogrua.docx',1,'2021-07-02','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2754,9,413,'POSN01-F9 Control de lavaojos','','documentacion','sgi/1 SG Documentos/PO-SN-01 Mantenimiento de equipos/POSN01-F9 Control de lavaojos.pdf','POSN01-F9 Control de lavaojos.pdf',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2755,9,415,'POSN02-IT-03 Uso de llave Stilson','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT-03 Uso de llave Stilson.pdf','POSN02-IT-03 Uso de llave Stilson.pdf',1,'2025-05-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2756,9,415,'POSN02-IT01 Movimiento de válvulas con HOT OIL-Fluxus','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT01 Movimiento de válvulas con HOT OIL-Fluxus.pdf','POSN02-IT01 Movimiento de válvulas con HOT OIL-Fluxus.pdf',1,'2025-05-02','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2757,9,415,'POSN02-IT02 Calibración de Casing con calibre Dummy para pozos que requieren BIF','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT02 Calibración de Casing con calibre Dummy para pozos que requieren BIF.pdf','POSN02-IT02 Calibración de Casing con calibre Dummy para pozos que requieren BIF.pdf',1,'2025-05-08','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2758,9,415,'POSN02-IT04 Reemplazo de Válvulas Esclusas','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT04 Reemplazo de Válvulas Esclusas.pdf','POSN02-IT04 Reemplazo de Válvulas Esclusas.pdf',1,'2025-05-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2759,9,415,'POSN02-IT05 Montaje de Pluma','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT05 Montaje de Pluma.pdf','POSN02-IT05 Montaje de Pluma.pdf',1,'2025-06-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2760,9,415,'POSN02-IT06 Desparafinación con PMT','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT06 Desparafinación con PMT.pdf','POSN02-IT06 Desparafinación con PMT.pdf',1,'2025-06-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2761,9,415,'POSN02-IT07 Pistoneo de pozos de forma contínua','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT07 Pistoneo de pozos de forma contínua.pdf','POSN02-IT07 Pistoneo de pozos de forma contínua.pdf',1,'2025-07-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2762,9,415,'POSN02-IT08 Montaje y desmontaje de Paños','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT08 Montaje y desmontaje de Paños.pdf','POSN02-IT08 Montaje y desmontaje de Paños.pdf',1,'2025-07-02','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2763,9,415,'POSN02-IT09 Montaje y desmontaje de pileta','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT09 Montaje y desmontaje de pileta.pdf','POSN02-IT09 Montaje y desmontaje de pileta.pdf',1,'2025-11-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2764,9,415,'POSN02-IT10 Uso de mazas','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Instructivos de trabajo SL/POSN02-IT10 Uso de mazas.pdf','POSN02-IT10 Uso de mazas.pdf',1,'2025-09-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2765,9,416,'0 Indice de procedimientos','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Manual de procedimientos/0 Indice de procedimientos.docx','0 Indice de procedimientos.docx',1,'2025-11-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2766,9,416,'Caratula PO','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Manual de procedimientos/Caratula PO.docx','Caratula PO.docx',1,'2025-04-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2767,9,416,'Manual Operativo','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Manual de procedimientos/Manual Operativo.docx','Manual Operativo.docx',1,'2025-11-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2768,9,416,'Manual Operativo','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/Manual de procedimientos/Manual Operativo.pdf','Manual Operativo.pdf',1,'2025-11-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2769,9,414,'MO-SN-01 Manual de operaciones de Slickline','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/MO-SN-01 Manual de operaciones de Slickline.pdf','MO-SN-01 Manual de operaciones de Slickline.pdf',1,'2025-12-10','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2770,9,414,'POSN-02 Servicio de slick line','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN-02 Servicio de slick line.pdf','POSN-02 Servicio de slick line.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2771,9,414,'POSN02-A1 Medidas preventivas ante fuertes vientos','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A1 Medidas preventivas ante fuertes vientos.pdf','POSN02-A1 Medidas preventivas ante fuertes vientos.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2772,9,414,'POSN02-A10 Uso de tijera mecánica','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A10 Uso de tijera mecánica.pdf','POSN02-A10 Uso de tijera mecánica.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2773,9,414,'POSN02-A11 Conexión de herramientas al tren de slick line','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A11 Conexión de herramientas al tren de slick line.pdf','POSN02-A11 Conexión de herramientas al tren de slick line.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2774,9,414,'POSN02-A12 Toma de cero','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A12 Toma de cero.pdf','POSN02-A12 Toma de cero.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2775,9,414,'POSN02-A13 Calibración de tuberías','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A13 Calibración de tuberías.pdf','POSN02-A13 Calibración de tuberías.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2776,9,414,'POSN02-A14 Constatación de fondo con bloque de impresión','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A14 Constatación de fondo con bloque de impresión.pdf','POSN02-A14 Constatación de fondo con bloque de impresión.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2777,9,414,'POSN02-A15 Desparafinación','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A15 Desparafinación.pdf','POSN02-A15 Desparafinación.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2778,9,414,'POSN02-A16 Pesca de alambre','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A16 Pesca de alambre.pdf','POSN02-A16 Pesca de alambre.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2779,9,414,'POSN02-A17 Corte de alambre de slick line','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A17 Corte de alambre de slick line.pdf','POSN02-A17 Corte de alambre de slick line.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2780,9,414,'POSN02-A18 Corte de alambre con cortador tipo Go Devil','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A18 Corte de alambre con cortador tipo Go Devil.pdf','POSN02-A18 Corte de alambre con cortador tipo Go Devil.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2781,9,414,'POSN02-A19 Pesca de tren de herramientas','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A19 Pesca de tren de herramientas.pdf','POSN02-A19 Pesca de tren de herramientas.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2782,9,414,'POSN02-A2 Uso y mantenimiento del alambre','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A2 Uso y mantenimiento del alambre.pdf','POSN02-A2 Uso y mantenimiento del alambre.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2783,9,414,'POSN02-A20 Gradientes de presión y temperatura','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A20 Gradientes de presión y temperatura.pdf','POSN02-A20 Gradientes de presión y temperatura.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2784,9,414,'POSN02-A21 Ensayo con herramienta PLT','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A21 Ensayo con herramienta PLT.pdf','POSN02-A21 Ensayo con herramienta PLT.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2785,9,414,'POSN02-A22 Ensayo con ILT (En Revisión)','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A22 Ensayo con ILT (En Revisión).pdf','POSN02-A22 Ensayo con ILT (En Revisión).pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2786,9,414,'POSN02-A23 Pistoneo de pozo','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A23 Pistoneo de pozo.pdf','POSN02-A23 Pistoneo de pozo.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2787,9,414,'POSN02-A24 Pesca y colocación de válvulas de recuperación secundaria','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A24 Pesca y colocación de válvulas de recuperación secundaria.pdf','POSN02-A24 Pesca y colocación de válvulas de recuperación secundaria.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2788,9,414,'POSN02-A25 Pesca y colocación de válvulas de Gas Lift','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A25 Pesca y colocación de válvulas de Gas Lift.pdf','POSN02-A25 Pesca y colocación de válvulas de Gas Lift.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2789,9,414,'POSN02-A26 Fijación y pesca de tapones W','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A26 Fijación y pesca de tapones W.pdf','POSN02-A26 Fijación y pesca de tapones W.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2790,9,414,'POSN02-A27 Fijación y pesca de tapones X-XN','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A27 Fijación y pesca de tapones X-XN.pdf','POSN02-A27 Fijación y pesca de tapones X-XN.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2791,9,414,'POSN02-A28 Fijación y pesca de tapones R-RN','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A28 Fijación y pesca de tapones R-RN.pdf','POSN02-A28 Fijación y pesca de tapones R-RN.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2792,9,414,'POSN02-A29 Calibración de Casing con calibre Dummy','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A29 Calibración de Casing con calibre Dummy.pdf','POSN02-A29 Calibración de Casing con calibre Dummy.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2793,9,414,'POSN02-A3 Ensayo de ductilidad de alambre','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A3 Ensayo de ductilidad de alambre.pdf','POSN02-A3 Ensayo de ductilidad de alambre.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2794,9,414,'POSN02-A30 Ruptura de disco cerámico','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A30 Ruptura de disco cerámico.pdf','POSN02-A30 Ruptura de disco cerámico.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2795,9,414,'POSN02-A31 Tubing Punch','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A31 Tubing Punch.pdf','POSN02-A31 Tubing Punch.pdf',1,'2025-11-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2796,9,414,'POSN02-A4 Cambio de empaquetaduras de stuffing box','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A4 Cambio de empaquetaduras de stuffing box.pdf','POSN02-A4 Cambio de empaquetaduras de stuffing box.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2797,9,414,'POSN02-A5 Enhebrado de alambre en stuffing box','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A5 Enhebrado de alambre en stuffing box.pdf','POSN02-A5 Enhebrado de alambre en stuffing box.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2798,9,414,'POSN02-A6 Uso de Tool Catcher','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A6 Uso de Tool Catcher.pdf','POSN02-A6 Uso de Tool Catcher.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2799,9,414,'POSN02-A7 Prueba de hermeticidad de PCE','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A7 Prueba de hermeticidad de PCE.pdf','POSN02-A7 Prueba de hermeticidad de PCE.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2800,9,414,'POSN02-A8 Nudo de alambre en Rope Socket','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A8 Nudo de alambre en Rope Socket.pdf','POSN02-A8 Nudo de alambre en Rope Socket.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2801,9,414,'POSN02-A9 Elección del tren de herramientas de slick line','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-A9 Elección del tren de herramientas de slick line.pdf','POSN02-A9 Elección del tren de herramientas de slick line.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2802,9,414,'POSN02-F1 Minuta de Reunion de Seguridad','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-F1 Minuta de Reunion de Seguridad.pdf','POSN02-F1 Minuta de Reunion de Seguridad.pdf',1,'2026-07-31','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2803,9,414,'POSN02-F4 Control operativo Slickline','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-F4 Control operativo Slickline.pdf','POSN02-F4 Control operativo Slickline.pdf',1,'2026-08-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2804,9,414,'POSN02-F5 Seguimiento de personal operativo','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-F5 Seguimiento de personal operativo.pdf','POSN02-F5 Seguimiento de personal operativo.pdf',1,'2026-08-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2805,9,414,'POSN02-F7 Informacion del pozo','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-F7 Informacion del pozo.docx','POSN02-F7 Informacion del pozo.docx',1,'2023-12-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2806,9,414,'POSN02-IT11 Uso de Registrador de Slickline','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-IT11 Uso de Registrador de Slickline.pdf','POSN02-IT11 Uso de Registrador de Slickline.pdf',1,'2026-02-25','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2807,9,414,'POSN02-IT12 Armado de equipamiento de desfogue','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-IT12 Armado de equipamiento de desfogue.pdf','POSN02-IT12 Armado de equipamiento de desfogue.pdf',1,'2026-06-09','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2808,9,414,'POSN02-IT12 Uso de dispositivos electrónicos','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-IT12 Uso de dispositivos electrónicos.pdf','POSN02-IT12 Uso de dispositivos electrónicos.pdf',1,'2026-08-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2809,9,414,'POSN02-IT13 Armado de equipamiento de desfogue','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02-IT13 Armado de equipamiento de desfogue.pdf','POSN02-IT13 Armado de equipamiento de desfogue.pdf',1,'2026-08-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2810,9,414,'POSN02A20-F1 Registro de Presiones','','procedimiento','sgi/1 SG Documentos/PO-SN-02 Procedimiento Slick Line/POSN02A20-F1 Registro de Presiones.pdf','POSN02A20-F1 Registro de Presiones.pdf',1,'2025-09-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2811,9,417,'PO-SN-03 Servicio de Well testing','','documentacion','sgi/1 SG Documentos/PO-SN-03 Servicio de Well Testing/PO-SN-03 Servicio de Well testing.pdf','PO-SN-03 Servicio de Well testing.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2812,9,417,'POSN03-A1 Determinación de encogimiento','','documentacion','sgi/1 SG Documentos/PO-SN-03 Servicio de Well Testing/POSN03-A1 Determinación de encogimiento.pdf','POSN03-A1 Determinación de encogimiento.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2813,9,417,'POSN03-A2 Cambio de másico','','documentacion','sgi/1 SG Documentos/PO-SN-03 Servicio de Well Testing/POSN03-A2 Cambio de másico.pdf','POSN03-A2 Cambio de másico.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2814,9,417,'POSN03-A3 Uso de bomba de doble diafragma','','documentacion','sgi/1 SG Documentos/PO-SN-03 Servicio de Well Testing/POSN03-A3 Uso de bomba de doble diafragma.pdf','POSN03-A3 Uso de bomba de doble diafragma.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2815,9,417,'POSN03-F1 Registro de supervisión de well testing','','registro','sgi/1 SG Documentos/PO-SN-03 Servicio de Well Testing/POSN03-F1 Registro de supervisión de well testing.pdf','POSN03-F1 Registro de supervisión de well testing.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2816,9,417,'POSN03-F2 Check List de Montaje de equipo de well testing','','checklist','sgi/1 SG Documentos/PO-SN-03 Servicio de Well Testing/POSN03-F2 Check List de Montaje de equipo de well testing.pdf','POSN03-F2 Check List de Montaje de equipo de well testing.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2817,9,417,'POSN03-F3 Relevamiento manual de datos','','documentacion','sgi/1 SG Documentos/PO-SN-03 Servicio de Well Testing/POSN03-F3 Relevamiento manual de datos.pdf','POSN03-F3 Relevamiento manual de datos.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2818,9,417,'POSN03-F4 Registro de control de generador','','registro','sgi/1 SG Documentos/PO-SN-03 Servicio de Well Testing/POSN03-F4 Registro de control de generador.pdf','POSN03-F4 Registro de control de generador.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2819,9,418,'PO-SN-04 Planificación del servicio','','documentacion','sgi/1 SG Documentos/PO-SN-04 Planificación del servicio/PO-SN-04 Planificación del servicio.pdf','PO-SN-04 Planificación del servicio.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2820,9,418,'POSN04-F1 Planificación semanal','','documentacion','sgi/1 SG Documentos/PO-SN-04 Planificación del servicio/POSN04-F1 Planificación semanal.pdf','POSN04-F1 Planificación semanal.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2821,9,418,'POSN04-F2 Diseño de servicio','','documentacion','sgi/1 SG Documentos/PO-SN-04 Planificación del servicio/POSN04-F2 Diseño de servicio.pdf','POSN04-F2 Diseño de servicio.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2822,9,418,'POSN04-F3 Tren de herramientas de slick line','','documentacion','sgi/1 SG Documentos/PO-SN-04 Planificación del servicio/POSN04-F3 Tren de herramientas de slick line.pdf','POSN04-F3 Tren de herramientas de slick line.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2823,9,418,'POSN04-F4 Remito','','documentacion','sgi/1 SG Documentos/PO-SN-04 Planificación del servicio/POSN04-F4 Remito.pdf','POSN04-F4 Remito.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2824,9,418,'POSN04-F5 Evaluación de riesgos operativos','','documentacion','sgi/1 SG Documentos/PO-SN-04 Planificación del servicio/POSN04-F5 Evaluación de riesgos operativos.pdf','POSN04-F5 Evaluación de riesgos operativos.pdf',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2825,9,419,'PO-SN-05 Manejo del cambio','','documentacion','sgi/1 SG Documentos/PO-SN-05 Manejo del cambio/PO-SN-05 Manejo del cambio.pdf','PO-SN-05 Manejo del cambio.pdf',1,'2025-11-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2826,9,420,'PO-SN-06 Uso de vehículos','','documentacion','sgi/1 SG Documentos/PO-SN-06 Uso de vehículos/PO-SN-06 Uso de vehículos.pdf','PO-SN-06 Uso de vehículos.pdf',1,'2025-11-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2827,9,421,'PO-SN-07 Trabajo en altura','','documentacion','sgi/1 SG Documentos/PO-SN-07 Trabajo en altura/PO-SN-07 Trabajo en altura.pdf','PO-SN-07 Trabajo en altura.pdf',1,'2025-11-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2828,9,422,'PO-SN-08 Control y trazabilidad','','documentacion','sgi/1 SG Documentos/PO-SN-08 Control y trazabilidad/PO-SN-08 Control y trazabilidad.pdf','PO-SN-08 Control y trazabilidad.pdf',1,'2025-11-10','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2829,9,422,'POSN08-F1 Listado de Herramientas','','documentacion','sgi/1 SG Documentos/PO-SN-08 Control y trazabilidad/POSN08-F1 Listado de Herramientas.pdf','POSN08-F1 Listado de Herramientas.pdf',1,'2025-11-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2830,9,422,'POSN08-F2 Listado de Operaciones','','documentacion','sgi/1 SG Documentos/PO-SN-08 Control y trazabilidad/POSN08-F2 Listado de Operaciones.pdf','POSN08-F2 Listado de Operaciones.pdf',1,'2025-11-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2831,9,422,'POSN08-F3 Visita a equipos','','documentacion','sgi/1 SG Documentos/PO-SN-08 Control y trazabilidad/POSN08-F3 Visita a equipos.pdf','POSN08-F3 Visita a equipos.pdf',1,'2025-11-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2832,9,423,'2026-01-06 CUM-04','','documentacion','sgi/1 SG Documentos/Z Documentos editables/2026-01-06 CUM-04.docx','2026-01-06 CUM-04.docx',1,'2026-01-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2833,9,423,'Cotizaciones','','documentacion','sgi/1 SG Documentos/Z Documentos editables/Cotizaciones.docx','Cotizaciones.docx',1,'2025-09-22','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2834,9,423,'MG-SN-01 Manual de gestión','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MG-SN-01 Manual de gestión.docx','MG-SN-01 Manual de gestión.docx',1,'2026-03-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2835,9,423,'MGSN01-A1 Mapa de procesos operaciones','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MGSN01-A1 Mapa de procesos operaciones.xlsx','MGSN01-A1 Mapa de procesos operaciones.xlsx',1,'2025-06-24','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2836,9,423,'MGSN01-A1 Mapa de procesos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MGSN01-A1 Mapa de procesos.pptx','MGSN01-A1 Mapa de procesos.pptx',1,'2026-03-20','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2837,9,423,'MGSN01-A2 Organigrama Rev.14','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MGSN01-A2 Organigrama Rev.14.pptx','MGSN01-A2 Organigrama Rev.14.pptx',1,'2026-03-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2838,9,423,'MGSN01-A2 Organigrama','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MGSN01-A2 Organigrama.pptx','MGSN01-A2 Organigrama.pptx',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2839,9,423,'MGSN01-A3 Análisis de contexto','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MGSN01-A3 Análisis de contexto.docx','MGSN01-A3 Análisis de contexto.docx',1,'2025-10-09','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2840,9,423,'MGSN01-A4 Objetivos e indicadores','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MGSN01-A4 Objetivos e indicadores.xlsx','MGSN01-A4 Objetivos e indicadores.xlsx',1,'2025-09-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2841,9,423,'MGSN01-A4 Objetivos e indicadores26','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MGSN01-A4 Objetivos e indicadores26.xlsx','MGSN01-A4 Objetivos e indicadores26.xlsx',1,'2026-07-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2842,9,423,'MGSN01-A5 Matriz Riesgos y Oportunidades','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MGSN01-A5 Matriz Riesgos y Oportunidades.xlsx','MGSN01-A5 Matriz Riesgos y Oportunidades.xlsx',1,'2025-06-24','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2843,9,423,'MGSN01-A6 Reglamento de ética','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MGSN01-A6 Reglamento de ética.docx','MGSN01-A6 Reglamento de ética.docx',1,'2025-08-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2844,9,423,'MO-SN-01 Manual de operaciones de Slickline','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MO-SN-01 Manual de operaciones de Slickline.docx','MO-SN-01 Manual de operaciones de Slickline.docx',1,'2025-12-10','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2845,9,423,'MS-SN-01 Manual de Seguridad e Higiene','','documentacion','sgi/1 SG Documentos/Z Documentos editables/MS-SN-01 Manual de Seguridad e Higiene.docx','MS-SN-01 Manual de Seguridad e Higiene.docx',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2846,9,423,'PE-01 Política de Calidad Ambiente Seguridad y Salud','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PE-01 Política de Calidad Ambiente Seguridad y Salud.pptx','PE-01 Política de Calidad Ambiente Seguridad y Salud.pptx',1,'2025-10-08','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2847,9,423,'PG-SN-01 Control de información documentada','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-01 Control de información documentada.docx','PG-SN-01 Control de información documentada.docx',1,'2025-10-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2848,9,423,'PG-SN-02 Gestión de las personas','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-02 Gestión de las personas.docx','PG-SN-02 Gestión de las personas.docx',1,'2025-09-10','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2849,9,423,'PG-SN-03 Gestión de compras','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-03 Gestión de compras.docx','PG-SN-03 Gestión de compras.docx',1,'2025-06-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2850,9,423,'PG-SN-04 Evaluación de desempeño y mejora','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-04 Evaluación de desempeño y mejora.docx','PG-SN-04 Evaluación de desempeño y mejora.docx',1,'2025-08-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2851,9,423,'PG-SN-05 Legislación Aplicable','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-05 Legislación Aplicable.docx','PG-SN-05 Legislación Aplicable.docx',1,'2025-07-02','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2852,9,423,'PG-SN-06 Gestion Ambiental','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-06 Gestion Ambiental.docx','PG-SN-06 Gestion Ambiental.docx',1,'2025-06-30','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2853,9,423,'PG-SN-07 Gestion del Riesgo','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-07 Gestion del Riesgo.docx','PG-SN-07 Gestion del Riesgo.docx',1,'2025-08-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2854,9,423,'PG-SN-08 Respuestas ante emergencias','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-08 Respuestas ante emergencias.docx','PG-SN-08 Respuestas ante emergencias.docx',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2855,9,423,'PG-SN-09 Gestión de Residuos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-09 Gestión de Residuos.docx','PG-SN-09 Gestión de Residuos.docx',1,'2025-09-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2856,9,423,'PG-SN-10 Investigacion de incidentes','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-10 Investigacion de incidentes.docx','PG-SN-10 Investigacion de incidentes.docx',1,'2025-09-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2857,9,423,'PG-SN-12 Bienes y propiedad del cliente','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-12 Bienes y propiedad del cliente.docx','PG-SN-12 Bienes y propiedad del cliente.docx',1,'2025-09-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2858,9,423,'PG-SN-13 Comunicaciones','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PG-SN-13 Comunicaciones.docx','PG-SN-13 Comunicaciones.docx',1,'2025-09-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2859,9,423,'PGSN01-F1 Listado maestro de documentos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN01-F1 Listado maestro de documentos.xlsx','PGSN01-F1 Listado maestro de documentos.xlsx',1,'2025-07-02','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2860,9,423,'PGSN01-F2 Control de distribución de documentos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN01-F2 Control de distribución de documentos.xlsx','PGSN01-F2 Control de distribución de documentos.xlsx',1,'2025-06-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2861,9,423,'PGSN02-A1 Control de consumo de estupefacientes','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-A1 Control de consumo de estupefacientes.docx','PGSN02-A1 Control de consumo de estupefacientes.docx',1,'2026-02-09','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2862,9,423,'PGSN02-A1 Reglamento interno','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-A1 Reglamento interno.docx','PGSN02-A1 Reglamento interno.docx',1,'2025-08-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2863,9,423,'PGSN02-F1 Perfil de puesto','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F1 Perfil de puesto.xlsx','PGSN02-F1 Perfil de puesto.xlsx',1,'2025-07-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2864,9,423,'PGSN02-F10 Sanción disciplinaria','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F10 Sanción disciplinaria.xlsx','PGSN02-F10 Sanción disciplinaria.xlsx',1,'2025-07-25','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2865,9,423,'PGSN02-F11 Plan de carrera','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F11 Plan de carrera.xlsx','PGSN02-F11 Plan de carrera.xlsx',1,'2025-07-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2866,9,423,'PGSN02-F12 Evaluación de desempeño del personal','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F12 Evaluación de desempeño del personal.xlsx','PGSN02-F12 Evaluación de desempeño del personal.xlsx',1,'2025-07-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2867,9,423,'PGSN02-F2 Entrevista al personal ingresante','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F2 Entrevista al personal ingresante.xlsx','PGSN02-F2 Entrevista al personal ingresante.xlsx',1,'2025-12-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2868,9,423,'PGSN02-F3 Informe medico laboral','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F3 Informe medico laboral.xlsx','PGSN02-F3 Informe medico laboral.xlsx',1,'2025-07-21','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2869,9,423,'PGSN02-F4 Listado de trabajadores','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F4 Listado de trabajadores.xlsx','PGSN02-F4 Listado de trabajadores.xlsx',1,'2025-10-09','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2870,9,423,'PGSN02-F5 Formulario de ingreso','','formulario','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F5 Formulario de ingreso.xlsx','PGSN02-F5 Formulario de ingreso.xlsx',1,'2025-07-24','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2871,9,423,'PGSN02-F6 Registro de entrega de EPP','','registro','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F6 Registro de entrega de EPP.xls','PGSN02-F6 Registro de entrega de EPP.xls',1,'2025-09-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2872,9,423,'PGSN02-F7 Registro de formación','','registro','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F7 Registro de formación.docx','PGSN02-F7 Registro de formación.docx',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2873,9,423,'PGSN02-F8 Diagrama de trabajo','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F8 Diagrama de trabajo.xlsx','PGSN02-F8 Diagrama de trabajo.xlsx',1,'2025-07-24','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2874,9,423,'PGSN02-F9 Comunicación de licencias y vacaciones','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN02-F9 Comunicación de licencias y vacaciones.xlsx','PGSN02-F9 Comunicación de licencias y vacaciones.xlsx',1,'2025-07-25','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2875,9,423,'PGSN03-F1 Pedido de Materiales y servicios','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN03-F1 Pedido de Materiales y servicios.xlsx','PGSN03-F1 Pedido de Materiales y servicios.xlsx',1,'2025-06-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2876,9,423,'PGSN03-F2 Listado de proveedores, productos y servicios','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN03-F2 Listado de proveedores, productos y servicios.xlsx','PGSN03-F2 Listado de proveedores, productos y servicios.xlsx',1,'2025-06-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2877,9,423,'PGSN03-F3 Alta de proveedores','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN03-F3 Alta de proveedores.xlsx','PGSN03-F3 Alta de proveedores.xlsx',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2878,9,423,'PGSN03-F4 Evaluación de proveedores','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN03-F4 Evaluación de proveedores.xlsx','PGSN03-F4 Evaluación de proveedores.xlsx',1,'2025-06-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2879,9,423,'PGSN03-F5 Seguimiento de proveedores','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN03-F5 Seguimiento de proveedores.xlsx','PGSN03-F5 Seguimiento de proveedores.xlsx',1,'2025-12-18','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2880,9,423,'PGSN03-F6 Entrega de materiales','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN03-F6 Entrega de materiales.xlsx','PGSN03-F6 Entrega de materiales.xlsx',1,'2025-11-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2881,9,423,'PGSN04-A1 No Conformidades y Acciones Correctivas','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-A1 No Conformidades y Acciones Correctivas.docx','PGSN04-A1 No Conformidades y Acciones Correctivas.docx',1,'2025-08-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2882,9,423,'PGSN04-F1 Indicadores de Gestión 2026','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F1 Indicadores de Gestión 2026.pdf','PGSN04-F1 Indicadores de Gestión 2026.pdf',1,'2026-05-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2883,9,423,'PGSN04-F1 Indicadores de Gestión 2026','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F1 Indicadores de Gestión 2026.xlsx','PGSN04-F1 Indicadores de Gestión 2026.xlsx',1,'2026-08-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:55'),
(2884,9,423,'PGSN04-F10 Visita Gerencial','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F10 Visita Gerencial.xlsx','PGSN04-F10 Visita Gerencial.xlsx',1,'2025-09-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2885,9,423,'PGSN04-F11 Revisión por la dirección','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F11 Revisión por la dirección.docx','PGSN04-F11 Revisión por la dirección.docx',1,'2026-01-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2886,9,423,'PGSN04-F2 Plan anual de auditorias','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F2 Plan anual de auditorias.xlsx','PGSN04-F2 Plan anual de auditorias.xlsx',1,'2026-08-19','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2887,9,423,'PGSN04-F3 Programa de auditoría','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F3 Programa de auditoría.docx','PGSN04-F3 Programa de auditoría.docx',1,'2025-10-09','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2888,9,423,'PGSN04-F4 Informe de auditoría','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F4 Informe de auditoría.docx','PGSN04-F4 Informe de auditoría.docx',1,'2025-09-24','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2889,9,423,'PGSN04-F5 No Conformidad','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F5 No Conformidad.xlsx','PGSN04-F5 No Conformidad.xlsx',1,'2025-08-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2890,9,423,'PGSN04-F6 Listado de No Conformidades','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F6 Listado de No Conformidades.xlsx','PGSN04-F6 Listado de No Conformidades.xlsx',1,'2025-09-24','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2891,9,423,'PGSN04-F7 Diagrama Causa-Efecto','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F7 Diagrama Causa-Efecto.pptx','PGSN04-F7 Diagrama Causa-Efecto.pptx',1,'2026-01-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2892,9,423,'PGSN04-F8 Minuta de Reunión','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F8 Minuta de Reunión.docx','PGSN04-F8 Minuta de Reunión.docx',1,'2025-09-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2893,9,423,'PGSN04-F9 Plan de Mejora','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN04-F9 Plan de Mejora.xlsx','PGSN04-F9 Plan de Mejora.xlsx',1,'2025-09-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2894,9,423,'PGSN05-F1 Matriz de requisitos legales','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN05-F1 Matriz de requisitos legales.xlsx','PGSN05-F1 Matriz de requisitos legales.xlsx',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2895,9,423,'PGSN06-A1 Plan de gestión ambiental','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN06-A1 Plan de gestión ambiental.docx','PGSN06-A1 Plan de gestión ambiental.docx',1,'2025-10-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2896,9,423,'PGSN06-A1 Plan gestion Ambiental','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN06-A1 Plan gestion Ambiental.docx','PGSN06-A1 Plan gestion Ambiental.docx',1,'2024-08-22','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2897,9,423,'PGSN06-F1 - Identificación de aspectos y evaluación de impactos (Compl)','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN06-F1 - Identificación de aspectos y evaluación de impactos (Compl).xls','PGSN06-F1 - Identificación de aspectos y evaluación de impactos (Compl).xls',1,'2026-01-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2898,9,423,'PGSN06-F1 - Identificación de aspectos y evaluación de impactos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN06-F1 - Identificación de aspectos y evaluación de impactos.xls','PGSN06-F1 - Identificación de aspectos y evaluación de impactos.xls',1,'2025-06-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2899,9,423,'PGSN07-A1 Observaciones Preventivas','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN07-A1 Observaciones Preventivas.docx','PGSN07-A1 Observaciones Preventivas.docx',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2900,9,423,'PGSN07-A2 Gestión de EPP','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN07-A2 Gestión de EPP.docx','PGSN07-A2 Gestión de EPP.docx',1,'2026-03-31','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2901,9,423,'PGSN07-F1 Identificacion de peligros y control de riesgos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN07-F1 Identificacion de peligros y control de riesgos.xlsx','PGSN07-F1 Identificacion de peligros y control de riesgos.xlsx',1,'2026-08-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2902,9,423,'PGSN07-F1 IPCR','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN07-F1 IPCR.xlsx','PGSN07-F1 IPCR.xlsx',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2903,9,423,'PGSN07-F2 Programa de gestión de HSEQ','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN07-F2 Programa de gestión de HSEQ.xlsx','PGSN07-F2 Programa de gestión de HSEQ.xlsx',1,'2026-01-19','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2904,9,423,'PGSN08-A2 Plan de emergencia en yacimiento','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN08-A2 Plan de emergencia en yacimiento.pptx','PGSN08-A2 Plan de emergencia en yacimiento.pptx',1,'2025-10-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2905,9,423,'PGSN08-F1 Planificación e informe de simulacro','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN08-F1 Planificación e informe de simulacro.xlsx','PGSN08-F1 Planificación e informe de simulacro.xlsx',1,'2025-10-22','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2906,9,423,'PGSN08-F2 Roles ante emergencias','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN08-F2 Roles ante emergencias.xlsx','PGSN08-F2 Roles ante emergencias.xlsx',1,'2025-11-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2907,9,423,'PGSN09-F1 Manejo de residuos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN09-F1 Manejo de residuos.xls','PGSN09-F1 Manejo de residuos.xls',1,'2025-09-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2908,9,423,'PGSN10-F1 Informe Tecnico','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN10-F1 Informe Tecnico.docx','PGSN10-F1 Informe Tecnico.docx',1,'2025-10-27','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2909,9,423,'PGSN10-F2 Listado de incidentes','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN10-F2 Listado de incidentes.xlsx','PGSN10-F2 Listado de incidentes.xlsx',1,'2025-12-18','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2910,9,423,'PGSN10-F3 Declaración ante incidente','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN10-F3 Declaración ante incidente.docx','PGSN10-F3 Declaración ante incidente.docx',1,'2025-09-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2911,9,423,'PGSN10-F4 Alerta de HSEQ','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN10-F4 Alerta de HSEQ.pptx','PGSN10-F4 Alerta de HSEQ.pptx',1,'2025-09-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2912,9,423,'PGSN11-F1 Propuesta Tecnica de Servicio','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN11-F1 Propuesta Tecnica de Servicio.docx','PGSN11-F1 Propuesta Tecnica de Servicio.docx',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2913,9,423,'PGSN11-F2 Propuesta Economica de Servicio','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN11-F2 Propuesta Economica de Servicio.docx','PGSN11-F2 Propuesta Economica de Servicio.docx',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2914,9,423,'PGSN11-F3 Lista de precios','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN11-F3 Lista de precios.docx','PGSN11-F3 Lista de precios.docx',1,'2026-07-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2915,9,423,'PGSN12-F1 Ingreso y Egreso de locación','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN12-F1 Ingreso y Egreso de locación.xlsx','PGSN12-F1 Ingreso y Egreso de locación.xlsx',1,'2026-08-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2916,9,423,'PGSN13-F1 Comunicación Interna','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN13-F1 Comunicación Interna.docx','PGSN13-F1 Comunicación Interna.docx',1,'2025-09-10','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2917,9,423,'PGSN13-F2 Boletín Informativo','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN13-F2 Boletín Informativo.pptx','PGSN13-F2 Boletín Informativo.pptx',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2918,9,423,'PGSN13-F3 Inducción al personal ingresante','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PGSN13-F3 Inducción al personal ingresante.docx','PGSN13-F3 Inducción al personal ingresante.docx',1,'2025-11-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2919,9,423,'PO-SN-01 Mantenimiento de equipos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PO-SN-01 Mantenimiento de equipos.docx','PO-SN-01 Mantenimiento de equipos.docx',1,'2025-10-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2920,9,423,'PO-SN-03 Servicio de Well testing','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PO-SN-03 Servicio de Well testing.docx','PO-SN-03 Servicio de Well testing.docx',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2921,9,423,'PO-SN-04 Planificación del servicio','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PO-SN-04 Planificación del servicio.docx','PO-SN-04 Planificación del servicio.docx',1,'2025-09-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2922,9,423,'PO-SN-05 Manejo del cambio','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PO-SN-05 Manejo del cambio.docx','PO-SN-05 Manejo del cambio.docx',1,'2025-08-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2923,9,423,'PO-SN-06 Uso de vehículos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PO-SN-06 Uso de vehículos.docx','PO-SN-06 Uso de vehículos.docx',1,'2025-11-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2924,9,423,'PO-SN-07 Tabajo en altura','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PO-SN-07 Tabajo en altura.docx','PO-SN-07 Tabajo en altura.docx',1,'2025-11-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2925,9,423,'PO-SN-08 Control y trazabilidad','','documentacion','sgi/1 SG Documentos/Z Documentos editables/PO-SN-08 Control y trazabilidad.docx','PO-SN-08 Control y trazabilidad.docx',1,'2025-11-10','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2926,9,423,'POSN-02 Servicio de slick line SMART','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN-02 Servicio de slick line SMART.docx','POSN-02 Servicio de slick line SMART.docx',1,'2025-07-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2927,9,423,'POSN-02 Servicio de slick line','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN-02 Servicio de slick line.docx','POSN-02 Servicio de slick line.docx',1,'2025-07-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2928,9,423,'POSN01-F1 Utilización del alambre','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN01-F1 Utilización del alambre.xlsx','POSN01-F1 Utilización del alambre.xlsx',1,'2026-03-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2929,9,423,'POSN01-F10 Mantenimiento de equipo de izaje y guinche','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN01-F10 Mantenimiento de equipo de izaje y guinche.xlsx','POSN01-F10 Mantenimiento de equipo de izaje y guinche.xlsx',1,'2025-10-09','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2930,9,423,'POSN01-F11 Mantenimiento de polea de reenvío','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN01-F11 Mantenimiento de polea de reenvío.xlsx','POSN01-F11 Mantenimiento de polea de reenvío.xlsx',1,'2026-03-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2931,9,423,'POSN01-F12 Control de apertura y cierre de BOP','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN01-F12 Control de apertura y cierre de BOP.xlsx','POSN01-F12 Control de apertura y cierre de BOP.xlsx',1,'2025-10-09','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2932,9,423,'POSN01-F13 Inspeccion de Herramientas Mano','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN01-F13 Inspeccion de Herramientas Mano.xlsx','POSN01-F13 Inspeccion de Herramientas Mano.xlsx',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2933,9,423,'POSN01-F14 Inspección de extintores','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN01-F14 Inspección de extintores.xlsx','POSN01-F14 Inspección de extintores.xlsx',1,'2025-10-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2934,9,423,'POSN01-F15 Manteimiento de  Equipo de control de presión','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN01-F15 Manteimiento de  Equipo de control de presión.xlsx','POSN01-F15 Manteimiento de  Equipo de control de presión.xlsx',1,'2026-07-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2935,9,423,'POSN01-F3 Chequeo Vehicular','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN01-F3 Chequeo Vehicular.xlsx','POSN01-F3 Chequeo Vehicular.xlsx',1,'2024-11-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2936,9,423,'POSN01-F9 Control de lavaojos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN01-F9 Control de lavaojos.docx','POSN01-F9 Control de lavaojos.docx',1,'2025-10-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2937,9,423,'POSN02-A1 Medidas preventivas ante fuertes vientos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A1 Medidas preventivas ante fuertes vientos.docx','POSN02-A1 Medidas preventivas ante fuertes vientos.docx',1,'2025-08-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2938,9,423,'POSN02-A10 Uso de tijera mecánica','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A10 Uso de tijera mecánica.docx','POSN02-A10 Uso de tijera mecánica.docx',1,'2025-09-18','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2939,9,423,'POSN02-A11 Conexión de herramientas al tren de slick line','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A11 Conexión de herramientas al tren de slick line.docx','POSN02-A11 Conexión de herramientas al tren de slick line.docx',1,'2025-08-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2940,9,423,'POSN02-A12 Toma de cero','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A12 Toma de cero.docx','POSN02-A12 Toma de cero.docx',1,'2025-08-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2941,9,423,'POSN02-A13 Calibración de tuberías','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A13 Calibración de tuberías.docx','POSN02-A13 Calibración de tuberías.docx',1,'2025-08-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2942,9,423,'POSN02-A14 Constatación de fondo con bloque de impresión','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A14 Constatación de fondo con bloque de impresión.docx','POSN02-A14 Constatación de fondo con bloque de impresión.docx',1,'2025-08-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2943,9,423,'POSN02-A15 Desparafinación','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A15 Desparafinación.docx','POSN02-A15 Desparafinación.docx',1,'2025-08-11','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2944,9,423,'POSN02-A16 Pesca de alambre','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A16 Pesca de alambre.docx','POSN02-A16 Pesca de alambre.docx',1,'2025-08-12','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2945,9,423,'POSN02-A17 Corte de alambre de slick line','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A17 Corte de alambre de slick line.docx','POSN02-A17 Corte de alambre de slick line.docx',1,'2025-08-12','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2946,9,423,'POSN02-A18 Corte de alambre con cortador tipo Go Devil','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A18 Corte de alambre con cortador tipo Go Devil.docx','POSN02-A18 Corte de alambre con cortador tipo Go Devil.docx',1,'2025-08-12','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2947,9,423,'POSN02-A19 Pesca de tren de herramientas','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A19 Pesca de tren de herramientas.docx','POSN02-A19 Pesca de tren de herramientas.docx',1,'2025-08-12','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2948,9,423,'POSN02-A2 Uso y mantenimiento del alambre','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A2 Uso y mantenimiento del alambre.docx','POSN02-A2 Uso y mantenimiento del alambre.docx',1,'2025-08-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2949,9,423,'POSN02-A20 Gradientes de presión y temperatura','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A20 Gradientes de presión y temperatura.docx','POSN02-A20 Gradientes de presión y temperatura.docx',1,'2025-08-12','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2950,9,423,'POSN02-A21 Ensayo con herramienta PLT','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A21 Ensayo con herramienta PLT.docx','POSN02-A21 Ensayo con herramienta PLT.docx',1,'2025-08-12','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2951,9,423,'POSN02-A22 Ensayo con ILT (En Revisión)','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A22 Ensayo con ILT (En Revisión).docx','POSN02-A22 Ensayo con ILT (En Revisión).docx',1,'2025-08-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2952,9,423,'POSN02-A23 Pistoneo de pozo','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A23 Pistoneo de pozo.docx','POSN02-A23 Pistoneo de pozo.docx',1,'2025-08-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2953,9,423,'POSN02-A24 Pesca y colocación de válvulas de recuperación secundaria','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A24 Pesca y colocación de válvulas de recuperación secundaria.docx','POSN02-A24 Pesca y colocación de válvulas de recuperación secundaria.docx',1,'2025-08-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2954,9,423,'POSN02-A25 Pesca y colocación de válvulas de Gas Lift','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A25 Pesca y colocación de válvulas de Gas Lift.docx','POSN02-A25 Pesca y colocación de válvulas de Gas Lift.docx',1,'2025-08-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2955,9,423,'POSN02-A26 Fijación y pesca de tapones W','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A26 Fijación y pesca de tapones W.docx','POSN02-A26 Fijación y pesca de tapones W.docx',1,'2025-08-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2956,9,423,'POSN02-A27 Fijación y pesca de tapones X-XN','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A27 Fijación y pesca de tapones X-XN.docx','POSN02-A27 Fijación y pesca de tapones X-XN.docx',1,'2025-08-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2957,9,423,'POSN02-A28 Fijación y pesca de tapones R-RN','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A28 Fijación y pesca de tapones R-RN.docx','POSN02-A28 Fijación y pesca de tapones R-RN.docx',1,'2025-08-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2958,9,423,'POSN02-A29 Calibración de Casing con calibre Dummy','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A29 Calibración de Casing con calibre Dummy.docx','POSN02-A29 Calibración de Casing con calibre Dummy.docx',1,'2025-08-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2959,9,423,'POSN02-A3 Ensayo de ductilidad de alambre','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A3 Ensayo de ductilidad de alambre.docx','POSN02-A3 Ensayo de ductilidad de alambre.docx',1,'2025-08-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2960,9,423,'POSN02-A30 Ruptura de disco cerámico','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A30 Ruptura de disco cerámico.docx','POSN02-A30 Ruptura de disco cerámico.docx',1,'2025-08-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2961,9,423,'POSN02-A31 Tubing Punch','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A31 Tubing Punch.docx','POSN02-A31 Tubing Punch.docx',1,'2025-11-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2962,9,423,'POSN02-A4 Cambio de empaquetaduras de stuffing box','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A4 Cambio de empaquetaduras de stuffing box.docx','POSN02-A4 Cambio de empaquetaduras de stuffing box.docx',1,'2026-02-18','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2963,9,423,'POSN02-A5 Enhebrado de alambre en stuffing box','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A5 Enhebrado de alambre en stuffing box.docx','POSN02-A5 Enhebrado de alambre en stuffing box.docx',1,'2025-08-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2964,9,423,'POSN02-A6 Uso de Tool Catcher','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A6 Uso de Tool Catcher.docx','POSN02-A6 Uso de Tool Catcher.docx',1,'2025-05-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2965,9,423,'POSN02-A7 Prueba de hermeticidad de PCE','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A7 Prueba de hermeticidad de PCE.docx','POSN02-A7 Prueba de hermeticidad de PCE.docx',1,'2025-08-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2966,9,423,'POSN02-A8 Nudo de alambre en Rope Socket','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A8 Nudo de alambre en Rope Socket.docx','POSN02-A8 Nudo de alambre en Rope Socket.docx',1,'2025-08-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2967,9,423,'POSN02-A9 Elección del tren de herramientas de slick line','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-A9 Elección del tren de herramientas de slick line.docx','POSN02-A9 Elección del tren de herramientas de slick line.docx',1,'2025-08-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2968,9,423,'POSN02-F1 Minuta de Reunion de Seguridad','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-F1 Minuta de Reunion de Seguridad.doc','POSN02-F1 Minuta de Reunion de Seguridad.doc',1,'2026-07-31','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2969,9,423,'POSN02-F4 Control operativo Slickline','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-F4 Control operativo Slickline.xlsx','POSN02-F4 Control operativo Slickline.xlsx',1,'2026-04-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2970,9,423,'POSN02-F5 Seguimiento de personal operativo','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-F5 Seguimiento de personal operativo.xlsx','POSN02-F5 Seguimiento de personal operativo.xlsx',1,'2026-04-13','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2971,9,423,'POSN02-IT-03 Uso de llave Stilson','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT-03 Uso de llave Stilson.docx','POSN02-IT-03 Uso de llave Stilson.docx',1,'2025-05-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2972,9,423,'POSN02-IT01 Movimiento de válvulas con HOT OIL-Fluxus','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT01 Movimiento de válvulas con HOT OIL-Fluxus.docx','POSN02-IT01 Movimiento de válvulas con HOT OIL-Fluxus.docx',1,'2025-05-02','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2973,9,423,'POSN02-IT02 Calibración de Casing con calibre Dummy para pozos que requieren BIF','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT02 Calibración de Casing con calibre Dummy para pozos que requieren BIF.docx','POSN02-IT02 Calibración de Casing con calibre Dummy para pozos que requieren BIF.docx',1,'2025-05-08','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2974,9,423,'POSN02-IT04 Reemplazo de Válvulas Esclusas','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT04 Reemplazo de Válvulas Esclusas.docx','POSN02-IT04 Reemplazo de Válvulas Esclusas.docx',1,'2025-05-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2975,9,423,'POSN02-IT05 Montaje de Pluma','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT05 Montaje de Pluma.docx','POSN02-IT05 Montaje de Pluma.docx',1,'2025-06-26','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2976,9,423,'POSN02-IT06 Desparafinación con PMT','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT06 Desparafinación con PMT.docx','POSN02-IT06 Desparafinación con PMT.docx',1,'2025-07-04','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2977,9,423,'POSN02-IT07 Pistoneo de pozos de forma contínua','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT07 Pistoneo de pozos de forma contínua.docx','POSN02-IT07 Pistoneo de pozos de forma contínua.docx',1,'2025-07-01','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2978,9,423,'POSN02-IT08 Montaje y desmontaje de Paños','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT08 Montaje y desmontaje de Paños.docx','POSN02-IT08 Montaje y desmontaje de Paños.docx',1,'2025-07-02','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2979,9,423,'POSN02-IT09 Montaje y desmontaje de pileta','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT09 Montaje y desmontaje de pileta.docx','POSN02-IT09 Montaje y desmontaje de pileta.docx',1,'2025-09-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2980,9,423,'POSN02-IT10 Uso de mazas','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT10 Uso de mazas.docx','POSN02-IT10 Uso de mazas.docx',1,'2025-09-29','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2981,9,423,'POSN02-IT11 Uso de Registrador de Slickline','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT11 Uso de Registrador de Slickline.docx','POSN02-IT11 Uso de Registrador de Slickline.docx',1,'2026-02-25','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2982,9,423,'POSN02-IT12 Uso de dispositivos electrónicos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT12 Uso de dispositivos electrónicos.docx','POSN02-IT12 Uso de dispositivos electrónicos.docx',1,'2026-04-14','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2983,9,423,'POSN02-IT13 Armado de equipamiento de desfogue','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN02-IT13 Armado de equipamiento de desfogue.docx','POSN02-IT13 Armado de equipamiento de desfogue.docx',1,'2026-08-05','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2984,9,423,'POSN03-A1 Determinación de encogimiento','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN03-A1 Determinación de encogimiento.docx','POSN03-A1 Determinación de encogimiento.docx',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2985,9,423,'POSN03-A2 Cambio de másico','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN03-A2 Cambio de másico.docx','POSN03-A2 Cambio de másico.docx',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2986,9,423,'POSN03-A3 Uso de bomba de doble diafragma','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN03-A3 Uso de bomba de doble diafragma.docx','POSN03-A3 Uso de bomba de doble diafragma.docx',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2987,9,423,'POSN03-F1 Registro de supervisión de well testing','','registro','sgi/1 SG Documentos/Z Documentos editables/POSN03-F1 Registro de supervisión de well testing.xls','POSN03-F1 Registro de supervisión de well testing.xls',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2988,9,423,'POSN03-F2 Check List de Montaje de equipo de well testing','','checklist','sgi/1 SG Documentos/Z Documentos editables/POSN03-F2 Check List de Montaje de equipo de well testing.xlsx','POSN03-F2 Check List de Montaje de equipo de well testing.xlsx',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2989,9,423,'POSN03-F3 Relevamiento manual de datos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN03-F3 Relevamiento manual de datos.xlsx','POSN03-F3 Relevamiento manual de datos.xlsx',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2990,9,423,'POSN03-F4 Registro de control de generador','','registro','sgi/1 SG Documentos/Z Documentos editables/POSN03-F4 Registro de control de generador.xlsx','POSN03-F4 Registro de control de generador.xlsx',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2991,9,423,'POSN04-F1 Planificación semanal','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN04-F1 Planificación semanal.xlsx','POSN04-F1 Planificación semanal.xlsx',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2992,9,423,'POSN04-F2 Diseño de servicio','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN04-F2 Diseño de servicio.docx','POSN04-F2 Diseño de servicio.docx',1,'2025-11-06','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2993,9,423,'POSN04-F3 Tren de herramientas de slick line','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN04-F3 Tren de herramientas de slick line.xls','POSN04-F3 Tren de herramientas de slick line.xls',1,'2026-01-22','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2994,9,423,'POSN04-F4 Remito','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN04-F4 Remito.docx','POSN04-F4 Remito.docx',1,'2026-05-15','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2995,9,423,'POSN04-F5 Evaluación de riesgos operativos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN04-F5 Evaluación de riesgos operativos.xlsx','POSN04-F5 Evaluación de riesgos operativos.xlsx',1,'2025-09-16','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2996,9,423,'POSN08-F1 Listado de Herramientas','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN08-F1 Listado de Herramientas.xlsx','POSN08-F1 Listado de Herramientas.xlsx',1,'2026-07-28','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2997,9,423,'POSN08-F2 Listado de Operaciones','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN08-F2 Listado de Operaciones.xlsx','POSN08-F2 Listado de Operaciones.xlsx',1,'2025-11-07','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56'),
(2998,9,423,'POSN08-F3 Visita a equipos','','documentacion','sgi/1 SG Documentos/Z Documentos editables/POSN08-F3 Visita a equipos.xlsx','POSN08-F3 Visita a equipos.xlsx',1,'2026-03-17','aprobado','1.0',NULL,NULL,'2026-09-07 15:28:56');
/*!40000 ALTER TABLE `documentos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `favoritos`
--

DROP TABLE IF EXISTS `favoritos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `favoritos` (
  `usuario_id` int(11) NOT NULL,
  `documento_id` int(11) NOT NULL,
  PRIMARY KEY (`usuario_id`,`documento_id`),
  KEY `fk_f_doc` (`documento_id`),
  CONSTRAINT `fk_f_doc` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_f_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favoritos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `favoritos` WRITE;
/*!40000 ALTER TABLE `favoritos` DISABLE KEYS */;
/*!40000 ALTER TABLE `favoritos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `finanzas_alertas`
--

DROP TABLE IF EXISTS `finanzas_alertas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finanzas_alertas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trabajador_nombre` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `canal` varchar(100) DEFAULT NULL,
  `fecha_envio` timestamp NULL DEFAULT current_timestamp(),
  `sector_id` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `fecha_vencimiento` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finanzas_alertas`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `finanzas_alertas` WRITE;
/*!40000 ALTER TABLE `finanzas_alertas` DISABLE KEYS */;
/*!40000 ALTER TABLE `finanzas_alertas` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `finanzas_checklist`
--

DROP TABLE IF EXISTS `finanzas_checklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finanzas_checklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requisito` varchar(255) NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `frecuencia` varchar(50) DEFAULT NULL,
  `completado` tinyint(1) DEFAULT 0,
  `archivo_adjunto` varchar(255) DEFAULT NULL,
  `estado_auditoria` varchar(50) DEFAULT 'APROBADO',
  `sector_id` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finanzas_checklist`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `finanzas_checklist` WRITE;
/*!40000 ALTER TABLE `finanzas_checklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `finanzas_checklist` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `finanzas_indicadores`
--

DROP TABLE IF EXISTS `finanzas_indicadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finanzas_indicadores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_indicador` varchar(100) NOT NULL,
  `valor_porcentaje` int(11) DEFAULT 0,
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sector_id` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finanzas_indicadores`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `finanzas_indicadores` WRITE;
/*!40000 ALTER TABLE `finanzas_indicadores` DISABLE KEYS */;
/*!40000 ALTER TABLE `finanzas_indicadores` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `finanzas_trabajadores`
--

DROP TABLE IF EXISTS `finanzas_trabajadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finanzas_trabajadores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `legajo` varchar(20) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `sector` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `categoria_carnet` varchar(50) DEFAULT NULL,
  `vencimiento_carnet` date NOT NULL,
  `curso_defensivo` varchar(100) DEFAULT NULL,
  `vencimiento_defensivo` date NOT NULL,
  `estado` enum('vigente','por_vencer','vencido') DEFAULT 'vigente',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `sector_id` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `legajo` (`legajo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finanzas_trabajadores`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `finanzas_trabajadores` WRITE;
/*!40000 ALTER TABLE `finanzas_trabajadores` DISABLE KEYS */;
/*!40000 ALTER TABLE `finanzas_trabajadores` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `operaciones`
--

DROP TABLE IF EXISTS `operaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `operaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('activa','inactiva') NOT NULL DEFAULT 'activa',
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_o_sector` (`sector_id`),
  CONSTRAINT `fk_o_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `operaciones`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `operaciones` WRITE;
/*!40000 ALTER TABLE `operaciones` DISABLE KEYS */;
INSERT INTO `operaciones` VALUES
(1,1,'Inspección HSEQ','Inspecciones de seguridad, ambiente y condiciones de trabajo.','activa',1),
(2,1,'Permiso de trabajo','Gestión y control de permisos de trabajo.','activa',2),
(3,2,'Mantenimiento preventivo','Planificación y seguimiento preventivo de unidades y equipos.','activa',1),
(4,2,'Control de unidades','Seguimiento de estado y disponibilidad de unidades.','activa',2),
(5,3,'Slickline','Operaciones de Slickline y trabajos asociados.','activa',1),
(6,3,'Well Testing','Pruebas de pozo, mediciones y control operativo.','activa',2),
(7,3,'Flow Back','Operaciones de Flow Back y control de retorno.','activa',3),
(8,4,'Inducción de personal','Ingreso, formación e inducción del personal.','activa',1),
(9,5,'Control presupuestario','Seguimiento presupuestario y administrativo.','activa',1),
(10,6,'Solicitud de compra','Gestión de solicitudes y compras.','activa',1),
(11,7,'Seguimiento comercial','Oportunidades y seguimiento comercial.','activa',1),
(12,9,'Auditoría interna SGI','Seguimiento de auditorías internas.','activa',1);
/*!40000 ALTER TABLE `operaciones` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `rrhh_vencimientos`
--

DROP TABLE IF EXISTS `rrhh_vencimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rrhh_vencimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empleado_nombre` varchar(150) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_vencimiento` date NOT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `sector_id` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rrhh_vencimientos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `rrhh_vencimientos` WRITE;
/*!40000 ALTER TABLE `rrhh_vencimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `rrhh_vencimientos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `sectores`
--

DROP TABLE IF EXISTS `sectores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sectores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5158 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sectores`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sectores` WRITE;
/*!40000 ALTER TABLE `sectores` DISABLE KEYS */;
INSERT INTO `sectores` VALUES
(1,'HSEQ','hseq',1),
(2,'Mantenimiento','mantenimiento',2),
(3,'Operaciones','operaciones',3),
(4,'Recursos Humanos','rrhh',4),
(5,'Finanzas','finanzas',5),
(6,'Compras','compras',6),
(7,'Ventas','ventas',7),
(8,'Gerencia','gerencia',8),
(9,'SGI','sgi',9);
/*!40000 ALTER TABLE `sectores` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `usuario_sector`
--

DROP TABLE IF EXISTS `usuario_sector`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_sector` (
  `usuario_id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `puede_ver` tinyint(1) NOT NULL DEFAULT 1,
  `puede_editar` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`usuario_id`,`sector_id`),
  KEY `fk_us_sector` (`sector_id`),
  CONSTRAINT `fk_us_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_us_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_sector`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `usuario_sector` WRITE;
/*!40000 ALTER TABLE `usuario_sector` DISABLE KEYS */;
INSERT INTO `usuario_sector` VALUES
(1,1,1,1),
(1,2,1,1),
(1,3,1,1),
(1,4,1,1),
(1,5,1,1),
(1,6,1,1),
(1,7,1,1),
(1,8,1,1),
(1,9,1,1),
(2,1,1,1),
(2,9,1,0),
(3,2,1,1),
(3,9,1,0),
(4,3,1,1),
(4,9,1,0),
(5,4,1,1),
(5,9,1,0),
(6,5,1,1),
(6,9,1,0),
(7,6,1,1),
(7,9,1,0),
(8,7,1,1),
(8,9,1,0),
(9,9,1,1),
(10,7,1,1),
(10,9,1,0),
(11,3,1,0),
(11,9,1,0);
/*!40000 ALTER TABLE `usuario_sector` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','supervisor','ventas','operador','usuario') NOT NULL DEFAULT 'operador',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES
(1,'Administrador General','admin@naser.test','$2y$10$UoxJcdxlmsUEF6odL1FNhOZNIlJABWeFl/Gdm47MHwQifnkcOQ1q6','admin',1,'2026-09-03 13:12:05'),
(2,'Supervisor HSEQ','hseq@naser.test','$2y$10$SkbCssScaHeQDCU3Wcrwbun3VYiByIRPwHHwfURHuTCjg3rrRi6GW','supervisor',1,'2026-09-03 13:12:05'),
(3,'Supervisor Mantenimiento','mantenimiento@naser.test','$2y$10$MGqDYIADSWa89SOuo1AUYup3LF9tHkLv7Cc4Y8FUKXTpxd30V9hb2','supervisor',1,'2026-09-03 13:12:05'),
(4,'Supervisor Operaciones','operaciones@naser.test','$2y$10$6e6JAkOp7BnnSLGOTtC5.OOrZe8EqzA2QanOQKJ5NIbOz3quGtGTK','supervisor',1,'2026-09-03 13:12:05'),
(5,'Supervisor RRHH','rrhh@naser.test','$2y$10$0BIHPTx9erj0gQHYctFLiuMbMFW4tg34LKG4IdYzTsVwklsIvwWNi','supervisor',1,'2026-09-03 13:12:06'),
(6,'Supervisor Finanzas','finanzas@naser.test','$2y$10$jAi/XnQMpIW4te59JrMl2O8n9l.UgaPNV3c309Bbv7aqujUUS4TSq','supervisor',1,'2026-09-03 13:12:06'),
(7,'Supervisor Compras','compras@naser.test','$2y$10$XEgoN7/Dr5maDaFakuIdm.SBugIYYHCEppwR68M/4Shfr8j8Eqt26','supervisor',1,'2026-09-03 13:12:06'),
(8,'Supervisor Ventas','ventas@naser.test','$2y$10$3KFq6vICnFAIPN06YQtXOew7hMFDGkrSElAnXXcUf56Nlannj97Eq','supervisor',1,'2026-09-03 13:12:06'),
(9,'Supervisor SGI','sgi@naser.test','$2y$10$QTcJJukbzscFqg8EtAWHru7JY4xzMNZUcN2UxBfuJqPBX5TW9YVZW','supervisor',1,'2026-09-03 13:12:06'),
(10,'Usuario Ventas','comercial@naser.test','$2y$10$1S1j0FsyjXPcnqfETJ4FMOcvFZKwdI57xo1zNEMq2/pIgnezLrFqO','ventas',1,'2026-09-03 13:12:06'),
(11,'Operador Prueba','operador@naser.test','$2y$10$8FvDQNl0/L663q93OXhp5Oint.ihSNq50XNzjc/1chOu.ignFW106','operador',1,'2026-09-03 13:12:06');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ventas_clientes`
--

DROP TABLE IF EXISTS `ventas_clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `contacto_nombre` varchar(100) DEFAULT NULL,
  `contacto_email` varchar(120) DEFAULT NULL,
  `contacto_telefono` varchar(50) DEFAULT NULL,
  `provincia` varchar(60) DEFAULT 'Neuquén',
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ventas_clientes_sector` (`sector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas_clientes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ventas_clientes` WRITE;
/*!40000 ALTER TABLE `ventas_clientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas_clientes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ventas_contratos`
--

DROP TABLE IF EXISTS `ventas_contratos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas_contratos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `numero_contrato` varchar(80) NOT NULL,
  `servicio_operativo` varchar(150) NOT NULL,
  `monto_estimado_usd` decimal(14,2) NOT NULL DEFAULT 0.00,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('Activo','Licitación','Finalizado') NOT NULL DEFAULT 'Activo',
  `observaciones` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_vc_cliente` (`cliente_id`),
  KEY `idx_ventas_contratos_sector` (`sector_id`),
  CONSTRAINT `fk_vc_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `ventas_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas_contratos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ventas_contratos` WRITE;
/*!40000 ALTER TABLE `ventas_contratos` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas_contratos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ventas_costos_operativos`
--

DROP TABLE IF EXISTS `ventas_costos_operativos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas_costos_operativos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `linea_servicio` varchar(150) NOT NULL,
  `ingreso_diario_usd` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_directo_finanzas_usd` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_mantenimiento_usd` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado_rentabilidad` varchar(50) DEFAULT 'Sostenible',
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ventas_costos_sector` (`sector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas_costos_operativos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ventas_costos_operativos` WRITE;
/*!40000 ALTER TABLE `ventas_costos_operativos` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas_costos_operativos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ventas_cotizaciones`
--

DROP TABLE IF EXISTS `ventas_cotizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas_cotizaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `codigo_cotizacion` varchar(80) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `monto_usd` decimal(14,2) NOT NULL DEFAULT 0.00,
  `estado_kpi` enum('Ganada','En Estudio','No Adjudicada') NOT NULL DEFAULT 'En Estudio',
  `fecha_presentacion` date NOT NULL,
  `fecha_resolucion` date DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_cotizacion` (`codigo_cotizacion`),
  KEY `fk_vcot_cliente` (`cliente_id`),
  KEY `idx_ventas_cot_sector` (`sector_id`),
  CONSTRAINT `fk_vcot_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `ventas_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas_cotizaciones`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ventas_cotizaciones` WRITE;
/*!40000 ALTER TABLE `ventas_cotizaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas_cotizaciones` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ventas_crm`
--

DROP TABLE IF EXISTS `ventas_crm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas_crm` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `oportunidad_servicio` varchar(180) NOT NULL,
  `etapa_pipeline` enum('Prospecto','Cotizado','En Negociación','Cierre Ganado','Perdido') NOT NULL DEFAULT 'Prospecto',
  `fecha_ultimo_contacto` date NOT NULL,
  `proxima_accion` text DEFAULT NULL,
  `responsable_naser` varchar(120) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_vcrm_cliente` (`cliente_id`),
  KEY `idx_ventas_crm_sector` (`sector_id`),
  CONSTRAINT `fk_vcrm_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `ventas_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas_crm`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ventas_crm` WRITE;
/*!40000 ALTER TABLE `ventas_crm` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas_crm` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ventas_mensajes`
--

DROP TABLE IF EXISTS `ventas_mensajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas_mensajes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `sector_emisor` varchar(80) NOT NULL DEFAULT 'Ventas',
  `sector_destino` varchar(80) NOT NULL,
  `asunto` varchar(180) NOT NULL,
  `mensaje_texto` text NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `fecha_envio` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ventas_mensajes_sector` (`sector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas_mensajes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ventas_mensajes` WRITE;
/*!40000 ALTER TABLE `ventas_mensajes` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas_mensajes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ventas_notificaciones`
--

DROP TABLE IF EXISTS `ventas_notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas_notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo_alerta` enum('Alerta','Aviso','Finanzas','Vencimiento') NOT NULL DEFAULT 'Aviso',
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ventas_notif_sector` (`sector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas_notificaciones`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ventas_notificaciones` WRITE;
/*!40000 ALTER TABLE `ventas_notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas_notificaciones` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ventas_precios`
--

DROP TABLE IF EXISTS `ventas_precios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas_precios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `servicio_nombre` varchar(150) NOT NULL,
  `unidad_medida` varchar(80) NOT NULL,
  `modalidad` varchar(120) DEFAULT NULL,
  `tarifa_base_usd` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ticket_promedio_tipo_usd` decimal(12,2) NOT NULL DEFAULT 0.00,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ventas_precios_sector` (`sector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas_precios`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ventas_precios` WRITE;
/*!40000 ALTER TABLE `ventas_precios` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas_precios` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ventas_presentaciones`
--

DROP TABLE IF EXISTS `ventas_presentaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas_presentaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sector_id` int(11) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `empresa` varchar(80) NOT NULL DEFAULT 'Naser',
  `categoria` varchar(80) DEFAULT 'General',
  `archivo_path` varchar(500) NOT NULL,
  `fecha_carga` date NOT NULL DEFAULT curdate(),
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ventas_presentaciones_sector` (`sector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas_presentaciones`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ventas_presentaciones` WRITE;
/*!40000 ALTER TABLE `ventas_presentaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas_presentaciones` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-09-15 13:27:33
