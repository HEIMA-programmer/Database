# 4. Technical Annex

*This section is intended for the IT Department and provides technical documentation of the database implementation.*

---

## 4.1 Database Schema Overview

The database consists of **17 core tables** organized into six functional groups:

### 4.1.1 Entity Relationship Summary

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           DATABASE SCHEMA OVERVIEW                           │
├─────────────────────────────────────────────────────────────────────────────┤
│  ORGANIZATION        CATALOG             PROCUREMENT                        │
│  ┌──────────┐       ┌─────────────┐     ┌──────────────┐                   │
│  │  Shop    │       │ReleaseAlbum │     │  Supplier    │                   │
│  └────┬─────┘       └──────┬──────┘     └──────┬───────┘                   │
│       │                    │                    │                           │
│  ┌────┴─────┐       ┌──────┴──────┐     ┌──────┴───────┐                   │
│  │ Employee │       │   Track     │     │SupplierOrder │                   │
│  └──────────┘       └─────────────┘     └──────┬───────┘                   │
│                                                │                            │
│  CUSTOMER                                ┌─────┴────────┐                   │
│  ┌──────────────┐                       │SupplierOrder │                   │
│  │MembershipTier│                       │    Line      │                   │
│  └──────┬───────┘                       └──────────────┘                   │
│         │                                                                   │
│  ┌──────┴───────┐   INVENTORY           BUYBACK                            │
│  │   Customer   │   ┌──────────┐       ┌──────────────┐                   │
│  └──────────────┘   │StockItem │◄──────┤BuybackOrder  │                   │
│                     └────┬─────┘       └──────┬───────┘                   │
│  SALES                   │                    │                            │
│  ┌──────────────┐  ┌─────┴──────┐     ┌──────┴───────┐                   │
│  │CustomerOrder │  │ Inventory  │     │ BuybackOrder │                   │
│  └──────┬───────┘  │  Transfer  │     │    Line      │                   │
│         │          └────────────┘     └──────────────┘                   │
│  ┌──────┴───────┐                                                          │
│  │  OrderLine   │   MANAGEMENT                                             │
│  └──────────────┘   ┌──────────────┐                                       │
│                     │ManagerRequest│                                       │
│                     └──────────────┘                                       │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 4.1.2 Core Table Definitions

**Organization Tables:**

```sql
CREATE TABLE Shop (
    ShopID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Address TEXT,
    Type ENUM('Retail', 'Warehouse') NOT NULL
);

CREATE TABLE Employee (
    EmployeeID INT AUTO_INCREMENT PRIMARY KEY,
    ShopID INT,  -- NULL for Admin (global access)
    Role ENUM('Admin', 'Manager', 'Staff') NOT NULL,
    Name VARCHAR(100) NOT NULL,
    Username VARCHAR(50) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    HireDate DATE DEFAULT (CURRENT_DATE),
    FOREIGN KEY (ShopID) REFERENCES Shop(ShopID)
);
```

**Inventory Table with Polymorphic Foreign Key:**

```sql
CREATE TABLE StockItem (
    StockItemID INT AUTO_INCREMENT PRIMARY KEY,
    ReleaseID INT NOT NULL,
    ShopID INT NOT NULL,
    SourceType ENUM('Supplier', 'Buyback') NOT NULL,  -- Discriminator
    SourceOrderID INT,  -- References SupplierOrder OR BuybackOrder
    BatchNo VARCHAR(50) NOT NULL,
    ConditionGrade ENUM('New', 'Mint', 'NM', 'VG+', 'VG') NOT NULL,
    Status ENUM('Available', 'Sold', 'Reserved', 'InTransit') DEFAULT 'Available',
    UnitPrice DECIMAL(10,2) NOT NULL,
    AcquiredDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    DateSold DATETIME DEFAULT NULL,
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID),
    FOREIGN KEY (ShopID) REFERENCES Shop(ShopID)
);
```

---

## 4.2 Access Management Through Views

The system implements **View-Based Access Control (VBAC)** with **109 database views** ensuring that users access data through filtered views rather than direct table access.

### 4.2.1 Role-Specific View Examples

