-- ========================================
-- Triggers for Data Integrity and Business Rules
-- 触发器 - 确保数据完整性和业务规则
-- ========================================

DELIMITER $$

-- ================================================
-- 1. 订单相关触发器
-- ================================================

-- 订单完成时自动更新客户积分和等级
DROP TRIGGER IF EXISTS trg_after_order_complete$$
CREATE TRIGGER trg_after_order_complete
AFTER UPDATE ON CustomerOrder
FOR EACH ROW
BEGIN
    DECLARE v_points_to_add INT;
    DECLARE v_current_points INT;
    DECLARE v_new_tier_id INT;

    -- 只在订单从非Completed状态变为Completed时触发
    IF NEW.OrderStatus = 'Completed' AND OLD.OrderStatus != 'Completed' AND NEW.CustomerID IS NOT NULL THEN
        -- 计算积分：每消费1元得1积分
        SET v_points_to_add = FLOOR(NEW.TotalAmount);

        -- 更新客户积分
        UPDATE Customer
        SET Points = Points + v_points_to_add
        WHERE CustomerID = NEW.CustomerID;

        -- 获取更新后的积分
        SELECT Points INTO v_current_points
        FROM Customer
        WHERE CustomerID = NEW.CustomerID;

        -- 自动升级会员等级
        SELECT TierID INTO v_new_tier_id
        FROM MembershipTier
        WHERE v_current_points >= MinPoints
        ORDER BY MinPoints DESC
        LIMIT 1;

        IF v_new_tier_id IS NOT NULL THEN
            UPDATE Customer
            SET TierID = v_new_tier_id
            WHERE CustomerID = NEW.CustomerID;
        END IF;
    END IF;
END$$

-- 订单取消时释放库存
DROP TRIGGER IF EXISTS trg_after_order_cancel$$
CREATE TRIGGER trg_after_order_cancel
AFTER UPDATE ON CustomerOrder
FOR EACH ROW
BEGIN
    -- 只在订单从非Cancelled状态变为Cancelled时触发
    IF NEW.OrderStatus = 'Cancelled' AND OLD.OrderStatus != 'Cancelled' THEN
        -- 释放所有Reserved状态的库存
        UPDATE StockItem s
        JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
        SET s.Status = 'Available'
        WHERE ol.OrderID = NEW.OrderID AND s.Status = 'Reserved';
    END IF;
END$$

-- ================================================
-- 2. 库存调拨触发器
-- ================================================

-- 调拨完成时自动更新库存位置
DROP TRIGGER IF EXISTS trg_after_transfer_complete$$
CREATE TRIGGER trg_after_transfer_complete
AFTER UPDATE ON InventoryTransfer
FOR EACH ROW
BEGIN
    -- 只在调拨从非Completed状态变为Completed时触发
    IF NEW.Status = 'Completed' AND OLD.Status != 'Completed' THEN
        UPDATE StockItem
        SET ShopID = NEW.ToShopID,
            Status = 'Available'
        WHERE StockItemID = NEW.StockItemID;
    END IF;
END$$

-- 调拨取消时恢复库存状态
DROP TRIGGER IF EXISTS trg_after_transfer_cancel$$
CREATE TRIGGER trg_after_transfer_cancel
AFTER UPDATE ON InventoryTransfer
FOR EACH ROW
BEGIN
    -- 只在调拨从非Cancelled状态变为Cancelled时触发
    IF NEW.Status = 'Cancelled' AND OLD.Status != 'Cancelled' THEN
        UPDATE StockItem
        SET Status = 'Available'
        WHERE StockItemID = NEW.StockItemID AND Status = 'InTransit';
    END IF;
END$$

-- ================================================
-- 3. 供应商订单触发器
-- ================================================

-- 供应商订单更新时自动计算总成本
DROP TRIGGER IF EXISTS trg_after_supplier_order_line_insert$$
CREATE TRIGGER trg_after_supplier_order_line_insert
AFTER INSERT ON SupplierOrderLine
FOR EACH ROW
BEGIN
    UPDATE SupplierOrder
    SET TotalCost = (
        SELECT SUM(Quantity * UnitCost)
        FROM SupplierOrderLine
        WHERE SupplierOrderID = NEW.SupplierOrderID
    )
    WHERE SupplierOrderID = NEW.SupplierOrderID;
END$$

