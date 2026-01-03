# 3. User Guide

This section provides comprehensive guidance for each user type within the Retro Echo Records Management System.

---

## 3.1 Customer User Guide

Customers can browse the vinyl catalog, manage their shopping cart, place orders, and track their membership benefits.

### 3.1.1 Registration and Login

**New Customer Registration:**
1. Navigate to the homepage and click **"Register"**
2. Enter your details: Name, Email, Password, Birthday
3. Click **"Create Account"** to complete registration
4. You will automatically receive Standard membership with 0 points

**Existing Customer Login:**
1. Click **"Login"** on the homepage
2. Enter your registered email and password
3. Select **"Customer"** as the user type
4. Click **"Sign In"**

### 3.1.2 Browsing the Catalog

**Product Catalog Features:**
- **Search:** Use the search bar to find albums by title, artist, or genre
- **Filters:** Filter by Genre, Condition Grade, Price Range, and Availability
- **Sorting:** Sort results by Price, Release Year, or Artist Name
- **Stock Display:** View real-time availability by store location and condition

**Viewing Product Details:**
1. Click on any album to view full details
2. View track listing, release information, and available stock
3. Select your preferred condition grade and shop location
4. Click **"Add to Cart"** to add items

### 3.1.3 Shopping Cart and Checkout

**Managing Your Cart:**
- View all items in your cart from the cart icon
- Adjust quantities or remove items
- See running total including any membership discounts

**Checkout Process:**
1. Click **"Proceed to Checkout"**
2. Select Fulfillment Type:
   - **Shipping:** Enter delivery address; shipping fee applies (¥15.00)
   - **Store Pickup:** Select pickup location; no shipping fee
3. Review order summary with applied discounts
4. Click **"Place Order"** to confirm
5. Complete payment within 15 minutes to avoid automatic cancellation

### 3.1.4 Order Management

**Viewing Orders:**
1. Navigate to **"My Orders"** from the user menu
2. View order status: Pending → Paid → Shipped/Ready for Pickup → Completed
3. Click on any order to view detailed item list

**Order Actions:**
- **Cancel Order:** Cancel pending orders before payment
- **Confirm Delivery:** Mark shipped orders as received to complete the transaction

### 3.1.5 Membership and Points

**Membership Tiers:**
| Tier | Points Required | Discount Rate |
|------|-----------------|---------------|
| Standard | 0 | 0% |
| VIP | 1,000 | 5% |
| Gold | 5,000 | 10% |

**Earning Points:**
- Earn 1 point for every ¥1 spent on completed orders
- **Birthday Bonus:** Earn 20% extra points during your birthday month
- **Buyback Bonus:** Earn 0.5 points for every ¥1 received from buyback

**Automatic Tier Upgrade:**
Your membership tier automatically upgrades when you reach the point threshold.

---

## 3.2 Staff User Guide

Staff members handle day-to-day operations including point-of-sale transactions, inventory management, buyback processing, and order fulfillment.

### 3.2.1 Point of Sale (POS)

**Processing In-Store Sales:**
1. Navigate to **Staff → POS**
2. Search for products by title, artist using the search bar
3. Select condition grade and quantity from available inventory
4. Click **"Add to Cart"** to add items to the transaction
5. **Customer Lookup (Optional):** Enter customer email to apply membership discount
6. Review grouped cart display showing items by album
7. Click **"Complete Sale"** to finalize the transaction

**POS Features:**
- Real-time inventory search with shop-level filtering
- Batch add multiple items by condition and quantity
- Grouped cart display by album for easy review
- Walk-in customer support (CustomerID optional)
- Sales history display for current session
- Automatic membership discount application when customer provided

**Technical Implementation:**
- `pos.php` - Main POS interface with inventory search
- `pos_checkout.php` - Transaction completion with atomic database operations
- Stock items are marked as 'Sold' immediately upon transaction completion
- Points are awarded automatically via database trigger

### 3.2.2 Inventory Management

**Viewing Store Inventory:**
1. Navigate to **Staff → Inventory**
2. View all available stock at your location
3. Use filters: Album Title, Artist, Condition, Stock Age

**Stock Status Indicators:**
- 🟢 **Available:** Ready for sale
- 🟡 **Reserved:** Held for pending order
- 🔴 **Low Stock Alert:** Less than 3 items remaining
- ⚠️ **Dead Stock Alert:** Unsold for 60+ days

### 3.2.3 Buyback Processing

**Processing Customer Buybacks:**
1. Navigate to **Staff → Buyback**
2. Select album from dropdown or search by title
3. Grade the vinyl condition (New, Mint, NM, VG+, VG)
4. System calculates suggested buyback price via server-side API:
   - Based on base unit cost and condition multiplier
   - Protected pricing logic not exposed to frontend
