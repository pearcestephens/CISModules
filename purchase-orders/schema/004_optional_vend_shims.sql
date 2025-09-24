-- https://staff.vapeshed.co.nz/modules/purchase-orders/schema/004_optional_vend_shims.sql
-- Optional shim tables; only created if missing to support UI joins.

CREATE TABLE IF NOT EXISTS `vend_products` (
  `product_id` varchar(100) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vend_inventory` (
  `product_id` varchar(100) NOT NULL,
  `outlet_id` varchar(100) NOT NULL,
  `inventory_level` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`,`outlet_id`),
  KEY `idx_outlet` (`outlet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
