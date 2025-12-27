-- ========================================
-- Migration: 添加订单履行相关字段
-- 用于修复运费和履行方式问题
-- ========================================

-- 修改 OrderStatus 枚举以支持 ReadyForPickup 状态
ALTER TABLE CustomerOrder
MODIFY COLUMN OrderStatus ENUM('Pending', 'Paid', 'Shipped', 'ReadyForPickup', 'Completed', 'Cancelled') DEFAULT 'Pending';

-- 添加履行方式字段
ALTER TABLE CustomerOrder
ADD COLUMN FulfillmentType ENUM('Shipping', 'Pickup') DEFAULT NULL
AFTER OrderType;

-- 添加送货地址字段
ALTER TABLE CustomerOrder
ADD COLUMN ShippingAddress TEXT DEFAULT NULL
AFTER FulfillmentType;

-- 添加运费字段
ALTER TABLE CustomerOrder
ADD COLUMN ShippingCost DECIMAL(10,2) DEFAULT 0.00
AFTER ShippingAddress;

-- 更新现有订单的履行方式（根据OrderType推断）
UPDATE CustomerOrder
SET FulfillmentType = 'Pickup'
WHERE OrderType = 'InStore' AND FulfillmentType IS NULL;

-- 在线订单默认设置为Shipping（如果没有设置）
UPDATE CustomerOrder
SET FulfillmentType = 'Shipping'
WHERE OrderType = 'Online' AND FulfillmentType IS NULL;
