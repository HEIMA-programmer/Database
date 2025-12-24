-- ========================================
-- 修复 4: 多态外键数据完整性验证
-- ========================================
-- 问题: StockItem.SourceOrderID 没有真实的外键约束
-- 解决方案: 添加触发器验证 SourceType 和 SourceOrderID 的一致性

DELIMITER $$

-- INSERT 时验证
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

        -- 可选: 验证订单状态必须是 Received
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

        -- 可选: 验证订单状态必须是 Completed
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

-- UPDATE 时验证（防止修改为无效值）
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

DELIMITER ;

SELECT '修复完成: 添加了多态外键验证触发器' AS Status;
