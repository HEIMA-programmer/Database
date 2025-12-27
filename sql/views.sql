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
-- 改为（支持两种自提方式）：
WHERE co.OrderStatus = 'Paid' AND co.OrderType = 'InStore';

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
-- 【修复】使用 LEFT JOIN 支持匿名客户回购（CustomerID 可为 NULL）
CREATE OR REPLACE VIEW vw_buyback_orders AS
SELECT
    bo.BuybackOrderID,
    COALESCE(c.Name, 'Walk-in Customer') AS CustomerName,
    COALESCE(c.Email, '-') AS CustomerEmail,
    e.Name AS ProcessedBy,
    sh.Name AS ShopName,
    bo.BuybackDate,
    bo.Status,
    bo.TotalPayment,
    COUNT(bol.ReleaseID) AS ItemTypes
FROM BuybackOrder bo
LEFT JOIN Customer c ON bo.CustomerID = c.CustomerID
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

-- ================================================
-- 【架构重构】新增视图 - 消除 PHP 直接物理表访问
-- ================================================

-- 16. [架构重构] 库存状态检查视图 - 用于购物车验证
-- 替换 cart_action.php 中的直接 StockItem 查询
CREATE OR REPLACE VIEW vw_stock_item_status AS
SELECT
    StockItemID,
    ReleaseID,
    ShopID,
    Status,
    UnitPrice,
    ConditionGrade
FROM StockItem;

-- 17. [架构重构] 客户查找视图 - 通过 Email 查找会员
-- 替换 pos_checkout.php 中的直接 Customer 查询
CREATE OR REPLACE VIEW vw_customer_lookup AS
SELECT
    CustomerID,
    Name,
    Email,
    TierID,
    Points
FROM Customer;

-- 18. [架构重构] 商品详情视图 - 包含完整的专辑和店铺信息
-- 替换 product.php 中的多表联接查询
CREATE OR REPLACE VIEW vw_product_detail AS
SELECT
    s.StockItemID,
    s.ReleaseID,
    s.ShopID,
    s.Status,
    s.ConditionGrade,
    s.UnitPrice,
    s.BatchNo,
    s.AcquiredDate,
    r.Title,
    r.ArtistName,
    r.LabelName,
    r.ReleaseYear,
    r.Genre,
    r.Format,
    r.Description,
    sh.Name AS ShopName,
    sh.Type AS ShopType
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID;

-- 19. [架构重构] 同款商品替代库存视图
-- 替换 product.php 中的其他库存查询
CREATE OR REPLACE VIEW vw_product_alternatives AS
SELECT
    s.StockItemID,
    s.ReleaseID,
    s.ConditionGrade,
    s.UnitPrice,
    s.ShopID,
    sh.Name AS ShopName
FROM StockItem s
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available';

-- 20. [架构重构] 员工库存详细列表视图
-- 替换 inventory.php 中的详细查询
CREATE OR REPLACE VIEW vw_staff_inventory_detail AS
SELECT
    s.StockItemID,
    s.ShopID,
    s.ReleaseID,
    s.BatchNo,
    s.ConditionGrade,
    s.UnitPrice,
    s.Status,
    s.AcquiredDate,
    r.Title,
    r.ArtistName,
    DATEDIFF(NOW(), s.AcquiredDate) AS DaysInStock
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID;

-- 21. [架构重构] 取货订单验证视图
-- 替换 pickup.php 中的直接 CustomerOrder 查询
CREATE OR REPLACE VIEW vw_order_for_pickup AS
SELECT
    OrderID,
    CustomerID,
    FulfilledByShopID AS ShopID,
    TotalAmount,
    OrderStatus,
    OrderType
FROM CustomerOrder;

-- 22. [架构重构] 专辑简单列表视图 - 用于下拉选择
-- 替换 buyback.php 中的直接 ReleaseAlbum 查询
CREATE OR REPLACE VIEW vw_release_simple_list AS
SELECT
    ReleaseID,
    Title,
    ArtistName,
    Genre,
    Format,
    ReleaseYear
FROM ReleaseAlbum
ORDER BY Title;

-- 23. [架构重构] 客户简单列表视图 - 用于下拉选择
-- 替换 buyback.php 中的直接 Customer 查询
CREATE OR REPLACE VIEW vw_customer_simple_list AS
SELECT
    CustomerID,
    Name,
    Email
FROM Customer
ORDER BY Name;

-- 24. [架构重构] 待支付订单视图 - 用于支付页面验证
-- 替换 pay.php 中的直接 CustomerOrder 查询
CREATE OR REPLACE VIEW vw_customer_pending_order AS
SELECT
    co.OrderID,
    co.CustomerID,
    co.TotalAmount,
    co.OrderStatus,
    co.OrderDate,
    co.OrderType,
    co.FulfilledByShopID
FROM CustomerOrder co
WHERE co.OrderStatus = 'Pending';

-- 25. [架构重构] 订单预留商品验证视图
-- 替换 pay.php 中的直接 OrderLine + StockItem 查询
CREATE OR REPLACE VIEW vw_order_reserved_items AS
SELECT
    ol.OrderID,
    ol.StockItemID,
    ol.PriceAtSale,
    s.Status AS StockStatus,
    s.ReleaseID
