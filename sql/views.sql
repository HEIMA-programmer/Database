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
-- 【修复】移除 Warehouse 限制，支持所有店铺类型，添加 ShopType 字段
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
    sh.ShopID,
    sh.Type AS ShopType
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available';

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
-- 【修复】添加 FulfillmentType 字段，用于正确区分 Pickup 和 Delivery 订单
CREATE OR REPLACE VIEW vw_customer_my_orders_list AS
SELECT
    OrderID,
    CustomerID,
    OrderDate,
    OrderStatus,
    TotalAmount,
    OrderType,
    FulfillmentType
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
-- 【修复】BOPIS 是在线下单到店自提，OrderType 应该是 'Online'
-- 同时支持 InStore 订单（店内已付款待提货）
CREATE OR REPLACE VIEW vw_staff_bopis_pending AS
SELECT
    co.OrderID,
    co.FulfilledByShopID AS ShopID,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    co.OrderDate,
    co.OrderStatus,
    co.TotalAmount,
    co.OrderType
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.OrderStatus = 'Paid' AND co.FulfillmentType = 'Pickup';

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
-- 【修复】使用 LEFT JOIN 支持 ShopID 为 NULL 的员工（如系统管理员）
CREATE OR REPLACE VIEW vw_admin_employee_list AS
SELECT
    e.EmployeeID,
    e.Name,
    e.Username,
    e.HireDate,
    e.Role,
    e.ShopID,
    COALESCE(s.Name, 'Headquarters') AS ShopName
FROM Employee e
LEFT JOIN Shop s ON e.ShopID = s.ShopID;

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
-- 【修复】添加 ShopType 字段，用于区分仓库和门店员工的菜单显示
-- 【修复】使用 LEFT JOIN 支持 ShopID 为 NULL 的全局管理员（如 Admin）
CREATE OR REPLACE VIEW vw_auth_employee AS
SELECT
    e.EmployeeID,
    e.Name,
    e.Username,
    e.PasswordHash,
    e.Role,
    e.ShopID,
    COALESCE(s.Name, 'Headquarters') AS ShopName,
    COALESCE(s.Type, 'Retail') AS ShopType
FROM Employee e
LEFT JOIN Shop s ON e.ShopID = s.ShopID;

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
-- 35. [新增] POS历史交易记录视图 - 门店销售历史
-- 用于POS界面显示历史交易记录
-- ================================================
CREATE OR REPLACE VIEW vw_staff_pos_history AS
SELECT
    co.OrderID,
    co.OrderDate,
    co.TotalAmount,
    co.OrderStatus,
    co.FulfilledByShopID AS ShopID,
    co.ProcessedByEmployeeID,
    COALESCE(c.Name, 'Walk-in Customer') AS CustomerName,
    e.Name AS ProcessedByName,
    (SELECT COUNT(*) FROM OrderLine WHERE OrderID = co.OrderID) AS ItemCount
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
LEFT JOIN Employee e ON co.ProcessedByEmployeeID = e.EmployeeID
WHERE co.OrderType = 'InStore'
ORDER BY co.OrderDate DESC;

-- ================================================
-- 36. [新增] Pickup历史记录视图 - 已完成的自提订单
-- 用于Pickup界面显示历史记录
-- ================================================
CREATE OR REPLACE VIEW vw_staff_pickup_history AS
SELECT
    co.OrderID,
    co.OrderDate,
    co.TotalAmount,
    co.OrderStatus,
    co.FulfilledByShopID AS ShopID,
    COALESCE(c.Name, 'Guest') AS CustomerName,
    c.Email AS CustomerEmail,
    (SELECT COUNT(*) FROM OrderLine WHERE OrderID = co.OrderID) AS ItemCount
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.FulfillmentType = 'Pickup'
  AND co.OrderStatus = 'Completed'
ORDER BY co.OrderDate DESC;

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
GROUP BY r.ReleaseID, r.Title, r.ArtistName, r.Genre, r.LabelName, r.ReleaseYear, r.Description,
         s.ConditionGrade, sh.Name, sh.ShopID
ORDER BY FIELD(s.ConditionGrade, 'New', 'Mint', 'NM', 'VG+', 'VG', 'G', 'Fair', 'Poor');

-- ================================================
-- 【Manager/Admin申请系统视图】
-- ================================================

-- 37. Manager申请列表视图 - Manager查看自己发出的申请
CREATE OR REPLACE VIEW vw_manager_requests_sent AS
SELECT
    mr.RequestID,
    mr.RequestType,
    mr.FromShopID,
    s1.Name AS FromShopName,
    mr.ToShopID,
    s2.Name AS ToShopName,
    mr.ReleaseID,
    r.Title,
    r.ArtistName,
    mr.ConditionGrade,
    mr.Quantity,
    mr.CurrentPrice,
    mr.RequestedPrice,
    mr.Reason,
    mr.Status,
    mr.AdminResponseNote,
    e1.Name AS RequestedByName,
    e2.Name AS RespondedByName,
    mr.RequestedByEmployeeID,
    mr.CreatedAt,
    mr.UpdatedAt
FROM ManagerRequest mr
JOIN Shop s1 ON mr.FromShopID = s1.ShopID
LEFT JOIN Shop s2 ON mr.ToShopID = s2.ShopID
JOIN ReleaseAlbum r ON mr.ReleaseID = r.ReleaseID
JOIN Employee e1 ON mr.RequestedByEmployeeID = e1.EmployeeID
LEFT JOIN Employee e2 ON mr.RespondedByEmployeeID = e2.EmployeeID
ORDER BY mr.CreatedAt DESC;

