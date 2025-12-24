-- seeds.sql - 完整测试数据
-- 密码统一为 'password123' 的 BCrypt Hash
SET FOREIGN_KEY_CHECKS = 0;

-- 清空现有数据（按依赖顺序）
DELETE FROM OrderLine;
DELETE FROM CustomerOrder;
DELETE FROM InventoryTransfer;
DELETE FROM StockItem;
DELETE FROM PurchaseOrderLine;
DELETE FROM PurchaseOrder;
DELETE FROM Track;
DELETE FROM ReleaseAlbum;
DELETE FROM Customer;
DELETE FROM Employee;
DELETE FROM Supplier;
DELETE FROM MembershipTier;
DELETE FROM Shop;

-- 重置自增ID
ALTER TABLE Shop AUTO_INCREMENT = 1;
ALTER TABLE MembershipTier AUTO_INCREMENT = 1;
ALTER TABLE Employee AUTO_INCREMENT = 1;
ALTER TABLE Customer AUTO_INCREMENT = 1;
ALTER TABLE Supplier AUTO_INCREMENT = 1;
ALTER TABLE ReleaseAlbum AUTO_INCREMENT = 1;
ALTER TABLE PurchaseOrder AUTO_INCREMENT = 1;
ALTER TABLE StockItem AUTO_INCREMENT = 1;
ALTER TABLE CustomerOrder AUTO_INCREMENT = 1;
ALTER TABLE InventoryTransfer AUTO_INCREMENT = 1;

-- ==========================================
-- 1. 基础组织数据
-- ==========================================
INSERT INTO Shop (Name, Address, Type) VALUES
('Changsha Flagship Store', '123 Vinyl St, Changsha', 'Retail'),
('Shanghai Branch', '456 Groove Ave, Shanghai', 'Retail'),
('Online Warehouse', 'No. 8 Logistics Park, Changsha', 'Warehouse');

-- UserRole table removed; Role is now ENUM in Employee table

INSERT INTO MembershipTier (TierName, MinPoints, DiscountRate) VALUES
('Standard', 0, 0.00),
('VIP', 1000, 0.05),
('Gold', 5000, 0.10);

