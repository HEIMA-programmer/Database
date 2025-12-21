-- 4_seed_data.sql
SET FOREIGN_KEY_CHECKS = 0;

-- 1. 基础组织数据
INSERT INTO Shop (Name, Address, Type) VALUES 
('Changsha Flagship Store', '123 Vinyl St, Changsha', 'Retail'),
('Shanghai Branch', '456 Groove Ave, Shanghai', 'Retail'),
('Online Warehouse', 'No. 8 Logistics Park, Changsha', 'Warehouse');

INSERT INTO UserRole (RoleName) VALUES 
('Admin'), ('Manager'), ('Staff'), ('Customer');

INSERT INTO MembershipTier (TierName, MinPoints, DiscountRate) VALUES 
('Standard', 0, 0.00),
('VIP', 1000, 0.05),
('Gold', 5000, 0.10);

-- 2. 员工账号 (密码统一为 'password123' 的 Hash 值)
-- 假设 password123 的 hash 是 $2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2
INSERT INTO Employee (ShopID, RoleID, Name, Username, PasswordHash) VALUES 
(1, 1, 'Super Admin', 'admin', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2'),
(1, 2, 'Changsha Manager', 'manager_cs', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2'),
(1, 3, 'Changsha Staff', 'staff_cs', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2'),
(2, 2, 'Shanghai Manager', 'manager_sh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2'),
(2, 3, 'Shanghai Staff', 'staff_sh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2'),
(3, 3, 'Warehouse Packer', 'staff_wh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2');

-- 3. 客户数据
INSERT INTO Customer (Name, Email, PasswordHash, TierID, Points, Birthday) VALUES 
('Alice Fan', 'alice@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 2, 1200, '1995-05-20'),
('Bob Collector', 'bob@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 3, 5500, '1988-12-15'),
('Charlie New', 'charlie@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 1, 100, '2000-01-01');

-- 4. 供应商
INSERT INTO Supplier (Name, Email) VALUES 
('Sony Music CN', 'sales@sonymusic.cn'),
('Universal Records', 'contact@universal.com');

-- 5. 产品目录
INSERT INTO ReleaseAlbum (Title, ArtistName, LabelName, ReleaseYear, Genre, Format, Description) VALUES 
('Abbey Road', 'The Beatles', 'Apple Records', '1969', 'Rock', 'Vinyl', 'The eleventh studio album by the English rock band the Beatles.'),
('The Dark Side of the Moon', 'Pink Floyd', 'Harvest', '1973', 'Progressive Rock', 'Vinyl', 'A concept album themes include conflict, greed, time, and mental illness.'),
('Thriller', 'Michael Jackson', 'Epic', '1982', 'Pop', 'Vinyl', 'The best-selling album of all time.'),
('Kind of Blue', 'Miles Davis', 'Columbia', '1959', 'Jazz', 'Vinyl', 'Regarded by many critics as the greatest jazz record.');

-- 6. 采购订单 (进货)
INSERT INTO PurchaseOrder (SupplierID, CreatedByEmployeeID, OrderDate, Status, SourceType) VALUES 
(1, 2, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', 'Supplier'), -- 70天前，用于测试滞销预警
(2, 2, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', 'Supplier');

INSERT INTO PurchaseOrderLine (PO_ID, ReleaseID, Quantity, UnitCost) VALUES 
(1, 1, 50, 20.00), -- Abbey Road
(1, 2, 30, 25.00), -- Dark Side
(2, 3, 20, 15.00), -- Thriller
(2, 4, 10, 18.00); -- Kind of Blue

-- 7. 库存项 (物理生成)
-- 为了方便，这里只手动插入几条代表性的。实际系统中这通常由PHP循环插入
INSERT INTO StockItem (ReleaseID, ShopID, SourcePO_ID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES 
-- 长沙店库存 (Abbey Road) - 滞销测试
(1, 1, 1, 'B20250901', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(1, 1, 1, 'B20250901', 'New', 'Available', 35.00, DATE_SUB(NOW(), INTERVAL 70 DAY)),
-- 上海店库存 (Dark Side)
(2, 2, 1, 'B20250901', 'Mint', 'Available', 40.00, DATE_SUB(NOW(), INTERVAL 60 DAY)),
-- 仓库库存 (Thriller)
(3, 3, 2, 'B20251101', 'New', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 3, 2, 'B20251101', 'New', 'Available', 25.00, DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- 已售出的库存 (用于报表测试)
(4, 1, 2, 'B20251101', 'VG+', 'Sold', 30.00, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- 8. 销售订单 (产生销售额)
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES 
(1, 1, DATE_SUB(NOW(), INTERVAL 5 DAY), 30.00, 'Completed', 'InStore');

INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES 
(1, 6, 30.00); -- 卖出了上面的第6个库存项

SET FOREIGN_KEY_CHECKS = 1;