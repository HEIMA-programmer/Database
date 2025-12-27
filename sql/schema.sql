-- ========================================
-- Refactored Schema for Retro Echo Records
-- 重构版架构 - PurchaseOrder拆分为SupplierOrder和BuybackOrder
-- ========================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Organization & Roles
CREATE TABLE Shop (
    ShopID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Address TEXT,
    Type ENUM('Retail', 'Warehouse') NOT NULL
);

CREATE TABLE Employee (
    EmployeeID INT AUTO_INCREMENT PRIMARY KEY,
    ShopID INT,
    Role ENUM('Admin', 'Manager', 'Staff') NOT NULL,
    Name VARCHAR(100) NOT NULL,
    Username VARCHAR(50) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    HireDate DATE DEFAULT (CURRENT_DATE),
    FOREIGN KEY (ShopID) REFERENCES Shop(ShopID)
);

-- 2. Customer & Membership
CREATE TABLE MembershipTier (
    TierID INT AUTO_INCREMENT PRIMARY KEY,
    TierName VARCHAR(50) NOT NULL,
    MinPoints INT NOT NULL,
    DiscountRate DECIMAL(3,2) NOT NULL
);

CREATE TABLE Customer (
    CustomerID INT AUTO_INCREMENT PRIMARY KEY,
    TierID INT DEFAULT 1,
    Name VARCHAR(255) NOT NULL,
    Email VARCHAR(255) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    Birthday DATE,
    Points INT DEFAULT 0,
    FOREIGN KEY (TierID) REFERENCES MembershipTier(TierID)
);

-- 3. Product Catalog
CREATE TABLE ReleaseAlbum (
    ReleaseID INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(255) NOT NULL,
    ArtistName VARCHAR(255) NOT NULL,
    LabelName VARCHAR(255) NOT NULL,
    ReleaseYear VARCHAR(4),
    Genre VARCHAR(50),
    Format VARCHAR(50) DEFAULT 'Vinyl',
    Description TEXT
);

CREATE TABLE Track (
    TrackID INT AUTO_INCREMENT PRIMARY KEY,
    ReleaseID INT NOT NULL,
    Title VARCHAR(255) NOT NULL,
    TrackNumber INT,
    Duration VARCHAR(10),
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID) ON DELETE CASCADE
);

-- 4. Procurement - REFACTORED
CREATE TABLE Supplier (
    SupplierID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Email VARCHAR(255)
);

-- 【重构】供应商订单表 - 专门处理从供应商进货
CREATE TABLE SupplierOrder (
    SupplierOrderID INT AUTO_INCREMENT PRIMARY KEY,
    SupplierID INT NOT NULL,
    CreatedByEmployeeID INT NOT NULL,
    DestinationShopID INT, -- 目标仓库/门店
    OrderDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Pending', 'Received', 'Cancelled') DEFAULT 'Pending',
    ReceivedDate DATETIME, -- 收货日期
    TotalCost DECIMAL(10,2), -- 总成本
    FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID),
    FOREIGN KEY (CreatedByEmployeeID) REFERENCES Employee(EmployeeID),
    FOREIGN KEY (DestinationShopID) REFERENCES Shop(ShopID)
);

-- 供应商订单明细表
CREATE TABLE SupplierOrderLine (
    SupplierOrderID INT,
    ReleaseID INT,
    Quantity INT NOT NULL,
    UnitCost DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (SupplierOrderID, ReleaseID),
    FOREIGN KEY (SupplierOrderID) REFERENCES SupplierOrder(SupplierOrderID) ON DELETE CASCADE,
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID)
);

-- 【重构】回购订单表 - 专门处理客户回购
CREATE TABLE BuybackOrder (
    BuybackOrderID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT, -- 【修复】允许NULL以支持Walk-in匿名客户回购
    ProcessedByEmployeeID INT NOT NULL,
    ShopID INT NOT NULL, -- 回购处理门店
    BuybackDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
    TotalPayment DECIMAL(10,2), -- 支付给客户的总金额
    Notes TEXT, -- 备注
    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID),
    FOREIGN KEY (ProcessedByEmployeeID) REFERENCES Employee(EmployeeID),
    FOREIGN KEY (ShopID) REFERENCES Shop(ShopID)
);