-- 38. Admin待处理申请视图 - Admin查看所有待审批的申请
CREATE OR REPLACE VIEW vw_admin_pending_requests AS
SELECT
    mr.RequestID,
    mr.RequestType,
    mr.FromShopID,
    s1.Name AS FromShopName,
    mr.ToShopID,
    s2.Name AS ToShopName,
    mr.ReleaseID,
    r.Title,
    r.ArtistName,
    mr.ConditionGrade,
    mr.Quantity,
    mr.CurrentPrice,
    mr.RequestedPrice,
    mr.Reason,
    mr.Status,
    e1.Name AS RequestedByName,
    mr.RequestedByEmployeeID,
    mr.CreatedAt
FROM ManagerRequest mr
JOIN Shop s1 ON mr.FromShopID = s1.ShopID
LEFT JOIN Shop s2 ON mr.ToShopID = s2.ShopID
JOIN ReleaseAlbum r ON mr.ReleaseID = r.ReleaseID
JOIN Employee e1 ON mr.RequestedByEmployeeID = e1.EmployeeID
WHERE mr.Status = 'Pending'
ORDER BY mr.CreatedAt ASC;

-- 39. Admin所有申请视图 - Admin查看所有申请（包括已处理的）
CREATE OR REPLACE VIEW vw_admin_all_requests AS
SELECT
    mr.RequestID,
    mr.RequestType,
    mr.FromShopID,
    s1.Name AS FromShopName,
    mr.ToShopID,
    s2.Name AS ToShopName,
    mr.ReleaseID,
    r.Title,
    r.ArtistName,
    mr.ConditionGrade,
    mr.Quantity,
    mr.CurrentPrice,
    mr.RequestedPrice,
    mr.Reason,
    mr.Status,
    mr.AdminResponseNote,
    e1.Name AS RequestedByName,
    e2.Name AS RespondedByName,
    mr.RequestedByEmployeeID,
    mr.RespondedByEmployeeID,
    mr.CreatedAt,
    mr.UpdatedAt
FROM ManagerRequest mr
JOIN Shop s1 ON mr.FromShopID = s1.ShopID
LEFT JOIN Shop s2 ON mr.ToShopID = s2.ShopID
JOIN ReleaseAlbum r ON mr.ReleaseID = r.ReleaseID
JOIN Employee e1 ON mr.RequestedByEmployeeID = e1.EmployeeID
LEFT JOIN Employee e2 ON mr.RespondedByEmployeeID = e2.EmployeeID
ORDER BY mr.CreatedAt DESC;

-- 40. 最受欢迎单品视图 - 统计销量最高的专辑
CREATE OR REPLACE VIEW vw_popular_items AS
SELECT
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    COUNT(ol.StockItemID) AS TotalSold,
    SUM(ol.PriceAtSale) AS TotalRevenue
FROM OrderLine ol
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY r.ReleaseID, r.Title, r.ArtistName, r.Genre
ORDER BY TotalSold DESC;

-- 41. 店铺总支出视图（Buyback支出）
CREATE OR REPLACE VIEW vw_shop_total_expense AS
SELECT
    bo.ShopID,
    sh.Name AS ShopName,
    SUM(bo.TotalPayment) AS TotalExpense,
    COUNT(bo.BuybackOrderID) AS BuybackCount
FROM BuybackOrder bo
JOIN Shop sh ON bo.ShopID = sh.ShopID
WHERE bo.Status = 'Completed'
GROUP BY bo.ShopID, sh.Name;

-- 42. 店铺收入明细视图 - 按类型分组（在线售卖/线下取货/POS/Buyback）
-- 【修复】处理FulfillmentType为NULL的旧订单，默认按OrderType推断
-- 【修复】添加COLLATE解决字符集排序规则冲突问题
CREATE OR REPLACE VIEW vw_shop_revenue_by_type AS
SELECT
    co.FulfilledByShopID AS ShopID,
    sh.Name AS ShopName,
    CASE
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Shipping' THEN 'OnlineSales'
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Pickup' THEN 'OnlinePickup'
        WHEN co.OrderType = 'Online' AND (co.FulfillmentType IS NULL OR co.FulfillmentType = '') THEN 'OnlineSales'
        WHEN co.OrderType = 'InStore' THEN 'POS'
        ELSE 'Other'
    END COLLATE utf8mb4_unicode_ci AS RevenueType,
    COUNT(co.OrderID) AS OrderCount,
    SUM(co.TotalAmount) AS Revenue,
    SUM(COALESCE(co.ShippingCost, 0)) AS TotalShipping
FROM CustomerOrder co
JOIN Shop sh ON co.FulfilledByShopID = sh.ShopID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY co.FulfilledByShopID, sh.Name,
    CASE
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Shipping' THEN 'OnlineSales'
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Pickup' THEN 'OnlinePickup'
        WHEN co.OrderType = 'Online' AND (co.FulfillmentType IS NULL OR co.FulfillmentType = '') THEN 'OnlineSales'
        WHEN co.OrderType = 'InStore' THEN 'POS'
        ELSE 'Other'
    END;

