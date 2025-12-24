# Retro Echo Records 数据库重构指南

## 📋 重构概述

本次重构针对Assignment 2的要求，全面优化了数据库设计和业务流程，主要改进包括：

### 🎯 核心改进

1. **PurchaseOrder表拆分** - 将单一的PurchaseOrder表拆分为SupplierOrder和BuybackOrder两个独立表
2. **库存汇总视图** - 创建vw_inventory_summary视图实现库存数量的快速查询
3. **存储过程封装** - 将所有业务流程封装为存储过程，确保事务一致性
4. **触发器保障** - 实现自动化数据一致性维护
5. **性能优化** - 添加全面的索引优化查询性能
6. **登录问题修复** - 解决跳转到XAMPP dashboard的问题

---

## 🗂 新增文件说明

### 数据库文件

| 文件名 | 说明 | 用途 |
|--------|------|------|
| `sql/schema_refactored.sql` | 重构后的数据库架构 | 替代原schema.sql |
| `sql/views_refactored.sql` | 重构后的视图定义 | 包含库存汇总等新视图 |
| `sql/procedures.sql` | 存储过程集合 | 封装所有业务流程 |
| `sql/triggers.sql` | 触发器集合 | 自动维护数据一致性 |
| `sql/indexes.sql` | 索引优化 | 提升查询性能 |
| `diagram_refactored.txt` | 重构后的ER图 | PlantUML源码 |

### 配置文件

| 文件名 | 说明 |
|--------|------|
| `public/.htaccess` | Apache配置 |
| `config/db_connect.php` | BASE_URL修复 |

---

## 📊 数据库架构变更详解

### 1. PurchaseOrder拆分

#### 🔴 原设计问题：
```sql
CREATE TABLE PurchaseOrder (
    PO_ID INT PRIMARY KEY,
    SupplierID INT,              -- Nullable
    BuybackCustomerID INT,        -- Nullable
    SourceType ENUM('Supplier', 'Buyback'),
    ...
);
```

**问题：**
- 字段冗余（SupplierID和BuybackCustomerID只有一个有值）
- 业务逻辑混淆
- 查询复杂度高
- 难以维护约束

#### ✅ 新设计方案：

```sql
-- 供应商订单表
CREATE TABLE SupplierOrder (
    SupplierOrderID INT PRIMARY KEY,
    SupplierID INT NOT NULL,
    CreatedByEmployeeID INT NOT NULL,
    DestinationShopID INT,
    OrderDate DATETIME,
    Status ENUM('Pending', 'Received', 'Cancelled'),
    ReceivedDate DATETIME,
    TotalCost DECIMAL(10,2)
);

-- 回购订单表
CREATE TABLE BuybackOrder (
    BuybackOrderID INT PRIMARY KEY,
    CustomerID INT NOT NULL,
    ProcessedByEmployeeID INT NOT NULL,
    ShopID INT NOT NULL,
    BuybackDate DATETIME,
    Status ENUM('Pending', 'Completed', 'Cancelled'),
    TotalPayment DECIMAL(10,2),
    Notes TEXT
);
```

**优势：**
✓ 清晰的语义分离
✓ 更强的数据完整性约束
✓ 简化查询逻辑
✓ 便于扩展不同的业务属性

### 2. 库存管理改进

#### StockItem表调整

```sql
CREATE TABLE StockItem (
    StockItemID INT PRIMARY KEY,
    ReleaseID INT NOT NULL,
    ShopID INT NOT NULL,
    SourceType ENUM('Supplier', 'Buyback'),  -- 来源类型
    SourceOrderID INT,                        -- 对应订单ID
    BatchNo VARCHAR(50),
    ConditionGrade ENUM('New','Mint','NM','VG+','VG'),
    Status ENUM('Available', 'Sold', 'Reserved', 'InTransit'),
    UnitPrice DECIMAL(10,2),
    AcquiredDate DATETIME,
    DateSold DATETIME
);
```

