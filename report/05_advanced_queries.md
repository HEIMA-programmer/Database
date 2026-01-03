# 5. Advanced SQL Queries

This section demonstrates the advanced SQL techniques implemented in the system, as required by the assignment specification. Each query uses techniques from the 3rd SQL lecture including window functions, complex aggregations, subqueries, and date arithmetic.

---

## 5.1 Query 1: Inventory Turnover Analysis

**Techniques Used:** `DATEDIFF()`, `AVG()` Aggregation, Multi-Table `JOIN`

**Business Purpose:** Analyzes how quickly inventory sells by genre, helping identify fast-moving and slow-moving categories for procurement optimization.

```sql
SELECT
    r.Genre,
    COUNT(s.StockItemID) AS TotalItemsSold,
    AVG(DATEDIFF(co.OrderDate, s.AcquiredDate)) AS AvgDaysToSell
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
WHERE s.Status = 'Sold'
GROUP BY r.Genre
ORDER BY AvgDaysToSell ASC;
```

**Sample Output:**

| Genre | TotalItemsSold | AvgDaysToSell |
|-------|----------------|---------------|
| Rock | 45 | 12.3 |
| Jazz | 28 | 18.7 |
| Classical | 15 | 32.5 |

**Business Insight:** Rock albums sell fastest (average 12 days), suggesting higher procurement priority. Classical stock moves slowly, indicating potential for markdown pricing.

---

## 5.2 Query 2: Customer Lifetime Value Ranking

**Techniques Used:** Window Function `DENSE_RANK()`, `SUM()` Aggregation, `GROUP BY`

**Business Purpose:** Identifies top customers by total spending for targeted marketing and VIP recognition programs.

```sql
SELECT
    c.Name,
    c.Email,
    mt.TierName,
    SUM(co.TotalAmount) AS TotalSpent,
    DENSE_RANK() OVER (ORDER BY SUM(co.TotalAmount) DESC) AS SpendingRank
FROM Customer c
JOIN MembershipTier mt ON c.TierID = mt.TierID
JOIN CustomerOrder co ON c.CustomerID = co.CustomerID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY c.CustomerID, c.Name, c.Email, mt.TierName
LIMIT 10;
```

**Sample Output:**

| Name | Email | TierName | TotalSpent | SpendingRank |
|------|-------|----------|------------|--------------|
| Bob Collector | bob@test.com | Gold | 6,245.80 | 1 |
| Diana Vinyl | diana@test.com | VIP | 2,356.00 | 2 |
| Alice Fan | alice@test.com | VIP | 1,567.20 | 3 |

**Advanced Technique Explanation:**
- `DENSE_RANK()` ensures no gaps in ranking even when customers have equal spending
- Window function calculates rank after aggregation, allowing both SUM and RANK in one query

---

## 5.3 Query 3: Artist Profit Margin Analysis

**Techniques Used:** Correlated Subquery, `SUM()` with Calculation, Multi-Source Cost Aggregation

**Business Purpose:** Calculates profit margin by artist to inform procurement and pricing decisions.

```sql
SELECT
    r.ArtistName,
    SUM(ol.PriceAtSale) AS TotalRevenue,
    (SELECT SUM(sol.Quantity * sol.UnitCost)
     FROM SupplierOrderLine sol
     WHERE sol.ReleaseID IN (
         SELECT ReleaseID FROM ReleaseAlbum
         WHERE ArtistName = r.ArtistName
     )
    ) AS ApproximateCost,
    (SUM(ol.PriceAtSale) -
        (SELECT SUM(sol.Quantity * sol.UnitCost)
         FROM SupplierOrderLine sol
         WHERE sol.ReleaseID IN (
             SELECT ReleaseID FROM ReleaseAlbum
             WHERE ArtistName = r.ArtistName
         ))
    ) AS GrossProfit
FROM OrderLine ol
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
GROUP BY r.ArtistName
ORDER BY GrossProfit DESC;
```

**Sample Output:**

| ArtistName | TotalRevenue | ApproximateCost | GrossProfit |
|------------|--------------|-----------------|-------------|
| The Beatles | 3,450.00 | 1,725.00 | 1,725.00 |
| Pink Floyd | 2,890.00 | 1,560.00 | 1,330.00 |
| Miles Davis | 1,234.00 | 890.00 | 344.00 |

**Advanced Technique Explanation:**
- Correlated subquery references outer query's `ArtistName`
- Calculates cost from procurement records (SupplierOrderLine)
- Computes gross profit margin for business decision support

---

## 5.4 Query 4: Monthly Sales Channel Comparison

**Techniques Used:** `CASE` Statement (Pivot), `DATE_FORMAT()`, Conditional Aggregation

**Business Purpose:** Compares revenue performance between Online and In-Store channels over time to inform channel strategy.

```sql
SELECT
    DATE_FORMAT(OrderDate, '%Y-%m') AS SalesMonth,
    SUM(CASE WHEN OrderType = 'InStore'
             THEN TotalAmount ELSE 0 END) AS StoreRevenue,
    SUM(CASE WHEN OrderType = 'Online'
             THEN TotalAmount ELSE 0 END) AS OnlineRevenue,
    SUM(TotalAmount) AS TotalRevenue,
    ROUND(
        SUM(CASE WHEN OrderType = 'Online' THEN TotalAmount ELSE 0 END) /
        SUM(TotalAmount) * 100, 2
    ) AS OnlinePercentage
FROM CustomerOrder
WHERE OrderStatus IN ('Paid', 'Completed')
GROUP BY SalesMonth
ORDER BY SalesMonth DESC;
```