**Customer View - Order History:**
```sql
CREATE OR REPLACE VIEW vw_customer_order_history AS
SELECT
    co.OrderID,
    co.CustomerID,
    co.OrderDate,
    co.OrderStatus,
    co.OrderType,
    co.TotalAmount,
    r.Title AS AlbumTitle,
    r.ArtistName,
    ol.PriceAtSale,
    s.ConditionGrade
FROM CustomerOrder co
JOIN OrderLine ol ON co.OrderID = ol.OrderID
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID;
-- Application filters by CustomerID = session user
```

**Staff View - POS Inventory Lookup:**
```sql
CREATE OR REPLACE VIEW vw_staff_pos_lookup AS
SELECT
    s.StockItemID,
    s.ShopID,
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    s.BatchNo,
    s.ConditionGrade,
    s.UnitPrice,
    s.Status
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID;
-- Application filters by ShopID = employee's shop
```

**Manager View - Shop Performance:**
```sql
CREATE OR REPLACE VIEW vw_manager_shop_performance AS
SELECT
    s.ShopID,
    s.Name AS ShopName,
    COUNT(DISTINCT co.OrderID) AS TotalOrders,
    SUM(co.TotalAmount) AS TotalRevenue,
    AVG(co.TotalAmount) AS AvgOrderValue
FROM Shop s
LEFT JOIN CustomerOrder co ON s.ShopID = co.FulfilledByShopID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY s.ShopID, s.Name;
```

**Admin View - Pending Requests:**
```sql
CREATE OR REPLACE VIEW vw_admin_pending_requests AS
SELECT
    mr.RequestID,
    mr.RequestType,
    e.Name AS RequesterName,
    sh_from.Name AS FromShop,
    sh_to.Name AS ToShop,
    r.Title AS AlbumTitle,
    mr.ConditionGrade,
    mr.Quantity,
    mr.CurrentPrice,
    mr.RequestedPrice,
    mr.Reason,
    mr.CreatedAt
FROM ManagerRequest mr
JOIN Employee e ON mr.RequestedByEmployeeID = e.EmployeeID
LEFT JOIN Shop sh_from ON mr.FromShopID = sh_from.ShopID
LEFT JOIN Shop sh_to ON mr.ToShopID = sh_to.ShopID
LEFT JOIN ReleaseAlbum r ON mr.ReleaseID = r.ReleaseID
WHERE mr.Status = 'Pending';
```

### 4.2.2 Inventory Analysis Views

**Low Stock Alert:**
```sql
CREATE OR REPLACE VIEW vw_low_stock_alert AS
SELECT
    ShopID, ShopName, ReleaseID, Title,
    ArtistName, ConditionGrade, AvailableQuantity
FROM vw_inventory_summary
WHERE AvailableQuantity < 3
ORDER BY AvailableQuantity ASC;
```

**Dead Stock Alert (60+ days):**
```sql
CREATE OR REPLACE VIEW vw_dead_stock_alert AS
SELECT
    r.Title,
    r.ArtistName,
    s.BatchNo,
    s.AcquiredDate,
    DATEDIFF(NOW(), s.AcquiredDate) AS DaysInStock,
    sh.Name AS ShopName
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
  AND s.AcquiredDate < DATE_SUB(NOW(), INTERVAL 60 DAY)
ORDER BY s.AcquiredDate ASC;
```

---

## 4.3 Index Strategy

The system implements **40+ indexes** including **8 covering indexes** for performance optimization.

### 4.3.1 Standard Indexes

| Table | Index Name | Columns | Purpose |
|-------|------------|---------|---------|
| ReleaseAlbum | idx_release_artist | ArtistName | Artist search |
| ReleaseAlbum | idx_release_genre | Genre | Genre filtering |
| ReleaseAlbum | idx_release_genre_year | Genre, ReleaseYear | Category browsing |
| StockItem | idx_stock_shop_status | ShopID, Status | Store inventory |
| StockItem | idx_stock_release_shop_status | ReleaseID, ShopID, Status | Inventory summary |
| StockItem | idx_stock_status_acquired | Status, AcquiredDate | Dead stock analysis |
| CustomerOrder | idx_order_customer_status | CustomerID, OrderStatus | Customer orders |
| CustomerOrder | idx_order_shop_status | FulfilledByShopID, OrderStatus | Shop order management |
| InventoryTransfer | idx_transfer_status_date | Status, TransferDate | Pending transfers |

