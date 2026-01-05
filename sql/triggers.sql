DELIMITER $$

DROP TRIGGER IF EXISTS trg_after_order_complete$$
CREATE TRIGGER trg_after_order_complete
AFTER UPDATE ON CustomerOrder
FOR EACH ROW
BEGIN
    DECLARE v_points_to_add INT;
    DECLARE v_current_points INT;
    DECLARE v_new_tier_id INT;
    DECLARE v_goods_amount DECIMAL(10,2);

    IF NEW.OrderStatus = 'Completed' AND OLD.OrderStatus != 'Completed' AND NEW.CustomerID IS NOT NULL THEN

        SET v_goods_amount = NEW.TotalAmount - COALESCE(NEW.ShippingCost, 0);

        SET v_points_to_add = FLOOR(v_goods_amount);

        UPDATE Customer
        SET Points = Points + v_points_to_add
        WHERE CustomerID = NEW.CustomerID;

        SELECT Points INTO v_current_points
        FROM Customer
        WHERE CustomerID = NEW.CustomerID;

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

DROP TRIGGER IF EXISTS trg_after_order_cancel$$
CREATE TRIGGER trg_after_order_cancel
AFTER UPDATE ON CustomerOrder
FOR EACH ROW
BEGIN

    IF NEW.OrderStatus = 'Cancelled' AND OLD.OrderStatus != 'Cancelled' THEN

        UPDATE StockItem s
        JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
        SET s.Status = 'Available'
        WHERE ol.OrderID = NEW.OrderID AND s.Status = 'Reserved';
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_after_transfer_complete$$
CREATE TRIGGER trg_after_transfer_complete
AFTER UPDATE ON InventoryTransfer
FOR EACH ROW
BEGIN

    IF NEW.Status = 'Completed' AND OLD.Status != 'Completed' THEN
        UPDATE StockItem
        SET ShopID = NEW.ToShopID,
            Status = 'Available'
        WHERE StockItemID = NEW.StockItemID;
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_after_transfer_cancel$$
CREATE TRIGGER trg_after_transfer_cancel
AFTER UPDATE ON InventoryTransfer
FOR EACH ROW
BEGIN

    IF NEW.Status = 'Cancelled' AND OLD.Status != 'Cancelled' THEN

        UPDATE StockItem
        SET Status = 'Available'
        WHERE StockItemID = NEW.StockItemID AND Status IN ('InTransit', 'Reserved');
    END IF;
END$$

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

DROP TRIGGER IF EXISTS trg_before_order_line_update$$
CREATE TRIGGER trg_before_order_line_update
BEFORE UPDATE ON OrderLine
FOR EACH ROW
BEGIN
    DECLARE v_order_status VARCHAR(20);

    SELECT OrderStatus INTO v_order_status
    FROM CustomerOrder
    WHERE OrderID = OLD.OrderID;

    IF v_order_status IN ('Completed', 'Shipped', 'Cancelled') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot modify completed, shipped or cancelled orders';
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_before_order_line_delete$$
CREATE TRIGGER trg_before_order_line_delete
BEFORE DELETE ON OrderLine
FOR EACH ROW
BEGIN
    DECLARE v_order_status VARCHAR(20);

    SELECT OrderStatus INTO v_order_status
    FROM CustomerOrder
    WHERE OrderID = OLD.OrderID;

    IF v_order_status IN ('Completed', 'Shipped', 'Cancelled') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot delete items from completed, shipped or cancelled orders';
    END IF;
END$$

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

    IF v_stock_status NOT IN ('Available', 'Reserved') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Can only transfer available or reserved stock items';
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

DROP TRIGGER IF EXISTS trg_before_stock_status_update$$
CREATE TRIGGER trg_before_stock_status_update
BEFORE UPDATE ON StockItem
FOR EACH ROW
BEGIN

    IF NEW.Status = 'Sold' AND OLD.Status != 'Sold' THEN
        SET NEW.DateSold = NOW();
    END IF;

    IF NEW.Status != 'Sold' AND OLD.Status = 'Sold' THEN
        SET NEW.DateSold = NULL;
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_before_stock_item_insert$$
CREATE TRIGGER trg_before_stock_item_insert
BEFORE INSERT ON StockItem
FOR EACH ROW
BEGIN
    DECLARE v_exists INT;

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

DROP TRIGGER IF EXISTS trg_before_stock_item_update$$
CREATE TRIGGER trg_before_stock_item_update
BEFORE UPDATE ON StockItem
FOR EACH ROW
BEGIN
    DECLARE v_exists INT;

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

DROP TRIGGER IF EXISTS trg_birthday_bonus$$
CREATE TRIGGER trg_birthday_bonus
AFTER UPDATE ON CustomerOrder
FOR EACH ROW
BEGIN
    DECLARE v_birth_month INT;
    DECLARE v_current_month INT;
    DECLARE v_bonus_points INT;

    IF NEW.OrderStatus = 'Completed' AND OLD.OrderStatus != 'Completed' AND NEW.CustomerID IS NOT NULL THEN

        SELECT MONTH(Birthday) INTO v_birth_month
        FROM Customer
        WHERE CustomerID = NEW.CustomerID;

        SET v_current_month = MONTH(NEW.OrderDate);

        IF v_birth_month = v_current_month THEN

            SET v_bonus_points = FLOOR((NEW.TotalAmount - COALESCE(NEW.ShippingCost, 0)) * 0.2);
            UPDATE Customer
            SET Points = Points + v_bonus_points
            WHERE CustomerID = NEW.CustomerID;
        END IF;
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_after_buyback_complete$$
CREATE TRIGGER trg_after_buyback_complete
AFTER INSERT ON BuybackOrder
FOR EACH ROW
BEGIN
    DECLARE v_points_to_add INT;
    DECLARE v_current_points INT;
    DECLARE v_new_tier_id INT;

    IF NEW.Status = 'Completed' AND NEW.CustomerID IS NOT NULL THEN

        SET v_points_to_add = FLOOR(NEW.TotalPayment * 0.5);

        IF v_points_to_add > 0 THEN

            UPDATE Customer
            SET Points = Points + v_points_to_add
            WHERE CustomerID = NEW.CustomerID;

            SELECT Points INTO v_current_points
            FROM Customer
            WHERE CustomerID = NEW.CustomerID;

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