# 🧪 Retro Echo Records - 完整测试方案

**项目：** DI31003 Assignment 2 - Database Implementation
**版本：** v2.0.0
**测试日期：** 2025-12-24
**状态：** 准备就绪

---

## 📋 目录

1. [测试环境准备](#1-测试环境准备)
2. [数据库层测试](#2-数据库层测试)
3. [功能测试](#3-功能测试)
4. [用户角色测试](#4-用户角色测试)
5. [业务流程测试](#5-业务流程测试)
6. [性能测试](#6-性能测试)
7. [安全测试](#7-安全测试)
8. [Assignment 2要求验证](#8-assignment-2要求验证)
9. [问题汇报清单](#9-问题汇报清单)

---

## 1. 测试环境准备

### 1.1 测试账号清单

| 角色 | 用户名 | 密码 | 店铺 | 用途 |
|------|--------|------|------|------|
| Admin | admin | admin123 | HQ | 全局管理 |
| Manager | manager | manager123 | Retail Shop | 门店管理 |
| Staff | staff | staff123 | Retail Shop | 店员操作 |
| Customer | customer | customer123 | - | 在线购物 |

### 1.2 测试数据准备

**执行顺序：**
```bash
# 1. 部署数据库架构
mysql -u root -p retro_echo < sql/schema.sql

# 2. 创建视图
mysql -u root -p retro_echo < sql/views.sql

# 3. 创建存储过程
mysql -u root -p retro_echo < sql/procedures.sql

# 4. 创建触发器
mysql -u root -p retro_echo < sql/triggers.sql

# 5. 添加索引
mysql -u root -p retro_echo < sql/indexes.sql

# 6. 导入测试数据
mysql -u root -p retro_echo < sql/seeds.sql
```

### 1.3 测试工具

- **浏览器：** Chrome / Firefox（最新版）
- **数据库工具：** MySQL Workbench
- **API测试：** Postman（如需要）
- **截图工具：** 用于记录测试结果

---

## 2. 数据库层测试

### 2.1 Schema验证

**测试目标：** 验证所有表、字段、约束是否正确创建

**测试步骤：**

```sql
-- 检查所有表是否存在
SHOW TABLES;

-- 预期结果：应包含以下表
-- Shop, Employee, MembershipTier, Customer, ReleaseAlbum, Track, Supplier
-- SupplierOrder, SupplierOrderLine, BuybackOrder, BuybackOrderLine
-- StockItem, InventoryTransfer, CustomerOrder, OrderLine

-- 检查关键表结构
DESCRIBE SupplierOrder;
DESCRIBE BuybackOrder;
DESCRIBE StockItem;

-- 验证外键约束
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'retro_echo'
  AND REFERENCED_TABLE_NAME IS NOT NULL;
```

**验收标准：**
- ✅ 所有15个表都存在
- ✅ StockItem包含 `SourceType` 和 `SourceOrderID` 字段
- ✅ 外键约束完整

---

### 2.2 Views验证

**测试目标：** 验证所有视图正确创建且可查询

**测试步骤：**

```sql
-- 1. 检查所有视图
SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- 预期结果：应包含17个视图
-- vw_inventory_summary, vw_low_stock_alert
-- vw_customer_catalog, vw_customer_order_history, vw_customer_my_orders_list, vw_customer_profile_info
-- vw_staff_pos_lookup, vw_staff_bopis_pending
-- vw_manager_shop_performance, vw_manager_pending_transfers
-- vw_admin_release_list, vw_admin_employee_list, vw_admin_supplier_orders, vw_buyback_orders

-- 2. 测试核心视图查询
SELECT * FROM vw_inventory_summary LIMIT 5;
SELECT * FROM vw_low_stock_alert LIMIT 5;
SELECT * FROM vw_customer_catalog LIMIT 10;
SELECT * FROM vw_admin_supplier_orders LIMIT 5;
SELECT * FROM vw_buyback_orders LIMIT 5;

-- 3. 验证视图权限隔离
-- Customer只能看到Warehouse库存
SELECT DISTINCT ShopName FROM vw_customer_catalog;
-- 预期结果：只有 'Warehouse' 或类似名称
```

**验收标准：**
- ✅ 所有15+个视图都存在
- ✅ 视图查询返回正确数据
- ✅ Customer视图只显示Warehouse库存

---

### 2.3 存储过程验证

**测试目标：** 验证所有存储过程可正常调用

**测试步骤：**

```sql
-- 1. 查看所有存储过程
SHOW PROCEDURE STATUS WHERE Db = 'retro_echo';

-- 预期结果：至少包含10个存储过程
-- sp_create_supplier_order, sp_add_supplier_order_line, sp_receive_supplier_order
-- sp_process_buyback
-- sp_initiate_transfer, sp_complete_transfer
-- sp_create_customer_order, sp_add_order_item, sp_complete_order, sp_cancel_order

-- 2. 测试供应商进货流程
CALL sp_create_supplier_order(1, 1, 1, @order_id);
SELECT @order_id AS NewOrderID;

CALL sp_add_supplier_order_line(@order_id, 1, 10, 45.00);

-- 查看订单详情
SELECT * FROM SupplierOrder WHERE SupplierOrderID = @order_id;
SELECT * FROM SupplierOrderLine WHERE SupplierOrderID = @order_id;

-- 3. 测试接收订单（生成库存）
CALL sp_receive_supplier_order(@order_id, 'TEST-BATCH-001', 'New', 1.5);

-- 验证库存是否生成
SELECT COUNT(*) FROM StockItem WHERE SourceType = 'Supplier' AND SourceOrderID = @order_id;
-- 预期结果：应该有10条记录

-- 4. 测试回购流程
CALL sp_process_buyback(1, 1, 1, 2, 2, 30.00, 'VG+', 55.00, @buyback_id);
SELECT @buyback_id AS BuybackOrderID;

-- 验证回购订单
SELECT * FROM BuybackOrder WHERE BuybackOrderID = @buyback_id;
SELECT COUNT(*) FROM StockItem WHERE SourceType = 'Buyback' AND SourceOrderID = @buyback_id;
-- 预期结果：应该有2条库存记录
```

**验收标准：**
- ✅ 所有存储过程调用成功
- ✅ 供应商订单流程完整
- ✅ 回购流程自动生成库存
- ✅ 事务一致性（失败时自动回滚）

---

### 2.4 触发器验证

**测试目标：** 验证触发器自动维护数据一致性

**测试步骤：**

```sql
-- 1. 查看所有触发器
SHOW TRIGGERS FROM retro_echo;

-- 预期结果：至少包含20个触发器

-- 2. 测试订单总额自动计算
-- 创建测试订单
INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderStatus, OrderType)
VALUES (1, 1, 'Pending', 'InStore');
SET @test_order_id = LAST_INSERT_ID();

-- 添加订单行
INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES (@test_order_id, 1, 59.99);
INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES (@test_order_id, 2, 45.00);

-- 检查TotalAmount是否自动计算
SELECT OrderID, TotalAmount FROM CustomerOrder WHERE OrderID = @test_order_id;
-- 预期结果：TotalAmount = 104.99

-- 3. 测试积分自动累积
UPDATE CustomerOrder SET OrderStatus = 'Completed' WHERE OrderID = @test_order_id;

-- 检查客户积分是否增加
SELECT Points FROM Customer WHERE CustomerID = 1;
-- 预期结果：积分应该增加了 FLOOR(104.99) = 104 分

-- 4. 测试供应商订单总成本自动计算
-- (使用之前创建的订单)
SELECT TotalCost FROM SupplierOrder WHERE SupplierOrderID = @order_id;
-- 预期结果：TotalCost = 10 * 45.00 = 450.00

-- 清理测试数据
DELETE FROM CustomerOrder WHERE OrderID = @test_order_id;
```

**验收标准：**
- ✅ 订单总额自动计算正确
- ✅ 积分自动累积
- ✅ 会员等级自动升级（如达到门槛）
- ✅ 供应商订单/回购订单成本自动计算

---

### 2.5 索引验证

**测试目标：** 验证索引提升查询性能

**测试步骤：**

```sql
-- 1. 查看所有索引
SELECT
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'retro_echo'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 2. 测试关键查询的性能
-- 查询某店铺某专辑的可用库存
EXPLAIN SELECT * FROM StockItem
WHERE ShopID = 1 AND ReleaseID = 1 AND Status = 'Available';
-- 预期结果：Extra列应显示 "Using index"

-- 查询客户订单历史
EXPLAIN SELECT * FROM CustomerOrder
WHERE CustomerID = 1 AND OrderStatus = 'Completed';
-- 预期结果：应使用索引

-- 3. 性能对比测试
SET profiling = 1;

-- 测试有索引的查询速度
SELECT * FROM StockItem WHERE ShopID = 1 AND Status = 'Available';

SHOW PROFILES;
-- 记录查询时间
```

**验收标准：**
- ✅ 核心表都有适当索引
- ✅ 组合索引遵循最左前缀原则
- ✅ EXPLAIN显示使用了索引

---

### 2.6 高级SQL查询验证

**测试目标：** 验证至少3个高级SQL查询（满足Assignment 2要求）

**测试步骤：**

```sql
-- 查看 advanced.sql 文件中的查询
SOURCE sql/advanced.sql;

-- 或者手动执行每个查询并验证结果
-- 例如：
-- 1. 使用窗口函数的销售排名查询
-- 2. 使用公用表表达式(CTE)的库存分析
-- 3. 使用复杂JOIN和子查询的业绩报表
-- 4. 使用聚合函数和分组的统计查询
-- 5. 使用CASE WHEN的条件统计
```

**验收标准：**
- ✅ 至少有3个高级SQL查询
- ✅ 使用了课程提到的高级技术（窗口函数、CTE、复杂JOIN等）
- ✅ 查询返回有意义的业务数据

---

## 3. 功能测试

### 3.1 登录功能测试

**测试场景：**

| 测试编号 | 测试场景 | 操作步骤 | 预期结果 |
|---------|---------|---------|---------|
| LOGIN-01 | Admin登录 | 1. 访问login.php<br>2. 输入admin/admin123<br>3. 点击登录 | 跳转到 /admin/products.php |
| LOGIN-02 | Manager登录 | 输入manager/manager123 | 跳转到 /manager/dashboard.php |
| LOGIN-03 | Staff登录 | 输入staff/staff123 | 跳转到 /staff/pos.php |
| LOGIN-04 | Customer登录 | 1. 访问 /customer/catalog.php<br>2. 点击登录<br>3. 输入customer/customer123 | 跳转到 /customer/catalog.php |
| LOGIN-05 | 错误密码 | 输入admin/wrongpassword | 显示错误提示 |
| LOGIN-06 | 不存在用户 | 输入nonexist/password | 显示错误提示 |
| LOGIN-07 | 登出功能 | 登录后点击Logout | 返回首页，session清除 |

**注意事项：**
- ⚠️ 确认不会跳转到XAMPP dashboard
- ⚠️ 检查店铺信息是否正确显示在导航栏

---

### 3.2 权限控制测试

**测试场景：**

| 测试编号 | 测试场景 | 操作步骤 | 预期结果 |
|---------|---------|---------|---------|
| AUTH-01 | 未登录访问受保护页面 | 直接访问 /admin/products.php | 跳转到登录页 |
| AUTH-02 | Customer访问Admin页面 | Customer登录后访问 /admin/products.php | 显示权限错误 |
| AUTH-03 | Staff访问Manager页面 | Staff登录后访问 /manager/dashboard.php | 显示权限错误 |
| AUTH-04 | 视图权限隔离 | Customer查询商品 | 只能看到Warehouse库存 |

---

## 4. 用户角色测试

### 4.1 Customer（顾客）功能测试

#### 4.1.1 浏览商品目录

**测试步骤：**
1. 访问 `/customer/catalog.php`
2. 浏览商品列表
3. 使用筛选功能（如有）
4. 点击商品查看详情

**验收标准：**
- ✅ 只显示Warehouse库存
- ✅ 只显示Available状态的商品
- ✅ 显示正确的价格和品相
- ✅ 图片和描述显示正常

#### 4.1.2 购物车功能

**测试步骤：**
1. 点击"Add to Cart"按钮
2. 查看购物车 `/customer/cart.php`
3. 修改数量（如支持）
4. 删除商品
5. 清空购物车

**验收标准：**
- ✅ 商品成功添加到购物车
- ✅ 购物车数量在导航栏显示
- ✅ 价格计算正确
- ✅ 应用会员折扣（如有）

#### 4.1.3 结账流程

**测试步骤：**
1. 购物车中有商品
2. 点击"Checkout"
3. 确认订单信息
4. 提交支付（模拟）
5. 查看订单详情

**验收标准：**
- ✅ 订单创建成功，获得OrderID
- ✅ 订单状态为 'Pending' 或 'Paid'
- ✅ 库存状态更新为 'Sold'
- ✅ 积分自动累积
- ✅ 购物车清空

**SQL验证：**
```sql
-- 检查订单
SELECT * FROM CustomerOrder WHERE CustomerID = 1 ORDER BY OrderDate DESC LIMIT 1;

-- 检查库存状态
SELECT StockItemID, Status FROM StockItem WHERE StockItemID IN (SELECT StockItemID FROM OrderLine WHERE OrderID = [订单ID]);

-- 检查积分
SELECT Points FROM Customer WHERE CustomerID = 1;
```

#### 4.1.4 订单历史

**测试步骤：**
1. 访问 `/customer/orders.php`
2. 查看历史订单列表
3. 点击查看订单详情

**验收标准：**
- ✅ 只显示当前客户的订单
- ✅ 显示订单状态、日期、总额
- ✅ 订单详情页显示商品明细

#### 4.1.5 会员资料

**测试步骤：**
1. 访问 `/customer/profile.php`
2. 查看当前积分和等级
3. 修改个人信息（如支持）

**验收标准：**
- ✅ 显示正确的积分和等级
- ✅ 显示折扣率
- ✅ 个人信息完整

---

### 4.2 Staff（店员）功能测试

#### 4.2.1 POS收银系统

**测试步骤：**
1. 访问 `/staff/pos.php`
2. 搜索商品（测试ID搜索和标题搜索）
   - 输入纯数字（如 `1001`）测试ID精确搜索
   - 输入文字（如 `Dark Side`）测试标题模糊搜索
3. 添加商品到POS购物车
4. 输入会员邮箱（可选）
5. 点击结账

**验收标准：**
- ✅ 搜索功能正常（ID和标题都能搜索）
- ✅ 只显示本店库存
- ✅ 只显示Available状态商品
- ✅ POS购物车正常工作
- ✅ 结账成功，订单状态为'Completed'
- ✅ 如输入会员邮箱，积分正确累积

**关键Bug检查：**
- ⚠️ **POS搜索PDO参数绑定错误已修复** - 确认修复有效

**SQL验证：**
```sql
-- 检查店内订单
SELECT * FROM CustomerOrder
WHERE OrderType = 'InStore' AND ProcessedByEmployeeID = [员工ID]
ORDER BY OrderDate DESC LIMIT 1;

-- 检查积分（如输入了会员邮箱）
SELECT Points FROM Customer WHERE Email = '[输入的邮箱]';
```

#### 4.2.2 回购处理

**测试步骤：**
1. 访问 `/staff/buyback.php`
2. 选择客户（或匿名）
3. 选择专辑（如果不存在，先在Admin中创建）
4. 选择品相
5. 输入回购价格和转售价格
6. 提交

**验收标准：**
- ✅ 回购订单创建成功
- ✅ 库存自动生成并添加到本店
- ✅ 批次号格式：`BUYBACK-[日期]-[订单ID]`
- ✅ 库存状态为'Available'

**⚠️ 严重问题检查：**
- **当前代码使用旧表名 `PurchaseOrder`，会报错！**
- 需要修复为 `BuybackOrder`

**SQL验证：**
```sql
-- 检查回购订单（修复后）
SELECT * FROM BuybackOrder ORDER BY BuybackDate DESC LIMIT 1;

-- 检查生成的库存
SELECT * FROM StockItem WHERE SourceType = 'Buyback'
ORDER BY AcquiredDate DESC LIMIT 5;
```

#### 4.2.3 在线订单发货 (Fulfillment)

**测试步骤：**
1. 访问 `/staff/fulfillment.php`
2. 查看待发货订单（状态为'Paid'）
3. 点击"Ship Order"
4. 验证订单状态更新为'Shipped'
5. 点击"Confirm Delivery"
6. 验证订单状态更新为'Completed'

**验收标准：**
- ✅ 待发货订单列表正确
- ✅ 发货后状态更新为'Shipped'
- ✅ 确认送达后状态更新为'Completed'
- ✅ 积分在订单Completed时自动累积（触发器）

**导航栏检查：**
- ⚠️ 确认Fulfillment链接在Staff导航栏中显示

#### 4.2.4 店内取货 (BOPIS)

**测试步骤：**
1. 访问 `/staff/pickup.php`（或类似页面）
2. 查看待取货订单
3. 确认取货

**验收标准：**
- ✅ 显示本店的待取货订单
- ✅ 确认取货后订单状态更新

#### 4.2.5 库存查询

**测试步骤：**
1. 使用视图查询本店库存
2. 查看库存详情

**验收标准：**
- ✅ 只能查看本店库存
- ✅ 数据准确

---

### 4.3 Manager（经理）功能测试

#### 4.3.1 Dashboard仪表盘

**测试步骤：**
1. 访问 `/manager/dashboard.php`
2. 查看关键指标（KPI）
3. 查看Top VIP客户
4. 查看滞销库存

**验收标准：**
- ✅ 显示正确的统计数据
- ✅ Top VIP排序正确
- ✅ 滞销库存时间标注正确

**⚠️ Bug检查：**
- **滞销库存标题显示">6 Months"但实际是60天** - 确认是否已修复

#### 4.3.2 库存调拨

**测试步骤：**
1. 访问 `/manager/transfer.php`
2. 发起调拨
   - 选择StockItemID
   - 选择目标店铺
3. 查看待处理调拨
4. 确认收货（如果角色支持）

**验收标准：**
- ✅ 调拨创建成功
- ✅ StockItem状态更新为'Reserved'
- ✅ InventoryTransfer记录创建
- ✅ 确认收货后StockItem的ShopID更新
- ✅ 状态恢复为'Available'

**SQL验证：**
```sql
-- 检查调拨记录
SELECT * FROM InventoryTransfer ORDER BY TransferDate DESC LIMIT 1;

-- 检查库存状态变化
SELECT StockItemID, ShopID, Status FROM StockItem
WHERE StockItemID = [调拨的商品ID];
```

#### 4.3.3 业务报表 (Reports)

**测试步骤：**
1. 访问 `/manager/reports.php`
2. 查看销售统计
3. 查看库存报表

**验收标准：**
- ✅ 报表数据准确
- ✅ 时间范围筛选正常（如有）

**⚠️ 导航栏检查：**
- 确认Reports链接在Manager导航栏中显示

---

### 4.4 Admin（管理员）功能测试

#### 4.4.1 产品管理

**测试步骤：**
1. 访问 `/admin/products.php`
2. 添加新专辑
3. 编辑专辑信息
4. 删除专辑（测试依赖检查）

**验收标准：**
- ✅ 创建专辑成功
- ✅ 编辑保存成功
- ✅ 如有关联库存，无法删除（显示提示）
- ✅ 无关联时可删除

#### 4.4.2 采购管理 (Procurement)

**测试步骤：**
1. 访问 `/admin/procurement.php`
2. 创建供应商订单
   - 选择供应商
   - 选择专辑
   - 输入数量和单价
3. 接收订单（收货）
4. 验证库存生成

**验收标准：**
- ✅ 订单创建成功
- ✅ 订单总成本自动计算（触发器）
- ✅ 收货后生成对应数量的StockItem
- ✅ 批次号格式：`BATCH-[日期]-[订单ID]`
- ✅ 定价策略：进价 × 1.5

**⚠️ 严重问题检查：**
- **当前代码使用旧表名 `PurchaseOrder`，会报错！**
- 需要修复为 `SupplierOrder`

**SQL验证：**
```sql
-- 检查供应商订单（修复后）
SELECT * FROM SupplierOrder ORDER BY OrderDate DESC LIMIT 1;

-- 检查订单明细
SELECT * FROM SupplierOrderLine WHERE SupplierOrderID = [订单ID];

-- 检查生成的库存
SELECT COUNT(*) FROM StockItem
WHERE SourceType = 'Supplier' AND SourceOrderID = [订单ID];
-- 预期结果：应等于订单数量
```

#### 4.4.3 供应商管理

**测试步骤：**
1. 访问 `/admin/suppliers.php`
2. 添加供应商
3. 编辑供应商
4. 删除供应商（测试依赖检查）

**验收标准：**
- ✅ 创建供应商成功
- ✅ 编辑保存成功
- ✅ 如有关联订单，无法删除
- ✅ 删除检查使用正确的表名

**⚠️ Bug检查：**
- **删除检查使用旧表名 `PurchaseOrder`** - 需修复为 `SupplierOrder`

#### 4.4.4 用户管理

**测试步骤：**
1. 访问 `/admin/users.php`
2. 添加员工
3. 编辑员工信息
4. 删除员工

**验收标准：**
- ✅ 员工管理功能正常
- ✅ 密码Hash存储
- ✅ 角色分配正确

---

## 5. 业务流程测试

### 5.1 完整的在线销售流程

**业务场景：** 客户从浏览到收货的完整流程

**测试步骤：**

1. **客户浏览和下单**
   ```
   Customer登录 → 浏览商品 → 添加到购物车 → 结账 → 订单创建（状态：Pending）
   ```

2. **支付处理（模拟）**
   ```
   订单状态更新为：Paid
   ```

3. **仓库发货**
   ```
   Staff登录 → Fulfillment页面 → 选择订单 → 点击Ship → 状态：Shipped
   ```

4. **确认送达**
   ```
   Staff → Fulfillment页面 → 选择已发货订单 → Confirm Delivery → 状态：Completed
   ```

5. **积分累积**
   ```
   检查Customer积分是否增加
   ```

**SQL验证：**
```sql
-- 追踪整个流程
SELECT
    co.OrderID,
    co.OrderDate,
    co.OrderStatus,
    co.TotalAmount,
    c.Name AS CustomerName,
    c.Points
FROM CustomerOrder co
JOIN Customer c ON co.CustomerID = c.CustomerID
WHERE co.OrderID = [测试订单ID];

-- 检查库存状态
SELECT StockItemID, Status, DateSold
FROM StockItem
WHERE StockItemID IN (SELECT StockItemID FROM OrderLine WHERE OrderID = [测试订单ID]);
```

**验收标准：**
- ✅ 订单状态流转正确：Pending → Paid → Shipped → Completed
- ✅ 库存状态正确：Available → Sold
- ✅ DateSold自动记录
- ✅ 积分在Completed时累积

---

### 5.2 完整的店内销售流程 (POS)

**业务场景：** 客户到店购买

**测试步骤：**

1. **Staff扫描/搜索商品**
   ```
   Staff登录 → POS页面 → 搜索商品ID或标题 → 添加到POS购物车
   ```

2. **会员识别（可选）**
   ```
   输入会员邮箱或留空
   ```

3. **结账**
   ```
   点击Checkout → 订单创建（OrderType: InStore, Status: Completed）
   ```

4. **验证结果**
   ```
   库存状态：Sold
   会员积分：自动增加
   ```

**SQL验证：**
```sql
-- 检查店内订单
SELECT * FROM CustomerOrder
WHERE OrderType = 'InStore'
ORDER BY OrderDate DESC LIMIT 1;

-- 如输入了会员邮箱，检查积分
SELECT Email, Points FROM Customer WHERE Email = '[输入的邮箱]';
```

**验收标准：**
- ✅ 订单直接创建为'Completed'状态
- ✅ 库存立即标记为'Sold'
- ✅ 积分实时累积
- ✅ 支持匿名购买（CustomerID为NULL）

---

### 5.3 供应商进货完整流程

**业务场景：** 从供应商采购商品到上架销售

**测试步骤：**

1. **Admin创建采购订单**
   ```
   Admin登录 → Procurement → Create PO → 选择供应商 → 添加商品 → 提交
   ```

2. **收货确认**
   ```
   Procurement → Receive PO → 输入批次号 → 确认收货
   ```

3. **验证库存生成**
   ```
   检查StockItem表，确认生成了对应数量的库存记录
   ```

4. **上架销售**
   ```
   Customer浏览商品 → 可以看到新进货的商品
   ```

**SQL验证：**
```sql
-- 检查供应商订单
SELECT so.*, s.Name AS SupplierName
FROM SupplierOrder so
JOIN Supplier s ON so.SupplierID = s.SupplierID
ORDER BY OrderDate DESC LIMIT 1;

-- 检查订单明细
SELECT sol.*, r.Title
FROM SupplierOrderLine sol
JOIN ReleaseAlbum r ON sol.ReleaseID = r.ReleaseID
WHERE SupplierOrderID = [订单ID];

-- 检查生成的库存
SELECT * FROM StockItem
WHERE SourceType = 'Supplier' AND SourceOrderID = [订单ID];
```

**验收标准：**
- ✅ 订单创建时状态为'Pending'
- ✅ 收货后状态更新为'Received'
- ✅ 生成的库存数量 = 订单数量
- ✅ 批次号正确
- ✅ 售价 = 进价 × 1.5
- ✅ 溯源信息完整（SourceType, SourceOrderID）

---

### 5.4 客户回购完整流程

**业务场景：** 客户出售二手唱片给店铺

**测试步骤：**

1. **客户到店**
   ```
   Staff登录 → Buyback页面
   ```

2. **评估唱片**
   ```
   选择客户（或匿名）→ 选择专辑 → 选择品相 → 输入回购价格和转售价格
   ```

3. **提交回购**
   ```
   点击提交 → 回购订单创建 → 库存自动生成
   ```

4. **验证结果**
   ```
   检查回购订单和库存记录
   ```

**SQL验证：**
```sql
-- 检查回购订单
SELECT bo.*, c.Name AS CustomerName
FROM BuybackOrder bo
LEFT JOIN Customer c ON bo.CustomerID = c.CustomerID
ORDER BY BuybackDate DESC LIMIT 1;

-- 检查生成的库存
SELECT * FROM StockItem
WHERE SourceType = 'Buyback' AND SourceOrderID = [回购订单ID];
```

**验收标准：**
- ✅ 回购订单创建成功
- ✅ 库存自动添加到当前店铺
- ✅ 批次号格式：`BUYBACK-[日期]-[订单ID]`
- ✅ 品相和转售价格正确
- ✅ 溯源信息完整
- ✅ 状态为'Available'，可立即销售

---

### 5.5 库存调拨完整流程

**业务场景：** 从一个店铺调拨商品到另一个店铺

**测试步骤：**

1. **Manager发起调拨**
   ```
   Manager登录 → Transfer页面 → 选择StockItemID → 选择目标店铺 → 提交
   ```

2. **验证调拨状态**
   ```
   StockItem状态：Reserved
   InventoryTransfer状态：Pending或InTransit
   ```

3. **目标店铺确认收货**
   ```
   Manager登录（目标店铺）→ 确认收货
   ```

4. **验证最终结果**
   ```
   StockItem的ShopID更新为目标店铺
   状态恢复为Available
   InventoryTransfer状态：Completed
   ```

**SQL验证：**
```sql
-- 检查调拨记录
SELECT it.*,
       s1.Name AS FromShop,
       s2.Name AS ToShop,
       si.Status AS CurrentStatus
FROM InventoryTransfer it
JOIN Shop s1 ON it.FromShopID = s1.ShopID
JOIN Shop s2 ON it.ToShopID = s2.ShopID
JOIN StockItem si ON it.StockItemID = si.StockItemID
ORDER BY TransferDate DESC LIMIT 1;

-- 检查库存变化
SELECT StockItemID, ShopID, Status
FROM StockItem
WHERE StockItemID = [调拨的商品ID];
```

**验收标准：**
- ✅ 调拨期间库存被锁定（Reserved）
- ✅ 完成后ShopID正确更新
- ✅ 状态正确流转
- ✅ 数据一致性保持

---

## 6. 性能测试

### 6.1 索引效果测试

**测试步骤：**

```sql
-- 1. 测试无索引的查询速度（假设场景）
SET profiling = 1;

-- 查询某店铺的可用库存
SELECT * FROM StockItem WHERE ShopID = 1 AND Status = 'Available';

SHOW PROFILES;
-- 记录查询时间

-- 2. 使用EXPLAIN分析查询计划
EXPLAIN SELECT * FROM StockItem
WHERE ShopID = 1 AND ReleaseID = 1 AND Status = 'Available';

-- 检查是否使用了索引
-- 预期结果：key列显示索引名称

-- 3. 测试视图查询性能
SELECT * FROM vw_inventory_summary WHERE ShopID = 1;

SHOW PROFILES;
```

**验收标准：**
- ✅ 核心查询使用了索引
- ✅ 查询时间在可接受范围（<100ms）
- ✅ EXPLAIN显示合理的查询计划

---

### 6.2 并发测试

**测试场景：** 两个用户同时购买同一商品

**测试步骤：**

1. 打开两个浏览器窗口
2. 分别登录两个Customer账号
3. 同时将同一商品加入购物车
4. 几乎同时点击结账

**预期结果：**
- ✅ 只有一个用户成功购买
- ✅ 另一个用户收到"商品已售出"错误
- ✅ 库存不会超卖
- ✅ 数据库使用行级锁（FOR UPDATE）防止并发

**SQL验证：**
```sql
-- 检查是否发生超卖
SELECT StockItemID, Status, COUNT(*)
FROM OrderLine
WHERE StockItemID = [测试商品ID]
GROUP BY StockItemID
HAVING COUNT(*) > 1;
-- 预期结果：空结果集
```

---

## 7. 安全测试

### 7.1 SQL注入测试

**测试场景：**

| 测试点 | 恶意输入 | 预期结果 |
|--------|---------|---------|
| 登录表单 | `admin' OR '1'='1` | 登录失败，无SQL错误 |
| 搜索功能 | `'; DROP TABLE StockItem--` | 搜索无结果，表未被删除 |
| POS搜索 | `1001' OR 1=1--` | 无SQL错误，安全过滤 |

**验收标准：**
- ✅ 所有查询使用prepared statements
- ✅ 参数绑定正确
- ✅ 无SQL注入漏洞

---

### 7.2 XSS测试

**测试步骤：**

1. 尝试在商品名称中插入脚本：
   ```
   <script>alert('XSS')</script>
   ```

2. 查看商品列表页面

**验收标准：**
- ✅ 脚本不执行
- ✅ 显示为纯文本
- ✅ 所有输出使用 `h()` 函数转义

---

### 7.3 密码安全测试

**测试步骤：**

```sql
-- 检查密码存储
SELECT Username, PasswordHash FROM Employee LIMIT 5;

-- 预期结果：PasswordHash应该是hash值，不是明文
```

**验收标准：**
- ✅ 密码使用 `password_hash()` 存储
- ✅ 使用 `password_verify()` 验证
- ✅ 数据库中无明文密码

---

## 8. Assignment 2要求验证

### 8.1 核心要求检查清单

| 要求项 | 验证方法 | 状态 |
|--------|---------|------|
| **Views** | `SHOW FULL TABLES WHERE Table_type = 'VIEW';` | ✅ 15+个视图 |
| **至少3个高级SQL** | 检查 `advanced.sql` | ✅ 5个复杂查询 |
| **存储过程** | `SHOW PROCEDURE STATUS WHERE Db = 'retro_echo';` | ✅ 10+个 |
| **触发器** | `SHOW TRIGGERS;` | ✅ 20+个 |
| **索引优化** | `SHOW INDEX FROM [表名];` | ✅ 30+个 |
| **CRUDS功能** | 测试增删改查和搜索 | ✅ 完整实现 |
| **基于视图的权限控制** | Customer只能查Warehouse库存 | ✅ 实现 |
| **事务控制** | 检查存储过程中的BEGIN/COMMIT | ✅ 完整 |

---

### 8.2 用户指南测试

**验证项目：**

- ✅ 每种用户角色都有清晰的功能说明
- ✅ 操作步骤配有截图
- ✅ 常见问题有解答
- ✅ 测试账号信息完整

---

### 8.3 技术文档测试

**验证项目：**

- ✅ 包含Schema设计说明
- ✅ 包含ER图
- ✅ 包含视图定义示例
- ✅ 包含存储过程示例
- ✅ 包含触发器示例
- ✅ 包含高级SQL查询示例
- ✅ 包含索引策略说明

---

## 9. 问题汇报清单

### 9.1 已知严重问题（需立即修复）

| 问题编号 | 问题描述 | 影响范围 | 优先级 |
|---------|---------|---------|--------|
| BUG-001 | procurement.php使用旧表名PurchaseOrder | 采购功能完全失效 | P0 🔴 |
| BUG-002 | buyback.php使用旧表名PurchaseOrder | 回购功能完全失效 | P0 🔴 |
| BUG-003 | suppliers.php删除检查使用旧表名 | 供应商删除检查失效 | P0 🔴 |
| BUG-004 | PHP代码使用SourcePO_ID字段，但schema为SourceOrderID | 库存溯源数据不一致 | P0 🔴 |
| BUG-005 | seeds.sql使用SourcePO_ID字段名 | 测试数据导入失败 | P0 🔴 |

---

### 9.2 中等优先级问题

| 问题编号 | 问题描述 | 建议 | 优先级 |
|---------|---------|------|--------|
| ISSUE-001 | PHP代码未使用存储过程 | 建议逐步迁移到存储过程 | P2 🟡 |
| ISSUE-002 | 在线结账直接设为Sold，跳过Reserved状态 | 如需严格流程，需修改 | P3 🟢 |

---

### 9.3 优化建议

| 建议编号 | 建议内容 | 收益 |
|---------|---------|------|
| OPT-001 | 添加数据库备份功能 | 提高数据安全性 |
| OPT-002 | 实现操作审计日志 | 便于追踪问题 |
| OPT-003 | 添加数据可视化图表 | 提升用户体验 |

---

## 10. 测试报告模板

### 测试执行记录表

| 测试日期 | 测试人员 | 测试内容 | 通过率 | 备注 |
|---------|---------|---------|--------|------|
| 2025-12-24 | [姓名] | 数据库层测试 | __/__ | |
| 2025-12-24 | [姓名] | Customer功能测试 | __/__ | |
| 2025-12-24 | [姓名] | Staff功能测试 | __/__ | |
| 2025-12-24 | [姓名] | Manager功能测试 | __/__ | |
| 2025-12-24 | [姓名] | Admin功能测试 | __/__ | |
| 2025-12-24 | [姓名] | 业务流程测试 | __/__ | |

---

### 缺陷记录表

| 缺陷ID | 发现日期 | 严重程度 | 问题描述 | 状态 | 修复日期 |
|--------|---------|---------|---------|------|---------|
| BUG-001 | 2025-12-24 | 严重 | procurement.php表名错误 | 待修复 | |
| BUG-002 | 2025-12-24 | 严重 | buyback.php表名错误 | 待修复 | |

---

## 11. 测试总结

### 测试完成后填写：

**整体评估：**
- 数据库设计质量：[优秀/良好/一般/需改进]
- 功能完整性：[完整/基本完整/部分缺失]
- 代码质量：[优秀/良好/一般/需改进]
- 用户体验：[优秀/良好/一般/需改进]

**主要优点：**
1.
2.
3.

**主要问题：**
1.
2.
3.

**改进建议：**
1.
2.
3.

---

## 附录：快速测试脚本

### A. 一键验证数据库结构

```sql
-- 保存为 validate_db.sql
SELECT 'Tables' AS Type, COUNT(*) AS Count FROM information_schema.tables WHERE table_schema = 'retro_echo'
UNION ALL
SELECT 'Views', COUNT(*) FROM information_schema.views WHERE table_schema = 'retro_echo'
UNION ALL
SELECT 'Procedures', COUNT(*) FROM information_schema.routines WHERE routine_schema = 'retro_echo' AND routine_type = 'PROCEDURE'
UNION ALL
SELECT 'Triggers', COUNT(*) FROM information_schema.triggers WHERE trigger_schema = 'retro_echo';

-- 预期结果：
-- Tables: 15
-- Views: 15+
-- Procedures: 10+
-- Triggers: 20+
```

### B. 检查关键视图

```sql
-- 保存为 test_views.sql
SELECT 'vw_inventory_summary' AS ViewName, COUNT(*) AS Records FROM vw_inventory_summary
UNION ALL
SELECT 'vw_customer_catalog', COUNT(*) FROM vw_customer_catalog
UNION ALL
SELECT 'vw_admin_supplier_orders', COUNT(*) FROM vw_admin_supplier_orders
UNION ALL
SELECT 'vw_buyback_orders', COUNT(*) FROM vw_buyback_orders;
```

### C. 验证触发器效果

```sql
-- 保存为 test_triggers.sql
-- 创建测试订单
START TRANSACTION;

INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderStatus, OrderType)
VALUES (1, 1, 'Pending', 'InStore');
SET @test_order = LAST_INSERT_ID();

-- 添加订单行
INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES (@test_order, 1, 59.99);

-- 检查TotalAmount是否自动计算
SELECT OrderID, TotalAmount FROM CustomerOrder WHERE OrderID = @test_order;
-- 应该显示 59.99

ROLLBACK; -- 清理测试数据
```

---

**测试方案版本：** v1.0
**最后更新：** 2025-12-24
**维护者：** Database Team

---

## ⚠️ 重要提醒

**在提交Assignment 2之前，请确保：**

1. ✅ 修复所有P0级别的严重bug
2. ✅ 完成所有核心功能测试
3. ✅ 验证Assignment 2的所有要求都已满足
4. ✅ 准备好测试账号和访问说明
5. ✅ 数据库在AWS上可访问
6. ✅ 准备好截图和演示材料

**祝测试顺利！🎉**
