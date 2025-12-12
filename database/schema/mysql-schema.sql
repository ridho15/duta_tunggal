/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `account_payables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_payables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `total` decimal(18,2) NOT NULL,
  `paid` decimal(18,2) NOT NULL DEFAULT '0.00',
  `remaining` decimal(18,2) NOT NULL,
  `status` enum('Lunas','Belum Lunas') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Belum Lunas',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_payables_created_by_foreign` (`created_by`),
  CONSTRAINT `account_payables_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_receivables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_receivables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `total` decimal(18,2) NOT NULL,
  `paid` decimal(18,2) NOT NULL DEFAULT '0.00',
  `remaining` decimal(18,2) NOT NULL,
  `status` enum('Lunas','Belum Lunas') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Belum Lunas',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_receivables_created_by_foreign` (`created_by`),
  KEY `account_receivables_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `account_receivables_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_receivables_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ageing_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ageing_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `days_outstanding` int NOT NULL,
  `bucket` enum('Current','31–60','61–90','>90') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `from_model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ageing_schedules_from_model_type_from_model_id_index` (`from_model_type`,`from_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_depreciations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_depreciations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `depreciation_date` date NOT NULL,
  `period_month` int NOT NULL,
  `period_year` int NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `accumulated_total` decimal(20,2) NOT NULL,
  `book_value` decimal(20,2) NOT NULL,
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'recorded',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_depreciations_asset_id_foreign` (`asset_id`),
  KEY `asset_depreciations_journal_entry_id_foreign` (`journal_entry_id`),
  CONSTRAINT `asset_depreciations_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_depreciations_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_disposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_disposals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `disposal_date` date NOT NULL,
  `disposal_type` enum('sale','scrap','donation','theft','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_price` decimal(15,2) DEFAULT NULL,
  `book_value_at_disposal` decimal(15,2) NOT NULL,
  `gain_loss_amount` decimal(15,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `disposal_document` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_disposals_approved_by_foreign` (`approved_by`),
  KEY `asset_disposals_asset_id_status_index` (`asset_id`,`status`),
  KEY `asset_disposals_disposal_date_index` (`disposal_date`),
  CONSTRAINT `asset_disposals_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `asset_disposals_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `from_cabang_id` bigint unsigned NOT NULL,
  `to_cabang_id` bigint unsigned NOT NULL,
  `transfer_date` date NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `transfer_document` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_transfers_to_cabang_id_foreign` (`to_cabang_id`),
  KEY `asset_transfers_requested_by_foreign` (`requested_by`),
  KEY `asset_transfers_approved_by_foreign` (`approved_by`),
  KEY `asset_transfers_completed_by_foreign` (`completed_by`),
  KEY `asset_transfers_asset_id_status_index` (`asset_id`,`status`),
  KEY `asset_transfers_from_cabang_id_to_cabang_id_index` (`from_cabang_id`,`to_cabang_id`),
  KEY `asset_transfers_transfer_date_index` (`transfer_date`),
  CONSTRAINT `asset_transfers_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `asset_transfers_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_transfers_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `asset_transfers_from_cabang_id_foreign` FOREIGN KEY (`from_cabang_id`) REFERENCES `cabangs` (`id`),
  CONSTRAINT `asset_transfers_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `asset_transfers_to_cabang_id_foreign` FOREIGN KEY (`to_cabang_id`) REFERENCES `cabangs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_date` date NOT NULL,
  `usage_date` date NOT NULL,
  `purchase_cost` decimal(20,2) NOT NULL,
  `salvage_value` decimal(20,2) NOT NULL DEFAULT '0.00',
  `useful_life_years` int NOT NULL,
  `depreciation_method` enum('straight_line','declining_balance','sum_of_years_digits','units_of_production') COLLATE utf8mb4_unicode_ci DEFAULT 'straight_line',
  `asset_coa_id` bigint unsigned NOT NULL,
  `accumulated_depreciation_coa_id` bigint unsigned NOT NULL,
  `depreciation_expense_coa_id` bigint unsigned NOT NULL,
  `annual_depreciation` decimal(20,2) NOT NULL DEFAULT '0.00',
  `monthly_depreciation` decimal(20,2) NOT NULL DEFAULT '0.00',
  `accumulated_depreciation` decimal(20,2) NOT NULL DEFAULT '0.00',
  `book_value` decimal(20,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `purchase_order_item_id` bigint unsigned DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assets_code_unique` (`code`),
  KEY `assets_asset_coa_id_foreign` (`asset_coa_id`),
  KEY `assets_accumulated_depreciation_coa_id_foreign` (`accumulated_depreciation_coa_id`),
  KEY `assets_depreciation_expense_coa_id_foreign` (`depreciation_expense_coa_id`),
  KEY `assets_product_id_foreign` (`product_id`),
  KEY `assets_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `assets_purchase_order_item_id_foreign` (`purchase_order_item_id`),
  KEY `assets_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `assets_accumulated_depreciation_coa_id_foreign` FOREIGN KEY (`accumulated_depreciation_coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `assets_asset_coa_id_foreign` FOREIGN KEY (`asset_coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `assets_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assets_depreciation_expense_coa_id_foreign` FOREIGN KEY (`depreciation_expense_coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `assets_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `assets_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `assets_purchase_order_item_id_foreign` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coa_id` bigint unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `statement_ending_balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `book_balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `difference` decimal(18,2) NOT NULL DEFAULT '0.00',
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('open','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_reconciliations_coa_id_foreign` (`coa_id`),
  CONSTRAINT `bank_reconciliations_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bill_of_material_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bill_of_material_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bill_of_material_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Harga per Satuan',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Subtotal (unit_price * quantity)',
  `uom_id` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bill_of_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bill_of_materials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cabang_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT '0.00',
  `labor_cost` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Biaya Tenaga Kerja Langsung',
  `overhead_cost` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Biaya Overhead Pabrik',
  `total_cost` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Total Biaya Produksi',
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_bom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama BOM',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `uom_id` int NOT NULL,
  `finished_goods_coa_id` bigint unsigned DEFAULT NULL,
  `work_in_progress_coa_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bill_of_materials_finished_goods_coa_id_foreign` (`finished_goods_coa_id`),
  KEY `bill_of_materials_work_in_progress_coa_id_foreign` (`work_in_progress_coa_id`),
  CONSTRAINT `bill_of_materials_finished_goods_coa_id_foreign` FOREIGN KEY (`finished_goods_coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `bill_of_materials_work_in_progress_coa_id_foreign` FOREIGN KEY (`work_in_progress_coa_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cabangs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cabangs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telepon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kenaikan_harga` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `warna_background` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipe_penjualan` enum('Semua','Pajak','Non Pajak') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Semua',
  `kode_invoice_pajak` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kode_invoice_non_pajak` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kode_invoice_pajak_walkin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_kwitansi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_invoice_pajak` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_invoice_non_pajak` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_invoice_non_pajak` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lihat_stok_cabang_lain` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cabangs_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_bank_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coa_id` bigint unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_bank_accounts_coa_id_index` (`coa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_bank_transaction_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_bank_transaction_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cash_bank_transaction_id` bigint unsigned NOT NULL,
  `chart_of_account_id` bigint unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ntpn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_bank_transaction_details_cash_bank_transaction_id_foreign` (`cash_bank_transaction_id`),
  KEY `cash_bank_transaction_details_chart_of_account_id_foreign` (`chart_of_account_id`),
  CONSTRAINT `cash_bank_transaction_details_cash_bank_transaction_id_foreign` FOREIGN KEY (`cash_bank_transaction_id`) REFERENCES `cash_bank_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_bank_transaction_details_chart_of_account_id_foreign` FOREIGN KEY (`chart_of_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_bank_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_bank_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `type` enum('cash_in','cash_out','bank_in','bank_out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cash_bank_account_id` bigint unsigned DEFAULT NULL,
  `account_coa_id` bigint unsigned NOT NULL,
  `offset_coa_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `counterparty` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attachment_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `voucher_request_id` bigint unsigned DEFAULT NULL,
  `voucher_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voucher_usage_type` enum('single_use','multi_use') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voucher_amount_used` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_bank_transactions_number_unique` (`number`),
  KEY `cash_bank_transactions_account_coa_id_foreign` (`account_coa_id`),
  KEY `cash_bank_transactions_offset_coa_id_foreign` (`offset_coa_id`),
  KEY `cash_bank_transactions_cash_bank_account_id_index` (`cash_bank_account_id`),
  KEY `cbt_voucher_idx` (`voucher_request_id`,`voucher_usage_type`),
  CONSTRAINT `cash_bank_transactions_account_coa_id_foreign` FOREIGN KEY (`account_coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `cash_bank_transactions_offset_coa_id_foreign` FOREIGN KEY (`offset_coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `cash_bank_transactions_voucher_request_id_foreign` FOREIGN KEY (`voucher_request_id`) REFERENCES `voucher_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_bank_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_bank_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `from_coa_id` bigint unsigned NOT NULL,
  `to_coa_id` bigint unsigned NOT NULL,
  `clearing_coa_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `other_costs` decimal(18,2) NOT NULL DEFAULT '0.00',
  `other_costs_coa_id` bigint unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','posted','reconciled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_bank_transfers_number_unique` (`number`),
  KEY `cash_bank_transfers_from_coa_id_foreign` (`from_coa_id`),
  KEY `cash_bank_transfers_to_coa_id_foreign` (`to_coa_id`),
  KEY `cash_bank_transfers_clearing_coa_id_foreign` (`clearing_coa_id`),
  KEY `cash_bank_transfers_other_costs_coa_id_index` (`other_costs_coa_id`),
  KEY `cash_bank_transfers_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `cash_bank_transfers_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_bank_transfers_clearing_coa_id_foreign` FOREIGN KEY (`clearing_coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `cash_bank_transfers_from_coa_id_foreign` FOREIGN KEY (`from_coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `cash_bank_transfers_other_costs_coa_id_foreign` FOREIGN KEY (`other_costs_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_bank_transfers_to_coa_id_foreign` FOREIGN KEY (`to_coa_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chart_of_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('Asset','Liability','Equity','Revenue','Expense','Contra Asset') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `ending_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_of_accounts_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_rupiah` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_receipt_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_receipt_id` int NOT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `coa_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `payment_date` date NOT NULL DEFAULT '2025-07-01',
  `selected_invoices` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int DEFAULT NULL,
  `customer_id` int NOT NULL,
  `selected_invoices` json DEFAULT NULL,
  `invoice_receipts` json DEFAULT NULL,
  `payment_date` date NOT NULL,
  `ntpn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_payment` decimal(18,2) NOT NULL,
  `coa_id` bigint unsigned DEFAULT NULL,
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('Draft','Partial','Paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `diskon` decimal(18,2) NOT NULL DEFAULT '0.00',
  `payment_adjustment` decimal(18,2) NOT NULL DEFAULT '0.00',
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_receipts_coa_id_foreign` (`coa_id`),
  KEY `customer_receipts_created_by_foreign` (`created_by`),
  KEY `customer_receipts_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `customer_receipts_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_receipts_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `perusahaan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe` enum('PKP','PRI') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fax` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `isSpecial` tinyint(1) NOT NULL DEFAULT '0',
  `tempo_kredit` int NOT NULL DEFAULT '0' COMMENT 'Hitungan Hari',
  `kredit_limit` bigint NOT NULL DEFAULT '0',
  `tipe_pembayaran` enum('Bebas','COD (Bayar Lunas)','Kredit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Bebas',
  `nik_npwp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'NIK / NPWP',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cabang_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_code_unique` (`code`),
  KEY `customers_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `customers_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_order_approval_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_order_approval_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` enum('approved','rejected','pending','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_order_approval_logs_delivery_order_id_foreign` (`delivery_order_id`),
  KEY `delivery_order_approval_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `delivery_order_approval_logs_delivery_order_id_foreign` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delivery_order_approval_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` int NOT NULL,
  `purchase_receipt_item_id` int DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `sale_order_item_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_order_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_order_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` int NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmed_by` int NOT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `old_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_date` datetime NOT NULL,
  `driver_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `status` enum('draft','sent','confirmed','received','supplier','completed','request_approve','approved','request_close','closed','reject') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `additional_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `additional_cost_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `do_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_orders_warehouse_id_foreign` (`warehouse_id`),
  KEY `delivery_orders_created_by_foreign` (`created_by`),
  KEY `delivery_orders_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `delivery_orders_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delivery_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `delivery_orders_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_sales_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` int NOT NULL,
  `sales_order_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deposit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deposit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `deposit_id` int NOT NULL,
  `type` enum('create','use','add','return','cancel','edit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deposit_logs_reference_type_reference_id_index` (`reference_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deposits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deposits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `from_model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_model_id` bigint unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `used_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `remaining_amount` decimal(15,2) NOT NULL,
  `coa_id` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deposit_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_coa_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deposits_deposit_number_unique` (`deposit_number`),
  KEY `deposits_from_model_type_from_model_id_index` (`from_model_type`,`from_model_id`),
  KEY `deposits_payment_coa_id_foreign` (`payment_coa_id`),
  CONSTRAINT `deposits_payment_coa_id_foreign` FOREIGN KEY (`payment_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `drivers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `drivers_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `drivers_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `income_statement_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `income_statement_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_stocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `qty_available` double NOT NULL DEFAULT '0',
  `qty_reserved` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `rak_id` int DEFAULT NULL,
  `qty_min` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_stocks_product_id_warehouse_id_rak_id_unique` (`product_id`,`warehouse_id`,`rak_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_model_id` bigint unsigned NOT NULL,
  `invoice_date` date NOT NULL,
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tax` int NOT NULL DEFAULT '0',
  `other_fee` json DEFAULT NULL,
  `total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','sent','paid','partially_paid','overdue','unpaid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `due_date` date NOT NULL,
  `ppn_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `dpp` decimal(18,2) NOT NULL DEFAULT '0.00',
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_orders` json DEFAULT NULL,
  `purchase_receipts` json DEFAULT NULL,
  `accounts_payable_coa_id` bigint unsigned DEFAULT NULL,
  `ppn_masukan_coa_id` bigint unsigned DEFAULT NULL,
  `inventory_coa_id` bigint unsigned DEFAULT NULL,
  `expense_coa_id` bigint unsigned DEFAULT NULL,
  `revenue_coa_id` bigint unsigned DEFAULT NULL,
  `ar_coa_id` bigint unsigned DEFAULT NULL,
  `ppn_keluaran_coa_id` bigint unsigned DEFAULT NULL,
  `biaya_pengiriman_coa_id` bigint unsigned DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoices_from_model_type_from_model_id_index` (`from_model_type`,`from_model_id`),
  KEY `invoices_accounts_payable_coa_id_foreign` (`accounts_payable_coa_id`),
  KEY `invoices_ppn_masukan_coa_id_foreign` (`ppn_masukan_coa_id`),
  KEY `invoices_inventory_coa_id_foreign` (`inventory_coa_id`),
  KEY `invoices_expense_coa_id_foreign` (`expense_coa_id`),
  KEY `invoices_revenue_coa_id_foreign` (`revenue_coa_id`),
  KEY `invoices_ar_coa_id_foreign` (`ar_coa_id`),
  KEY `invoices_ppn_keluaran_coa_id_foreign` (`ppn_keluaran_coa_id`),
  KEY `invoices_biaya_pengiriman_coa_id_foreign` (`biaya_pengiriman_coa_id`),
  KEY `invoices_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `invoices_accounts_payable_coa_id_foreign` FOREIGN KEY (`accounts_payable_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ar_coa_id_foreign` FOREIGN KEY (`ar_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_biaya_pengiriman_coa_id_foreign` FOREIGN KEY (`biaya_pengiriman_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_expense_coa_id_foreign` FOREIGN KEY (`expense_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_inventory_coa_id_foreign` FOREIGN KEY (`inventory_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ppn_keluaran_coa_id_foreign` FOREIGN KEY (`ppn_keluaran_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ppn_masukan_coa_id_foreign` FOREIGN KEY (`ppn_masukan_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_revenue_coa_id_foreign` FOREIGN KEY (`revenue_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coa_id` int NOT NULL,
  `date` date NOT NULL,
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `debit` decimal(20,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(20,2) NOT NULL DEFAULT '0.00',
  `journal_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `transaction_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_recon_id` bigint unsigned DEFAULT NULL,
  `bank_recon_status` enum('matched','cleared','confirmed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_recon_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entries_source_type_source_id_index` (`source_type`,`source_id`),
  KEY `journal_entries_cabang_id_index` (`cabang_id`),
  KEY `journal_entries_department_id_index` (`department_id`),
  KEY `journal_entries_project_id_index` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manufacturing_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `manufacturing_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `production_plan_id` bigint unsigned DEFAULT NULL,
  `mo_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','in_progress','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `items` json DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `manufacturing_orders_production_plan_id_foreign` (`production_plan_id`),
  KEY `manufacturing_orders_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `manufacturing_orders_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `manufacturing_orders_production_plan_id_foreign` FOREIGN KEY (`production_plan_id`) REFERENCES `production_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material_issue_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_issue_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `material_issue_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `uom_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `rak_id` bigint unsigned DEFAULT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `cost_per_unit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','pending_approval','approved','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_issue_items_material_issue_id_foreign` (`material_issue_id`),
  KEY `material_issue_items_product_id_foreign` (`product_id`),
  KEY `material_issue_items_uom_id_foreign` (`uom_id`),
  KEY `material_issue_items_warehouse_id_foreign` (`warehouse_id`),
  KEY `material_issue_items_rak_id_foreign` (`rak_id`),
  KEY `material_issue_items_approved_by_foreign` (`approved_by`),
  CONSTRAINT `material_issue_items_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_issue_items_material_issue_id_foreign` FOREIGN KEY (`material_issue_id`) REFERENCES `material_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_issue_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_issue_items_rak_id_foreign` FOREIGN KEY (`rak_id`) REFERENCES `raks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_issue_items_uom_id_foreign` FOREIGN KEY (`uom_id`) REFERENCES `unit_of_measures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_issue_items_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_issues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `production_plan_id` bigint unsigned DEFAULT NULL,
  `issue_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `manufacturing_order_id` bigint unsigned DEFAULT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `issue_date` date NOT NULL,
  `type` enum('issue','return') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issue' COMMENT 'issue = Ambil Barang, return = Retur Barang',
  `status` enum('draft','pending_approval','approved','rejected','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `total_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_issues_issue_number_unique` (`issue_number`),
  KEY `material_issues_manufacturing_order_id_foreign` (`manufacturing_order_id`),
  KEY `material_issues_warehouse_id_foreign` (`warehouse_id`),
  KEY `material_issues_created_by_foreign` (`created_by`),
  KEY `material_issues_approved_by_foreign` (`approved_by`),
  KEY `material_issues_production_plan_id_foreign` (`production_plan_id`),
  CONSTRAINT `material_issues_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_issues_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_issues_manufacturing_order_id_foreign` FOREIGN KEY (`manufacturing_order_id`) REFERENCES `manufacturing_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_issues_production_plan_id_foreign` FOREIGN KEY (`production_plan_id`) REFERENCES `production_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_issues_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_request_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_request_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` int NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  `request_date` date NOT NULL,
  `status` enum('draft','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_requests_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `order_requests_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `other_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `other_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_date` date NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `coa_id` bigint unsigned NOT NULL,
  `cash_bank_account_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `cabang_id` bigint unsigned NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `other_sales_reference_number_unique` (`reference_number`),
  KEY `other_sales_coa_id_foreign` (`coa_id`),
  KEY `other_sales_cash_bank_account_id_foreign` (`cash_bank_account_id`),
  KEY `other_sales_customer_id_foreign` (`customer_id`),
  KEY `other_sales_cabang_id_foreign` (`cabang_id`),
  KEY `other_sales_created_by_foreign` (`created_by`),
  CONSTRAINT `other_sales_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`),
  CONSTRAINT `other_sales_cash_bank_account_id_foreign` FOREIGN KEY (`cash_bank_account_id`) REFERENCES `cash_bank_accounts` (`id`),
  CONSTRAINT `other_sales_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `other_sales_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `other_sales_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `kode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cabang_id` int NOT NULL,
  `kenaikan_harga` decimal(5,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_unit_conversions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_unit_conversions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `uom_id` int NOT NULL,
  `nilai_konversi` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plan_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` enum('sale_order','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_order_id` bigint unsigned DEFAULT NULL,
  `bill_of_material_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `uom_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('draft','scheduled','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `production_plans_plan_number_unique` (`plan_number`),
  KEY `production_plans_sale_order_id_foreign` (`sale_order_id`),
  KEY `production_plans_bill_of_material_id_foreign` (`bill_of_material_id`),
  KEY `production_plans_product_id_foreign` (`product_id`),
  KEY `production_plans_uom_id_foreign` (`uom_id`),
  KEY `production_plans_created_by_foreign` (`created_by`),
  KEY `production_plans_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `production_plans_bill_of_material_id_foreign` FOREIGN KEY (`bill_of_material_id`) REFERENCES `bill_of_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_plans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_plans_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_plans_sale_order_id_foreign` FOREIGN KEY (`sale_order_id`) REFERENCES `sale_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_plans_uom_id_foreign` FOREIGN KEY (`uom_id`) REFERENCES `unit_of_measures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_plans_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `productions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `production_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_produced` decimal(15,2) DEFAULT NULL,
  `manufacturing_order_id` int NOT NULL,
  `production_date` date NOT NULL,
  `status` enum('draft','finished') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_category_id` int NOT NULL,
  `cost_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `sell_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `uom_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cabang_id` int NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `harga_batas` int NOT NULL DEFAULT '0' COMMENT '%',
  `item_value` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tipe_pajak` enum('Non Pajak','Inklusif','Eksklusif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Non Pajak',
  `pajak` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '%',
  `jumlah_kelipatan_gudang_besar` int NOT NULL DEFAULT '0',
  `jumlah_jual_kategori_banyak` int NOT NULL DEFAULT '0',
  `kode_merk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `biaya` decimal(18,2) NOT NULL DEFAULT '0.00',
  `is_manufacture` tinyint(1) NOT NULL DEFAULT '0',
  `is_raw_material` tinyint(1) NOT NULL DEFAULT '0',
  `inventory_coa_id` bigint unsigned DEFAULT NULL,
  `sales_coa_id` bigint unsigned DEFAULT NULL,
  `sales_return_coa_id` bigint unsigned DEFAULT NULL,
  `sales_discount_coa_id` bigint unsigned DEFAULT NULL,
  `goods_delivery_coa_id` bigint unsigned DEFAULT NULL,
  `cogs_coa_id` bigint unsigned DEFAULT NULL,
  `purchase_return_coa_id` bigint unsigned DEFAULT NULL,
  `unbilled_purchase_coa_id` bigint unsigned DEFAULT NULL,
  `temporary_procurement_coa_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  KEY `products_supplier_id_foreign` (`supplier_id`),
  KEY `products_inventory_coa_id_foreign` (`inventory_coa_id`),
  KEY `products_sales_coa_id_foreign` (`sales_coa_id`),
  KEY `products_sales_return_coa_id_foreign` (`sales_return_coa_id`),
  KEY `products_sales_discount_coa_id_foreign` (`sales_discount_coa_id`),
  KEY `products_goods_delivery_coa_id_foreign` (`goods_delivery_coa_id`),
  KEY `products_cogs_coa_id_foreign` (`cogs_coa_id`),
  KEY `products_purchase_return_coa_id_foreign` (`purchase_return_coa_id`),
  KEY `products_unbilled_purchase_coa_id_foreign` (`unbilled_purchase_coa_id`),
  KEY `products_temporary_procurement_coa_id_foreign` (`temporary_procurement_coa_id`),
  CONSTRAINT `products_cogs_coa_id_foreign` FOREIGN KEY (`cogs_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_goods_delivery_coa_id_foreign` FOREIGN KEY (`goods_delivery_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_inventory_coa_id_foreign` FOREIGN KEY (`inventory_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_purchase_return_coa_id_foreign` FOREIGN KEY (`purchase_return_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_sales_coa_id_foreign` FOREIGN KEY (`sales_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_sales_discount_coa_id_foreign` FOREIGN KEY (`sales_discount_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_sales_return_coa_id_foreign` FOREIGN KEY (`sales_return_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_temporary_procurement_coa_id_foreign` FOREIGN KEY (`temporary_procurement_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_unbilled_purchase_coa_id_foreign` FOREIGN KEY (`unbilled_purchase_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_biayas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_biayas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int NOT NULL,
  `nama_biaya` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_id` int NOT NULL,
  `total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `untuk_pembelian` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Untuk pembelian',
  `masuk_invoice` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `coa_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_biayas_coa_id_foreign` (`coa_id`),
  CONSTRAINT `purchase_order_biayas_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int NOT NULL,
  `currency_id` int NOT NULL,
  `nominal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `refer_item_model_id` int DEFAULT NULL,
  `refer_item_model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipe_pajak` enum('Non Pajak','Inklusif','Eklusif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Non Pajak',
  `currency_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `refer_item_index` (`refer_item_model_type`,`refer_item_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `po_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_date` datetime NOT NULL,
  `status` enum('draft','approved','partially_received','completed','invoiced','paid','closed','request_close','request_approval') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_date` datetime DEFAULT NULL,
  `total_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_asset` tinyint(1) NOT NULL DEFAULT '0',
  `is_import` tinyint(1) NOT NULL DEFAULT '0',
  `ppn_option` enum('standard','non_ppn') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `close_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_approved` datetime DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approval_signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approval_signed_at` timestamp NULL DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `close_requested_by` int DEFAULT NULL COMMENT 'User yang melakukan request close',
  `close_requested_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `refer_model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refer_model_id` bigint unsigned DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `tempo_hutang` int NOT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_orders_refer_model_type_refer_model_id_index` (`refer_model_type`,`refer_model_id`),
  KEY `purchase_orders_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `purchase_orders_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_receipt_biayas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_receipt_biayas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_receipt_id` bigint unsigned NOT NULL,
  `nama_biaya` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_id` bigint unsigned NOT NULL,
  `coa_id` bigint unsigned DEFAULT NULL,
  `total` bigint NOT NULL DEFAULT '0',
  `untuk_pembelian` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Untuk pembelian',
  `masuk_invoice` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `purchase_order_biaya_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_receipt_biayas_purchase_receipt_id_foreign` (`purchase_receipt_id`),
  KEY `purchase_receipt_biayas_currency_id_foreign` (`currency_id`),
  KEY `purchase_receipt_biayas_coa_id_foreign` (`coa_id`),
  KEY `purchase_receipt_biayas_purchase_order_biaya_id_foreign` (`purchase_order_biaya_id`),
  CONSTRAINT `purchase_receipt_biayas_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_receipt_biayas_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_receipt_biayas_purchase_order_biaya_id_foreign` FOREIGN KEY (`purchase_order_biaya_id`) REFERENCES `purchase_order_biayas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_receipt_biayas_purchase_receipt_id_foreign` FOREIGN KEY (`purchase_receipt_id`) REFERENCES `purchase_receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_receipt_item_nominals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_receipt_item_nominals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_receipt_item_id` int NOT NULL,
  `currency_id` int NOT NULL,
  `nominal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_receipt_item_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_receipt_item_photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_receipt_item_id` int NOT NULL,
  `photo_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_receipt_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_receipt_id` int NOT NULL,
  `product_id` int NOT NULL,
  `qty_received` decimal(10,2) NOT NULL DEFAULT '0.00',
  `qty_accepted` decimal(10,2) NOT NULL DEFAULT '0.00',
  `qty_rejected` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reason_rejected` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `is_sent` tinyint(1) NOT NULL DEFAULT '0',
  `purchase_order_item_id` int DEFAULT NULL,
  `rak_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_receipt_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_receipt_photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_receipt_id` int NOT NULL,
  `photo_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int NOT NULL,
  `receipt_date` datetime NOT NULL,
  `received_by` int NOT NULL COMMENT 'orang yang menerima / user yang menerima',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `receipt_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_id` int NOT NULL,
  `other_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','partial','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_receipts_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `purchase_receipts_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_return_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_return_id` int NOT NULL,
  `product_id` int NOT NULL,
  `qty_returned` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `purchase_receipt_item_id` int NOT NULL,
  `unit_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_receipt_id` int NOT NULL,
  `return_date` datetime NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','pending_approval','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_notes` text COLLATE utf8mb4_unicode_ci,
  `rejected_by` bigint unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejection_notes` text COLLATE utf8mb4_unicode_ci,
  `credit_note_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_note_date` date DEFAULT NULL,
  `credit_note_amount` decimal(15,2) DEFAULT NULL,
  `refund_amount` decimal(15,2) DEFAULT NULL,
  `refund_date` date DEFAULT NULL,
  `refund_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `nota_retur` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  `replacement_po_id` bigint unsigned DEFAULT NULL,
  `replacement_date` date DEFAULT NULL,
  `replacement_notes` text COLLATE utf8mb4_unicode_ci,
  `supplier_response` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_note_received` tinyint(1) NOT NULL DEFAULT '0',
  `case_closed_date` date DEFAULT NULL,
  `tracking_notes` text COLLATE utf8mb4_unicode_ci,
  `delivery_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_details` text COLLATE utf8mb4_unicode_ci,
  `physical_return_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_returns_approved_by_foreign` (`approved_by`),
  KEY `purchase_returns_rejected_by_foreign` (`rejected_by`),
  KEY `purchase_returns_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `purchase_returns_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `purchase_returns_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_returns_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quality_controls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quality_controls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` int NOT NULL,
  `inspected_by` int DEFAULT NULL COMMENT 'user quality control',
  `passed_quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `rejected_quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reason_reject` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `product_id` int NOT NULL,
  `date_send_stock` datetime DEFAULT NULL,
  `rak_id` int DEFAULT NULL,
  `from_model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_model_id` bigint unsigned DEFAULT NULL,
  `qc_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_return_processed` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quality_controls_from_model_type_from_model_id_index` (`from_model_type`,`from_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int NOT NULL,
  `product_id` int NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `quantity` int NOT NULL DEFAULT '0',
  `unit_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `discount` int NOT NULL DEFAULT '0',
  `tax` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int NOT NULL,
  `date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `total_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status_payment` enum('Sudah Bayar','Belum Bayar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Belum Bayar',
  `po_file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','request_approve','approve','reject') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int DEFAULT NULL,
  `request_approve_by` int DEFAULT NULL,
  `request_approve_at` datetime DEFAULT NULL,
  `reject_by` int DEFAULT NULL,
  `reject_at` datetime DEFAULT NULL,
  `approve_by` int DEFAULT NULL,
  `approve_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `raks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `raks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_cash_flow_cash_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_cash_flow_cash_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prefix` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_cash_flow_cash_accounts_prefix_unique` (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_cash_flow_item_prefixes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_cash_flow_item_prefixes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `prefix` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_asset` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_cash_flow_item_prefixes_item_id_is_asset_index` (`item_id`,`is_asset`),
  CONSTRAINT `report_cash_flow_item_prefixes_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `report_cash_flow_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_cash_flow_item_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_cash_flow_item_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_cash_flow_item_sources_item_id_foreign` (`item_id`),
  CONSTRAINT `report_cash_flow_item_sources_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `report_cash_flow_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_cash_flow_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_cash_flow_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_id` bigint unsigned NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('inflow','outflow','net') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outflow',
  `resolver` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `include_assets` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_cash_flow_items_key_unique` (`key`),
  KEY `report_cash_flow_items_section_id_foreign` (`section_id`),
  CONSTRAINT `report_cash_flow_items_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `report_cash_flow_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_cash_flow_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_cash_flow_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_cash_flow_sections_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_hpp_overhead_item_prefixes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_hpp_overhead_item_prefixes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `overhead_item_id` bigint unsigned NOT NULL,
  `prefix` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_hpp_overhead_item_prefixes_overhead_item_id_foreign` (`overhead_item_id`),
  CONSTRAINT `report_hpp_overhead_item_prefixes_overhead_item_id_foreign` FOREIGN KEY (`overhead_item_id`) REFERENCES `report_hpp_overhead_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_hpp_overhead_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_hpp_overhead_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_hpp_overhead_items_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_hpp_prefixes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_hpp_prefixes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_hpp_prefixes_category_sort_order_index` (`category`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `return_product_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_product_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `return_product_id` int NOT NULL,
  `from_item_model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_item_model_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `rak_id` int DEFAULT NULL,
  `condition` enum('good','damage','repair') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `from_item_index` (`from_item_model_type`,`from_item_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `return_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `return_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_model_id` bigint unsigned NOT NULL,
  `warehouse_id` int NOT NULL,
  `status` enum('draft','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `return_action` enum('reduce_quantity_only','close_do_partial','close_so_complete') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reduce_quantity_only',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `return_products_from_model_type_from_model_id_index` (`from_model_type`,`from_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` double NOT NULL DEFAULT '0',
  `delivered_quantity` decimal(15,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `discount` int NOT NULL DEFAULT '0',
  `tax` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `rak_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `quotation_id` bigint unsigned DEFAULT NULL,
  `so_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_date` datetime NOT NULL,
  `status` enum('draft','request_approve','request_close','approved','closed','completed','partial_confirmed','confirmed','received','canceled','reject') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `delivery_date` datetime DEFAULT NULL,
  `total_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `request_approve_by` int DEFAULT NULL,
  `request_approve_at` datetime DEFAULT NULL,
  `request_close_by` int DEFAULT NULL,
  `request_close_at` datetime DEFAULT NULL,
  `approve_by` int DEFAULT NULL,
  `approve_at` datetime DEFAULT NULL,
  `close_by` int DEFAULT NULL,
  `close_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `shipped_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reject_by` int DEFAULT NULL,
  `reject_at` datetime DEFAULT NULL,
  `reason_close` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipe_pengiriman` enum('Ambil Sendiri','Kirim Langsung') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_confirmed_at` datetime DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_orders_quotation_id_foreign` (`quotation_id`),
  KEY `sale_orders_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `sale_orders_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_orders_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_adjustment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_adjustment_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_adjustment_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `rak_id` bigint unsigned DEFAULT NULL,
  `current_qty` decimal(15,2) NOT NULL DEFAULT '0.00',
  `adjusted_qty` decimal(15,2) NOT NULL DEFAULT '0.00',
  `difference_qty` decimal(15,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `difference_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_adjustment_items_stock_adjustment_id_foreign` (`stock_adjustment_id`),
  KEY `stock_adjustment_items_product_id_foreign` (`product_id`),
  KEY `stock_adjustment_items_rak_id_foreign` (`rak_id`),
  CONSTRAINT `stock_adjustment_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_adjustment_items_rak_id_foreign` FOREIGN KEY (`rak_id`) REFERENCES `raks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_adjustment_items_stock_adjustment_id_foreign` FOREIGN KEY (`stock_adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjustment_date` date NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `adjustment_type` enum('increase','decrease') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_adjustments_adjustment_number_unique` (`adjustment_number`),
  KEY `stock_adjustments_warehouse_id_foreign` (`warehouse_id`),
  KEY `stock_adjustments_created_by_foreign` (`created_by`),
  KEY `stock_adjustments_approved_by_foreign` (`approved_by`),
  CONSTRAINT `stock_adjustments_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_adjustments_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `quantity` double NOT NULL DEFAULT '0',
  `value` decimal(18,2) DEFAULT NULL,
  `type` enum('purchase_in','sales','transfer_in','transfer_out','manufacture_in','manufacture_out','adjustment_in','adjustment_out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID Dokument terkait',
  `date` datetime NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `rak_id` int DEFAULT NULL,
  `from_model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_model_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_from_model_type_from_model_id_index` (`from_model_type`,`from_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_opname_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_opname_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_opname_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `rak_id` bigint unsigned DEFAULT NULL,
  `system_qty` decimal(15,2) NOT NULL DEFAULT '0.00',
  `physical_qty` decimal(15,2) NOT NULL DEFAULT '0.00',
  `difference_qty` decimal(15,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `average_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `difference_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_opname_items_stock_opname_id_foreign` (`stock_opname_id`),
  KEY `stock_opname_items_product_id_foreign` (`product_id`),
  KEY `stock_opname_items_rak_id_foreign` (`rak_id`),
  CONSTRAINT `stock_opname_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_opname_items_rak_id_foreign` FOREIGN KEY (`rak_id`) REFERENCES `raks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_opname_items_stock_opname_id_foreign` FOREIGN KEY (`stock_opname_id`) REFERENCES `stock_opnames` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_opnames`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_opnames` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opname_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `opname_date` date NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `status` enum('draft','in_progress','completed','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_opnames_opname_number_unique` (`opname_number`),
  KEY `stock_opnames_warehouse_id_foreign` (`warehouse_id`),
  KEY `stock_opnames_created_by_foreign` (`created_by`),
  KEY `stock_opnames_approved_by_foreign` (`approved_by`),
  CONSTRAINT `stock_opnames_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_opnames_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_opnames_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_reservations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_order_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `rak_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `material_issue_id` bigint unsigned DEFAULT NULL,
  `delivery_order_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_reservations_sale_order_id_foreign` (`sale_order_id`),
  KEY `stock_reservations_product_id_foreign` (`product_id`),
  KEY `stock_reservations_warehouse_id_foreign` (`warehouse_id`),
  KEY `stock_reservations_rak_id_foreign` (`rak_id`),
  KEY `stock_reservations_material_issue_id_foreign` (`material_issue_id`),
  KEY `stock_reservations_delivery_order_id_foreign` (`delivery_order_id`),
  CONSTRAINT `stock_reservations_delivery_order_id_foreign` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_reservations_material_issue_id_foreign` FOREIGN KEY (`material_issue_id`) REFERENCES `material_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_reservations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_reservations_rak_id_foreign` FOREIGN KEY (`rak_id`) REFERENCES `raks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_reservations_sale_order_id_foreign` FOREIGN KEY (`sale_order_id`) REFERENCES `sale_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_reservations_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transfer_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_transfer_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `from_warehouse_id` int NOT NULL,
  `from_rak_id` int NOT NULL,
  `to_warehouse_id` int NOT NULL,
  `to_rak_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_warehouse_id` int NOT NULL,
  `to_warehouse_id` int NOT NULL,
  `transfer_date` datetime NOT NULL,
  `status` enum('Pending','Completed','Cancelled','Draft','Approved','Request','Reject') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `perusahaan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `handphone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fax` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `npwp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tempo_hutang` int NOT NULL DEFAULT '0' COMMENT 'Hari',
  `kontak_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cabang_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_code_unique` (`code`),
  KEY `suppliers_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `suppliers_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `surat_jalan_delivery_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `surat_jalan_delivery_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `surat_jalan_id` int NOT NULL,
  `delivery_order_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `surat_jalans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `surat_jalans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sj_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor surat jalan',
  `issued_at` datetime NOT NULL COMMENT 'Tanggal surat jalan dibuat',
  `signed_by` int DEFAULT NULL,
  `status` tinyint(1) NOT NULL COMMENT 'terbit / tidak',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int NOT NULL,
  `document_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `effective_date` date NOT NULL,
  `status` tinyint(1) NOT NULL,
  `type` enum('PPN','PPH','CUSTOM') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unit_of_measures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unit_of_measures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbreviation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` int DEFAULT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telepon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manage_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `cabang_id` int DEFAULT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `kode_user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `posisi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor Polisi',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'truck, pickup dan lain lain',
  `capacity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'untuk keterangan kapasitas',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicles_cabang_id_foreign` (`cabang_id`),
  CONSTRAINT `vehicles_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_payment_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_payment_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vendor_payment_id` int NOT NULL,
  `invoice_id` int DEFAULT NULL,
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `coa_id` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `payment_date` date NOT NULL,
  `adjustment_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `selected_invoices` json DEFAULT NULL,
  `invoice_receipts` json DEFAULT NULL,
  `ntpn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `total_payment` decimal(18,2) NOT NULL,
  `coa_id` bigint unsigned DEFAULT NULL,
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_import_payment` tinyint(1) NOT NULL DEFAULT '0',
  `ppn_import_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `pph22_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `bea_masuk_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('Draft','Partial','Paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `diskon` bigint NOT NULL DEFAULT '0' COMMENT 'Nilai Rupiah',
  `payment_adjustment` decimal(18,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `vendor_payments_coa_id_foreign` (`coa_id`),
  CONSTRAINT `vendor_payments_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voucher_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voucher_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor pengajuan voucher (auto-generated)',
  `voucher_date` date NOT NULL COMMENT 'Tanggal pengajuan (bisa backdate)',
  `amount` decimal(15,2) NOT NULL COMMENT 'Nominal pengajuan',
  `related_party` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Pihak terkait (customer/supplier/lainnya)',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Keterangan/catatan pengajuan',
  `status` enum('draft','pending','approved','rejected','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'Status pengajuan',
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu approval',
  `approval_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Catatan approval/rejection',
  `requested_to_owner_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu saat request dikirim ke Owner',
  `cash_bank_transaction_id` bigint unsigned DEFAULT NULL,
  `cabang_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `requested_to_owner_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_requests_voucher_number_unique` (`voucher_number`),
  KEY `voucher_requests_cash_bank_transaction_id_foreign` (`cash_bank_transaction_id`),
  KEY `voucher_requests_cabang_id_foreign` (`cabang_id`),
  KEY `voucher_requests_voucher_date_index` (`voucher_date`),
  KEY `voucher_requests_status_index` (`status`),
  KEY `voucher_requests_created_by_index` (`created_by`),
  KEY `voucher_requests_approved_by_index` (`approved_by`),
  KEY `voucher_requests_requested_to_owner_by_foreign` (`requested_to_owner_by`),
  CONSTRAINT `voucher_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `voucher_requests_cabang_id_foreign` FOREIGN KEY (`cabang_id`) REFERENCES `cabangs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `voucher_requests_cash_bank_transaction_id_foreign` FOREIGN KEY (`cash_bank_transaction_id`) REFERENCES `cash_bank_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `voucher_requests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `voucher_requests_requested_to_owner_by_foreign` FOREIGN KEY (`requested_to_owner_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_confirmation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_confirmation_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_confirmation_id` bigint unsigned NOT NULL,
  `sale_order_item_id` bigint unsigned NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_qty` decimal(15,2) DEFAULT NULL,
  `confirmed_qty` decimal(15,2) NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `rak_id` bigint unsigned DEFAULT NULL,
  `status` enum('request','confirmed','partial_confirmed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouse_confirmation_items_warehouse_confirmation_id_foreign` (`warehouse_confirmation_id`),
  KEY `warehouse_confirmation_items_sale_order_item_id_foreign` (`sale_order_item_id`),
  KEY `warehouse_confirmation_items_warehouse_id_foreign` (`warehouse_id`),
  KEY `warehouse_confirmation_items_rak_id_foreign` (`rak_id`),
  CONSTRAINT `warehouse_confirmation_items_rak_id_foreign` FOREIGN KEY (`rak_id`) REFERENCES `raks` (`id`),
  CONSTRAINT `warehouse_confirmation_items_sale_order_item_id_foreign` FOREIGN KEY (`sale_order_item_id`) REFERENCES `sale_order_items` (`id`),
  CONSTRAINT `warehouse_confirmation_items_warehouse_confirmation_id_foreign` FOREIGN KEY (`warehouse_confirmation_id`) REFERENCES `warehouse_confirmations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warehouse_confirmation_items_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_confirmation_warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_confirmation_warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_confirmation_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `status` enum('request','confirmed','partial_confirmed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'request',
  `confirmed_by` bigint unsigned DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wcw_wc_id_fk` (`warehouse_confirmation_id`),
  KEY `wcw_wh_id_fk` (`warehouse_id`),
  KEY `wcw_cb_id_fk` (`confirmed_by`),
  CONSTRAINT `wcw_cb_id_fk` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wcw_wc_id_fk` FOREIGN KEY (`warehouse_confirmation_id`) REFERENCES `warehouse_confirmations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wcw_wh_id_fk` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_confirmations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_confirmations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `manufacturing_order_id` int DEFAULT NULL,
  `confirmation_type` enum('sales_order','manufacturing_order') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_order_id` bigint unsigned DEFAULT NULL,
  `status` enum('Confirmed','Rejected','Request') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Request',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `confirmed_by` int DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouse_confirmations_sale_order_id_foreign` (`sale_order_id`),
  CONSTRAINT `warehouse_confirmations_sale_order_id_foreign` FOREIGN KEY (`sale_order_id`) REFERENCES `sale_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `kode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cabang_id` int NOT NULL,
  `tipe` enum('Kecil','Besar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Kecil',
  `telepon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `warna_background` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_05_21_165320_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_05_22_002137_create_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_05_22_002443_create_product_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_05_22_002555_create_unit_of_measures_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_05_22_002814_create_warehouses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_05_22_003014_create_stock_movements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_05_22_003539_create_suppliers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_05_22_003551_create_customers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_05_22_003557_create_inventory_stocks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_05_22_004111_create_purchase_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_05_22_004323_create_purchase_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_05_22_004516_create_purchase_receipts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_05_22_004632_create_purchase_receipt_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_05_22_004842_create_purchase_returns_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_05_22_004850_create_purchase_return_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_05_22_024024_modify_type_in_stock_movements',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_05_22_085500_create_currencies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_05_22_095255_add_column_is_asset_in_products',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_05_22_095306_add_column_is_asset_in_purchaseorders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_05_22_104231_create_purchase_receipt_photos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_05_22_104243_create_purchase_receipt_item_photos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_05_22_142259_create_sale_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_05_22_142314_create_sale_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_05_23_184713_create_stock_transfers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_05_23_184733_create_stock_transfer_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_05_23_185938_create_manufacturing_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_05_23_185958_create_manufacturing_order_materials_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_05_23_190011_create_warehouse_confirmations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_05_25_013425_modify_column_status_in_purchase_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_05_25_015840_modify_column_in_purchase_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_05_25_021858_add_column_opsi_harga_in_purchase_order_item',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_05_25_200327_add_column_signature_in_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_05_25_224701_add_column_date_approved_at_in_purchase_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_05_25_224929_add_column_approved_by_in_purchase_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_05_26_000429_add_column_note_in_purchase_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_05_26_142653_remove_photo_url_in_purchase_receipt_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_05_27_230439_remove_column_code_in_currencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_05_28_000000_create_raks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_05_28_000200_add_column_warehouse_id_in_raks',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_05_28_225259_add_column_in_manufacturing_order_materials',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_05_28_230537_modify_column_in_warehouse_confirmations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_05_29_000041_add_columns_in_purchase_receipt_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_05_29_000412_create_purchase_receipt_item_nominals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_05_29_164405_add_code_in_currency',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_05_29_171629_add_data_enum_in_purchase_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_05_30_000836_add_columns_in_purchase_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_05_30_002402_create_delivery_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_05_30_002810_create_delivery_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_05_30_002912_create_surat_jalans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_05_30_003052_create_drivers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_05_30_003106_create_vehicles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_05_30_010712_create_quality_controls_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_05_30_012917_modify_column_status_in_purchase_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_05_30_073429_add_warehouse_id_in_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2025_05_30_103800_add_column_is_sent_in_purchase_receipt_item',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2025_05_30_233858_create_delivery_sales_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_05_31_023730_add_column_product_id_in_quality_control',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2025_06_01_001905_add_columns_in_products',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2025_06_02_015354_add_columns_in_quality_control',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2025_06_03_010723_add_columns_in_purchase_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2025_06_03_011523_create_delivery_order_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2025_06_05_001137_add_column_code_in_currencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2025_06_06_231926_drop_column_opsi_harga_in_purchase_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2025_06_08_140447_add_column_created_by_in_purchase_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2025_06_09_150611_modify_column_warehouse_confirmations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2025_06_10_101358_add_columns_in_sales_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2025_06_13_214959_create_quotations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2025_06_13_215007_create_quotation_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2025_06_14_025922_add_column_shipped_to_in_sale_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2025_06_14_124456_add_columns_reject_in_sale_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2025_06_14_130922_modify_column_status_in_sale_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_06_14_155625_add_column_titip_saldo_in_sale_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2025_06_14_192925_add_column_from_sales_id_in_delivery_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2025_06_14_194134_add_column_sale_order_item_id_in_delivery_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_06_14_195803_drop_column_from_sales_in_delivery_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_06_14_205131_add_column_product_id_in_delivery_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_06_14_205334_modify_column_purchase_receip_item_id_in_delivery_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_06_14_205646_modify_column_status_in_delivery_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_06_14_212035_modify_column_status_in_delivery_order_logs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_06_14_224558_add_column_do_number_in_delivery_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_06_14_230457_drop_colum_delivery_order_id_in_surat_jalans',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_06_14_230508_create_surat_jalan_delivery_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2025_06_14_231240_modify_columns_in_surat_jalans',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2025_06_14_235120_add_column_reason_close_in_sale_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2025_06_15_024756_create_return_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2025_06_15_024804_create_return_product_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2025_06_15_030932_drop_column_from_id_in_return_product',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2025_06_15_031044_drop_column_from_item_id_in_return_product_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2025_06_15_142010_add_column_receipt_number_in_purchase_receipts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2025_06_15_164411_add_column_refer_model_in_purchase_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2025_06_15_164704_modify_column_i_n_return_products',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2025_06_15_164809_create_order_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2025_06_15_164819_create_order_request_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2025_06_15_165105_add_column_refer_item_model_in_purchase_order_item',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2025_06_15_171348_add_column_opsi_harga_in_purchase_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_06_15_175213_create_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_06_15_175214_add_event_column_to_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2025_06_15_175215_add_batch_uuid_column_to_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2025_06_15_205940_add_column_created_by_in_order_requests',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2025_06_15_212023_add_columns_in_purchase_receipt',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2025_06_15_212241_add_columns_in_purchase_receipt_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2025_06_16_013903_add_column_rak_in_quality_control',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2025_06_16_202734_add_and_modify_column_inventory_stock',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2025_06_16_215011_drop_column_date_create_delivery_order_in_quality_controls',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2025_06_16_215305_add_column_rak_id_in_stock_movements',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2025_06_16_220831_drop_columns_in_inventory_stocks',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2025_06_16_233924_modify_column_inventory_stocks',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2025_06_16_235124_add_column_from_model_in_stock_movements',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2025_06_17_002003_modify_columns_in_return_product_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2025_06_17_182632_create_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2025_06_17_182647_create_invoice_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2025_06_23_190743_add_columns_in_customers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2025_06_23_192140_add_telephone_in_customers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2025_06_23_200852_create_cabangs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2025_06_23_204132_add_columns_in_warehouse',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2025_06_23_210409_add_columns_in_product_categories',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2025_06_23_211328_add_columns_in_products',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2025_06_23_212054_add_column_biaya_in_products',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2025_06_23_222516_create_product_unit_conversions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2025_06_23_223456_modify_column_in_product_unit_conversions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2025_06_23_231740_add_columns_in_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2025_06_24_001026_modify_column_manage_type_in_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2025_06_24_012149_add_columns_in_purchase_returns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_06_24_021107_add_columns_in_purchase_return_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2025_06_24_114712_modify_column_status_in_stock_transfers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2025_06_24_121527_add_columns_in_transfer_stock_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2025_06_24_201430_add_item_enum_status_in_stock_transfers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2025_06_24_233708_add_column_from_model_in_quality_controls',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2025_06_24_235206_add_column_product_unit_conversion_id_in_manufacturing_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2025_06_25_000805_add_column_product_unit_conversion_id_in_manufacturing_order_materials',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2025_06_25_001500_add_column_rak_id_in_manufacturing_order_materials',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2025_06_25_011515_create_productions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2025_06_25_153353_drop_column_purchase_receipt_item_id_in_quality_controls',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2025_06_25_163407_add_column_qc_number_in_quality_controls',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2025_06_25_195801_add_column_due_date_in_invoices',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2025_06_25_200254_create_vendor_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2025_06_25_200601_create_vendor_payment_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2025_06_25_200953_create_account_payables_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2025_06_25_201002_create_ageing_schedules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2025_06_26_004642_create_chart_of_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2025_06_26_012950_add_column_diskon_to_vendor_payments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2025_06_27_021953_add_column_code_in_suppliers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2025_06_27_023834_add_column_delivery_date_in_purchase_order',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2025_06_27_024217_add_columns_in_purchase_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2025_06_27_024736_create_purchase_order_currencies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2025_06_27_035350_drop_column_payment_date_in_vendor_payments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2025_06_27_035444_add_column_payment_date_in_vendor_payment_details',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2025_06_27_041219_create_deposits_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2025_06_27_044836_create_deposit_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2025_06_28_145604_modify_columns_in_purchase_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2025_06_28_150707_create_purchase_order_item_biayas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2025_06_28_191936_modify_column_nilai_in_currencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2025_06_28_192501_modify_column_total_in_purchase_order_biayas',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2025_06_28_192542_modify_column_nominal_in_purchase_order_currencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2025_06_28_193334_add_columns_in_suppliers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2025_06_28_203551_modify_column_status_in_cabangs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2025_06_29_025717_modify_column_type_in_deposit_logs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2025_07_05_000042_modify_columns_in_purchase_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2025_07_05_000221_modify_columns_in_sale_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2025_07_05_002202_modify_columns_in_products',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2025_07_05_002415_modify_column_unit_price_in_purchase_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2025_07_05_002517_modify_column_total_amount_in_purchase_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2025_07_05_002642_modify_columns_in_quotation_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2025_07_05_002758_modify_column_total_amount_in_quotations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2025_07_05_002918_modify_columns_in_sale_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2025_07_05_003025_modify_column_total_amount_in_sale_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2025_07_05_003143_modify_columns_in_invoice_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2025_07_05_003242_modify_columns_in_invoices',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2025_07_05_005231_modify_columns_in_purchase_receipt_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2025_07_05_005404_modify_column_nominal_in_purchase_receipt_item_nominals',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2025_07_05_010235_modify_columns_in_purchase_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2025_07_05_010411_modify_column_other_fee_in_purchase_receipts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2025_07_05_010617_modify_columns_in_quality_controls',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2025_07_05_010811_modify_columns_in_purchase_return_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2025_07_05_010934_modify_column_quantity_in_return_product_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2025_07_05_011109_modify_column_amount_in_deposit_logs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2025_07_05_011351_modify_column_quantity_in_invoice_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2025_07_05_124923_drop_column_titip_saldo_in_sale_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2025_07_05_144008_modify_columns_in_deposit_logs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2025_07_05_144938_add_columns_in_sale_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2025_07_05_151820_add_column_min_qty_in_inventory_stocks',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2025_07_06_025918_create_tax_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2025_07_06_031123_add_columns_in_invoices',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2025_07_10_105118_add_column_tipe_pengiriman_in_sale_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2025_07_11_085134_add_column_is_manufacture_in_product',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2025_07_11_085550_create_bill_of_materials_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2025_07_11_085915_create_bill_of_material_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2025_07_11_092257_add_column_uom_id_in_bill_of_materials',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2025_07_12_220914_remove_columns_product_unit_conversions_id_in_manufacturing_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2025_07_12_221743_rename_column_product_unit_conversion_id_in_manufacturing_order_materials',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2025_07_13_012734_add_columns_in_manufacturing_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2025_07_13_225834_create_account_receivables_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2025_07_13_230649_create_customer_receipts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2025_07_13_230806_create_customer_receipt_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2025_07_15_203112_add_column_diskon_in_customer_receipts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2025_07_15_212950_modify_columns_in_ageing_schedules',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2025_07_16_004414_add_column_payment_date_in_customer_receipt_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2025_07_16_200954_create_journal_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2025_08_20_003544_add_is_active_column_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2025_08_20_003652_add_supplier_id_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2025_08_20_005534_create_delivery_order_approval_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2025_08_20_012216_add_created_by_to_sale_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2025_08_20_015121_ensure_delivery_date_not_null_in_delivery_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2025_08_20_020554_add_purchase_return_processed_to_quality_controls',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2025_08_20_193914_drop_column_delivery_date_in_purchase_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2025_08_30_211435_add_customer_supplier_info_to_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2025_08_30_211519_add_customer_supplier_info_to_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2025_08_30_213228_update_customer_receipts_for_multiple_invoices',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2025_08_30_213245_add_multiple_invoice_support_to_customer_receipts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2025_08_30_214244_add_multiple_invoice_support_to_vendor_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2025_09_02_000000_add_coa_id_and_payment_method_to_customer_receipts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2025_09_02_000001_add_coa_id_and_payment_method_to_vendor_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2025_09_02_005323_add_coa_id_and_payment_method_to_customer_receipts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2025_09_07_011156_add_invoice_receipts_to_customer_receipts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2025_09_07_012634_add_invoice_id_to_customer_receipt_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2025_09_08_112614_add_unique_constraint_to_customers_code_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2025_09_08_115258_add_unique_constraint_to_suppliers_code_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2025_09_09_101816_add_invoice_receipts_to_vendor_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2025_09_09_110634_add_invoice_id_and_notes_to_vendor_payment_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2025_10_07_101806_drop_level_column_from_chart_of_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2025_10_07_105545_add_balance_fields_to_chart_of_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2025_10_07_105846_add_contra_asset_to_chart_of_accounts_type_enum',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2025_10_07_111822_add_description_to_raks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2025_10_07_113714_create_assets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2025_10_07_113807_create_asset_depreciations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2025_10_07_135425_add_manufacturing_fields_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2025_10_07_135443_add_cost_fields_to_bill_of_materials_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2025_10_07_135457_create_material_issues_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2025_10_07_135502_create_material_issue_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2025_10_07_220018_drop_is_asset_column_from_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2025_10_07_224005_add_unit_price_and_subtotal_to_bill_of_material_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2025_10_07_232358_create_production_plans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2025_10_07_232415_add_production_plan_id_to_manufacturing_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2025_10_08_114834_add_production_plan_id_to_material_issues_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2025_10_09_144049_add_created_by_to_account_receivables_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2025_10_09_144305_add_created_by_to_account_payables_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2025_10_09_155047_change_other_fee_to_json_in_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2025_10_09_180001_add_cabang_id_to_journal_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2025_10_12_115439_add_department_project_to_journal_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2025_10_12_140000_add_links_to_assets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2025_10_13_090000_create_cash_bank_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2025_10_13_090100_create_cash_bank_transfers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2025_10_13_090200_add_reconciliation_fields_to_journal_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2025_10_13_090300_create_bank_reconciliations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2025_10_13_231113_add_other_costs_to_cash_bank_transfers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2025_10_17_000001_extend_stock_movements_with_value_and_meta',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2025_10_18_000100_create_report_cash_flow_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2025_10_18_000200_create_report_hpp_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2025_10_29_104636_create_voucher_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2025_10_29_110617_add_other_costs_coa_id_to_cash_bank_transfers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2025_10_30_000000_create_cash_bank_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2025_10_30_000001_add_cash_bank_account_id_to_cash_bank_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2025_10_30_000003_backfill_and_make_voucher_requests_columns_not_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2025_10_30_151806_add_is_current_to_chart_of_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2025_10_30_155920_update_bank_recon_status_enum_in_journal_entries',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2025_11_01_180143_add_quotation_id_to_sale_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (260,'2025_11_01_181044_add_sale_order_id_to_warehouse_confirmations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2025_11_01_181053_create_warehouse_confirmation_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2025_11_01_215615_make_manufacturing_order_id_nullable_in_warehouse_confirmations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2025_11_01_215648_add_warehouse_confirmed_at_to_sale_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2025_11_01_215908_add_warehouse_id_and_created_by_to_delivery_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2025_11_02_230000_modify_invoices_status',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2025_11_03_000000_add_invoiced_paid_to_purchase_orders_status',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2025_11_04_000001_add_requested_owner_columns_to_voucher_requests',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2025_11_05_112512_add_deleted_at_to_cash_bank_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2025_11_05_113805_create_cash_bank_transaction_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2025_11_06_000100_add_product_account_mapping_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2025_11_06_232120_add_temporary_procurement_coa_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2025_11_07_035855_add_coa_id_to_purchase_order_biayas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2025_11_07_042526_create_purchase_receipt_biayas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2025_11_07_042934_add_purchase_order_biaya_id_to_purchase_receipt_biayas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2025_11_07_052531_add_transaction_id_to_journal_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2025_11_07_072921_add_voucher_fields_to_cash_bank_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2025_11_07_082313_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2025_11_13_181825_add_supplier_id_to_order_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2025_11_15_000500_add_import_flags_and_non_ppn_option',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2025_11_18_081630_create_stock_adjustments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (281,'2025_11_18_081636_create_stock_opnames_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2025_11_18_081639_create_stock_opname_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2025_11_18_081712_create_stock_adjustment_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (284,'2025_11_18_094901_add_average_cost_to_stock_opname_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (285,'2025_11_18_135149_add_ntpn_to_cash_bank_transaction_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (286,'2025_11_19_000916_create_other_sales_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (287,'2025_11_20_121008_create_stock_reservations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (288,'2025_11_25_040839_add_deposit_number_to_deposits_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (289,'2025_11_25_043507_modify_column_type_in_deposit_logs_add_edit',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (290,'2025_11_25_180113_add_coa_fields_to_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (291,'2025_11_26_102942_add_sales_coa_fields_to_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (292,'2025_11_26_223133_add_payment_coa_id_to_deposits_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (293,'2025_11_27_010243_drop_invoice_id_from_vendor_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (294,'2025_11_27_101225_update_material_issues_status_enum',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (295,'2025_11_27_110303_add_approval_fields_to_material_issue_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (296,'2025_11_27_120000_add_adjustment_fields_to_vendor_payment_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (297,'2025_11_27_150938_add_coa_fields_to_bill_of_materials_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (298,'2025_11_28_193149_drop_unused_columns_from_manufacturing_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (299,'2025_11_28_193513_add_items_column_to_manufacturing_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (300,'2025_11_28_195941_add_warehouse_id_to_production_plans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (301,'2025_11_29_015510_drop_rak_id_from_manufacturing_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (302,'2025_11_29_033835_add_created_by_to_customer_receipts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (303,'2025_11_29_092057_add_material_issue_id_to_stock_reservations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (304,'2025_11_29_092800_make_sale_order_id_nullable_in_stock_reservations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (305,'2025_11_29_114439_drop_manufacturing_order_materials_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (306,'2025_11_30_090558_make_source_fields_nullable_in_journal_entries_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (307,'2025_11_30_215246_add_delivery_order_id_to_stock_reservations_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (308,'2025_12_01_011736_create_income_statement_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (309,'2025_12_01_095914_add_selected_invoices_to_customer_receipt_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (310,'2025_12_01_112327_add_depreciation_method_to_assets_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (311,'2025_12_01_113100_update_depreciation_method_enum_in_assets_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (312,'2025_12_01_133859_make_rak_id_nullable_in_warehouse_confirmation_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (313,'2025_12_01_141437_add_request_status_to_warehouse_confirmation_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (314,'2025_12_01_141622_add_missing_columns_to_warehouse_confirmation_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (315,'2025_12_01_184540_add_document_to_surat_jalans_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (316,'2025_12_01_220119_create_warehouse_confirmation_warehouses_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (317,'2025_12_02_082727_add_confirmation_type_to_warehouse_confirmations_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (318,'2025_12_02_092534_add_cascade_delete_to_warehouse_confirmation_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (319,'2025_12_02_104929_add_return_action_to_return_products_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (320,'2025_12_02_163606_add_additional_cost_to_delivery_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (321,'2025_12_02_194042_add_tax_discount_to_invoice_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (322,'2025_12_02_224510_modify_inventory_stocks_unique_constraint',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (323,'2025_12_02_234303_add_missing_columns_to_delivery_order_logs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (324,'2025_12_02_234622_add_delivered_quantity_to_sale_order_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (325,'2025_12_05_085407_add_cabang_id_to_sale_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (326,'2025_12_05_085419_add_cabang_id_to_purchase_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (327,'2025_12_05_085426_add_cabang_id_to_invoices_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (328,'2025_12_05_090332_add_cabang_id_to_delivery_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (329,'2025_12_05_090349_add_cabang_id_to_purchase_receipts_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (330,'2025_12_05_090410_add_cabang_id_to_customer_receipts_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (331,'2025_12_05_090433_add_cabang_id_to_manufacturing_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (332,'2025_12_05_090453_add_cabang_id_to_account_receivables_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (333,'2025_12_05_092332_add_cabang_id_to_cash_bank_transfers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (334,'2025_12_05_092916_add_cabang_id_to_customers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (335,'2025_12_05_093317_add_cabang_id_to_suppliers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (336,'2025_12_05_111357_add_cabang_id_to_drivers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (337,'2025_12_05_111359_add_cabang_id_to_vehicles_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (338,'2025_12_05_151132_add_cabang_id_to_assets_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (339,'2025_12_05_154036_add_approval_signature_to_purchase_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (340,'2025_12_05_154201_create_asset_disposals_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (341,'2025_12_05_154637_create_asset_transfers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (342,'2025_12_05_164120_add_deleted_at_to_asset_disposals_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (343,'2025_12_05_164134_add_deleted_at_to_asset_transfers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (344,'2025_12_06_085628_add_cabang_id_to_order_requests_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (345,'2025_12_07_133843_add_status_and_approval_fields_to_purchase_returns_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (346,'2025_12_07_135003_add_cabang_id_to_purchase_returns_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (347,'2025_12_07_135826_add_additional_fields_to_purchase_returns_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (348,'2025_12_08_151652_add_code_to_assets_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (349,'2025_12_10_215026_add_quantity_produced_to_productions_table',2);
