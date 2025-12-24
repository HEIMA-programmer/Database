-- ========================================
-- 修复 8: 库存超时释放机制
-- ========================================
-- 问题: Reserved 库存如果用户不支付会一直被占用
-- 解决方案: 添加定时任务自动释放超过30分钟的预留库存

DELIMITER $$

-- 创建存储过程用于释放过期预留
DROP PROCEDURE IF EXISTS sp_release_expired_reservations$$
CREATE PROCEDURE sp_release_expired_reservations()
BEGIN
    DECLARE v_affected_orders INT;
    DECLARE v_released_items INT;

    START TRANSACTION;

    -- 记录受影响的订单数
    SELECT COUNT(DISTINCT co.OrderID) INTO v_affected_orders
    FROM CustomerOrder co
    WHERE co.OrderStatus = 'Pending'
      AND co.OrderDate < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

    -- 释放预留库存
    UPDATE StockItem s
    JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
    JOIN CustomerOrder co ON ol.OrderID = co.OrderID
    SET s.Status = 'Available'
    WHERE co.OrderStatus = 'Pending'
      AND s.Status = 'Reserved'
      AND co.OrderDate < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

    SET v_released_items = ROW_COUNT();

    -- 自动取消过期订单
    UPDATE CustomerOrder
    SET OrderStatus = 'Cancelled'
    WHERE OrderStatus = 'Pending'
      AND OrderDate < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

    COMMIT;

    -- 记录日志
    SELECT CONCAT('Released ', v_released_items, ' items from ', v_affected_orders, ' expired orders') AS Result;
END$$

DELIMITER ;

-- 创建定时事件（每15分钟执行一次）
-- 注意: 需要确保 MySQL 的 event_scheduler 已开启
-- SET GLOBAL event_scheduler = ON;

DROP EVENT IF EXISTS evt_release_expired_reservations;

CREATE EVENT evt_release_expired_reservations
ON SCHEDULE EVERY 15 MINUTE
STARTS CURRENT_TIMESTAMP
DO
    CALL sp_release_expired_reservations();

-- 验证事件是否创建成功
SHOW EVENTS LIKE 'evt_release_expired_reservations';

SELECT '修复完成: 添加了自动释放过期预留库存的定时任务' AS Status;
SELECT '注意: 请确保 event_scheduler 已开启: SET GLOBAL event_scheduler = ON;' AS Note;
