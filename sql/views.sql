
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
    r.ReleaseID,
    ol.PriceAtSale,
    s.UnitPrice AS OriginalPrice,
    s.ConditionGrade
FROM CustomerOrder co
JOIN OrderLine ol ON co.OrderID = ol.OrderID
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID;

CREATE OR REPLACE VIEW vw_customer_my_orders_list AS
SELECT
    co.OrderID,
    co.CustomerID,
    co.OrderDate,
    co.OrderStatus,
    co.TotalAmount,
    co.OrderType,
    co.FulfillmentType,
    co.FulfilledByShopID AS ShopID,
    COALESCE(c.Name, 'Walk-in Customer') AS CustomerName
FROM CustomerOrder co
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID;

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

CREATE OR REPLACE VIEW vw_admin_release_list AS
SELECT
    r.*,
    COUNT(DISTINCT s.StockItemID) AS TotalStockItems,
    SUM(CASE WHEN s.Status = 'Available' THEN 1 ELSE 0 END) AS AvailableItems,
    SUM(CASE WHEN s.Status = 'Sold' THEN 1 ELSE 0 END) AS SoldItems
FROM ReleaseAlbum r
LEFT JOIN StockItem s ON r.ReleaseID = s.ReleaseID
GROUP BY r.ReleaseID;

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
    COALESCE(SUM(sol.Quantity), 0) AS TotalItems,
    GROUP_CONCAT(DISTINCT CONCAT(r.Title, ' (', sol.ConditionGrade, ')') SEPARATOR ', ') AS Albums
FROM SupplierOrder so
JOIN Supplier s ON so.SupplierID = s.SupplierID
JOIN Employee e ON so.CreatedByEmployeeID = e.EmployeeID
LEFT JOIN Shop sh ON so.DestinationShopID = sh.ShopID
LEFT JOIN SupplierOrderLine sol ON so.SupplierOrderID = sol.SupplierOrderID
LEFT JOIN ReleaseAlbum r ON sol.ReleaseID = r.ReleaseID
GROUP BY so.SupplierOrderID, s.Name, e.Name, sh.Name, so.OrderDate, so.Status, so.ReceivedDate, so.TotalCost;

CREATE OR REPLACE VIEW vw_report_sales_by_genre AS
SELECT
    r.Genre,
    COUNT(DISTINCT ol.OrderID) AS TotalOrders,
    COUNT(ol.StockItemID) AS ItemsSold,

    SUM(ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2)) AS TotalRevenue,
    AVG(ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2)) AS AvgPrice,
    AVG(DATEDIFF(COALESCE(s.DateSold, NOW()), s.AcquiredDate)) AS AvgDaysToSell
FROM OrderLine ol
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID

LEFT JOIN (
    SELECT OrderID, SUM(PriceAtSale) AS OrderSubtotal
    FROM OrderLine
    GROUP BY OrderID
) order_subtotals ON co.OrderID = order_subtotals.OrderID
WHERE co.OrderStatus IN ('Paid', 'Shipped', 'Completed')
GROUP BY r.Genre
ORDER BY TotalRevenue DESC;

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

CREATE OR REPLACE VIEW vw_customer_lookup AS
SELECT CustomerID, Name, Email, TierID, Points FROM Customer;

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

CREATE OR REPLACE VIEW vw_order_for_pickup AS
SELECT
    OrderID,
    CustomerID,
    FulfilledByShopID AS ShopID,
    TotalAmount,
    OrderStatus,
    OrderType,
    FulfillmentType
FROM CustomerOrder;

CREATE OR REPLACE VIEW vw_release_simple_list AS
SELECT
    ReleaseID,
    Title,
    ArtistName,
    Genre,
    Format,
    ReleaseYear,
    BaseUnitCost
FROM ReleaseAlbum
ORDER BY Title;

CREATE OR REPLACE VIEW vw_customer_simple_list AS
SELECT
    CustomerID,
    Name,
    Email,
    TierID,
    Points
FROM Customer
ORDER BY Name;

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

CREATE OR REPLACE VIEW vw_order_reserved_items AS
SELECT
    ol.OrderID,
    ol.StockItemID,
    ol.PriceAtSale,
    s.Status AS StockStatus,
    s.ReleaseID
