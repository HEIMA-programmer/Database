-- seeds.sql - 完整测试数据
-- 密码统一为 'password123' 的 BCrypt Hash
-- 【修复版】确保数据一致性：订单、库存、积分正确关联
-- 【价格修复】使用公式计算售价：BaseUnitCost × Condition系数 × 利润率

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
DELETE FROM ManagerRequest;

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
ALTER TABLE ManagerRequest AUTO_INCREMENT = 1;

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
-- 【修复】Admin作为全局管理员，ShopID应为NULL，不属于任何特定店铺
INSERT INTO Employee (ShopID, Role, Name, Username, PasswordHash, HireDate) VALUES
(NULL, 'Admin', 'Super Admin', 'admin', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-01-01'),
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
-- Alice: 已完成订单 1,3,6 => 56+40+51.20=147.20积分，升级后VIP需要补充到1500
('Alice Fan', 'alice@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 2, 1500, '1995-05-20'),
-- Bob: 已完成订单 2,5,7 => 60.80+45.60+57.76=164.16积分，升级后Gold需要补充到6200
('Bob Collector', 'bob@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 3, 6200, '1988-12-15'),
-- Charlie: 已完成订单 4 => 72积分 + 补充到150
('Charlie New', 'charlie@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 1, 150, '2000-01-01'),
-- Diana: 已完成订单 8 => 47.04积分 + 补充到2300
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

-- Complete track listings for all 15 albums
INSERT INTO Track (ReleaseID, Title, TrackNumber, Duration) VALUES
-- 1. Abbey Road - The Beatles
(1, 'Come Together', 1, '4:20'),
(1, 'Something', 2, '3:03'),
(1, 'Maxwell''s Silver Hammer', 3, '3:27'),
(1, 'Oh! Darling', 4, '3:28'),
(1, 'Octopus''s Garden', 5, '2:51'),
(1, 'I Want You (She''s So Heavy)', 6, '7:47'),
(1, 'Here Comes the Sun', 7, '3:06'),
(1, 'Because', 8, '2:45'),
(1, 'You Never Give Me Your Money', 9, '4:02'),
(1, 'Sun King', 10, '2:26'),
(1, 'Mean Mr. Mustard', 11, '1:06'),
(1, 'Polythene Pam', 12, '1:12'),
(1, 'She Came In Through the Bathroom Window', 13, '1:57'),
(1, 'Golden Slumbers', 14, '1:31'),
(1, 'Carry That Weight', 15, '1:36'),
(1, 'The End', 16, '2:19'),
(1, 'Her Majesty', 17, '0:23'),
-- 2. The Dark Side of the Moon - Pink Floyd
(2, 'Speak to Me', 1, '1:30'),
(2, 'Breathe', 2, '2:43'),
(2, 'On the Run', 3, '3:30'),
(2, 'Time', 4, '7:06'),
(2, 'The Great Gig in the Sky', 5, '4:43'),
(2, 'Money', 6, '6:22'),
(2, 'Us and Them', 7, '7:49'),
(2, 'Any Colour You Like', 8, '3:26'),
(2, 'Brain Damage', 9, '3:48'),
(2, 'Eclipse', 10, '2:03'),
-- 3. Thriller - Michael Jackson
(3, 'Wanna Be Startin'' Somethin''', 1, '6:03'),
(3, 'Baby Be Mine', 2, '4:20'),
(3, 'The Girl Is Mine', 3, '3:42'),
(3, 'Thriller', 4, '5:57'),
(3, 'Beat It', 5, '4:18'),
(3, 'Billie Jean', 6, '4:54'),
(3, 'Human Nature', 7, '4:06'),
(3, 'P.Y.T. (Pretty Young Thing)', 8, '3:59'),
(3, 'The Lady in My Life', 9, '4:57'),
-- 4. Kind of Blue - Miles Davis
(4, 'So What', 1, '9:22'),
(4, 'Freddie Freeloader', 2, '9:46'),
(4, 'Blue in Green', 3, '5:37'),
(4, 'All Blues', 4, '11:33'),
(4, 'Flamenco Sketches', 5, '9:26'),
-- 5. Back in Black - AC/DC
(5, 'Hells Bells', 1, '5:12'),
(5, 'Shoot to Thrill', 2, '5:17'),
(5, 'What Do You Do for Money Honey', 3, '3:35'),
(5, 'Given the Dog a Bone', 4, '3:31'),
(5, 'Let Me Put My Love into You', 5, '4:15'),
(5, 'Back in Black', 6, '4:15'),
(5, 'You Shook Me All Night Long', 7, '3:30'),
(5, 'Have a Drink on Me', 8, '3:58'),
(5, 'Shake a Leg', 9, '4:05'),
(5, 'Rock and Roll Ain''t Noise Pollution', 10, '4:15'),
-- 6. Rumours - Fleetwood Mac
(6, 'Second Hand News', 1, '2:43'),
(6, 'Dreams', 2, '4:14'),
(6, 'Never Going Back Again', 3, '2:02'),
(6, 'Don''t Stop', 4, '3:11'),
(6, 'Go Your Own Way', 5, '3:38'),
(6, 'Songbird', 6, '3:20'),
(6, 'The Chain', 7, '4:28'),
(6, 'You Make Loving Fun', 8, '3:31'),
(6, 'I Don''t Want to Know', 9, '3:11'),
(6, 'Oh Daddy', 10, '3:54'),
(6, 'Gold Dust Woman', 11, '4:51'),
-- 7. Led Zeppelin IV - Led Zeppelin
(7, 'Black Dog', 1, '4:54'),
(7, 'Rock and Roll', 2, '3:40'),
(7, 'The Battle of Evermore', 3, '5:51'),
(7, 'Stairway to Heaven', 4, '8:02'),
(7, 'Misty Mountain Hop', 5, '4:38'),
(7, 'Four Sticks', 6, '4:44'),
(7, 'Going to California', 7, '3:31'),
(7, 'When the Levee Breaks', 8, '7:07'),
-- 8. The Wall - Pink Floyd
(8, 'In the Flesh?', 1, '3:16'),
(8, 'The Thin Ice', 2, '2:27'),
(8, 'Another Brick in the Wall Part 1', 3, '3:21'),
(8, 'The Happiest Days of Our Lives', 4, '1:46'),
(8, 'Another Brick in the Wall Part 2', 5, '3:59'),
(8, 'Mother', 6, '5:32'),
(8, 'Goodbye Blue Sky', 7, '2:45'),
(8, 'Young Lust', 8, '3:25'),
(8, 'One of My Turns', 9, '3:35'),
(8, 'Don''t Leave Me Now', 10, '4:08'),
(8, 'Another Brick in the Wall Part 3', 11, '1:18'),
(8, 'Comfortably Numb', 12, '6:23'),
-- 9. A Night at the Opera - Queen
(9, 'Death on Two Legs', 1, '3:43'),
(9, 'Lazing on a Sunday Afternoon', 2, '1:07'),
(9, 'I''m in Love with My Car', 3, '3:05'),
(9, 'You''re My Best Friend', 4, '2:50'),
(9, '''39', 5, '3:30'),
(9, 'Sweet Lady', 6, '4:01'),
(9, 'Seaside Rendezvous', 7, '2:13'),
(9, 'The Prophet''s Song', 8, '8:21'),
(9, 'Love of My Life', 9, '3:38'),
(9, 'Good Company', 10, '3:23'),
(9, 'Bohemian Rhapsody', 11, '5:55'),
(9, 'God Save the Queen', 12, '1:11'),
-- 10. Hotel California - Eagles
(10, 'Hotel California', 1, '6:30'),
(10, 'New Kid in Town', 2, '5:04'),
(10, 'Life in the Fast Lane', 3, '4:46'),
(10, 'Wasted Time', 4, '4:55'),
(10, 'Wasted Time (Reprise)', 5, '1:22'),
(10, 'Victim of Love', 6, '4:11'),
(10, 'Pretty Maids All in a Row', 7, '4:05'),
(10, 'Try and Love Again', 8, '5:10'),
(10, 'The Last Resort', 9, '7:28'),
-- 11. Born to Run - Bruce Springsteen
(11, 'Thunder Road', 1, '4:50'),
(11, 'Tenth Avenue Freeze-Out', 2, '3:11'),
(11, 'Night', 3, '3:00'),
(11, 'Backstreets', 4, '6:30'),
(11, 'Born to Run', 5, '4:30'),
(11, 'She''s the One', 6, '4:30'),
(11, 'Meeting Across the River', 7, '3:18'),
(11, 'Jungleland', 8, '9:35'),
-- 12. Blue - Joni Mitchell
(12, 'All I Want', 1, '3:32'),
(12, 'My Old Man', 2, '3:33'),
(12, 'Little Green', 3, '3:27'),
(12, 'Carey', 4, '3:00'),
(12, 'Blue', 5, '3:01'),
(12, 'California', 6, '3:50'),
(12, 'This Flight Tonight', 7, '2:50'),
(12, 'River', 8, '4:00'),
(12, 'A Case of You', 9, '4:20'),
(12, 'The Last Time I Saw Richard', 10, '4:14'),
-- 13. What's Going On - Marvin Gaye
(13, 'What''s Going On', 1, '3:53'),
(13, 'What''s Happening Brother', 2, '2:43'),
(13, 'Flyin'' High (In the Friendly Sky)', 3, '3:50'),
(13, 'Save the Children', 4, '4:03'),
(13, 'God Is Love', 5, '1:42'),
(13, 'Mercy Mercy Me (The Ecology)', 6, '3:16'),
(13, 'Right On', 7, '7:27'),
(13, 'Wholy Holy', 8, '3:09'),
(13, 'Inner City Blues (Make Me Wanna Holler)', 9, '5:27'),
-- 14. Purple Rain - Prince
(14, 'Let''s Go Crazy', 1, '4:39'),
(14, 'Take Me with U', 2, '3:54'),
(14, 'The Beautiful Ones', 3, '5:14'),
(14, 'Computer Blue', 4, '3:59'),
(14, 'Darling Nikki', 5, '4:14'),
(14, 'When Doves Cry', 6, '5:54'),
(14, 'I Would Die 4 U', 7, '2:49'),
(14, 'Baby I''m a Star', 8, '4:20'),
(14, 'Purple Rain', 9, '8:41'),
-- 15. Nevermind - Nirvana
(15, 'Smells Like Teen Spirit', 1, '5:01'),
(15, 'In Bloom', 2, '4:14'),
(15, 'Come as You Are', 3, '3:38'),
(15, 'Breed', 4, '3:03'),
(15, 'Lithium', 5, '4:17'),
(15, 'Polly', 6, '2:57'),
(15, 'Territorial Pissings', 7, '2:22'),
(15, 'Drain You', 8, '3:43'),
(15, 'Lounge Act', 9, '2:36'),
(15, 'Stay Away', 10, '3:32'),
(15, 'On a Plain', 11, '3:14'),
(15, 'Something in the Way', 12, '3:52');

-- ==========================================
-- 6. 供应商订单
-- ==========================================
-- 【重构】每个不同Condition必须是独立的采购订单（符合UI业务逻辑）
-- 采购成本 = BaseUnitCost × Condition系数
-- Condition系数：New=1.00, Mint=0.95, NM=0.85, VG+=0.70, VG=0.55

-- 长沙店采购订单 (DestinationShopID=1)
INSERT INTO SupplierOrder (SupplierID, CreatedByEmployeeID, DestinationShopID, OrderDate, Status, ReceivedDate) VALUES
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),  -- ID 1: Abbey Road New
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),  -- ID 2: Abbey Road Mint
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),  -- ID 3: Abbey Road VG+
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),  -- ID 4: Dark Side New
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),  -- ID 5: Dark Side Mint
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),  -- ID 6: Dark Side VG+
-- 上海店采购订单 (DestinationShopID=2)
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 7: Thriller New
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 8: Thriller Mint
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 9: Thriller VG
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 10: Kind of Blue New
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 11: Kind of Blue Mint
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 12: Kind of Blue NM
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 13: Kind of Blue VG
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 14: Back in Black New
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 15: Back in Black Mint
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 16: Back in Black NM
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- ID 17: Back in Black VG+
-- 仓库采购订单 (DestinationShopID=3)
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 18: Rumours New
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 19: Rumours Mint
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 20: Rumours VG
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 21: Led Zeppelin IV New
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 22: Led Zeppelin IV Mint
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 23: Led Zeppelin IV NM
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 24: Led Zeppelin IV VG
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 25: The Wall New
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 26: The Wall Mint
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 27: The Wall NM
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- ID 28: The Wall VG+
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 29: Opera New
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 30: Opera Mint
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 31: Opera NM
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 32: Opera VG
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 33: Hotel California New
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 34: Hotel California Mint
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 35: Hotel California NM
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 36: Hotel California VG+
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 37: Born to Run New
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 38: Born to Run Mint
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- ID 39: Born to Run NM
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY));   -- ID 40: Born to Run VG+

-- 采购订单明细（每条记录对应一个专辑+一个Condition）
-- UnitCost = BaseUnitCost × Condition系数
INSERT INTO SupplierOrderLine (SupplierOrderID, ReleaseID, Quantity, UnitCost, ConditionGrade) VALUES
-- 长沙店: Abbey Road (Base=35)
(1, 1, 3, 35.00, 'New'),    -- 35×1.00=35, 数量3(2张Available+1张Sold)
(2, 1, 1, 33.25, 'Mint'),   -- 35×0.95=33.25
(3, 1, 1, 24.50, 'VG+'),    -- 35×0.70=24.50
-- 长沙店: Dark Side (Base=40)
(4, 2, 2, 40.00, 'New'),    -- 40×1.00=40
(5, 2, 2, 38.00, 'Mint'),   -- 40×0.95=38, 数量2(1张Available+1张Sold)
(6, 2, 1, 28.00, 'VG+'),    -- 40×0.70=28
-- 上海店: Thriller (Base=25)
(7, 3, 3, 25.00, 'New'),    -- 25×1.00=25, 数量3(2张Available+1张Sold)
(8, 3, 1, 23.75, 'Mint'),   -- 25×0.95=23.75
(9, 3, 1, 13.75, 'VG'),     -- 25×0.55=13.75
-- 上海店: Kind of Blue (Base=45)
(10, 4, 2, 45.00, 'New'),   -- 45×1.00=45, 数量2(1张Available+1张Sold)
(11, 4, 1, 42.75, 'Mint'),  -- 45×0.95=42.75
(12, 4, 1, 38.25, 'NM'),    -- 45×0.85=38.25
(13, 4, 1, 24.75, 'VG'),    -- 45×0.55=24.75
-- 上海店: Back in Black (Base=30)
(14, 5, 1, 30.00, 'New'),   -- 30×1.00=30
(15, 5, 2, 28.50, 'Mint'),  -- 30×0.95=28.5, 数量2(1张Available+1张Sold)
(16, 5, 1, 25.50, 'NM'),    -- 30×0.85=25.5
(17, 5, 1, 21.00, 'VG+'),   -- 30×0.70=21
-- 仓库: Rumours (Base=32)
(18, 6, 3, 32.00, 'New'),   -- 32×1.00=32, 数量3(2张Available+1张Sold)
(19, 6, 1, 30.40, 'Mint'),  -- 32×0.95=30.4
(20, 6, 1, 17.60, 'VG'),    -- 32×0.55=17.6
-- 仓库: Led Zeppelin IV (Base=38)
(21, 7, 2, 38.00, 'New'),   -- 38×1.00=38, 数量2(1张Available+1张Sold)
(22, 7, 1, 36.10, 'Mint'),  -- 38×0.95=36.1
(23, 7, 1, 32.30, 'NM'),    -- 38×0.85=32.3
(24, 7, 1, 20.90, 'VG'),    -- 38×0.55=20.9
-- 仓库: The Wall (Base=42)
(25, 8, 2, 42.00, 'New'),   -- 42×1.00=42, 数量2(1张Available+1张Sold)
(26, 8, 1, 39.90, 'Mint'),  -- 42×0.95=39.9
(27, 8, 1, 35.70, 'NM'),    -- 42×0.85=35.7
(28, 8, 1, 29.40, 'VG+'),   -- 42×0.70=29.4
-- 仓库: Opera (Base=36)
(29, 9, 2, 36.00, 'New'),   -- 36×1.00=36
(30, 9, 1, 34.20, 'Mint'),  -- 36×0.95=34.2
(31, 9, 1, 30.60, 'NM'),    -- 36×0.85=30.6
(32, 9, 1, 19.80, 'VG'),    -- 36×0.55=19.8
-- 仓库: Hotel California (Base=28)
(33, 10, 2, 28.00, 'New'),  -- 28×1.00=28
(34, 10, 1, 26.60, 'Mint'), -- 28×0.95=26.6
(35, 10, 1, 23.80, 'NM'),   -- 28×0.85=23.8
(36, 10, 1, 19.60, 'VG+'),  -- 28×0.70=19.6
-- 仓库: Born to Run (Base=26)
(37, 11, 2, 26.00, 'New'),  -- 26×1.00=26
(38, 11, 1, 24.70, 'Mint'), -- 26×0.95=24.7
(39, 11, 1, 22.10, 'NM'),   -- 26×0.85=22.1
(40, 11, 1, 18.20, 'VG+');  -- 26×0.70=18.2

-- ==========================================
-- 7. 回购订单
-- ==========================================
-- 【修复】只有门店可以进行回购，仓库不能回购
-- 长沙店(ShopID=1)员工staff_cs(EmployeeID=3)处理回购
INSERT INTO BuybackOrder (CustomerID, ProcessedByEmployeeID, ShopID, BuybackDate, Status, TotalPayment) VALUES
(2, 3, 1, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Completed', 36.00);

-- 【修复】BuybackOrderID 都改为 1，因为只有一个回购订单
INSERT INTO BuybackOrderLine (BuybackOrderID, ReleaseID, Quantity, UnitPrice, ConditionGrade) VALUES
(1, 9, 3, 12.00, 'VG+');

-- ==========================================
-- 8. 库存项 - 【修复】使用公式计算售价
-- ==========================================
-- 【定价公式】售价 = BaseUnitCost × Condition系数 × 利润率
-- Condition系数：New=1.00, Mint=0.95, NM=0.85, VG+=0.70, VG=0.55
-- 利润率：成本≤20→×1.50, 21-50→×1.60, 51-100→×1.70, >100→×1.80
--
-- 各专辑售价速查表：
-- Album 1 (Abbey Road, Base=35): New=56.00, Mint=53.20, NM=47.60, VG+=39.20, VG=28.88
-- Album 2 (Dark Side, Base=40): New=64.00, Mint=60.80, NM=54.40, VG+=44.80, VG=35.20
-- Album 3 (Thriller, Base=25): New=40.00, Mint=38.00, NM=34.00, VG+=26.25, VG=20.63
-- Album 4 (Kind of Blue, Base=45): New=72.00, Mint=68.40, NM=61.20, VG+=50.40, VG=39.60
-- Album 5 (Back in Black, Base=30): New=48.00, Mint=45.60, NM=40.80, VG+=33.60, VG=24.75
-- Album 6 (Rumours, Base=32): New=51.20, Mint=48.64, NM=43.52, VG+=35.84, VG=26.40
-- Album 7 (Led Zeppelin IV, Base=38): New=60.80, Mint=57.76, NM=51.68, VG+=42.56, VG=33.44
-- Album 8 (The Wall, Base=42): New=67.20, Mint=63.84, NM=57.12, VG+=47.04, VG=36.96
-- Album 9 (Opera, Base=36): New=57.60, Mint=54.72, NM=48.96, VG+=40.32, VG=29.70
-- Album 10 (Hotel California, Base=28): New=44.80, Mint=42.56, NM=38.08, VG+=29.40, VG=23.10
-- Album 11 (Born to Run, Base=26): New=41.60, Mint=39.52, NM=35.36, VG+=27.30, VG=21.45

-- 长沙店库存 (ShopID=1) - ID 1-10
-- 【重构】SourceOrderID必须指向包含对应ConditionGrade的采购订单
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 56.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),   -- Order 1: Abbey Road New
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Available', 56.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),   -- Order 1: Abbey Road New
(1, 1, 'Supplier', 2, 'B20251001-CS', 'Mint', 'Available', 53.20, DATE_SUB(NOW(), INTERVAL 69 DAY)),  -- Order 2: Abbey Road Mint
(2, 1, 'Supplier', 4, 'B20251001-CS', 'New', 'Available', 64.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),   -- Order 4: Dark Side New
(2, 1, 'Supplier', 4, 'B20251001-CS', 'New', 'Available', 64.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),   -- Order 4: Dark Side New
(2, 1, 'Supplier', 5, 'B20251001-CS', 'Mint', 'Available', 60.80, DATE_SUB(NOW(), INTERVAL 69 DAY)),  -- Order 5: Dark Side Mint
(2, 1, 'Supplier', 6, 'B20251001-CS', 'VG+', 'Available', 44.80, DATE_SUB(NOW(), INTERVAL 69 DAY)),   -- Order 6: Dark Side VG+
(1, 1, 'Supplier', 1, 'B20251001-CS', 'New', 'Sold', 56.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),        -- Order 1: Abbey Road New (Sold)
(2, 1, 'Supplier', 5, 'B20251001-CS', 'Mint', 'Sold', 60.80, DATE_SUB(NOW(), INTERVAL 69 DAY)),       -- Order 5: Dark Side Mint (Sold)
(1, 1, 'Supplier', 3, 'B20251001-CS', 'VG+', 'Available', 39.20, DATE_SUB(NOW(), INTERVAL 69 DAY));   -- Order 3: Abbey Road VG+

-- 上海店库存 (ShopID=2) - ID 11-25
-- 【重构】SourceOrderID必须指向包含对应ConditionGrade的采购订单
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(3, 2, 'Supplier', 7, 'B20251115-SH', 'New', 'Available', 40.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),   -- Order 7: Thriller New
(3, 2, 'Supplier', 7, 'B20251115-SH', 'New', 'Available', 40.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),   -- Order 7: Thriller New
(3, 2, 'Supplier', 7, 'B20251115-SH', 'New', 'Sold', 40.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),        -- Order 7: Thriller New (Sold)
(4, 2, 'Supplier', 10, 'B20251115-SH', 'New', 'Available', 72.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- Order 10: Kind of Blue New
(4, 2, 'Supplier', 10, 'B20251115-SH', 'New', 'Sold', 72.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),       -- Order 10: Kind of Blue New (Sold)
(4, 2, 'Supplier', 12, 'B20251115-SH', 'NM', 'Available', 61.20, DATE_SUB(NOW(), INTERVAL 49 DAY)),   -- Order 12: Kind of Blue NM
(5, 2, 'Supplier', 15, 'B20251115-SH', 'Mint', 'Available', 45.60, DATE_SUB(NOW(), INTERVAL 49 DAY)), -- Order 15: Back in Black Mint
(5, 2, 'Supplier', 15, 'B20251115-SH', 'Mint', 'Sold', 45.60, DATE_SUB(NOW(), INTERVAL 49 DAY)),      -- Order 15: Back in Black Mint (Sold)
(5, 2, 'Supplier', 17, 'B20251115-SH', 'VG+', 'Available', 33.60, DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- Order 17: Back in Black VG+
(3, 2, 'Supplier', 9, 'B20251115-SH', 'VG', 'Available', 20.63, DATE_SUB(NOW(), INTERVAL 49 DAY)),    -- Order 9: Thriller VG
(4, 2, 'Supplier', 13, 'B20251115-SH', 'VG', 'Available', 39.60, DATE_SUB(NOW(), INTERVAL 49 DAY)),   -- Order 13: Kind of Blue VG
(5, 2, 'Supplier', 14, 'B20251115-SH', 'New', 'Available', 48.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- Order 14: Back in Black New
(3, 2, 'Supplier', 8, 'B20251115-SH', 'Mint', 'Available', 38.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),  -- Order 8: Thriller Mint
(4, 2, 'Supplier', 11, 'B20251115-SH', 'Mint', 'Available', 68.40, DATE_SUB(NOW(), INTERVAL 49 DAY)), -- Order 11: Kind of Blue Mint
(5, 2, 'Supplier', 16, 'B20251115-SH', 'NM', 'Available', 40.80, DATE_SUB(NOW(), INTERVAL 49 DAY));   -- Order 16: Back in Black NM

-- 仓库库存 (ShopID=3) - ID 26-55
-- 【重构】SourceOrderID必须指向包含对应ConditionGrade的采购订单
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(6, 3, 'Supplier', 18, 'B20251210-WH', 'New', 'Available', 51.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- Order 18: Rumours New
(6, 3, 'Supplier', 18, 'B20251210-WH', 'New', 'Available', 51.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- Order 18: Rumours New
(6, 3, 'Supplier', 18, 'B20251210-WH', 'New', 'Sold', 51.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),       -- Order 18: Rumours New (Sold)
(7, 3, 'Supplier', 21, 'B20251210-WH', 'New', 'Available', 60.80, DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- Order 21: Led Zeppelin IV New
(7, 3, 'Supplier', 21, 'B20251210-WH', 'New', 'Available', 60.80, DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- Order 21: Led Zeppelin IV New
(7, 3, 'Supplier', 22, 'B20251210-WH', 'Mint', 'Sold', 57.76, DATE_SUB(NOW(), INTERVAL 29 DAY)),      -- Order 22: Led Zeppelin IV Mint (Sold)
(8, 3, 'Supplier', 25, 'B20251210-WH', 'New', 'Available', 67.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- Order 25: The Wall New
(8, 3, 'Supplier', 25, 'B20251210-WH', 'New', 'Available', 67.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),  -- Order 25: The Wall New
(8, 3, 'Supplier', 28, 'B20251210-WH', 'VG+', 'Sold', 47.04, DATE_SUB(NOW(), INTERVAL 29 DAY)),       -- Order 28: The Wall VG+ (Sold)
(6, 3, 'Supplier', 20, 'B20251210-WH', 'VG', 'Available', 26.40, DATE_SUB(NOW(), INTERVAL 29 DAY)),   -- Order 20: Rumours VG
(7, 3, 'Supplier', 24, 'B20251210-WH', 'VG', 'Available', 33.44, DATE_SUB(NOW(), INTERVAL 29 DAY)),   -- Order 24: Led Zeppelin IV VG
(8, 3, 'Supplier', 27, 'B20251210-WH', 'NM', 'Available', 57.12, DATE_SUB(NOW(), INTERVAL 29 DAY)),   -- Order 27: The Wall NM
(6, 3, 'Supplier', 19, 'B20251210-WH', 'Mint', 'Available', 48.64, DATE_SUB(NOW(), INTERVAL 29 DAY)), -- Order 19: Rumours Mint
(7, 3, 'Supplier', 23, 'B20251210-WH', 'NM', 'Available', 51.68, DATE_SUB(NOW(), INTERVAL 29 DAY)),   -- Order 23: Led Zeppelin IV NM
(8, 3, 'Supplier', 26, 'B20251210-WH', 'Mint', 'Available', 63.84, DATE_SUB(NOW(), INTERVAL 29 DAY)), -- Order 26: The Wall Mint
-- 最新批次 (Orders 29-40)
(9, 3, 'Supplier', 29, 'B20251218-WH', 'New', 'Available', 57.60, DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- Order 29: Opera New
(9, 3, 'Supplier', 29, 'B20251218-WH', 'New', 'Available', 57.60, DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- Order 29: Opera New
(9, 3, 'Supplier', 30, 'B20251218-WH', 'Mint', 'Available', 54.72, DATE_SUB(NOW(), INTERVAL 9 DAY)),  -- Order 30: Opera Mint
(10, 3, 'Supplier', 33, 'B20251218-WH', 'New', 'Available', 44.80, DATE_SUB(NOW(), INTERVAL 9 DAY)),  -- Order 33: Hotel California New
(10, 3, 'Supplier', 33, 'B20251218-WH', 'New', 'Available', 44.80, DATE_SUB(NOW(), INTERVAL 9 DAY)),  -- Order 33: Hotel California New
(10, 3, 'Supplier', 36, 'B20251218-WH', 'VG+', 'Available', 29.40, DATE_SUB(NOW(), INTERVAL 9 DAY)),  -- Order 36: Hotel California VG+
(11, 3, 'Supplier', 37, 'B20251218-WH', 'New', 'Available', 41.60, DATE_SUB(NOW(), INTERVAL 9 DAY)),  -- Order 37: Born to Run New
(11, 3, 'Supplier', 37, 'B20251218-WH', 'New', 'Available', 41.60, DATE_SUB(NOW(), INTERVAL 9 DAY)),  -- Order 37: Born to Run New
(11, 3, 'Supplier', 39, 'B20251218-WH', 'NM', 'Available', 35.36, DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- Order 39: Born to Run NM
(9, 3, 'Supplier', 32, 'B20251218-WH', 'VG', 'Available', 29.70, DATE_SUB(NOW(), INTERVAL 9 DAY)),    -- Order 32: Opera VG
(10, 3, 'Supplier', 34, 'B20251218-WH', 'Mint', 'Available', 42.56, DATE_SUB(NOW(), INTERVAL 9 DAY)), -- Order 34: Hotel California Mint
(11, 3, 'Supplier', 40, 'B20251218-WH', 'VG+', 'Available', 27.30, DATE_SUB(NOW(), INTERVAL 9 DAY)),  -- Order 40: Born to Run VG+
(9, 3, 'Supplier', 31, 'B20251218-WH', 'NM', 'Available', 48.96, DATE_SUB(NOW(), INTERVAL 9 DAY)),    -- Order 31: Opera NM
(10, 3, 'Supplier', 35, 'B20251218-WH', 'NM', 'Available', 38.08, DATE_SUB(NOW(), INTERVAL 9 DAY)),   -- Order 35: Hotel California NM
(11, 3, 'Supplier', 38, 'B20251218-WH', 'Mint', 'Available', 39.52, DATE_SUB(NOW(), INTERVAL 9 DAY)); -- Order 38: Born to Run Mint

-- 回购二手唱片 - ID 56-58
-- 【修复】回购入库到长沙店(ShopID=1)而不是仓库
-- Album 9 VG+ = 40.32 (回购数量3张)
INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(9, 1, 'Buyback', 1, 'BUY-20251215', 'VG+', 'Available', 40.32, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9, 1, 'Buyback', 1, 'BUY-20251215', 'VG+', 'Available', 40.32, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9, 1, 'Buyback', 1, 'BUY-20251215', 'VG+', 'Available', 40.32, DATE_SUB(NOW(), INTERVAL 15 DAY));

-- ==========================================
-- 9. 销售订单 - 【修复】使用正确的算法价格
-- ==========================================

-- 已完成的门店订单 (用于报表)
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, ProcessedByEmployeeID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES
(1, 1, 3, DATE_SUB(NOW(), INTERVAL 60 DAY), 56.00, 'Completed', 'InStore'),  -- 订单1: Alice 在长沙店购买 Abbey Road New
(2, 1, 3, DATE_SUB(NOW(), INTERVAL 55 DAY), 60.80, 'Completed', 'InStore'),  -- 订单2: Bob 在长沙店购买 Dark Side Mint
(1, 2, 5, DATE_SUB(NOW(), INTERVAL 45 DAY), 40.00, 'Completed', 'InStore'),  -- 订单3: Alice 在上海店购买 Thriller New
(3, 2, 5, DATE_SUB(NOW(), INTERVAL 40 DAY), 72.00, 'Completed', 'InStore'),  -- 订单4: Charlie 在上海店购买 Kind of Blue New
(2, 2, 5, DATE_SUB(NOW(), INTERVAL 35 DAY), 45.60, 'Completed', 'InStore');  -- 订单5: Bob 在上海店购买 Back in Black Mint

-- 已完成的线上订单 (含运费 ¥15)
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType, FulfillmentType, ShippingCost, ShippingAddress) VALUES
(1, 3, DATE_SUB(NOW(), INTERVAL 20 DAY), 66.20, 'Completed', 'Online', 'Shipping', 15.00, '123 Main Street, Changsha'),  -- 订单6: Alice 线上购买 Rumours New
(2, 3, DATE_SUB(NOW(), INTERVAL 15 DAY), 72.76, 'Completed', 'Online', 'Shipping', 15.00, '456 Oak Avenue, Shanghai'),   -- 订单7: Bob 线上购买 Led Zeppelin IV Mint
(4, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 62.04, 'Completed', 'Online', 'Shipping', 15.00, '789 Elm Road, Beijing');       -- 订单8: Diana 线上购买 The Wall VG+

-- 已发货待确认收货的订单 (测试 shipped 通知)
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType, FulfillmentType, ShippingCost, ShippingAddress) VALUES
(1, 3, DATE_SUB(NOW(), INTERVAL 3 DAY), 72.60, 'Shipped', 'Online', 'Shipping', 15.00, '123 Main Street, Changsha'),  -- 订单9: Alice Opera New + 运费
(2, 3, DATE_SUB(NOW(), INTERVAL 2 DAY), 63.64, 'Shipped', 'Online', 'Shipping', 15.00, '456 Oak Avenue, Shanghai');   -- 订单10: Bob Rumours Mint + 运费

-- ==========================================
-- 10. 订单明细 - 【修复】关联正确的库存ID和算法价格
-- ==========================================
INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES
-- 门店订单
(1, 8, 56.00),   -- 订单1: Abbey Road New (已售)
(2, 9, 60.80),   -- 订单2: Dark Side Mint (已售)
(3, 13, 40.00),  -- 订单3: Thriller New (已售)
(4, 15, 72.00),  -- 订单4: Kind of Blue New (已售)
(5, 18, 45.60),  -- 订单5: Back in Black Mint (已售)
-- 线上已完成订单
(6, 28, 51.20),  -- 订单6: Rumours New (已售)
(7, 31, 57.76),  -- 订单7: Led Zeppelin IV Mint (已售)
(8, 34, 47.04),  -- 订单8: The Wall VG+ (已售)
-- 已发货待确认订单
(9, 41, 57.60),  -- 订单9: Opera New (已发货)
(10, 38, 48.64); -- 订单10: Rumours Mint (已发货)

-- 更新已售库存的售出日期
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 60 DAY) WHERE StockItemID = 8;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 55 DAY) WHERE StockItemID = 9;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 45 DAY) WHERE StockItemID = 13;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 40 DAY) WHERE StockItemID = 15;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 35 DAY) WHERE StockItemID = 18;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 20 DAY) WHERE StockItemID = 28;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 15 DAY) WHERE StockItemID = 31;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE StockItemID = 34;

-- 更新已发货订单的库存状态
UPDATE StockItem SET Status = 'Sold', DateSold = DATE_SUB(NOW(), INTERVAL 3 DAY) WHERE StockItemID = 41;
UPDATE StockItem SET Status = 'Sold', DateSold = DATE_SUB(NOW(), INTERVAL 2 DAY) WHERE StockItemID = 38;

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
-- 6. 【价格一致性】同一Release+同一Condition的StockItem.UnitPrice必须一致

-- ==========================================
-- Price 和 Cost 属性说明
-- ==========================================
-- 【成本类 Cost】
-- ReleaseAlbum.BaseUnitCost    = 基础采购成本（每张专辑的基准成本）
-- SupplierOrderLine.UnitCost   = 实际采购单价（= BaseUnitCost × Condition系数）
-- SupplierOrder.TotalCost      = 采购订单总成本（SUM(Quantity × UnitCost)）
-- CustomerOrder.ShippingCost   = 运费（线上送货¥15，自提免费）
--
-- 【价格类 Price】
-- SupplierOrderLine.SalePrice  = 预设售价（采购时可选设定的销售价）
-- BuybackOrderLine.UnitPrice   = 回购支付单价（支付给客户的金额）
-- StockItem.UnitPrice          = 【核心】库存销售价（客户购买时的实际价格）
-- OrderLine.PriceAtSale        = 成交价快照（订单创建时的价格副本，不受后续调价影响）
-- ManagerRequest.CurrentPrice  = 调价申请当前价格
-- ManagerRequest.RequestedPrice= 调价申请目标价格
--
-- 【汇总金额】
-- BuybackOrder.TotalPayment    = 回购总支付（SUM(Quantity × BuybackOrderLine.UnitPrice)）
-- CustomerOrder.TotalAmount    = 订单总金额（SUM(PriceAtSale) + ShippingCost）
--
-- 【Condition系数（用于计算采购成本）】
-- New=1.00, Mint=0.95, NM=0.85, VG+=0.70, VG=0.55
--
-- 【利润率（用于计算建议售价）】
-- 成本≤20: ×1.50 (50%利润)
-- 成本21-50: ×1.60 (60%利润)
-- 成本51-100: ×1.70 (70%利润)
-- 成本>100: ×1.80 (80%利润)
--
-- 【定价公式】
-- 售价 = BaseUnitCost × Condition系数 × 利润率
-- 例如: Abbey Road (Base=35) + New (×1.00) + 成本35在21-50区间 (×1.60) = 35 × 1.00 × 1.60 = 56.00
--
-- 【定价流程】
-- 1. 采购入库：UnitCost → 根据利润率计算 → StockItem.UnitPrice
-- 2. 回购入库：检查是否有同Release+Condition现有价格，有则使用现有价格
-- 3. 销售时：StockItem.UnitPrice → 记录到 OrderLine.PriceAtSale
-- 4. 调价：Manager申请 → Admin批准 → 更新StockItem.UnitPrice
