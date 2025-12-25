-- Query 1: Inventory Turnover Analysis (库存周转率分析)
-- Technique: DATEDIFF, AVG Aggregation
-- Business Value: Identifies how fast stock is moving (Assignment 1 Requirement 1.3.2)
SELECT 
    r.Genre,
    COUNT(s.StockItemID) as TotalItemsSold,
    AVG(DATEDIFF(co.OrderDate, s.AcquiredDate)) as AvgDaysToSell
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
WHERE s.Status = 'Sold'
GROUP BY r.Genre
ORDER BY AvgDaysToSell ASC;

-- Query 2: Customer Lifetime Value Ranking (VIP客户价值排行)
-- Technique: Window Function (DENSE_RANK), Joins
-- Business Value: Helps identify top customers for marketing
SELECT 
    c.Name,
    c.Email,
    mt.TierName,
    SUM(co.TotalAmount) as TotalSpent,
    DENSE_RANK() OVER (ORDER BY SUM(co.TotalAmount) DESC) as SpendingRank
FROM Customer c
JOIN MembershipTier mt ON c.TierID = mt.TierID
JOIN CustomerOrder co ON c.CustomerID = co.CustomerID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY c.CustomerID, c.Name, c.Email, mt.TierName
LIMIT 10;

-- Query 3: 按艺术家分析利润率 (修改后)
SELECT 
    r.ArtistName,
    SUM(ol.PriceAtSale) as TotalRevenue,
    -- 从供应商订单明细中获取成本
    (SELECT SUM(sol.Quantity * sol.UnitCost) 
     FROM SupplierOrderLine sol 
     WHERE sol.ReleaseID IN (SELECT ReleaseID FROM ReleaseAlbum WHERE ArtistName = r.ArtistName)
    ) as ApproximateCost,
    -- 计算毛利
    (SUM(ol.PriceAtSale) - 
        (SELECT SUM(sol.Quantity * sol.UnitCost) 
         FROM SupplierOrderLine sol 
         WHERE sol.ReleaseID IN (SELECT ReleaseID FROM ReleaseAlbum WHERE ArtistName = r.ArtistName))
    ) as GrossProfit
FROM OrderLine ol
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
GROUP BY r.ArtistName
ORDER BY GrossProfit DESC;

-- Query 4: Monthly Sales Trend Comparison (Retail vs Online)
-- Technique: CASE Statement (Pivot), DATE_FORMAT
-- Business Value: Compares channel performance over time
SELECT 
    DATE_FORMAT(OrderDate, '%Y-%m') as SalesMonth,
    SUM(CASE WHEN OrderType = 'InStore' THEN TotalAmount ELSE 0 END) as StoreRevenue,
    SUM(CASE WHEN OrderType = 'Online' THEN TotalAmount ELSE 0 END) as OnlineRevenue,
    SUM(TotalAmount) as TotalRevenue
FROM CustomerOrder
WHERE OrderStatus IN ('Paid', 'Completed')
GROUP BY SalesMonth
ORDER BY SalesMonth DESC;

-- Query 5: Dead Stock Alert (滞销库存预警)
-- Technique: HAVING Clause, Date Arithmetic
-- Business Value: Alerts managers to stock sitting longer than 60 days (Section 1.3.2)
SELECT 
    sh.Name as ShopName,
    r.Title,
    s.BatchNo,
    DATEDIFF(CURRENT_DATE, s.AcquiredDate) as DaysInStock
FROM StockItem s
JOIN Shop sh ON s.ShopID = sh.ShopID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
WHERE s.Status = 'Available'
HAVING DaysInStock > 60
ORDER BY DaysInStock DESC;