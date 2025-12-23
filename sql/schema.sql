-- Disable foreign key checks for bulk creation
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Organization & Roles
CREATE TABLE Shop (
    ShopID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Address TEXT,
    Type ENUM('Retail', 'Warehouse') NOT NULL
);

-- UserRole removed per teacher feedback: "字典表" with only 2 attributes is redundant
-- Role is now an ENUM directly in Employee table

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
    TierName VARCHAR(50) NOT NULL, -- 'Standard', 'VIP', 'Gold'
    MinPoints INT NOT NULL,
    DiscountRate DECIMAL(3,2) NOT NULL -- e.g., 0.05 for 5%
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

-- 3. Product Catalog (Merged Artist/Label into Release per feedback)
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

-- 4. Procurement & Inventory
CREATE TABLE Supplier (
    SupplierID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Email VARCHAR(255)
);

CREATE TABLE PurchaseOrder (
    PO_ID INT AUTO_INCREMENT PRIMARY KEY,
    SupplierID INT, -- Nullable if SourceType is Buyback
    CreatedByEmployeeID INT NOT NULL,
    OrderDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Pending', 'Received') DEFAULT 'Pending',
    SourceType ENUM('Supplier', 'Buyback') NOT NULL,
    FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID),
    FOREIGN KEY (CreatedByEmployeeID) REFERENCES Employee(EmployeeID)
);

CREATE TABLE PurchaseOrderLine (
    PO_ID INT,
    ReleaseID INT,
    Quantity INT NOT NULL,
    UnitCost DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (PO_ID, ReleaseID), -- Composite PK per feedback
    FOREIGN KEY (PO_ID) REFERENCES PurchaseOrder(PO_ID) ON DELETE CASCADE,
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID)
);

CREATE TABLE StockItem (
    StockItemID INT AUTO_INCREMENT PRIMARY KEY,
    ReleaseID INT NOT NULL,
    ShopID INT NOT NULL,
    SourcePO_ID INT, -- Traceability link
    BatchNo VARCHAR(50) NOT NULL, -- "B2025-12-01"
    ConditionGrade ENUM('New', 'Mint', 'NM', 'VG+', 'VG') NOT NULL,
    Status ENUM('Available', 'Sold', 'Reserved') DEFAULT 'Available',
    UnitPrice DECIMAL(10,2) NOT NULL,
    AcquiredDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    DateSold DATETIME DEFAULT NULL, -- 售出日期，用于计算库存周转率
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID),
    FOREIGN KEY (ShopID) REFERENCES Shop(ShopID),
    FOREIGN KEY (SourcePO_ID) REFERENCES PurchaseOrder(PO_ID)
);

CREATE TABLE InventoryTransfer (
    TransferID INT AUTO_INCREMENT PRIMARY KEY,
    StockItemID INT NOT NULL,
    FromShopID INT NOT NULL,
    ToShopID INT NOT NULL,
    TransferDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    AuthorizedByEmployeeID INT,
    FOREIGN KEY (StockItemID) REFERENCES StockItem(StockItemID),
    FOREIGN KEY (FromShopID) REFERENCES Shop(ShopID),
    FOREIGN KEY (ToShopID) REFERENCES Shop(ShopID),
    FOREIGN KEY (AuthorizedByEmployeeID) REFERENCES Employee(EmployeeID)
);

-- 5. Sales (Outbound)
CREATE TABLE CustomerOrder (
    OrderID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT,
    FulfilledByShopID INT NOT NULL,
    OrderDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    TotalAmount DECIMAL(10,2),
    OrderStatus ENUM('Pending', 'Paid', 'Shipped', 'Completed', 'Cancelled') DEFAULT 'Pending',
    OrderType ENUM('InStore', 'Online') NOT NULL,
    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID),
    FOREIGN KEY (FulfilledByShopID) REFERENCES Shop(ShopID)
);

CREATE TABLE OrderLine (
    OrderID INT,
    StockItemID INT,
    PriceAtSale DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (OrderID, StockItemID), -- Composite PK per feedback
    FOREIGN KEY (OrderID) REFERENCES CustomerOrder(OrderID) ON DELETE CASCADE,
    FOREIGN KEY (StockItemID) REFERENCES StockItem(StockItemID)
);

SET FOREIGN_KEY_CHECKS = 1;