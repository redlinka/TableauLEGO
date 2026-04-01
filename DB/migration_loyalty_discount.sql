-- Migration: Add loyalty discount tracking to ORDER_BILL
-- Date: 2026-04-01

ALTER TABLE `ORDER_BILL`
  ADD COLUMN `discount_percent` DECIMAL(5,2) DEFAULT NULL COMMENT 'Loyalty discount percentage applied',
  ADD COLUMN `discount_amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Discount amount in EUR',
  ADD COLUMN `loyalty_points_used` INT DEFAULT NULL COMMENT 'Number of loyalty points redeemed';
