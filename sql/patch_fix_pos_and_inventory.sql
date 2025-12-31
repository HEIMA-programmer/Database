-- ================================================
-- POS订单状态和库存成本修复补丁
-- 创建日期: 2025-12-31
-- 问题修复:
-- 1. 确保POS订单直接完成为Completed状态
-- 2. 修复库存成本统计（包含Reserved状态）
-- 3. 确保Revenue统计正确
-- ================================================

DELIMITER $$

-- ================================================
-- 1. 修复 sp_create_pos_order - 确保POS订单直接完成
-- ================================================
DROP PROCEDURE IF EXISTS sp_create_pos_order$$
CREATE PROCEDURE sp_create_pos_order(
    IN p_customer_id INT, -- 可为NULL（walk-in customer）
    IN p_employee_id INT,
    IN p_shop_id INT,
    IN p_stock_item_ids TEXT, -- 逗号分隔的 StockItemID 列表
    OUT p_order_id INT,
    OUT p_total_amount DECIMAL(10,2)
)
BEGIN
    DECLARE v_stock_item_id INT;
    DECLARE v_unit_price DECIMAL(10,2);
    DECLARE v_stock_status VARCHAR(20);
    DECLARE v_subtotal DECIMAL(10,2) DEFAULT 0;
    DECLARE v_discount_rate DECIMAL(3,2) DEFAULT 0;
    DECLARE v_discount_amount DECIMAL(10,2) DEFAULT 0;
    DECLARE v_pos INT DEFAULT 1;
    DECLARE v_next_pos INT;
    DECLARE v_id_str VARCHAR(20);
    DECLARE v_items_processed INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_order_id = -1;
        SET p_total_amount = 0;
        RESIGNAL;
    END;

    -- 获取客户折扣率
    IF p_customer_id IS NOT NULL THEN
        SELECT mt.DiscountRate INTO v_discount_rate
        FROM Customer c
        JOIN MembershipTier mt ON c.TierID = mt.TierID
        WHERE c.CustomerID = p_customer_id;
    END IF;

    -- 创建订单
    INSERT INTO CustomerOrder (
        CustomerID, FulfilledByShopID, ProcessedByEmployeeID,
        OrderType, OrderStatus
    ) VALUES (
        p_customer_id, p_shop_id, p_employee_id,
        'InStore', 'Pending'
    );

    SET p_order_id = LAST_INSERT_ID();

    -- 解析并处理每个 StockItemID
    SET p_stock_item_ids = CONCAT(p_stock_item_ids, ',');

    parse_loop: WHILE v_pos > 0 DO
        SET v_next_pos = LOCATE(',', p_stock_item_ids, v_pos);
        IF v_next_pos = 0 THEN
            LEAVE parse_loop;
        END IF;

        SET v_id_str = TRIM(SUBSTRING(p_stock_item_ids, v_pos, v_next_pos - v_pos));
        IF v_id_str != '' THEN
            SET v_stock_item_id = CAST(v_id_str AS UNSIGNED);

            -- 验证库存属于指定店铺且可用
            SELECT Status, UnitPrice INTO v_stock_status, v_unit_price
            FROM StockItem
            WHERE StockItemID = v_stock_item_id AND ShopID = p_shop_id
            FOR UPDATE;

            IF v_stock_status = 'Available' THEN
                -- 添加订单行
                INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale)
                VALUES (p_order_id, v_stock_item_id, v_unit_price);

                -- 预留库存
                UPDATE StockItem
                SET Status = 'Reserved'
                WHERE StockItemID = v_stock_item_id;

                SET v_subtotal = v_subtotal + v_unit_price;
                SET v_items_processed = v_items_processed + 1;
            END IF;
        END IF;

        SET v_pos = v_next_pos + 1;
    END WHILE;

    -- 计算折扣
    SET v_discount_amount = v_subtotal * v_discount_rate;

    -- 计算总金额
    SET p_total_amount = v_subtotal - v_discount_amount;

    -- 更新订单金额
    UPDATE CustomerOrder
    SET TotalAmount = p_total_amount,
        DiscountApplied = v_discount_amount
    WHERE OrderID = p_order_id;

    -- 如果没有处理任何商品，删除订单
    IF v_items_processed = 0 THEN
        DELETE FROM CustomerOrder WHERE OrderID = p_order_id;
        SET p_order_id = -2;
    ELSE
        -- 【修复】POS订单直接完成，调用完成订单存储过程
        CALL sp_complete_order(p_order_id);
    END IF;
