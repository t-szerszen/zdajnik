/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_audit_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_audit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` int unsigned NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_polish_ci NOT NULL,
  `target_type` varchar(100) COLLATE utf8mb4_polish_ci NOT NULL,
  `target_id` int unsigned DEFAULT NULL,
  `old_value` longtext COLLATE utf8mb4_polish_ci,
  `new_value` longtext COLLATE utf8mb4_polish_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_user` (`admin_user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `admin_audit_logs_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `analytics_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `event_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_hash` char(64) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `exam_type_id` int unsigned DEFAULT NULL,
  `question_id` int unsigned DEFAULT NULL,
  `topic_id` int unsigned DEFAULT NULL,
  `test_id` int unsigned DEFAULT NULL,
  `source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `test_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `auth_provider` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `page_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `search_term` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `is_correct` tinyint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_analytics_event_type` (`event_type`),
  KEY `idx_analytics_event_date` (`event_date`),
  KEY `idx_analytics_user_id` (`user_id`),
  KEY `idx_analytics_created_at` (`created_at`),
  KEY `idx_analytics_type_date` (`event_type`,`event_date`),
  KEY `idx_analytics_user_date` (`user_id`,`event_date`),
  KEY `idx_analytics_exam_date` (`exam_type_id`,`event_date`),
  KEY `idx_analytics_test_date` (`test_id`,`event_date`),
  KEY `idx_analytics_test_type_date` (`test_type`,`event_date`),
  KEY `idx_analytics_provider_date` (`auth_provider`,`event_date`),
  KEY `idx_analytics_path_date` (`page_path`,`event_date`),
  KEY `idx_analytics_source_date` (`source`,`event_date`),
  KEY `idx_analytics_question_date` (`question_id`,`event_date`),
  KEY `idx_analytics_correct_date` (`is_correct`,`event_date`),
  KEY `idx_analytics_session_date` (`session_hash`,`event_date`),
  KEY `idx_analytics_type_topic_date` (`event_type`,`topic_id`,`event_date`),
  KEY `idx_analytics_type_search_date` (`event_type`,`search_term`,`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `answers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `answers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `question_version_id` int unsigned NOT NULL,
  `answer_text` text NOT NULL,
  `answer_order` int unsigned NOT NULL DEFAULT '1',
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_answer_order` (`question_version_id`,`answer_order`),
  CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`question_version_id`) REFERENCES `question_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attempt_answers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attempt_answers` (
  `attempt_id` int unsigned NOT NULL,
  `question_version_id` int unsigned NOT NULL,
  `answer_id` int unsigned DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `answered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `position` smallint unsigned DEFAULT NULL,
  PRIMARY KEY (`attempt_id`,`question_version_id`),
  KEY `question_version_id` (`question_version_id`),
  KEY `answer_id` (`answer_id`),
  CONSTRAINT `attempt_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attempt_answers_ibfk_2` FOREIGN KEY (`question_version_id`) REFERENCES `question_versions` (`id`),
  CONSTRAINT `attempt_answers_ibfk_3` FOREIGN KEY (`answer_id`) REFERENCES `answers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attempt_topics`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attempt_topics` (
  `attempt_id` int unsigned NOT NULL,
  `topic_id` int unsigned NOT NULL,
  PRIMARY KEY (`attempt_id`,`topic_id`),
  KEY `topic_id` (`topic_id`),
  CONSTRAINT `attempt_topics_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attempt_topics_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attempts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `exam_type_id` int unsigned NOT NULL,
  `test_type` enum('full_exam','personalized') NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `correct_count` int NOT NULL DEFAULT '0',
  `total_questions` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_type_id` (`exam_type_id`),
  KEY `attempts_ibfk_1` (`user_id`),
  KEY `idx_attempts_completed_filters` (`completed_at`,`exam_type_id`,`test_type`,`user_id`),
  KEY `idx_attempts_started_filters` (`started_at`,`exam_type_id`,`test_type`,`user_id`),
  CONSTRAINT `attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attempts_ibfk_2` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exam_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `question_reports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `question_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `question_id` int unsigned NOT NULL,
  `question_version_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `category` enum('wrong_answer','content_error','explanation','display','other') COLLATE utf8mb4_polish_ci NOT NULL,
  `message` varchar(1000) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `context` varchar(32) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `status` enum('new','resolved','rejected') COLLATE utf8mb4_polish_ci NOT NULL DEFAULT 'new',
  `resolved_by` int unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_question_reports_status_created` (`status`,`created_at`),
  KEY `idx_question_reports_question` (`question_id`),
  KEY `fk_question_reports_version` (`question_version_id`),
  KEY `fk_question_reports_user` (`user_id`),
  KEY `fk_question_reports_resolver` (`resolved_by`),
  CONSTRAINT `fk_question_reports_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_question_reports_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_question_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_question_reports_version` FOREIGN KEY (`question_version_id`) REFERENCES `question_versions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `question_versions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `question_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `question_id` int unsigned NOT NULL,
  `version_num` int NOT NULL DEFAULT '1',
  `question_text` text NOT NULL,
  `image_name` varchar(500) DEFAULT NULL,
  `explanation` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qv` (`question_id`,`version_num`),
  CONSTRAINT `question_versions_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `questions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `exam_type_id` int unsigned NOT NULL,
  `topic_id` int unsigned NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') NOT NULL DEFAULT 'medium',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_type_id` (`exam_type_id`),
  KEY `topic_id` (`topic_id`),
  KEY `idx_difficulty` (`difficulty`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `questions_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `saved_questions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `saved_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `question_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_question` (`user_id`,`question_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_saved_questions_created` (`created_at`,`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schema_migrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_migrations` (
  `version` varchar(128) NOT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_notes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_notes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sheet_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `subjective_difficulty` tinyint unsigned NOT NULL,
  `problem_description` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sheet_user` (`sheet_id`,`user_id`),
  KEY `idx_sheet_note_user` (`user_id`),
  CONSTRAINT `sheet_notes_ibfk_1` FOREIGN KEY (`sheet_id`) REFERENCES `sheets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sheet_notes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheet_topics`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheet_topics` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `exam_type_id` int unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `display_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sheet_topic` (`exam_type_id`,`name`),
  CONSTRAINT `fk_sheet_topics_exam_type` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sheets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sheets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sheet_topic_id` int unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `difficulty` tinyint unsigned NOT NULL,
  `pdf_path` varchar(255) NOT NULL,
  `zip_path` varchar(255) DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `resources_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sheet_topic` (`sheet_topic_id`),
  CONSTRAINT `sheets_ibfk_1` FOREIGN KEY (`sheet_topic_id`) REFERENCES `sheet_topics` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `topics`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topics` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `exam_type_id` int unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_topic` (`exam_type_id`,`name`),
  CONSTRAINT `topics_ibfk_1` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_progress`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_progress` (
  `user_id` int unsigned NOT NULL,
  `question_id` int unsigned NOT NULL,
  `attempts_count` int NOT NULL DEFAULT '0',
  `correct_count` int NOT NULL DEFAULT '0',
  `incorrect_count` int NOT NULL DEFAULT '0',
  `skipped_count` int NOT NULL DEFAULT '0',
  `last_result` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`question_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `user_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_progress_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token` varchar(512) NOT NULL,
  `type` enum('email_verify','password_reset','email_change','account_deletion','progress_anonymization') NOT NULL,
  `token_data` varchar(255) DEFAULT NULL,
  `auth_code` varchar(50) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_token` (`token`),
  CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `google_id` varchar(255) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `auth_provider` enum('local','google') COLLATE utf8mb4_polish_ci NOT NULL DEFAULT 'local',
  `first_name` varchar(100) COLLATE utf8mb4_polish_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_polish_ci NOT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `password_hash` char(255) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `role` enum('user','redaktor','admin') COLLATE utf8mb4_polish_ci DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_google_id` (`google_id`),
  KEY `idx_users_analytics_filters` (`created_at`,`auth_provider`,`role`,`is_verified`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'zdajnik_db'
--
--
-- WARNING: can't read the INFORMATION_SCHEMA.libraries table. It's most probably an old server 8.0.45.
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed

--
-- Dbmate schema migrations
--

LOCK TABLES `schema_migrations` WRITE;
INSERT INTO `schema_migrations` (version) VALUES
  ('20260207130033'),
  ('20260218102245'),
  ('20260218105514'),
  ('20260218120000'),
  ('20260218143259'),
  ('20260220213620'),
  ('20260220222647'),
  ('20260326114113'),
  ('20260410105320'),
  ('20260515181036'),
  ('20260520120000'),
  ('20260520170000'),
  ('20260611220000'),
  ('20260612193000'),
  ('20260715193439'),
  ('20260719120000'),
  ('20260719150000'),
  ('20260802220000'),
  ('20260806120000'),
  ('20261124120000');
UNLOCK TABLES;