FROM OrderLine ol
JOIN StockItem s ON ol.StockItemID = s.StockItemID;

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

CREATE OR REPLACE VIEW vw_kpi_stats AS
SELECT
    (SELECT COALESCE(SUM(TotalAmount), 0) FROM CustomerOrder WHERE OrderStatus != 'Cancelled') AS TotalSales,
    (SELECT COUNT(*) FROM CustomerOrder WHERE OrderStatus IN ('Pending', 'Paid', 'Shipped')) AS ActiveOrders,
    (SELECT COUNT(*) FROM vw_low_stock_alert) AS LowStockCount;

CREATE OR REPLACE VIEW vw_shop_list AS
SELECT
    ShopID,
    Name,
    Type,
    Address
FROM Shop;

CREATE OR REPLACE VIEW vw_membership_tier_rules AS
SELECT
    TierID,
    TierName,
    MinPoints,
    DiscountRate
FROM MembershipTier
ORDER BY MinPoints ASC;

CREATE OR REPLACE VIEW vw_report_monthly_sales AS
SELECT
    DATE_FORMAT(OrderDate, '%Y-%m') AS SalesMonth,
    COUNT(*) AS OrderCount,
    SUM(TotalAmount) AS MonthlyRevenue
FROM CustomerOrder
WHERE OrderStatus != 'Cancelled'
GROUP BY SalesMonth
ORDER BY SalesMonth DESC;

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

CREATE OR REPLACE VIEW vw_popular_items AS
SELECT
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    COUNT(ol.StockItemID) AS TotalSold,

    SUM(ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2)) AS TotalRevenue
FROM OrderLine ol
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID

LEFT JOIN (
    SELECT OrderID, SUM(PriceAtSale) AS OrderSubtotal
    FROM OrderLine
    GROUP BY OrderID
) order_subtotals ON co.OrderID = order_subtotals.OrderID
WHERE co.OrderStatus IN ('Paid', 'Shipped', 'Completed')
GROUP BY r.ReleaseID, r.Title, r.ArtistName, r.Genre
ORDER BY TotalSold DESC;

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
WHERE co.OrderStatus IN ('Paid', 'Shipped', 'Completed');

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
WHERE co.OrderStatus IN ('Paid', 'Shipped', 'Completed');

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
WHERE co.OrderStatus IN ('Paid', 'Shipped', 'Completed')
GROUP BY co.FulfilledByShopID, co.CustomerID, c.Name, c.Email, mt.TierName, c.Points
ORDER BY TotalSpent DESC;

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
    co.TotalAmount,
    co.ShippingCost,
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    ol.PriceAtSale,
    order_subtotals.OrderSubtotal,

    ROUND(
        ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)),
        2
    ) AS ItemRevenue,
    DATEDIFF(COALESCE(si.DateSold, NOW()), si.AcquiredDate) AS DaysToSell
FROM OrderLine ol
JOIN StockItem si ON ol.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
JOIN Shop sh ON co.FulfilledByShopID = sh.ShopID
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID

LEFT JOIN (
    SELECT OrderID, SUM(PriceAtSale) AS OrderSubtotal
    FROM OrderLine
    GROUP BY OrderID
) order_subtotals ON co.OrderID = order_subtotals.OrderID
WHERE co.OrderStatus IN ('Paid', 'Shipped', 'Completed');

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
    co.ShippingCost,
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
    ol.PriceAtSale,
    order_subtotals.OrderSubtotal,

    ROUND(
        ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)),
        2
    ) AS ItemRevenue
FROM CustomerOrder co
JOIN Shop sh ON co.FulfilledByShopID = sh.ShopID
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
JOIN OrderLine ol ON co.OrderID = ol.OrderID
JOIN StockItem si ON ol.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID

LEFT JOIN (
    SELECT OrderID, SUM(PriceAtSale) AS OrderSubtotal
    FROM OrderLine
    GROUP BY OrderID
) order_subtotals ON co.OrderID = order_subtotals.OrderID
WHERE co.OrderStatus IN ('Paid', 'Shipped', 'Completed');

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

CREATE OR REPLACE VIEW vw_supplier_list AS
SELECT
    SupplierID,
    Name,
    Email
