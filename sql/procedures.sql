-- ========================================
-- Stored Procedures for Business Logic
-- 存储过程 - 封装业务流程并确保事务一致性
-- ========================================

DELIMITER $$

-- ================================================
-- 1. 供应商进货流程
-- ================================================

-- 创建供应商订单
-- 【修复】移除内部事务控制，由调用方管理事务
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
        SET p_order_id = -1;
        RESIGNAL;
    END;

    INSERT INTO SupplierOrder (SupplierID, CreatedByEmployeeID, DestinationShopID)
    VALUES (p_supplier_id, p_employee_id, p_destination_shop_id);

    SET p_order_id = LAST_INSERT_ID();
END$$

-- 添加订单行项目
-- 【修复】移除内部事务控制，由调用方管理事务
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
        RESIGNAL;
    END;

    INSERT INTO SupplierOrderLine (SupplierOrderID, ReleaseID, Quantity, UnitCost)
    VALUES (p_order_id, p_release_id, p_quantity, p_unit_cost);
END$$

-- 接收供应商订单并生成库存
-- 【修复】移除内部事务控制，由调用方管理事务
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
        RESIGNAL;
    END;

    -- 检查订单状态
    SELECT Status, DestinationShopID INTO v_status, v_shop_id
    FROM SupplierOrder
    WHERE SupplierOrderID = p_order_id;

    IF v_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order is not in Pending status';
    END IF;

    -- 【修复】先更新订单状态为Received，避免触发器循环依赖
    -- 触发器 trg_before_stock_item_insert 要求订单状态为 Received
    UPDATE SupplierOrder
    SET Status = 'Received', ReceivedDate = NOW()
    WHERE SupplierOrderID = p_order_id;

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

    -- 计算总成本
    UPDATE SupplierOrder so
    SET TotalCost = (
        SELECT SUM(Quantity * UnitCost)
        FROM SupplierOrderLine
        WHERE SupplierOrderID = p_order_id
    )
    WHERE SupplierOrderID = p_order_id;
END$$

-- ================================================
-- 2. 客户回购流程
-- ================================================

-- 创建回购订单并生成库存
-- 【修复】移除内部事务控制，由调用方管理事务
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
        SET p_buyback_id = -1;
        RESIGNAL;
    END;

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
END$$

-- ================================================
-- 3. 库存调拨流程
-- ================================================

-- 发起库存调拨
-- 【修复】移除内部事务控制，由调用方管理事务
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
        SET p_transfer_id = -1;
        RESIGNAL;
    END;

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
END$$

-- 完成库存调拨
-- 【修复】移除内部事务控制，由调用方管理事务
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
        RESIGNAL;
    END;

    -- 获取调拨信息
    SELECT StockItemID, ToShopID, Status
    INTO v_stock_item_id, v_to_shop_id, v_status
    FROM InventoryTransfer
    WHERE TransferID = p_transfer_id
    FOR UPDATE;

    IF v_status != 'InTransit' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transfer is not in transit';
    END IF;

    -- 更新调拨状态（触发器 trg_after_transfer_complete 会自动更新库存位置和状态）
    UPDATE InventoryTransfer
    SET Status = 'Completed',
        ReceivedByEmployeeID = p_received_by_employee_id,
        ReceivedDate = NOW()
    WHERE TransferID = p_transfer_id;

    -- 库存位置和状态的更新由触发器 trg_after_transfer_complete 自动处理
END$$

-- ================================================
-- 4. 销售流程
-- ================================================

-- 创建客户订单（在线/店内）
-- 【修复】移除内部事务控制，由调用方管理事务
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
        SET p_order_id = -1;
        RESIGNAL;
    END;

    INSERT INTO CustomerOrder (
        CustomerID, FulfilledByShopID, ProcessedByEmployeeID,
        OrderType, OrderStatus
    ) VALUES (
        p_customer_id, p_shop_id, p_employee_id,
        p_order_type, 'Pending'
    );

    SET p_order_id = LAST_INSERT_ID();
END$$

