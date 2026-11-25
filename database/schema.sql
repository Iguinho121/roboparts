-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: flux
-- ------------------------------------------------------
-- Server version	8.0.46

CREATE DATABASE flux;

USE flux;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'Microcontroladores','Placas de desenvolvimento e processamento central'),(2,'Sensores','Componentes para leitura do ambiente (luz, distância, temperatura)'),(3,'Atuadores','Motores, servos e componentes de movimento');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `componentes`
--

DROP TABLE IF EXISTS `componentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `componentes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text,
  `codigo` varchar(50) DEFAULT NULL,
  `categoria_id` int NOT NULL,
  `fornecedor_id` int DEFAULT NULL,
  `condicao_id` int NOT NULL,
  `quantidade_total` int NOT NULL DEFAULT '0',
  `quantidade_disponivel` int NOT NULL DEFAULT '0',
  `estoque_minimo` int NOT NULL DEFAULT '5',
  `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `categoria_id` (`categoria_id`),
  KEY `fornecedor_id` (`fornecedor_id`),
  KEY `condicao_id` (`condicao_id`),
  CONSTRAINT `componentes_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `componentes_ibfk_2` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `componentes_ibfk_3` FOREIGN KEY (`condicao_id`) REFERENCES `condicoes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `componentes`
--

LOCK TABLES `componentes` WRITE;
/*!40000 ALTER TABLE `componentes` DISABLE KEYS */;
INSERT INTO `componentes` VALUES (1,'Arduino Uno R3','Placa microcontroladora ATmega328P','ARD-001',1,1,1,10,8,3,'2026-11-18 15:18:31'),(2,'Sensor Ultrassônico HC-SR04','Sensor de distância','SEN-001',2,2,1,15,15,5,'2026-11-18 15:18:31'),(3,'Micro Servo 9g SG90','Servo motor pequeno para articulações','ATU-001',3,1,2,4,4,5,'2026-11-18 15:18:31'),(4,'Motor DC com Roda','Motor utilizado para movimentação de robôs.','MOR-001',3,2,2,10,8,5,'2026-11-18 15:18:31'),(5,'Sensor TCRT5000','Sensor IR para linhas e obstáculos.','SEN-002',2,2,1,8,6,5,'2026-11-18 15:30:30'),(6,'Driver Ponte H L298N','Driver para controle de velocidade e sentido de motores.','DRI-001',3,2,1,10,9,6,'2026-11-18 15:34:37'),(7,'Módulo ESP32 Wi-Fi','Microcontrolador com Wi-Fi e Bluetooth para IoT.','MOD-001',3,2,1,15,12,10,'2026-11-18 16:43:12'),(8,'Sensor DHT22 Temp/Umidade','Sensor digital de temperatura e umidade.','SEN-003',2,1,1,10,5,6,'2026-11-18 16:44:47'),(9,'Display OLED 0.96\" I2C','Display gráfico OLED I2C de 0,96\".','DIS-001',2,1,2,15,10,8,'2026-11-18 16:46:11'),(10,'Arduino Mega 2560','Placa microcontroladora com múltiplos pinos e memória expandida.','ARD-002',1,1,1,10,8,3,'2026-11-18 16:51:35'),(11,'Micro Servo 9g SG90','Micro servo motor de controle angular (0° a 180°).','MIC-001',3,1,1,10,8,5,'2026-11-18 16:51:35'),(12,'Sensor Ultrassônico HC-SR04','Sensor ultrassônico de distância e detecção de objetos.','SEN-004',2,1,1,12,9,7,'2026-11-18 16:51:35'),(13,'Mini Bomba d\'Água Submersa','Mini bomba silenciosa para circulação de água.','MIN-001',3,1,2,10,8,7,'2026-11-23 14:21:13');
/*!40000 ALTER TABLE `componentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `condicoes`
--

DROP TABLE IF EXISTS `condicoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `condicoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `descricao` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `condicoes`
--