FROM Supplier
ORDER BY Name;

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

CREATE OR REPLACE VIEW vw_pos_all_releases AS
SELECT
    r.ReleaseID,
    r.Title,
    r.ArtistName,
    r.Genre,
    r.ReleaseYear,
    COALESCE(
        (SELECT MIN(si2.UnitPrice) FROM StockItem si2
         WHERE si2.ReleaseID = r.ReleaseID AND si2.Status = 'Available'),
        r.BaseUnitCost * 1.5
    ) AS SuggestedPrice
FROM ReleaseAlbum r
ORDER BY r.Title;

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

CREATE OR REPLACE VIEW vw_retail_shops AS
SELECT
    ShopID,
    Name,
    Address
FROM Shop
WHERE Type = 'Retail'
ORDER BY Name;

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

CREATE OR REPLACE VIEW vw_order_line_detail AS
SELECT
    ol.OrderID,
    ol.StockItemID,
    ol.PriceAtSale,
    si.ReleaseID,
    si.ConditionGrade,
    r.Title,
    r.ArtistName,
    r.Genre,
    r.ReleaseYear
FROM OrderLine ol
JOIN StockItem si ON ol.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID;

CREATE OR REPLACE VIEW vw_shop_walk_in_revenue AS
SELECT
    FulfilledByShopID AS ShopID,
    COUNT(DISTINCT OrderID) AS OrderCount,
    COALESCE(SUM(TotalAmount), 0) AS TotalSpent
FROM CustomerOrder
WHERE CustomerID IS NULL AND OrderStatus IN ('Paid', 'Completed')
GROUP BY FulfilledByShopID;

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
                    (SELECT r.BaseUnitCost FROM ReleaseAlbum r WHERE r.ReleaseID = si.ReleaseID)
                )
            WHEN si.SourceType = 'Buyback' THEN
                COALESCE(
                    (SELECT bol.UnitPrice
                     FROM BuybackOrderLine bol
                     WHERE bol.BuybackOrderID = si.SourceOrderID
                     AND bol.ReleaseID = si.ReleaseID
                     AND bol.ConditionGrade = si.ConditionGrade
                     LIMIT 1),
                    (SELECT r.BaseUnitCost FROM ReleaseAlbum r WHERE r.ReleaseID = si.ReleaseID)
                )
            ELSE COALESCE(
                (SELECT r.BaseUnitCost FROM ReleaseAlbum r WHERE r.ReleaseID = si.ReleaseID),
                0
            )
        END
    ), 0) AS TotalInventoryCost,
    COUNT(*) AS InventoryCount
FROM StockItem si
WHERE si.Status IN ('Available', 'Reserved', 'Sold')
GROUP BY si.ShopID;

CREATE OR REPLACE VIEW vw_shop_procurement_stats AS
SELECT
    DestinationShopID AS ShopID,
    COUNT(SupplierOrderID) AS ProcurementCount
FROM SupplierOrder
WHERE Status = 'Received'
GROUP BY DestinationShopID;

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

CREATE OR REPLACE VIEW vw_available_stock_ids AS
SELECT
    StockItemID,
    ReleaseID,
    ShopID,
    ConditionGrade
FROM StockItem
WHERE Status = 'Available'
ORDER BY StockItemID;

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

CREATE OR REPLACE VIEW vw_fulfillment_order_status_counts AS
SELECT
    FulfilledByShopID,
    OrderStatus,
    COUNT(*) as cnt
FROM CustomerOrder
WHERE FulfillmentType = 'Shipping'
GROUP BY FulfilledByShopID, OrderStatus;

CREATE OR REPLACE VIEW vw_release_list_with_cost AS
SELECT ReleaseID, Title, ArtistName, Genre, BaseUnitCost FROM vw_release_simple_list;

CREATE OR REPLACE VIEW vw_customer_list_with_points AS
SELECT CustomerID, Name, Email, Points FROM vw_customer_simple_list;

CREATE OR REPLACE VIEW vw_stock_price_map AS
SELECT
    ShopID,
    ReleaseID,
    ConditionGrade,
    MAX(UnitPrice) as CurrentPrice
FROM StockItem
WHERE Status = 'Available'
GROUP BY ShopID, ReleaseID, ConditionGrade;

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