DROP TRIGGER IF EXISTS trg_after_supplier_order_line_update$$
CREATE TRIGGER trg_after_supplier_order_line_update
AFTER UPDATE ON SupplierOrderLine
FOR EACH ROW
BEGIN
    UPDATE SupplierOrder
    SET TotalCost = (
        SELECT SUM(Quantity * UnitCost)
        FROM SupplierOrderLine
        WHERE SupplierOrderID = NEW.SupplierOrderID
    )
    WHERE SupplierOrderID = NEW.SupplierOrderID;
END$$

DROP TRIGGER IF EXISTS trg_after_supplier_order_line_delete$$
CREATE TRIGGER trg_after_supplier_order_line_delete
AFTER DELETE ON SupplierOrderLine
FOR EACH ROW
BEGIN
    UPDATE SupplierOrder
    SET TotalCost = (
        SELECT COALESCE(SUM(Quantity * UnitCost), 0)
        FROM SupplierOrderLine
        WHERE SupplierOrderID = OLD.SupplierOrderID
    )
    WHERE SupplierOrderID = OLD.SupplierOrderID;
END$$

-- ================================================
-- 4. 回购订单触发器
-- ================================================

-- 回购订单更新时自动计算总支付金额
DROP TRIGGER IF EXISTS trg_after_buyback_order_line_insert$$
CREATE TRIGGER trg_after_buyback_order_line_insert
AFTER INSERT ON BuybackOrderLine
FOR EACH ROW
BEGIN
    UPDATE BuybackOrder
    SET TotalPayment = (
        SELECT SUM(Quantity * UnitPrice)
        FROM BuybackOrderLine
        WHERE BuybackOrderID = NEW.BuybackOrderID
    )
    WHERE BuybackOrderID = NEW.BuybackOrderID;
END$$

DROP TRIGGER IF EXISTS trg_after_buyback_order_line_update$$
CREATE TRIGGER trg_after_buyback_order_line_update
AFTER UPDATE ON BuybackOrderLine
FOR EACH ROW
BEGIN
    UPDATE BuybackOrder
    SET TotalPayment = (
        SELECT SUM(Quantity * UnitPrice)
        FROM BuybackOrderLine
        WHERE BuybackOrderID = NEW.BuybackOrderID
    )
    WHERE BuybackOrderID = NEW.BuybackOrderID;
END$$

DROP TRIGGER IF EXISTS trg_after_buyback_order_line_delete$$
CREATE TRIGGER trg_after_buyback_order_line_delete
AFTER DELETE ON BuybackOrderLine
FOR EACH ROW
BEGIN
    UPDATE BuybackOrder
    SET TotalPayment = (
        SELECT COALESCE(SUM(Quantity * UnitPrice), 0)
        FROM BuybackOrderLine
        WHERE BuybackOrderID = OLD.BuybackOrderID
    )
    WHERE BuybackOrderID = OLD.BuybackOrderID;
END$$

-- ================================================
-- 5. 客户订单总额触发器
-- ================================================

-- 订单行插入时更新订单总额
DROP TRIGGER IF EXISTS trg_after_order_line_insert$$
CREATE TRIGGER trg_after_order_line_insert
AFTER INSERT ON OrderLine
FOR EACH ROW
BEGIN
    UPDATE CustomerOrder
    SET TotalAmount = (
        SELECT SUM(PriceAtSale)
        FROM OrderLine
        WHERE OrderID = NEW.OrderID
    )
    WHERE OrderID = NEW.OrderID;
END$$

DROP TRIGGER IF EXISTS trg_after_order_line_update$$
CREATE TRIGGER trg_after_order_line_update
AFTER UPDATE ON OrderLine
FOR EACH ROW
BEGIN
    UPDATE CustomerOrder
    SET TotalAmount = (
        SELECT SUM(PriceAtSale)
        FROM OrderLine
        WHERE OrderID = NEW.OrderID
    )
    WHERE OrderID = NEW.OrderID;
END$$

DROP TRIGGER IF EXISTS trg_after_order_line_delete$$
CREATE TRIGGER trg_after_order_line_delete
AFTER DELETE ON OrderLine
FOR EACH ROW
BEGIN
    UPDATE CustomerOrder
    SET TotalAmount = (
        SELECT COALESCE(SUM(PriceAtSale), 0)
        FROM OrderLine
        WHERE OrderID = OLD.OrderID
    )
    WHERE OrderID = OLD.OrderID;
END$$

-- ================================================
-- 6. 数据验证触发器
-- ================================================

