-- =====================================================================================
--  SPIMS — Spare Parts Manufacturing & Inventory Management System
--  Complete installation SQL for phpMyAdmin (schema + base data)
--
--  CONTAINS: all tables, indexes & foreign keys; 4 roles (Super Admin, CNC User,
--            Import Inventory User, Combined User) with their permissions; CNC machines
--            M-1 … M-20; operations 1st/2nd/3rd; units; part categories; migrations record.
--  DOES NOT CONTAIN: any user account or password, demo/test data.
--
--  HOW TO USE (Hostinger):
--   1. hPanel → Databases → MySQL Databases: create an EMPTY database + user.
--   2. hPanel → phpMyAdmin → select that database → Import → choose this file → Go.
--   3. Put the DB name/user/password in the app's .env file.
--   4. Create the first Super Admin: set SPIMS_SETUP_TOKEN=<long random text> in .env,
--      open https://your-domain.com/setup, then REMOVE the token from .env.
--
--  WARNING: importing into an existing SPIMS database DROPS and recreates every table
--           (all data is lost). Only import into an empty/new database.
--  Compatible with MySQL 8.0.16+ and MariaDB 10.4+.  Generated from the app's migrations.
-- =====================================================================================

SET NAMES utf8mb4;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `module` varchar(40) DEFAULT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `reference` varchar(60) DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `activity_logs_action_index` (`action`),
  KEY `activity_logs_module_index` (`module`),
  KEY `activity_logs_reference_index` (`reference`),
  KEY `activity_logs_created_at_index` (`created_at`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
DROP TABLE IF EXISTS `cnc_inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cnc_inventory_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(40) NOT NULL,
  `transaction_date` date NOT NULL,
  `spare_part_id` bigint(20) unsigned NOT NULL,
  `part_name` varchar(191) NOT NULL,
  `part_sku` varchar(60) NOT NULL,
  `unit_name` varchar(50) DEFAULT NULL,
  `type` enum('opening','production_receipt','issue','adjustment_in','adjustment_out','reversal') NOT NULL,
  `quantity_in` decimal(14,3) NOT NULL DEFAULT 0.000,
  `quantity_out` decimal(14,3) NOT NULL DEFAULT 0.000,
  `balance_after` decimal(14,3) NOT NULL,
  `completion_id` bigint(20) unsigned DEFAULT NULL,
  `stock_adjustment_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_of_id` bigint(20) unsigned DEFAULT NULL,
  `is_reversed` tinyint(1) NOT NULL DEFAULT 0,
  `issued_to` varchar(150) DEFAULT NULL,
  `machinery_model_id` bigint(20) unsigned DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `idempotency_key` char(36) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnc_inventory_transactions_reference_no_unique` (`reference_no`),
  UNIQUE KEY `cnc_inventory_transactions_reversal_of_id_unique` (`reversal_of_id`),
  UNIQUE KEY `cnc_inventory_transactions_idempotency_key_unique` (`idempotency_key`),
  KEY `cnc_inventory_transactions_completion_id_foreign` (`completion_id`),
  KEY `cnc_inventory_transactions_stock_adjustment_id_foreign` (`stock_adjustment_id`),
  KEY `cnc_inventory_transactions_machinery_model_id_foreign` (`machinery_model_id`),
  KEY `cnc_inventory_transactions_created_by_foreign` (`created_by`),
  KEY `cnc_inventory_transactions_spare_part_id_transaction_date_index` (`spare_part_id`,`transaction_date`),
  KEY `cnc_inventory_transactions_type_transaction_date_index` (`type`,`transaction_date`),
  CONSTRAINT `cnc_inventory_transactions_completion_id_foreign` FOREIGN KEY (`completion_id`) REFERENCES `cnc_production_completions` (`id`),
  CONSTRAINT `cnc_inventory_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cnc_inventory_transactions_machinery_model_id_foreign` FOREIGN KEY (`machinery_model_id`) REFERENCES `machinery_models` (`id`),
  CONSTRAINT `cnc_inventory_transactions_reversal_of_id_foreign` FOREIGN KEY (`reversal_of_id`) REFERENCES `cnc_inventory_transactions` (`id`),
  CONSTRAINT `cnc_inventory_transactions_spare_part_id_foreign` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`),
  CONSTRAINT `cnc_inventory_transactions_stock_adjustment_id_foreign` FOREIGN KEY (`stock_adjustment_id`) REFERENCES `stock_adjustments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `cnc_inventory_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `cnc_inventory_transactions` ENABLE KEYS */;
DROP TABLE IF EXISTS `cnc_production_completions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cnc_production_completions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(40) NOT NULL,
  `completion_date` date NOT NULL,
  `spare_part_id` bigint(20) unsigned NOT NULL,
  `part_name` varchar(191) NOT NULL,
  `part_sku` varchar(60) NOT NULL,
  `quantity_inspected` decimal(14,3) NOT NULL,
  `quantity_accepted` decimal(14,3) NOT NULL,
  `quantity_rejected` decimal(14,3) NOT NULL DEFAULT 0.000,
  `status` enum('pending','approved','rejected','reversed') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `submitted_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `decision_notes` varchar(500) DEFAULT NULL,
  `reversed_by` bigint(20) unsigned DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversal_reason` varchar(500) DEFAULT NULL,
  `idempotency_key` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnc_production_completions_reference_no_unique` (`reference_no`),
  UNIQUE KEY `cnc_production_completions_idempotency_key_unique` (`idempotency_key`),
  KEY `cnc_production_completions_submitted_by_foreign` (`submitted_by`),
  KEY `cnc_production_completions_approved_by_foreign` (`approved_by`),
  KEY `cnc_production_completions_reversed_by_foreign` (`reversed_by`),
  KEY `cnc_production_completions_spare_part_id_status_index` (`spare_part_id`,`status`),
  KEY `cnc_production_completions_status_completion_date_index` (`status`,`completion_date`),
  CONSTRAINT `cnc_production_completions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cnc_production_completions_reversed_by_foreign` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cnc_production_completions_spare_part_id_foreign` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`),
  CONSTRAINT `cnc_production_completions_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `cnc_production_completions` DISABLE KEYS */;
/*!40000 ALTER TABLE `cnc_production_completions` ENABLE KEYS */;
DROP TABLE IF EXISTS `cnc_production_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cnc_production_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(40) NOT NULL,
  `production_date` date NOT NULL,
  `machine_id` bigint(20) unsigned NOT NULL,
  `spare_part_id` bigint(20) unsigned NOT NULL,
  `part_name` varchar(191) NOT NULL,
  `part_sku` varchar(60) NOT NULL,
  `machinery_model_id` bigint(20) unsigned DEFAULT NULL,
  `operation_id` bigint(20) unsigned NOT NULL,
  `operation_name` varchar(50) NOT NULL,
  `operation_sequence` smallint(5) unsigned NOT NULL,
  `is_final_operation` tinyint(1) NOT NULL DEFAULT 0,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `duration_minutes` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `operator_id` bigint(20) unsigned NOT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('running','completed','cancelled') NOT NULL DEFAULT 'completed',
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancel_reason` varchar(500) DEFAULT NULL,
  `idempotency_key` char(36) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnc_production_records_reference_no_unique` (`reference_no`),
  UNIQUE KEY `cnc_production_records_idempotency_key_unique` (`idempotency_key`),
  KEY `cnc_production_records_machinery_model_id_foreign` (`machinery_model_id`),
  KEY `cnc_production_records_operation_id_foreign` (`operation_id`),
  KEY `cnc_production_records_cancelled_by_foreign` (`cancelled_by`),
  KEY `cnc_production_records_created_by_foreign` (`created_by`),
  KEY `cnc_production_records_updated_by_foreign` (`updated_by`),
  KEY `cnc_production_records_production_date_machine_id_index` (`production_date`,`machine_id`),
  KEY `cnc_production_records_machine_id_status_index` (`machine_id`,`status`),
  KEY `cnc_production_records_spare_part_id_operation_id_status_index` (`spare_part_id`,`operation_id`,`status`),
  KEY `cnc_production_records_operator_id_production_date_index` (`operator_id`,`production_date`),
  KEY `cnc_production_records_status_production_date_index` (`status`,`production_date`),
  CONSTRAINT `cnc_production_records_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cnc_production_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cnc_production_records_machine_id_foreign` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`),
  CONSTRAINT `cnc_production_records_machinery_model_id_foreign` FOREIGN KEY (`machinery_model_id`) REFERENCES `machinery_models` (`id`),
  CONSTRAINT `cnc_production_records_operation_id_foreign` FOREIGN KEY (`operation_id`) REFERENCES `operations` (`id`),
  CONSTRAINT `cnc_production_records_operator_id_foreign` FOREIGN KEY (`operator_id`) REFERENCES `operators` (`id`),
  CONSTRAINT `cnc_production_records_spare_part_id_foreign` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`),
  CONSTRAINT `cnc_production_records_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `cnc_production_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `cnc_production_records` ENABLE KEYS */;
DROP TABLE IF EXISTS `document_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_sequences` (
  `prefix` varchar(30) NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `last_number` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`prefix`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `document_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_sequences` ENABLE KEYS */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
DROP TABLE IF EXISTS `imported_inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `imported_inventory_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(40) NOT NULL,
  `transaction_date` date NOT NULL,
  `spare_part_id` bigint(20) unsigned NOT NULL,
  `part_name` varchar(191) NOT NULL,
  `part_sku` varchar(60) NOT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `unit_name` varchar(50) DEFAULT NULL,
  `specification` varchar(255) DEFAULT NULL,
  `type` enum('opening','in','out','adjustment_in','adjustment_out','reversal') NOT NULL,
  `quantity_in` decimal(14,3) NOT NULL DEFAULT 0.000,
  `quantity_out` decimal(14,3) NOT NULL DEFAULT 0.000,
  `balance_after` decimal(14,3) NOT NULL,
  `supplier_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(150) DEFAULT NULL,
  `document_reference` varchar(100) DEFAULT NULL,
  `unit_cost` decimal(14,2) DEFAULT NULL,
  `currency` char(3) DEFAULT NULL,
  `machinery_model_id` bigint(20) unsigned DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `collected_by` varchar(150) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `machine_assembly_id` bigint(20) unsigned DEFAULT NULL,
  `stock_adjustment_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_of_id` bigint(20) unsigned DEFAULT NULL,
  `is_reversed` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `idempotency_key` char(36) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `imported_inventory_transactions_reference_no_unique` (`reference_no`),
  UNIQUE KEY `imported_inventory_transactions_reversal_of_id_unique` (`reversal_of_id`),
  UNIQUE KEY `imported_inventory_transactions_idempotency_key_unique` (`idempotency_key`),
  KEY `imported_inventory_transactions_supplier_id_foreign` (`supplier_id`),
  KEY `imported_inventory_transactions_stock_adjustment_id_foreign` (`stock_adjustment_id`),
  KEY `imported_inventory_transactions_created_by_foreign` (`created_by`),
  KEY `iit_part_date_idx` (`spare_part_id`,`transaction_date`),
  KEY `iit_type_date_idx` (`type`,`transaction_date`),
  KEY `iit_assembly_type_idx` (`machine_assembly_id`,`type`),
  KEY `iit_model_type_idx` (`machinery_model_id`,`type`),
  KEY `iit_docref_idx` (`document_reference`),
  CONSTRAINT `imported_inventory_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `imported_inventory_transactions_machine_assembly_id_foreign` FOREIGN KEY (`machine_assembly_id`) REFERENCES `machine_assemblies` (`id`),
  CONSTRAINT `imported_inventory_transactions_machinery_model_id_foreign` FOREIGN KEY (`machinery_model_id`) REFERENCES `machinery_models` (`id`),
  CONSTRAINT `imported_inventory_transactions_reversal_of_id_foreign` FOREIGN KEY (`reversal_of_id`) REFERENCES `imported_inventory_transactions` (`id`),
  CONSTRAINT `imported_inventory_transactions_spare_part_id_foreign` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`),
  CONSTRAINT `imported_inventory_transactions_stock_adjustment_id_foreign` FOREIGN KEY (`stock_adjustment_id`) REFERENCES `stock_adjustments` (`id`),
  CONSTRAINT `imported_inventory_transactions_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `imported_inventory_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `imported_inventory_transactions` ENABLE KEYS */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
