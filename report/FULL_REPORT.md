# DI31003 Assignment 2 - Database Implementation

---

## Retro Echo Records
### Multi-Store Vinyl Record Management System

---

**Module:** DI31003 Database Systems

**Assessment:** Assignment 2 - Database Implementation

**Submission Date:** 28th December 2025

---

### Team Information

| Role | Name | Student ID |
|------|------|------------|
| Team Member 1 | [Your Name] | [Student ID] |
| Team Member 2 | [Your Name] | [Student ID] |
| Team Member 3 | [Your Name] | [Student ID] |
| Team Member 4 | [Your Name] | [Student ID] |

---

**Team Number:** [Your Team Number]

**Total Pages:** 10 (excluding cover and references)

---

*This report documents the implementation of a comprehensive database system for Retro Echo Records, a multi-store vinyl record retailer with online and in-store sales capabilities.*

---

# Table of Contents

1. [System Access Information](#1-system-access-information)
   - 1.1 Web Application Access
   - 1.2 Direct Database Access
   - 1.3 System Architecture Overview
   - 1.4 Store Locations

2. [Executive Summary](#2-executive-summary)
   - 2.1 Project Overview
   - 2.2 Business Objectives Achieved
   - 2.3 Development Approach
   - 2.4 Key System Features
   - 2.5 Additional Features Beyond Requirements
   - 2.6 Technology Stack

3. [User Guide](#3-user-guide)
   - 3.1 Customer User Guide
   - 3.2 Staff User Guide
   - 3.3 Manager User Guide
   - 3.4 Admin User Guide

4. [Technical Annex](#4-technical-annex)
   - 4.1 Database Schema Overview
   - 4.2 Access Management Through Views
   - 4.3 Index Strategy
   - 4.4 Stored Procedures
   - 4.5 Triggers
   - 4.6 Event Scheduler
   - 4.7 Security Implementation

5. [Advanced SQL Queries](#5-advanced-sql-queries)
   - 5.1 Inventory Turnover Analysis
   - 5.2 Customer Lifetime Value Ranking
   - 5.3 Artist Profit Margin Analysis
   - 5.4 Monthly Sales Channel Comparison
   - 5.5 Dead Stock Alert

6. [Additional Features](#6-additional-features)
   - 6.1 Advanced Design Patterns
   - 6.2 Manager Request Workflow
   - 6.3 Event Scheduling
   - 6.4 Trigger System
   - 6.5 Multi-Channel Fulfillment

---

# 1. System Access Information

## 1.1 Web Application Access

**Application URL:** `http://[YOUR-AWS-IP]/public/`

### Employee Accounts

| User Type | Username | Password | Description |
|-----------|----------|----------|-------------|
| **Admin** | `admin` | `password123` | Global system administrator |
| **Manager (Changsha)** | `manager_cs` | `password123` | Changsha retail store manager |
| **Manager (Shanghai)** | `manager_sh` | `password123` | Shanghai retail store manager |
| **Manager (Warehouse)** | `manager_wh` | `password123` | Central warehouse manager |
| **Staff (Changsha)** | `staff_cs` | `password123` | Changsha retail store staff |
| **Staff (Shanghai)** | `staff_sh` | `password123` | Shanghai retail store staff |
| **Staff (Warehouse)** | `staff_wh` | `password123` | Central warehouse staff |

### Customer Accounts

| Customer Name | Email (Username) | Password | Membership Tier |
|---------------|------------------|----------|-----------------|
| Alice Fan | `alice@test.com` | `password123` | VIP |
| Bob Collector | `bob@test.com` | `password123` | Gold |
| Charlie New | `charlie@test.com` | `password123` | Standard |
| Diana Vinyl | `diana@test.com` | `password123` | VIP |
| Edward Rock | `edward@test.com` | `password123` | Standard |

---

## 1.2 Direct Database Access (MySQL Workbench)

| Parameter | Value |
|-----------|-------|
| **Host** | `[YOUR-AWS-IP]` |
| **Port** | `3306` |
| **Database Name** | `retro_echo` |
| **Username** | `root` |
| **Password** | `[YOUR-DB-PASSWORD]` |
| **Character Set** | `utf8mb4` |

---

## 1.3 System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     AWS Cloud Infrastructure                      │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐    ┌─────────────────┐                     │
│  │   Web Server    │    │  MySQL Server   │                     │
│  │   (Apache)      │◄──►│  (MariaDB)      │                     │
│  │   PHP 8.2+      │    │                 │                     │
│  └─────────────────┘    └─────────────────┘                     │
│           ▲                                                      │
│           │ HTTPS                                                │
│           ▼                                                      │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    Internet (Users)                          ││
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐        ││
│  │  │ Admin   │  │ Manager │  │  Staff  │  │Customer │        ││
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘        ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

## 1.4 Store Locations

| Shop ID | Shop Name | Type | Location |
|---------|-----------|------|----------|
| 1 | Retro Echo Changsha | Retail | Changsha, Hunan |
| 2 | Retro Echo Shanghai | Retail | Shanghai |
| 3 | Central Warehouse | Warehouse | Distribution Center |

---

# 2. Executive Summary

## 2.1 Project Overview

Retro Echo Records is a comprehensive multi-store vinyl record management system designed to support the growing operations of our vintage music retail business. This system addresses the complete lifecycle of vinyl record sales, from supplier procurement through to customer fulfillment, while supporting both physical retail locations and online e-commerce channels.

## 2.2 Business Objectives Achieved

| Feature Category | Capabilities |
|------------------|--------------|
| **Sales Channels** | In-Store POS, Online Shop, Guest Checkout |
| **Inventory** | Multi-location, Condition Grading, Batch Tracking |
| **Procurement** | Supplier Orders, Buyback Processing |
| **Fulfillment** | Shipping, Store Pickup, Inter-Store Transfers |
| **Customers** | Membership Tiers, Points System, Order History |
| **Analytics** | Sales Reports, Inventory Turnover, Profit Analysis |

## 2.3 Development Approach

**Phase 1:** Database design refinement from Assessment 1
**Phase 2:** Schema implementation with 17 tables, 109 views, 29 procedures
**Phase 3:** Frontend development with PHP modular architecture
**Phase 4:** Testing, optimization with 40+ indexes including covering indexes

## 2.4 Technology Stack

| Component | Technology |
|-----------|------------|
| Database | MySQL/MariaDB 5.7+ |
| Backend | PHP 8.2+ |
| Frontend | Bootstrap 5, JavaScript |
| Hosting | AWS EC2 |

---

# 3. User Guide

## 3.1 Customer User Guide

### Registration and Login
1. Navigate to homepage and click **"Register"**
2. Enter: Name, Email, Password, Birthday
3. Click **"Create Account"**
4. For login: Enter email/password, select **"Customer"**

### Shopping Process
1. **Browse Catalog:** Filter by genre, artist, condition, price
2. **Add to Cart:** Select condition grade and shop location
3. **Checkout:** Choose Shipping or Store Pickup
4. **Payment:** Complete within 15 minutes (auto-cancels otherwise)
5. **Track Orders:** View status in "My Orders"

### Membership Benefits
| Tier | Points Required | Discount |
|------|-----------------|----------|
| Standard | 0 | 0% |
| VIP | 1,000 | 5% |
| Gold | 5,000 | 10% |

---

## 3.2 Staff User Guide

### Point of Sale (POS)
1. Navigate to **Staff → POS**
2. Search products by title/artist
3. Select condition and quantity
4. Optional: Look up customer for discount
5. Click **"Complete Sale"**

### Buyback Processing
1. Navigate to **Staff → Buyback**
2. Select album, grade condition
3. System suggests buyback price
4. Enter quantity, confirm with customer
5. Process buyback (adds to inventory)

### Order Fulfillment
- **Pickups:** Verify and complete pickup orders
- **Shipping:** Pick, pack, mark as shipped
- **Transfers:** Receive incoming transfers

---

## 3.3 Manager User Guide

### Dashboard KPIs
- Daily/Monthly revenue
- Pending orders count
- Low stock alerts
- Performance trends

### Reports Available
1. Sales by Genre
2. Sales by Artist
3. Monthly Trends
4. Batch Analysis

### Request System
- Submit price adjustment requests
- Submit transfer requests
- Track approval status

---

## 3.4 Admin User Guide

### Product Management
- Add/edit album catalog
- Manage track listings
- Set base pricing

### Procurement
- Create supplier orders
- Receive shipments (auto-generates inventory)
- Manage suppliers

### Request Approval
- Review manager requests
- Approve/reject with notes
- System executes approved changes

---

# 4. Technical Annex

## 4.1 Database Schema

**17 Core Tables:**
- Organization: Shop, Employee
- Customers: MembershipTier, Customer
- Catalog: ReleaseAlbum, Track
- Procurement: Supplier, SupplierOrder, SupplierOrderLine
- Buyback: BuybackOrder, BuybackOrderLine
- Inventory: StockItem, InventoryTransfer
- Sales: CustomerOrder, OrderLine
- Management: ManagerRequest

## 4.2 View-Based Access Control

**109 Views** implementing role-based data filtering:

```sql
-- Customer sees only their orders
CREATE VIEW vw_customer_order_history AS
SELECT co.*, r.Title FROM CustomerOrder co
JOIN OrderLine ol ON co.OrderID = ol.OrderID
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID;
-- App filters: WHERE CustomerID = ?
```

## 4.3 Index Strategy

**40+ Indexes** including 8 covering indexes:

```sql
-- Covering index for inventory queries
CREATE INDEX idx_stock_covering_available
ON StockItem(ShopID, ReleaseID, Status, ConditionGrade, UnitPrice);

-- Covering index for fulfillment
CREATE INDEX idx_order_fulfillment_covering
ON CustomerOrder(FulfilledByShopID, FulfillmentType, OrderStatus,
                 OrderDate, TotalAmount);
```

## 4.4 Stored Procedures (29 Total)

Key procedures include:
- `sp_receive_supplier_order` - Generate inventory from procurement
- `sp_process_buyback` - Handle customer buybacks with points
- `sp_create_pos_order` - Atomic POS transaction
- `sp_respond_to_request` - Process manager requests

## 4.5 Triggers (15 Total)

- Order completion → award points, upgrade tier
- Order cancellation → release reserved stock
- Transfer completion → move stock to destination
- Order line changes → recalculate totals
- Polymorphic FK validation

## 4.6 Event Scheduler

```sql
CREATE EVENT evt_release_expired_reservations
ON SCHEDULE EVERY 5 MINUTE
DO CALL sp_release_expired_reservations();
```

---

# 5. Advanced SQL Queries

## 5.1 Inventory Turnover Analysis

**Techniques:** DATEDIFF, AVG, Multi-JOIN

```sql
SELECT r.Genre,
       COUNT(*) AS TotalItemsSold,
       AVG(DATEDIFF(co.OrderDate, s.AcquiredDate)) AS AvgDaysToSell
FROM StockItem s
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
JOIN CustomerOrder co ON ol.OrderID = co.OrderID
WHERE s.Status = 'Sold'
GROUP BY r.Genre
ORDER BY AvgDaysToSell;
```

## 5.2 Customer Lifetime Value Ranking

**Techniques:** Window Function DENSE_RANK()

```sql
SELECT c.Name, c.Email, mt.TierName,
       SUM(co.TotalAmount) AS TotalSpent,
       DENSE_RANK() OVER (ORDER BY SUM(co.TotalAmount) DESC) AS Rank
FROM Customer c
JOIN MembershipTier mt ON c.TierID = mt.TierID
JOIN CustomerOrder co ON c.CustomerID = co.CustomerID
WHERE co.OrderStatus IN ('Paid', 'Completed')
GROUP BY c.CustomerID, c.Name, c.Email, mt.TierName
LIMIT 10;
```

## 5.3 Artist Profit Analysis

**Techniques:** Correlated Subquery

```sql
SELECT r.ArtistName,
       SUM(ol.PriceAtSale) AS Revenue,
       (SELECT SUM(sol.Quantity * sol.UnitCost)
        FROM SupplierOrderLine sol
        WHERE sol.ReleaseID IN (
            SELECT ReleaseID FROM ReleaseAlbum
            WHERE ArtistName = r.ArtistName)
       ) AS Cost
FROM OrderLine ol
JOIN StockItem s ON ol.StockItemID = s.StockItemID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
GROUP BY r.ArtistName;
```

## 5.4 Channel Comparison

**Techniques:** CASE Pivot, DATE_FORMAT

```sql
SELECT DATE_FORMAT(OrderDate, '%Y-%m') AS Month,
       SUM(CASE WHEN OrderType='InStore' THEN TotalAmount ELSE 0 END) AS Store,
       SUM(CASE WHEN OrderType='Online' THEN TotalAmount ELSE 0 END) AS Online
FROM CustomerOrder
WHERE OrderStatus IN ('Paid', 'Completed')
GROUP BY Month ORDER BY Month DESC;
```

## 5.5 Dead Stock Alert

**Techniques:** HAVING, Date Arithmetic

```sql
SELECT sh.Name, r.Title,
       DATEDIFF(CURRENT_DATE, s.AcquiredDate) AS DaysInStock
FROM StockItem s
JOIN Shop sh ON s.ShopID = sh.ShopID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
WHERE s.Status = 'Available'
HAVING DaysInStock > 60
ORDER BY DaysInStock DESC;
```

---

# 6. Additional Features

## 6.1 Polymorphic Foreign Key Pattern

StockItem tracks provenance from either SupplierOrder or BuybackOrder using discriminator column.

## 6.2 Manager Request Workflow

Complete approval system for price adjustments and inventory transfers with audit trail.

## 6.3 Event-Based Cleanup

Automatic cancellation of abandoned orders after 15 minutes via MySQL Event Scheduler.

## 6.4 Birthday Bonus System

20% extra points earned during customer's birthday month.

## 6.5 Covering Indexes

8 covering indexes for zero-table-lookup on critical queries.

## 6.6 Walk-in Customer Support

Anonymous transactions supported for both purchases and buybacks.

---

# References

1. MySQL 8.0 Reference Manual - https://dev.mysql.com/doc/refman/8.0/en/
2. PHP PDO Documentation - https://www.php.net/manual/en/book.pdo.php
3. Bootstrap 5 Documentation - https://getbootstrap.com/docs/5.0/

---

*End of Report*

---

**Note:** Systems must remain accessible on AWS servers until the end of February 2025 for marking purposes.