#### 库存汇总视图

```sql
CREATE VIEW vw_inventory_summary AS
SELECT
    ShopID,
    ShopName,
    ReleaseID,
    Title,
    ConditionGrade,
    COUNT(*) AS AvailableQuantity,  -- 关键：聚合单品数量
    MIN(UnitPrice) AS MinPrice,
    MAX(UnitPrice) AS MaxPrice,
    AVG(UnitPrice) AS AvgPrice
FROM StockItem s
JOIN Shop sh ON s.ShopID = sh.ShopID
JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
WHERE s.Status = 'Available'
GROUP BY ShopID, ReleaseID, ConditionGrade;
```

**用途：**
- 快速查询某专辑在某店的库存数量
- 在业务流程中检查库存是否充足
- 库存不足时可以直接ROLLBACK事务

---

## 🔄 存储过程详解

### 1. 供应商进货流程

```sql
-- 步骤1：创建订单
CALL sp_create_supplier_order(supplier_id, employee_id, shop_id, @order_id);

-- 步骤2：添加订单行
CALL sp_add_supplier_order_line(@order_id, release_id, quantity, unit_cost);

-- 步骤3：接收订单并生成库存
CALL sp_receive_supplier_order(@order_id, 'BATCH-2025-001', 'New', 0.50);
```

**事务保障：**
- 任何步骤失败都会自动回滚
- 确保库存和订单数据一致性

### 2. 客户回购流程

```sql
CALL sp_process_buyback(
    customer_id,
    employee_id,
    shop_id,
    release_id,
    quantity,
    buyback_price,  -- 支付给客户的价格
    'VG+',
    resale_price,   -- 转售价格
    @buyback_id
);
```

**自动化操作：**
- 创建回购订单
- 生成批次号
- 创建库存记录
- 计算总支付金额

### 3. 库存调拨流程

```sql
-- 发起调拨
CALL sp_initiate_transfer(stock_item_id, from_shop, to_shop, employee_id, @transfer_id);

-- 完成调拨
CALL sp_complete_transfer(@transfer_id, receiver_employee_id);
```

**并发控制：**
- 使用`FOR UPDATE`锁定行
- 防止同时调拨同一库存项

### 4. 销售流程

```sql
-- 创建订单
CALL sp_create_customer_order(customer_id, shop_id, employee_id, 'InStore', @order_id);

-- 添加商品
CALL sp_add_order_item(@order_id, stock_item_id, sale_price);

-- 完成订单
CALL sp_complete_order(@order_id, points_earned);

-- 或取消订单
CALL sp_cancel_order(@order_id);
```

**业务规则：**
- 添加商品时自动预留库存（Reserved）
- 完成订单时标记为Sold并更新DateSold
- 取消订单时自动释放库存

---

## ⚡ 触发器说明

### 1. 订单完成时自动更新积分和等级

```sql
CREATE TRIGGER trg_after_order_complete
AFTER UPDATE ON CustomerOrder
FOR EACH ROW
BEGIN
    IF NEW.OrderStatus = 'Completed' AND OLD.OrderStatus != 'Completed' THEN
        -- 每消费1元得1积分
        UPDATE Customer SET Points = Points + FLOOR(NEW.TotalAmount);

        -- 自动升级会员等级
        UPDATE Customer SET TierID = (
            SELECT TierID FROM MembershipTier
            WHERE Points >= MinPoints
            ORDER BY MinPoints DESC LIMIT 1
        );
    END IF;
END;
```

### 2. 订单行变更时自动更新订单总额

```sql
CREATE TRIGGER trg_after_order_line_insert
AFTER INSERT ON OrderLine
FOR EACH ROW
BEGIN
    UPDATE CustomerOrder
    SET TotalAmount = (SELECT SUM(PriceAtSale) FROM OrderLine WHERE OrderID = NEW.OrderID)
    WHERE OrderID = NEW.OrderID;
END;
```