-- 43. 按店铺的死库存视图（带condition和数量合并）
CREATE OR REPLACE VIEW vw_dead_stock_by_shop AS
SELECT
    sh.ShopID,
    sh.Name AS ShopName,
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    s.ConditionGrade,
    COUNT(*) AS Quantity,
    MIN(s.UnitPrice) AS UnitPrice,
    MIN(s.AcquiredDate) AS OldestAcquiredDate,
    MAX(DATEDIFF(NOW(), s.AcquiredDate)) AS MaxDaysInStock
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
  AND s.AcquiredDate < DATE_SUB(NOW(), INTERVAL 60 DAY)
GROUP BY sh.ShopID, sh.Name, r.ReleaseID, r.Title, r.ArtistName, s.ConditionGrade
ORDER BY MaxDaysInStock DESC;

-- 44. 按店铺的低库存视图（带condition和数量合并）
CREATE OR REPLACE VIEW vw_low_stock_by_shop AS
SELECT
    sh.ShopID,
    sh.Name AS ShopName,
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    s.ConditionGrade,
    COUNT(*) AS AvailableQuantity,
    MIN(s.UnitPrice) AS UnitPrice
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
GROUP BY sh.ShopID, sh.Name, r.ReleaseID, r.Title, r.ArtistName, s.ConditionGrade
HAVING COUNT(*) < 3
ORDER BY AvailableQuantity ASC;

-- 45. 客户在特定店铺的消费历史视图
-- 【修复】处理FulfillmentType为NULL的旧订单
-- 【修复】添加COLLATE解决字符集排序规则冲突问题
CREATE OR REPLACE VIEW vw_customer_shop_orders AS
SELECT
    co.OrderID,
    co.CustomerID,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    co.FulfilledByShopID AS ShopID,
    sh.Name AS ShopName,
    co.OrderDate,
    co.TotalAmount,
    co.OrderStatus,
    co.OrderType,
    co.FulfillmentType,
    co.ShippingCost,
    CASE
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Shipping' THEN 'OnlineSales'
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Pickup' THEN 'OnlinePickup'
        WHEN co.OrderType = 'Online' AND (co.FulfillmentType IS NULL OR co.FulfillmentType = '') THEN 'OnlineSales'
        WHEN co.OrderType = 'InStore' THEN 'POS'
        ELSE 'Other'
    END COLLATE utf8mb4_unicode_ci AS OrderCategory
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
JOIN Shop sh ON co.FulfilledByShopID = sh.ShopID
WHERE co.OrderStatus IN ('Paid', 'Completed');

-- 46. 客户Buyback历史视图
CREATE OR REPLACE VIEW vw_customer_buyback_history AS
SELECT
    bo.BuybackOrderID,
    bo.CustomerID,
    COALESCE(c.Name, 'Walk-in Customer') AS CustomerName,
    bo.ShopID,
    sh.Name AS ShopName,
    bo.BuybackDate,
    bo.TotalPayment,
    bo.Status,
    r.Title,
    r.ArtistName,
    bol.Quantity,
    bol.UnitPrice,
    bol.ConditionGrade
FROM BuybackOrder bo
LEFT JOIN Customer c ON bo.CustomerID = c.CustomerID
JOIN Shop sh ON bo.ShopID = sh.ShopID
JOIN BuybackOrderLine bol ON bo.BuybackOrderID = bol.BuybackOrderID
JOIN ReleaseAlbum r ON bol.ReleaseID = r.ReleaseID
WHERE bo.Status = 'Completed';

-- 47. 店铺在特定类型的订单明细视图
-- 【修复】处理FulfillmentType为NULL的旧订单
-- 【修复】使用LEFT JOIN确保即使缺少明细数据也能显示订单
-- 【修复】添加COLLATE解决字符集排序规则冲突问题
CREATE OR REPLACE VIEW vw_shop_order_details AS
SELECT
    co.OrderID,
    co.FulfilledByShopID AS ShopID,
    co.CustomerID,
    COALESCE(c.Name, 'Guest') AS CustomerName,
    co.OrderDate,
    co.TotalAmount,
    co.ShippingCost,
    co.OrderStatus,
    co.OrderType,
    co.FulfillmentType,
    CASE
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Shipping' THEN 'OnlineSales'
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Pickup' THEN 'OnlinePickup'
        WHEN co.OrderType = 'Online' AND (co.FulfillmentType IS NULL OR co.FulfillmentType = '') THEN 'OnlineSales'
        WHEN co.OrderType = 'InStore' THEN 'POS'
        ELSE 'Other'
    END COLLATE utf8mb4_unicode_ci AS OrderCategory,
    ol.StockItemID,
    COALESCE(ol.PriceAtSale, 0) AS PriceAtSale,
    COALESCE(r.Title, 'Unknown Album') AS Title,
    COALESCE(r.ArtistName, 'Unknown Artist') AS ArtistName,
    COALESCE(si.ConditionGrade, 'N/A') AS ConditionGrade
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
LEFT JOIN OrderLine ol ON co.OrderID = ol.OrderID
LEFT JOIN StockItem si ON ol.StockItemID = si.StockItemID
LEFT JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
WHERE co.OrderStatus IN ('Paid', 'Completed');

-- 48. 店铺Top消费者视图
CREATE OR REPLACE VIEW vw_shop_top_customers AS
SELECT
    co.FulfilledByShopID AS ShopID,
    co.CustomerID,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    mt.TierName,
    c.Points,
    COUNT(DISTINCT co.OrderID) AS OrderCount,
    SUM(co.TotalAmount) AS TotalSpent,
    MAX(co.OrderDate) AS LastOrderDate
