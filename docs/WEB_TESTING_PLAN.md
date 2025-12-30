# Retro Echo Records 唱片零售系统 - Web界面测试方案

> 版本: 1.0
> 日期: 2025-12-30
> 适用系统: Retro Echo Records 唱片零售管理系统

---

## 目录

1. [测试概述](#1-测试概述)
2. [初始测试数据说明](#2-初始测试数据说明)
3. [测试账号信息](#3-测试账号信息)
4. [客户端功能测试](#4-客户端功能测试)
5. [员工端功能测试](#5-员工端功能测试)
6. [经理端功能测试](#6-经理端功能测试)
7. [管理员端功能测试](#7-管理员端功能测试)
8. [多用户协同测试](#8-多用户协同测试)
9. [数据一致性验证测试](#9-数据一致性验证测试)
10. [边界条件与异常测试](#10-边界条件与异常测试)

---

## 1. 测试概述

### 1.1 测试目标

通过Web界面测试确保：
- 所有业务流程能够完整无误地执行
- 多用户之间的数据协同正确
- 数据一致性设计（价格一致性、库存状态、积分计算等）生效
- UI交互符合预期

### 1.2 测试环境要求

- 浏览器：Chrome/Firefox/Safari 最新版本
- 建议使用多个浏览器窗口（或隐身模式）模拟多用户并发操作
- 数据库已执行 `seeds.sql` 初始化测试数据

### 1.3 用户角色概览

| 角色 | 访问入口 | 主要功能 |
|------|---------|---------|
| Customer | `/public/login.php` | 浏览商品、购物、下单、会员管理 |
| Staff | `/public/employee_login.php` | POS销售、回购、发货、库存管理 |
| Manager | `/public/employee_login.php` | 订单管理、申请管理、业务报表 |
| Admin | `/public/employee_login.php` | 全局管理、采购、审批 |

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

| CustomerID | 姓名 | 邮箱 | 等级 | 当前积分 | 生日 | 已完成订单数 |
|------------|------|------|------|---------|------|-------------|
| 1 | Alice Fan | alice@test.com | VIP | 1500 | 1995-05-20 | 3笔 |
| 2 | Bob Collector | bob@test.com | Gold | 6200 | 1988-12-15 | 3笔 |
| 3 | Charlie New | charlie@test.com | Standard | 150 | 2000-01-01 | 1笔 |
| 4 | Diana Vinyl | diana@test.com | VIP | 2300 | 1992-12-23 | 1笔 |
| 5 | Edward Rock | edward@test.com | Standard | 450 | 1985-07-04 | 0笔 |

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

### 2.5 专辑目录（15张）

| ReleaseID | 专辑名称 | 艺术家 | 流派 | 基础成本 |
|-----------|---------|--------|------|---------|
| 1 | Abbey Road | The Beatles | Rock | ¥35.00 |
| 2 | The Dark Side of the Moon | Pink Floyd | Progressive Rock | ¥40.00 |
| 3 | Thriller | Michael Jackson | Pop | ¥25.00 |
| 4 | Kind of Blue | Miles Davis | Jazz | ¥45.00 |
| 5 | Back in Black | AC/DC | Hard Rock | ¥30.00 |
| 6 | Rumours | Fleetwood Mac | Soft Rock | ¥32.00 |
| 7 | Led Zeppelin IV | Led Zeppelin | Hard Rock | ¥38.00 |
| 8 | The Wall | Pink Floyd | Progressive Rock | ¥42.00 |
| 9 | A Night at the Opera | Queen | Rock | ¥36.00 |
| 10 | Hotel California | Eagles | Rock | ¥28.00 |
| 11 | Born to Run | Bruce Springsteen | Rock | ¥26.00 |
| 12 | Blue | Joni Mitchell | Folk | ¥22.00 |
| 13 | What is Going On | Marvin Gaye | Soul | ¥20.00 |
| 14 | Purple Rain | Prince | Pop/Rock | ¥24.00 |
| 15 | Nevermind | Nirvana | Grunge | ¥18.00 |

### 2.6 库存初始分布

#### 长沙店库存（ShopID=1）- 共10件，2件已售

| StockItemID | 专辑 | 成色 | 售价 | 状态 |
|-------------|------|------|------|------|
| 1 | Abbey Road | New | ¥35.00 | Available |
| 2 | Abbey Road | New | ¥35.00 | Available |
| 3 | Abbey Road | Mint | ¥33.00 | Available |
| 4 | The Dark Side of the Moon | New | ¥42.00 | Available |
| 5 | The Dark Side of the Moon | New | ¥42.00 | Available |
| 6 | The Dark Side of the Moon | Mint | ¥40.00 | Available |
| 7 | The Dark Side of the Moon | VG+ | ¥35.00 | Available |
| 8 | Abbey Road | New | ¥35.00 | **Sold** |
| 9 | The Dark Side of the Moon | Mint | ¥42.00 | **Sold** |
| 10 | Abbey Road | VG+ | ¥28.00 | Available |

> **可用库存统计：** Abbey Road (3件: 2×New, 1×Mint, 1×VG+), Dark Side (4件: 2×New, 1×Mint, 1×VG+)

#### 上海店库存（ShopID=2）- 共15件，3件已售

| StockItemID | 专辑 | 成色 | 售价 | 状态 |
|-------------|------|------|------|------|
| 11 | Thriller | New | ¥25.00 | Available |
| 12 | Thriller | New | ¥25.00 | Available |
| 13 | Thriller | New | ¥25.00 | **Sold** |
| 14 | Kind of Blue | New | ¥30.00 | Available |
| 15 | Kind of Blue | New | ¥30.00 | **Sold** |
| 16 | Kind of Blue | NM | ¥28.00 | Available |
| 17 | Back in Black | Mint | ¥36.00 | Available |
| 18 | Back in Black | Mint | ¥36.00 | **Sold** |
| 19 | Back in Black | VG+ | ¥32.00 | Available |
| 20 | Thriller | VG | ¥20.00 | Available |
| 21 | Kind of Blue | VG | ¥25.00 | Available |
| 22 | Back in Black | New | ¥36.00 | Available |
| 23 | Thriller | Mint | ¥24.00 | Available |
| 24 | Kind of Blue | Mint | ¥29.00 | Available |
| 25 | Back in Black | NM | ¥34.00 | Available |

> **可用库存统计：** Thriller (4件), Kind of Blue (4件), Back in Black (4件)

#### 仓库库存（ShopID=3）- 共35件，3件已售，2件已调拨

| 批次 | 专辑 | 成色组合 | 状态概览 |
|------|------|---------|---------|
| B20251210-WH | Rumours | New×2(1售), Mint×1, VG×1 | 3件可用 |
| B20251210-WH | Led Zeppelin IV | New×2(1售), Mint×1, NM×1, VG×1 | 4件可用 |
| B20251210-WH | The Wall | New×2(1售), Mint×1, NM×1, VG+×1 | 4件可用 |
| B20251218-WH | A Night at the Opera | New×2, Mint×1, NM×1, VG×1 | 5件可用 |
| B20251218-WH | Hotel California | New×2, Mint×1, NM×1, VG+×1 | 5件可用 |
| B20251218-WH | Born to Run | New×2, Mint×1, NM×1, VG+×1 | 5件可用 |
| BUY-20251211 | A Night at the Opera (回购) | VG+×3, VG×2 | 5件可用 |

> **特殊说明：**
> - StockItemID 26 (Rumours New) 已调拨至长沙店
> - StockItemID 29 (Led Zeppelin NM) 已调拨至上海店

### 2.7 已完成订单历史

| OrderID | 客户 | 店铺 | 类型 | 金额 | 商品 | 时间(天前) |
|---------|------|------|------|------|------|-----------|
| 1 | Alice | 长沙店 | InStore | ¥35.00 | Abbey Road New | 60天 |
| 2 | Bob | 长沙店 | InStore | ¥42.00 | Dark Side Mint | 55天 |
| 3 | Alice | 上海店 | InStore | ¥25.00 | Thriller New | 45天 |
| 4 | Charlie | 上海店 | InStore | ¥30.00 | Kind of Blue New | 40天 |
| 5 | Bob | 上海店 | InStore | ¥36.00 | Back in Black Mint | 35天 |
| 6 | Alice | 仓库 | Online | ¥32.00 | Rumours New | 20天 |
| 7 | Bob | 仓库 | Online | ¥38.00 | Led Zeppelin New | 15天 |
| 8 | Diana | 仓库 | Online | ¥45.00 | The Wall New | 10天 |

### 2.8 回购订单历史

| BuybackOrderID | 客户 | 店铺 | 专辑 | 数量 | 回购单价 | 总支付 | 赠送积分 |
|----------------|------|------|------|------|---------|--------|---------|
| 1 | Bob | 仓库 | A Night at the Opera | 3 | ¥22.00 | ¥66.00 | 33积分 |

### 2.9 库存调拨历史

| TransferID | 库存ID | 从 | 到 | 状态 | 时间(天前) |
|------------|--------|----|----|------|-----------|
| 1 | 26 (Rumours New) | 仓库 | 长沙店 | Completed | 25天 |
| 2 | 29 (Led Zeppelin NM) | 仓库 | 上海店 | Completed | 20天 |

---

## 3. 测试账号信息

### 3.1 客户账号

所有密码均为: `password123`

| 邮箱 | 等级 | 积分 | 测试用途 |
|------|------|------|---------|
| alice@test.com | VIP | 1500 | 测试VIP折扣(5%)、升级到Gold |
| bob@test.com | Gold | 6200 | 测试Gold折扣(10%)、高积分用户 |
| charlie@test.com | Standard | 150 | 测试普通用户、升级到VIP |
| diana@test.com | VIP | 2300 | 测试12月生日加成 |
| edward@test.com | Standard | 450 | 测试新用户、升级场景 |

### 3.2 员工账号

所有密码均为: `password123`

| 用户名 | 角色 | 店铺 | 测试用途 |
|--------|------|------|---------|
| admin | Admin | 长沙店 | 全局管理、审批、采购 |
| manager_cs | Manager | 长沙店 | 长沙店申请管理 |
| manager_sh | Manager | 上海店 | 上海店申请管理 |
| manager_wh | Manager | 仓库 | 仓库申请管理 |
| staff_cs | Staff | 长沙店 | POS、回购、发货 |
| staff_sh | Staff | 上海店 | POS、回购、发货 |
| staff_wh | Staff | 仓库 | 仅发货（无POS/回购） |

---

## 4. 客户端功能测试

### 4.1 用户注册测试

**测试路径:** `/public/register.php`

#### 测试用例 4.1.1：成功注册新用户

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 访问注册页面 | 显示注册表单 |
| 2 | 填写：姓名=Test User, 邮箱=test@new.com, 密码=password123, 生日=1990-06-15 | 表单填写成功 |
| 3 | 点击注册 | 注册成功，跳转到登录页 |
| 4 | 使用新账号登录 | 登录成功，显示目录页 |

**数据验证：**
- 新客户等级默认为 Standard
- 初始积分为 0
- 可在"Profile"页面查看个人信息

#### 测试用例 4.1.2：邮箱重复注册

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 尝试注册已存在邮箱 alice@test.com | 显示错误：邮箱已存在 |

---

### 4.2 商品目录浏览测试

**测试路径:** `/public/customer/catalog.php`

#### 测试用例 4.2.1：店铺切换与库存显示

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 alice@test.com 登录 | 登录成功 |
| 2 | 访问目录页，默认选择仓库 | 显示仓库库存：Rumours, Led Zeppelin IV, The Wall, Opera, Hotel California, Born to Run |
| 3 | 切换到"Changsha Flagship Store" | 显示长沙店库存：Abbey Road, Dark Side of the Moon |
| 4 | 切换到"Shanghai Branch" | 显示上海店库存：Thriller, Kind of Blue, Back in Black |
| 5 | 点击某专辑卡片 | 进入专辑详情页，显示该店可用库存 |

**UI验证：**
- 有库存专辑：显示价格范围、成色标签、库存数量
- 无库存专辑：显示灰色，标记"无库存"
- 店铺提示：仓库显示"Online shipping only"，门店显示"Pick up in store or online shipping available"

#### 测试用例 4.2.2：搜索和筛选

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 在搜索框输入"Beatles" | 只显示 Abbey Road |
| 2 | 选择流派"Rock" | 只显示Rock类专辑 |
| 3 | 点击清除筛选 | 恢复显示所有专辑 |

---

### 4.3 购物车测试

**测试路径:** `/public/customer/cart.php`, `/public/customer/release.php`

#### 测试用例 4.3.1：添加商品到购物车

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 选择长沙店，进入 Abbey Road 详情页 | 显示可用库存：2×New(¥35), 1×Mint(¥33), 1×VG+(¥28) |
| 2 | 选择"New"成色，点击"Add to Cart" | 显示成功消息，购物车图标显示数量1 |
| 3 | 再次添加一件"Mint"成色 | 购物车数量变为2 |
| 4 | 进入购物车页面 | 显示2件商品，小计 ¥35+¥33=¥68 |

**数据验证：**
- 加入购物车的库存状态仍为 Available（未预留）
- 购物车数据存储在 Session 中

#### 测试用例 4.3.2：店铺切换清空购物车

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 购物车中有长沙店商品 | 购物车显示商品 |
| 2 | 切换到上海店 | 显示警告"You changed store location. Your cart has been cleared." |
| 3 | 查看购物车 | 购物车为空 |

---

### 4.4 结账流程测试

**测试路径:** `/public/customer/checkout.php`

#### 测试用例 4.4.1：门店订单 - 自提方式

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 选择长沙店，添加1件 Abbey Road New (¥35) 到购物车 | 购物车显示1件 |
| 2 | 进入结账页面 | 显示两个选项：In-Store Pickup / Home Delivery |
| 3 | 选择"In-Store Pickup" | 运费显示"Free"，总计¥35（VIP折扣¥1.75后=¥33.25）|
| 4 | 点击"Place Order" | 创建订单，跳转到支付页面 |

**数据验证：**
- 订单状态：Pending
- 履行方式：Pickup
- 运费：¥0
- 库存状态：Reserved（已预留）

#### 测试用例 4.4.2：门店订单 - 运输方式

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 添加商品到购物车 | 购物车有商品 |
| 2 | 结账时选择"Home Delivery" | 显示地址输入框，运费显示¥15 |
| 3 | 输入地址，点击"Place Order" | 创建订单，运费¥15计入总额 |

#### 测试用例 4.4.3：仓库订单 - 仅运输

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 选择仓库，添加商品到购物车 | 购物车显示仓库商品 |
| 2 | 进入结账页面 | 只显示"Home Delivery"选项（自提不可用）|
| 3 | 强制选择Pickup（如果可能） | 显示错误"Warehouse orders can only be shipped" |

---

### 4.5 支付流程测试

**测试路径:** `/public/customer/pay.php`

#### 测试用例 4.5.1：成功支付

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 创建订单后跳转到支付页 | 显示订单详情和支付按钮 |
| 2 | 点击"Confirm Payment" | 支付成功，订单状态变为Paid |
| 3 | 查看订单详情 | 显示"Paid"状态 |

**数据验证：**
- 订单状态：Pending → Paid
- 库存状态：保持 Reserved
- 触发器不会在此时赠送积分（等待订单完成）

#### 测试用例 4.5.2：30分钟超时未支付

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 创建订单但不支付 | 订单状态Pending |
| 2 | 等待30分钟（或手动触发定时事件） | 系统自动取消订单 |
| 3 | 查看订单状态 | 订单变为Cancelled，库存恢复Available |

---

### 4.6 订单管理测试

**测试路径:** `/public/customer/orders.php`, `/public/customer/order_detail.php`

#### 测试用例 4.6.1：查看订单历史

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 alice@test.com 登录 | 登录成功 |
| 2 | 访问"My Orders"页面 | 显示3个已完成订单 + 新建的订单 |
| 3 | 点击某订单查看详情 | 显示订单商品、金额、状态、履行方式 |

#### 测试用例 4.6.2：取消未支付订单

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 创建新订单（状态Pending） | 订单创建成功 |
| 2 | 在订单详情页点击"Cancel Order" | 订单状态变为Cancelled |
| 3 | 查看之前预留的库存 | 库存状态恢复为Available |

---

### 4.7 会员积分与折扣测试

#### 测试用例 4.7.1：VIP折扣验证

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 alice@test.com (VIP) 登录 | 登录成功 |
| 2 | 添加¥100商品到购物车 | 购物车显示¥100 |
| 3 | 进入结账页面 | 显示"VIP Discount (5%): -¥5.00"，总计¥95 |

#### 测试用例 4.7.2：Gold折扣验证

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 bob@test.com (Gold) 登录 | 登录成功 |
| 2 | 添加¥100商品到购物车 | 购物车显示¥100 |
| 3 | 进入结账页面 | 显示"Gold Discount (10%): -¥10.00"，总计¥90 |

#### 测试用例 4.7.3：积分累积与等级升级

**场景：Charlie (Standard, 150积分) 消费后升级到VIP**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 charlie@test.com 登录 | 显示当前积分150，等级Standard |
| 2 | 购买¥900商品并完成订单 | 订单完成 |
| 3 | 查看Profile页面 | 积分变为150+900=1050，等级自动升级为VIP |

**数据验证：**
- 触发器 `trg_after_order_complete` 自动增加积分
- 存储过程 `sp_update_customer_tier` 自动升级等级

---

### 4.8 个人资料管理测试

**测试路径:** `/public/customer/profile.php`

#### 测试用例 4.8.1：查看个人资料

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 登录后访问Profile页面 | 显示姓名、邮箱、等级、积分、生日 |

#### 测试用例 4.8.2：修改个人资料

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 修改姓名为"Alice Updated" | 保存成功 |
| 2 | 刷新页面 | 显示新姓名 |
| 3 | 修改密码 | 保存成功，下次登录使用新密码 |

---

## 5. 员工端功能测试

### 5.1 POS销售测试（仅门店）

**测试路径:** `/public/staff/pos.php`

#### 测试用例 5.1.1：门店POS销售 - 绑定会员

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_cs 登录 | 进入长沙店员工界面 |
| 2 | 访问POS页面 | 显示本店库存查询界面 |
| 3 | 搜索"Abbey Road" | 显示可用库存：New×2, Mint×1, VG+×1 |
| 4 | 选择1件New(¥35)加入购物车 | POS购物车显示1件，金额¥35 |
| 5 | 选择客户"Alice Fan" | 显示客户信息和VIP折扣 |
| 6 | 点击"Complete Sale" | 订单创建并直接完成 |

**数据验证：**
- 订单类型：InStore
- 订单状态：直接Completed（跳过Pending/Paid）
- 库存状态：直接变为Sold
- Alice积分：+35（假设无折扣情况）
- 如果有VIP折扣(5%)：订单金额¥33.25，积分+33

#### 测试用例 5.1.2：门店POS销售 - Walk-in顾客

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | POS页面不选择客户 | 客户字段为空/Walk-in |
| 2 | 添加商品并完成销售 | 订单完成，CustomerID为NULL |
| 3 | 验证积分 | 无积分赠送（没有关联客户） |

#### 测试用例 5.1.3：仓库员工无法访问POS

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_wh 登录 | 进入仓库员工界面 |
| 2 | 尝试访问 /staff/pos.php | 显示错误或重定向，POS功能不可用 |

---

### 5.2 回购功能测试（仅门店）

**测试路径:** `/public/staff/buyback.php`

#### 测试用例 5.2.1：回购绑定会员 - 积分赠送

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_cs 登录长沙店 | 进入员工界面 |
| 2 | 访问Buyback页面 | 显示回购表单 |
| 3 | 选择客户"Edward Rock" (450积分) | 客户选择成功 |
| 4 | 选择专辑"Abbey Road"，成色VG，数量2，回购单价¥15，转售价¥28 | 表单填写完成 |
| 5 | 点击"Process Buyback" | 回购成功，显示"Customer earned 15 points" |

**数据验证：**
- BuybackOrder创建，状态Completed
- 生成2件StockItem：Abbey Road VG，售价¥28，状态Available
- Edward积分：450 + (30×0.5) = 450 + 15 = 465积分
- 库存来源标记：SourceType=Buyback, SourceOrderID=新回购订单ID

#### 测试用例 5.2.2：回购Walk-in顾客

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 选择客户为"Walk-in (No points)" | 客户字段为空 |
| 2 | 完成回购 | 回购成功，无积分赠送 |
| 3 | 验证库存 | 新库存正常生成 |

#### 测试用例 5.2.3：回购价格一致性

**场景：回购已有库存价格的专辑**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 仓库已有 A Night at the Opera VG+ 库存，售价¥22 | 初始状态 |
| 2 | 回购同专辑VG+成色，输入转售价¥30 | 表单提交 |
| 3 | 验证新库存售价 | 系统自动使用现有价格¥22（保证价格一致性）|

#### 测试用例 5.2.4：仓库员工无法访问回购

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_wh 登录 | 仓库员工界面 |
| 2 | 尝试访问 /staff/buyback.php | 显示"Buyback is only available at retail locations"并重定向 |

---

### 5.3 订单发货测试

**测试路径:** `/public/staff/fulfillment.php`

#### 测试用例 5.3.1：处理运输订单

**前置条件：** 客户创建了一个选择Shipping的订单并已支付

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_wh 登录 | 仓库员工界面 |
| 2 | 访问Fulfillment页面"顾客订单"标签 | 显示Paid状态的订单 |
| 3 | 找到目标订单，点击"Ship" | 订单状态变为Shipped |
| 4 | 等待客户确认收货（或手动点击Complete） | 订单状态变为Completed |

**数据验证：**
- 订单状态流程：Paid → Shipped → Completed
- 库存状态：Reserved → Sold（在Complete时变更）
- 积分在Complete时赠送

#### 测试用例 5.3.2：员工只能看到本店订单

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_cs 登录长沙店 | 长沙店员工界面 |
| 2 | 查看Fulfillment页面 | 只显示 FulfilledByShopID=1 的订单 |
| 3 | 以 staff_wh 登录仓库 | 仓库员工界面 |
| 4 | 查看Fulfillment页面 | 只显示 FulfilledByShopID=3 的订单 |

---

### 5.4 自提处理测试

**测试路径:** `/public/staff/pickup.php`

#### 测试用例 5.4.1：处理自提订单

**前置条件：** 客户创建了选择Pickup的门店订单并已支付

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_cs 登录长沙店 | 长沙店员工界面 |
| 2 | 访问Pickup页面 | 显示ReadyForPickup状态的订单 |
| 3 | 点击"Mark as Collected" | 订单状态变为Completed |

**数据验证：**
- 订单状态：ReadyForPickup → Completed
- 库存状态：Reserved → Sold
- 客户积分增加

---

### 5.5 库存调拨测试

**测试路径:** `/public/staff/fulfillment.php`（待发货/待接收标签）

#### 测试用例 5.5.1：源店铺确认发货

**前置条件：** Admin已批准调货申请，生成了Pending状态的InventoryTransfer记录

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_wh 登录仓库 | 仓库员工界面 |
| 2 | 访问Fulfillment页面，点击"待发货"标签 | 显示待发货的调拨请求 |
| 3 | 点击"确认发货" | 调拨状态变为InTransit，库存状态变为InTransit |

**数据验证：**
- InventoryTransfer.Status: Pending → InTransit
- StockItem.Status: Available → InTransit

#### 测试用例 5.5.2：目标店铺确认收货

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_cs 登录长沙店（目标店铺） | 长沙店员工界面 |
| 2 | 访问Fulfillment页面，点击"待接收"标签 | 显示InTransit状态的调拨 |
| 3 | 点击"确认收货" | 调拨完成，库存转移到本店 |

**数据验证：**
- InventoryTransfer.Status: InTransit → Completed
- StockItem.Status: InTransit → Available
- StockItem.ShopID: 从源店铺ID变为目标店铺ID（触发器自动更新）

---

### 5.6 库存查询测试

**测试路径:** `/public/staff/inventory.php`

#### 测试用例 5.6.1：查看本店库存

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 staff_cs 登录 | 长沙店员工界面 |
| 2 | 访问Inventory页面 | 显示长沙店所有库存 |
| 3 | 筛选成色"New" | 只显示New成色库存 |
| 4 | 搜索"Abbey" | 只显示Abbey Road库存 |

---

## 6. 经理端功能测试

### 6.1 调价申请测试

**测试路径:** `/public/manager/requests.php`

#### 测试用例 6.1.1：提交调价申请

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 manager_cs 登录 | 长沙店经理界面 |
| 2 | 访问Requests页面，点击"New Price Request" | 显示调价申请表单 |
| 3 | 选择专辑"Abbey Road"，成色"New" | 自动填充当前价格¥35，数量2 |
| 4 | 输入目标价格¥30，理由"清仓促销" | 表单填写完成 |
| 5 | 点击"Submit Request" | 申请提交成功，显示在"Pending"列表 |

**数据验证：**
- ManagerRequest记录创建
- RequestType: PriceAdjustment
- Status: Pending
- FromShopID: 1（长沙店）

#### 测试用例 6.1.2：查看申请状态

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 访问Requests页面 | 显示左侧菜单：All/Pending/Approved/Rejected |
| 2 | 点击"Pending" | 显示待审批的申请 |
| 3 | 点击"Approved" | 显示已批准的申请 |

---

### 6.2 调货申请测试

#### 测试用例 6.2.1：提交调货申请

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 manager_cs 登录 | 长沙店经理界面 |
| 2 | 访问Requests页面，点击"New Transfer Request" | 显示调货申请表单 |
| 3 | 选择专辑"Rumours"，成色"New"，数量2 | 表单填写完成 |
| 4 | 输入理由"库存不足，需要补货" | 理由填写完成 |
| 5 | 点击"Submit Request" | 申请提交成功 |

**数据验证：**
- RequestType: TransferRequest
- FromShopID: 1（申请方=长沙店）
- ToShopID: NULL（由Admin决定源店铺）

---

### 6.3 订单管理测试

**测试路径:** `/public/manager/customer_orders.php`

#### 测试用例 6.3.1：查看本店订单

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 manager_cs 登录 | 长沙店经理界面 |
| 2 | 访问Customer Orders页面 | 显示长沙店所有订单 |
| 3 | 按状态筛选"Completed" | 只显示已完成订单 |
| 4 | 点击订单查看详情 | 显示订单商品、客户、金额等 |

---

### 6.4 业务报表测试

**测试路径:** `/public/manager/reports.php`

#### 测试用例 6.4.1：查看销售报表

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 manager_cs 登录 | 长沙店经理界面 |
| 2 | 访问Reports页面 | 显示销售统计、订单数量、平均客单价 |
| 3 | 查看流派分析 | 显示各流派销售额和占比 |
| 4 | 查看客户排行 | 显示消费最高的客户 |

---

## 7. 管理员端功能测试

### 7.1 申请审批测试

**测试路径:** `/public/admin/requests.php`

#### 测试用例 7.1.1：审批调价申请

**前置条件：** Manager已提交调价申请

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 admin 登录 | 管理员界面 |
| 2 | 访问Requests页面 | 显示待审批申请列表 |
| 3 | 展开调价申请详情 | 显示申请内容：专辑、成色、当前价格、目标价格 |
| 4 | 输入审批意见，点击"Approve" | 申请状态变为Approved |

**数据验证：**
- ManagerRequest.Status: Pending → Approved
- 对应StockItem.UnitPrice: 更新为目标价格
- 价格更新数量不超过申请的Quantity

#### 测试用例 7.1.2：审批调货申请

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 展开调货申请详情 | 显示申请内容和其他店铺库存情况 |
| 2 | 查看"Available Stock in Other Shops" | 实时显示各店铺可用库存 |
| 3 | 选择源店铺（如仓库） | 下拉选择源店铺 |
| 4 | 点击"Approve" | 申请批准，创建InventoryTransfer记录 |

**数据验证：**
- ManagerRequest.Status: Approved
- ManagerRequest.ToShopID: 设置为选择的源店铺ID
- 创建InventoryTransfer记录，Status=Pending
- 等待源店铺员工确认发货

#### 测试用例 7.1.3：拒绝申请

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 展开申请详情 | 显示申请内容 |
| 2 | 输入拒绝理由，点击"Reject" | 申请状态变为Rejected |

**数据验证：**
- ManagerRequest.Status: Rejected
- AdminResponseNote: 记录拒绝理由
- 无库存/价格变更

#### 测试用例 7.1.4：库存不足无法批准

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 提交调货申请：数量10 | 申请创建 |
| 2 | Admin查看申请 | 显示其他店铺库存不足10件 |
| 3 | 选择库存不足的店铺并批准 | 显示错误"Source shop only has X available items" |

---

### 7.2 采购管理测试

**测试路径:** `/public/admin/procurement.php`

#### 测试用例 7.2.1：创建采购订单

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 admin 登录 | 管理员界面 |
| 2 | 访问Procurement页面 | 显示采购订单管理界面 |
| 3 | 点击"Create New Order" | 显示创建表单 |
| 4 | 选择供应商"Sony Music CN"，目标店铺"长沙店" | 基本信息填写 |
| 5 | 添加订单行：Abbey Road, 数量5, 成本¥20, 成色New, 售价¥35 | 订单行添加成功 |
| 6 | 点击"Submit Order" | 订单创建，状态Pending |

**数据验证：**
- SupplierOrder创建，Status=Pending
- SupplierOrderLine记录创建
- TotalCost自动计算（触发器）

#### 测试用例 7.2.2：接收采购订单 - 库存生成

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 找到Pending状态的采购订单 | 显示订单详情 |
| 2 | 点击"Receive Order" | 订单接收处理 |
| 3 | 验证库存 | 生成5件新StockItem |

**数据验证：**
- SupplierOrder.Status: Pending → Received
- SupplierOrder.ReceivedDate: 设置为当前时间
- 生成5件StockItem：
  - ReleaseID=1 (Abbey Road)
  - ShopID=1 (长沙店)
  - ConditionGrade=New
  - UnitPrice=¥35
  - SourceType=Supplier
  - SourceOrderID=采购订单ID
  - Status=Available

#### 测试用例 7.2.3：采购时价格一致性更新

**场景：新采购价格与现有库存不同**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 长沙店现有 Abbey Road New 价格¥35 | 初始状态 |
| 2 | 创建采购订单：Abbey Road New，售价¥38 | 订单创建 |
| 3 | 接收订单 | 订单接收 |
| 4 | 验证现有库存价格 | 所有Abbey Road New的UnitPrice更新为¥38 |

**数据验证：**
- 存储过程 `sp_receive_supplier_order` 自动更新同Release+Condition的现有库存价格
- 保证价格一致性

---

### 7.3 产品管理测试

**测试路径:** `/public/admin/products.php`

#### 测试用例 7.3.1：添加新专辑

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 访问Products页面 | 显示专辑列表 |
| 2 | 点击"Add New Album" | 显示添加表单 |
| 3 | 填写：标题、艺术家、厂牌、年份、流派、描述 | 表单填写 |
| 4 | 点击保存 | 专辑创建成功 |

#### 测试用例 7.3.2：编辑专辑信息

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击某专辑的"Edit"按钮 | 显示编辑表单 |
| 2 | 修改标题或描述 | 修改完成 |
| 3 | 点击保存 | 更新成功 |

---

### 7.4 员工管理测试

**测试路径:** `/public/admin/users.php`

#### 测试用例 7.4.1：添加新员工

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 访问Users页面 | 显示员工列表 |
| 2 | 点击"Add Employee" | 显示添加表单 |
| 3 | 填写：姓名、用户名、密码、角色、店铺 | 表单填写 |
| 4 | 点击保存 | 员工创建成功 |
| 5 | 使用新账号登录 | 登录成功，权限正确 |

#### 测试用例 7.4.2：防止删除自己

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 以 admin 登录 | 管理员界面 |
| 2 | 尝试删除 admin 账号 | 显示错误"Cannot delete your own account" |

---

### 7.5 供应商管理测试

**测试路径:** `/public/admin/suppliers.php`

#### 测试用例 7.5.1：添加供应商

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 访问Suppliers页面 | 显示供应商列表 |
| 2 | 点击"Add Supplier" | 显示添加表单 |
| 3 | 填写名称和邮箱 | 表单填写 |
| 4 | 点击保存 | 供应商创建成功 |

#### 测试用例 7.5.2：有订单的供应商不能删除

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 尝试删除"Sony Music CN" | 显示错误：供应商有关联订单，无法删除 |

---

## 8. 多用户协同测试

### 8.1 库存并发预留测试

**场景：两个客户同时购买同一件库存**

| 时间线 | 用户A (Alice) | 用户B (Bob) | 系统状态 |
|--------|--------------|-------------|---------|
| T1 | 浏览长沙店Abbey Road New | 浏览长沙店Abbey Road New | 库存Available |
| T2 | 加入购物车 | 加入购物车 | 库存仍Available |
| T3 | 进入结账页面 | 进入结账页面 | 库存仍Available |
| T4 | 点击Place Order | - | 库存变Reserved |
| T5 | 订单创建成功 | 点击Place Order | - |
| T6 | - | **显示错误：商品不再可用** | 库存Reserved |

**验证要点：**
- 第一个提交的用户成功预留库存
- 第二个用户收到明确的错误提示
- 库存不会被重复预留

### 8.2 调拨与销售并发测试

**场景：管理员批准调货的同时，库存被客户购买**

| 时间线 | Admin | Customer | 库存状态 |
|--------|-------|----------|---------|
| T1 | 查看调货申请 | 浏览商品 | Available |
| T2 | 准备批准 | 加入购物车 | Available |
| T3 | - | Place Order | Reserved |
| T4 | 点击Approve | - | Reserved |
| T5 | **显示错误：库存不足** | 订单创建成功 | Reserved |

**验证要点：**
- 系统检查实时库存状态
- 已被预留的库存不会被调拨

### 8.3 订单完成与积分同步测试

**场景：员工完成订单，客户积分实时更新**

| 步骤 | 操作 | 验证 |
|------|------|------|
| 1 | 客户Edward (450积分) 下单¥100商品 | 订单Pending |
| 2 | 客户支付订单 | 订单Paid，积分仍450 |
| 3 | 员工发货 | 订单Shipped，积分仍450 |
| 4 | 员工点击Complete | 订单Completed |
| 5 | **客户刷新Profile页面** | **积分变为450+100=550** |

**验证要点：**
- 积分在订单Complete时通过触发器自动增加
- 客户无需重新登录即可看到更新

### 8.4 调拨流程多角色协同测试

**完整流程：Manager申请 → Admin批准 → 源店发货 → 目标店收货**

| 步骤 | 角色 | 操作 | 数据变化 |
|------|------|------|---------|
| 1 | manager_cs | 提交调货申请：Rumours New ×2 | ManagerRequest创建(Pending) |
| 2 | admin | 批准申请，选择仓库为源 | Request=Approved, 创建2条Transfer(Pending) |
| 3 | staff_wh | 确认发货 | Transfer=InTransit, Stock=InTransit |
| 4 | staff_cs | 确认收货 | Transfer=Completed, Stock.ShopID=1, Stock=Available |
| 5 | **验证** | 长沙店库存页面 | **显示新增2件Rumours New** |

---

## 9. 数据一致性验证测试

### 9.1 价格一致性测试

**规则：同一Release + 同一Condition的所有库存必须单价相同**

#### 测试用例 9.1.1：采购时价格同步

| 步骤 | 操作 | 验证 |
|------|------|------|
| 1 | 确认长沙店Abbey Road New当前价格¥35 | 初始状态 |
| 2 | 创建采购订单：Abbey Road New，售价¥40 | 订单创建 |
| 3 | 接收订单 | 生成新库存 |
| 4 | **验证所有Abbey Road New价格** | **全部变为¥40** |

#### 测试用例 9.1.2：回购时价格检查

| 步骤 | 操作 | 验证 |
|------|------|------|
| 1 | 确认仓库 A Night at the Opera VG+ 价格¥22 | 初始状态 |
| 2 | 回购同专辑VG+，输入转售价¥30 | 提交回购 |
| 3 | **验证新库存价格** | **使用现有价格¥22，而非输入的¥30** |

### 9.2 库存状态一致性测试

**规则：库存状态转换必须符合预定义流程**

| 初始状态 | 操作 | 目标状态 | 是否允许 |
|---------|------|---------|---------|
| Available | 加入购物车并下单 | Reserved | ✅ |
| Reserved | 订单取消 | Available | ✅ |
| Reserved | 订单完成 | Sold | ✅ |
| Available | 发起调拨 | InTransit | ✅ |
| InTransit | 完成调拨 | Available | ✅ |
| Sold | 任何操作 | - | ❌ |
| Reserved | 发起调拨 | - | ❌ |

#### 测试用例 9.2.1：已预留库存不能调拨

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 客户下单预留某库存 | 库存Reserved |
| 2 | 尝试对该库存发起调拨 | 触发器报错"Can only transfer available stock" |

### 9.3 订单金额一致性测试

**规则：订单金额 = 商品小计 - 会员折扣 + 运费**

| 步骤 | 操作 | 验证 |
|------|------|------|
| 1 | VIP客户(5%折扣)购买¥100商品 | 小计¥100 |
| 2 | 选择运输（运费¥15） | 运费¥15 |
| 3 | **验证总金额** | **100 × 0.95 + 15 = ¥110** |

### 9.4 积分计算一致性测试

#### 测试用例 9.4.1：购物积分计算

| 客户 | 订单金额 | 是否生日月 | 预期积分 |
|------|---------|-----------|---------|
| Alice | ¥100 | 否（5月生日，当前12月）| +100 |
| Diana | ¥100 | 是（12月生日）| +100 + 20 = +120 |
| Bob | ¥100 | 是（12月生日）| +100 + 20 = +120 |

#### 测试用例 9.4.2：回购积分计算

| 客户 | 回购金额 | 预期积分 |
|------|---------|---------|
| Edward | ¥100 | +50 (100 × 0.5) |
| Walk-in | ¥100 | 无积分 |

### 9.5 会员等级自动升级测试

| 客户 | 当前积分 | 当前等级 | 订单金额 | 新积分 | 新等级 |
|------|---------|---------|---------|-------|-------|
| Charlie | 150 | Standard | ¥900 | 1050 | **VIP** |
| Alice | 1500 | VIP | ¥3600 | 5100 | **Gold** |
| Edward | 450 | Standard | ¥100 | 550 | Standard |

---

## 10. 边界条件与异常测试

### 10.1 权限边界测试

| 测试场景 | 操作 | 预期结果 |
|---------|------|---------|
| 客户访问员工页面 | 访问 /staff/pos.php | 重定向到登录或拒绝访问 |
| 员工访问管理员页面 | Staff访问 /admin/requests.php | 重定向或拒绝访问 |
| 仓库员工访问POS | staff_wh访问 /staff/pos.php | 显示错误并重定向 |
| 仓库员工访问回购 | staff_wh访问 /staff/buyback.php | 显示"仅门店可用"并重定向 |

### 10.2 库存边界测试

| 测试场景 | 操作 | 预期结果 |
|---------|------|---------|
| 购买最后一件库存 | 购买某专辑仅剩的1件 | 购买成功，目录显示"无库存" |
| 购物车商品被他人买走 | 结账时商品已被预留 | 显示错误"商品不再可用" |
| 调拨超过可用数量 | 申请调拨10件，实际只有3件 | Admin批准时显示错误 |

### 10.3 输入验证测试

| 测试场景 | 输入 | 预期结果 |
|---------|------|---------|
| 回购负数价格 | 单价=-10 | 表单验证失败 |
| 采购零数量 | 数量=0 | 表单验证失败 |
| 空白地址运输 | 选择Shipping但地址为空 | 显示"请输入配送地址" |
| 重复邮箱注册 | 使用已存在邮箱 | 显示"邮箱已存在" |

### 10.4 状态流转异常测试

| 测试场景 | 操作 | 预期结果 |
|---------|------|---------|
| 取消已完成订单 | 对Completed订单点取消 | 操作不可用或报错 |
| 修改已发货订单行 | 尝试修改Shipped订单商品 | 触发器阻止"Cannot modify shipped orders" |
| 调拨同一店铺 | FromShop = ToShop | 触发器阻止"Source and destination cannot be same" |

### 10.5 并发超时测试

| 测试场景 | 操作 | 预期结果 |
|---------|------|---------|
| 30分钟未支付 | 创建订单后等待30分钟 | 系统自动取消订单，释放库存 |
| 支付页面刷新 | 订单已被取消后刷新支付页 | 显示"订单已取消" |

---

## 附录：测试检查清单

### A. 客户端功能检查

- [ ] 用户注册
- [ ] 用户登录/登出
- [ ] 店铺切换与购物车清空
- [ ] 商品浏览与搜索
- [ ] 购物车管理
- [ ] 结账流程（自提/运输）
- [ ] 支付流程
- [ ] 订单查看与取消
- [ ] 会员折扣应用
- [ ] 积分显示与更新
- [ ] 个人资料管理

### B. 员工端功能检查

- [ ] POS销售（门店）
- [ ] POS绑定会员
- [ ] 回购功能（门店）
- [ ] 回购积分赠送
- [ ] 订单发货
- [ ] 自提处理
- [ ] 调拨发货确认
- [ ] 调拨收货确认
- [ ] 库存查询

### C. 经理端功能检查

- [ ] 调价申请提交
- [ ] 调货申请提交
- [ ] 申请状态跟踪
- [ ] 订单管理
- [ ] 业务报表查看

### D. 管理员端功能检查

- [ ] 调价申请审批
- [ ] 调货申请审批
- [ ] 采购订单创建
- [ ] 采购订单接收
- [ ] 产品管理
- [ ] 员工管理
- [ ] 供应商管理

### E. 数据一致性检查

- [ ] 价格一致性（采购同步）
- [ ] 价格一致性（回购检查）
- [ ] 库存状态流转正确
- [ ] 订单金额计算正确
- [ ] 积分计算正确
- [ ] 会员等级自动升级
- [ ] 触发器正常工作

---

**文档结束**

> 本测试方案覆盖了系统的所有主要功能、业务流程和数据一致性设计。测试执行时建议按照章节顺序进行，确保前置数据状态正确。如有问题，请参考初始测试数据说明部分确认当前数据状态。