### 3. 防止修改已完成订单

```sql
CREATE TRIGGER trg_before_order_line_update
BEFORE UPDATE ON OrderLine
FOR EACH ROW
BEGIN
    IF (SELECT OrderStatus FROM CustomerOrder WHERE OrderID = OLD.OrderID) IN ('Completed', 'Shipped') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot modify completed orders';
    END IF;
END;
```

### 4. 生日月份额外积分

```sql
CREATE TRIGGER trg_birthday_bonus
AFTER UPDATE ON CustomerOrder
FOR EACH ROW
BEGIN
    IF MONTH(Customer.Birthday) = MONTH(NEW.OrderDate) THEN
        -- 生日月份额外20%积分
        UPDATE Customer SET Points = Points + FLOOR(NEW.TotalAmount * 0.2);
    END IF;
END;
```

---

## 🔍 索引优化策略

### 核心索引

```sql
-- 库存查询核心索引
CREATE INDEX idx_stock_release_shop_status
ON StockItem(ReleaseID, ShopID, Status);

-- 订单查询优化
CREATE INDEX idx_order_customer_status
ON CustomerOrder(CustomerID, OrderStatus);

-- 库存汇总性能优化
CREATE INDEX idx_stock_shop_status
ON StockItem(ShopID, Status);
```

### 组合索引遵循最左前缀原则

✅ 可以使用idx_stock_release_shop_status的查询：
- `WHERE ReleaseID = 1`
- `WHERE ReleaseID = 1 AND ShopID = 2`
- `WHERE ReleaseID = 1 AND ShopID = 2 AND Status = 'Available'`

❌ 不能使用该索引：
- `WHERE ShopID = 2`
- `WHERE Status = 'Available'`

---

## 🚀 部署步骤

### 方案A：全新部署（推荐用于测试环境）

```bash
# 1. 备份现有数据库
mysqldump -u root -p retro_echo > backup_$(date +%Y%m%d).sql

# 2. 删除旧数据库
mysql -u root -p -e "DROP DATABASE IF EXISTS retro_echo;"

# 3. 创建新数据库
mysql -u root -p -e "CREATE DATABASE retro_echo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. 导入重构后的架构
mysql -u root -p retro_echo < sql/schema_refactored.sql

# 5. 创建视图
mysql -u root -p retro_echo < sql/views_refactored.sql

# 6. 创建存储过程
mysql -u root -p retro_echo < sql/procedures.sql

# 7. 创建触发器
mysql -u root -p retro_echo < sql/triggers.sql

# 8. 添加索引
mysql -u root -p retro_echo < sql/indexes.sql

# 9. 导入测试数据（如果需要）
mysql -u root -p retro_echo < sql/seeds.sql
```

### 方案B：数据迁移（用于生产环境）

```sql
-- 1. 创建新表
SOURCE sql/schema_refactored.sql;

-- 2. 迁移PurchaseOrder数据到新表
INSERT INTO SupplierOrder (...)
SELECT ... FROM PurchaseOrder WHERE SourceType = 'Supplier';

INSERT INTO BuybackOrder (...)
SELECT ... FROM PurchaseOrder WHERE SourceType = 'Buyback';

-- 3. 更新StockItem的外键引用
UPDATE StockItem SET SourceOrderID = (...)
WHERE SourceType = 'Supplier';

-- 4. 验证数据完整性
SELECT COUNT(*) FROM PurchaseOrder;
SELECT COUNT(*) FROM SupplierOrder + COUNT(*) FROM BuybackOrder;

-- 5. 删除旧表
DROP TABLE PurchaseOrderLine;
DROP TABLE PurchaseOrder;

-- 6. 创建视图、存储过程、触发器、索引
SOURCE sql/views_refactored.sql;
SOURCE sql/procedures.sql;
SOURCE sql/triggers.sql;
SOURCE sql/indexes.sql;
```

---

