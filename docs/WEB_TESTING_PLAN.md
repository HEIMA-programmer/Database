# Retro Echo Records 唱片零售系统 - Web界面测试方案

> 版本: 2.0
> 日期: 2025-12-30
> 适用系统: Retro Echo Records 唱片零售管理系统
> 测试主线角色: Alice Fan (VIP会员)

---

## 目录

1. [测试概述](#1-测试概述)
2. [初始测试数据说明](#2-初始测试数据说明)
3. [测试账号信息](#3-测试账号信息)
4. [Alice Fan 主线测试流程](#4-alice-fan-主线测试流程)
5. [客户端功能测试](#5-客户端功能测试) → [详见 WEB_TESTING_PLAN_CUSTOMER.md](WEB_TESTING_PLAN_CUSTOMER.md)
6. [员工端功能测试](#6-员工端功能测试) → [详见 WEB_TESTING_PLAN_STAFF.md](WEB_TESTING_PLAN_STAFF.md)
7. [经理端功能测试](#7-经理端功能测试) → [详见 WEB_TESTING_PLAN_MANAGER.md](WEB_TESTING_PLAN_MANAGER.md)
8. [管理员端功能测试](#8-管理员端功能测试) → [详见 WEB_TESTING_PLAN_ADMIN.md](WEB_TESTING_PLAN_ADMIN.md)
9. [高级测试场景](#9-高级测试场景) → [详见 WEB_TESTING_PLAN_ADVANCED.md](WEB_TESTING_PLAN_ADVANCED.md)

---

## 1. 测试概述

### 1.1 测试目标

通过Web界面测试确保：
- 所有业务流程能够完整无误地执行
- 多用户之间的数据协同正确
- 数据一致性设计（价格一致性、库存状态、积分计算等）生效
- UI交互符合预期
- Dashboard统计数据实时更新正确
- 所有按钮功能正常工作

### 1.2 测试环境要求

- 浏览器：Chrome/Firefox/Safari 最新版本
- 建议使用多个浏览器窗口（或隐身模式）模拟多用户并发操作
- 数据库已执行 `seeds.sql` 初始化测试数据

### 1.3 用户角色概览

| 角色 | 访问入口 | 主要功能 |
|------|---------|---------|
| Customer | `/public/login.php` | 浏览商品、购物、下单、会员管理 |
| Staff | `/public/employee_login.php` | POS销售、回购、发货、库存管理 |
| Manager | `/public/employee_login.php` | Dashboard统计、订单管理、申请管理、业务报表 |
| Admin | `/public/employee_login.php` | 全局管理、采购、审批、员工管理 |

### 1.4 定价公式说明

```
售价 = BaseUnitCost × Condition系数 × 利润率

Condition系数：
- New = 1.00
- Mint = 0.95
- NM = 0.85
- VG+ = 0.70
- VG = 0.55

利润率：
- 成本 ≤ ¥20: ×1.50 (50%利润)
- 成本 ¥21-50: ×1.60 (60%利润)
- 成本 ¥51-100: ×1.70 (70%利润)
- 成本 > ¥100: ×1.80 (80%利润)
```

### 1.5 成本与营业额计算机制

> **重要更新：** 以下计算规则已在最近版本中更新，请注意区分。

#### 店铺总成本（Total Cost）计算

Dashboard显示的店铺成本为**历史成本总和**，包含：
- ✅ 当前可用库存（Available）的采购成本
- ✅ 已售出库存（Sold）的历史采购成本
- ✅ 调货后成本跟随库存转移到目标店铺

**成本来源：**
| SourceType | 成本取值字段 |
|------------|-------------|
| Supplier（供应商采购） | SupplierOrderLine.UnitCost |
| Buyback（回购入库） | BuybackOrderLine.UnitPrice |

**显示标签：**
- 小框标题：`Total Cost`（非Inventory Cost）
- 描述文字：`Historical cost`（非Real-time cost）
- 数量显示：`items (incl. sold)`（包含已售库存）

#### 营业额（Revenue）计算

只统计**已确认收入**的订单：
- ✅ OrderStatus = `Paid`
- ✅ OrderStatus = `Completed`
- ❌ 排除 `Pending`、`Shipped`、`Cancelled` 等未确认订单

---

## 2. 初始测试数据说明

### 2.1 店铺信息

| ShopID | 店铺名称 | 类型 | 地址 | 说明 |
|--------|---------|------|------|------|
| 1 | Changsha Flagship Store | Retail | 123 Vinyl St, Changsha | 长沙旗舰店，可POS销售、回购、自提 |
| 2 | Shanghai Branch | Retail | 456 Groove Ave, Shanghai | 上海分店，可POS销售、回购、自提 |
| 3 | Online Warehouse | Warehouse | No. 8 Logistics Park, Changsha | 线上仓库，仅支持运输，无POS/回购 |

### 2.2 会员等级

| TierID | 等级名称 | 最低积分 | 折扣率 |
|--------|---------|---------|--------|
| 1 | Standard | 0 | 0% |
| 2 | VIP | 1000 | 5% |
| 3 | Gold | 5000 | 10% |

### 2.3 客户初始数据

| CustomerID | 姓名 | 邮箱 | 等级 | 当前积分 | 生日 | 已完成订单数 | 历史消费 |
|------------|------|------|------|---------|------|-------------|----------|
| 1 | **Alice Fan** | alice@test.com | VIP | 1500 | 1995-05-20 | 3笔 | ¥147.20 |
| 2 | Bob Collector | bob@test.com | Gold | 6200 | 1988-12-15 | 3笔 | ¥164.16 |
| 3 | Charlie New | charlie@test.com | Standard | 150 | 2000-01-01 | 1笔 | ¥72.00 |
| 4 | Diana Vinyl | diana@test.com | VIP | 2300 | 1992-12-23 | 1笔 | ¥47.04 |
| 5 | Edward Rock | edward@test.com | Standard | 450 | 1985-07-04 | 0笔 | ¥0 |

**积分规则说明：**
- 购物完成：每消费¥1得1积分
- 回购：每回购¥1得0.5积分
- 生日月份：额外+20%积分
- 积分达到阈值自动升级会员等级

### 2.4 员工初始数据

| EmployeeID | 姓名 | 用户名 | 角色 | 所属店铺 |
|------------|------|--------|------|---------|
| 1 | Super Admin | admin | Admin | 长沙店 |
| 2 | Changsha Manager | manager_cs | Manager | 长沙店 |
| 3 | Changsha Staff | staff_cs | Staff | 长沙店 |
| 4 | Shanghai Manager | manager_sh | Manager | 上海店 |
| 5 | Shanghai Staff | staff_sh | Staff | 上海店 |
| 6 | Warehouse Manager | manager_wh | Manager | 仓库 |
| 7 | Warehouse Packer | staff_wh | Staff | 仓库 |

### 2.5 专辑目录（15张）及定价速查表

| ReleaseID | 专辑名称 | 艺术家 | 基础成本 | New | Mint | NM | VG+ | VG |
|-----------|---------|--------|---------|-----|------|-----|-----|-----|
| 1 | Abbey Road | The Beatles | ¥35.00 | ¥56.00 | ¥53.20 | ¥47.60 | ¥39.20 | ¥28.88 |
| 2 | The Dark Side of the Moon | Pink Floyd | ¥40.00 | ¥64.00 | ¥60.80 | ¥54.40 | ¥44.80 | ¥35.20 |
| 3 | Thriller | Michael Jackson | ¥25.00 | ¥40.00 | ¥38.00 | ¥34.00 | ¥26.25 | ¥20.63 |
| 4 | Kind of Blue | Miles Davis | ¥45.00 | ¥72.00 | ¥68.40 | ¥61.20 | ¥50.40 | ¥39.60 |
| 5 | Back in Black | AC/DC | ¥30.00 | ¥48.00 | ¥45.60 | ¥40.80 | ¥33.60 | ¥24.75 |
| 6 | Rumours | Fleetwood Mac | ¥32.00 | ¥51.20 | ¥48.64 | ¥43.52 | ¥35.84 | ¥26.40 |
| 7 | Led Zeppelin IV | Led Zeppelin | ¥38.00 | ¥60.80 | ¥57.76 | ¥51.68 | ¥42.56 | ¥33.44 |
| 8 | The Wall | Pink Floyd | ¥42.00 | ¥67.20 | ¥63.84 | ¥57.12 | ¥47.04 | ¥36.96 |
| 9 | A Night at the Opera | Queen | ¥36.00 | ¥57.60 | ¥54.72 | ¥48.96 | ¥40.32 | ¥29.70 |
| 10 | Hotel California | Eagles | ¥28.00 | ¥44.80 | ¥42.56 | ¥38.08 | ¥29.40 | ¥23.10 |
| 11 | Born to Run | Bruce Springsteen | ¥26.00 | ¥41.60 | ¥39.52 | ¥35.36 | ¥27.30 | ¥21.45 |
| 12 | Blue | Joni Mitchell | ¥22.00 | ¥35.20 | ¥33.44 | ¥29.92 | ¥24.64 | ¥18.15 |
| 13 | What is Going On | Marvin Gaye | ¥20.00 | ¥30.00 | ¥28.50 | ¥25.50 | ¥21.00 | ¥16.50 |
| 14 | Purple Rain | Prince | ¥24.00 | ¥38.40 | ¥36.48 | ¥32.64 | ¥26.88 | ¥19.80 |
| 15 | Nevermind | Nirvana | ¥18.00 | ¥27.00 | ¥25.65 | ¥22.95 | ¥18.90 | ¥14.85 |

### 2.6 库存初始分布

#### 长沙店库存（ShopID=1）- 共13件（8件供应商+1件调拨+3件回购），2件已售

| StockItemID | 专辑 | 成色 | 售价 | 状态 | 备注 |
|-------------|------|------|------|------|------|
| 1 | Abbey Road | New | ¥56.00 | Available | |
| 2 | Abbey Road | New | ¥56.00 | Available | |
| 3 | Abbey Road | Mint | ¥53.20 | Available | |
| 4 | The Dark Side of the Moon | New | ¥64.00 | Available | |
| 5 | The Dark Side of the Moon | New | ¥64.00 | Available | |
| 6 | The Dark Side of the Moon | Mint | ¥60.80 | Available | |
| 7 | The Dark Side of the Moon | VG+ | ¥44.80 | Available | |
| 8 | Abbey Road | New | ¥56.00 | **Sold** | Alice订单1 |
| 9 | The Dark Side of the Moon | Mint | ¥60.80 | **Sold** | Bob订单2 |
| 10 | Abbey Road | VG+ | ¥39.20 | Available | |
| 26 | Rumours | New | ¥51.20 | Available | 从仓库调拨 |
| 56 | A Night at the Opera | VG+ | ¥40.32 | Available | Bob回购 |
| 57 | A Night at the Opera | VG+ | ¥40.32 | Available | Bob回购 |
| 58 | A Night at the Opera | VG+ | ¥40.32 | Available | Bob回购 |

**可用库存统计：**
- Abbey Road: 3件 (2×New, 1×Mint, 1×VG+)
- Dark Side: 4件 (2×New, 1×Mint, 1×VG+)
- Rumours: 1件 (1×New，从仓库调拨)
- A Night at the Opera: 3件 (3×VG+，Bob回购)

#### 上海店库存（ShopID=2）- 共16件（15件供应商+1件调拨），3件已售

| StockItemID | 专辑 | 成色 | 售价 | 状态 | 备注 |
|-------------|------|------|------|------|------|
| 11 | Thriller | New | ¥40.00 | Available | |
| 12 | Thriller | New | ¥40.00 | Available | |
| 13 | Thriller | New | ¥40.00 | **Sold** | Alice订单3 |
| 14 | Kind of Blue | New | ¥72.00 | Available | |
| 15 | Kind of Blue | New | ¥72.00 | **Sold** | Charlie订单4 |
| 16 | Kind of Blue | NM | ¥61.20 | Available | |
| 17 | Back in Black | Mint | ¥45.60 | Available | |
| 18 | Back in Black | Mint | ¥45.60 | **Sold** | Bob订单5 |
| 19 | Back in Black | VG+ | ¥33.60 | Available | |
| 20 | Thriller | VG | ¥20.63 | Available | |
| 21 | Kind of Blue | VG | ¥39.60 | Available | |
| 22 | Back in Black | New | ¥48.00 | Available | |
| 23 | Thriller | Mint | ¥38.00 | Available | |
| 24 | Kind of Blue | Mint | ¥68.40 | Available | |
| 25 | Back in Black | NM | ¥40.80 | Available | |
| 29 | Led Zeppelin IV | New | ¥60.80 | Available | 从仓库调拨 |

#### 仓库库存（ShopID=3）- 共30件，3件已售，2件已调拨

**B20251210-WH批次（实际仓库库存）：**

| StockItemID | 专辑 | 成色 | 售价 | 状态 | 备注 |
|-------------|------|------|------|------|------|
| 26-已调拨 | Rumours | New | ¥51.20 | - | 已调拨至长沙店 |
| 27 | Rumours | New | ¥51.20 | Available | |
| 28 | Rumours | New | ¥51.20 | **Sold** | Alice订单6 |
| 29-已调拨 | Led Zeppelin IV | New | ¥60.80 | - | 已调拨至上海店 |
| 30 | Led Zeppelin IV | New | ¥60.80 | Available | |
| 31 | Led Zeppelin IV | Mint | ¥57.76 | **Sold** | Bob订单7 |
| 32 | The Wall | New | ¥67.20 | Available | |
| 33 | The Wall | New | ¥67.20 | Available | |
| 34 | The Wall | VG+ | ¥47.04 | **Sold** | Diana订单8 |
| 35 | Rumours | VG | ¥26.40 | Available | |
| 36 | Led Zeppelin IV | VG | ¥33.44 | Available | |
| 37 | The Wall | NM | ¥57.12 | Available | |
| 38 | Rumours | Mint | ¥48.64 | Available | |
| 39 | Led Zeppelin IV | NM | ¥51.68 | Available | |
| 40 | The Wall | Mint | ¥63.84 | Available | |

**B20251218-WH批次：**

| StockItemID | 专辑 | 成色 | 售价 | 状态 |
|-------------|------|------|------|------|
| 41-42 | A Night at the Opera | New | ¥57.60 | Available |
| 43 | A Night at the Opera | Mint | ¥54.72 | Available |
| 44-45 | Hotel California | New | ¥44.80 | Available |
| 46 | Hotel California | VG+ | ¥29.40 | Available |
| 47-48 | Born to Run | New | ¥41.60 | Available |
| 49 | Born to Run | NM | ¥35.36 | Available |
| 50 | A Night at the Opera | VG | ¥29.70 | Available |
| 51 | Hotel California | Mint | ¥42.56 | Available |
| 52 | Born to Run | VG+ | ¥27.30 | Available |
| 53 | A Night at the Opera | NM | ¥48.96 | Available |
| 54 | Hotel California | NM | ¥38.08 | Available |
| 55 | Born to Run | Mint | ¥39.52 | Available |

> **注意：** 回购库存（ID 56-58）入库到长沙店，详见长沙店库存表格。

### 2.7 已完成订单历史

| OrderID | 客户 | 店铺 | 类型 | 金额 | 商品 | StockItemID | 时间(天前) |
|---------|------|------|------|------|------|-------------|-----------|
| 1 | **Alice** | 长沙店 | InStore | ¥56.00 | Abbey Road New | 8 | 60天 |
| 2 | Bob | 长沙店 | InStore | ¥60.80 | Dark Side Mint | 9 | 55天 |
| 3 | **Alice** | 上海店 | InStore | ¥40.00 | Thriller New | 13 | 45天 |
| 4 | Charlie | 上海店 | InStore | ¥72.00 | Kind of Blue New | 15 | 40天 |
| 5 | Bob | 上海店 | InStore | ¥45.60 | Back in Black Mint | 18 | 35天 |
| 6 | **Alice** | 仓库 | Online | ¥51.20 | Rumours New | 28 | 20天 |
| 7 | Bob | 仓库 | Online | ¥57.76 | Led Zeppelin IV Mint | 31 | 15天 |
| 8 | Diana | 仓库 | Online | ¥47.04 | The Wall VG+ | 34 | 10天 |

### 2.8 回购订单历史

| BuybackOrderID | 客户 | 店铺 | 专辑 | 数量 | 成色 | 回购单价 | 总支付 | 赠送积分 |
|----------------|------|------|------|------|------|---------|--------|---------|
| 1 | Bob | 长沙店 | A Night at the Opera | 3 | VG+ | ¥12.00 | ¥36.00 | 18积分 |

> **注意：** 回购只能在零售店（长沙店、上海店）进行，仓库不支持回购功能。回购入库的库存ID为56-58，入库到长沙店。

### 2.9 库存调拨历史

| TransferID | 库存ID | 专辑 | 从 | 到 | 状态 | 时间(天前) |
|------------|--------|------|----|----|------|-----------|
| 1 | 26 (Rumours New ¥51.20) | Rumours | 仓库 | 长沙店 | Completed | 25天 |
| 2 | 29 (Led Zeppelin IV New ¥60.80) | Led Zeppelin IV | 仓库 | 上海店 | Completed | 20天 |

---

## 3. 测试账号信息

### 3.1 客户账号

所有密码均为: `password123`

| 邮箱 | 姓名 | 等级 | 积分 | 测试用途 |
|------|------|------|------|---------|
| **alice@test.com** | **Alice Fan** | VIP | 1500 | **主线测试角色**：VIP折扣(5%)、升级到Gold |
| bob@test.com | Bob Collector | Gold | 6200 | 测试Gold折扣(10%)、高积分用户、回购历史 |
| charlie@test.com | Charlie New | Standard | 150 | 测试普通用户、升级到VIP |
| diana@test.com | Diana Vinyl | VIP | 2300 | 测试12月生日加成 |
| edward@test.com | Edward Rock | Standard | 450 | 测试新用户、无订单历史 |

### 3.2 员工账号

所有密码均为: `password123`

| 用户名 | 角色 | 店铺 | 测试用途 |
|--------|------|------|---------|
| admin | Admin | 长沙店 | 全局管理、审批、采购 |
| manager_cs | Manager | 长沙店 | 长沙店Dashboard、申请管理 |
| manager_sh | Manager | 上海店 | 上海店Dashboard、申请管理 |
| manager_wh | Manager | 仓库 | 仓库Dashboard、申请管理 |
| staff_cs | Staff | 长沙店 | POS、回购、发货 |
| staff_sh | Staff | 上海店 | POS、回购、发货 |
| staff_wh | Staff | 仓库 | 仅发货（无POS/回购） |

---

## 4. Alice Fan 主线测试流程

> 本节以 Alice Fan 为中心，串联整个业务流程的测试。Alice 是 VIP 会员，有1500积分，历史消费¥147.20（3笔订单）。

### 4.1 Alice 当前状态验证

**测试路径:** `/public/customer/profile.php`

| 验证项 | 预期值 | 验证方法 |
|--------|--------|---------|
| 姓名 | Alice Fan | Profile页面显示 |
| 等级 | VIP | Profile页面显示 |
| 积分 | 1500 | Profile页面显示 |
| 折扣率 | 5% | Profile页面显示 |
| 距Gold积分 | 3500 (5000-1500) | 进度条显示 |

**历史订单验证：**

| OrderID | 日期 | 店铺 | 类型 | 金额 | 状态 |
|---------|------|------|------|------|------|
| 1 | 60天前 | 长沙店 | InStore | ¥56.00 | Completed |
| 3 | 45天前 | 上海店 | InStore | ¥40.00 | Completed |
| 6 | 20天前 | 仓库 | Online | ¥51.20 | Completed |

### 4.2 Alice 门店自提购物流程

**场景：** Alice 在长沙店购买 Abbey Road Mint (¥53.20)，选择自提

| 步骤 | 操作 | 预期结果 | 数据验证 |
|------|------|---------|---------|
| 1 | 登录 alice@test.com | 进入客户首页 | Session建立 |
| 2 | 进入Catalog，选择长沙店 | 显示长沙店库存 | 显示Abbey Road等 |
| 3 | 点击Abbey Road进入详情 | 显示可用库存 | 2×New(¥56), 1×Mint(¥53.20), 1×VG+(¥39.20) |
| 4 | 选择Mint成色，Add to Cart | 购物车+1 | Cart Session存储StockItemID=3 |
| 5 | 进入Cart页面 | 显示1件商品 | 小计¥53.20 |
| 6 | 点击Proceed to Checkout | 进入结账页 | 显示VIP折扣 |
| 7 | 验证折扣计算 | VIP 5%折扣 | 折扣¥2.66，应付¥50.54 |
| 8 | 选择Pickup（自提） | 运费¥0 | 总计¥50.54 |
| 9 | 点击Pay Now | 创建订单 | OrderStatus=Pending, StockItem.Status=Reserved |
| 10 | 确认支付 | 支付成功 | OrderStatus=Paid |

**员工处理自提：**

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|---------|
| 11 | staff_cs | 登录Staff界面 | 进入长沙店Staff |
| 12 | staff_cs | 访问Pickup Queue | 显示Alice的自提订单 |
| 13 | staff_cs | 点击Mark as Collected | 订单完成 |

**Alice 积分更新验证：**

| 验证项 | 操作前 | 操作后 | 计算说明 |
|--------|--------|--------|---------|
| 积分 | 1500 | 1550 | 1500 + floor(50.54) = 1550 |
| 等级 | VIP | VIP | 1550 < 5000，保持VIP |

### 4.3 Alice 线上配送购物流程

**场景：** Alice 在仓库购买 Hotel California New (¥44.80)，配送到家

| 步骤 | 操作 | 预期结果 | 数据验证 |
|------|------|---------|---------|
| 1 | 切换到仓库(Online Warehouse) | 购物车清空警告 | 显示清空提示 |
| 2 | 确认切换 | 显示仓库库存 | 购物车清空 |
| 3 | 找到Hotel California | 显示可用成色 | 2×New, 1×Mint, 1×NM, 1×VG+ |
| 4 | 选择New成色，Add to Cart | 购物车+1 | StockItemID=45或46 |
| 5 | 进入Checkout | 只显示Shipping选项 | 仓库不支持自提 |
| 6 | 输入配送地址 | 地址填写 | ShippingAddress字段 |
| 7 | 验证费用 | 商品¥44.80，折扣¥2.24，运费¥15 | 总计¥57.56 |
| 8 | 支付完成 | 订单Paid | 等待发货 |

**员工发货流程：**

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|---------|
| 9 | staff_wh | 登录仓库Staff | 进入仓库Staff界面 |
| 10 | staff_wh | 访问Fulfillment→Customer Orders | 显示Alice的订单 |
| 11 | staff_wh | 点击Ship | OrderStatus=Shipped |
| 12 | staff_wh | 点击Complete(确认送达) | OrderStatus=Completed |

**Alice 积分再次更新：**

| 验证项 | 操作前 | 操作后 | 计算说明 |
|--------|--------|--------|---------|
| 积分 | 1550 | 1607 | 1550 + floor(57.56) = 1607 |

### 4.4 Alice 积分累积升级到Gold

**场景：** Alice 需要再消费约¥3400才能升级到Gold(5000积分)

**快速升级测试（大额购买）：**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 在上海店添加多件高价商品 | 购物车金额达到¥3500+ |
| 2 | 完成支付 | 订单创建并支付 |
| 3 | 员工完成订单 | 订单Completed |
| 4 | **验证Alice等级** | **升级为Gold** |
| 5 | 再次购物 | 享受10%折扣 |

### 4.5 Alice 相关的员工端操作

**场景1：为Alice办理POS销售**

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|---------|
| 1 | staff_cs | 登录长沙店Staff | 进入POS |
| 2 | staff_cs | 搜索Abbey Road | 显示可用库存 |
| 3 | staff_cs | 添加1件New到购物车 | ¥56.00 |
| 4 | staff_cs | 选择客户"Alice Fan" | 显示VIP折扣5% |
| 5 | staff_cs | 完成销售 | 订单直接Completed |
| 6 | 验证 | Alice积分增加 | +53积分(56×0.95=53.2) |

**场景2：Alice参与回购**

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|---------|
| 1 | staff_sh | 登录上海店Staff | 进入Buyback |
| 2 | staff_sh | 选择客户Alice Fan | 选择成功 |
| 3 | staff_sh | 选择专辑Thriller，成色VG，数量2 | 表单填写 |
| 4 | staff_sh | 输入回购单价¥10 | 总支付¥20 |
| 5 | staff_sh | 提交回购 | 回购完成 |
| 6 | 验证 | Alice获得回购积分 | +10积分(20×0.5) |
| 7 | 验证 | 上海店新增库存 | 2件Thriller VG |

### 4.6 Alice 相关的Manager视角

**场景：长沙店Manager查看Alice消费数据**

| 步骤 | 角色 | 操作 | 预期结果 |
|------|------|------|---------|
| 1 | manager_cs | 登录长沙店Manager | 进入Dashboard |
| 2 | manager_cs | 查看Top Spenders表格 | Alice应在列表中 |
| 3 | manager_cs | 点击Alice的Details | 显示Alice在长沙店的订单 |
| 4 | 验证 | 订单1(¥56.00 Abbey Road) | 60天前InStore |

---

## 5-9. 详细测试用例

由于测试内容较多，各角色的详细测试用例已拆分到以下文件：

- **[WEB_TESTING_PLAN_CUSTOMER.md](WEB_TESTING_PLAN_CUSTOMER.md)** - 客户端功能测试
- **[WEB_TESTING_PLAN_STAFF.md](WEB_TESTING_PLAN_STAFF.md)** - 员工端功能测试
- **[WEB_TESTING_PLAN_MANAGER.md](WEB_TESTING_PLAN_MANAGER.md)** - 经理端功能测试（含Dashboard四框测试）
- **[WEB_TESTING_PLAN_ADMIN.md](WEB_TESTING_PLAN_ADMIN.md)** - 管理员端功能测试
- **[WEB_TESTING_PLAN_ADVANCED.md](WEB_TESTING_PLAN_ADVANCED.md)** - 高级测试场景（多用户协同、数据一致性等）

---

## 附录：快速检查清单

### A. Alice Fan 主线检查

- [ ] Profile显示正确（VIP/1500积分/5%折扣）
- [ ] 历史订单显示3笔（¥56+¥40+¥51.20）
- [ ] 门店自提流程完整（VIP折扣正确应用）
- [ ] 线上配送流程完整（仓库不可自提）
- [ ] 积分累积正确
- [ ] 升级到Gold后折扣变为10%
- [ ] POS销售绑定Alice成功
- [ ] 回购时Alice获得积分

### B. 数据一致性检查

- [ ] 所有Abbey Road New售价一致（¥56.00）
- [ ] 所有Thriller New售价一致（¥40.00）
- [ ] 库存状态流转正确（Available→Reserved→Sold）
- [ ] 调拨后库存ShopID更新
- [ ] Dashboard库存成本实时更新

### C. 角色权限检查

- [ ] Customer无法访问Staff页面
- [ ] Staff无法访问Manager/Admin页面
- [ ] 仓库Staff无法访问POS/Buyback
- [ ] Manager只能看到本店数据

---

**文档结束**

> 本测试方案以Alice Fan为主线，覆盖系统的所有核心业务流程。测试时请按照Alice主线流程顺序执行，确保数据状态正确。详细的各角色测试用例请参阅对应的子文件。