FROM OrderLine ol
JOIN StockItem s ON ol.StockItemID = s.StockItemID;

-- 26. [架构重构] 员工认证视图
-- 替换 login.php 中的直接 Employee + Shop 查询
CREATE OR REPLACE VIEW vw_auth_employee AS
SELECT
    e.EmployeeID,
    e.Name,
    e.Username,
    e.PasswordHash,
    e.Role,
    e.ShopID,
    s.Name AS ShopName
FROM Employee e
JOIN Shop s ON e.ShopID = s.ShopID;

-- 27. [架构重构] 客户认证视图
-- 替换 login.php 中的直接 Customer 查询
CREATE OR REPLACE VIEW vw_auth_customer AS
SELECT
    CustomerID,
    Name,
    Email,
    PasswordHash,
    Birthday,
    TierID,
    Points
FROM Customer;

-- 28. [架构重构] KPI 统计视图
-- 替换 dashboard.php 中的直接聚合查询
CREATE OR REPLACE VIEW vw_kpi_stats AS
SELECT
    (SELECT COALESCE(SUM(TotalAmount), 0) FROM CustomerOrder WHERE OrderStatus != 'Cancelled') AS TotalSales,
    (SELECT COUNT(*) FROM CustomerOrder WHERE OrderStatus IN ('Pending', 'Paid', 'Shipped')) AS ActiveOrders,
    (SELECT COUNT(*) FROM vw_low_stock_alert) AS LowStockCount;

-- 29. [架构重构] 店铺列表视图
-- 通用店铺查询
CREATE OR REPLACE VIEW vw_shop_list AS
SELECT
    ShopID,
    Name,
    Type,
    Address
FROM Shop;

-- 30. [架构重构] 会员等级规则视图
-- 替换 profile.php 和 register.php 中的直接 MembershipTier 查询
CREATE OR REPLACE VIEW vw_membership_tier_rules AS
SELECT
    TierID,
    TierName,
    MinPoints,
    DiscountRate
FROM MembershipTier
ORDER BY MinPoints ASC;

-- 31. [架构重构] 在线订单履约视图
-- 替换 fulfillment.php 中的直接查询
CREATE OR REPLACE VIEW vw_staff_online_orders_pending AS
SELECT
    co.OrderID,
    co.CustomerID,
    co.OrderDate,
    co.OrderStatus,
    co.TotalAmount,
    co.OrderType,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    (SELECT COUNT(*) FROM OrderLine WHERE OrderID = co.OrderID) AS ItemCount
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.OrderType = 'Online'
  AND co.OrderStatus IN ('Paid', 'Shipped');

-- 32. [架构重构] 月度销售报表视图
-- 替换 reports.php 中的直接聚合查询
CREATE OR REPLACE VIEW vw_report_monthly_sales AS
SELECT
    DATE_FORMAT(OrderDate, '%Y-%m') AS SalesMonth,
    COUNT(*) AS OrderCount,
    SUM(TotalAmount) AS MonthlyRevenue
FROM CustomerOrder
WHERE OrderStatus != 'Cancelled'
GROUP BY SalesMonth
ORDER BY SalesMonth DESC;

-- ================================================
-- 33. [新增] 客户目录分组视图 - 按专辑分组显示
-- 解决问题：同一专辑多库存显示多个卡片的问题
-- ================================================
CREATE OR REPLACE VIEW vw_customer_catalog_grouped AS
SELECT
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    r.Format,
    r.ReleaseYear,
    r.Description,
    MIN(s.UnitPrice) AS MinPrice,
    MAX(s.UnitPrice) AS MaxPrice,
    COUNT(*) AS TotalAvailable,
    GROUP_CONCAT(DISTINCT s.ConditionGrade ORDER BY
        FIELD(s.ConditionGrade, 'New', 'Mint', 'NM', 'VG+', 'VG', 'G', 'Fair', 'Poor')
        SEPARATOR ', ') AS AvailableConditions
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
  AND sh.Type = 'Warehouse'
GROUP BY r.ReleaseID, r.Title, r.ArtistName, r.Genre, r.Format, r.ReleaseYear, r.Description;

-- ================================================
-- 34. [新增] 专辑库存详情视图 - 按条件分组
-- 用于商品详情页显示各条件的库存数量
-- ================================================
CREATE OR REPLACE VIEW vw_release_stock_by_condition AS
SELECT
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    r.LabelName,
    r.ReleaseYear,
    r.Description,
    s.ConditionGrade,
    COUNT(*) AS AvailableQuantity,
    MIN(s.UnitPrice) AS UnitPrice,
    sh.Name AS LocationName,
    sh.ShopID
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
  AND sh.Type = 'Warehouse'
GROUP BY r.ReleaseID, r.Title, r.ArtistName, r.Genre, r.LabelName, r.ReleaseYear, r.Description,
         s.ConditionGrade, sh.Name, sh.ShopID
ORDER BY FIELD(s.ConditionGrade, 'New', 'Mint', 'NM', 'VG+', 'VG', 'G', 'Fair', 'Poor');
