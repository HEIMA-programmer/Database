# 6. Additional Features and Design Highlights

This section documents the additional features and design decisions that go beyond the basic assignment requirements, demonstrating advanced database techniques and comprehensive system design.

---

## 6.1 Advanced Design Patterns

### 6.1.1 Polymorphic Foreign Key Pattern

The `StockItem` table implements a polymorphic foreign key pattern to track item provenance from either suppliers or customer buybacks:

```sql
CREATE TABLE StockItem (
    StockItemID INT AUTO_INCREMENT PRIMARY KEY,
    SourceType ENUM('Supplier', 'Buyback') NOT NULL,  -- Discriminator
    SourceOrderID INT,  -- References either table based on SourceType
    -- ...
);
```

**Benefits:**
- Single inventory table for unified management
- Full traceability of item source
- Trigger-validated referential integrity
- Simplified reporting across all stock

### 6.1.2 View-Based Access Control (VBAC)

Instead of complex permission systems, we implemented **109 database views** that:
- Filter data based on user role context
- Prevent direct table access
- Simplify application security logic
- Provide role-appropriate data projections

**View Categories:**
| Category | Count | Purpose |
|----------|-------|---------|
| Customer Views | 12 | Order history, profile, catalog |
| Staff Views | 18 | POS, inventory, fulfillment |
| Manager Views | 25 | Reports, KPIs, requests |
| Admin Views | 20 | User/product management |
| Analytics Views | 15 | Business intelligence |
| Utility Views | 19 | Shared lookups, validation |

---

## 6.2 Manager Request Approval Workflow

A complete request management system was implemented for controlled operations:

### 6.2.1 Request Types

**Price Adjustment Request:**
- Manager identifies need for price change
- Submits request with justification
- Admin reviews market conditions
- Approval updates all matching stock prices

**Transfer Request:**
- Manager requests inventory from warehouse or other stores
- Specifies quantity and urgency
- Admin approves based on availability
- System creates transfer records for fulfillment

### 6.2.2 Request Lifecycle

```
┌──────────┐    Submit    ┌──────────┐    Review    ┌──────────┐
│ Manager  │──────────────▶│ Pending  │──────────────▶│  Admin   │
└──────────┘              └──────────┘              └──────────┘
                                                         │
                          ┌──────────────────────────────┼──────────────────┐
                          │                              │                  │
                          ▼                              ▼                  ▼
                    ┌──────────┐                  ┌──────────┐       ┌──────────┐
                    │ Approved │                  │ Rejected │       │  Notes   │
                    └────┬─────┘                  └──────────┘       └──────────┘
                         │
                         ▼
                    ┌──────────┐
                    │ Execute  │ (Price update or Transfer creation)
                    └──────────┘
```

### 6.2.3 Notification System

- Managers see pending request count on dashboard
- "New response" indicator for admin replies
- Request history with admin notes

---

## 6.3 Automated Event Scheduling

### 6.3.1 Expired Reservation Cleanup

```sql
CREATE EVENT evt_release_expired_reservations
ON SCHEDULE EVERY 5 MINUTE
ENABLE
DO
CALL sp_release_expired_reservations();
```

**Functionality:**
- Runs every 5 minutes
- Cancels orders pending > 15 minutes
- Releases reserved stock to available
- Prevents inventory lock-up from abandoned carts

### 6.3.2 Business Rule Enforcement

The event scheduler ensures business rules are enforced even when users don't complete actions, maintaining inventory accuracy and availability.

---

## 6.4 Comprehensive Trigger System

### 6.4.1 Trigger Summary

| Trigger | Event | Purpose |
|---------|-------|---------|
| trg_after_order_complete | Order → Completed | Award points, upgrade tier |
| trg_after_order_cancel | Order → Cancelled | Release reserved stock |
| trg_after_transfer_complete | Transfer → Completed | Move stock to destination |
| trg_after_transfer_cancel | Transfer → Cancelled | Restore stock status |
| trg_before_stock_item_insert | Insert StockItem | Validate polymorphic FK |
| trg_before_stock_status_update | Update StockItem | Auto-set DateSold |
| trg_after_order_line_* | OrderLine changes | Recalculate order total |
| trg_after_supplier_order_line_* | SupplierOrderLine | Recalculate order cost |
| trg_after_buyback_order_line_* | BuybackOrderLine | Recalculate payment |
| trg_birthday_bonus | Order complete | 20% bonus points in birthday month |

