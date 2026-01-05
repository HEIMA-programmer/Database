DELIMITER $$

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

DROP PROCEDURE IF EXISTS sp_receive_supplier_order$$
CREATE PROCEDURE sp_receive_supplier_order(
    IN p_order_id INT,
    IN p_batch_no VARCHAR(50),
    IN p_condition_grade VARCHAR(10),
    IN p_markup_rate DECIMAL(3,2)
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

    SELECT Status, DestinationShopID INTO v_status, v_shop_id
    FROM SupplierOrder
    WHERE SupplierOrderID = p_order_id;

    IF v_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order is not in Pending status';
    END IF;

    UPDATE SupplierOrder
    SET Status = 'Received', ReceivedDate = NOW()
    WHERE SupplierOrderID = p_order_id;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_release_id, v_quantity, v_unit_cost, v_line_condition, v_line_sale_price;
        IF done THEN
            LEAVE read_loop;
        END IF;

        IF v_line_sale_price IS NOT NULL AND v_line_sale_price > 0 THEN
            SET v_unit_price = v_line_sale_price;
        ELSE
            SET v_unit_price = v_unit_cost * (1 + p_markup_rate);
        END IF;

        IF v_line_condition IS NULL OR v_line_condition = '' THEN
            SET v_line_condition = COALESCE(p_condition_grade, 'New');
        END IF;

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

        UPDATE StockItem
        SET UnitPrice = v_unit_price
        WHERE ReleaseID = v_release_id
          AND ConditionGrade = v_line_condition
          AND Status = 'Available'
          AND UnitPrice != v_unit_price;
    END LOOP;
    CLOSE cur;

END$$

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
    DECLARE v_existing_price DECIMAL(10,2);
    DECLARE v_final_resale_price DECIMAL(10,2);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_buyback_id = -1;
        RESIGNAL;
    END;

    SELECT MAX(UnitPrice) INTO v_existing_price
    FROM StockItem
    WHERE ReleaseID = p_release_id
      AND ConditionGrade = p_condition_grade
      AND ShopID = p_shop_id
      AND Status = 'Available';

    IF v_existing_price IS NOT NULL AND v_existing_price > 0 THEN
        SET v_final_resale_price = v_existing_price;
    ELSE
        SET v_final_resale_price = p_resale_price;
    END IF;

    SET v_total_payment = p_quantity * p_unit_price;

    INSERT INTO BuybackOrder (CustomerID, ProcessedByEmployeeID, ShopID, Status, TotalPayment)
    VALUES (p_customer_id, p_employee_id, p_shop_id, 'Completed', v_total_payment);

    SET p_buyback_id = LAST_INSERT_ID();

    INSERT INTO BuybackOrderLine (BuybackOrderID, ReleaseID, Quantity, UnitPrice, ConditionGrade)
    VALUES (p_buyback_id, p_release_id, p_quantity, p_unit_price, p_condition_grade);

    SET v_batch_no = CONCAT('BUY-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', p_buyback_id);

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

END$$

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

    SELECT StockItemID, ToShopID, Status
    INTO v_stock_item_id, v_to_shop_id, v_status
    FROM InventoryTransfer
    WHERE TransferID = p_transfer_id
    FOR UPDATE;

    IF v_status != 'InTransit' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transfer is not in transit';
    END IF;

    UPDATE InventoryTransfer
    SET Status = 'Completed',
        ReceivedByEmployeeID = p_received_by_employee_id,
        ReceivedDate = NOW()
    WHERE TransferID = p_transfer_id;

END$$

DROP PROCEDURE IF EXISTS sp_create_customer_order$$
CREATE PROCEDURE sp_create_customer_order(
    IN p_customer_id INT,
    IN p_shop_id INT,
    IN p_employee_id INT,
    IN p_order_type VARCHAR(10),
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

    SELECT OrderStatus INTO v_order_status
    FROM CustomerOrder
    WHERE OrderID = p_order_id
    FOR UPDATE;

    IF v_order_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order is not in Pending status';
    END IF;

    UPDATE CustomerOrder
    SET OrderStatus = 'Paid'
    WHERE OrderID = p_order_id;

END$$

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

    SELECT CustomerID, OrderStatus, OrderType INTO v_customer_id, v_order_status, v_order_type
    FROM CustomerOrder
    WHERE OrderID = p_order_id
    FOR UPDATE;

    IF v_order_type = 'InStore' THEN
        IF v_order_status NOT IN ('Pending', 'Paid') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'InStore order must be Pending or Paid to complete';
        END IF;
    ELSE
        IF v_order_status NOT IN ('Paid', 'Shipped') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Online order must be Paid or Shipped to complete';
        END IF;
    END IF;

    UPDATE CustomerOrder
    SET OrderStatus = 'Completed'
    WHERE OrderID = p_order_id;

    UPDATE StockItem s
    JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
    SET s.Status = 'Sold'
    WHERE ol.OrderID = p_order_id;

END$$

DROP PROCEDURE IF EXISTS sp_cancel_order$$
CREATE PROCEDURE sp_cancel_order(
    IN p_order_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    UPDATE CustomerOrder
    SET OrderStatus = 'Cancelled'
    WHERE OrderID = p_order_id;
END$$

DROP PROCEDURE IF EXISTS sp_release_expired_reservations$$
CREATE PROCEDURE sp_release_expired_reservations()
BEGIN
    DECLARE v_affected_orders INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    SELECT COUNT(DISTINCT co.OrderID) INTO v_affected_orders
    FROM CustomerOrder co
    WHERE co.OrderStatus = 'Pending'
      AND co.OrderDate < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

    UPDATE CustomerOrder
    SET OrderStatus = 'Cancelled'
    WHERE OrderStatus = 'Pending'
      AND OrderDate < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

    SELECT
        v_affected_orders AS ExpiredOrders,
        ROW_COUNT() AS CancelledOrders,
        NOW() AS ProcessedAt;
END$$

DELIMITER ;

DROP EVENT IF EXISTS evt_release_expired_reservations;

CREATE EVENT evt_release_expired_reservations
ON SCHEDULE EVERY 5 MINUTE
STARTS CURRENT_TIMESTAMP
DO
    CALL sp_release_expired_reservations();

DELIMITER $$

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

    SELECT COUNT(*) INTO v_email_exists FROM Customer WHERE Email = p_email;

    IF v_email_exists > 0 THEN
        SET p_customer_id = -2;
        SET p_tier_id = -1;
    ELSE

        SELECT TierID INTO v_default_tier_id
        FROM MembershipTier
        ORDER BY MinPoints ASC
        LIMIT 1;

        INSERT INTO Customer (TierID, Name, Email, PasswordHash, Birthday, Points)
        VALUES (v_default_tier_id, p_name, p_email, p_password_hash, p_birthday, 0);

        SET p_customer_id = LAST_INSERT_ID();
        SET p_tier_id = v_default_tier_id;
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_update_customer_profile$$
CREATE PROCEDURE sp_update_customer_profile(
    IN p_customer_id INT,
    IN p_name VARCHAR(100),
    IN p_password_hash VARCHAR(255)
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

DROP PROCEDURE IF EXISTS sp_update_employee$$
CREATE PROCEDURE sp_update_employee(
    IN p_employee_id INT,
    IN p_name VARCHAR(100),
    IN p_role ENUM('Admin', 'Manager', 'Staff'),
    IN p_shop_id INT,
    IN p_password_hash VARCHAR(255)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    UPDATE Employee
    SET Name = p_name,
        Role = COALESCE(p_role, Role),
        ShopID = COALESCE(p_shop_id, ShopID),
        PasswordHash = CASE
            WHEN p_password_hash IS NOT NULL AND p_password_hash != '' THEN p_password_hash
            ELSE PasswordHash
        END
    WHERE EmployeeID = p_employee_id;
END$$

DROP PROCEDURE IF EXISTS sp_delete_employee$$
CREATE PROCEDURE sp_delete_employee(
    IN p_employee_id INT,
    IN p_current_user_id INT
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

DROP PROCEDURE IF EXISTS sp_add_supplier$$
CREATE PROCEDURE sp_add_supplier(
    IN p_name VARCHAR(100),
    IN p_email VARCHAR(100),
    OUT p_supplier_id INT
)
BEGIN
    DECLARE v_existing_count INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_supplier_id = -1;
        RESIGNAL;
    END;

    SELECT COUNT(*) INTO v_existing_count
    FROM Supplier
    WHERE Name = p_name;

    IF v_existing_count > 0 THEN

        SET p_supplier_id = -2;
    ELSE
        INSERT INTO Supplier (Name, Email)
        VALUES (p_name, p_email);

        SET p_supplier_id = LAST_INSERT_ID();
    END IF;
END$$

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

DROP PROCEDURE IF EXISTS sp_delete_supplier$$
CREATE PROCEDURE sp_delete_supplier(
    IN p_supplier_id INT,
    OUT p_result INT
)
BEGIN
    DECLARE v_order_count INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_result = 0;
        RESIGNAL;
    END;

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
    DECLARE v_existing_count INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_release_id = -1;
        RESIGNAL;
    END;

    SELECT COUNT(*) INTO v_existing_count
    FROM ReleaseAlbum
    WHERE Title = p_title AND ArtistName = p_artist;

    IF v_existing_count > 0 THEN

        SET p_release_id = -2;
    ELSE
        INSERT INTO ReleaseAlbum (Title, ArtistName, LabelName, ReleaseYear, Genre, Format, Description)
        VALUES (p_title, p_artist, p_label, p_year, p_genre, 'Vinyl', p_description);

        SET p_release_id = LAST_INSERT_ID();
    END IF;
END$$

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
        LIMIT 100;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    SELECT RequestType, Status, FromShopID, ToShopID, ReleaseID, ConditionGrade, Quantity, RequestedPrice
    INTO v_request_type, v_current_status, v_from_shop_id, v_to_shop_id, v_release_id, v_condition_grade, v_quantity, v_requested_price
    FROM ManagerRequest
    WHERE RequestID = p_request_id
    FOR UPDATE;

    IF v_current_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Request has already been processed';
    END IF;

    SET v_status = IF(p_approved, 'Approved', 'Rejected');

    UPDATE ManagerRequest
    SET Status = v_status,
        AdminResponseNote = p_response_note,
        RespondedByEmployeeID = p_admin_id
    WHERE RequestID = p_request_id;

    IF p_approved THEN
        IF v_request_type = 'PriceAdjustment' THEN

            UPDATE StockItem
            SET UnitPrice = v_requested_price
            WHERE ShopID = v_from_shop_id
              AND ReleaseID = v_release_id
              AND ConditionGrade = v_condition_grade
              AND Status = 'Available'
            LIMIT v_quantity;
        ELSEIF v_request_type = 'TransferRequest' THEN

            OPEN stock_cursor;
            transfer_loop: LOOP
                IF v_counter >= v_quantity THEN
                    LEAVE transfer_loop;
                END IF;

                FETCH stock_cursor INTO v_stock_item_id;
                IF done THEN
                    LEAVE transfer_loop;
                END IF;

                UPDATE StockItem
                SET Status = 'Reserved'
                WHERE StockItemID = v_stock_item_id;

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

    SELECT StockItemID, Status INTO v_stock_item_id, v_current_status
    FROM InventoryTransfer
    WHERE TransferID = p_transfer_id
    FOR UPDATE;

    IF v_current_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transfer is not in Pending status';
    END IF;

    UPDATE InventoryTransfer
    SET Status = 'InTransit'
    WHERE TransferID = p_transfer_id;

    UPDATE StockItem
    SET Status = 'InTransit'
    WHERE StockItemID = v_stock_item_id;
END$$

DROP PROCEDURE IF EXISTS sp_create_online_order_complete$$
CREATE PROCEDURE sp_create_online_order_complete(
    IN p_customer_id INT,
    IN p_shop_id INT,
    IN p_stock_item_ids TEXT,
    IN p_fulfillment_type VARCHAR(20),
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

    IF p_customer_id IS NOT NULL THEN
        SELECT mt.DiscountRate INTO v_discount_rate
        FROM Customer c
        JOIN MembershipTier mt ON c.TierID = mt.TierID
        WHERE c.CustomerID = p_customer_id;
    END IF;

    INSERT INTO CustomerOrder (
        CustomerID, FulfilledByShopID, OrderType, OrderStatus,
        FulfillmentType, ShippingAddress, ShippingCost
    ) VALUES (
        p_customer_id, p_shop_id, 'Online', 'Pending',
        p_fulfillment_type, p_shipping_address, COALESCE(p_shipping_cost, 0)
    );

    SET p_order_id = LAST_INSERT_ID();

    SET p_stock_item_ids = CONCAT(p_stock_item_ids, ',');

    parse_loop: WHILE v_pos > 0 DO
        SET v_next_pos = LOCATE(',', p_stock_item_ids, v_pos);
        IF v_next_pos = 0 THEN
            LEAVE parse_loop;
        END IF;

        SET v_id_str = TRIM(SUBSTRING(p_stock_item_ids, v_pos, v_next_pos - v_pos));
        IF v_id_str != '' THEN
            SET v_stock_item_id = CAST(v_id_str AS UNSIGNED);

            SELECT Status, UnitPrice INTO v_stock_status, v_unit_price
            FROM StockItem
            WHERE StockItemID = v_stock_item_id
            FOR UPDATE;

            IF v_stock_status = 'Available' THEN

                INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale)
                VALUES (p_order_id, v_stock_item_id, v_unit_price);

                UPDATE StockItem
                SET Status = 'Reserved'
                WHERE StockItemID = v_stock_item_id;

                SET v_subtotal = v_subtotal + v_unit_price;
                SET v_items_processed = v_items_processed + 1;
            END IF;
        END IF;

        SET v_pos = v_next_pos + 1;
    END WHILE;

    SET v_discount_amount = v_subtotal * v_discount_rate;

    SET p_total_amount = v_subtotal - v_discount_amount + COALESCE(p_shipping_cost, 0);

    UPDATE CustomerOrder
    SET TotalAmount = p_total_amount
    WHERE OrderID = p_order_id;

    IF v_items_processed = 0 THEN
        DELETE FROM CustomerOrder WHERE OrderID = p_order_id;
        SET p_order_id = -2;
    END IF;
END$$

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

    IF v_current_status = 'Cancelled' OR v_current_status = 'Completed' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot update terminal order status';
    END IF;

    UPDATE CustomerOrder
    SET OrderStatus = p_new_status,
        ProcessedByEmployeeID = COALESCE(ProcessedByEmployeeID, p_employee_id)
    WHERE OrderID = p_order_id;

END$$

DROP PROCEDURE IF EXISTS sp_create_pos_order$$
CREATE PROCEDURE sp_create_pos_order(
    IN p_customer_id INT,
    IN p_employee_id INT,
    IN p_shop_id INT,
    IN p_stock_item_ids TEXT,
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

    IF p_customer_id IS NOT NULL THEN
        SELECT mt.DiscountRate INTO v_discount_rate
        FROM Customer c
        JOIN MembershipTier mt ON c.TierID = mt.TierID
        WHERE c.CustomerID = p_customer_id;
    END IF;

    INSERT INTO CustomerOrder (
        CustomerID, FulfilledByShopID, ProcessedByEmployeeID,
        OrderType, OrderStatus
    ) VALUES (
        p_customer_id, p_shop_id, p_employee_id,
        'InStore', 'Pending'
    );

    SET p_order_id = LAST_INSERT_ID();

    SET p_stock_item_ids = CONCAT(p_stock_item_ids, ',');

    parse_loop: WHILE v_pos > 0 DO
        SET v_next_pos = LOCATE(',', p_stock_item_ids, v_pos);
        IF v_next_pos = 0 THEN
            LEAVE parse_loop;
        END IF;

        SET v_id_str = TRIM(SUBSTRING(p_stock_item_ids, v_pos, v_next_pos - v_pos));
        IF v_id_str != '' THEN
            SET v_stock_item_id = CAST(v_id_str AS UNSIGNED);

            SELECT Status, UnitPrice INTO v_stock_status, v_unit_price
            FROM StockItem
            WHERE StockItemID = v_stock_item_id AND ShopID = p_shop_id
            FOR UPDATE;

            IF v_stock_status = 'Available' THEN

                INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale)
                VALUES (p_order_id, v_stock_item_id, v_unit_price);

                UPDATE StockItem
                SET Status = 'Reserved'
                WHERE StockItemID = v_stock_item_id;

                SET v_subtotal = v_subtotal + v_unit_price;
                SET v_items_processed = v_items_processed + 1;
            END IF;
        END IF;

        SET v_pos = v_next_pos + 1;
    END WHILE;

    SET v_discount_amount = v_subtotal * v_discount_rate;

    SET p_total_amount = v_subtotal - v_discount_amount;

    UPDATE CustomerOrder
    SET TotalAmount = p_total_amount
    WHERE OrderID = p_order_id;

    IF v_items_processed = 0 THEN
        DELETE FROM CustomerOrder WHERE OrderID = p_order_id;
        SET p_order_id = -2;
    ELSE

        CALL sp_complete_order(p_order_id);
    END IF;
END$$

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

DROP PROCEDURE IF EXISTS sp_cancel_transfer$$
CREATE PROCEDURE sp_cancel_transfer(
    IN p_transfer_id INT,
    IN p_shop_id INT
)
BEGIN
    DECLARE v_from_shop_id INT;
    DECLARE v_status VARCHAR(20);
    DECLARE v_stock_item_id INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    SELECT FromShopID, Status, StockItemID INTO v_from_shop_id, v_status, v_stock_item_id
    FROM InventoryTransfer
    WHERE TransferID = p_transfer_id
    FOR UPDATE;

    IF v_from_shop_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transfer not found';
    END IF;

    IF v_from_shop_id != p_shop_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transfer does not belong to this shop';
    END IF;

    IF v_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only pending transfers can be cancelled';
    END IF;

    UPDATE StockItem
    SET Status = 'Available'
    WHERE StockItemID = v_stock_item_id;

    DELETE FROM InventoryTransfer WHERE TransferID = p_transfer_id;
END$$

DROP PROCEDURE IF EXISTS sp_update_transfer_request_source$$
CREATE PROCEDURE sp_update_transfer_request_source(
    IN p_request_id INT,
    IN p_source_shop_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    UPDATE ManagerRequest
    SET ToShopID = p_source_shop_id
    WHERE RequestID = p_request_id
      AND RequestType = 'TransferRequest';
END$$

DROP PROCEDURE IF EXISTS sp_initiate_warehouse_dispatch$$
CREATE PROCEDURE sp_initiate_warehouse_dispatch(
    IN p_warehouse_id INT,
    IN p_target_shop_id INT,
    IN p_release_id INT,
    IN p_condition_grade VARCHAR(10),
    IN p_quantity INT,
    IN p_employee_id INT,
    OUT p_initiated_count INT
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
        LIMIT 100;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_initiated_count = v_counter;
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

        INSERT INTO InventoryTransfer (
            StockItemID, FromShopID, ToShopID,
            AuthorizedByEmployeeID, Status
        ) VALUES (
            v_stock_item_id, p_warehouse_id, p_target_shop_id,
            p_employee_id, 'Pending'
        );

        UPDATE StockItem
        SET Status = 'Reserved'
        WHERE StockItemID = v_stock_item_id;

        SET v_counter = v_counter + 1;
    END LOOP;
    CLOSE stock_cursor;

    SET p_initiated_count = v_counter;
END$$

DROP PROCEDURE IF EXISTS sp_get_stock_item_with_lock$$
CREATE PROCEDURE sp_get_stock_item_with_lock(
    IN p_stock_id INT
)
BEGIN

    SELECT StockItemID, ShopID, Status
    FROM StockItem
    WHERE StockItemID = p_stock_id
    FOR UPDATE;
END$$

DROP PROCEDURE IF EXISTS sp_mark_requests_viewed$$
CREATE PROCEDURE sp_mark_requests_viewed(
    IN p_employee_id INT
)
BEGIN

    UPDATE ManagerRequest
    SET ViewedByRequesterAt = NOW()
    WHERE RequestedByEmployeeID = p_employee_id
      AND Status IN ('Approved', 'Rejected')
      AND ViewedByRequesterAt IS NULL;
END$$

DROP PROCEDURE IF EXISTS sp_update_employee_session$$
CREATE PROCEDURE sp_update_employee_session(
    IN p_employee_id INT,
    IN p_session_id VARCHAR(128)
)
BEGIN
    UPDATE Employee
    SET CurrentSessionID = p_session_id
    WHERE EmployeeID = p_employee_id;
END$$

DROP PROCEDURE IF EXISTS sp_update_customer_session$$
CREATE PROCEDURE sp_update_customer_session(
    IN p_customer_id INT,
    IN p_session_id VARCHAR(128)
)
BEGIN
    UPDATE Customer
    SET CurrentSessionID = p_session_id
    WHERE CustomerID = p_customer_id;
END$$

DELIMITER ;