-- ==========================================
-- 2. 员工账号 (密码: password123)
-- Role is now ENUM directly in Employee table
-- ==========================================
INSERT INTO Employee (ShopID, Role, Name, Username, PasswordHash, HireDate) VALUES
(1, 'Admin', 'Super Admin', 'admin', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-01-01'),
(1, 'Manager', 'Changsha Manager', 'manager_cs', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-03-15'),
(1, 'Staff', 'Changsha Staff', 'staff_cs', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-06-01'),
(2, 'Manager', 'Shanghai Manager', 'manager_sh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-04-01'),
(2, 'Staff', 'Shanghai Staff', 'staff_sh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-07-15'),
(3, 'Staff', 'Warehouse Packer', 'staff_wh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-08-01');

-- ==========================================
-- 3. 客户数据 (密码: password123)
-- ==========================================
INSERT INTO Customer (Name, Email, PasswordHash, TierID, Points, Birthday) VALUES
('Alice Fan', 'alice@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 2, 1500, '1995-05-20'),
('Bob Collector', 'bob@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 3, 6200, '1988-12-15'),
('Charlie New', 'charlie@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 1, 150, '2000-01-01'),
('Diana Vinyl', 'diana@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 2, 2300, '1992-12-23'),
('Edward Rock', 'edward@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 1, 450, '1985-07-04');

-- ==========================================
-- 4. 供应商
-- ==========================================
INSERT INTO Supplier (Name, Email) VALUES
('Sony Music CN', 'sales@sonymusic.cn'),
('Universal Records', 'contact@universal.com'),
('Warner Music Asia', 'asia@warnermusic.com'),
('EMI Classics', 'classics@emi.com');

-- ==========================================
-- 5. 产品目录 (更多唱片)
-- ==========================================
INSERT INTO ReleaseAlbum (Title, ArtistName, LabelName, ReleaseYear, Genre, Format, Description) VALUES
('Abbey Road', 'The Beatles', 'Apple Records', '1969', 'Rock', 'Vinyl', 'The eleventh studio album by the English rock band the Beatles.'),
('The Dark Side of the Moon', 'Pink Floyd', 'Harvest', '1973', 'Progressive Rock', 'Vinyl', 'A concept album with themes of conflict, greed, time, and mental illness.'),
('Thriller', 'Michael Jackson', 'Epic', '1982', 'Pop', 'Vinyl', 'The best-selling album of all time.'),
('Kind of Blue', 'Miles Davis', 'Columbia', '1959', 'Jazz', 'Vinyl', 'Regarded by many critics as the greatest jazz record.'),
('Back in Black', 'AC/DC', 'Atlantic', '1980', 'Hard Rock', 'Vinyl', 'The second-highest-selling album of all time.'),
('Rumours', 'Fleetwood Mac', 'Warner Bros.', '1977', 'Soft Rock', 'Vinyl', 'One of the best-selling albums ever.'),
('Led Zeppelin IV', 'Led Zeppelin', 'Atlantic', '1971', 'Hard Rock', 'Vinyl', 'Features the iconic Stairway to Heaven.'),
('The Wall', 'Pink Floyd', 'Harvest', '1979', 'Progressive Rock', 'Vinyl', 'A rock opera about isolation and abandonment.'),
('A Night at the Opera', 'Queen', 'EMI', '1975', 'Rock', 'Vinyl', 'Contains the legendary Bohemian Rhapsody.'),
('Hotel California', 'Eagles', 'Asylum', '1976', 'Rock', 'Vinyl', 'Their fifth studio album featuring the title track.'),
('Born to Run', 'Bruce Springsteen', 'Columbia', '1975', 'Rock', 'Vinyl', 'A breakthrough album for The Boss.'),
('Blue', 'Joni Mitchell', 'Reprise', '1971', 'Folk', 'Vinyl', 'Widely regarded as a masterpiece of confessional songwriting.'),
('What is Going On', 'Marvin Gaye', 'Tamla', '1971', 'Soul', 'Vinyl', 'A groundbreaking concept album.'),
('Purple Rain', 'Prince', 'Warner Bros.', '1984', 'Pop/Rock', 'Vinyl', 'Soundtrack album to the film of the same name.'),
('Nevermind', 'Nirvana', 'DGC', '1991', 'Grunge', 'Vinyl', 'The album that brought grunge to the mainstream.');

-- 添加一些曲目数据
INSERT INTO Track (ReleaseID, Title, TrackNumber, Duration) VALUES
(1, 'Come Together', 1, '4:20'),
(1, 'Something', 2, '3:03'),
(1, 'Here Comes the Sun', 7, '3:06'),
(2, 'Speak to Me', 1, '1:30'),
(2, 'Breathe', 2, '2:43'),
(2, 'Time', 4, '7:06'),
(2, 'Money', 6, '6:22'),
(3, 'Wanna Be Startin Somethin', 1, '6:03'),
(3, 'Thriller', 4, '5:57'),
(3, 'Billie Jean', 6, '4:54'),
(3, 'Beat It', 5, '4:18');

-- ==========================================
-- 6. 采购订单
-- BuybackCustomerID added to track which customer sold items back
-- ==========================================
INSERT INTO PurchaseOrder (SupplierID, BuybackCustomerID, CreatedByEmployeeID, OrderDate, Status, SourceType) VALUES
-- 70天前的采购，用于测试滞销预警
(1, NULL, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', 'Supplier'),
-- 30天前的采购
(2, NULL, 1, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', 'Supplier'),
-- 10天前的采购
(3, NULL, 1, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', 'Supplier'),
-- 最近的采购
(4, NULL, 2, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Received', 'Supplier'),
-- 二手回购 - 从客户Bob Collector (CustomerID=2) 处回购
(NULL, 2, 3, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Received', 'Buyback');

INSERT INTO PurchaseOrderLine (PO_ID, ReleaseID, Quantity, UnitCost) VALUES
-- PO 1: 老库存
(1, 1, 10, 20.00),  -- Abbey Road
(1, 2, 8, 25.00),   -- Dark Side
-- PO 2: 中期库存
(2, 3, 15, 15.00),  -- Thriller
(2, 4, 10, 18.00),  -- Kind of Blue
(2, 5, 12, 22.00),  -- Back in Black
-- PO 3: 新库存
(3, 6, 20, 20.00),  -- Rumours
(3, 7, 15, 24.00),  -- Led Zeppelin IV
(3, 8, 10, 28.00),  -- The Wall
-- PO 4: 最新库存
(4, 9, 25, 22.00),  -- A Night at the Opera
(4, 10, 20, 20.00), -- Hotel California
(4, 11, 15, 18.00), -- Born to Run
-- PO 5: 二手回购
(5, 12, 3, 12.00),  -- Blue (二手)
(5, 13, 2, 15.00);  -- What's Going On (二手)

-- ==========================================
-- 7. 库存项 (更完整的数据)
-- ==========================================

-- 长沙店库存 (ShopID=1) - 滞销测试
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'Mint', 'Available', 42.00, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'Mint', 'Available', 42.00, DATE_SUB(NOW(), INTERVAL 70 DAY));

-- 上海店库存 (ShopID=2)
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(3, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(3, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 30.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 30.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Available', 36.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Available', 36.00, DATE_SUB(NOW(), INTERVAL 30 DAY));

-- 仓库库存 (ShopID=3) - 线上销售
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
-- Rumours
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- Led Zeppelin IV
(7, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 38.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(7, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 38.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- The Wall
(8, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 45.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(8, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 45.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- A Night at the Opera
(9, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(9, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(9, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
-- Hotel California
(10, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(10, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
-- Born to Run
(11, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(11, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
-- 二手唱片 (回购)
(12, 3, 'Buyback', 1, 'BUYBACK-20251215', 'VG+', 'Available', 22.00, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(12, 3, 'Buyback', 1, 'BUYBACK-20251215', 'VG', 'Available', 18.00, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(13, 3, 'Buyback', 1, 'BUYBACK-20251215', 'NM', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- 已售出的库存项 (用于报表测试)
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate, DateSold) VALUES
-- 长沙门店销售
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Sold', 35.00, DATE_SUB(NOW(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'Mint', 'Sold', 42.00, DATE_SUB(NOW(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 55 DAY)),
-- 上海门店销售
(3, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Sold', 25.00, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Sold', 30.00, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Sold', 36.00, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
-- 线上仓库销售
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Sold', 32.00, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
(7, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Sold', 38.00, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(8, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Sold', 45.00, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY));

-- ==========================================
-- 8. 销售订单 (各种状态)
-- ==========================================

-- 已完成的门店订单 (用于报表)
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES
(1, 1, DATE_SUB(NOW(), INTERVAL 60 DAY), 35.00, 'Completed', 'InStore'),
(2, 1, DATE_SUB(NOW(), INTERVAL 55 DAY), 42.00, 'Completed', 'InStore'),
(1, 2, DATE_SUB(NOW(), INTERVAL 20 DAY), 25.00, 'Completed', 'InStore'),
(3, 2, DATE_SUB(NOW(), INTERVAL 18 DAY), 30.00, 'Completed', 'InStore'),
(2, 2, DATE_SUB(NOW(), INTERVAL 15 DAY), 36.00, 'Completed', 'InStore');

-- 已完成的线上订单
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES
(1, 3, DATE_SUB(NOW(), INTERVAL 7 DAY), 32.00, 'Completed', 'Online'),
(2, 3, DATE_SUB(NOW(), INTERVAL 5 DAY), 38.00, 'Completed', 'Online'),
(4, 3, DATE_SUB(NOW(), INTERVAL 3 DAY), 45.00, 'Completed', 'Online');

-- 待支付的订单 (Pending)
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES
(4, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), 67.00, 'Pending', 'Online'),
(5, 3, NOW(), 35.00, 'Pending', 'Online');

-- 已支付待发货 (Paid)
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES
(1, 3, DATE_SUB(NOW(), INTERVAL 2 DAY), 70.00, 'Paid', 'Online'),
(3, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), 32.00, 'Paid', 'Online');

-- 已发货 (Shipped)
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES
(2, 3, DATE_SUB(NOW(), INTERVAL 3 DAY), 56.00, 'Shipped', 'Online');

-- ==========================================
-- 9. 订单明细
-- ==========================================

-- 关联已售出的库存到订单
INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES
-- 订单1: Abbey Road
(1, 32, 35.00),
-- 订单2: Dark Side
(2, 33, 42.00),
-- 订单3: Thriller
(3, 34, 25.00),
-- 订单4: Kind of Blue
(4, 35, 30.00),
-- 订单5: Back in Black
(5, 36, 36.00),
-- 订单6: Rumours (线上已完成)
(6, 37, 32.00),
-- 订单7: Led Zeppelin IV (线上已完成)
(7, 38, 38.00),
-- 订单8: The Wall (线上已完成)
(8, 39, 45.00);

-- ==========================================
-- 10. 库存转运记录
-- Now includes Status, ReceivedByEmployeeID, ReceivedDate for complete tracking
-- ==========================================
INSERT INTO InventoryTransfer (StockItemID, FromShopID, ToShopID, TransferDate, AuthorizedByEmployeeID, ReceivedByEmployeeID, Status, ReceivedDate) VALUES
-- 已完成的转运：长沙店 -> 上海店
(5, 1, 2, DATE_SUB(NOW(), INTERVAL 40 DAY), 2, 5, 'Completed', DATE_SUB(NOW(), INTERVAL 39 DAY)),
(6, 1, 2, DATE_SUB(NOW(), INTERVAL 35 DAY), 2, 5, 'Completed', DATE_SUB(NOW(), INTERVAL 34 DAY)),
-- 进行中的转运：仓库 -> 长沙店 (等待接收)
(14, 3, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 6, NULL, 'InTransit', NULL);

SET FOREIGN_KEY_CHECKS = 1;

-- 输出测试账号信息
SELECT '========== TEST ACCOUNTS ==========' AS Info;
SELECT 'All passwords: password123' AS Password;
SELECT '' AS '';
SELECT 'ADMIN: admin' AS Account;
SELECT 'MANAGERS: manager_cs, manager_sh' AS Account;
SELECT 'STAFF: staff_cs, staff_sh, staff_wh' AS Account;
SELECT 'CUSTOMERS: alice@test.com, bob@test.com, charlie@test.com, diana@test.com, edward@test.com' AS Account;