CREATE OR REPLACE VIEW vw_shop_inventory_by_release AS
SELECT
    si.ShopID,
    s.Name as ShopName,
    s.Type as ShopType,
    si.ReleaseID,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    COUNT(*) as AvailableQuantity,
    MIN(si.UnitPrice) as UnitPrice
FROM StockItem si
JOIN Shop s ON si.ShopID = s.ShopID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
WHERE si.Status = 'Available'
GROUP BY si.ShopID, s.Name, s.Type, si.ReleaseID, r.Title, r.ArtistName, si.ConditionGrade
HAVING AvailableQuantity > 0;

CREATE OR REPLACE VIEW vw_checkout_cart_items AS
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
    s.Type AS ShopType,
    s.Address AS ShopAddress
FROM StockItem si
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN Shop s ON si.ShopID = s.ShopID;

CREATE OR REPLACE VIEW vw_customer_order_detail AS
SELECT
    co.*,
    s.Name AS ShopName
FROM CustomerOrder co
LEFT JOIN Shop s ON co.FulfilledByShopID = s.ShopID;

CREATE OR REPLACE VIEW vw_shop_kpi_stats AS
SELECT
    FulfilledByShopID AS ShopID,
    COALESCE(SUM(CASE WHEN OrderStatus IN ('Paid', 'Completed') THEN TotalAmount ELSE 0 END), 0) AS TotalSales,
    COALESCE(SUM(CASE WHEN OrderStatus IN ('Pending', 'Paid', 'Shipped') THEN 1 ELSE 0 END), 0) AS ActiveOrders
FROM CustomerOrder
GROUP BY FulfilledByShopID;

CREATE OR REPLACE VIEW vw_transfer_validation AS
SELECT
    TransferID,
    StockItemID,
    FromShopID,
    ToShopID,
    Status,
    TransferDate,
    AuthorizedByEmployeeID,
    ReceivedByEmployeeID,
    ReceivedDate
FROM InventoryTransfer;

CREATE OR REPLACE VIEW vw_order_shop_validation AS
SELECT
    OrderID,
    CustomerID,
    FulfilledByShopID,
    OrderStatus,
    OrderType,
    FulfillmentType
FROM CustomerOrder;

CREATE OR REPLACE VIEW vw_catalog_by_shop_grouped AS
SELECT
    r.ReleaseID,
    r.Title,
    r.Genre,
    r.ReleaseYear AS Year,
    r.ArtistName,
    s.ShopID,
    COUNT(CASE WHEN si.Status = 'Available' THEN 1 END) AS TotalAvailable,
    MIN(CASE WHEN si.Status = 'Available' THEN si.UnitPrice END) AS MinPrice,
    MAX(CASE WHEN si.Status = 'Available' THEN si.UnitPrice END) AS MaxPrice,
    GROUP_CONCAT(DISTINCT CASE WHEN si.Status = 'Available' THEN si.ConditionGrade END
        ORDER BY FIELD(si.ConditionGrade, 'New', 'Mint', 'NM', 'VG+', 'VG', 'G+', 'G', 'F', 'P')
    ) AS AvailableConditions
FROM ReleaseAlbum r
CROSS JOIN Shop s
LEFT JOIN StockItem si ON si.ReleaseID = r.ReleaseID AND si.ShopID = s.ShopID
GROUP BY r.ReleaseID, r.Title, r.Genre, r.ReleaseYear, r.ArtistName, s.ShopID;

CREATE OR REPLACE VIEW vw_release_info AS
SELECT
    ReleaseID,
    Title,
    ArtistName,
    LabelName,
    ReleaseYear,
    Genre,
    Format,
    Description,
    BaseUnitCost
FROM ReleaseAlbum;

CREATE OR REPLACE VIEW vw_release_genres AS
SELECT DISTINCT Genre
FROM ReleaseAlbum
WHERE Genre IS NOT NULL AND Genre != ''
ORDER BY Genre;

CREATE OR REPLACE VIEW vw_release_tracks AS
SELECT
    TrackID,
    ReleaseID,
    Title,
    TrackNumber,
    Duration
FROM Track
ORDER BY ReleaseID, TrackNumber;