-- 添加订单商品
-- 【修复】移除内部事务控制，由调用方管理事务
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
        RESIGNAL;
    END;

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
END$$

-- 完成订单（支付成功）
-- 【修复】移除内部事务控制，由调用方管理事务
DROP PROCEDURE IF EXISTS sp_complete_order$$
-- 【注意】积分由触发器 trg_after_order_complete 自动计算
CREATE PROCEDURE sp_complete_order(
    IN p_order_id INT
)
BEGIN
    DECLARE v_customer_id INT;
    DECLARE v_total_amount DECIMAL(10,2);
    DECLARE v_order_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    -- 获取订单信息
    SELECT CustomerID, OrderStatus INTO v_customer_id, v_order_status
    FROM CustomerOrder
    WHERE OrderID = p_order_id
    FOR UPDATE;

    -- 【修复】支持 Shipped 状态的订单完成（在线订单发货后确认送达）
    IF v_order_status NOT IN ('Pending', 'Paid', 'Shipped') THEN
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

    -- 注意: 积分更新和会员升级由触发器 trg_after_order_complete 自动处理
END$$

-- 取消订单
-- 【修复】移除内部事务控制，由调用方管理事务
DROP PROCEDURE IF EXISTS sp_cancel_order$$
CREATE PROCEDURE sp_cancel_order(
    IN p_order_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    -- 释放预留库存
    UPDATE StockItem s
    JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
    SET s.Status = 'Available'
    WHERE ol.OrderID = p_order_id AND s.Status = 'Reserved';

    -- 更新订单状态
    UPDATE CustomerOrder
    SET OrderStatus = 'Cancelled'
    WHERE OrderID = p_order_id;
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
-- 6. 库存超时释放机制
-- ================================================

-- 释放过期的预留库存（超过30分钟未支付）
DROP PROCEDURE IF EXISTS sp_release_expired_reservations$$
CREATE PROCEDURE sp_release_expired_reservations()
BEGIN
    DECLARE v_affected_orders INT DEFAULT 0;
    DECLARE v_released_items INT DEFAULT 0;

    START TRANSACTION;

    -- 统计受影响的订单数
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

    -- 返回统计信息
    SELECT
        v_affected_orders AS ExpiredOrders,
        v_released_items AS ReleasedItems,
        NOW() AS ProcessedAt;
END$$

-- ================================================
-- 7. 会员等级自动升级
-- ================================================

-- 【修复】移除内部事务控制，由调用方管理事务
DROP PROCEDURE IF EXISTS sp_update_customer_tier$$
CREATE PROCEDURE sp_update_customer_tier(
    IN p_customer_id INT
)
BEGIN
    DECLARE v_points INT;
    DECLARE v_new_tier_id INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

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
END$$

DELIMITER ;

-- ================================================
-- 定时事件：自动释放过期预留库存
-- ================================================
-- 注意：需要确保 MySQL 的 event_scheduler 已开启
-- 执行：SET GLOBAL event_scheduler = ON;

DROP EVENT IF EXISTS evt_release_expired_reservations;

CREATE EVENT evt_release_expired_reservations
ON SCHEDULE EVERY 15 MINUTE
STARTS CURRENT_TIMESTAMP
DO
    CALL sp_release_expired_reservations();

-- ================================================
-- 【架构重构】新增存储过程 - 消除 PHP 直接写物理表
-- ================================================

DELIMITER $$

-- ------------------------------------------------
-- 8. 客户注册存储过程
-- 替换 register.php 中的直接 INSERT
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_register_customer$$
CREATE PROCEDURE sp_register_customer(
    IN p_name VARCHAR(100),
    IN p_email VARCHAR(100),
    IN p_password_hash VARCHAR(255),
    IN p_birthday DATE,
    OUT p_customer_id INT,
    OUT p_tier_id INT
)
BEGIN
    DECLARE v_email_exists INT DEFAULT 0;
    DECLARE v_default_tier_id INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_customer_id = -1;
        SET p_tier_id = -1;
        RESIGNAL;
    END;

    -- 检查邮箱是否已存在
    SELECT COUNT(*) INTO v_email_exists FROM Customer WHERE Email = p_email;

    IF v_email_exists > 0 THEN
        SET p_customer_id = -2; -- 表示邮箱已存在
        SET p_tier_id = -1;
    ELSE
        -- 获取默认等级（积分最低的等级）
        SELECT TierID INTO v_default_tier_id
        FROM MembershipTier
        ORDER BY MinPoints ASC
        LIMIT 1;

        -- 创建新客户
        INSERT INTO Customer (TierID, Name, Email, PasswordHash, Birthday, Points)
        VALUES (v_default_tier_id, p_name, p_email, p_password_hash, p_birthday, 0);

        SET p_customer_id = LAST_INSERT_ID();
        SET p_tier_id = v_default_tier_id;
    END IF;
END$$

-- ------------------------------------------------
-- 9. 更新客户资料存储过程
-- 替换 profile.php 中的直接 UPDATE
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_update_customer_profile$$
CREATE PROCEDURE sp_update_customer_profile(
    IN p_customer_id INT,
    IN p_name VARCHAR(100),
    IN p_password_hash VARCHAR(255) -- NULL 表示不更新密码
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    IF p_password_hash IS NOT NULL AND p_password_hash != '' THEN
        UPDATE Customer
        SET Name = p_name, PasswordHash = p_password_hash
        WHERE CustomerID = p_customer_id;
    ELSE
        UPDATE Customer
        SET Name = p_name
        WHERE CustomerID = p_customer_id;
    END IF;
END$$

-- ------------------------------------------------
-- 10. 添加员工存储过程
-- 替换 users.php 中的直接 INSERT
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_employee$$
CREATE PROCEDURE sp_add_employee(
    IN p_name VARCHAR(100),
    IN p_username VARCHAR(50),
    IN p_password_hash VARCHAR(255),
    IN p_role ENUM('Admin', 'Manager', 'Staff'),
    IN p_shop_id INT,
    OUT p_employee_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_employee_id = -1;
        RESIGNAL;
    END;

    INSERT INTO Employee (Name, Username, PasswordHash, Role, ShopID, HireDate)
    VALUES (p_name, p_username, p_password_hash, p_role, p_shop_id, CURDATE());

    SET p_employee_id = LAST_INSERT_ID();
END$$

-- ------------------------------------------------
-- 11. 更新员工存储过程
-- 替换 users.php 中的直接 UPDATE
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_update_employee$$
CREATE PROCEDURE sp_update_employee(
    IN p_employee_id INT,
    IN p_name VARCHAR(100),
    IN p_role ENUM('Admin', 'Manager', 'Staff'),
    IN p_shop_id INT,
    IN p_password_hash VARCHAR(255) -- NULL 表示不更新密码
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    IF p_password_hash IS NOT NULL AND p_password_hash != '' THEN
        UPDATE Employee
        SET Name = p_name, Role = p_role, ShopID = p_shop_id, PasswordHash = p_password_hash
        WHERE EmployeeID = p_employee_id;
    ELSE
        UPDATE Employee
        SET Name = p_name, Role = p_role, ShopID = p_shop_id
        WHERE EmployeeID = p_employee_id;
    END IF;
END$$

-- ------------------------------------------------
-- 12. 删除员工存储过程
-- 替换 users.php 中的直接 DELETE
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_delete_employee$$
CREATE PROCEDURE sp_delete_employee(
    IN p_employee_id INT,
    IN p_current_user_id INT -- 防止删除自己
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    IF p_employee_id = p_current_user_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete your own account';
    END IF;

    DELETE FROM Employee WHERE EmployeeID = p_employee_id;
END$$

-- ------------------------------------------------
-- 13. 添加供应商存储过程
-- 替换 suppliers.php 中的直接 INSERT
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_supplier$$
CREATE PROCEDURE sp_add_supplier(
    IN p_name VARCHAR(100),
    IN p_email VARCHAR(100),
    OUT p_supplier_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_supplier_id = -1;
        RESIGNAL;
    END;

    INSERT INTO Supplier (Name, Email)
    VALUES (p_name, p_email);

    SET p_supplier_id = LAST_INSERT_ID();
END$$

-- ------------------------------------------------
-- 14. 更新供应商存储过程
-- 替换 suppliers.php 中的直接 UPDATE
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_update_supplier$$
CREATE PROCEDURE sp_update_supplier(
    IN p_supplier_id INT,
    IN p_name VARCHAR(100),
    IN p_email VARCHAR(100)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    UPDATE Supplier
    SET Name = p_name, Email = p_email
    WHERE SupplierID = p_supplier_id;
END$$

-- ------------------------------------------------
-- 15. 删除供应商存储过程（带依赖检查）
-- 替换 suppliers.php 中的直接 DELETE
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_delete_supplier$$
CREATE PROCEDURE sp_delete_supplier(
    IN p_supplier_id INT,
    OUT p_result INT -- 1=成功, -1=有依赖不能删除
)
BEGIN
    DECLARE v_order_count INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_result = 0;
        RESIGNAL;
    END;

    -- 检查是否有关联的供应商订单
    SELECT COUNT(*) INTO v_order_count
    FROM SupplierOrder
    WHERE SupplierID = p_supplier_id;

    IF v_order_count > 0 THEN
        SET p_result = -1;
    ELSE
        DELETE FROM Supplier WHERE SupplierID = p_supplier_id;
        SET p_result = 1;
    END IF;
END$$

-- ------------------------------------------------
-- 16. 添加专辑存储过程
-- 替换 products.php 中的直接 INSERT
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_release$$
CREATE PROCEDURE sp_add_release(
    IN p_title VARCHAR(255),
    IN p_artist VARCHAR(255),
    IN p_label VARCHAR(255),
    IN p_year INT,
    IN p_genre VARCHAR(50),
    IN p_description TEXT,
    OUT p_release_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_release_id = -1;
        RESIGNAL;
    END;

    INSERT INTO ReleaseAlbum (Title, ArtistName, LabelName, ReleaseYear, Genre, Format, Description)
    VALUES (p_title, p_artist, p_label, p_year, p_genre, 'Vinyl', p_description);

    SET p_release_id = LAST_INSERT_ID();
END$$

-- ------------------------------------------------
-- 17. 更新专辑存储过程
-- 替换 products.php 中的直接 UPDATE
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_update_release$$
CREATE PROCEDURE sp_update_release(
    IN p_release_id INT,
    IN p_title VARCHAR(255),
    IN p_artist VARCHAR(255),
    IN p_label VARCHAR(255),
    IN p_year INT,
    IN p_genre VARCHAR(50),
    IN p_description TEXT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    UPDATE ReleaseAlbum
    SET Title = p_title,
        ArtistName = p_artist,
        LabelName = p_label,
        ReleaseYear = p_year,
        Genre = p_genre,
        Description = p_description
    WHERE ReleaseID = p_release_id;
END$$

-- ------------------------------------------------
-- 18. 发货订单存储过程
-- 替换 fulfillment.php 中的直接 UPDATE
-- 【修复】移除内部事务控制，由调用方管理事务
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_ship_order$$
CREATE PROCEDURE sp_ship_order(
    IN p_order_id INT,
    OUT p_result INT -- 1=成功, 0=失败
)
BEGIN
    DECLARE v_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_result = 0;
        RESIGNAL;
    END;

    -- 验证订单状态
    SELECT OrderStatus INTO v_status
    FROM CustomerOrder
    WHERE OrderID = p_order_id AND OrderType = 'Online'
    FOR UPDATE;

    IF v_status = 'Paid' THEN
        UPDATE CustomerOrder
        SET OrderStatus = 'Shipped'
        WHERE OrderID = p_order_id;
        SET p_result = 1;
    ELSE
        SET p_result = 0;
    END IF;
END$$

DELIMITER ;
