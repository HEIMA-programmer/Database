-- ========================================
-- 修复 2: 订单完成流程不一致问题
-- ========================================
-- 需要修改的PHP文件:
-- 1. public/customer/pay.php
-- 2. public/staff/pickup.php
-- 3. public/staff/fulfillment.php
--
-- 此SQL文件提供辅助函数用于统一订单完成流程

DELIMITER $$

-- 创建辅助函数：安全地完成订单（带完整验证）
DROP PROCEDURE IF EXISTS sp_safe_complete_order$$
CREATE PROCEDURE sp_safe_complete_order(
    IN p_order_id INT,
    IN p_customer_id INT,  -- 用于验证
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_order_customer_id INT;
    DECLARE v_order_status VARCHAR(20);
    DECLARE v_total_amount DECIMAL(10,2);
    DECLARE v_reserved_count INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Transaction failed';
    END;

    START TRANSACTION;

    -- 验证订单所有权和状态
    SELECT CustomerID, OrderStatus, TotalAmount
    INTO v_order_customer_id, v_order_status, v_total_amount
    FROM CustomerOrder
    WHERE OrderID = p_order_id
    FOR UPDATE;

    IF v_order_customer_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Order not found';
        ROLLBACK;
    ELSEIF v_order_customer_id != p_customer_id THEN
        SET p_success = FALSE;
        SET p_message = 'Order does not belong to this customer';
        ROLLBACK;
    ELSEIF v_order_status IN ('Completed', 'Cancelled') THEN
        SET p_success = FALSE;
        SET p_message = CONCAT('Order already ', v_order_status);
        ROLLBACK;
    ELSE
        -- 验证库存仍然Reserved
        SELECT COUNT(*) INTO v_reserved_count
        FROM OrderLine ol
        JOIN StockItem s ON ol.StockItemID = s.StockItemID
        WHERE ol.OrderID = p_order_id AND s.Status = 'Reserved';

        IF v_reserved_count = 0 THEN
            SET p_success = FALSE;
            SET p_message = 'Order items no longer reserved';
            ROLLBACK;
        ELSE
            -- 调用标准完成流程
            CALL sp_complete_order(p_order_id, FLOOR(v_total_amount));
            SET p_success = TRUE;
            SET p_message = 'Order completed successfully';
            COMMIT;
        END IF;
    END IF;
END$$

DELIMITER ;

SELECT '修复完成: 创建了 sp_safe_complete_order 用于统一订单完成流程' AS Status;
