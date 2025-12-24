-- ========================================
-- 修复 1: 积分重复增加问题
-- ========================================
-- 问题: sp_complete_order 和 trg_after_order_complete 都在增加积分
-- 解决方案: 删除存储过程中的积分逻辑,只保留触发器中的实现

DELIMITER $$

-- 重新创建 sp_complete_order，移除积分更新逻辑
DROP PROCEDURE IF EXISTS sp_complete_order$$
CREATE PROCEDURE sp_complete_order(
    IN p_order_id INT,
    IN p_points_earned INT  -- 保留参数但不使用，保持接口兼容性
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

    -- 更新订单状态（积分更新由触发器自动处理）
    UPDATE CustomerOrder
    SET OrderStatus = 'Completed',
        TotalAmount = v_total_amount
    WHERE OrderID = p_order_id;

    -- 更新库存状态为已售出
    UPDATE StockItem s
    JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
    SET s.Status = 'Sold', s.DateSold = NOW()
    WHERE ol.OrderID = p_order_id;

    -- 注意: 积分增加和会员升级现在完全由触发器处理
    -- trg_after_order_complete 会自动执行

    COMMIT;
END$$

DELIMITER ;

-- 验证修复
SELECT '修复完成: sp_complete_order 不再手动更新积分，由触发器自动处理' AS Status;