## 🐛 常见问题解决

### 问题1：登录后跳转到XAMPP dashboard

**原因：** BASE_URL配置不正确导致相对路径跳转失败

**解决：**
1. 确保`public/.htaccess`文件存在
2. 检查`config/db_connect.php`中的BASE_URL定义
3. 确认Apache已启用mod_rewrite

### 问题2：存储过程执行失败

**原因：** DELIMITER设置问题

**解决：**
```sql
-- 在MySQL命令行中执行
DELIMITER $$
SOURCE sql/procedures.sql$$
DELIMITER ;
```

或使用MySQL Workbench的SQL Script执行功能。

### 问题3：触发器未生效

**检查方法：**
```sql
-- 查看所有触发器
SHOW TRIGGERS FROM retro_echo;

-- 删除并重新创建
DROP TRIGGER IF EXISTS trg_after_order_complete;
SOURCE sql/triggers.sql;
```

### 问题4：库存汇总视图返回空结果

**原因：** StockItem表数据状态不正确

**检查：**
```sql
SELECT Status, COUNT(*)
FROM StockItem
GROUP BY Status;

-- 确保有Status='Available'的记录
```

---

## ✅ 功能验证清单

### 数据库层面

- [ ] 所有表创建成功
- [ ] 所有视图可以查询
- [ ] 所有存储过程可以调用
- [ ] 所有触发器已生效
- [ ] 索引已创建

### 业务流程

- [ ] 供应商进货流程完整
- [ ] 客户回购流程正常
- [ ] 库存调拨功能正常
- [ ] 销售流程（店内+在线）正常
- [ ] 积分和等级自动更新
- [ ] 库存状态正确维护

### 前端功能

- [ ] 登录跳转正确
- [ ] 各角色页面可访问
- [ ] 库存查询显示正确
- [ ] 订单创建和查看正常

---

## 📚 Assignment 2 要求对照

### ✅ 数据库要求

| 要求 | 实现 | 文件 |
|------|------|------|
| Views视图 | 15+个视图，包含权限控制视图 | views_refactored.sql |
| 至少3个高级SQL查询 | 已实现5个复杂查询 | advanced.sql |
| 存储过程 | 10+个业务流程存储过程 | procedures.sql |
| 索引 | 30+个性能优化索引 | indexes.sql |
| 事务控制 | 所有存储过程内含事务 | procedures.sql |
| CRUDS功能 | 完整实现 | PHP代码 |

### ✅ 视图权限控制

- `vw_customer_*` - 客户只能查看目录和自己的订单
- `vw_staff_*` - 员工查看本店库存和待处理任务
- `vw_manager_*` - 经理查看绩效和调拨
- `vw_admin_*` - 管理员全局管理视图

### ✅ 高级功能

- **事务控制**：所有业务流程都在存储过程中使用START TRANSACTION
- **并发处理**：使用FOR UPDATE锁定关键数据
- **数据一致性**：触发器自动维护
- **性能优化**：全面的索引策略

---

## 🎓 技术亮点（用于报告）

1. **架构优化**：PurchaseOrder拆分体现了数据库范式和业务分离原则
2. **事务完整性**：所有业务流程都有完整的ACID保障
3. **自动化**：触发器实现了积分、等级、总额的自动计算
4. **性能**：组合索引优化了核心业务查询
5. **安全性**：视图权限控制确保数据访问安全
6. **可维护性**：清晰的存储过程封装使代码易于维护

---

## 📝 下一步行动

1. **代码更新**：修改PHP代码以使用新的表和存储过程
2. **UI优化**：美化前端界面
3. **测试**：全面测试所有业务流程
4. **文档**：编写用户手册和技术文档
5. **部署**：部署到AWS服务器

---

## 📞 联系支持

如有问题，请查看：
- 项目文档：`/docs`
- ER图：`diagram_refactored.txt`
- SQL文件：`/sql`目录

Happy Coding! 🎉