END$$

-- ================================================
-- 2. 确保 sp_complete_order 正确处理InStore订单
-- ================================================
DROP PROCEDURE IF EXISTS sp_complete_order$$
CREATE PROCEDURE sp_complete_order(
    IN p_order_id INT
)
BEGIN
    DECLARE v_customer_id INT;
    DECLARE v_order_status VARCHAR(20);
    DECLARE v_order_type VARCHAR(10);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    -- 获取订单信息
    SELECT CustomerID, OrderStatus, OrderType INTO v_customer_id, v_order_status, v_order_type
    FROM CustomerOrder
    WHERE OrderID = p_order_id
    FOR UPDATE;

    -- 【修复】门店订单(InStore)可以从Pending直接完成，线上订单需要Paid或Shipped
    IF v_order_type = 'InStore' THEN
        IF v_order_status NOT IN ('Pending', 'Paid') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'InStore order must be Pending or Paid to complete';
        END IF;
    ELSE
        IF v_order_status NOT IN ('Paid', 'Shipped') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Online order must be Paid or Shipped to complete';
        END IF;
    END IF;

    -- 更新订单状态为Completed
    UPDATE CustomerOrder
    SET OrderStatus = 'Completed'
    WHERE OrderID = p_order_id;

    -- 更新库存状态为已售出
    UPDATE StockItem s
    JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
    SET s.Status = 'Sold', s.DateSold = NOW()
    WHERE ol.OrderID = p_order_id;

    -- 注意: 积分更新和会员升级由触发器 trg_after_order_complete 自动处理
END$$

DELIMITER ;

-- ================================================
-- 3. 修复库存成本视图 - 包含Reserved状态
-- 【重要】Reserved商品仍属于店铺库存，应计入成本
-- ================================================
CREATE OR REPLACE VIEW vw_shop_inventory_cost AS
SELECT
    si.ShopID,
    COALESCE(SUM(
        CASE
            WHEN si.SourceType = 'Supplier' THEN
                COALESCE(
                    (SELECT sol.UnitCost
                     FROM SupplierOrderLine sol
                     WHERE sol.SupplierOrderID = si.SourceOrderID
                     AND sol.ReleaseID = si.ReleaseID
                     AND sol.ConditionGrade = si.ConditionGrade
                     LIMIT 1),
                    si.UnitCost
                )
            WHEN si.SourceType = 'Buyback' THEN
                COALESCE(
                    (SELECT bol.UnitPrice
                     FROM BuybackOrderLine bol
                     WHERE bol.BuybackOrderID = si.SourceOrderID
                     AND bol.ReleaseID = si.ReleaseID
                     AND bol.ConditionGrade = si.ConditionGrade
                     LIMIT 1),
                    si.UnitCost
                )
            ELSE COALESCE(si.UnitCost, 0)
        END
    ), 0) AS TotalInventoryCost,
    COUNT(*) AS InventoryCount
FROM StockItem si
-- 【修复】包含Reserved状态，因为Reserved商品仍然是库存的一部分
WHERE si.Status IN ('Available', 'Reserved', 'Sold')
GROUP BY si.ShopID;

-- ================================================
-- 4. 修复任何处于错误状态的POS订单（历史数据修复）
-- 将InStore类型且状态为Pending的订单完成
-- ================================================
-- 首先更新商品状态
UPDATE StockItem si
JOIN OrderLine ol ON si.StockItemID = ol.StockItemID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
SET si.Status = 'Sold', si.DateSold = NOW()
WHERE co.OrderType = 'InStore'
  AND co.OrderStatus = 'Pending'
  AND si.Status = 'Reserved';

-- 然后更新订单状态
UPDATE CustomerOrder
SET OrderStatus = 'Completed'
WHERE OrderType = 'InStore'
  AND OrderStatus = 'Pending';

-- ================================================
-- 完成提示
-- ================================================
SELECT 'POS和库存成本修复补丁已应用' AS Status;