-- 防止修改已完成订单的订单行
DROP TRIGGER IF EXISTS trg_before_order_line_update$$
CREATE TRIGGER trg_before_order_line_update
BEFORE UPDATE ON OrderLine
FOR EACH ROW
BEGIN
    DECLARE v_order_status VARCHAR(20);

    SELECT OrderStatus INTO v_order_status
    FROM CustomerOrder
    WHERE OrderID = OLD.OrderID;

    IF v_order_status IN ('Completed', 'Shipped') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot modify completed or shipped orders';
    END IF;
END$$

-- 防止删除已完成订单的订单行
DROP TRIGGER IF EXISTS trg_before_order_line_delete$$
CREATE TRIGGER trg_before_order_line_delete
BEFORE DELETE ON OrderLine
FOR EACH ROW
BEGIN
    DECLARE v_order_status VARCHAR(20);

    SELECT OrderStatus INTO v_order_status
    FROM CustomerOrder
    WHERE OrderID = OLD.OrderID;

    IF v_order_status IN ('Completed', 'Shipped') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot delete items from completed or shipped orders';
    END IF;
END$$

-- 防止调拨不存在或不可用的库存
DROP TRIGGER IF EXISTS trg_before_transfer_insert$$
CREATE TRIGGER trg_before_transfer_insert
BEFORE INSERT ON InventoryTransfer
FOR EACH ROW
BEGIN
    DECLARE v_stock_status VARCHAR(20);
    DECLARE v_stock_shop INT;

    SELECT Status, ShopID INTO v_stock_status, v_stock_shop
    FROM StockItem
    WHERE StockItemID = NEW.StockItemID;

    IF v_stock_status != 'Available' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Can only transfer available stock items';
    END IF;

    IF v_stock_shop != NEW.FromShopID THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Stock item is not in the source shop';
    END IF;

    IF NEW.FromShopID = NEW.ToShopID THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Source and destination shops cannot be the same';
    END IF;
END$$

-- 库存状态变更时记录售出日期
DROP TRIGGER IF EXISTS trg_before_stock_status_update$$
CREATE TRIGGER trg_before_stock_status_update
BEFORE UPDATE ON StockItem
FOR EACH ROW
BEGIN
    -- 当状态从非Sold变为Sold时，记录售出日期
    IF NEW.Status = 'Sold' AND OLD.Status != 'Sold' THEN
        SET NEW.DateSold = NOW();
    END IF;

    -- 当状态从Sold变回其他状态时，清空售出日期
    IF NEW.Status != 'Sold' AND OLD.Status = 'Sold' THEN
        SET NEW.DateSold = NULL;
    END IF;
END$$

-- ================================================
-- 7. 多态外键验证触发器
-- ================================================

-- 验证 StockItem 的 SourceType 和 SourceOrderID 一致性
DROP TRIGGER IF EXISTS trg_before_stock_item_insert$$
CREATE TRIGGER trg_before_stock_item_insert
BEFORE INSERT ON StockItem
FOR EACH ROW
BEGIN
    DECLARE v_exists INT;

    -- 验证 SourceOrderID 不能为空
    IF NEW.SourceOrderID IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'SourceOrderID cannot be NULL';
    END IF;

    -- 根据 SourceType 验证对应订单是否存在
    IF NEW.SourceType = 'Supplier' THEN
        SELECT COUNT(*) INTO v_exists
        FROM SupplierOrder
        WHERE SupplierOrderID = NEW.SourceOrderID;

        IF v_exists = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid SourceOrderID: SupplierOrder does not exist';
        END IF;

        -- 验证订单状态必须是 Received（确保已收货才能生成库存）
        SELECT COUNT(*) INTO v_exists
        FROM SupplierOrder
        WHERE SupplierOrderID = NEW.SourceOrderID AND Status = 'Received';

        IF v_exists = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'SupplierOrder must be Received before creating stock';
        END IF;

    ELSEIF NEW.SourceType = 'Buyback' THEN
        SELECT COUNT(*) INTO v_exists
        FROM BuybackOrder
        WHERE BuybackOrderID = NEW.SourceOrderID;

        IF v_exists = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid SourceOrderID: BuybackOrder does not exist';
        END IF;

        -- 验证订单状态必须是 Completed
        SELECT COUNT(*) INTO v_exists
        FROM BuybackOrder
        WHERE BuybackOrderID = NEW.SourceOrderID AND Status = 'Completed';

        IF v_exists = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'BuybackOrder must be Completed before creating stock';
        END IF;

    ELSE
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid SourceType';
    END IF;