CREATE OR REPLACE VIEW vw_shop_stock_count AS
SELECT
    ShopID,
    ReleaseID,
    ConditionGrade,
    COUNT(*) AS AvailableCount
FROM StockItem
WHERE Status = 'Available'
GROUP BY ShopID, ReleaseID, ConditionGrade;

CREATE OR REPLACE VIEW vw_stock_item_with_cost AS
SELECT
    si.StockItemID,
    si.ReleaseID,
    si.ShopID,
    si.ConditionGrade,
    si.Status,
    si.SourceType,
    si.SourceOrderID,
    si.AcquiredDate,
    si.DateSold,
    r.Title,
    r.ArtistName,
    r.BaseUnitCost,
    CASE
        WHEN si.SourceType = 'Supplier' THEN
            COALESCE(
                (SELECT sol.UnitCost
                 FROM SupplierOrderLine sol
                 WHERE sol.SupplierOrderID = si.SourceOrderID
                 AND sol.ReleaseID = si.ReleaseID
                 AND sol.ConditionGrade = si.ConditionGrade
                 LIMIT 1),
                r.BaseUnitCost
            )
        WHEN si.SourceType = 'Buyback' THEN
            COALESCE(
                (SELECT bol.UnitPrice
                 FROM BuybackOrderLine bol
                 WHERE bol.BuybackOrderID = si.SourceOrderID
                 AND bol.ReleaseID = si.ReleaseID
                 AND bol.ConditionGrade = si.ConditionGrade
                 LIMIT 1),
                r.BaseUnitCost
            )
        ELSE r.BaseUnitCost
    END AS UnitCost
FROM StockItem si
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID;

CREATE OR REPLACE VIEW vw_all_shops_inventory_summary AS
SELECT
    inv.ShopID,
    inv.ShopName,
    s.Type AS ShopType,
    inv.ReleaseID,
    inv.Title,
    inv.ArtistName,
    inv.Genre,
    inv.ConditionGrade,
    inv.AvailableQuantity,
    inv.MinPrice,
    inv.MaxPrice,
    inv.AvgPrice
FROM vw_inventory_summary inv
JOIN Shop s ON inv.ShopID = s.ShopID;

CREATE OR REPLACE VIEW vw_all_shops_inventory_detail AS
SELECT
    sd.StockItemID,
    sd.ShopID,
    s.Name AS ShopName,
    s.Type AS ShopType,
    sd.ReleaseID,
    sd.Title,
    sd.ArtistName,
    r.Genre,
    sd.ConditionGrade,
    sd.UnitPrice,
    sd.BatchNo,
    sd.Status,
    sd.AcquiredDate,
    sd.DaysInStock
FROM vw_staff_inventory_detail sd
JOIN Shop s ON sd.ShopID = s.ShopID
JOIN ReleaseAlbum r ON sd.ReleaseID = r.ReleaseID;

CREATE OR REPLACE VIEW vw_release_basic AS
SELECT
    ReleaseID,
    Title,
    ArtistName,
    Genre,
    ReleaseYear,
    Format,
    Description,
    BaseUnitCost
FROM ReleaseAlbum;

CREATE OR REPLACE VIEW vw_stock_summary AS
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

CREATE OR REPLACE VIEW vw_stock_detail AS
SELECT
    s.StockItemID,
    s.ShopID,
    sh.Name AS ShopName,
    s.ReleaseID,
    s.BatchNo,
    s.ConditionGrade,
    s.UnitPrice,
    s.Status,
    s.AcquiredDate,
    r.Title,
    r.ArtistName,
    r.Genre,
    DATEDIFF(NOW(), s.AcquiredDate) AS DaysInStock
FROM StockItem s
JOIN Shop sh ON s.ShopID = sh.ShopID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
WHERE s.Status = 'Available';

CREATE OR REPLACE VIEW vw_customer_shipped_delivery AS
SELECT
    co.OrderID,
    co.CustomerID,
    co.OrderDate,
    co.TotalAmount,
    co.ShippingAddress,
    s.Name AS ShopName
FROM CustomerOrder co
LEFT JOIN Shop s ON co.FulfilledByShopID = s.ShopID
WHERE co.OrderStatus = 'Shipped'
  AND co.OrderType = 'Online'
  AND co.FulfillmentType = 'Shipping';