5. Enter quantity and confirm resale price
6. Click **"Process Buyback"** to complete transaction
7. Stock items are automatically created with 'Buyback' source type

**Points Award System:**
- Customers earn 0.5 points per ¥1 received
- Walk-in buybacks supported (CustomerID optional)
- Points credited via stored procedure `sp_process_buyback`

**Technical Implementation:**
- `buyback.php` - Buyback interface with condition grading
- `/api/staff/calculate_price.php` - Server-side price calculation
- BuybackOrder and BuybackOrderLine records created
- StockItem records generated with polymorphic FK to BuybackOrder

### 3.2.4 Order Fulfillment

The fulfillment page (`fulfillment.php`) provides a multi-tab interface for comprehensive order management:

**Tab 1: Customer Orders**
- View all orders for your shop by status (Pending/Paid/Shipped)
- Filter by order type and date range
- Complete pickup orders when customer arrives
- Access order details via modal popup

**Tab 2: Pending Shipments (Shipping Orders)**
1. View orders marked for shipping
2. Pick items from warehouse inventory
3. Enter tracking information
4. Click **"Mark as Shipped"** to update status
5. Order status triggers customer notification

**Tab 3: Incoming Transfers**
1. View transfer requests destined for your shop
2. Verify received items match transfer record
3. Click **"Confirm Receipt"** to complete
4. Trigger moves stock from source shop to destination

**Tab 4: Supplier Receipts (Warehouse Only)**
1. View pending supplier orders awaiting receipt
2. Confirm delivery and verify quantities
3. Click **"Receive Order"** to generate inventory
4. StockItems created automatically via `sp_receive_supplier_order`

**Technical Implementation:**
- `fulfillment.php` - Multi-tab fulfillment interface
- `/api/staff/order_detail.php` - AJAX order detail retrieval
- Order status changes trigger inventory status updates via triggers
- Transfer completion moves StockItem to destination ShopID

---

## 3.3 Manager User Guide

Managers oversee shop operations, access analytics, manage customer orders, and submit requests to administration.

### 3.3.1 Dashboard Overview

The manager dashboard (`dashboard.php`) provides real-time shop-level business intelligence with strict shop isolation.

**KPI Cards (Top Row):**
| Metric | Description |
|--------|-------------|
| **Total Revenue** | All-time sales revenue for your shop |
| **Most Popular Item** | Top-selling album with quantity sold |
| **Top Spender** | Highest spending registered customer |
| **Total Inventory Cost** | Historical cost of all inventory acquired |

**Analysis Panels (Bottom Row):**
- **Top Spenders:** Ranked customer list with tier badges, includes walk-in revenue summary
- **Stagnant Inventory:** Items unsold for 60+ days with "Request Price Adjustment" shortcut
- **Low Stock Alert:** Items with <3 units, with "Request Transfer" shortcut
- **Revenue Breakdown:** By channel (Online/Pickup/POS) with detail links

**Security Implementation:**
- Shop affiliation verified from database, not session
- `DBProcedures::getEmployeeShopInfo()` validates employee-shop relationship
- Managers can only view data for their assigned shop

### 3.3.2 Sales Reports

The reports page (`reports.php`) provides four analytical sections with drill-down modals:

**Report Sections:**
| Report | Metrics | Insight |
|--------|---------|---------|
| **Genre Turnover** | Items sold, avg days to sell, revenue | How fast different genres sell |
| **Artist Profit** | Revenue, cost, gross profit, margin % | Which artists are most profitable |
| **Batch Analysis** | Total items, sold/available, sell-through % | Procurement batch performance |
| **Monthly Trends** | Order count, revenue, visual bar chart | Revenue trends over time |

**Interactive Features:**
- Click any row's "Details" button to open modal
- Modal displays individual order/item breakdown
- Data preloaded via PHP for instant display
- `/api/manager/report_details.php` provides AJAX fallback

**Technical Implementation:**
- `prepareReportsPageData()` aggregates all report data
- Genre/artist/batch details preloaded as JSON
- Speed indicators: Fast (<30 days), Moderate (30-90), Slow (>90)

### 3.3.3 Inventory Cost Analysis

**Cost Tracking:**
1. Navigate to **Manager → Inventory Cost**
2. View inventory value by:
   - Individual items
   - Album groupings
   - Condition grades
3. Identify high-value stock and potential write-downs

### 3.3.4 Customer Order Management

**Order Operations:**
1. Navigate to **Manager → Customer Orders**
2. View all orders for your location
3. Filter by status, date range, customer
4. Click order to view details including profit margin

