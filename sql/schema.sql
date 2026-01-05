SET FOREIGN_KEY_CHECKS = 0;

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
    CurrentSessionID VARCHAR(128) DEFAULT NULL,
    FOREIGN KEY (ShopID) REFERENCES Shop(ShopID)
);

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
    CurrentSessionID VARCHAR(128) DEFAULT NULL,
    FOREIGN KEY (TierID) REFERENCES MembershipTier(TierID)
);

CREATE TABLE ReleaseAlbum (
    ReleaseID INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(255) NOT NULL,
    ArtistName VARCHAR(255) NOT NULL,
    LabelName VARCHAR(255) NOT NULL,
    ReleaseYear VARCHAR(4),
    Genre VARCHAR(50),
    Format VARCHAR(50) DEFAULT 'Vinyl',
    Description TEXT,
    BaseUnitCost DECIMAL(10,2) DEFAULT 25.00
);

CREATE TABLE Track (
    TrackID INT AUTO_INCREMENT PRIMARY KEY,
    ReleaseID INT NOT NULL,
    Title VARCHAR(255) NOT NULL,
    TrackNumber INT,
    Duration VARCHAR(10),
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID) ON DELETE CASCADE
);

CREATE TABLE Supplier (
    SupplierID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Email VARCHAR(255)
);

CREATE TABLE SupplierOrder (
    SupplierOrderID INT AUTO_INCREMENT PRIMARY KEY,
    SupplierID INT NOT NULL,
    CreatedByEmployeeID INT NOT NULL,
    DestinationShopID INT,
    OrderDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Pending', 'Received', 'Cancelled') DEFAULT 'Pending',
    ReceivedDate DATETIME,
    TotalCost DECIMAL(10,2),
    FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID),
    FOREIGN KEY (CreatedByEmployeeID) REFERENCES Employee(EmployeeID),
    FOREIGN KEY (DestinationShopID) REFERENCES Shop(ShopID)
);

CREATE TABLE SupplierOrderLine (
    SupplierOrderID INT,
    ReleaseID INT,
    Quantity INT NOT NULL,
    UnitCost DECIMAL(10,2) NOT NULL,
    ConditionGrade ENUM('New', 'Mint', 'NM', 'VG+', 'VG') DEFAULT 'New',
    SalePrice DECIMAL(10,2) DEFAULT NULL,
    PRIMARY KEY (SupplierOrderID, ReleaseID),
    FOREIGN KEY (SupplierOrderID) REFERENCES SupplierOrder(SupplierOrderID) ON DELETE CASCADE,
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID)
);

CREATE TABLE BuybackOrder (
    BuybackOrderID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT,
    ProcessedByEmployeeID INT NOT NULL,
    ShopID INT NOT NULL,
    BuybackDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
    TotalPayment DECIMAL(10,2),
    Notes TEXT,
    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID),
    FOREIGN KEY (ProcessedByEmployeeID) REFERENCES Employee(EmployeeID),
    FOREIGN KEY (ShopID) REFERENCES Shop(ShopID)
);

CREATE TABLE BuybackOrderLine (
    BuybackOrderID INT,
    ReleaseID INT,
    Quantity INT NOT NULL,
    UnitPrice DECIMAL(10,2) NOT NULL,
    ConditionGrade ENUM('New', 'Mint', 'NM', 'VG+', 'VG') NOT NULL,
    PRIMARY KEY (BuybackOrderID, ReleaseID),
    FOREIGN KEY (BuybackOrderID) REFERENCES BuybackOrder(BuybackOrderID) ON DELETE CASCADE,
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID)
);

CREATE TABLE StockItem (
    StockItemID INT AUTO_INCREMENT PRIMARY KEY,
    ReleaseID INT NOT NULL,
    ShopID INT NOT NULL,
    SourceType ENUM('Supplier', 'Buyback') NOT NULL,
    SourceOrderID INT,
    BatchNo VARCHAR(50) NOT NULL,
    ConditionGrade ENUM('New', 'Mint', 'NM', 'VG+', 'VG') NOT NULL,
    Status ENUM('Available', 'Sold', 'Reserved', 'InTransit') DEFAULT 'Available',
    UnitPrice DECIMAL(10,2) NOT NULL,
    AcquiredDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    DateSold DATETIME DEFAULT NULL,
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID),
    FOREIGN KEY (ShopID) REFERENCES Shop(ShopID)

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

CREATE TABLE CustomerOrder (
    OrderID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT,
    FulfilledByShopID INT NOT NULL,
    ProcessedByEmployeeID INT,
    OrderDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    TotalAmount DECIMAL(10,2),
    OrderStatus ENUM('Pending', 'Paid', 'Shipped', 'ReadyForPickup', 'Completed', 'Cancelled') DEFAULT 'Pending',
    OrderType ENUM('InStore', 'Online') NOT NULL,
    FulfillmentType ENUM('Shipping', 'Pickup') DEFAULT NULL,
    ShippingAddress TEXT DEFAULT NULL,
    ShippingCost DECIMAL(10,2) DEFAULT 0.00,
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

CREATE TABLE ManagerRequest (
    RequestID INT AUTO_INCREMENT PRIMARY KEY,
    RequestType ENUM('PriceAdjustment', 'TransferRequest') NOT NULL,
    RequestedByEmployeeID INT NOT NULL,
    FromShopID INT NOT NULL,
    ToShopID INT DEFAULT NULL,
    ReleaseID INT NOT NULL,
    ConditionGrade ENUM('New', 'Mint', 'NM', 'VG+', 'VG') NOT NULL,
    Quantity INT NOT NULL DEFAULT 1,
    CurrentPrice DECIMAL(10,2) DEFAULT NULL,
    RequestedPrice DECIMAL(10,2) DEFAULT NULL,
    Reason TEXT,
    Status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    AdminResponseNote TEXT,
    RespondedByEmployeeID INT DEFAULT NULL,
    ViewedByRequesterAt DATETIME DEFAULT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (RequestedByEmployeeID) REFERENCES Employee(EmployeeID),
    FOREIGN KEY (RespondedByEmployeeID) REFERENCES Employee(EmployeeID),
    FOREIGN KEY (FromShopID) REFERENCES Shop(ShopID),
    FOREIGN KEY (ToShopID) REFERENCES Shop(ShopID),
    FOREIGN KEY (ReleaseID) REFERENCES ReleaseAlbum(ReleaseID)
);

SET FOREIGN_KEY_CHECKS = 1;