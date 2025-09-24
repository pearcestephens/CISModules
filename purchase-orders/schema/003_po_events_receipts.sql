-- https://staff.vapeshed.co.nz/modules/purchase-orders/schema/003_po_events_receipts.sql
-- Events + Receipts ledger. Idempotent creates.

CREATE TABLE IF NOT EXISTS `po_events` (
  `event_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `event_data` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `idx_po_event` (`purchase_order_id`,`event_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_receipts` (
  `receipt_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `outlet_id` varchar(100) NOT NULL,
  `is_final` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`receipt_id`),
  KEY `idx_po_receipt` (`purchase_order_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_receipt_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_id` bigint(20) unsigned NOT NULL,
  `product_id` varchar(100) NOT NULL,
  `expected_qty` int(11) NOT NULL DEFAULT 0,
  `received_qty` int(11) NOT NULL DEFAULT 0,
  `line_note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_receipt` (`receipt_id`),
  CONSTRAINT `fk_receipt_items_header` FOREIGN KEY (`receipt_id`) REFERENCES `po_receipts` (`receipt_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support tables discovered in workspace (attachments, discrepancies, documents, email ingest, evidence, notes, panel state, sim sessions/lines, security events, settings, supplier ratings)

CREATE TABLE IF NOT EXISTS `po_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `line_item_id` int(11) DEFAULT NULL,
  `document_type` enum('packing_slip','invoice','other') DEFAULT 'other',
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL,
  PRIMARY KEY (`attachment_id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  CONSTRAINT `po_attachments_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_discrepancies` (
  `discrepancy_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `line_item_id` int(11) DEFAULT NULL,
  `type` enum('missing','damaged','over','under','other') DEFAULT 'other',
  `description` mediumtext DEFAULT NULL,
  `discrepancy_note` mediumtext DEFAULT NULL,
  `detected_by` int(11) DEFAULT NULL,
  `detected_at` datetime NOT NULL,
  PRIMARY KEY (`discrepancy_id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  CONSTRAINT `po_discrepancies_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `document_type` enum('packing_slip','invoice','other') DEFAULT 'other',
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL,
  PRIMARY KEY (`document_id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  CONSTRAINT `po_documents_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_email_ingest` (
  `ingest_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `email_from` varchar(128) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `received_at` datetime NOT NULL,
  `processed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`ingest_id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  CONSTRAINT `po_email_ingest_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_evidence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `evidence_type` varchar(50) DEFAULT 'delivery',
  `file_path` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_po_id` (`purchase_order_id`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `line_item_id` int(11) DEFAULT NULL,
  `discrepancy_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `note_type` enum('general','line','discrepancy','event') DEFAULT 'general',
  `note` mediumtext NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`note_id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  CONSTRAINT `po_notes_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_receiving_sim_sessions` (
  `sim_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `quality` enum('poor','fair','good','excellent') DEFAULT NULL,
  `items_count` int(11) NOT NULL DEFAULT 0,
  `is_partial` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`sim_id`),
  KEY `idx_po` (`purchase_order_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_receiving_sim_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sim_id` int(11) NOT NULL,
  `product_id` varchar(64) NOT NULL,
  `expected_qty` int(11) NOT NULL DEFAULT 0,
  `received_qty` int(11) NOT NULL DEFAULT 0,
  `flags_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `line_note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sim` (`sim_id`),
  CONSTRAINT `fk_sim_header` FOREIGN KEY (`sim_id`) REFERENCES `po_receiving_sim_sessions` (`sim_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_security_events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) DEFAULT NULL,
  `event_type` varchar(64) NOT NULL,
  `event_data` longtext DEFAULT NULL,
  `triggered_by` int(11) DEFAULT NULL,
  `triggered_at` datetime NOT NULL,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(64) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `scope` enum('global','outlet','user') DEFAULT 'global',
  `scope_id` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_supplier_ratings` (
  `rating_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `rating` enum('excellent','good','fair','poor') DEFAULT 'good',
  `notes` mediumtext DEFAULT NULL,
  `rated_by` int(11) NOT NULL,
  `rated_at` datetime NOT NULL,
  PRIMARY KEY (`rating_id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  CONSTRAINT `po_supplier_ratings_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `po_ui_panels_state` (
  `state_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `panel_name` varchar(64) NOT NULL,
  `state` longtext DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`state_id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  CONSTRAINT `po_ui_panels_state_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