FROM CustomerOrder co
JOIN Customer c ON co.CustomerID = c.CustomerID
JOIN MembershipTier mt ON c.TierID = mt.TierID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY co.FulfilledByShopID, co.CustomerID, c.Name, c.Email, mt.TierName, c.Points
ORDER BY TotalSpent DESC;

-- 49. 按流派销售明细视图（含店铺信息）
CREATE OR REPLACE VIEW vw_sales_by_genre_detail AS
SELECT
    r.Genre,
    co.FulfilledByShopID AS ShopID,
    sh.Name AS ShopName,
    co.OrderID,
    co.CustomerID,
    COALESCE(c.Name, 'Guest') AS CustomerName,
    co.OrderDate,
    co.OrderType,
    co.FulfillmentType,
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    ol.PriceAtSale,
    DATEDIFF(COALESCE(si.DateSold, NOW()), si.AcquiredDate) AS DaysToSell
FROM OrderLine ol
JOIN StockItem si ON ol.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
JOIN Shop sh ON co.FulfilledByShopID = sh.ShopID
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.OrderStatus IN ('Paid', 'Completed');

-- 50. 月度销售明细视图
-- 【修复】处理FulfillmentType为NULL的旧订单
-- 【修复】添加COLLATE解决字符集排序规则冲突问题
CREATE OR REPLACE VIEW vw_monthly_sales_detail AS
SELECT
    DATE_FORMAT(co.OrderDate, '%Y-%m') AS SalesMonth,
    co.FulfilledByShopID AS ShopID,
    sh.Name AS ShopName,
    co.OrderID,
    co.CustomerID,
    COALESCE(c.Name, 'Guest') AS CustomerName,
    co.OrderDate,
    co.TotalAmount,
    co.OrderType,
    co.FulfillmentType,
    CASE
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Shipping' THEN 'OnlineSales'
        WHEN co.OrderType = 'Online' AND co.FulfillmentType = 'Pickup' THEN 'OnlinePickup'
        WHEN co.OrderType = 'Online' AND (co.FulfillmentType IS NULL OR co.FulfillmentType = '') THEN 'OnlineSales'
        WHEN co.OrderType = 'InStore' THEN 'POS'
        ELSE 'Other'
    END COLLATE utf8mb4_unicode_ci AS OrderCategory,
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    ol.PriceAtSale
FROM CustomerOrder co
JOIN Shop sh ON co.FulfilledByShopID = sh.ShopID
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
JOIN OrderLine ol ON co.OrderID = ol.OrderID
JOIN StockItem si ON ol.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
WHERE co.OrderStatus IN ('Paid', 'Completed');

-- 51. 库存售价管理视图（按Release和Condition分组）
CREATE OR REPLACE VIEW vw_stock_price_by_condition AS
SELECT
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    sh.ShopID,
    sh.Name AS ShopName,
    s.ConditionGrade,
    COUNT(*) AS Quantity,
    MIN(s.UnitPrice) AS MinPrice,
    MAX(s.UnitPrice) AS MaxPrice,
    AVG(s.UnitPrice) AS AvgPrice
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN Shop sh ON s.ShopID = sh.ShopID
WHERE s.Status = 'Available'
GROUP BY r.ReleaseID, r.Title, r.ArtistName, r.Genre, sh.ShopID, sh.Name, s.ConditionGrade
ORDER BY r.Title, sh.Name, FIELD(s.ConditionGrade, 'New', 'Mint', 'NM', 'VG+', 'VG');

-- ================================================
-- 【架构重构Phase2】新增视图 - 消除剩余PHP直接表访问
-- ================================================

-- 52. [架构重构] 供应商列表视图
-- 替换 db_procedures.php:getSupplierList() 中的直接表访问
CREATE OR REPLACE VIEW vw_supplier_list AS
SELECT
    SupplierID,
    Name,
    Email
FROM Supplier
ORDER BY Name;

-- 53. [架构重构] 购物车商品验证视图
-- 替换 cart.php 中的直接表访问（验证商品可用性）
CREATE OR REPLACE VIEW vw_cart_item_validation AS
SELECT
    si.StockItemID,
    si.ReleaseID,
    si.UnitPrice,
    si.ConditionGrade,
    si.ShopID,
    si.Status,
    r.Title,
    r.ArtistName
FROM StockItem si
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID;

-- 54. [架构重构] 购物车商品详情视图
-- 替换 cart.php 中的购物车数据获取
CREATE OR REPLACE VIEW vw_cart_items_detail AS
SELECT
    si.StockItemID,
    si.ReleaseID,
    si.UnitPrice,
    si.ConditionGrade,
    si.ShopID,
    si.Status,
    r.Title,
    r.ArtistName,
    s.Name AS ShopName,
    s.Type AS ShopType
FROM StockItem si
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN Shop s ON si.ShopID = s.ShopID;

-- 55. [架构重构] 订单取消验证视图
-- 替换 cancel_order.php 中的直接表访问
CREATE OR REPLACE VIEW vw_order_cancel_validation AS
SELECT
    OrderID,
    CustomerID,
    OrderStatus
FROM CustomerOrder
WHERE OrderStatus = 'Pending';