CREATE OR REPLACE VIEW vw_warehouse_pending_receipts AS
SELECT
    so.SupplierOrderID,
    s.Name AS SupplierName,
    so.OrderDate,
    so.DestinationShopID,
    sol.ReleaseID,
    r.Title AS ReleaseTitle,
    r.ArtistName,
    sol.Quantity AS TotalItems,
    sol.UnitCost,
    (sol.Quantity * sol.UnitCost) AS TotalCost,
    sol.ConditionGrade,
    sol.SalePrice
FROM SupplierOrder so
JOIN Supplier s ON so.SupplierID = s.SupplierID
JOIN SupplierOrderLine sol ON so.SupplierOrderID = sol.SupplierOrderID
JOIN ReleaseAlbum r ON sol.ReleaseID = r.ReleaseID
WHERE so.Status = 'Pending'
ORDER BY so.OrderDate DESC;

CREATE OR REPLACE VIEW vw_shop_artist_profit_analysis AS
SELECT
    si.ShopID,
    r.ArtistName,
    COUNT(DISTINCT ol.StockItemID) AS ItemsSold,

    SUM(ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2)) AS TotalRevenue,
    SUM(COALESCE(sic.UnitCost, si.UnitPrice * 0.6)) AS TotalCost,
    SUM(ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2)) - SUM(COALESCE(sic.UnitCost, si.UnitPrice * 0.6)) AS GrossProfit,
    ROUND(((SUM(ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2)) - SUM(COALESCE(sic.UnitCost, si.UnitPrice * 0.6))) / NULLIF(SUM(ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2)), 0)) * 100, 1) AS ProfitMargin
FROM OrderLine ol
JOIN StockItem si ON ol.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
LEFT JOIN vw_stock_item_with_cost sic ON si.StockItemID = sic.StockItemID

LEFT JOIN (
    SELECT OrderID, SUM(PriceAtSale) AS OrderSubtotal
    FROM OrderLine
    GROUP BY OrderID
) order_subtotals ON co.OrderID = order_subtotals.OrderID
WHERE co.OrderStatus IN ('Paid', 'Completed', 'Shipped')
GROUP BY si.ShopID, r.ArtistName
ORDER BY GrossProfit DESC;

CREATE OR REPLACE VIEW vw_artist_sales_detail AS
SELECT
    si.ShopID,
    r.ArtistName,
    co.OrderID,
    co.OrderDate,
    COALESCE(c.Name, 'Guest') AS CustomerName,
    r.Title,
    si.ConditionGrade,
    ol.PriceAtSale,

    ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2) AS ItemRevenue,
    COALESCE(sic.UnitCost, si.UnitPrice * 0.6) AS Cost,

    ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2) - COALESCE(sic.UnitCost, si.UnitPrice * 0.6) AS Profit
FROM OrderLine ol
JOIN StockItem si ON ol.StockItemID = si.StockItemID
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
LEFT JOIN vw_stock_item_with_cost sic ON si.StockItemID = sic.StockItemID

LEFT JOIN (
    SELECT OrderID, SUM(PriceAtSale) AS OrderSubtotal
    FROM OrderLine
    GROUP BY OrderID
) order_subtotals ON co.OrderID = order_subtotals.OrderID
WHERE co.OrderStatus IN ('Paid', 'Completed', 'Shipped')
ORDER BY co.OrderDate DESC;

CREATE OR REPLACE VIEW vw_shop_batch_sales_analysis AS
SELECT
    si.ShopID,
    si.BatchNo,
    COUNT(DISTINCT si.StockItemID) AS TotalItems,
    SUM(CASE WHEN si.Status = 'Sold' THEN 1 ELSE 0 END) AS SoldItems,
    SUM(CASE WHEN si.Status = 'Available' THEN 1 ELSE 0 END) AS AvailableItems,

    SUM(CASE WHEN si.Status = 'Sold' AND co.OrderStatus IN ('Paid', 'Shipped', 'Completed')
        THEN ROUND(ol.PriceAtSale * (co.TotalAmount / NULLIF(order_subtotals.OrderSubtotal, 0)), 2)
        ELSE 0 END) AS TotalRevenue,
    MIN(si.AcquiredDate) AS AcquiredDate