### 4.3.2 Covering Indexes

Covering indexes eliminate table lookups by including all columns needed for specific queries:

```sql
-- Stock availability covering index
CREATE INDEX idx_stock_covering_available
    ON StockItem(ShopID, ReleaseID, Status, ConditionGrade, UnitPrice);

-- Order fulfillment covering index
CREATE INDEX idx_order_fulfillment_covering
    ON CustomerOrder(FulfilledByShopID, FulfillmentType, OrderStatus,
                     OrderDate, TotalAmount);

-- Transfer status covering index
CREATE INDEX idx_transfer_status_covering
    ON InventoryTransfer(Status, FromShopID, ToShopID,
                         StockItemID, TransferDate);

-- Pending request covering index
CREATE INDEX idx_request_pending_covering
    ON ManagerRequest(Status, RequestType, FromShopID,
                      ReleaseID, ConditionGrade, CreatedAt);
```

### 4.3.3 Virtual Column Index

Birthday month filtering uses a virtual column for efficient indexing:

```sql
ALTER TABLE Customer
    ADD COLUMN b_month INT AS (MONTH(Birthday)) VIRTUAL;

CREATE INDEX idx_customer_birthday_month ON Customer(b_month);
```

---

## 4.4 Stored Procedures

The system implements **29 stored procedures** encapsulating critical business logic with transaction safety.

### 4.4.1 Procurement Procedures

**Receive Supplier Order:**
```sql
CREATE PROCEDURE sp_receive_supplier_order(
    IN p_order_id INT,
    IN p_batch_no VARCHAR(50),
    IN p_condition_grade VARCHAR(10),
    IN p_markup_rate DECIMAL(3,2)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        RESIGNAL;
    END;

    -- Validate order status
    SELECT Status INTO v_status FROM SupplierOrder WHERE SupplierOrderID = p_order_id;
    IF v_status != 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order not pending';
    END IF;

    -- Update order status
    UPDATE SupplierOrder SET Status = 'Received', ReceivedDate = NOW()
    WHERE SupplierOrderID = p_order_id;

    -- Generate StockItems from order lines
    OPEN cur;
    read_loop: LOOP
        -- Create individual StockItem records with calculated pricing
        INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID,
                              BatchNo, ConditionGrade, Status, UnitPrice)
        VALUES (v_release_id, v_shop_id, 'Supplier', p_order_id,
                p_batch_no, v_condition, 'Available', v_unit_cost * (1 + p_markup_rate));
    END LOOP;
    CLOSE cur;
END
```

### 4.4.2 Sales Procedures

**Create POS Order (Atomic):**
```sql
CREATE PROCEDURE sp_create_pos_order(
    IN p_employee_id INT,
    IN p_shop_id INT,
    IN p_customer_id INT,
    IN p_stock_ids TEXT,  -- Comma-separated list
    OUT p_order_id INT
)
BEGIN
    -- Create order header
    INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID,
        ProcessedByEmployeeID, OrderType, OrderStatus)
    VALUES (p_customer_id, p_shop_id, p_employee_id, 'InStore', 'Completed');

    SET p_order_id = LAST_INSERT_ID();

    -- Add order lines and update stock status
    -- Apply membership discount
    -- Award loyalty points (via trigger)
END
```

### 4.4.3 Manager Request Procedures

**Respond to Request:**
```sql
CREATE PROCEDURE sp_respond_to_request(
    IN p_request_id INT,
    IN p_admin_id INT,
    IN p_status VARCHAR(20),  -- 'Approved' or 'Rejected'
    IN p_response_note TEXT
)
BEGIN
    -- Update request status
    UPDATE ManagerRequest
    SET Status = p_status,
        AdminResponseNote = p_response_note,
        RespondedByEmployeeID = p_admin_id,
        UpdatedAt = NOW()
    WHERE RequestID = p_request_id;

    -- If approved transfer request, create InventoryTransfer record
    IF p_status = 'Approved' AND v_request_type = 'TransferRequest' THEN
        -- Create transfer records for fulfillment
    END IF;

    -- If approved price adjustment, update stock prices
    IF p_status = 'Approved' AND v_request_type = 'PriceAdjustment' THEN
        UPDATE StockItem SET UnitPrice = v_requested_price
        WHERE ReleaseID = v_release_id AND ConditionGrade = v_condition;
    END IF;
END
```