-- 56. [架构重构] 员工店铺信息视图（包含shopId）
-- 替换 pos.php, fulfillment.php, buyback.php 中的员工信息查询
CREATE OR REPLACE VIEW vw_employee_shop_info AS
SELECT
    e.EmployeeID,
    e.Name AS EmployeeName,
    e.Role,
    e.ShopID,
    COALESCE(s.Name, 'Headquarters') AS ShopName,
    COALESCE(s.Type, 'Retail') AS ShopType
FROM Employee e
LEFT JOIN Shop s ON e.ShopID = s.ShopID;

-- 57. [架构重构] POS库存分组视图
-- 替换 pos.php 中的库存分组查询
CREATE OR REPLACE VIEW vw_pos_stock_grouped AS
SELECT
    si.ShopID,
    si.ReleaseID,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    COUNT(*) AS Quantity,
    MIN(si.UnitPrice) AS UnitPrice,
    GROUP_CONCAT(si.StockItemID ORDER BY si.StockItemID) AS StockItemIds
FROM StockItem si
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
WHERE si.Status = 'Available'
GROUP BY si.ShopID, si.ReleaseID, r.Title, r.ArtistName, si.ConditionGrade
ORDER BY r.Title, FIELD(si.ConditionGrade, 'New', 'Mint', 'NM', 'VG+', 'VG');

-- 58. [架构重构] 待处理调拨列表（源店铺视角）
-- 替换 fulfillment.php 中的待发货调拨查询
CREATE OR REPLACE VIEW vw_fulfillment_pending_transfers AS
SELECT
    t.TransferID,
    t.StockItemID,
    t.FromShopID,
    t.ToShopID,
    t.Status,
    t.TransferDate,
    s1.Name AS FromShopName,
    s2.Name AS ToShopName,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    si.UnitPrice,
    e.Name AS AuthorizedByName
FROM InventoryTransfer t
JOIN StockItem si ON t.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN Shop s1 ON t.FromShopID = s1.ShopID
JOIN Shop s2 ON t.ToShopID = s2.ShopID
LEFT JOIN Employee e ON t.AuthorizedByEmployeeID = e.EmployeeID
WHERE t.Status = 'Pending'
ORDER BY t.TransferDate DESC;

-- 59. [架构重构] 进货中调拨列表（目标店铺视角）
-- 替换 fulfillment.php 中的待收货调拨查询
CREATE OR REPLACE VIEW vw_fulfillment_incoming_transfers AS
SELECT
    t.TransferID,
    t.StockItemID,
    t.FromShopID,
    t.ToShopID,
    t.Status,
    t.TransferDate,
    s1.Name AS FromShopName,
    s2.Name AS ToShopName,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    si.UnitPrice,
    e.Name AS AuthorizedByName
FROM InventoryTransfer t
JOIN StockItem si ON t.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN Shop s1 ON t.FromShopID = s1.ShopID
JOIN Shop s2 ON t.ToShopID = s2.ShopID
LEFT JOIN Employee e ON t.AuthorizedByEmployeeID = e.EmployeeID
WHERE t.Status = 'InTransit'
ORDER BY t.TransferDate DESC;

-- 60. [架构重构] Fulfillment待发货订单视图
-- 替换 fulfillment.php 中的待发货订单查询
CREATE OR REPLACE VIEW vw_fulfillment_shipping_orders AS
SELECT
    co.OrderID,
    co.CustomerID,
    co.OrderDate,
    co.OrderStatus,
    co.TotalAmount,
    co.FulfilledByShopID AS ShopID,
    co.FulfillmentType,
    co.ShippingAddress,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    (SELECT COUNT(*) FROM OrderLine WHERE OrderID = co.OrderID) AS ItemCount
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.OrderType = 'Online'
  AND co.OrderStatus = 'Paid'
  AND co.FulfillmentType = 'Shipping'
ORDER BY co.OrderDate ASC;

-- 61. [架构重构] 已发货待确认订单视图
-- 替换 fulfillment.php 中的已发货订单查询
CREATE OR REPLACE VIEW vw_fulfillment_shipped_orders AS
SELECT
    co.OrderID,
    co.CustomerID,
    co.OrderDate,
    co.OrderStatus,
    co.TotalAmount,
    co.FulfilledByShopID AS ShopID,
    co.FulfillmentType,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    (SELECT COUNT(*) FROM OrderLine WHERE OrderID = co.OrderID) AS ItemCount
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.OrderType = 'Online'
  AND co.OrderStatus = 'Shipped'
ORDER BY co.OrderDate ASC;

-- 62. [架构重构] Warehouse库存视图
-- 替换 warehouse_dispatch.php 中的仓库库存查询
CREATE OR REPLACE VIEW vw_warehouse_stock AS
SELECT
    si.ReleaseID,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    COUNT(*) AS Quantity,
    MIN(si.UnitPrice) AS UnitPrice,
    si.ShopID,
    s.Name AS ShopName
FROM StockItem si
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN Shop s ON si.ShopID = s.ShopID
WHERE si.Status = 'Available' AND s.Type = 'Warehouse'
GROUP BY si.ReleaseID, r.Title, r.ArtistName, si.ConditionGrade, si.ShopID, s.Name
ORDER BY r.Title, FIELD(si.ConditionGrade, 'New', 'Mint', 'NM', 'VG+', 'VG');

-- 63. [架构重构] 零售店铺列表视图
-- 替换 warehouse_dispatch.php 中的零售店铺查询
CREATE OR REPLACE VIEW vw_retail_shops AS
SELECT
    ShopID,
    Name,
    Address
