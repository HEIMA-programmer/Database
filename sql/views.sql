-- ========================================
-- Views for Refactored Schema
-- 重构后的视图 - 包含库存汇总视图
-- ========================================

-- ================================================
-- 核心业务视图
-- ================================================

-- 【新增】库存汇总视图 - 按Release和Shop统计可用库存
-- 用于在业务流程中快速检查库存数量
CREATE OR REPLACE VIEW vw_inventory_summary AS
SELECT
    s.ShopID,
    sh.Name AS ShopName,
    s.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    s.ConditionGrade,
    COUNT(*) AS AvailableQuantity,
    MIN(s.UnitPrice) AS MinPrice,
    MAX(s.UnitPrice) AS MaxPrice,
    AVG(s.UnitPrice) AS AvgPrice
FROM StockItem s
JOIN Shop sh ON s.ShopID = sh.ShopID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
WHERE s.Status = 'Available'
GROUP BY s.ShopID, sh.Name, s.ReleaseID, r.Title, r.ArtistName, r.Genre, s.ConditionGrade;

-- 【新增】低库存预警视图 - 库存少于3件的商品
CREATE OR REPLACE VIEW vw_low_stock_alert AS
SELECT
    ShopID,
    ShopName,
    ReleaseID,
    Title,
    ArtistName,
    ConditionGrade,
    AvailableQuantity
FROM vw_inventory_summary
WHERE AvailableQuantity < 3
ORDER BY AvailableQuantity ASC, ShopID;

-- 【新增】死库存预警视图 - 超过60天未售出的商品
CREATE OR REPLACE VIEW vw_dead_stock_alert AS
SELECT
    r.Title,
    r.ArtistName,
    s.BatchNo,
    s.AcquiredDate,
    DATEDIFF(NOW(), s.AcquiredDate) as DaysInStock,
    sh.Name as ShopName,
    sh.ShopID
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
  AND s.AcquiredDate < DATE_SUB(NOW(), INTERVAL 60 DAY)
ORDER BY s.AcquiredDate ASC;

-- ================================================
-- 用户访问视图 (View-based Access Control)
-- ================================================

-- 1. [Customer View] Browse Catalog
CREATE OR REPLACE VIEW vw_customer_catalog AS
SELECT
    s.StockItemID,
    s.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    r.Format,
    r.ReleaseYear,
    r.Description,
    s.ConditionGrade,
    s.UnitPrice,
    sh.Name AS LocationName,
    sh.ShopID
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
  AND sh.Type = 'Warehouse';

-- 2. [Customer View] Order History with Details
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

-- 3. [Customer View] My Orders List
CREATE OR REPLACE VIEW vw_customer_my_orders_list AS
SELECT
    OrderID,
    CustomerID,
    OrderDate,
    OrderStatus,
    TotalAmount,
    OrderType
FROM CustomerOrder;

-- 4. [Customer View] Profile & Membership Info
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

-- 5. [Staff View] POS Lookup - 查看本店库存
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

-- 6. [Staff View] Pending Pickups (BOPIS - Buy Online Pick up In Store)
-- 【修复】BOPIS 是在线下单到店自提，所以 OrderType 应该是 'Online' 而非 'InStore'
CREATE OR REPLACE VIEW vw_staff_bopis_pending AS
SELECT
    co.OrderID,
    co.FulfilledByShopID AS ShopID,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    co.OrderDate,
    co.OrderStatus,
    co.TotalAmount
FROM CustomerOrder co
JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.OrderType = 'Online' AND co.OrderStatus = 'Paid';

-- 7. [Manager View] Shop Performance
CREATE OR REPLACE VIEW vw_manager_shop_performance AS
SELECT
    sh.ShopID,
    sh.Name AS ShopName,
    sh.Type,
    COUNT(DISTINCT co.OrderID) AS TotalOrders,
    COALESCE(SUM(co.TotalAmount), 0) AS Revenue,
    COUNT(DISTINCT s.StockItemID) AS CurrentInventoryCount
FROM Shop sh
LEFT JOIN CustomerOrder co ON sh.ShopID = co.FulfilledByShopID
    AND co.OrderStatus IN ('Paid', 'Completed')
LEFT JOIN StockItem s ON sh.ShopID = s.ShopID
    AND s.Status = 'Available'
GROUP BY sh.ShopID, sh.Name, sh.Type;

-- 8. [Manager View] Pending Transfers
CREATE OR REPLACE VIEW vw_manager_pending_transfers AS
SELECT
    t.TransferID,
    t.StockItemID,
    r.Title,
    si.BatchNo,
    si.ConditionGrade,
    s1.Name AS FromShopName,
    s2.Name AS ToShopName,
    t.TransferDate,
    t.Status,
    e.Name AS AuthorizedBy
FROM InventoryTransfer t
JOIN StockItem si ON t.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN Shop s1 ON t.FromShopID = s1.ShopID
JOIN Shop s2 ON t.ToShopID = s2.ShopID
JOIN Employee e ON t.AuthorizedByEmployeeID = e.EmployeeID
WHERE t.Status IN ('Pending', 'InTransit');