**Sample Output:**

| SalesMonth | StoreRevenue | OnlineRevenue | TotalRevenue | OnlinePercentage |
|------------|--------------|---------------|--------------|------------------|
| 2025-12 | 4,560.00 | 3,240.00 | 7,800.00 | 41.54% |
| 2025-11 | 3,890.00 | 2,890.00 | 6,780.00 | 42.63% |
| 2025-10 | 4,120.00 | 2,345.00 | 6,465.00 | 36.27% |

**Advanced Technique Explanation:**
- `CASE` statement acts as a pivot, splitting totals by channel
- `DATE_FORMAT()` groups by month regardless of day
- Derived column calculates online channel percentage

---

## 5.5 Query 5: Dead Stock Alert with Condition Analysis

**Techniques Used:** `HAVING` Clause, Date Arithmetic, `DATEDIFF()`

**Business Purpose:** Identifies slow-moving inventory (60+ days without sale) for potential markdown or clearance.

```sql
SELECT
    sh.Name AS ShopName,
    r.Title,
    r.ArtistName,
    s.ConditionGrade,
    s.BatchNo,
    s.UnitPrice,
    DATEDIFF(CURRENT_DATE, s.AcquiredDate) AS DaysInStock
FROM StockItem s
JOIN Shop sh ON s.ShopID = sh.ShopID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
WHERE s.Status = 'Available'
GROUP BY s.StockItemID, sh.Name, r.Title, r.ArtistName,
         s.ConditionGrade, s.BatchNo, s.UnitPrice, s.AcquiredDate
HAVING DaysInStock > 60
ORDER BY DaysInStock DESC;
```

**Sample Output:**

| ShopName | Title | ArtistName | ConditionGrade | DaysInStock |
|----------|-------|------------|----------------|-------------|
| Changsha | Kind of Blue | Miles Davis | VG | 95 |
| Shanghai | Abbey Road | The Beatles | VG+ | 78 |
| Warehouse | Thriller | Michael Jackson | NM | 65 |

**Advanced Technique Explanation:**
- `HAVING` clause filters on calculated column (DaysInStock)
- `DATEDIFF()` computes days between acquisition and current date
- Results prioritized by longest stock time for immediate attention

---

## 5.6 Additional Advanced Query: Batch Sell-Through Analysis

**Techniques Used:** CTE (Common Table Expression), Window Functions, Conditional Aggregation

**Business Purpose:** Analyzes sell-through rate by procurement batch to evaluate supplier quality and pricing decisions.

```sql
WITH BatchStats AS (
    SELECT
        s.BatchNo,
        r.Title,
        COUNT(*) AS TotalInBatch,
        SUM(CASE WHEN s.Status = 'Sold' THEN 1 ELSE 0 END) AS SoldCount,
        SUM(CASE WHEN s.Status = 'Available' THEN 1 ELSE 0 END) AS AvailableCount,
        AVG(s.UnitPrice) AS AvgPrice,
        MIN(s.AcquiredDate) AS BatchDate
    FROM StockItem s
    JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
    GROUP BY s.BatchNo, r.Title
)
SELECT
    BatchNo,
    Title,
    TotalInBatch,
    SoldCount,
    ROUND(SoldCount * 100.0 / TotalInBatch, 1) AS SellThroughRate,
    AvgPrice,
    DATEDIFF(CURRENT_DATE, BatchDate) AS DaysSincePurchase,
    CASE
        WHEN SoldCount * 100.0 / TotalInBatch >= 80 THEN 'Excellent'
        WHEN SoldCount * 100.0 / TotalInBatch >= 50 THEN 'Good'
        WHEN SoldCount * 100.0 / TotalInBatch >= 25 THEN 'Fair'
        ELSE 'Poor'
    END AS PerformanceRating
FROM BatchStats
ORDER BY SellThroughRate DESC;
```

**Sample Output:**

| BatchNo | Title | TotalInBatch | SoldCount | SellThroughRate | PerformanceRating |
|---------|-------|--------------|-----------|-----------------|-------------------|
| BATCH-20251201-1 | Abbey Road | 10 | 9 | 90.0% | Excellent |
| BATCH-20251115-2 | Dark Side | 8 | 5 | 62.5% | Good |
| BATCH-20251001-1 | Kind of Blue | 12 | 2 | 16.7% | Poor |

---

## 5.7 Summary of Advanced SQL Techniques

| Query | Techniques | Business Value |
|-------|------------|----------------|
| Inventory Turnover | DATEDIFF, AVG, Multi-JOIN | Optimize procurement timing |
| Customer LTV | DENSE_RANK Window Function | VIP identification |
| Artist Profit | Correlated Subquery | Pricing decisions |
| Channel Comparison | CASE Pivot, DATE_FORMAT | Channel strategy |
| Dead Stock | HAVING, Date Arithmetic | Clearance planning |
| Batch Analysis | CTE, Conditional Aggregation | Supplier evaluation |

---

*End of Advanced Queries Section*
