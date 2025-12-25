-- seeds.sql - 完整测试数据
-- 密码统一为 'password123' 的 BCrypt Hash
SET FOREIGN_KEY_CHECKS = 0;

-- 清空现有数据（按依赖顺序）
DELETE FROM OrderLine;
DELETE FROM CustomerOrder;
DELETE FROM InventoryTransfer;
DELETE FROM StockItem;
DELETE FROM SupplierOrderLine;
DELETE FROM SupplierOrder;
DELETE FROM BuybackOrderLine;
DELETE FROM BuybackOrder;
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
ALTER TABLE SupplierOrder AUTO_INCREMENT = 1;
ALTER TABLE BuybackOrder AUTO_INCREMENT = 1;
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

-- 2. 插入供应商订单 (替换原 PurchaseOrder 1-4 号数据)
INSERT INTO SupplierOrder (SupplierID, CreatedByEmployeeID, DestinationShopID, OrderDate, Status) VALUES
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received'),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received'),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received'),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Received');

-- 3. 插入供应商订单明细 (替换原 PurchaseOrderLine 1-11 行)
INSERT INTO SupplierOrderLine (SupplierOrderID, ReleaseID, Quantity, UnitCost) VALUES
(1, 1, 10, 20.00), (1, 2, 8, 25.00),
(2, 3, 15, 15.00), (2, 4, 10, 18.00), (2, 5, 12, 22.00),
(3, 6, 20, 20.00), (3, 7, 15, 24.00), (3, 8, 10, 28.00),
(4, 9, 25, 22.00), (4, 10, 20, 20.00), (4, 11, 15, 18.00);

-- 4. 插入回购订单 (替换原 PurchaseOrder 5 号数据)
INSERT INTO BuybackOrder (CustomerID, ProcessedByEmployeeID, ShopID, BuybackDate, Status) VALUES
(2, 3, 3, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Completed');

-- 5. 插入回购订单明细 (替换原 PurchaseOrderLine 12-13 行)
INSERT INTO BuybackOrderLine (BuybackOrderID, ReleaseID, Quantity, UnitPrice, ConditionGrade) VALUES
(1, 12, 3, 12.00, 'VG+'),
(1, 13, 2, 15.00, 'NM')
-- ==========================================
-- 7. 库存项 (修正后的数据，确保 ID 对应)
-- ==========================================

-- [ID 1-4] 长沙店库存 (ShopID=1)
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'Mint', 'Available', 42.00, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'Mint', 'Available', 42.00, DATE_SUB(NOW(), INTERVAL 70 DAY));

-- [ID 5-10] 上海店库存 (ShopID=2)
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(3, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(3, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 30.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 30.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Available', 36.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Available', 36.00, DATE_SUB(NOW(), INTERVAL 30 DAY));

-- [ID 11-24] 仓库库存 (ShopID=3)
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- [ID 14] 下面这条是用于测试“在途”状态的，必须设为 InTransit
(7, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'InTransit', 38.00, DATE_SUB(NOW(), INTERVAL 10 DAY)), 
(7, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 38.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(8, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 45.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(8, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 45.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(9, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(9, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(9, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(10, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(10, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(11, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(11, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- [ID 25-27] 二手唱片 (回购)
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(12, 3, 'Buyback', 1, 'BUYBACK-20251215', 'VG+', 'Available', 22.00, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(12, 3, 'Buyback', 1, 'BUYBACK-20251215', 'VG', 'Available', 18.00, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(13, 3, 'Buyback', 1, 'BUYBACK-20251215', 'NM', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- [ID 28-35] 已售出的库存项 (8项)
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate, DateSold) VALUES
-- 长沙门店销售
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Sold', 35.00, DATE_SUB(NOW(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'Mint', 'Sold', 42.00, DATE_SUB(NOW(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 55 DAY)),
-- 上海门店销售
(3, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Sold', 25.00, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Sold', 30.00, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Sold', 36.00, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
-- 线上仓库销售 (对应 ID 33, 34, 35)
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
-- 9. 订单明细 (修正 StockItemID)
-- ==========================================
-- 注意：ID 必须与上面 StockItem 的生成顺序对应
-- 28-35 是 Sold 的项目
INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES
(1, 28, 35.00), -- Abbey Road
(2, 29, 42.00), -- Dark Side
(3, 30, 25.00), -- Thriller
(4, 31, 30.00), -- Kind of Blue
(5, 32, 36.00), -- Back in Black
(6, 33, 32.00), -- Rumours
(7, 34, 38.00), -- Led Zeppelin
(8, 35, 45.00); -- The Wall

-- ==========================================
-- 10. 库存转运记录
-- ==========================================
INSERT INTO InventoryTransfer (StockItemID, FromShopID, ToShopID, TransferDate, AuthorizedByEmployeeID, ReceivedByEmployeeID, Status, ReceivedDate) VALUES
-- ID 5, 6 现在在 Shop 2，且状态是 Available。
-- 逻辑：它们原本在 Shop 1，通过此调拨单移动到了 Shop 2。数据一致。
(5, 1, 2, DATE_SUB(NOW(), INTERVAL 40 DAY), 2, 5, 'Completed', DATE_SUB(NOW(), INTERVAL 39 DAY)),
(6, 1, 2, DATE_SUB(NOW(), INTERVAL 35 DAY), 2, 5, 'Completed', DATE_SUB(NOW(), INTERVAL 34 DAY)),

-- ID 14 在 StockItem 中被我们手动修正为 'InTransit' 且 ShopID=3 (源)
-- 逻辑：正在从 Warehouse 发往 CS，尚未接收。数据一致。
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
