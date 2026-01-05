SET FOREIGN_KEY_CHECKS = 0;

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

INSERT INTO Shop (Name, Address, Type) VALUES
('Changsha Flagship Store', '123 Vinyl St, Changsha', 'Retail'),
('Shanghai Branch', '456 Groove Ave, Shanghai', 'Retail'),
('Online Warehouse', 'No. 8 Logistics Park, Changsha', 'Warehouse');

INSERT INTO MembershipTier (TierName, MinPoints, DiscountRate) VALUES
('Standard', 0, 0.00),
('VIP', 1000, 0.05),
('Gold', 5000, 0.10);

INSERT INTO Employee (ShopID, Role, Name, Username, PasswordHash, HireDate) VALUES
(NULL, 'Admin', 'Super Admin', 'admin', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-01-01'),
(1, 'Manager', 'Changsha Manager', 'manager_cs', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-03-15'),
(1, 'Staff', 'Changsha Staff', 'staff_cs', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-06-01'),
(2, 'Manager', 'Shanghai Manager', 'manager_sh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-04-01'),
(2, 'Staff', 'Shanghai Staff', 'staff_sh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-07-15'),
(3, 'Manager', 'Warehouse Manager', 'manager_wh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-05-01'),
(3, 'Staff', 'Warehouse Packer', 'staff_wh', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', '2023-08-01');

INSERT INTO Customer (Name, Email, PasswordHash, TierID, Points, Birthday) VALUES

('Alice Fan', 'alice@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 2, 1500, '1995-05-20'),

('Bob Collector', 'bob@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 3, 6200, '1988-12-15'),

('Charlie New', 'charlie@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 1, 150, '2000-01-01'),

('Diana Vinyl', 'diana@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 2, 2300, '1992-12-23'),

('Edward Rock', 'edward@test.com', '$2y$10$dfU5tM5IPYgDKUliWz6ygOmsEi52gBa0uVD2FZJIhh6iSeE05Ztq2', 1, 450, '1985-07-04');

INSERT INTO Supplier (Name, Email) VALUES
('Sony Music CN', 'sales@sonymusic.cn'),
('Universal Records', 'contact@universal.com'),
('Warner Music Asia', 'asia@warnermusic.com'),
('EMI Classics', 'classics@emi.com');

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

INSERT INTO Track (ReleaseID, Title, TrackNumber, Duration) VALUES

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

(3, 'Wanna Be Startin'' Somethin''', 1, '6:03'),
(3, 'Baby Be Mine', 2, '4:20'),
(3, 'The Girl Is Mine', 3, '3:42'),
(3, 'Thriller', 4, '5:57'),
(3, 'Beat It', 5, '4:18'),
(3, 'Billie Jean', 6, '4:54'),
(3, 'Human Nature', 7, '4:06'),
(3, 'P.Y.T. (Pretty Young Thing)', 8, '3:59'),
(3, 'The Lady in My Life', 9, '4:57'),

(4, 'So What', 1, '9:22'),
(4, 'Freddie Freeloader', 2, '9:46'),
(4, 'Blue in Green', 3, '5:37'),
(4, 'All Blues', 4, '11:33'),
(4, 'Flamenco Sketches', 5, '9:26'),

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

(7, 'Black Dog', 1, '4:54'),
(7, 'Rock and Roll', 2, '3:40'),
(7, 'The Battle of Evermore', 3, '5:51'),
(7, 'Stairway to Heaven', 4, '8:02'),
(7, 'Misty Mountain Hop', 5, '4:38'),
(7, 'Four Sticks', 6, '4:44'),
(7, 'Going to California', 7, '3:31'),
(7, 'When the Levee Breaks', 8, '7:07'),

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

(10, 'Hotel California', 1, '6:30'),
(10, 'New Kid in Town', 2, '5:04'),
(10, 'Life in the Fast Lane', 3, '4:46'),
(10, 'Wasted Time', 4, '4:55'),
(10, 'Wasted Time (Reprise)', 5, '1:22'),
(10, 'Victim of Love', 6, '4:11'),
(10, 'Pretty Maids All in a Row', 7, '4:05'),
(10, 'Try and Love Again', 8, '5:10'),
(10, 'The Last Resort', 9, '7:28'),

(11, 'Thunder Road', 1, '4:50'),
(11, 'Tenth Avenue Freeze-Out', 2, '3:11'),
(11, 'Night', 3, '3:00'),
(11, 'Backstreets', 4, '6:30'),
(11, 'Born to Run', 5, '4:30'),
(11, 'She''s the One', 6, '4:30'),
(11, 'Meeting Across the River', 7, '3:18'),
(11, 'Jungleland', 8, '9:35'),

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

(13, 'What''s Going On', 1, '3:53'),
(13, 'What''s Happening Brother', 2, '2:43'),
(13, 'Flyin'' High (In the Friendly Sky)', 3, '3:50'),
(13, 'Save the Children', 4, '4:03'),
(13, 'God Is Love', 5, '1:42'),
(13, 'Mercy Mercy Me (The Ecology)', 6, '3:16'),
(13, 'Right On', 7, '7:27'),
(13, 'Wholy Holy', 8, '3:09'),
(13, 'Inner City Blues (Make Me Wanna Holler)', 9, '5:27'),

(14, 'Let''s Go Crazy', 1, '4:39'),
(14, 'Take Me with U', 2, '3:54'),
(14, 'The Beautiful Ones', 3, '5:14'),
(14, 'Computer Blue', 4, '3:59'),
(14, 'Darling Nikki', 5, '4:14'),
(14, 'When Doves Cry', 6, '5:54'),
(14, 'I Would Die 4 U', 7, '2:49'),
(14, 'Baby I''m a Star', 8, '4:20'),
(14, 'Purple Rain', 9, '8:41'),

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

INSERT INTO SupplierOrder (SupplierID, CreatedByEmployeeID, DestinationShopID, OrderDate, Status, ReceivedDate) VALUES
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 69 DAY)),

(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),
(2, 1, 2, DATE_SUB(NOW(), INTERVAL 50 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 49 DAY)),

(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(3, 1, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(4, 2, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Received', DATE_SUB(NOW(), INTERVAL 9 DAY));

INSERT INTO SupplierOrderLine (SupplierOrderID, ReleaseID, Quantity, UnitCost, ConditionGrade) VALUES

(1, 1, 3, 35.00, 'New'),
(2, 1, 1, 33.25, 'Mint'),
(3, 1, 1, 24.50, 'VG+'),

(4, 2, 2, 40.00, 'New'),
(5, 2, 2, 38.00, 'Mint'),
(6, 2, 1, 28.00, 'VG+'),

(7, 3, 3, 25.00, 'New'),
(8, 3, 1, 23.75, 'Mint'),
(9, 3, 1, 13.75, 'VG'),

(10, 4, 2, 45.00, 'New'),
(11, 4, 1, 42.75, 'Mint'),
(12, 4, 1, 38.25, 'NM'),
(13, 4, 1, 24.75, 'VG'),

(14, 5, 1, 30.00, 'New'),
(15, 5, 2, 28.50, 'Mint'),
(16, 5, 1, 25.50, 'NM'),
(17, 5, 1, 21.00, 'VG+'),

(18, 6, 3, 32.00, 'New'),
(19, 6, 1, 30.40, 'Mint'),
(20, 6, 1, 17.60, 'VG'),

(21, 7, 2, 38.00, 'New'),
(22, 7, 1, 36.10, 'Mint'),
(23, 7, 1, 32.30, 'NM'),
(24, 7, 1, 20.90, 'VG'),

(25, 8, 2, 42.00, 'New'),
(26, 8, 1, 39.90, 'Mint'),
(27, 8, 1, 35.70, 'NM'),
(28, 8, 1, 29.40, 'VG+'),

(29, 9, 2, 36.00, 'New'),
(30, 9, 1, 34.20, 'Mint'),
(31, 9, 1, 30.60, 'NM'),
(32, 9, 1, 19.80, 'VG'),

(33, 10, 2, 28.00, 'New'),
(34, 10, 1, 26.60, 'Mint'),
(35, 10, 1, 23.80, 'NM'),
(36, 10, 1, 19.60, 'VG+'),

(37, 11, 2, 26.00, 'New'),
(38, 11, 1, 24.70, 'Mint'),
(39, 11, 1, 22.10, 'NM'),
(40, 11, 1, 18.20, 'VG+');

INSERT INTO BuybackOrder (CustomerID, ProcessedByEmployeeID, ShopID, BuybackDate, Status, TotalPayment) VALUES
(2, 3, 1, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Completed', 36.00);

INSERT INTO BuybackOrderLine (BuybackOrderID, ReleaseID, Quantity, UnitPrice, ConditionGrade) VALUES
(1, 9, 3, 12.00, 'VG+');

INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(1, 1, 'Supplier', 1, 'BATCH-20251001-1', 'New', 'Available', 56.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 'Supplier', 1, 'BATCH-20251001-1', 'New', 'Available', 56.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 'Supplier', 2, 'BATCH-20251001-2', 'Mint', 'Available', 53.20, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 4, 'BATCH-20251001-4', 'New', 'Available', 64.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 4, 'BATCH-20251001-4', 'New', 'Available', 64.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 5, 'BATCH-20251001-5', 'Mint', 'Available', 60.80, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 6, 'BATCH-20251001-6', 'VG+', 'Available', 44.80, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 'Supplier', 1, 'BATCH-20251001-1', 'New', 'Sold', 56.00, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(2, 1, 'Supplier', 5, 'BATCH-20251001-5', 'Mint', 'Sold', 60.80, DATE_SUB(NOW(), INTERVAL 69 DAY)),
(1, 1, 'Supplier', 3, 'BATCH-20251001-3', 'VG+', 'Available', 39.20, DATE_SUB(NOW(), INTERVAL 69 DAY));

INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(3, 2, 'Supplier', 7, 'BATCH-20251115-7', 'New', 'Available', 40.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(3, 2, 'Supplier', 7, 'BATCH-20251115-7', 'New', 'Available', 40.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(3, 2, 'Supplier', 7, 'BATCH-20251115-7', 'New', 'Sold', 40.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 10, 'BATCH-20251115-10', 'New', 'Available', 72.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 10, 'BATCH-20251115-10', 'New', 'Sold', 72.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 12, 'BATCH-20251115-12', 'NM', 'Available', 61.20, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 15, 'BATCH-20251115-15', 'Mint', 'Available', 45.60, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 15, 'BATCH-20251115-15', 'Mint', 'Sold', 45.60, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 17, 'BATCH-20251115-17', 'VG+', 'Available', 33.60, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(3, 2, 'Supplier', 9, 'BATCH-20251115-9', 'VG', 'Available', 20.63, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 13, 'BATCH-20251115-13', 'VG', 'Available', 39.60, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 14, 'BATCH-20251115-14', 'New', 'Available', 48.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(3, 2, 'Supplier', 8, 'BATCH-20251115-8', 'Mint', 'Available', 38.00, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(4, 2, 'Supplier', 11, 'BATCH-20251115-11', 'Mint', 'Available', 68.40, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(5, 2, 'Supplier', 16, 'BATCH-20251115-16', 'NM', 'Available', 40.80, DATE_SUB(NOW(), INTERVAL 49 DAY));

INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(6, 3, 'Supplier', 18, 'BATCH-20251210-18', 'New', 'Available', 51.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 3, 'Supplier', 18, 'BATCH-20251210-18', 'New', 'Available', 51.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 3, 'Supplier', 18, 'BATCH-20251210-18', 'New', 'Sold', 51.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 21, 'BATCH-20251210-21', 'New', 'Available', 60.80, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 21, 'BATCH-20251210-21', 'New', 'Available', 60.80, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 22, 'BATCH-20251210-22', 'Mint', 'Sold', 57.76, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 25, 'BATCH-20251210-25', 'New', 'Available', 67.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 25, 'BATCH-20251210-25', 'New', 'Available', 67.20, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 28, 'BATCH-20251210-28', 'VG+', 'Sold', 47.04, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 3, 'Supplier', 20, 'BATCH-20251210-20', 'VG', 'Available', 26.40, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 24, 'BATCH-20251210-24', 'VG', 'Available', 33.44, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 27, 'BATCH-20251210-27', 'NM', 'Available', 57.12, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 3, 'Supplier', 19, 'BATCH-20251210-19', 'Mint', 'Available', 48.64, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 3, 'Supplier', 23, 'BATCH-20251210-23', 'NM', 'Available', 51.68, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(8, 3, 'Supplier', 26, 'BATCH-20251210-26', 'Mint', 'Available', 63.84, DATE_SUB(NOW(), INTERVAL 29 DAY)),

(9, 3, 'Supplier', 29, 'BATCH-20251218-29', 'New', 'Available', 57.60, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(9, 3, 'Supplier', 29, 'BATCH-20251218-29', 'New', 'Available', 57.60, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(9, 3, 'Supplier', 30, 'BATCH-20251218-30', 'Mint', 'Available', 54.72, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 33, 'BATCH-20251218-33', 'New', 'Available', 44.80, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 33, 'BATCH-20251218-33', 'New', 'Available', 44.80, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 36, 'BATCH-20251218-36', 'VG+', 'Available', 29.40, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 37, 'BATCH-20251218-37', 'New', 'Available', 41.60, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 37, 'BATCH-20251218-37', 'New', 'Available', 41.60, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 39, 'BATCH-20251218-39', 'NM', 'Available', 35.36, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(9, 3, 'Supplier', 32, 'BATCH-20251218-32', 'VG', 'Available', 29.70, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 34, 'BATCH-20251218-34', 'Mint', 'Available', 42.56, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 40, 'BATCH-20251218-40', 'VG+', 'Available', 27.30, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(9, 3, 'Supplier', 31, 'BATCH-20251218-31', 'NM', 'Available', 48.96, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(10, 3, 'Supplier', 35, 'BATCH-20251218-35', 'NM', 'Available', 38.08, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(11, 3, 'Supplier', 38, 'BATCH-20251218-38', 'Mint', 'Available', 39.52, DATE_SUB(NOW(), INTERVAL 9 DAY));

INSERT INTO StockItem (ReleaseID, ShopID, SourceType, SourceOrderID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) VALUES
(9, 1, 'Buyback', 1, 'BATCH-20251215-B1', 'VG+', 'Available', 40.32, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9, 1, 'Buyback', 1, 'BATCH-20251215-B1', 'VG+', 'Available', 40.32, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9, 1, 'Buyback', 1, 'BATCH-20251215-B1', 'VG+', 'Available', 40.32, DATE_SUB(NOW(), INTERVAL 15 DAY));

INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, ProcessedByEmployeeID, OrderDate, TotalAmount, OrderStatus, OrderType) VALUES
(1, 1, 3, DATE_SUB(NOW(), INTERVAL 60 DAY), 56.00, 'Completed', 'InStore'),
(2, 1, 3, DATE_SUB(NOW(), INTERVAL 55 DAY), 60.80, 'Completed', 'InStore'),
(1, 2, 5, DATE_SUB(NOW(), INTERVAL 45 DAY), 40.00, 'Completed', 'InStore'),
(3, 2, 5, DATE_SUB(NOW(), INTERVAL 40 DAY), 72.00, 'Completed', 'InStore'),
(2, 2, 5, DATE_SUB(NOW(), INTERVAL 35 DAY), 45.60, 'Completed', 'InStore');

INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType, FulfillmentType, ShippingCost, ShippingAddress) VALUES
(1, 3, DATE_SUB(NOW(), INTERVAL 20 DAY), 66.20, 'Completed', 'Online', 'Shipping', 15.00, '123 Main Street, Changsha'),
(2, 3, DATE_SUB(NOW(), INTERVAL 15 DAY), 72.76, 'Completed', 'Online', 'Shipping', 15.00, '456 Oak Avenue, Shanghai'),
(4, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), 62.04, 'Completed', 'Online', 'Shipping', 15.00, '789 Elm Road, Beijing');

INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES

(1, 8, 56.00),
(2, 9, 60.80),
(3, 13, 40.00),
(4, 15, 72.00),
(5, 18, 45.60),

(6, 28, 51.20),
(7, 31, 57.76),
(8, 34, 47.04);

UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 60 DAY) WHERE StockItemID = 8;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 55 DAY) WHERE StockItemID = 9;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 45 DAY) WHERE StockItemID = 13;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 40 DAY) WHERE StockItemID = 15;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 35 DAY) WHERE StockItemID = 18;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 20 DAY) WHERE StockItemID = 28;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 15 DAY) WHERE StockItemID = 31;
UPDATE StockItem SET DateSold = DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE StockItemID = 34;

INSERT INTO InventoryTransfer (StockItemID, FromShopID, ToShopID, TransferDate, AuthorizedByEmployeeID, ReceivedByEmployeeID, Status, ReceivedDate) VALUES
(26, 3, 1, DATE_SUB(NOW(), INTERVAL 25 DAY), 6, 3, 'Completed', DATE_SUB(NOW(), INTERVAL 24 DAY)),
(29, 3, 2, DATE_SUB(NOW(), INTERVAL 20 DAY), 6, 5, 'Completed', DATE_SUB(NOW(), INTERVAL 19 DAY));

UPDATE StockItem SET ShopID = 1 WHERE StockItemID = 26;
UPDATE StockItem SET ShopID = 2 WHERE StockItemID = 29;

SET FOREIGN_KEY_CHECKS = 1;

SELECT '========== TEST ACCOUNTS ==========' AS Info;
SELECT 'All passwords: password123' AS Password;
SELECT '' AS '';
SELECT 'ADMIN: admin' AS Account;
SELECT 'MANAGERS: manager_cs, manager_sh' AS Account;
SELECT 'STAFF: staff_cs, staff_sh, staff_wh' AS Account;
SELECT 'CUSTOMERS: alice@test.com, bob@test.com, charlie@test.com, diana@test.com, edward@test.com' AS Account;