FROM Shop
WHERE Type = 'Retail'
ORDER BY Name;

-- 64. [架构重构] Buyback专辑价格参考视图
-- 替换 buyback.php 中的现有库存价格查询
CREATE OR REPLACE VIEW vw_buyback_price_reference AS
SELECT
    ReleaseID,
    ConditionGrade,
    MIN(UnitPrice) AS MinPrice,
    MAX(UnitPrice) AS MaxPrice,
    AVG(UnitPrice) AS AvgPrice,
    COUNT(*) AS StockCount
FROM StockItem
WHERE Status = 'Available'
GROUP BY ReleaseID, ConditionGrade;

-- 65. [架构重构] 最近回购订单视图
-- 替换 buyback.php 中的最近回购查询
CREATE OR REPLACE VIEW vw_recent_buyback_orders AS
SELECT
    bo.BuybackOrderID,
    bo.CustomerID,
    COALESCE(c.Name, 'Walk-in') AS CustomerName,
    bo.ShopID,
    bo.BuybackDate,
    bo.TotalPayment,
    bo.Status,
    r.Title,
    r.ArtistName,
    bol.Quantity,
    bol.UnitPrice AS BuybackPrice,
    bol.ConditionGrade,
    e.Name AS ProcessedByName
FROM BuybackOrder bo
LEFT JOIN Customer c ON bo.CustomerID = c.CustomerID
JOIN BuybackOrderLine bol ON bo.BuybackOrderID = bol.BuybackOrderID
JOIN ReleaseAlbum r ON bol.ReleaseID = r.ReleaseID
JOIN Employee e ON bo.ProcessedByEmployeeID = e.EmployeeID
ORDER BY bo.BuybackDate DESC;

-- 66. [架构重构] Manager申请详情视图（用于库存验证）
-- 替换 admin/requests.php 中的库存验证查询
CREATE OR REPLACE VIEW vw_request_stock_verification AS
SELECT
    mr.RequestID,
    mr.FromShopID,
    mr.ToShopID,
    mr.ReleaseID,
    mr.ConditionGrade,
    mr.Quantity AS RequestedQuantity,
    (
        SELECT COUNT(*)
        FROM StockItem si
        WHERE si.ShopID = mr.ToShopID
          AND si.ReleaseID = mr.ReleaseID
          AND si.ConditionGrade = mr.ConditionGrade
          AND si.Status = 'Available'
    ) AS AvailableQuantity
FROM ManagerRequest mr
WHERE mr.RequestType = 'TransferRequest' AND mr.Status = 'Pending';

-- 67. [架构重构] 其他店铺同款库存视图
-- 替换 admin/requests.php 中的跨店库存查询
CREATE OR REPLACE VIEW vw_other_shops_inventory AS
SELECT
    si.ShopID,
    s.Name AS ShopName,
    si.ReleaseID,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    COUNT(*) AS AvailableQuantity,
    MIN(si.UnitPrice) AS UnitPrice
FROM StockItem si
JOIN Shop s ON si.ShopID = s.ShopID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
WHERE si.Status = 'Available'
GROUP BY si.ShopID, s.Name, si.ReleaseID, r.Title, r.ArtistName, si.ConditionGrade;

-- 68. [架构重构] 订单详情视图（包含商品信息）
-- 替换 pos.php 中的订单明细查询
CREATE OR REPLACE VIEW vw_order_line_detail AS
SELECT
    ol.OrderID,
    ol.StockItemID,
    ol.PriceAtSale,
    si.ReleaseID,
    si.ConditionGrade,
    r.Title,
    r.ArtistName
FROM OrderLine ol
JOIN StockItem si ON ol.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID;

-- 69. [架构重构] Checkout库存验证视图
-- 替换 checkout.php 中的库存验证查询
CREATE OR REPLACE VIEW vw_checkout_stock_validation AS
SELECT
    si.StockItemID,
    si.ReleaseID,
    si.ShopID,
    si.Status,
    si.UnitPrice,
    si.ConditionGrade,
    r.Title,
    r.ArtistName,
    s.Name AS ShopName,
    s.Type AS ShopType
FROM StockItem si
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN Shop s ON si.ShopID = s.ShopID
WHERE si.Status = 'Available';

-- 70. [架构重构] 店铺Walk-in顾客收入视图
-- 替换 functions.php:prepareDashboardData 中的walk-in收入查询
CREATE OR REPLACE VIEW vw_shop_walk_in_revenue AS
SELECT
    FulfilledByShopID AS ShopID,
    COUNT(DISTINCT OrderID) AS OrderCount,
    COALESCE(SUM(TotalAmount), 0) AS TotalSpent
FROM CustomerOrder
WHERE CustomerID IS NULL AND OrderStatus IN ('Paid', 'Completed')
GROUP BY FulfilledByShopID;

-- 71. [架构重构] 店铺库存成本视图
-- 替换 functions.php:prepareDashboardData 中的库存成本计算
CREATE OR REPLACE VIEW vw_shop_inventory_cost AS
SELECT
    si.ShopID,
    COALESCE(SUM(
        CASE
            WHEN si.SourceType = 'Supplier' THEN
                COALESCE(
                    (SELECT sol.UnitCost
                     FROM SupplierOrderLine sol
                     WHERE sol.SupplierOrderID = si.SourceOrderID
                     AND sol.ReleaseID = si.ReleaseID
                     AND sol.ConditionGrade = si.ConditionGrade
                     LIMIT 1),
                    0
                )
            WHEN si.SourceType = 'Buyback' THEN
                COALESCE(
                    (SELECT bol.UnitPrice
                     FROM BuybackOrderLine bol
                     WHERE bol.BuybackOrderID = si.SourceOrderID
                     AND bol.ReleaseID = si.ReleaseID
                     AND bol.ConditionGrade = si.ConditionGrade
                     LIMIT 1),
                    0
                )
            ELSE 0
        END
    ), 0) AS TotalInventoryCost,
    COUNT(*) AS InventoryCount