END$$

-- UPDATE 时也验证（防止修改为无效值）
DROP TRIGGER IF EXISTS trg_before_stock_item_update$$
CREATE TRIGGER trg_before_stock_item_update
BEFORE UPDATE ON StockItem
FOR EACH ROW
BEGIN
    DECLARE v_exists INT;

    -- 只在 SourceType 或 SourceOrderID 发生变化时验证
    IF NEW.SourceType != OLD.SourceType OR NEW.SourceOrderID != OLD.SourceOrderID THEN

        IF NEW.SourceOrderID IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'SourceOrderID cannot be NULL';
        END IF;

        IF NEW.SourceType = 'Supplier' THEN
            SELECT COUNT(*) INTO v_exists
            FROM SupplierOrder
            WHERE SupplierOrderID = NEW.SourceOrderID;

            IF v_exists = 0 THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Invalid SourceOrderID: SupplierOrder does not exist';
            END IF;

        ELSEIF NEW.SourceType = 'Buyback' THEN
            SELECT COUNT(*) INTO v_exists
            FROM BuybackOrder
            WHERE BuybackOrderID = NEW.SourceOrderID;

            IF v_exists = 0 THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Invalid SourceOrderID: BuybackOrder does not exist';
            END IF;
        END IF;

    END IF;
END$$

-- ================================================
-- 8. 生日优惠触发器（可选）
-- ================================================

-- 在客户生日月份下单时自动增加额外积分
DROP TRIGGER IF EXISTS trg_birthday_bonus$$
CREATE TRIGGER trg_birthday_bonus
AFTER UPDATE ON CustomerOrder
FOR EACH ROW
BEGIN
    DECLARE v_birth_month INT;
    DECLARE v_current_month INT;
    DECLARE v_bonus_points INT;

    IF NEW.OrderStatus = 'Completed' AND OLD.OrderStatus != 'Completed' AND NEW.CustomerID IS NOT NULL THEN
        -- 获取客户生日月份
        SELECT MONTH(Birthday) INTO v_birth_month
        FROM Customer
        WHERE CustomerID = NEW.CustomerID;

        -- 获取当前月份
        SET v_current_month = MONTH(NEW.OrderDate);

        -- 如果是生日月份，额外奖励积分
        IF v_birth_month = v_current_month THEN
            SET v_bonus_points = FLOOR(NEW.TotalAmount * 0.2); -- 额外20%积分
            UPDATE Customer
            SET Points = Points + v_bonus_points
            WHERE CustomerID = NEW.CustomerID;
        END IF;
    END IF;
END$$

-- ================================================
-- 9. 回购订单积分触发器
-- 回购订单完成时自动赠送积分并升级会员等级
-- 与客户订单触发器(trg_after_order_complete)保持一致的积分处理逻辑
-- ================================================

DROP TRIGGER IF EXISTS trg_after_buyback_complete$$
CREATE TRIGGER trg_after_buyback_complete
AFTER INSERT ON BuybackOrder
FOR EACH ROW
BEGIN
    DECLARE v_points_to_add INT;
    DECLARE v_current_points INT;
    DECLARE v_new_tier_id INT;

    -- 只有已完成的回购订单且有关联客户时才处理积分
    IF NEW.Status = 'Completed' AND NEW.CustomerID IS NOT NULL THEN
        -- 计算积分：每回购1元得0.5积分（与存储过程逻辑一致）
        SET v_points_to_add = FLOOR(NEW.TotalPayment * 0.5);

        IF v_points_to_add > 0 THEN
            -- 更新客户积分
            UPDATE Customer
            SET Points = Points + v_points_to_add
            WHERE CustomerID = NEW.CustomerID;

            -- 获取更新后的积分
            SELECT Points INTO v_current_points
            FROM Customer
            WHERE CustomerID = NEW.CustomerID;

            -- 自动升级会员等级
            SELECT TierID INTO v_new_tier_id
            FROM MembershipTier
            WHERE v_current_points >= MinPoints
            ORDER BY MinPoints DESC
            LIMIT 1;

            IF v_new_tier_id IS NOT NULL THEN
                UPDATE Customer
                SET TierID = v_new_tier_id
                WHERE CustomerID = NEW.CustomerID;
            END IF;
        END IF;
    END IF;
END$$

DELIMITER ;
