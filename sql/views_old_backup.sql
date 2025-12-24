-- 1. [Customer View] Browse Catalog (只显示仓库的库存供线上购买)
CREATE OR REPLACE VIEW vw_customer_catalog AS
SELECT
    s.StockItemID,
    s.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    r.Format,
    s.ConditionGrade,
    s.UnitPrice,
    sh.Name AS LocationName
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
  AND sh.Type = 'Warehouse';

-- 2. [Customer View] Order History (Details)
-- 用于查看订单包含的具体商品详情
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
CREATE OR REPLACE VIEW vw_staff_bopis_pending AS
SELECT 
    co.OrderID,
    co.FulfilledByShopID AS ShopID,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    co.OrderDate,
    co.OrderStatus
FROM CustomerOrder co
JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.OrderType = 'InStore' AND co.OrderStatus = 'Paid';

-- 5. [Manager View] Shop Performance
CREATE OR REPLACE VIEW vw_manager_shop_performance AS
SELECT 
    sh.Name AS ShopName,
    COUNT(DISTINCT co.OrderID) AS TotalOrders,
    SUM(co.TotalAmount) AS Revenue
FROM Shop sh
LEFT JOIN CustomerOrder co ON sh.ShopID = co.FulfilledByShopID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY sh.Name;

-- === PHASE 2 新增视图 (View Compliance) ===

-- 6. [Customer View] My Order List (Header Only)
-- 替代原直接查询 CustomerOrder 表
CREATE OR REPLACE VIEW vw_customer_my_orders_list AS
SELECT 
    OrderID,
    CustomerID,
    OrderDate,
    OrderStatus,
    TotalAmount,
    OrderType
FROM CustomerOrder;

-- 7. [Customer View] Profile & Membership Info
-- 替代 profile.php 中的 JOIN 查询
CREATE OR REPLACE VIEW vw_customer_profile_info AS
SELECT 
    c.CustomerID,
    c.Name,
    c.Email,
    c.Points,
    c.Birthday,
    c.TierID,
    mt.TierName,
    mt.DiscountRate,
    mt.MinPoints
FROM Customer c
JOIN MembershipTier mt ON c.TierID = mt.TierID;

-- 8. [Admin View] Release List
-- 替代 products.php 中的查询
CREATE OR REPLACE VIEW vw_admin_release_list AS
SELECT * FROM ReleaseAlbum;

-- 9. [Admin View] Employee List
-- 替代 users.php 中的员工查询
-- UserRole table removed; Role is now ENUM in Employee table
CREATE OR REPLACE VIEW vw_admin_employee_list AS
SELECT
    e.EmployeeID,
    e.Name,
    e.Username,
    e.HireDate,
    e.Role,
    e.ShopID,
    s.Name AS ShopName
FROM Employee e
JOIN Shop s ON e.ShopID = s.ShopID;

-- 10. [Admin View] Customer List
-- 替代 users.php 中的顾客查询
CREATE OR REPLACE VIEW vw_admin_customer_list AS
SELECT 
    c.CustomerID,
    c.Name,
    c.Email,
    c.Points,
    c.Birthday,
    mt.TierName
FROM Customer c 
JOIN MembershipTier mt ON c.TierID = mt.TierID;