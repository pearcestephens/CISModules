-- https://staff.vapeshed.co.nz/modules/purchase-orders/schema/002_inventory_adjust_requests.sql
-- Queue table for inventory adjustments. Standalone, idempotent.

CREATE TABLE IF NOT EXISTS `inventory_adjust_requests` (
  `request_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` varchar(96) DEFAULT NULL,
  `outlet_id` varchar(100) NOT NULL,
  `product_id` varchar(100) NOT NULL,
  `delta` int(11) NOT NULL,
  `reason` varchar(64) DEFAULT NULL,
  `source` varchar(64) DEFAULT 'purchase-order',
  `status` enum('pending','queued','processing','done','failed') NOT NULL DEFAULT 'pending',
  `idempotency_key` varchar(190) NOT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `error_msg` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uidx_idem` (`idempotency_key`),
  KEY `idx_prod_outlet` (`product_id`,`outlet_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_at` (`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
