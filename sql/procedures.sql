-- ========================================
-- Stored Procedures for Business Logic
-- 存储过程 - 封装业务流程并确保事务一致性
-- ========================================

DELIMITER $$

-- ================================================
-- 1. 供应商进货流程
-- ================================================

-- 创建供应商订单
DROP PROCEDURE IF EXISTS sp_create_supplier_order$$
CREATE PROCEDURE sp_create_supplier_order(
    IN p_supplier_id INT,
    IN p_employee_id INT,
    IN p_destination_shop_id INT,
    OUT p_order_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_order_id = -1;
    END;

    START TRANSACTION;

    INSERT INTO SupplierOrder (SupplierID, CreatedByEmployeeID, DestinationShopID)
    VALUES (p_supplier_id, p_employee_id, p_destination_shop_id);

    SET p_order_id = LAST_INSERT_ID();

    COMMIT;
END$$

-- 添加订单行项目
DROP PROCEDURE IF EXISTS sp_add_supplier_order_line$$
CREATE PROCEDURE sp_add_supplier_order_line(
    IN p_order_id INT,
    IN p_release_id INT,
    IN p_quantity INT,
    IN p_unit_cost DECIMAL(10,2)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Failed to add order line';
    END;

    START TRANSACTION;

    INSERT INTO SupplierOrderLine (SupplierOrderID, ReleaseID, Quantity, UnitCost)
    VALUES (p_order_id, p_release_id, p_quantity, p_unit_cost);

    COMMIT;
END$$

-- 接收供应商订单并生成库存
DROP PROCEDURE IF EXISTS sp_receive_supplier_order$$
CREATE PROCEDURE sp_receive_supplier_order(
    IN p_order_id INT,
    IN p_batch_no VARCHAR(50),
    IN p_condition_grade VARCHAR(10),
    IN p_markup_rate DECIMAL(3,2) -- 加价率,例如 0.50 表示成本价的150%
)
BEGIN
    DECLARE v_release_id INT;
    DECLARE v_quantity INT;
    DECLARE v_unit_cost DECIMAL(10,2);
    DECLARE v_unit_price DECIMAL(10,2);
    DECLARE v_shop_id INT;
    DECLARE v_counter INT;
    DECLARE v_status VARCHAR(20);
    DECLARE done INT DEFAULT FALSE;

    DECLARE cur CURSOR FOR
        SELECT ReleaseID, Quantity, UnitCost
        FROM SupplierOrderLine
        WHERE SupplierOrderID = p_order_id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Failed to receive supplier order';
    END;

    START TRANSACTION;

    -- 检查订单状态
    SELECT Status, DestinationShopID INTO v_status, v_shop_id
    FROM SupplierOrder
    WHERE SupplierOrderID = p_order_id;

    IF v_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order is not in Pending status';
    END IF;

    -- 遍历订单行，生成StockItem
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_release_id, v_quantity, v_unit_cost;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- 计算售价
        SET v_unit_price = v_unit_cost * (1 + p_markup_rate);

        -- 为每个数量创建独立的StockItem
        SET v_counter = 0;
        WHILE v_counter < v_quantity DO
            INSERT INTO StockItem (
                ReleaseID, ShopID, SourceType, SourceOrderID,
                BatchNo, ConditionGrade, Status, UnitPrice
            ) VALUES (
                v_release_id, v_shop_id, 'Supplier', p_order_id,
                p_batch_no, p_condition_grade, 'Available', v_unit_price
            );
            SET v_counter = v_counter + 1;
        END WHILE;
    END LOOP;
    CLOSE cur;

    -- 更新订单状态
    UPDATE SupplierOrder
    SET Status = 'Received', ReceivedDate = NOW()
    WHERE SupplierOrderID = p_order_id;

    -- 计算总成本
    UPDATE SupplierOrder so
    SET TotalCost = (
        SELECT SUM(Quantity * UnitCost)
        FROM SupplierOrderLine
        WHERE SupplierOrderID = p_order_id
    )
    WHERE SupplierOrderID = p_order_id;

    COMMIT;
END$$

-- ================================================
-- 2. 客户回购流程
-- ================================================

-- 创建回购订单并生成库存
DROP PROCEDURE IF EXISTS sp_process_buyback$$
CREATE PROCEDURE sp_process_buyback(
    IN p_customer_id INT,
    IN p_employee_id INT,
    IN p_shop_id INT,
    IN p_release_id INT,
    IN p_quantity INT,
    IN p_unit_price DECIMAL(10,2),
    IN p_condition_grade VARCHAR(10),
    IN p_resale_price DECIMAL(10,2),
    OUT p_buyback_id INT
)
BEGIN
    DECLARE v_batch_no VARCHAR(50);
    DECLARE v_counter INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_buyback_id = -1;
    END;

    START TRANSACTION;

    -- 创建回购订单
    INSERT INTO BuybackOrder (CustomerID, ProcessedByEmployeeID, ShopID, Status)
    VALUES (p_customer_id, p_employee_id, p_shop_id, 'Completed');

    SET p_buyback_id = LAST_INSERT_ID();

    -- 添加订单行
    INSERT INTO BuybackOrderLine (BuybackOrderID, ReleaseID, Quantity, UnitPrice, ConditionGrade)
    VALUES (p_buyback_id, p_release_id, p_quantity, p_unit_price, p_condition_grade);

    -- 生成批次号
    SET v_batch_no = CONCAT('BUY-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', p_buyback_id);

    -- 生成StockItem
    WHILE v_counter < p_quantity DO
        INSERT INTO StockItem (
            ReleaseID, ShopID, SourceType, SourceOrderID,
            BatchNo, ConditionGrade, Status, UnitPrice
        ) VALUES (
            p_release_id, p_shop_id, 'Buyback', p_buyback_id,
            v_batch_no, p_condition_grade, 'Available', p_resale_price
        );
        SET v_counter = v_counter + 1;
    END WHILE;

    -- 更新总支付金额
    UPDATE BuybackOrder
    SET TotalPayment = p_quantity * p_unit_price
    WHERE BuybackOrderID = p_buyback_id;

    COMMIT;
END$$

-- ================================================
-- 3. 库存调拨流程
-- ================================================

-- 发起库存调拨
DROP PROCEDURE IF EXISTS sp_initiate_transfer$$
CREATE PROCEDURE sp_initiate_transfer(
    IN p_stock_item_id INT,
    IN p_from_shop_id INT,
    IN p_to_shop_id INT,
    IN p_employee_id INT,
    OUT p_transfer_id INT
)
BEGIN
    DECLARE v_current_shop INT;
    DECLARE v_current_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_transfer_id = -1;
    END;

    START TRANSACTION;

    -- 验证库存状态
    SELECT ShopID, Status INTO v_current_shop, v_current_status
    FROM StockItem
    WHERE StockItemID = p_stock_item_id
    FOR UPDATE; -- 锁定行防止并发

    IF v_current_shop != p_from_shop_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock item not in source shop';
    END IF;

    IF v_current_status != 'Available' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock item is not available';
    END IF;

    -- 创建调拨记录
    INSERT INTO InventoryTransfer (
        StockItemID, FromShopID, ToShopID,
        AuthorizedByEmployeeID, Status
    ) VALUES (
        p_stock_item_id, p_from_shop_id, p_to_shop_id,
        p_employee_id, 'InTransit'
    );

    SET p_transfer_id = LAST_INSERT_ID();

    -- 更新库存状态
    UPDATE StockItem
    SET Status = 'InTransit'
    WHERE StockItemID = p_stock_item_id;

    COMMIT;
END$$

-- 完成库存调拨
DROP PROCEDURE IF EXISTS sp_complete_transfer$$
CREATE PROCEDURE sp_complete_transfer(
    IN p_transfer_id INT,
    IN p_received_by_employee_id INT
)
BEGIN
    DECLARE v_stock_item_id INT;
    DECLARE v_to_shop_id INT;
    DECLARE v_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Failed to complete transfer';
    END;

    START TRANSACTION;

    -- 获取调拨信息
    SELECT StockItemID, ToShopID, Status
    INTO v_stock_item_id, v_to_shop_id, v_status
    FROM InventoryTransfer
    WHERE TransferID = p_transfer_id
    FOR UPDATE;

    IF v_status != 'InTransit' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transfer is not in transit';
    END IF;

    -- 更新调拨状态
    UPDATE InventoryTransfer
    SET Status = 'Completed',
        ReceivedByEmployeeID = p_received_by_employee_id,
        ReceivedDate = NOW()
    WHERE TransferID = p_transfer_id;

    -- 更新库存位置和状态
    UPDATE StockItem
    SET ShopID = v_to_shop_id,
        Status = 'Available'
    WHERE StockItemID = v_stock_item_id;

    COMMIT;
END$$

-- ================================================
-- 4. 销售流程
-- ================================================

-- 创建客户订单（在线/店内）
DROP PROCEDURE IF EXISTS sp_create_customer_order$$
CREATE PROCEDURE sp_create_customer_order(
    IN p_customer_id INT,
    IN p_shop_id INT,
    IN p_employee_id INT, -- NULL for online orders
    IN p_order_type VARCHAR(10), -- 'InStore' or 'Online'
    OUT p_order_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_order_id = -1;
    END;

    START TRANSACTION;

    INSERT INTO CustomerOrder (
        CustomerID, FulfilledByShopID, ProcessedByEmployeeID,
        OrderType, OrderStatus
    ) VALUES (
        p_customer_id, p_shop_id, p_employee_id,
        p_order_type, 'Pending'
    );

    SET p_order_id = LAST_INSERT_ID();

    COMMIT;
END$$

-- 添加订单商品
DROP PROCEDURE IF EXISTS sp_add_order_item$$
CREATE PROCEDURE sp_add_order_item(
    IN p_order_id INT,
    IN p_stock_item_id INT,
    IN p_price_at_sale DECIMAL(10,2)
)
BEGIN
    DECLARE v_stock_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Failed to add item to order';
    END;

    START TRANSACTION;

    -- 检查库存状态
    SELECT Status INTO v_stock_status
    FROM StockItem
    WHERE StockItemID = p_stock_item_id
    FOR UPDATE;

    IF v_stock_status != 'Available' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock item is not available';
    END IF;

    -- 添加订单行
    INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale)
    VALUES (p_order_id, p_stock_item_id, p_price_at_sale);

    -- 预留库存
    UPDATE StockItem
    SET Status = 'Reserved'
    WHERE StockItemID = p_stock_item_id;

    COMMIT;
END$$

-- 完成订单（支付成功）
DROP PROCEDURE IF EXISTS sp_complete_order$$
CREATE PROCEDURE sp_complete_order(
    IN p_order_id INT,
    IN p_points_earned INT
)
BEGIN
    DECLARE v_customer_id INT;
    DECLARE v_total_amount DECIMAL(10,2);
    DECLARE v_order_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Failed to complete order';
    END;

    START TRANSACTION;

    -- 获取订单信息
    SELECT CustomerID, OrderStatus INTO v_customer_id, v_order_status
    FROM CustomerOrder
    WHERE OrderID = p_order_id
    FOR UPDATE;

    IF v_order_status NOT IN ('Pending', 'Paid') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid order status for completion';
    END IF;

    -- 计算订单总额
    SELECT SUM(PriceAtSale) INTO v_total_amount
    FROM OrderLine
    WHERE OrderID = p_order_id;

    -- 更新订单状态
    UPDATE CustomerOrder
    SET OrderStatus = 'Completed',
        TotalAmount = v_total_amount
    WHERE OrderID = p_order_id;

    -- 更新库存状态为已售出
    UPDATE StockItem s
    JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
    SET s.Status = 'Sold', s.DateSold = NOW()
    WHERE ol.OrderID = p_order_id;

    -- 更新客户积分
    IF v_customer_id IS NOT NULL THEN
        UPDATE Customer
        SET Points = Points + p_points_earned
        WHERE CustomerID = v_customer_id;
    END IF;

    COMMIT;
END$$

-- 取消订单
DROP PROCEDURE IF EXISTS sp_cancel_order$$
CREATE PROCEDURE sp_cancel_order(
    IN p_order_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Failed to cancel order';
    END;

    START TRANSACTION;

    -- 释放预留库存
    UPDATE StockItem s
    JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
    SET s.Status = 'Available'
    WHERE ol.OrderID = p_order_id AND s.Status = 'Reserved';

    -- 更新订单状态
    UPDATE CustomerOrder
    SET OrderStatus = 'Cancelled'
    WHERE OrderID = p_order_id;

    COMMIT;
END$$

-- ================================================
-- 5. 库存检查辅助函数
-- ================================================

-- 检查特定商品的可用库存数量
DROP FUNCTION IF EXISTS fn_get_available_stock$$
CREATE FUNCTION fn_get_available_stock(
    p_release_id INT,
    p_shop_id INT,
    p_condition_grade VARCHAR(10)
) RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_count INT;

    SELECT COUNT(*) INTO v_count
    FROM StockItem
    WHERE ReleaseID = p_release_id
      AND ShopID = p_shop_id
      AND ConditionGrade = p_condition_grade
      AND Status = 'Available';

    RETURN v_count;
END$$

-- ================================================
-- 6. 会员等级自动升级
-- ================================================

DROP PROCEDURE IF EXISTS sp_update_customer_tier$$
CREATE PROCEDURE sp_update_customer_tier(
    IN p_customer_id INT
)
BEGIN
    DECLARE v_points INT;
    DECLARE v_new_tier_id INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    -- 获取客户积分
    SELECT Points INTO v_points
    FROM Customer
    WHERE CustomerID = p_customer_id;

    -- 根据积分确定新等级
    SELECT TierID INTO v_new_tier_id
    FROM MembershipTier
    WHERE v_points >= MinPoints
    ORDER BY MinPoints DESC
    LIMIT 1;

    -- 更新等级
    UPDATE Customer
    SET TierID = v_new_tier_id
    WHERE CustomerID = p_customer_id;

    COMMIT;
END$$

DELIMITER ;
