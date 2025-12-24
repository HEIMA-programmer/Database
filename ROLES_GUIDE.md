# 用户角色功能指南

## 四个用户角色详解

### 1. 👤 Customer（顾客）

**主要职责**：浏览购买唱片，管理个人会员信息

**功能列表**：
- **📚 Catalog**：浏览在线目录（仅显示仓库Warehouse的库存）
- **🛒 Cart**：购物车管理和结账
- **📦 Orders**：查看订单历史和物流状态
- **🎫 Membership**：查看会员等级、积分和个人资料

**业务流程**：
1. 浏览目录选择商品
2. 添加到购物车
3. 结账付款（订单状态：Pending → Paid）
4. 仓库发货（状态：Shipped）
5. 确认收货（状态：Completed）
6. 自动积分累积和会员升级

**会员等级系统**：
- 消费1元 = 1积分
- 达到门槛自动升级会员等级
- 享受对应等级的折扣率

---

### 2. 🏪 Staff（店员）

**主要职责**：处理店内销售、回购、发货和库存管理

**功能列表**：
- **💰 POS**：店内销售收银系统
  - 扫描或搜索商品（支持ID精确搜索和标题模糊搜索）
  - 添加到POS购物车
  - 结账（可选输入顾客邮箱累积积分）
  - 即时完成交易（Status: Completed）

- **♻️ Buyback**：回购二手唱片
  - 评估品相（Mint, NM, VG+, VG, G）
  - 设定收购价和转售价
  - 创建回购单（PO Type: Buyback）
  - 立即加入库存可售

- **🚚 Fulfillment**：在线订单发货
  - 查看待发货订单（Status: Paid）
  - 标记已发货（Status: Shipped）
  - 确认送达（Status: Completed）

- **📦 Pickups**：店内取货（BOPIS - Buy Online, Pick up In Store）
  - 查看待取货订单
  - 确认顾客取货

- **📊 Inventory**：查看本店库存
  - 显示库存天数
  - 标注超过60天的滞销商品

**注意事项**：
- Staff在自己所属的店铺（Retail或Warehouse）工作
- 只能看到和操作本店的库存
- POS销售的商品必须是本店Available状态的库存

---

### 3. 📈 Manager（经理）

**主要职责**：数据分析、库存调拨、业务决策

**功能列表**：
- **📊 Dashboard**：执行仪表盘
  - 总收入统计
  - 活跃订单数量
  - Top VIP客户排名（使用窗口函数DENSE_RANK）
  - 滞销库存预警（>60天未售出）

- **📄 Reports**：业务报表
  - 库存周转率分析（按流派Genre分析）
  - 月度销售趋势图
  - 销售速度分类（Fast < 30天, Moderate < 90天, Slow ≥ 90天）

- **🚛 Transfers**：库存调拨管理
  - 发起调拨：将商品从一个店铺调到另一个店铺
  - 确认收货：接收调拨来的商品
  - 状态跟踪：InTransit → Completed

**库存调拨流程**：
1. Manager发起调拨（输入StockItemID和目标店铺）
2. 系统更新：
   - StockItem状态 → Reserved（在途中不可售）
   - 创建InventoryTransfer记录（Status: InTransit）
3. 目标店铺确认收货
4. 系统更新：
   - StockItem的ShopID更新为目标店铺
   - StockItem状态 → Available
   - InventoryTransfer状态 → Completed

**权限继承**：
- Manager可以访问Staff的所有功能
- Manager通常也负责监督店铺日常运营

---

### 4. ⚙️ Admin（管理员）

**主要职责**：系统配置、产品管理、采购管理、用户管理

**功能列表**：
- **💿 Products**：产品/专辑管理
  - 添加新专辑（Release Album）
  - 编辑专辑信息（标题、艺人、厂牌、年份、流派、格式）
  - 管理曲目列表（Track List）
  - 删除专辑（需检查库存依赖）

- **📦 Procurement**：采购管理
  - 创建采购订单（PO - Purchase Order）
  - 选择供应商和商品
  - 设置数量和单价
  - 收货确认（Receive PO）

- **🏢 Suppliers**：供应商管理
  - 添加供应商
  - 编辑供应商信息
  - 删除供应商（需检查是否有关联PO）

- **👥 Users**：用户管理
  - 查看员工列表
  - 查看顾客列表
  - 管理用户角色和权限

**采购流程（Procurement Flow）**：
1. **创建PO**：
   - 选择供应商
   - 选择专辑
   - 设置数量和单价
   - 订单状态：Pending