-- 9. [Admin View] Release List
CREATE OR REPLACE VIEW vw_admin_release_list AS
SELECT
    r.*,
    COUNT(DISTINCT s.StockItemID) AS TotalStockItems,
    SUM(CASE WHEN s.Status = 'Available' THEN 1 ELSE 0 END) AS AvailableItems,
    SUM(CASE WHEN s.Status = 'Sold' THEN 1 ELSE 0 END) AS SoldItems
FROM ReleaseAlbum r
LEFT JOIN StockItem s ON r.ReleaseID = s.ReleaseID
GROUP BY r.ReleaseID;

-- 10. [Admin View] Employee List
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

-- 11. [Admin View] Customer List
CREATE OR REPLACE VIEW vw_admin_customer_list AS
SELECT
    c.CustomerID,
    c.Name,
    c.Email,
    c.Points,
    c.Birthday,
    mt.TierName,
    COUNT(DISTINCT co.OrderID) AS TotalOrders,
    COALESCE(SUM(co.TotalAmount), 0) AS TotalSpent
FROM Customer c
JOIN MembershipTier mt ON c.TierID = mt.TierID
LEFT JOIN CustomerOrder co ON c.CustomerID = co.CustomerID
    AND co.OrderStatus IN ('Paid', 'Completed')
GROUP BY c.CustomerID, c.Name, c.Email, c.Points, c.Birthday, mt.TierName;

-- 12. [Admin View] Supplier Orders
CREATE OR REPLACE VIEW vw_admin_supplier_orders AS
SELECT
    so.SupplierOrderID,
    s.Name AS SupplierName,
    e.Name AS CreatedBy,
    sh.Name AS DestinationShop,
    so.OrderDate,
    so.Status,
    so.ReceivedDate,
    so.TotalCost,
    COUNT(sol.ReleaseID) AS ItemTypes,
    COALESCE(SUM(sol.Quantity), 0) AS TotalItems
FROM SupplierOrder so
JOIN Supplier s ON so.SupplierID = s.SupplierID
JOIN Employee e ON so.CreatedByEmployeeID = e.EmployeeID
LEFT JOIN Shop sh ON so.DestinationShopID = sh.ShopID
LEFT JOIN SupplierOrderLine sol ON so.SupplierOrderID = sol.SupplierOrderID
GROUP BY so.SupplierOrderID, s.Name, e.Name, sh.Name, so.OrderDate, so.Status, so.ReceivedDate, so.TotalCost;

-- 13. [Admin/Staff View] Buyback Orders
CREATE OR REPLACE VIEW vw_buyback_orders AS
SELECT
    bo.BuybackOrderID,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    e.Name AS ProcessedBy,
    sh.Name AS ShopName,
    bo.BuybackDate,
    bo.Status,
    bo.TotalPayment,
    COUNT(bol.ReleaseID) AS ItemTypes
FROM BuybackOrder bo
JOIN Customer c ON bo.CustomerID = c.CustomerID
JOIN Employee e ON bo.ProcessedByEmployeeID = e.EmployeeID
JOIN Shop sh ON bo.ShopID = sh.ShopID
LEFT JOIN BuybackOrderLine bol ON bo.BuybackOrderID = bol.BuybackOrderID
GROUP BY bo.BuybackOrderID, c.Name, c.Email, e.Name, sh.Name, bo.BuybackDate, bo.Status, bo.TotalPayment;

-- ================================================
-- 分析报表视图
-- ================================================

-- 14. [Report] Sales by Genre (with turnover metrics)
CREATE OR REPLACE VIEW vw_report_sales_by_genre AS
SELECT
    r.Genre,
    COUNT(DISTINCT ol.OrderID) AS TotalOrders,
    COUNT(ol.StockItemID) AS ItemsSold,
    SUM(ol.PriceAtSale) AS TotalRevenue,
    AVG(ol.PriceAtSale) AS AvgPrice,
    AVG(DATEDIFF(COALESCE(s.DateSold, NOW()), s.AcquiredDate)) AS AvgDaysToSell
FROM OrderLine ol
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY r.Genre
ORDER BY TotalRevenue DESC;

-- 15. [Report] Top Customers (with RankPosition using window function)
CREATE OR REPLACE VIEW vw_report_top_customers AS
SELECT
    CustomerID,
    Name,
    Email,
    TierName,
    Points,
    OrderCount,
    TotalSpent,
    LastOrderDate,
    RANK() OVER (ORDER BY TotalSpent DESC) AS RankPosition
FROM (
    SELECT
        c.CustomerID,
        c.Name,
        c.Email,
        mt.TierName,
        c.Points,
        COUNT(DISTINCT co.OrderID) AS OrderCount,
        COALESCE(SUM(co.TotalAmount), 0) AS TotalSpent,
        MAX(co.OrderDate) AS LastOrderDate
    FROM Customer c
    JOIN MembershipTier mt ON c.TierID = mt.TierID
    LEFT JOIN CustomerOrder co ON c.CustomerID = co.CustomerID
        AND co.OrderStatus IN ('Paid', 'Completed')
    GROUP BY c.CustomerID, c.Name, c.Email, mt.TierName, c.Points
) AS customer_stats
ORDER BY TotalSpent DESC
LIMIT 50;
