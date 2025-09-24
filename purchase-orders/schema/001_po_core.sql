-- https://staff.vapeshed.co.nz/modules/purchase-orders/schema/001_po_core.sql
-- Core Purchase Order schema. Idempotent: uses IF NOT EXISTS and IF NOT EXISTS on columns.

-- purchase_orders (existing-compatible)
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `purchase_order_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for each purchase order in the system.',
  `outlet_id` varchar(100) NOT NULL COMMENT 'Unique identifier for the store location placing the purchase order.',
  `supplier_id` varchar(100) NOT NULL COMMENT 'Unique identifier for the supplier associated with the purchase order.',
  `supplier_name_cache` varchar(150) DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last time this purchase order was updated',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User ID of staff who soft-deleted this purchase order',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When the purchase order was soft deleted',
  `status` int(11) DEFAULT 0 COMMENT 'Indicates the current progress stage of a purchase order in the system.',
  `created_by` varchar(45) NOT NULL COMMENT 'Identifier for the staff member who initiated the purchase order.',
  `completed_by` varchar(100) DEFAULT NULL COMMENT 'The staff member responsible for finalizing the purchase order.',
  `completed_timestamp` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when the purchase order was fully processed and completed.',
  `completed_notes` mediumtext DEFAULT NULL COMMENT 'Notes on discrepancies or issues encountered when completing a purchase order.',
  `receiving_started_by` varchar(45) DEFAULT NULL COMMENT 'Staff member who started the receiving process',
  `receiving_started_at` timestamp NULL DEFAULT NULL COMMENT 'When receiving process was initiated',
  `mobile_session_token` varchar(96) DEFAULT NULL COMMENT 'Token for mobile receiving session',
  `receiving_device_info` mediumtext DEFAULT NULL COMMENT 'JSON data about receiving device/browser',
  `receive_summary_json` longtext DEFAULT NULL,
  `api_id` varchar(45) DEFAULT NULL COMMENT 'Unique identifier for linking purchase orders to external systems or APIs.',
  `partial_delivery` int(11) NOT NULL DEFAULT 0 COMMENT 'Indicates if a purchase order has been partially delivered.',
  `partial_delivery_time` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when a partial delivery of the order was received.',
  `partial_delivery_by` varchar(45) DEFAULT NULL COMMENT 'The staff member responsible for handling the partial delivery of an order.',
  `packing_slip_no` varchar(80) DEFAULT NULL,
  `invoice_no` varchar(80) DEFAULT NULL,
  `no_packing_slip` tinyint(1) NOT NULL DEFAULT 0,
  `totals_mode` enum('EX_GST','INC_GST') DEFAULT NULL,
  `subtotal_ex_gst` decimal(12,4) DEFAULT NULL,
  `gst` decimal(12,4) DEFAULT NULL,
  `total_inc_gst` decimal(12,4) DEFAULT NULL,
  `id` int(11) GENERATED ALWAYS AS (`purchase_order_id`) VIRTUAL,
  `created_at` datetime GENERATED ALWAYS AS (`date_created`) VIRTUAL,
  `completed_at` datetime GENERATED ALWAYS AS (`completed_timestamp`) VIRTUAL,
  `last_received_by` int(11) DEFAULT NULL,
  `last_received_at` datetime DEFAULT NULL,
  `unlocked_by` int(11) DEFAULT NULL,
  `unlocked_at` datetime DEFAULT NULL,
  `receiving_notes` mediumtext DEFAULT NULL,
  `receiving_quality` enum('poor','fair','good','excellent') DEFAULT NULL,
  PRIMARY KEY (`purchase_order_id`,`outlet_id`,`supplier_id`),
  UNIQUE KEY `id_UNIQUE` (`purchase_order_id`),
  KEY `ix_po_supplier_status_completed` (`supplier_id`,`status`,`completed_timestamp`),
  KEY `ix_po_outlet_status_created` (`outlet_id`,`status`,`date_created`),
  KEY `idx_po_status_created` (`status`,`date_created`),
  KEY `idx_po_api` (`api_id`),
  KEY `ix_po_status` (`status`),
  KEY `ix_po_date_created` (`date_created`),
  KEY `ix_po_partial` (`partial_delivery`),
  KEY `idx_po_receiving_started` (`receiving_started_by`,`receiving_started_at`),
  KEY `idx_po_mobile_token` (`mobile_session_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- purchase_order_line_items (existing-compatible)