### 6.4.2 Birthday Bonus Implementation

```sql
-- Enhanced point calculation with birthday bonus
IF MONTH(v_customer_birthday) = MONTH(CURRENT_DATE) THEN
    SET v_points_to_add = FLOOR(v_goods_amount * 1.2);  -- 20% bonus
ELSE
    SET v_points_to_add = FLOOR(v_goods_amount);
END IF;
```

---

## 6.5 Multi-Channel Fulfillment Support

### 6.5.1 Order Types

| OrderType | FulfillmentType | Description |
|-----------|-----------------|-------------|
| InStore | N/A | POS transaction, immediate completion |
| Online | Shipping | Ship to customer address |
| Online | Pickup | Buy Online, Pick up In Store (BOPIS) |

### 6.5.2 Walk-in Customer Support

Both orders and buybacks support anonymous customers:

```sql
CREATE TABLE CustomerOrder (
    CustomerID INT,  -- NULL allowed for walk-in
    -- ...
);

CREATE TABLE BuybackOrder (
    CustomerID INT,  -- NULL allowed for anonymous buyback
    -- ...
);
```

---

## 6.6 Intelligent Pricing System

### 6.6.1 Condition-Based Pricing

| Condition | Price Multiplier | Description |
|-----------|------------------|-------------|
| New | 1.00 | Factory sealed |
| Mint | 0.95 | Perfect, played once |
| NM (Near Mint) | 0.85 | Minor sleeve wear |
| VG+ | 0.70 | Light surface noise |
| VG | 0.55 | Audible wear |

### 6.6.2 Price Consistency

When new stock is received, all existing stock of same Release+Condition is updated to the new price, ensuring consistent pricing across batches.

---

## 6.7 Comprehensive Reporting Suite

### 6.7.1 Real-Time Dashboard KPIs

- Daily/Weekly/Monthly revenue
- Pending orders count
- Low stock alerts
- Dead stock warnings
- Top customers
- Channel performance

### 6.7.2 Analytical Reports

| Report | Metrics | Use Case |
|--------|---------|----------|
| Sales by Genre | Revenue, Volume, Turnover | Category management |
| Sales by Artist | Revenue, Margin | Licensing decisions |
| Monthly Trends | MoM growth, YoY comparison | Strategic planning |
| Batch Analysis | Sell-through rate | Supplier evaluation |
| Customer LTV | Spending rank, Tier distribution | Marketing targeting |

---

## 6.8 Database Performance Optimizations

### 6.8.1 Covering Index Strategy

Eight covering indexes eliminate table lookups for common query patterns:

```sql
-- Eliminates table lookup for inventory queries
CREATE INDEX idx_stock_covering_available
    ON StockItem(ShopID, ReleaseID, Status, ConditionGrade, UnitPrice);
```

### 6.8.2 Virtual Column Index

Birthday month filtering optimized with generated column:

```sql
ALTER TABLE Customer ADD COLUMN b_month INT AS (MONTH(Birthday)) VIRTUAL;
CREATE INDEX idx_customer_birthday_month ON Customer(b_month);
```

### 6.8.3 Query Plan Verification

```sql
EXPLAIN SELECT * FROM vw_inventory_summary WHERE ShopID = 1;
-- Extra: Using index (covering index in use)
```

---

## 6.9 Security Features

### 6.9.1 Password Security

- BCrypt hashing with cost factor 10
- No plaintext password storage
- Password never logged or displayed

### 6.9.2 SQL Injection Prevention

- PDO prepared statements throughout
- No string concatenation in queries
- Input validation on all forms

### 6.9.3 Access Control

- Session-based authentication
- Role verification on every page
- Shop-level data isolation

---

## 6.10 Modification from Assessment 1

The following modifications were made to the original design:

| Original Design | Modification | Reason |
|-----------------|--------------|--------|
| Single PurchaseOrder | Split to SupplierOrder + BuybackOrder | Clearer business logic separation |
| Direct table access | 109 views | Security and abstraction |
| Manual price updates | Request workflow | Controlled operations |
| Single condition price | Condition-based multipliers | Market accuracy |
| Fixed reservation | 15-minute timeout with events | Prevent inventory lock |

---

*End of Additional Features Section*
