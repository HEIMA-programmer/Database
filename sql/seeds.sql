-- seeds.sql - 完整测试数据
-- 密码统一为 'password123' 的 BCrypt Hash
-- 【修复版】确保数据一致性：订单、库存、积分正确关联

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

INSERT INTO MembershipTier (TierName, MinPoints, DiscountRate) VALUES
('Standard', 0, 0.00),
('VIP', 1000, 0.05),
('Gold', 5000, 0.10);

-- ==========================================
-- 2. 员工账号 (密码: password123)
-- ==========================================
INSERT INTO Employee (ShopID, Role, Name, Username, PasswordHash, HireDate) VALUES
(1, 'Admin', 'Super Admin', 'admin', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-01-01'),
(1, 'Manager', 'Changsha Manager', 'manager_cs', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-03-15'),
(1, 'Staff', 'Changsha Staff', 'staff_cs', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-06-01'),
(2, 'Manager', 'Shanghai Manager', 'manager_sh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-04-01'),
(2, 'Staff', 'Shanghai Staff', 'staff_sh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-07-15'),
(3, 'Manager', 'Warehouse Manager', 'manager_wh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-05-01'),
(3, 'Staff', 'Warehouse Packer', 'staff_wh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-08-01');

-- ==========================================
-- 3. 客户数据 (密码: password123)
-- 【修复】积分与已完成订单匹配
-- 计算公式：积分 = 已完成订单的TotalAmount之和（向下取整）
-- ==========================================
INSERT INTO Customer (Name, Email, PasswordHash, TierID, Points, Birthday) VALUES
-- Alice: 已完成订单 1,3,6 => 35+25+32=92积分，升级后VIP需要补充到1500
('Alice Fan', 'alice@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 2, 1500, '1995-05-20'),
-- Bob: 已完成订单 2,5,7 => 42+36+38=116积分，升级后Gold需要补充到6200
('Bob Collector', 'bob@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 3, 6200, '1988-12-15'),
-- Charlie: 已完成订单 4 => 30积分 + 补充到150
('Charlie New', 'charlie@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 1, 150, '2000-01-01'),
-- Diana: 已完成订单 8 => 45积分 + 补充到2300
('Diana Vinyl', 'diana@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 2, 2300, '1992-12-23'),
-- Edward: 无已完成订单，补充到450
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
-- 5. 产品目录
-- 【重构】添加BaseUnitCost字段，每张专辑有不同的基础采购成本
-- 注意：同一专辑不同condition的实际成本 = BaseUnitCost × condition系数
-- condition系数：New=1.00, Mint=0.95, NM=0.85, VG+=0.70, VG=0.55
-- ==========================================
INSERT INTO ReleaseAlbum (Title, ArtistName, LabelName, ReleaseYear, Genre, Format, Description, BaseUnitCost) VALUES
('Abbey Road', 'The Beatles', 'Apple Records', '1969', 'Rock', 'Vinyl', 'The eleventh studio album by the English rock band the Beatles.', 35.00),
('The Dark Side of the Moon', 'Pink Floyd', 'Harvest', '1973', 'Progressive Rock', 'Vinyl', 'A concept album with themes of conflict, greed, time, and mental illness.', 40.00),
('Thriller', 'Michael Jackson', 'Epic', '1982', 'Pop', 'Vinyl', 'The best-selling album of all time.', 25.00),
('Kind of Blue', 'Miles Davis', 'Columbia', '1959', 'Jazz', 'Vinyl', 'Regarded by many critics as the greatest jazz record.', 45.00),
('Back in Black', 'AC/DC', 'Atlantic', '1980', 'Hard Rock', 'Vinyl', 'The second-highest-selling album of all time.', 30.00),
('Rumours', 'Fleetwood Mac', 'Warner Bros.', '1977', 'Soft Rock', 'Vinyl', 'One of the best-selling albums ever.', 32.00),
('Led Zeppelin IV', 'Led Zeppelin', 'Atlantic', '1971', 'Hard Rock', 'Vinyl', 'Features the iconic Stairway to Heaven.', 38.00),
('The Wall', 'Pink Floyd', 'Harvest', '1979', 'Progressive Rock', 'Vinyl', 'A rock opera about isolation and abandonment.', 42.00),
('A Night at the Opera', 'Queen', 'EMI', '1975', 'Rock', 'Vinyl', 'Contains the legendary Bohemian Rhapsody.', 36.00),
('Hotel California', 'Eagles', 'Asylum', '1976', 'Rock', 'Vinyl', 'Their fifth studio album featuring the title track.', 28.00),
('Born to Run', 'Bruce Springsteen', 'Columbia', '1975', 'Rock', 'Vinyl', 'A breakthrough album for The Boss.', 26.00),
('Blue', 'Joni Mitchell', 'Reprise', '1971', 'Folk', 'Vinyl', 'Widely regarded as a masterpiece of confessional songwriting.', 22.00),
('What is Going On', 'Marvin Gaye', 'Tamla', '1971', 'Soul', 'Vinyl', 'A groundbreaking concept album.', 20.00),
('Purple Rain', 'Prince', 'Warner Bros.', '1984', 'Pop/Rock', 'Vinyl', 'Soundtrack album to the film of the same name.', 24.00),
('Nevermind', 'Nirvana', 'DGC', '1991', 'Grunge', 'Vinyl', 'The album that brought grunge to the mainstream.', 18.00);

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
-- 6. 供应商订单
-- ==========================================
INSERT INTO SupplierOrder (SupplierID, CreatedByEmployeeID, DestinationShopID, OrderDate, Status, ReceivedDate) VALUES
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY));

INSERT INTO SupplierOrderLine (SupplierOrderID, ReleaseID, Quantity, UnitCost) VALUES
(1, 1, 5, 20.00), (1, 2, 5, 25.00),
(2, 3, 5, 15.00), (2, 4, 5, 18.00), (2, 5, 5, 22.00),
(3, 6, 5, 20.00), (3, 7, 5, 24.00), (3, 8, 5, 28.00),
(4, 9, 5, 22.00), (4, 10, 5, 20.00), (4, 11, 5, 18.00);

-- ==========================================
-- 7. 回购订单
-- ==========================================
-- 改为（员工6属于仓库ShopID=3）：
INSERT INTO BuybackOrder (CustomerID, ProcessedByEmployeeID, ShopID, BuybackDate, Status, TotalPayment) VALUES
(2, 6, 3, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Completed', 66.00);

-- 【修复】BuybackOrderID 都改为 1，因为只有一个回购订单
INSERT INTO BuybackOrderLine (BuybackOrderID, ReleaseID, Quantity, UnitPrice, ConditionGrade) VALUES
(1, 9, 3, 12.00, 'VG+');

-- ==========================================
-- 8. 库存项 - 【修复】确保状态正确
-- ==========================================

-- 长沙店库存 (ShopID=1) - ID 1-10
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 'Supplier', 1, 'B20251001-CS', 'Mint', 'Available', 33.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 42.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 42.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'Mint', 'Available', 40.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'VG+', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Sold', 35.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 1, 'B20251001-CS', 'Mint', 'Sold', 42.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 'Supplier', 1, 'B20251001-CS', 'VG+', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 69 DAY));

-- 上海店库存 (ShopID=2) - ID 11-25
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(3, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(3, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(3, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Sold', 25.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 30.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Sold', 30.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'NM', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Available', 36.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Sold', 36.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'VG+', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(3, 2, 'Supplier', 2, 'B20251115-SH', 'VG', 'Available', 20.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'VG', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'New', 'Available', 36.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(3, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Available', 24.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 2, 'B20251115-SH', 'Mint', 'Available', 29.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 2, 'B20251115-SH', 'NM', 'Available', 34.00, DATE_SUB(NOW(), INTERVAL 49 DAY));

-- 仓库库存 (ShopID=3) - ID 26-55
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Sold', 32.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 38.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Sold', 38.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 3, 'B20251210-WH', 'Mint', 'Available', 36.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Available', 45.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 3, 'B20251210-WH', 'New', 'Sold', 45.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 3, 'B20251210-WH', 'VG+', 'Available', 38.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 3, 'Supplier', 3, 'B20251210-WH', 'VG', 'Available', 26.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 3, 'B20251210-WH', 'VG', 'Available', 30.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 3, 'B20251210-WH', 'NM', 'Available', 42.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 3, 'Supplier', 3, 'B20251210-WH', 'Mint', 'Available', 30.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 3, 'B20251210-WH', 'NM', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 3, 'B20251210-WH', 'Mint', 'Available', 43.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
-- 最新批次
(9, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(9, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(9, 3, 'Supplier', 4, 'B20251218-WH', 'Mint', 'Available', 33.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 4, 'B20251218-WH', 'VG+', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 4, 'B20251218-WH', 'New', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 4, 'B20251218-WH', 'NM', 'Available', 26.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(9, 3, 'Supplier', 4, 'B20251218-WH', 'VG', 'Available', 28.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 4, 'B20251218-WH', 'Mint', 'Available', 30.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 4, 'B20251218-WH', 'VG+', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(9, 3, 'Supplier', 4, 'B20251218-WH', 'NM', 'Available', 32.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 4, 'B20251218-WH', 'NM', 'Available', 29.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 4, 'B20251218-WH', 'Mint', 'Available', 27.00, DATE_SUB(NOW(), INTERVAL 9 DAY));

-- 回购二手唱片 - ID 56-60
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(9, 3, 'Buyback', 1, 'BUY-20251211', 'VG+', 'Available', 22.00, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9, 3, 'Buyback', 1, 'BUY-20251211', 'VG+', 'Available', 22.00, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9, 3, 'Buyback', 1, 'BUY-20251211', 'VG+', 'Available', 22.00, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9, 3, 'Buyback', 1, 'BUY-20251211', 'VG', 'Available', 18.00, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9, 3, 'Buyback', 1, 'BUY-20251211', 'VG', 'Available', 18.00, DATE_SUB(NOW(), INTERVAL 15 DAY));

-- ==========================================
-- 9. 销售订单 - 【修复】确保状态和数据一致
-- ==========================================

-- 已完成的门店订单 (用于报表)
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, ProcessedByEmployeeID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES
(1, 1, 3, DATE_SUB(NOW(), INTERVAL 60 DAY), 35.00, 'Completed', 'InStore'),  -- 订单1: Alice 在长沙店购买
(2, 1, 3, DATE_SUB(NOW(), INTERVAL 55 DAY), 42.00, 'Completed', 'InStore'),  -- 订单2: Bob 在长沙店购买
(1, 2, 5, DATE_SUB(NOW(), INTERVAL 45 DAY), 25.00, 'Completed', 'InStore'),  -- 订单3: Alice 在上海店购买
(3, 2, 5, DATE_SUB(NOW(), INTERVAL 40 DAY), 30.00, 'Completed', 'InStore'),  -- 订单4: Charlie 在上海店购买
(2, 2, 5, DATE_SUB(NOW(), INTERVAL 35 DAY), 36.00, 'Completed', 'InStore');  -- 订单5: Bob 在上海店购买

-- 已完成的线上订单
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES
(1, 3, DATE_SUB(NOW(), INTERVAL 20 DAY), 32.00, 'Completed', 'Online'),  -- 订单6: Alice 线上购买
(2, 3, DATE_SUB(NOW(), INTERVAL 15 DAY), 38.00, 'Completed', 'Online'),  -- 订单7: Bob 线上购买
(4, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 45.00, 'Completed', 'Online');  -- 订单8: Diana 线上购买

-- ==========================================
-- 10. 订单明细 - 【修复】关联正确的库存ID
-- ==========================================
INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES
-- 门店订单
(1, 8, 35.00),   -- 订单1: Abbey Road New (已售)
(2, 9, 42.00),   -- 订单2: Dark Side Mint (已售)
(3, 13, 25.00),  -- 订单3: Thriller New (已售)
(4, 15, 30.00),  -- 订单4: Kind of Blue New (已售)
(5, 18, 36.00),  -- 订单5: Back in Black Mint (已售)
-- 线上订单
(6, 28, 32.00),  -- 订单6: Rumours New (已售)
(7, 31, 38.00),  -- 订单7: Led Zeppelin New (已售)
(8, 34, 45.00);  -- 订单8: The Wall New (已售)

-- 更新已售库存的售出日期
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 60 DAY) WHERE StockItemID = 8;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 55 DAY) WHERE StockItemID = 9;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 45 DAY) WHERE StockItemID = 13;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 40 DAY) WHERE StockItemID = 15;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 35 DAY) WHERE StockItemID = 18;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 20 DAY) WHERE StockItemID = 28;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 15 DAY) WHERE StockItemID = 31;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE StockItemID = 34;

-- ==========================================
-- 11. 库存调拨记录
-- ==========================================
-- 将一些仓库库存调拨到门店
INSERT INTO InventoryTransfer (StockItemID, FromShopID, ToShopID, TransferDate, AuthorizedByEmployeeID, ReceivedByEmployeeID, Status, ReceivedDate) VALUES
(26, 3, 1, DATE_SUB(NOW(), INTERVAL 25 DAY), 6, 3, 'Completed', DATE_SUB(NOW(), INTERVAL 24 DAY)),
(29, 3, 2, DATE_SUB(NOW(), INTERVAL 20 DAY), 6, 5, 'Completed', DATE_SUB(NOW(), INTERVAL 19 DAY));

-- 更新已调拨库存的店铺ID
UPDATE StockItem SET ShopID = 1 WHERE StockItemID = 26;
UPDATE StockItem SET ShopID = 2 WHERE StockItemID = 29;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 12. 输出测试账号信息
-- ==========================================
SELECT '========== TEST ACCOUNTS ==========' AS Info;
SELECT 'All passwords: password123' AS Password;
SELECT '' AS '';
SELECT 'ADMIN: admin' AS Account;
SELECT 'MANAGERS: manager_cs, manager_sh' AS Account;
SELECT 'STAFF: staff_cs, staff_sh, staff_wh' AS Account;
SELECT 'CUSTOMERS: alice@test.com, bob@test.com, charlie@test.com, diana@test.com, edward@test.com' AS Account;

-- ==========================================
-- 数据一致性说明
-- ==========================================
-- 1. 所有已完成订单(Completed)都有对应的OrderLine和已售库存(Sold)
-- 2. 所有Sold状态的库存都有DateSold
-- 3. 客户积分与订单历史相关（注：现有积分包含历史消费积累）
-- 4. 没有未关联的Pending/Paid/Shipped订单
-- 5. 库存调拨记录与库存位置一致