FROM StockItem si
LEFT JOIN OrderLine ol ON si.StockItemID = ol.StockItemID
LEFT JOIN CustomerOrder co ON ol.OrderID = co.OrderID

LEFT JOIN (
    SELECT OrderID, SUM(PriceAtSale) AS OrderSubtotal
    FROM OrderLine
    GROUP BY OrderID
) order_subtotals ON co.OrderID = order_subtotals.OrderID
WHERE si.BatchNo IS NOT NULL AND si.BatchNo != ''
GROUP BY si.ShopID, si.BatchNo
ORDER BY AcquiredDate DESC;

CREATE OR REPLACE VIEW vw_batch_sales_detail AS
SELECT
    si.ShopID,
    si.BatchNo,
    si.StockItemID,
    r.Title,
    r.ArtistName,
    si.ConditionGrade,
    si.UnitPrice,
    si.Status,
    si.AcquiredDate,
    CASE WHEN si.Status = 'Sold' AND co.OrderStatus IN ('Paid', 'Shipped', 'Completed') THEN ol.PriceAtSale ELSE NULL END AS SoldPrice,

    CASE WHEN si.Status = 'Sold' AND co.OrderStatus IN ('Paid', 'Shipped', 'Completed')
        THEN ROUND(ol.PriceAtSale * ((co.TotalAmount - COALESCE(co.ShippingCost, 0)) / NULLIF(order_subtotals.OrderSubtotal, 0)), 2)
        ELSE NULL END AS ItemSoldRevenue,
    CASE WHEN si.Status = 'Sold' AND co.OrderStatus IN ('Paid', 'Shipped', 'Completed') THEN co.OrderDate ELSE NULL END AS SoldDate,
    CASE WHEN si.Status = 'Sold' AND co.OrderStatus IN ('Paid', 'Shipped', 'Completed') THEN COALESCE(c.Name, 'Guest') ELSE NULL END AS CustomerName,

    CASE WHEN si.Status = 'Sold' AND co.OrderStatus IN ('Paid', 'Shipped', 'Completed') THEN co.OrderID ELSE NULL END AS OrderID,
    CASE WHEN si.Status = 'Sold' AND co.OrderStatus IN ('Paid', 'Shipped', 'Completed') THEN COALESCE(co.ShippingCost, 0) ELSE NULL END AS ShippingCost
FROM StockItem si
JOIN ReleaseAlbum r ON si.ReleaseID = r.ReleaseID
LEFT JOIN OrderLine ol ON si.StockItemID = ol.StockItemID
LEFT JOIN CustomerOrder co ON ol.OrderID = co.OrderID
LEFT JOIN Customer c ON co.CustomerID = c.CustomerID

LEFT JOIN (
    SELECT OrderID, SUM(PriceAtSale) AS OrderSubtotal
    FROM OrderLine
    GROUP BY OrderID
) order_subtotals ON co.OrderID = order_subtotals.OrderID
ORDER BY si.Status DESC, r.Title;

CREATE OR REPLACE VIEW vw_shop_genre_sales_summary AS
SELECT
    ShopID,
    Genre,
    COUNT(DISTINCT OrderID) AS TotalOrders,
    COUNT(*) AS ItemsSold,
    SUM(ItemRevenue) AS TotalRevenue,
    AVG(ItemRevenue) AS AvgPrice,
    AVG(DaysToSell) AS AvgDaysToSell
FROM vw_sales_by_genre_detail
GROUP BY ShopID, Genre
ORDER BY TotalRevenue DESC;

CREATE OR REPLACE VIEW vw_shop_monthly_sales_summary AS
SELECT
    co.FulfilledByShopID AS ShopID,
    DATE_FORMAT(co.OrderDate, '%Y-%m') AS SalesMonth,
    COUNT(DISTINCT co.OrderID) AS OrderCount,
    SUM(co.TotalAmount) AS MonthlyRevenue
FROM CustomerOrder co
WHERE co.OrderStatus IN ('Paid', 'Shipped', 'Completed')
GROUP BY co.FulfilledByShopID, DATE_FORMAT(co.OrderDate, '%Y-%m')
ORDER BY SalesMonth DESC;