FROM StockItem si
WHERE si.Status IN ('Available', 'Sold')
GROUP BY si.ShopID;

-- 72. [架构重构] 店铺采购统计视图
-- 替换 functions.php:prepareDashboardData 中的采购统计查询
CREATE OR REPLACE VIEW vw_shop_procurement_stats AS
SELECT
    DestinationShopID AS ShopID,
    COUNT(SupplierOrderID) AS ProcurementCount
FROM SupplierOrder
WHERE Status = 'Received'
GROUP BY DestinationShopID;

-- 73. [架构重构] 专辑店铺库存分组视图
-- 替换 functions.php:prepareReleaseDetailData 中的库存分组查询
CREATE OR REPLACE VIEW vw_release_shop_stock_grouped AS
SELECT
    si.ReleaseID,
    si.ShopID,
    si.ConditionGrade,
    si.UnitPrice,
    COUNT(*) AS AvailableQuantity
FROM StockItem si
WHERE si.Status = 'Available'
GROUP BY si.ReleaseID, si.ShopID, si.ConditionGrade, si.UnitPrice;

-- 74. [架构重构] 可用库存ID列表视图
-- 替换 functions.php:addMultipleToCart 中的库存ID查询
CREATE OR REPLACE VIEW vw_available_stock_ids AS
SELECT
    StockItemID,
    ReleaseID,
    ShopID,
    ConditionGrade
FROM StockItem
WHERE Status = 'Available'
ORDER BY StockItemID;

-- 75. [架构重构Phase2] POS可用库存ID视图（含价格）
-- 替换 pos.php 中的 add_multiple 库存ID查询
CREATE OR REPLACE VIEW vw_pos_available_stock_ids AS
SELECT
    StockItemID,
    ShopID,
    ReleaseID,
    ConditionGrade,
    UnitPrice
FROM StockItem
WHERE Status = 'Available'
ORDER BY StockItemID;

-- 76. [架构重构Phase2] 简单客户列表视图
-- 替换 pos.php 中的客户下拉框查询
CREATE OR REPLACE VIEW vw_customer_list_simple AS
SELECT
    CustomerID,
    Name,
    Email
FROM Customer
ORDER BY Name;

-- 77. [架构重构Phase2] POS购物车商品验证视图
-- 替换 pos.php 中的添加商品验证查询
CREATE OR REPLACE VIEW vw_pos_cart_item_validation AS
SELECT
    si.StockItemID,
    si.ShopID,
    si.ReleaseID,
    si.ConditionGrade,
    si.UnitPrice,
    si.Status,
    r.Title,
    r.ArtistName
FROM StockItem si
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
WHERE si.Status = 'Available';

-- 78. [架构重构Phase2] 待发货调拨分组视图（源店铺视角）
-- 替换 fulfillment.php 中的待发货调拨分组查询
CREATE OR REPLACE VIEW vw_fulfillment_pending_transfers_grouped AS
SELECT
    MIN(it.TransferID) as FirstTransferID,
    GROUP_CONCAT(it.TransferID ORDER BY it.TransferID) as TransferIDs,
    it.FromShopID,
    it.ToShopID,
    it.Status,
    MIN(it.TransferDate) as TransferDate,
    from_shop.Name as FromShopName,
    to_shop.Name as ToShopName,
    r.ReleaseID,
    r.Title as ReleaseTitle,
    r.ArtistName,
    si.ConditionGrade,
    MIN(si.UnitPrice) as UnitPrice,
    COUNT(*) as Quantity
FROM InventoryTransfer it
JOIN Shop from_shop ON it.FromShopID = from_shop.ShopID
JOIN Shop to_shop ON it.ToShopID = to_shop.ShopID
JOIN StockItem si ON it.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
WHERE it.Status = 'Pending'
GROUP BY it.FromShopID, it.ToShopID, r.ReleaseID, si.ConditionGrade, it.Status,
         from_shop.Name, to_shop.Name, r.Title, r.ArtistName
ORDER BY MIN(it.TransferDate) DESC;

-- 79. [架构重构Phase2] 待接收调拨分组视图（目标店铺视角）
-- 替换 fulfillment.php 中的待接收调拨分组查询
CREATE OR REPLACE VIEW vw_fulfillment_incoming_transfers_grouped AS
SELECT
    MIN(it.TransferID) as FirstTransferID,
    GROUP_CONCAT(it.TransferID ORDER BY it.TransferID) as TransferIDs,
    it.FromShopID,
    it.ToShopID,
    it.Status,
    MIN(it.TransferDate) as TransferDate,
    from_shop.Name as FromShopName,
    to_shop.Name as ToShopName,
    r.ReleaseID,
    r.Title as ReleaseTitle,
    r.ArtistName,
    si.ConditionGrade,
    MIN(si.UnitPrice) as UnitPrice,
    COUNT(*) as Quantity