**Order Actions:**
- View order timeline and history
- Access customer contact information
- Escalate issues as needed

### 3.3.5 Request System

The request page (`requests.php`) implements an email-like inbox interface for managing requests:

**Submitting Price Adjustment Requests:**
1. Navigate to **Manager → Requests → New Price Request**
2. Select album from your shop's current inventory
3. Conditions dynamically loaded via `/api/manager/inventory_price.php`
4. Current price and quantity auto-populated from database
5. Enter requested new price with justification
6. Submit for admin approval

**Submitting Transfer Requests:**
1. Click **"New Transfer Request"**
2. Select desired album and condition grade
3. Enter quantity needed for your shop
4. Source shop is determined by Admin upon approval
5. Submit request with justification

**Request Workflow:**
```
Manager Submits → Pending → Admin Reviews → Approved/Rejected
                                              ↓
                           Approved: System executes change
                           - Price: Updates all matching StockItems
                           - Transfer: Creates InventoryTransfer record
```

**Notification System:**
- "New Response" badge when admin replies
- `DBProcedures::markRequestsAsViewed()` clears badge on page visit
- Full audit trail with timestamps

---

## 3.4 Admin User Guide

Administrators have global system access for managing products, suppliers, users, and approving manager requests.

### 3.4.1 Product Management

**Adding New Products:**
1. Navigate to **Admin → Products**
2. Click **"Add New Product"**
3. Enter album details: Title, Artist, Label, Year, Genre, Format
4. Set base unit cost (used for pricing calculations)
5. Add track listing
6. Save product

**Editing Products:**
- Click any product to edit details
- Update pricing, descriptions, or metadata
- Changes apply system-wide

### 3.4.2 Supplier Management

**Managing Suppliers:**
1. Navigate to **Admin → Suppliers**
2. View all registered suppliers
3. Add, edit, or deactivate suppliers
4. View order history per supplier

### 3.4.3 Procurement

The procurement page (`procurement.php`) manages the complete supplier order lifecycle:

**Creating Supplier Orders:**
1. Navigate to **Admin → Procurement**
2. Click **"New Purchase Order"**
3. Select supplier from registered vendors
4. Select album and condition grade
5. System calculates unit cost using condition multipliers:
   | Condition | Cost Multiplier |
   |-----------|-----------------|
   | New | 100% of base cost |
   | Mint | 95% |
   | NM | 85% |
   | VG+ | 70% |
   | VG | 55% |
6. Set sale price and quantity
7. View expected profit margin preview
8. Submit order (status: Pending)

**Order Receipt (Warehouse Staff):**
1. View pending orders in **Staff → Fulfillment → Procurement**
2. Confirm shipment receipt
3. `sp_receive_supplier_order` procedure:
   - Updates order status to 'Received'
   - Generates StockItem records for each unit
   - Assigns batch number (BATCH-YYYYMMDD-ID format)
   - Sets SourceType = 'Supplier' with polymorphic FK

### 3.4.4 User Management

**Employee Management:**
1. Navigate to **Admin → Users**
2. View all employees across locations
3. Add new employees: Name, Role, Shop, Username
4. Edit permissions or deactivate accounts

**Customer Management:**
- View customer accounts and membership tiers
- Manually adjust points if needed
- Access customer order history

### 3.4.5 Request Approval

The admin request page (`admin/requests.php`) provides an accordion-style approval interface:

**Request Dashboard:**
- Statistics cards: Pending / Approved / Rejected / Total
- Filter by status with URL parameters
- Expandable request cards with full details

**Processing Price Adjustment Requests:**
1. Expand request card to view details
2. Review current vs. requested price
3. Add response note (optional)
4. Click **Approve** or **Reject**
5. On approval: `sp_respond_to_request` updates all matching StockItems

**Processing Transfer Requests:**
1. Expand request card
2. System loads real-time inventory from other shops via AJAX
3. `/api/admin/requests.php` returns available stock counts
4. Select source shop with sufficient inventory
5. On approval:
   - Validates source shop has enough stock
   - Creates InventoryTransfer records
   - Stock items marked 'InTransit'

**Validation Features:**
- Source shop stock count validated before approval
- Cannot approve if insufficient inventory
- Boundary check: "Source shop only has X available items"

### 3.4.6 Warehouse Dispatch

**Managing Inter-Store Transfers:**
1. Navigate to **Admin → Warehouse Dispatch**
2. View approved transfer requests
3. Pick items from warehouse inventory
4. Click **"Dispatch"** to mark as InTransit
5. Destination shop receives and confirms

---

*Next section: Technical Annex for IT Department*