LOCK TABLES `condicoes` WRITE;
/*!40000 ALTER TABLE `condicoes` DISABLE KEYS */;
INSERT INTO `condicoes` VALUES (1,'Novo','Componente na embalagem original, nunca utilizado'),(2,'Usado - Bom estado','Componente já utilizado, mas funcionando perfeitamente'),(3,'Danificado','Componente com defeito ou precisando de reparo');
/*!40000 ALTER TABLE `condicoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fornecedores`
--

DROP TABLE IF EXISTS `fornecedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fornecedores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fornecedores`
--

LOCK TABLES `fornecedores` WRITE;
/*!40000 ALTER TABLE `fornecedores` DISABLE KEYS */;
INSERT INTO `fornecedores` VALUES (1,'RoboCore','robo.core@gmail.com','11910307554','Rua Ribeirão Preto, 100 - SP'),(2,'FilipeFlop','felipe.flop@gmail.com','11964371606','Av. Prosperidade, 200 - SC');
/*!40000 ALTER TABLE `fornecedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kit_componentes`
--

DROP TABLE IF EXISTS `kit_componentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kit_componentes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kit_id` int NOT NULL,
  `componente_id` int NOT NULL,
  `quantidade` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kit_id` (`kit_id`,`componente_id`),
  KEY `componente_id` (`componente_id`),
  CONSTRAINT `kit_componentes_ibfk_1` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kit_componentes_ibfk_2` FOREIGN KEY (`componente_id`) REFERENCES `componentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kit_componentes`
--

LOCK TABLES `kit_componentes` WRITE;
/*!40000 ALTER TABLE `kit_componentes` DISABLE KEYS */;
INSERT INTO `kit_componentes` VALUES (1,1,1,7),(2,1,2,10),(3,1,3,3),(4,2,4,3),(5,2,5,6),(6,2,6,8),(7,3,1,6),(8,3,4,5),(9,3,6,8),(10,4,1,5),(11,4,5,6),(12,4,4,7),(13,5,7,8),(14,5,8,4),(15,5,9,5),(16,6,10,6),(17,6,11,3),(18,6,12,7),(19,7,1,5),(20,7,8,4),(21,7,13,6),(22,8,1,8),(23,8,8,4),(24,8,13,7);
/*!40000 ALTER TABLE `kit_componentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kits`
--

DROP TABLE IF EXISTS `kits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text,
  `tipo_projeto` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kits`
--

LOCK TABLES `kits` WRITE;
/*!40000 ALTER TABLE `kits` DISABLE KEYS */;
INSERT INTO `kits` VALUES (1,'Kit Iniciante Arduino','Pacote básico para quem está começando em robótica','Educacional'),(2,'Kit Braço Robótico Manipulador 4 DOF','Estrutura completa com 4 micro servos para prototipagem e manipulação de objetos.','Intermediário'),(3,'Kit Rover Autônomo com Ultrassom & Wi-Fi','Robô com conectividade remota ESP32 e navegação com desvio de obstáculos.','Avançado'),(4,'Kit Seguidor de Linha Inteligente','Robô móvel compacto projetado para competições de velocidade e lógica básica de sensores ópticos.','Educacional'),(5,'Kit Estação Meteorológica IoT','Sistema conectado para monitoramento remoto de temperatura, umidade e qualidade do ar em tempo real.','Intermediário'),(6,'Kit Quadrúpede Robótico Spider','Plataforma articulada de 4 patas para estudos de cinemática inversa e locomoção em terrenos irregulares.','Avançado'),(7,'Kit Garra Mecânica Controlada','Sistema de atuação simples para aprendizado de controle PWM e precisão de movimentos angulares.','Educacional'),(8,'Kit Sistema de Irrigação Automatizado','Projeto de automação ambiental com leitura de umidade do solo e acionamento inteligente de bomba.','Intermediário');
/*!40000 ALTER TABLE `kits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimentacoes`
--

DROP TABLE IF EXISTS `movimentacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimentacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `componente_id` int NOT NULL,
  `projeto_id` int DEFAULT NULL,
  `tipo_movimentacao` enum('Entrada','Saida','Emprestimo','Devolucao') NOT NULL,
  `quantidade` int NOT NULL,
  `data_movimentacao` datetime DEFAULT CURRENT_TIMESTAMP,
  `observacao` text,
  PRIMARY KEY (`id`),
  KEY `componente_id` (`componente_id`),
  KEY `projeto_id` (`projeto_id`),
  CONSTRAINT `movimentacoes_ibfk_1` FOREIGN KEY (`componente_id`) REFERENCES `componentes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movimentacoes_ibfk_2` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimentacoes`
--

LOCK TABLES `movimentacoes` WRITE;
/*!40000 ALTER TABLE `movimentacoes` DISABLE KEYS */;
INSERT INTO `movimentacoes` VALUES (1,1,NULL,'Entrada',10,'2026-11-18 15:18:31','Compra inicial de Arduinos'),(2,1,1,'Emprestimo',2,'2026-11-18 15:18:31','Arduinos separados para a equipe do Carrinho Seguidor de Linha');
/*!40000 ALTER TABLE `movimentacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projeto_componentes`
--

DROP TABLE IF EXISTS `projeto_componentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projeto_componentes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `projeto_id` int NOT NULL,
  `componente_id` int NOT NULL,
  `quantidade` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projeto_id` (`projeto_id`,`componente_id`),
  KEY `componente_id` (`componente_id`),
  CONSTRAINT `projeto_componentes_ibfk_1` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projeto_componentes_ibfk_2` FOREIGN KEY (`componente_id`) REFERENCES `componentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projeto_componentes`
--

LOCK TABLES `projeto_componentes` WRITE;
/*!40000 ALTER TABLE `projeto_componentes` DISABLE KEYS */;
INSERT INTO `projeto_componentes` VALUES (1,1,1,1),(2,1,2,2),(3,2,1,1),(4,2,3,4);
/*!40000 ALTER TABLE `projeto_componentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projetos`
--

DROP TABLE IF EXISTS `projetos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projetos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `status` enum('Planejamento','Em andamento','Concluido') DEFAULT 'Planejamento',
  `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projetos`
--

LOCK TABLES `projetos` WRITE;
/*!40000 ALTER TABLE `projetos` DISABLE KEYS */;
INSERT INTO `projetos` VALUES (1,'Carrinho Seguidor de Linha','Robô autônomo que segue uma linha preta no chão','2026-11-01',NULL,'Em andamento','2026-11-18 15:18:31'),(2,'Braço Robótico Industrial','Braço de 4 eixos para separação de peças','2026-11-15',NULL,'Planejamento','2026-11-18 15:18:31');
/*!40000 ALTER TABLE `projetos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo_usuario` enum('Comum','Admin') DEFAULT 'Comum',
  `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador Geral','igor.s@roboparts.com','123456','Admin','2026-11-18 15:18:31'),(2,'Aluno Teste','vitor@email.com','123456','Comum','2026-11-18 15:18:31');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-24 15:34:42

ALTER TABLE movimentacoes
MODIFY COLUMN tipo_movimentacao ENUM('Entrada', 'Saida', 'Emprestimo', 'Devolucao', 'Kit Requisitado') NOT NULL;

ALTER TABLE movimentacoes
ADD COLUMN devolvido TINYINT(1) NOT NULL DEFAULT 0;
	