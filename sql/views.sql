-- 1. [Customer View] Browse Catalog
-- Only shows items that are currently available. Hides cost/supplier info.
CREATE OR REPLACE VIEW vw_customer_catalog AS
SELECT 
    s.StockItemID,
    r.Title,
    r.ArtistName,
    r.Genre,
    s.ConditionGrade,
    s.UnitPrice,
    sh.Name AS LocationName
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available';

-- 2. [Customer View] My Orders
-- Joins Order, OrderLine and Product info for display
CREATE OR REPLACE VIEW vw_customer_order_history AS
SELECT 
    co.OrderID,
    co.CustomerID,
    co.OrderDate,
    co.OrderStatus,
    co.TotalAmount,
    r.Title AS AlbumTitle,
    ol.PriceAtSale
FROM CustomerOrder co
JOIN OrderLine ol ON co.OrderID = ol.OrderID
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID;

-- 3. [Staff View] POS Lookup
-- Shows BatchNo for specific item identification, essential for physical sales
CREATE OR REPLACE VIEW vw_staff_pos_lookup AS
SELECT 
    s.StockItemID,
    s.ShopID,
    r.Title,
    r.ArtistName,
    s.BatchNo,
    s.ConditionGrade,
    s.UnitPrice,
    s.Status
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID;

-- 4. [Staff View] Pending Pickups (BOPIS)
-- Used in 'staff/pickup.php'
CREATE OR REPLACE VIEW vw_staff_bopis_pending AS
SELECT 
    co.OrderID,
    co.FulfilledByShopID AS ShopID,
    c.Name AS CustomerName,
    co.OrderDate,
    co.OrderStatus
FROM CustomerOrder co
JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.OrderType = 'Online' AND co.OrderStatus = 'Paid'; 
-- 'Paid' status implies ready for pickup/shipping in this workflow

-- 5. [Manager View] Shop Performance
-- Aggregates sales by Shop
CREATE OR REPLACE VIEW vw_manager_shop_performance AS
SELECT 
    sh.Name AS ShopName,
    COUNT(DISTINCT co.OrderID) AS TotalOrders,
    SUM(co.TotalAmount) AS Revenue
FROM Shop sh
LEFT JOIN CustomerOrder co ON sh.ShopID = co.FulfilledByShopID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY sh.Name;