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
-- 【修复】添加ConditionGrade和SalePrice参数
DROP PROCEDURE IF EXISTS sp_add_supplier_order_line$$
CREATE PROCEDURE sp_add_supplier_order_line(
    IN p_order_id INT,
    IN p_release_id INT,
    IN p_quantity INT,
    IN p_unit_cost DECIMAL(10,2),
    IN p_condition_grade VARCHAR(10),
    IN p_sale_price DECIMAL(10,2)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    INSERT INTO SupplierOrderLine (SupplierOrderID, ReleaseID, Quantity, UnitCost, ConditionGrade, SalePrice)
    VALUES (p_order_id, p_release_id, p_quantity, p_unit_cost, COALESCE(p_condition_grade, 'New'), p_sale_price);
END$$

-- 接收供应商订单并生成库存
-- 【修复】使用订单行中保存的ConditionGrade和SalePrice
DROP PROCEDURE IF EXISTS sp_receive_supplier_order$$
CREATE PROCEDURE sp_receive_supplier_order(
    IN p_order_id INT,
    IN p_batch_no VARCHAR(50),
    IN p_condition_grade VARCHAR(10), -- 保留参数用于兼容，但优先使用订单行中的值
    IN p_markup_rate DECIMAL(3,2) -- 加价率,例如 0.50 表示成本价的150%
)
BEGIN
    DECLARE v_release_id INT;
    DECLARE v_quantity INT;
    DECLARE v_unit_cost DECIMAL(10,2);
    DECLARE v_line_condition VARCHAR(10);
    DECLARE v_line_sale_price DECIMAL(10,2);
    DECLARE v_unit_price DECIMAL(10,2);
    DECLARE v_shop_id INT;
    DECLARE v_counter INT;
    DECLARE v_status VARCHAR(20);
    DECLARE done INT DEFAULT FALSE;

    DECLARE cur CURSOR FOR
        SELECT ReleaseID, Quantity, UnitCost, ConditionGrade, SalePrice
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
        FETCH cur INTO v_release_id, v_quantity, v_unit_cost, v_line_condition, v_line_sale_price;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- 使用订单行中的SalePrice，如果没有则用加价率计算
        IF v_line_sale_price IS NOT NULL AND v_line_sale_price > 0 THEN
            SET v_unit_price = v_line_sale_price;
        ELSE
            SET v_unit_price = v_unit_cost * (1 + p_markup_rate);
        END IF;

        -- 使用订单行中的ConditionGrade，如果没有则使用参数值
        IF v_line_condition IS NULL OR v_line_condition = '' THEN
            SET v_line_condition = COALESCE(p_condition_grade, 'New');
        END IF;

        -- 为每个数量创建独立的StockItem
        SET v_counter = 0;
        WHILE v_counter < v_quantity DO
            INSERT INTO StockItem (
                ReleaseID, ShopID, SourceType, SourceOrderID,
                BatchNo, ConditionGrade, Status, UnitPrice
            ) VALUES (
                v_release_id, v_shop_id, 'Supplier', p_order_id,
                p_batch_no, v_line_condition, 'Available', v_unit_price
            );
            SET v_counter = v_counter + 1;
        END WHILE;

        -- 【新增】采购时同步更新所有同Release、同Condition的现有库存价格
        -- 确保价格一致性：新采购的售价会更新到所有现有的同类库存
        UPDATE StockItem
        SET UnitPrice = v_unit_price
        WHERE ReleaseID = v_release_id
          AND ConditionGrade = v_line_condition
          AND Status = 'Available'
          AND UnitPrice != v_unit_price;
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
-- 修复2: sp_process_buyback - 添加回购积分逻辑
-- 回购给客户积分：每回购1元得0.5积分（可调整）
-- ================================================
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
    DECLARE v_total_payment DECIMAL(10,2);
    DECLARE v_points_earned INT;
    DECLARE v_current_points INT;
    DECLARE v_new_tier_id INT;
    DECLARE v_existing_price DECIMAL(10,2);
    DECLARE v_final_resale_price DECIMAL(10,2);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_buyback_id = -1;
        RESIGNAL;
    END;

    -- 【新增】检查是否有现有库存的价格，优先使用现有价格确保一致性
    SELECT MAX(UnitPrice) INTO v_existing_price
    FROM StockItem
    WHERE ReleaseID = p_release_id
      AND ConditionGrade = p_condition_grade
      AND Status = 'Available';

    -- 如果有现有价格则使用现有价格，否则使用传入的resale价格
    IF v_existing_price IS NOT NULL AND v_existing_price > 0 THEN
        SET v_final_resale_price = v_existing_price;
    ELSE
        SET v_final_resale_price = p_resale_price;
    END IF;

    -- 计算总支付金额
    SET v_total_payment = p_quantity * p_unit_price;

    -- 创建回购订单
    INSERT INTO BuybackOrder (CustomerID, ProcessedByEmployeeID, ShopID, Status, TotalPayment)
    VALUES (p_customer_id, p_employee_id, p_shop_id, 'Completed', v_total_payment);

    SET p_buyback_id = LAST_INSERT_ID();

    -- 添加订单行
    INSERT INTO BuybackOrderLine (BuybackOrderID, ReleaseID, Quantity, UnitPrice, ConditionGrade)
    VALUES (p_buyback_id, p_release_id, p_quantity, p_unit_price, p_condition_grade);

    -- 生成批次号
    SET v_batch_no = CONCAT('BUY-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', p_buyback_id);

    -- 生成StockItem（使用一致的价格）
    WHILE v_counter < p_quantity DO
        INSERT INTO StockItem (
            ReleaseID, ShopID, SourceType, SourceOrderID,
            BatchNo, ConditionGrade, Status, UnitPrice
        ) VALUES (
            p_release_id, p_shop_id, 'Buyback', p_buyback_id,
            v_batch_no, p_condition_grade, 'Available', v_final_resale_price
        );
        SET v_counter = v_counter + 1;
    END WHILE;

    -- 【新增】给回购客户增加积分（每回购1元得0.5积分）
    IF p_customer_id IS NOT NULL THEN
        SET v_points_earned = FLOOR(v_total_payment * 0.5);
        
        IF v_points_earned > 0 THEN
            -- 更新客户积分
            UPDATE Customer
            SET Points = Points + v_points_earned
            WHERE CustomerID = p_customer_id;

            -- 获取更新后的积分
            SELECT Points INTO v_current_points
            FROM Customer
            WHERE CustomerID = p_customer_id;

            -- 自动升级会员等级
            SELECT TierID INTO v_new_tier_id
            FROM MembershipTier
            WHERE v_current_points >= MinPoints
            ORDER BY MinPoints DESC
            LIMIT 1;

            IF v_new_tier_id IS NOT NULL THEN
                UPDATE Customer
                SET TierID = v_new_tier_id
                WHERE CustomerID = p_customer_id;
            END IF;
        END IF;
    END IF;
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


-- 支付订单（仅更新状态为 Paid，不完成订单）
-- 【修复】移除TotalAmount重算，避免覆盖包含运费的金额
DROP PROCEDURE IF EXISTS sp_pay_order$$
CREATE PROCEDURE sp_pay_order(
    IN p_order_id INT
)
BEGIN
    DECLARE v_order_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    -- 获取订单信息
    SELECT OrderStatus INTO v_order_status
    FROM CustomerOrder
    WHERE OrderID = p_order_id
    FOR UPDATE;

    IF v_order_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order is not in Pending status';
    END IF;

    -- 更新订单状态为 Paid（TotalAmount已在创建订单时正确设置，包含运费）
    UPDATE CustomerOrder
    SET OrderStatus = 'Paid'
    WHERE OrderID = p_order_id;

    -- 库存保持 Reserved 状态，等待发货或取货后再改为 Sold
END$$



-- ================================================
-- 修复1: sp_complete_order - 支持门店直接完成订单
-- 【修复】移除TotalAmount重算，避免覆盖包含运费的金额
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

    -- 更新订单状态（TotalAmount已在创建订单时正确设置，包含运费）
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
-- 【修复】移除内部事务控制，由调用方（PHP或定时事件）管理事务
DROP PROCEDURE IF EXISTS sp_release_expired_reservations$$
CREATE PROCEDURE sp_release_expired_reservations()
BEGIN
    DECLARE v_affected_orders INT DEFAULT 0;
    DECLARE v_released_items INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

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

-- ================================================
-- 19. Manager申请相关存储过程
-- ================================================

-- 创建调价申请
DROP PROCEDURE IF EXISTS sp_create_price_adjustment_request$$
CREATE PROCEDURE sp_create_price_adjustment_request(
    IN p_employee_id INT,
    IN p_shop_id INT,
    IN p_release_id INT,
    IN p_condition_grade VARCHAR(10),
    IN p_quantity INT,
    IN p_current_price DECIMAL(10,2),
    IN p_requested_price DECIMAL(10,2),
    IN p_reason TEXT,
    OUT p_request_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_request_id = -1;
        RESIGNAL;
    END;

    INSERT INTO ManagerRequest (
        RequestType, RequestedByEmployeeID, FromShopID, ReleaseID,
        ConditionGrade, Quantity, CurrentPrice, RequestedPrice, Reason
    ) VALUES (
        'PriceAdjustment', p_employee_id, p_shop_id, p_release_id,
        p_condition_grade, p_quantity, p_current_price, p_requested_price, p_reason
    );

    SET p_request_id = LAST_INSERT_ID();
END$$

-- 创建调货申请
DROP PROCEDURE IF EXISTS sp_create_transfer_request$$
CREATE PROCEDURE sp_create_transfer_request(
    IN p_employee_id INT,
    IN p_from_shop_id INT,
    IN p_to_shop_id INT,
    IN p_release_id INT,
    IN p_condition_grade VARCHAR(10),
    IN p_quantity INT,
    IN p_reason TEXT,
    OUT p_request_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_request_id = -1;
        RESIGNAL;
    END;

    INSERT INTO ManagerRequest (
        RequestType, RequestedByEmployeeID, FromShopID, ToShopID, ReleaseID,
        ConditionGrade, Quantity, Reason
    ) VALUES (
        'TransferRequest', p_employee_id, p_from_shop_id, p_to_shop_id, p_release_id,
        p_condition_grade, p_quantity, p_reason
    );

    SET p_request_id = LAST_INSERT_ID();
END$$

-- Admin审批申请
-- 【修改】批准调货申请时创建调拨记录，需要源店铺员工确认后才完成调拨
DROP PROCEDURE IF EXISTS sp_respond_to_request$$
CREATE PROCEDURE sp_respond_to_request(
    IN p_request_id INT,
    IN p_admin_id INT,
    IN p_approved BOOLEAN,
    IN p_response_note TEXT
)
BEGIN
    DECLARE v_request_type VARCHAR(20);
    DECLARE v_status VARCHAR(10);
    DECLARE v_from_shop_id INT;
    DECLARE v_to_shop_id INT;
    DECLARE v_release_id INT;
    DECLARE v_condition_grade VARCHAR(10);
    DECLARE v_quantity INT;
    DECLARE v_requested_price DECIMAL(10,2);
    DECLARE v_current_status VARCHAR(10);
    DECLARE v_stock_item_id INT;
    DECLARE v_counter INT DEFAULT 0;
    DECLARE done INT DEFAULT FALSE;

    DECLARE stock_cursor CURSOR FOR
        SELECT StockItemID
        FROM StockItem
        WHERE ShopID = v_to_shop_id
          AND ReleaseID = v_release_id
          AND ConditionGrade = v_condition_grade
          AND Status = 'Available'
        LIMIT 100; -- 安全限制

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    -- 获取申请信息
    SELECT RequestType, Status, FromShopID, ToShopID, ReleaseID, ConditionGrade, Quantity, RequestedPrice
    INTO v_request_type, v_current_status, v_from_shop_id, v_to_shop_id, v_release_id, v_condition_grade, v_quantity, v_requested_price
    FROM ManagerRequest
    WHERE RequestID = p_request_id
    FOR UPDATE;

    IF v_current_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Request has already been processed';
    END IF;

    SET v_status = IF(p_approved, 'Approved', 'Rejected');

    -- 更新申请状态
    UPDATE ManagerRequest
    SET Status = v_status,
        AdminResponseNote = p_response_note,
        RespondedByEmployeeID = p_admin_id
    WHERE RequestID = p_request_id;

    -- 如果批准，执行相应操作
    IF p_approved THEN
        IF v_request_type = 'PriceAdjustment' THEN
            -- 更新库存价格
            UPDATE StockItem
            SET UnitPrice = v_requested_price
            WHERE ShopID = v_from_shop_id
              AND ReleaseID = v_release_id
              AND ConditionGrade = v_condition_grade
              AND Status = 'Available'
            LIMIT v_quantity;
        ELSEIF v_request_type = 'TransferRequest' THEN
            -- 【修改】创建调拨记录，需要源店铺员工确认发货
            -- FromShopID是目标店铺(Manager的店)，ToShopID是源店铺(Admin选择的)
            -- 从ToShopID调货到FromShopID

            OPEN stock_cursor;
            transfer_loop: LOOP
                IF v_counter >= v_quantity THEN
                    LEAVE transfer_loop;
                END IF;

                FETCH stock_cursor INTO v_stock_item_id;
                IF done THEN
                    LEAVE transfer_loop;
                END IF;

                -- 创建调拨记录（状态为Pending，等待源店铺员工确认）
                INSERT INTO InventoryTransfer (
                    StockItemID, FromShopID, ToShopID,
                    AuthorizedByEmployeeID, Status
                ) VALUES (
                    v_stock_item_id, v_to_shop_id, v_from_shop_id,
                    p_admin_id, 'Pending'
                );

                SET v_counter = v_counter + 1;
            END LOOP;
            CLOSE stock_cursor;
        END IF;
    END IF;
END$$

-- 更新库存价格（Admin直接修改）
DROP PROCEDURE IF EXISTS sp_update_stock_price$$
CREATE PROCEDURE sp_update_stock_price(
    IN p_shop_id INT,
    IN p_release_id INT,
    IN p_condition_grade VARCHAR(10),
    IN p_new_price DECIMAL(10,2)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    UPDATE StockItem
    SET UnitPrice = p_new_price
    WHERE ShopID = p_shop_id
      AND ReleaseID = p_release_id
      AND ConditionGrade = p_condition_grade
      AND Status = 'Available';
END$$

-- ================================================
-- 【架构重构Phase2】新增存储过程 - 消除剩余PHP直接写表操作
-- ================================================

-- ------------------------------------------------
-- 20. 确认调拨发货存储过程
-- 替换 fulfillment.php 中的调拨发货操作
-- 状态从 Pending 变为 InTransit，库存状态变为 InTransit
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_confirm_transfer_dispatch$$
CREATE PROCEDURE sp_confirm_transfer_dispatch(
    IN p_transfer_id INT,
    IN p_employee_id INT
)
BEGIN
    DECLARE v_stock_item_id INT;
    DECLARE v_current_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    -- 获取调拨信息
    SELECT StockItemID, Status INTO v_stock_item_id, v_current_status
    FROM InventoryTransfer
    WHERE TransferID = p_transfer_id
    FOR UPDATE;

    IF v_current_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transfer is not in Pending status';
    END IF;

    -- 更新调拨状态为 InTransit
    UPDATE InventoryTransfer
    SET Status = 'InTransit'
    WHERE TransferID = p_transfer_id;

    -- 更新库存状态为 InTransit
    UPDATE StockItem
    SET Status = 'InTransit'
    WHERE StockItemID = v_stock_item_id;
END$$

-- ------------------------------------------------
-- 21. Warehouse库存调配存储过程
-- 替换 warehouse_dispatch.php 中的库存调拨操作
-- 直接将库存从Warehouse调配到零售店铺
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_dispatch_warehouse_stock$$
CREATE PROCEDURE sp_dispatch_warehouse_stock(
    IN p_warehouse_id INT,
    IN p_target_shop_id INT,
    IN p_release_id INT,
    IN p_condition_grade VARCHAR(10),
    IN p_quantity INT,
    OUT p_dispatched_count INT
)
BEGIN
    DECLARE v_stock_item_id INT;
    DECLARE v_counter INT DEFAULT 0;
    DECLARE done INT DEFAULT FALSE;

    DECLARE stock_cursor CURSOR FOR
        SELECT StockItemID
        FROM StockItem
        WHERE ShopID = p_warehouse_id
          AND ReleaseID = p_release_id
          AND ConditionGrade = p_condition_grade
          AND Status = 'Available'
        ORDER BY StockItemID
        LIMIT 100; -- 安全限制

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_dispatched_count = v_counter;
        RESIGNAL;
    END;

    OPEN stock_cursor;
    dispatch_loop: LOOP
        IF v_counter >= p_quantity THEN
            LEAVE dispatch_loop;
        END IF;

        FETCH stock_cursor INTO v_stock_item_id;
        IF done THEN
            LEAVE dispatch_loop;
        END IF;

        -- 直接更新库存位置（不创建调拨记录，因为是Admin统一调配）
        UPDATE StockItem
        SET ShopID = p_target_shop_id
        WHERE StockItemID = v_stock_item_id;

        SET v_counter = v_counter + 1;
    END LOOP;
    CLOSE stock_cursor;

    SET p_dispatched_count = v_counter;
END$$

-- ------------------------------------------------
-- 22. 创建在线订单完整流程存储过程
-- 替换 checkout.php 中的订单创建流程
-- 包含订单创建、添加商品、计算折扣、设置运费
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_create_online_order_complete$$
CREATE PROCEDURE sp_create_online_order_complete(
    IN p_customer_id INT,
    IN p_shop_id INT,
    IN p_stock_item_ids TEXT, -- 逗号分隔的 StockItemID 列表
    IN p_fulfillment_type VARCHAR(20), -- 'Shipping' or 'Pickup'
    IN p_shipping_address TEXT,
    IN p_shipping_cost DECIMAL(10,2),
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
        CustomerID, FulfilledByShopID, OrderType, OrderStatus,
        FulfillmentType, ShippingAddress, ShippingCost
    ) VALUES (
        p_customer_id, p_shop_id, 'Online', 'Pending',
        p_fulfillment_type, p_shipping_address, COALESCE(p_shipping_cost, 0)
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

            -- 验证并锁定库存
            SELECT Status, UnitPrice INTO v_stock_status, v_unit_price
            FROM StockItem
            WHERE StockItemID = v_stock_item_id
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

    -- 计算总金额（小计 - 折扣 + 运费）
    SET p_total_amount = v_subtotal - v_discount_amount + COALESCE(p_shipping_cost, 0);

    -- 更新订单金额
    UPDATE CustomerOrder
    SET TotalAmount = p_total_amount,
        DiscountApplied = v_discount_amount
    WHERE OrderID = p_order_id;

    -- 如果没有处理任何商品，取消订单
    IF v_items_processed = 0 THEN
        DELETE FROM CustomerOrder WHERE OrderID = p_order_id;
        SET p_order_id = -2; -- 表示没有可用商品
    END IF;
END$$

-- ------------------------------------------------
-- 23. 更新订单状态存储过程
-- 通用订单状态更新，替换各页面中的直接UPDATE
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_update_order_status$$
CREATE PROCEDURE sp_update_order_status(
    IN p_order_id INT,
    IN p_new_status VARCHAR(20),
    IN p_employee_id INT
)
BEGIN
    DECLARE v_current_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    SELECT OrderStatus INTO v_current_status
    FROM CustomerOrder
    WHERE OrderID = p_order_id
    FOR UPDATE;

    -- 验证状态转换合法性
    IF v_current_status = 'Cancelled' OR v_current_status = 'Completed' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot update terminal order status';
    END IF;

    UPDATE CustomerOrder
    SET OrderStatus = p_new_status,
        ProcessedByEmployeeID = COALESCE(ProcessedByEmployeeID, p_employee_id)
    WHERE OrderID = p_order_id;

    -- 如果取消订单，释放库存
    IF p_new_status = 'Cancelled' THEN
        UPDATE StockItem s
        JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
        SET s.Status = 'Available'
        WHERE ol.OrderID = p_order_id AND s.Status = 'Reserved';
    END IF;
END$$

-- ------------------------------------------------
-- 24. POS创建门店订单完整流程
-- 替换 pos.php 中的订单创建流程
-- ------------------------------------------------
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
    END IF;
END$$

-- ------------------------------------------------
-- 25. 获取店铺ID存储过程（按类型）
-- 替换 functions.php 中的 getShopIdByType
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_get_shop_id_by_type$$
CREATE PROCEDURE sp_get_shop_id_by_type(
    IN p_shop_type VARCHAR(20),
    OUT p_shop_id INT
)
BEGIN
    SELECT ShopID INTO p_shop_id
    FROM Shop
    WHERE Type = p_shop_type
    LIMIT 1;
END$$

-- ------------------------------------------------
-- 26. 验证购物车商品存储过程
-- 替换 cart.php 中的商品验证
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_validate_cart_item$$
CREATE PROCEDURE sp_validate_cart_item(
    IN p_stock_item_id INT,
    IN p_shop_id INT,
    OUT p_valid INT,
    OUT p_title VARCHAR(255),
    OUT p_artist VARCHAR(255),
    OUT p_price DECIMAL(10,2),
    OUT p_condition VARCHAR(10)
)
BEGIN
    DECLARE v_status VARCHAR(20);

    SET p_valid = 0;

    SELECT si.Status, r.Title, r.ArtistName, si.UnitPrice, si.ConditionGrade
    INTO v_status, p_title, p_artist, p_price, p_condition
    FROM StockItem si
    JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
    WHERE si.StockItemID = p_stock_item_id
      AND si.ShopID = p_shop_id;

    IF v_status = 'Available' THEN
        SET p_valid = 1;
    END IF;
END$$

-- ------------------------------------------------
-- 27. 确认订单收货存储过程
-- 替换 fulfillment.php / customer 端确认收货
-- ------------------------------------------------
DROP PROCEDURE IF EXISTS sp_confirm_order_received$$
CREATE PROCEDURE sp_confirm_order_received(
    IN p_order_id INT
)
BEGIN
    DECLARE v_status VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    SELECT OrderStatus INTO v_status
    FROM CustomerOrder
    WHERE OrderID = p_order_id
    FOR UPDATE;

    IF v_status != 'Shipped' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order must be in Shipped status to confirm receipt';
    END IF;

    -- 调用完成订单存储过程
    CALL sp_complete_order(p_order_id);
END$$

DELIMITER ;