---

## 4.5 Triggers

The system uses **15 triggers** to enforce business rules and maintain data integrity.

### 4.5.1 Order Completion Trigger

Automatically awards points and upgrades membership tier:

```sql
CREATE TRIGGER trg_after_order_complete
AFTER UPDATE ON CustomerOrder
FOR EACH ROW
BEGIN
    IF NEW.OrderStatus = 'Completed' AND OLD.OrderStatus != 'Completed'
       AND NEW.CustomerID IS NOT NULL THEN
        -- Calculate points (exclude shipping)
        SET v_goods_amount = NEW.TotalAmount - COALESCE(NEW.ShippingCost, 0);
        SET v_points_to_add = FLOOR(v_goods_amount);

        -- Update customer points
        UPDATE Customer SET Points = Points + v_points_to_add
        WHERE CustomerID = NEW.CustomerID;

        -- Auto-upgrade tier
        UPDATE Customer SET TierID = (
            SELECT TierID FROM MembershipTier
            WHERE Points >= MinPoints ORDER BY MinPoints DESC LIMIT 1
        ) WHERE CustomerID = NEW.CustomerID;
    END IF;
END
```

### 4.5.2 Inventory Transfer Trigger

Automatically moves stock to destination shop:

```sql
CREATE TRIGGER trg_after_transfer_complete
AFTER UPDATE ON InventoryTransfer
FOR EACH ROW
BEGIN
    IF NEW.Status = 'Completed' AND OLD.Status != 'Completed' THEN
        UPDATE StockItem
        SET ShopID = NEW.ToShopID, Status = 'Available'
        WHERE StockItemID = NEW.StockItemID;
    END IF;
END
```

### 4.5.3 Order Total Recalculation Triggers

Automatically recalculates order totals when lines change:

```sql
CREATE TRIGGER trg_after_order_line_insert
AFTER INSERT ON OrderLine
FOR EACH ROW
BEGIN
    UPDATE CustomerOrder
    SET TotalAmount = (
        SELECT SUM(PriceAtSale) FROM OrderLine WHERE OrderID = NEW.OrderID
    ) WHERE OrderID = NEW.OrderID;
END
```

### 4.5.4 Polymorphic FK Validation Trigger

Validates source order references:

```sql
CREATE TRIGGER trg_before_stock_item_insert
BEFORE INSERT ON StockItem
FOR EACH ROW
BEGIN
    IF NEW.SourceType = 'Supplier' THEN
        IF NOT EXISTS (SELECT 1 FROM SupplierOrder
                       WHERE SupplierOrderID = NEW.SourceOrderID
                       AND Status = 'Received') THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid supplier order reference';
        END IF;
    ELSEIF NEW.SourceType = 'Buyback' THEN
        IF NOT EXISTS (SELECT 1 FROM BuybackOrder
                       WHERE BuybackOrderID = NEW.SourceOrderID) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid buyback order reference';
        END IF;
    END IF;
END
```

---

## 4.6 Event Scheduler

Automatic cleanup of expired order reservations:

```sql
CREATE EVENT evt_release_expired_reservations
ON SCHEDULE EVERY 5 MINUTE
DO
BEGIN
    -- Cancel orders pending > 15 minutes
    UPDATE CustomerOrder
    SET OrderStatus = 'Cancelled'
    WHERE OrderStatus = 'Pending'
      AND OrderDate < DATE_SUB(NOW(), INTERVAL 15 MINUTE);

    -- Trigger will release reserved stock
END
```

---

## 4.7 Security Implementation

### 4.7.1 Password Security
- BCrypt hashing (cost factor 10)
- Passwords never stored in plaintext
- Example hash: `$2y$10$dfU5tM5IPYgDKUliWz6yg...`

### 4.7.2 Session Management
- PHP session-based authentication
- Role stored in session after login
- Session validation on every protected page

### 4.7.3 SQL Injection Prevention
- PDO prepared statements throughout
- Parameterized queries only

```php
$stmt = $pdo->prepare("SELECT * FROM Employee WHERE Username = ?");
$stmt->execute([$username]);
```

---

*Next section: Advanced SQL Queries*