DROP TABLE IF EXISTS `machine_assemblies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `machine_assemblies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(40) NOT NULL,
  `name` varchar(191) NOT NULL,
  `machinery_model_id` bigint(20) unsigned DEFAULT NULL,
  `customer` varchar(150) DEFAULT NULL,
  `status` enum('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `start_date` date DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machine_assemblies_reference_no_unique` (`reference_no`),
  KEY `machine_assemblies_machinery_model_id_foreign` (`machinery_model_id`),
  KEY `machine_assemblies_created_by_foreign` (`created_by`),
  KEY `machine_assemblies_updated_by_foreign` (`updated_by`),
  KEY `machine_assemblies_status_index` (`status`),
  CONSTRAINT `machine_assemblies_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `machine_assemblies_machinery_model_id_foreign` FOREIGN KEY (`machinery_model_id`) REFERENCES `machinery_models` (`id`),
  CONSTRAINT `machine_assemblies_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `machine_assemblies` DISABLE KEYS */;
/*!40000 ALTER TABLE `machine_assemblies` ENABLE KEYS */;
DROP TABLE IF EXISTS `machine_assembly_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `machine_assembly_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `machine_assembly_id` bigint(20) unsigned NOT NULL,
  `spare_part_id` bigint(20) unsigned NOT NULL,
  `planned_quantity` decimal(14,3) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machine_assembly_items_machine_assembly_id_spare_part_id_unique` (`machine_assembly_id`,`spare_part_id`),
  KEY `machine_assembly_items_spare_part_id_foreign` (`spare_part_id`),
  CONSTRAINT `machine_assembly_items_machine_assembly_id_foreign` FOREIGN KEY (`machine_assembly_id`) REFERENCES `machine_assemblies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `machine_assembly_items_spare_part_id_foreign` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `machine_assembly_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `machine_assembly_items` ENABLE KEYS */;
DROP TABLE IF EXISTS `machinery_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `machinery_models` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `model_code` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machinery_models_name_unique` (`name`),
  KEY `machinery_models_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `machinery_models` DISABLE KEYS */;
/*!40000 ALTER TABLE `machinery_models` ENABLE KEYS */;
DROP TABLE IF EXISTS `machines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `machines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('active','maintenance','inactive') NOT NULL DEFAULT 'active',
  `description` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machines_code_unique` (`code`),
  KEY `machines_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `machines` DISABLE KEYS */;
INSERT INTO `machines` VALUES
(1,'M-1','M-1','active',NULL,1,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(2,'M-2','M-2','active',NULL,2,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(3,'M-3','M-3','active',NULL,3,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(4,'M-4','M-4','active',NULL,4,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(5,'M-5','M-5','active',NULL,5,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(6,'M-6','M-6','active',NULL,6,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(7,'M-7','M-7','active',NULL,7,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(8,'M-8','M-8','active',NULL,8,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(9,'M-9','M-9','active',NULL,9,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(10,'M-10','M-10','active',NULL,10,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(11,'M-11','M-11','active',NULL,11,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(12,'M-12','M-12','active',NULL,12,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(13,'M-13','M-13','active',NULL,13,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(14,'M-14','M-14','active',NULL,14,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(15,'M-15','M-15','active',NULL,15,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(16,'M-16','M-16','active',NULL,16,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(17,'M-17','M-17','active',NULL,17,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(18,'M-18','M-18','active',NULL,18,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(19,'M-19','M-19','active',NULL,19,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(20,'M-20','M-20','active',NULL,20,'2026-09-30 21:24:10','2026-09-30 21:24:10');
/*!40000 ALTER TABLE `machines` ENABLE KEYS */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_01_01_000100_create_master_data_tables',1),
(5,'2026_01_01_000200_create_spare_parts_tables',1),
(6,'2026_01_01_000300_create_cnc_tables',1),
(7,'2026_01_01_000400_create_imported_tables',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
DROP TABLE IF EXISTS `operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `operations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `sequence` smallint(5) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `operations_name_unique` (`name`),
  UNIQUE KEY `operations_sequence_unique` (`sequence`),
  KEY `operations_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `operations` DISABLE KEYS */;
INSERT INTO `operations` VALUES
(1,'1st Operation',1,NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(2,'2nd Operation',2,NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(3,'3rd Operation',3,NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10');
/*!40000 ALTER TABLE `operations` ENABLE KEYS */;
DROP TABLE IF EXISTS `operators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `operators` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `operators_employee_code_unique` (`employee_code`),
  KEY `operators_name_index` (`name`),
  KEY `operators_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `operators` DISABLE KEYS */;
/*!40000 ALTER TABLE `operators` ENABLE KEYS */;
DROP TABLE IF EXISTS `part_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `part_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `scope` enum('cnc','imported','both') NOT NULL DEFAULT 'both',
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `part_categories_name_scope_unique` (`name`,`scope`),
  KEY `part_categories_scope_index` (`scope`),
  KEY `part_categories_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `part_categories` DISABLE KEYS */;
INSERT INTO `part_categories` VALUES
(1,'Pneumatic','imported',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(2,'Electrical','imported',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(3,'Mechanical','imported',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(4,'Sensors','imported',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(5,'Valves','imported',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(6,'Bearings','imported',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(7,'Shafts','cnc',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(8,'Gears','cnc',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(9,'Cams','cnc',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(10,'Knives & Blades','cnc',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(11,'Bushes','cnc',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(12,'Rollers','cnc',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(13,'Drums','cnc',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(14,'Brackets','cnc',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(15,'Other Machined Parts','cnc',NULL,1,'2026-09-30 21:24:10','2026-09-30 21:24:10');
/*!40000 ALTER TABLE `part_categories` ENABLE KEYS */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  `group` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`),
  KEY `permissions_group_index` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES
(1,'dashboard.overall','View overall dashboard','Dashboard','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(2,'cnc.dashboard','View CNC production dashboard','CNC Production','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(3,'cnc.production.view','View production entries & machine history','CNC Production','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(4,'cnc.production.create','Create / finish production entries','CNC Production','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(5,'cnc.production.edit','Edit production entries','CNC Production','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(6,'cnc.production.cancel','Cancel production entries','CNC Production','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(7,'cnc.completion.create','Submit production completions','CNC Production','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(8,'cnc.completion.approve','Approve / reject / reverse completions (quality)','CNC Production','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(9,'cnc.inventory.view','View CNC stock & ledger','CNC Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(10,'cnc.inventory.issue','Issue CNC finished stock','CNC Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(11,'cnc.inventory.reverse','Reverse CNC stock issues','CNC Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(12,'cnc.parts.manage','Create / edit CNC part master & images','CNC Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(13,'cnc.stock.adjust','CNC stock adjustments','CNC Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(14,'imported.dashboard','View imported inventory dashboard','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(15,'imported.inventory.view','View imported stock, transactions & ledger','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(16,'imported.in.create','Record Inventory IN','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(17,'imported.out.create','Record Inventory OUT','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(18,'imported.transactions.reverse','Reverse IN / OUT transactions','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(19,'imported.products.manage','Create / edit imported product master & images','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(20,'imported.stock.adjust','Imported stock adjustments','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(21,'imported.assembly.view','View machine assemblies','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(22,'imported.assembly.manage','Create / manage assemblies & issue parts','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(23,'imported.purpose.manual','Enter a free-text machine / purpose on OUT','Imported Inventory','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(24,'reports.cnc','CNC reports & exports','Reports','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(25,'reports.imported','Imported inventory reports & exports','Reports','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(26,'settings.machines','Manage CNC machines','Settings','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(27,'settings.operators','Manage machine operators','Settings','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(28,'settings.masters','Manage categories, units, operations & machinery models','Settings','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(29,'settings.suppliers','Manage suppliers','Settings','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(30,'settings.company','Manage company settings','Settings','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(31,'users.manage','Manage users & reset passwords','Administration','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(32,'roles.manage','Manage roles & permissions','Administration','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(33,'audit.view','View activity log','Administration','2026-09-30 21:24:09','2026-09-30 21:24:09'),
(34,'system.health','View system health & error log','Administration','2026-09-30 21:24:09','2026-09-30 21:24:09');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES
(1,1),
(1,2),
(1,3),
(1,4),
(1,5),
(1,6),
(1,7),
(1,8),
(1,9),
(1,10),
(1,11),
(1,12),
(1,13),
(1,14),
(1,15),
(1,16),
(1,17),
(1,18),
(1,19),
(1,20),
(1,21),
(1,22),
(1,23),
(1,24),
(1,25),
(1,26),
(1,27),
(1,28),
(1,29),
(1,30),
(1,31),
(1,32),
(1,33),
(1,34),
(2,1),
(2,2),
(2,3),
(2,4),
(2,5),
(2,6),
(2,7),
(2,9),
(2,10),
(2,12),
(2,24),
(3,1),
(3,14),
(3,15),
(3,16),
(3,17),
(3,19),
(3,21),
(3,22),
(3,25),
(4,1),
(4,2),
(4,3),
(4,4),
(4,5),
(4,6),
(4,7),
(4,9),
(4,10),
(4,12),
(4,14),
(4,15),
(4,16),
(4,17),
(4,19),
(4,21),
(4,22),
(4,24),
(4,25);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES
(1,'super_admin','Super Admin','Full access to all modules, settings, users and audit logs.',1,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(2,'cnc_user','CNC User','CNC production entries, CNC dashboards and CNC inventory.',1,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(3,'import_user','Import Inventory User','Imported inventory IN / OUT, assemblies, imported dashboards and reports.',1,'2026-09-30 21:24:09','2026-09-30 21:24:09'),
(4,'combined_user','Combined User','Both CNC and imported inventory operations.',1,'2026-09-30 21:24:09','2026-09-30 21:24:09');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
DROP TABLE IF EXISTS `spare_part_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `spare_part_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spare_part_id` bigint(20) unsigned NOT NULL,
  `path` varchar(255) NOT NULL,
  `thumb_path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime` varchar(50) NOT NULL,
  `size` int(10) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spare_part_images_uploaded_by_foreign` (`uploaded_by`),
  KEY `spare_part_images_spare_part_id_is_primary_index` (`spare_part_id`,`is_primary`),
  CONSTRAINT `spare_part_images_spare_part_id_foreign` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spare_part_images_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `spare_part_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `spare_part_images` ENABLE KEYS */;
DROP TABLE IF EXISTS `spare_part_machinery_model`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `spare_part_machinery_model` (
  `spare_part_id` bigint(20) unsigned NOT NULL,
  `machinery_model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`spare_part_id`,`machinery_model_id`),
  KEY `spare_part_machinery_model_machinery_model_id_foreign` (`machinery_model_id`),
  CONSTRAINT `spare_part_machinery_model_machinery_model_id_foreign` FOREIGN KEY (`machinery_model_id`) REFERENCES `machinery_models` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spare_part_machinery_model_spare_part_id_foreign` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `spare_part_machinery_model` DISABLE KEYS */;
/*!40000 ALTER TABLE `spare_part_machinery_model` ENABLE KEYS */;
DROP TABLE IF EXISTS `spare_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `spare_parts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_type` enum('cnc','imported') NOT NULL,
  `sku` varchar(60) NOT NULL,
  `name` varchar(191) NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `specification` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `part_number` varchar(100) DEFAULT NULL,
  `supplier_id` bigint(20) unsigned DEFAULT NULL,
  `final_operation_id` bigint(20) unsigned DEFAULT NULL,
  `min_stock` decimal(14,3) NOT NULL DEFAULT 0.000,
  `opening_stock` decimal(14,3) NOT NULL DEFAULT 0.000,
  `current_stock` decimal(14,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(14,2) DEFAULT NULL,
  `currency` char(3) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `spare_parts_sku_unique` (`sku`),
  KEY `spare_parts_category_id_foreign` (`category_id`),
  KEY `spare_parts_unit_id_foreign` (`unit_id`),
  KEY `spare_parts_supplier_id_foreign` (`supplier_id`),
  KEY `spare_parts_final_operation_id_foreign` (`final_operation_id`),
  KEY `spare_parts_created_by_foreign` (`created_by`),
  KEY `spare_parts_updated_by_foreign` (`updated_by`),
  KEY `spare_parts_inventory_type_is_active_index` (`inventory_type`,`is_active`),
  KEY `spare_parts_name_index` (`name`),
  KEY `spare_parts_part_number_index` (`part_number`),
  CONSTRAINT `spare_parts_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `part_categories` (`id`),
  CONSTRAINT `spare_parts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spare_parts_final_operation_id_foreign` FOREIGN KEY (`final_operation_id`) REFERENCES `operations` (`id`),
  CONSTRAINT `spare_parts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `spare_parts_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `spare_parts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `spare_parts` DISABLE KEYS */;
/*!40000 ALTER TABLE `spare_parts` ENABLE KEYS */;
DROP TABLE IF EXISTS `stock_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(40) NOT NULL,
  `inventory_type` enum('cnc','imported') NOT NULL,
  `spare_part_id` bigint(20) unsigned NOT NULL,
  `adjustment_date` date NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_adjustments_reference_no_unique` (`reference_no`),
  KEY `stock_adjustments_spare_part_id_foreign` (`spare_part_id`),
  KEY `stock_adjustments_created_by_foreign` (`created_by`),
  KEY `stock_adjustments_inventory_type_adjustment_date_index` (`inventory_type`,`adjustment_date`),
  CONSTRAINT `stock_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_adjustments_spare_part_id_foreign` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `stock_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_adjustments` ENABLE KEYS */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `country` varchar(80) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_name_unique` (`name`),
  KEY `suppliers_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(20) NOT NULL,
  `allows_decimal` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_name_unique` (`name`),
  UNIQUE KEY `units_symbol_unique` (`symbol`),
  KEY `units_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` VALUES
(1,'Piece','pcs',0,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(2,'Set','set',0,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(3,'Box','box',0,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(4,'Pair','pair',0,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(5,'Meter','m',1,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(6,'Kilogram','kg',1,1,'2026-09-30 21:24:10','2026-09-30 21:24:10'),
(7,'Roll','roll',0,1,'2026-09-30 21:24:10','2026-09-30 21:24:10');
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `user_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`permission_id`),
  KEY `user_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `user_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_is_active_index` (`is_active`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