CREATE TABLE IF NOT EXISTS `purchase_order_line_items` (
  `product_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each product in a purchase order line item.',
  `substitution_product_id` varchar(100) DEFAULT NULL,
  `purchase_order_id` int(11) NOT NULL COMMENT 'Links each line item to its corresponding purchase order for tracking and reference.',
  `order_qty` int(11) NOT NULL COMMENT 'The number of units ordered for a specific product in a purchase order.',
  `slip_qty` int(11) DEFAULT NULL,
  `order_purchase_price` decimal(10,4) NOT NULL,
  `qty_in_stock_before` int(11) DEFAULT NULL COMMENT 'The stock level of a product before receiving the current purchase order.',
  `qty_arrived` int(11) DEFAULT NULL COMMENT 'The number of items received from a supplier for a specific purchase order.',
  `damaged_qty` int(11) NOT NULL DEFAULT 0,
  `discrepancy_type` enum('OK','MISSING','SENT_LOW','SENT_HIGH','SUBSTITUTED','DAMAGED','UNORDERED') NOT NULL DEFAULT 'OK',
  `unit_cost_ex_gst` decimal(10,4) DEFAULT NULL,
  `line_note` varchar(255) DEFAULT NULL,
  `received_by` varchar(45) DEFAULT NULL COMMENT 'Staff member who received this line item',
  `received_at` timestamp NULL DEFAULT NULL COMMENT 'When this line item was processed',
  `barcode_scanned` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether barcode was scanned during receiving',
  `photo_evidence_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of photos attached to this line item',
  `receiving_notes` mediumtext DEFAULT NULL COMMENT 'Additional notes during receiving process',
  `added_product` int(11) NOT NULL DEFAULT 0 COMMENT 'Indicates if the product was newly added to inventory with this order.',
  `unexpected_product` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last time this PO line item was updated',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User ID of staff who soft-deleted this PO line item',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When the PO line item was soft deleted',
  `qty_ordered` int(11) GENERATED ALWAYS AS (`order_qty`) VIRTUAL,
  `qty_received` int(11) GENERATED ALWAYS AS (`qty_arrived`) VIRTUAL,
  `has_damage` tinyint(1) DEFAULT NULL,
  `is_substitute` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`product_id`,`purchase_order_id`),
  KEY `purchaseOrderID_idx` (`purchase_order_id`),
  KEY `ix_poli_product` (`product_id`),
  KEY `idx_poli_unexpected` (`unexpected_product`),
  KEY `ix_poli_substitute` (`substitution_product_id`),
  KEY `idx_poli_received` (`received_by`,`received_at`),
  KEY `idx_poli_barcode` (`barcode_scanned`),
  KEY `idx_poli_photos` (`photo_evidence_count`),
  CONSTRAINT `purchaseOrderID` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- purchase_orders_flagged (optional helper)
CREATE TABLE IF NOT EXISTS `purchase_orders_flagged` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` varchar(96) NOT NULL,
  `outlet_id` varchar(64) NOT NULL,
  `status` tinyint(4) DEFAULT 0,
  `flagged_reason` varchar(255) DEFAULT NULL,
  `flagged_notes` text DEFAULT NULL,
  `flagged_by` varchar(64) DEFAULT NULL,
  `flagged_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_po` (`purchase_order_id`),
  KEY `idx_outlet` (`outlet_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Defensive column adds (no-ops if already exist)
ALTER TABLE `purchase_orders` 
  ADD COLUMN IF NOT EXISTS `status` int(11) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `completed_at` datetime NULL;

ALTER TABLE `purchase_order_line_items`
  ADD COLUMN IF NOT EXISTS `qty_arrived` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `received_at` timestamp NULL DEFAULT NULL;