FROM InventoryTransfer it
JOIN Shop from_shop ON it.FromShopID = from_shop.ShopID
JOIN Shop to_shop ON it.ToShopID = to_shop.ShopID
JOIN StockItem si ON it.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
WHERE it.Status = 'InTransit'
GROUP BY it.FromShopID, it.ToShopID, r.ReleaseID, si.ConditionGrade, it.Status,
         from_shop.Name, to_shop.Name, r.Title, r.ArtistName
ORDER BY MIN(it.TransferDate) DESC;

-- 80. [架构重构Phase2] 订单履行列表视图
-- 替换 fulfillment.php 中的订单列表查询
CREATE OR REPLACE VIEW vw_fulfillment_orders AS
SELECT
    co.OrderID,
    co.CustomerID,
    co.FulfilledByShopID,
    co.OrderDate,
    co.OrderStatus,
    co.FulfillmentType,
    co.ShippingAddress,
    co.ShippingCost,
    co.TotalAmount,
    co.OrderType,
    c.Name as CustomerName,
    c.Email as CustomerEmail,
    COUNT(ol.StockItemID) as ItemCount,
    (SELECT GROUP_CONCAT(DISTINCT r.Title SEPARATOR ', ')
     FROM OrderLine ol2
     JOIN StockItem si ON ol2.StockItemID = si.StockItemID
     JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
     WHERE ol2.OrderID = co.OrderID
     LIMIT 3) as ItemTitles
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
LEFT JOIN OrderLine ol ON co.OrderID = ol.OrderID
WHERE co.FulfillmentType = 'Shipping'
GROUP BY co.OrderID
ORDER BY co.OrderDate DESC;

-- 81. [架构重构Phase2] 订单状态统计视图
-- 替换 fulfillment.php 中的状态统计查询
CREATE OR REPLACE VIEW vw_fulfillment_order_status_counts AS
SELECT
    FulfilledByShopID,
    OrderStatus,
    COUNT(*) as cnt
FROM CustomerOrder
WHERE FulfillmentType = 'Shipping'
GROUP BY FulfilledByShopID, OrderStatus;

-- 82. [架构重构Phase2] 专辑列表视图（含基础成本）
-- 替换 buyback.php 中的专辑列表查询
CREATE OR REPLACE VIEW vw_release_list_with_cost AS
SELECT
    ReleaseID,
    Title,
    ArtistName,
    Genre,
    BaseUnitCost
FROM ReleaseAlbum
ORDER BY Title;

-- 83. [架构重构Phase2] 客户列表视图（含积分）
-- 替换 buyback.php 中的客户下拉框查询
CREATE OR REPLACE VIEW vw_customer_list_with_points AS
SELECT
    CustomerID,
    Name,
    Email,
    Points
FROM Customer
ORDER BY Name;

-- 84. [架构重构Phase2] 库存价格映射视图
-- 替换 buyback.php 中的价格映射查询
CREATE OR REPLACE VIEW vw_stock_price_map AS
SELECT
    ReleaseID,
    ConditionGrade,
    MAX(UnitPrice) as CurrentPrice
FROM StockItem
WHERE Status = 'Available'
GROUP BY ReleaseID, ConditionGrade;

-- 85. [架构重构Phase2] 最近回购记录详情视图
-- 替换 buyback.php 中的最近回购查询
CREATE OR REPLACE VIEW vw_recent_buybacks_detail AS
SELECT
    bo.BuybackOrderID,
    bo.ShopID,
    bo.BuybackDate,
    bo.TotalPayment,
    bo.Status,
    c.Name as CustomerName,
    c.Email as CustomerEmail,
    r.Title,
    r.ArtistName,
    bol.Quantity,
    bol.UnitPrice,
    bol.ConditionGrade,
    (bol.Quantity * bol.UnitPrice) as LineTotal,
    e.Name as ProcessedByName
FROM BuybackOrder bo
LEFT JOIN Customer c ON bo.CustomerID = c.CustomerID
JOIN BuybackOrderLine bol ON bo.BuybackOrderID = bol.BuybackOrderID
JOIN ReleaseAlbum r ON bol.ReleaseID = r.ReleaseID
JOIN Employee e ON bo.ProcessedByEmployeeID = e.EmployeeID
ORDER BY bo.BuybackDate DESC;

-- 86. [架构重构Phase2] 调货申请详情视图
-- 替换 requests.php 中的申请信息查询
CREATE OR REPLACE VIEW vw_transfer_request_info AS
SELECT
    RequestID,
    ReleaseID,
    ConditionGrade,
    Quantity,
    RequestType,
    FromShopID,
    ToShopID,
    Status
FROM ManagerRequest
WHERE RequestType = 'TransferRequest';

-- 87. [架构重构Phase2] 店铺库存查询视图（按专辑和成色分组）
-- 替换 requests.php 中的getOtherShopsInventory函数
CREATE OR REPLACE VIEW vw_shop_inventory_by_release AS
SELECT
    si.ShopID,
    s.Name as ShopName,
    s.Type as ShopType,
    si.ReleaseID,
    si.ConditionGrade,
    COUNT(*) as AvailableQuantity,
    MIN(si.UnitPrice) as UnitPrice
FROM StockItem si
JOIN Shop s ON si.ShopID = s.ShopID
WHERE si.Status = 'Available'
GROUP BY si.ShopID, s.Name, s.Type, si.ReleaseID, si.ConditionGrade
HAVING AvailableQuantity > 0;