-- 回购订单明细表
CREATE TABLE BuybackOrderLine (
    BuybackOrderID INT,
    ReleaseID INT,
    Quantity INT NOT NULL,
    UnitPrice DECIMAL(10,2) NOT NULL, -- 回购单价
    ConditionGrade ENUM('New', 'Mint', 'NM', 'VG+', 'VG') NOT NULL,
    PRIMARY KEY (BuybackOrderID, ReleaseID),
    FOREIGN KEY (BuybackOrderID) REFERENCES BuybackOrder(BuybackOrderID) ON DELETE CASCADE,
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID)
);

-- 5. Inventory Management
CREATE TABLE StockItem (
    StockItemID INT AUTO_INCREMENT PRIMARY KEY,
    ReleaseID INT NOT NULL,
    ShopID INT NOT NULL,
    SourceType ENUM('Supplier', 'Buyback') NOT NULL, -- 来源类型
    SourceOrderID INT, -- 来源订单ID（根据SourceType查对应表）
    BatchNo VARCHAR(50) NOT NULL,
    ConditionGrade ENUM('New', 'Mint', 'NM', 'VG+', 'VG') NOT NULL,
    Status ENUM('Available', 'Sold', 'Reserved', 'InTransit') DEFAULT 'Available',
    UnitPrice DECIMAL(10,2) NOT NULL,
    AcquiredDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    DateSold DATETIME DEFAULT NULL,
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID),
    FOREIGN KEY (ShopID) REFERENCES Shop(ShopID)
    -- Note: 无法直接用FK引用SourceOrderID，需在应用层或触发器中验证
);

CREATE TABLE InventoryTransfer (
    TransferID INT AUTO_INCREMENT PRIMARY KEY,
    StockItemID INT NOT NULL,
    FromShopID INT NOT NULL,
    ToShopID INT NOT NULL,
    TransferDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    AuthorizedByEmployeeID INT,
    ReceivedByEmployeeID INT,
    Status ENUM('Pending', 'InTransit', 'Completed', 'Cancelled') DEFAULT 'Pending',
    ReceivedDate DATETIME,
    FOREIGN KEY (StockItemID) REFERENCES StockItem(StockItemID),
    FOREIGN KEY (FromShopID) REFERENCES Shop(ShopID),
    FOREIGN KEY (ToShopID) REFERENCES Shop(ShopID),
    FOREIGN KEY (AuthorizedByEmployeeID) REFERENCES Employee(EmployeeID),
    FOREIGN KEY (ReceivedByEmployeeID) REFERENCES Employee(EmployeeID)
);

-- 6. Sales (Outbound)
CREATE TABLE CustomerOrder (
    OrderID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT,
    FulfilledByShopID INT NOT NULL,
    ProcessedByEmployeeID INT, -- 处理员工（店内销售时）
    OrderDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    TotalAmount DECIMAL(10,2),
    OrderStatus ENUM('Pending', 'Paid', 'Shipped', 'ReadyForPickup', 'Completed', 'Cancelled') DEFAULT 'Pending',
    OrderType ENUM('InStore', 'Online') NOT NULL,
    FulfillmentType ENUM('Shipping', 'Pickup') DEFAULT NULL, -- 【新增】履行方式：运输或自提
    ShippingAddress TEXT DEFAULT NULL, -- 【新增】送货地址
    ShippingCost DECIMAL(10,2) DEFAULT 0.00, -- 【新增】运费
    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID),
    FOREIGN KEY (FulfilledByShopID) REFERENCES Shop(ShopID),
    FOREIGN KEY (ProcessedByEmployeeID) REFERENCES Employee(EmployeeID)
);

CREATE TABLE OrderLine (
    OrderID INT,
    StockItemID INT,
    PriceAtSale DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (OrderID, StockItemID),
    FOREIGN KEY (OrderID) REFERENCES CustomerOrder(OrderID) ON DELETE CASCADE,
    FOREIGN KEY (StockItemID) REFERENCES StockItem(StockItemID)
);

SET FOREIGN_KEY_CHECKS = 1;