2. **收货确认（Receive PO）**：
   - 点击"Receive Goods"
   - 系统自动：
     - 根据数量生成对应数量的StockItem
     - 默认品相：New
     - 默认定价：进货价 × 1.5
     - 批次号：BATCH-{日期}-{PO_ID}
     - 店铺：Warehouse（所有采购商品先入仓库）
     - 状态：Available
   - PO状态更新为：Received

**业务规则**：
- 所有采购商品默认进入Warehouse（仓库）
- 可通过Manager的Transfer功能调拨到零售店
- Admin拥有最高权限，可以访问所有功能

---

## 业务流程图

### 采购到销售完整流程

```
[Admin创建PO] → [供应商发货] → [Admin收货]
       ↓
[生成StockItem到Warehouse, Status: Available]
       ↓
   ┌───┴────┐
   │        │
[线上销售]  [调拨到零售店] → [店内POS销售]
   │        │                     │
   ↓        ↓                     ↓
[发货]  [店内取货]           [即时完成]
   ↓
[确认送达]
   ↓
[Status: Sold]
```

### 回购流程

```
[顾客带旧唱片] → [Staff评估品相和价格]
       ↓
[创建Buyback PO (Type: Buyback)]
       ↓
[生成StockItem, Status: Available]
       ↓
[立即可售]
```

---

## 数据溯源（Traceability）

系统设计了完整的溯源机制：

1. **StockItem.SourcePO_ID**：每个库存商品都记录来源采购单
2. **StockItem.BatchNo**：批次号标识
   - 供应商采购：`BATCH-{日期}-{PO_ID}`
   - 回购：`BUYBACK-{日期}-{PO_ID}`
3. **PurchaseOrder.SourceType**：区分采购类型
   - `Supplier`：从供应商采购
   - `Buyback`：从顾客回购
4. **PurchaseOrder.BuybackCustomerID**：记录回购来源顾客

这样可以追踪：
- 任何一张唱片从哪里来
- 什么时候进货的
- 进货成本多少
- 如果是回购，从哪个顾客回购的

---

## 测试账号

**员工登录（Employee）**：
- 用户名：`admin`
- 密码：`password123`
- 角色：Admin / Manager / Staff（根据数据库配置）

**顾客登录（Customer）**：
- 邮箱：`alice@test.com`
- 密码：`password123`

---

## 技术亮点

### 1. 安全性
- ✅ PDO Prepared Statements防SQL注入
- ✅ Password Hash存储
- ✅ XSS防护（h()函数）
- ✅ 角色权限分离（requireRole）

### 2. 数据完整性
- ✅ 外键约束
- ✅ 事务管理（BEGIN/COMMIT/ROLLBACK）
- ✅ 状态机设计（ENUM类型）
- ✅ 行级锁（FOR UPDATE）防并发

### 3. 业务逻辑
- ✅ 积分自动累积
- ✅ 会员自动升级
- ✅ 库存状态自动更新
- ✅ 完整的采购到销售流程

### 4. 可维护性
- ✅ 视图封装复杂查询
- ✅ 函数复用（functions.php）
- ✅ 模块化设计
- ✅ 代码注释清晰

---

## 常见问题 FAQ

**Q1: 为什么Customer只能看到Warehouse的库存？**
A: 在线购物由仓库统一发货，零售店的库存用于店内销售（POS）和BOPIS取货。

**Q2: Staff可以跨店铺操作吗？**
A: 不可以，Staff只能操作自己所属店铺的库存和订单。

**Q3: 如何将库存从仓库调到零售店？**
A: 使用Manager的Transfer功能进行库存调拨。

**Q4: 回购的商品可以立即销售吗？**
A: 可以，回购后立即生成StockItem（Status: Available），可以通过POS或在线销售。

**Q5: 如何追踪一张唱片的来源？**
A: 通过StockItem.SourcePO_ID和BatchNo可以追踪到具体的采购单和批次。

**Q6: 积分规则是什么？**
A: 消费1元获得1积分，达到对应等级的MinPoints即可自动升级。

**Q7: POS搜索支持什么方式？**
A: 支持两种方式：
   - 输入纯数字：精确匹配StockItemID
   - 输入文字：模糊匹配专辑标题

**Q8: 库存调拨时商品可以销售吗？**
A: 不可以，调拨中的商品状态为Reserved（预留），到达目标店铺确认收货后才变为Available。
