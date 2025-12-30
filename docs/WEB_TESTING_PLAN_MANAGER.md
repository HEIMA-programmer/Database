# Manager 端功能测试方案

> 版本: 2.0
> 关联主文件: [WEB_TESTING_PLAN.md](WEB_TESTING_PLAN.md)

---

## 目录

1. [Dashboard 界面测试](#1-dashboard-界面测试)
2. [Requests 申请管理测试](#2-requests-申请管理测试)
3. [Reports 报表测试](#3-reports-报表测试)
4. [Customer Orders 订单管理测试](#4-customer-orders-订单管理测试)
5. [调货引起的成本变化测试](#5-调货引起的成本变化测试)

---

## 1. Dashboard 界面测试

**测试路径:** `/public/manager/dashboard.php`

Manager Dashboard 包含 **四个KPI小框** 和 **四个详细大框**，需要全面测试其数据显示和交互功能。

### 1.1 四个KPI小框测试

#### 小框1: Total Revenue（总收入）

| 测试项 | 测试方法 | 预期结果 |
|--------|---------|---------|
| 初始显示 | manager_cs 登录查看 | 显示长沙店历史总收入 |
| 计算验证 | 对比数据库 | = SUM(已完成订单的TotalAmount，不含运费) |
| 边框颜色 | 视觉检查 | 绿色边框(border-success) |

**长沙店初始值计算：**
- 订单1(Alice): ¥56.00
- 订单2(Bob): ¥60.80
- **总计: ¥116.80**

**测试用例 1.1.1: 新订单后收入更新**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 记录当前Total Revenue | ¥116.80 |
| 2 | Alice在长沙店完成一笔¥56.00订单 | 订单完成 |
| 3 | 刷新Dashboard | Total Revenue变为¥172.80 |

#### 小框2: Most Popular（最畅销单品）

| 测试项 | 测试方法 | 预期结果 |
|--------|---------|---------|
| 初始显示 | manager_cs 登录查看 | 显示专辑名 + 销量数字 |
| 边框颜色 | 视觉检查 | 青色边框(border-info) |

**长沙店初始值：**
- Abbey Road: 1件(OrderID=1, Alice)
- Dark Side of the Moon: 1件(OrderID=2, Bob)
- **显示: Abbey Road (1) 或 Dark Side (1)**（取决于排序）

**测试用例 1.1.2: 销售后畅销榜更新**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 记录当前Most Popular | Abbey Road (1) |
| 2 | 通过POS销售2件Dark Side | 销售完成 |
| 3 | 刷新Dashboard | Most Popular变为 Dark Side (3) |

#### 小框3: Top Spender（消费最高客户）

| 测试项 | 测试方法 | 预期结果 |
|--------|---------|---------|
| 初始显示 | manager_cs 登录查看 | 显示客户姓名 |
| 边框颜色 | 视觉检查 | 黄色边框(border-warning) |

**长沙店初始值：**
- Bob: ¥60.80 (订单2)
- Alice: ¥56.00 (订单1)
- **显示: Bob Collector**

**测试用例 1.1.3: Alice超过Bob后更新**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 记录当前Top Spender | Bob Collector |
| 2 | Alice在长沙店完成¥100订单 | 订单完成 |
| 3 | 刷新Dashboard | Top Spender变为 Alice Fan |

#### 小框4: Total Cost（店铺总成本）

> **重要更新：** 此指标已从"Inventory Cost（库存成本）"改为"Total Cost（店铺总成本）"，计算逻辑也有重大变化。

| 测试项 | 测试方法 | 预期结果 |
|--------|---------|---------|
| 初始显示 | manager_cs 登录查看 | 显示**所有库存（包含已售）的历史采购成本总和** |
| 标题文字 | 视觉检查 | 显示"Total Cost"（非Inventory Cost） |
| 描述文字 | 视觉检查 | 显示"Historical cost"（非Real-time cost） |
| 数量描述 | 视觉检查 | 显示"items (incl. sold)"（包含已售库存件数） |
| 边框颜色 | 视觉检查 | 红色边框(border-danger) |
| 实时更新 | 调拨后刷新 | 数值随调拨转移而变化 |

**成本计算规则（新版）：**
- ✅ 统计所有库存（Available + Sold）的历史成本
- ✅ 调货后成本跟随库存转移到目标店铺
- ❌ 不再仅统计Available状态的库存成本

**成本来源：**
| SourceType | 成本取值字段 |
|------------|-------------|
| Supplier（供应商采购） | SupplierOrderLine.UnitCost |
| Buyback（回购入库） | BuybackOrderLine.UnitPrice |

**长沙店初始总成本计算（包含已售）：**

| StockItemID | 专辑 | 成色 | 来源成本 | 状态 | 是否计入 |
|-------------|------|------|---------|------|---------|
| 1 | Abbey Road | New | ¥35.00 | Available | ✅ |
| 2 | Abbey Road | New | ¥35.00 | Available | ✅ |
| 3 | Abbey Road | Mint | ¥33.25 | Available | ✅ |
| 4 | Dark Side | New | ¥40.00 | Available | ✅ |
| 5 | Dark Side | New | ¥40.00 | Available | ✅ |
| 6 | Dark Side | Mint | ¥38.00 | Available | ✅ |
| 7 | Dark Side | VG+ | ¥28.00 | Available | ✅ |
| 8 | Abbey Road | New | ¥35.00 | **Sold** | ✅ (历史成本) |
| 9 | Dark Side | Mint | ¥38.00 | **Sold** | ✅ (历史成本) |
| 10 | Abbey Road | VG+ | ¥24.50 | Available | ✅ |
| 26 | Rumours | New | ¥32.00 | Available | ✅ (调拨来) |
| 56-58 | Opera | VG+ | ¥12.00×3 | Available | ✅ (回购) |

**初始总成本计算：**
- 供应商库存成本: ¥35+35+33.25+40+40+38+28+35+38+24.50+32 = ¥378.75
- 回购库存成本: ¥12×3 = ¥36.00
- **总计约: ¥414.75**

**测试用例 1.1.4: 调货后总成本变化**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 记录长沙店Total Cost | 约¥415 |
| 2 | 从仓库调拨1件Opera New(成本¥36)到长沙店 | 调拨完成 |
| 3 | 刷新长沙店Dashboard | Total Cost增加¥36 |
| 4 | 检查仓库Dashboard | Total Cost减少¥36 |

> **注意：** 销售完成后，Total Cost**不会减少**，因为已售库存的历史成本仍计入统计。

---

### 1.2 四个详细大框测试

#### 大框1: Top Spenders（消费排行）

**显示内容：**
- 排名(#)
- 客户名称
- 会员等级(Tier)
- 总消费金额(右对齐)
- Details按钮
- 底部特行: Walk-in Customers合计

**测试用例 1.2.1: Top Spenders列表验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs 登录查看Dashboard | 进入长沙店Dashboard |
| 2 | 查看Top Spenders表格 | 显示客户列表 |
| 3 | 验证Bob Collector行 | #1, Bob Collector, Gold, ¥60.80 |
| 4 | 验证Alice Fan行 | #2, Alice Fan, VIP, ¥56.00 |
| 5 | 验证Walk-in行 | 显示Walk-in总消费(如有) |

**测试用例 1.2.2: Details按钮功能**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击Alice行的Details按钮 | 弹出/跳转订单详情 |
| 2 | 验证显示内容 | 显示Alice在长沙店的订单#1 |
| 3 | 订单信息验证 | 日期、金额¥56.00、Abbey Road New |

**测试用例 1.2.3: Walk-in消费统计**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | staff_cs 进行一笔Walk-in POS销售(¥64) | 无客户绑定的销售 |
| 2 | manager_cs 刷新Dashboard | Dashboard刷新 |
| 3 | 查看Walk-in Customers行 | 显示1笔订单，金额¥64 |

---

#### 大框2: Stagnant Inventory（滞销库存）

**显示条件：** 库存天数 > 60天

**显示内容：**
- 滞销天数
- 专辑名称 + 艺术家
- 成色等级
- 库存数量
- 调价按钮(tag icon)

**测试用例 1.2.4: 滞销库存列表验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs 登录查看Dashboard | 进入长沙店Dashboard |
| 2 | 查看Stagnant Inventory表格 | 显示>60天的库存 |
| 3 | 验证长沙店滞销库存 | 供应商订单1(69天前)的库存应显示 |

**长沙店初始滞销库存(69天前入库)：**
- Abbey Road New: 2件
- Abbey Road Mint: 1件
- Abbey Road VG+: 1件
- Dark Side New: 2件
- Dark Side Mint: 1件
- Dark Side VG+: 1件

**测试用例 1.2.5: 调价按钮功能**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 找到Abbey Road New行 | 显示调价按钮(tag icon) |
| 2 | 点击调价按钮 | 跳转到Requests页面 |
| 3 | 验证预填信息 | Album=Abbey Road, Condition=New |
| 4 | 验证Current Price | ¥56.00 |
| 5 | 验证Quantity | 2（可用数量） |

**测试用例 1.2.6: 快速调价流程**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 从滞销库存点击调价 | 进入调价申请表单 |
| 2 | 输入目标价格¥45.00 | 价格填写 |
| 3 | 输入理由"清仓促销" | 理由填写 |
| 4 | 提交申请 | 申请创建成功 |
| 5 | 返回Dashboard | Stagnant Inventory仍显示(等待审批) |

---

#### 大框3: Low Stock Alert（低库存预警）

**显示条件：** 某专辑+成色的可用库存 < 3件

**显示内容：**
- 专辑名称 + 艺术家
- 成色等级
- 当前库存数量(红色标识)
- 调货按钮(truck icon)

**测试用例 1.2.7: 低库存列表验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs 登录查看Dashboard | 进入长沙店Dashboard |
| 2 | 查看Low Stock Alert表格 | 显示<3件的库存 |

**长沙店初始低库存：**
- Abbey Road New: 2件 → 显示
- Abbey Road Mint: 1件 → 显示
- Abbey Road VG+: 1件 → 显示
- Dark Side New: 2件 → 显示
- Dark Side Mint: 1件 → 显示
- Dark Side VG+: 1件 → 显示
- Rumours New: 1件 → 显示

**测试用例 1.2.8: 调货按钮功能**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 找到Rumours New行(1件) | 显示调货按钮(truck icon) |
| 2 | 点击调货按钮 | 跳转到Requests页面 |
| 3 | 验证预填信息 | Album=Rumours, Condition=New |
| 4 | 验证Destination | 长沙店(只读) |

**测试用例 1.2.9: 快速调货流程**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 从低库存点击调货 | 进入调货申请表单 |
| 2 | 输入需求数量2 | 数量填写 |
| 3 | 输入理由"库存补货" | 理由填写 |
| 4 | 提交申请 | 申请创建成功 |
| 5 | 返回Dashboard | Low Stock仍显示(等待审批和调拨) |

**测试用例 1.2.10: 调货完成后低库存更新**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | Admin批准调货申请(从仓库) | 申请批准 |
| 2 | staff_wh确认发货 | 状态InTransit |
| 3 | staff_cs确认收货 | 状态Completed |
| 4 | manager_cs刷新Dashboard | Rumours New数量变为3件 |
| 5 | 验证Low Stock列表 | Rumours New不再显示(≥3件) |

---

#### 大框4: Revenue & Expense Breakdown（收支明细）

**显示内容（按店铺类型）：**

**零售店(Retail)显示：**
| 类型 | 说明 | 颜色 |
|------|------|------|
| Online Sales (Shipping) | 线上配送订单收入 | 绿色 |
| Online Pickup | 线上自提订单收入 | 绿色 |
| POS In-Store | 门店POS销售收入 | 绿色 |
| Buyback | 回购支出 | 红色 |
| Total Cost | 历史库存总成本（含已售） | 红色 |

**仓库(Warehouse)显示：**
| 类型 | 说明 | 颜色 |
|------|------|------|
| Online Sales | 线上订单收入 | 绿色 |
| Total Cost | 历史库存总成本（含已售） | 红色 |

> **注意：** Revenue Breakdown中的"Total Cost"与KPI小框中的"Total Cost"计算方式相同，都包含已售出库存的历史成本。

**每行显示：**
- 订单数量
- 金额
- Details按钮

**测试用例 1.2.11: 长沙店收支明细验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs 查看Revenue Breakdown | 显示收支表格 |
| 2 | 验证POS In-Store行 | 2笔订单，¥116.80 |
| 3 | 验证Online行 | 0笔订单，¥0 |
| 4 | 验证Buyback行 | 1笔，¥36.00（Bob回购Opera VG+×3） |
| 5 | 验证Total Cost | 约¥415(包含已售库存的历史成本) |

**测试用例 1.2.12: Details按钮 - 查看POS订单**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击POS In-Store的Details | 弹出订单列表 |
| 2 | 验证订单#1 | Alice, Abbey Road New, ¥56.00 |
| 3 | 验证订单#2 | Bob, Dark Side Mint, ¥60.80 |

**测试用例 1.2.13: 仓库收支明细验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_wh 登录查看Dashboard | 进入仓库Dashboard |
| 2 | 验证Online Sales行 | 3笔订单 |
| 3 | 计算验证 | ¥51.20+¥57.76+¥47.04=¥156.00 |
| 4 | 验证无Buyback行 | 仓库不支持回购功能 |
| 5 | 验证Total Cost | 仓库所有库存（含已售）的历史成本 |

**测试用例 1.2.14: 新订单后收支更新**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 记录长沙店POS收入 | ¥116.80，2笔 |
| 2 | staff_cs 完成Alice的POS销售(¥53.20) | 订单完成 |
| 3 | manager_cs 刷新Dashboard | POS行变为3笔，¥170.00 |
| 4 | 同时验证Total Cost | **保持不变**（已售库存的历史成本仍计入） |

---

### 1.3 Dashboard按钮功能汇总测试

| 按钮位置 | 按钮功能 | 测试操作 | 预期结果 |
|---------|---------|---------|---------|
| Top Spenders → Details | 查看客户订单 | 点击任意客户 | 显示该客户在本店的所有订单 |
| Stagnant Inventory → 调价(tag) | 发起调价申请 | 点击按钮 | 跳转Requests页面，预填专辑/成色/价格 |
| Low Stock → 调货(truck) | 发起调货申请 | 点击按钮 | 跳转Requests页面，预填专辑/成色 |
| Revenue Breakdown → Details | 查看订单明细 | 点击任意类型 | 显示该类型的所有订单列表 |

---

## 2. Requests 申请管理测试

**测试路径:** `/public/manager/requests.php`

### 2.1 左侧菜单测试

| 菜单项 | 显示内容 | 测试验证 |
|--------|---------|---------|
| All Requests | 所有申请总数 | 计数正确 |
| Pending | 待审批申请数(黄色标签) | 只显示Pending状态 |
| Approved | 已批准申请数(绿色标签) | 只显示Approved状态 |
| Rejected | 已驳回申请数(红色标签) | 只显示Rejected状态 |
| New Price Request | 新建调价申请 | 打开表单 |
| New Transfer Request | 新建调货申请 | 打开表单 |

### 2.2 New Price Request 表单测试

**测试用例 2.2.1: 完整调价申请流程**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs点击New Price Request | 显示调价表单 |
| 2 | 选择Album: Abbey Road | 下拉选择成功 |
| 3 | 选择Condition: New | 下拉选择成功 |
| 4 | 验证Quantity自动填充 | 显示2(长沙店Abbey Road New可用数量) |
| 5 | 验证Current Price自动填充 | ¥56.00 |
| 6 | 输入Requested Price: ¥45.00 | 手动输入 |
| 7 | 输入Reason: 滞销商品促销 | 手动输入 |
| 8 | 点击Submit | 申请创建成功 |
| 9 | 验证Pending列表 | 新申请显示在列表中 |

**测试用例 2.2.2: 表单验证**

| 测试场景 | 输入 | 预期结果 |
|---------|------|---------|
| 目标价格为空 | Requested Price留空 | 显示错误提示 |
| 目标价格为负 | Requested Price=-10 | 显示错误提示 |
| 目标价格等于当前价格 | Requested Price=56 | 允许提交(可能) |
| 无库存的成色 | 选择不存在的成色 | 下拉中不显示/提示无库存 |

### 2.3 New Transfer Request 表单测试

**测试用例 2.3.1: 完整调货申请流程**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs点击New Transfer Request | 显示调货表单 |
| 2 | 选择Album: A Night at the Opera | 下拉选择成功 |
| 3 | 选择Condition: New | 下拉选择成功 |
| 4 | 输入Quantity Needed: 2 | 手动输入 |
| 5 | 验证Destination自动显示 | 长沙店(只读) |
| 6 | 输入Reason: 客户预订需要 | 手动输入 |
| 7 | 点击Submit | 申请创建成功 |

### 2.4 申请列表显示测试

**申请卡片/行应显示：**
- 请求类型徽章(蓝色Price/紫色Transfer)
- 专辑名称 - 艺术家名
- 成色等级
- 数量
- 价格信息(调价) 或 源店信息(调货)
- 理由说明
- Admin回复(若有)
- 状态徽章 + 创建日期

**测试用例 2.4.1: 申请状态跟踪**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 提交一个调价申请 | 状态Pending(黄色) |
| 2 | Admin批准申请 | 状态Approved(绿色) |
| 3 | 刷新manager页面 | 申请显示在Approved列表 |
| 4 | 验证AdminResponseNote | 显示Admin的审批意见 |

---

## 3. Reports 报表测试

**测试路径:** `/public/manager/reports.php`

### 3.1 Inventory Turnover by Genre（按流派库存周转）

**显示列：**
- 流派(Genre)
- 销售件数
- 平均销售天数
- 周转速度(Fast/Moderate/Slow)
- 收入
- Details按钮

**测试用例 3.1.1: 流派周转数据验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs 访问Reports | 显示报表页面 |
| 2 | 查看Rock流派行 | 显示Abbey Road销售数据 |
| 3 | 验证销售件数 | 1件(OrderID=1) |
| 4 | 验证收入 | ¥56.00 |

**测试用例 3.1.2: Details按钮 - 流派订单明细**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击Rock行的Details | 弹出模态框 |
| 2 | 验证订单列表 | 显示订单号、日期、客户、专辑、价格 |
| 3 | 找到订单#1 | Alice, Abbey Road, ¥56.00 |

### 3.2 Monthly Sales Trend（月度销售趋势）

**显示列：**
- 月份
- 订单数
- 收入
- 进度条(可视化)
- Details按钮

**测试用例 3.2.1: 月度趋势数据验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 查看月度趋势表格 | 显示各月数据 |
| 2 | 找到有订单的月份 | 显示订单数和收入 |
| 3 | 验证进度条 | 与收入金额成比例 |

**测试用例 3.2.2: Details按钮 - 月度订单明细**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击某月的Details | 弹出模态框 |
| 2 | 验证订单列表 | 显示该月所有订单 |
| 3 | 验证订单信息 | 订单号、日期、类型、客户、专辑、价格 |

---

## 4. Customer Orders 订单管理测试

**测试路径:** `/public/manager/customer_orders.php`

### 4.1 订单列表测试

**测试用例 4.1.1: 只能看本店订单**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs 登录 | 长沙店Manager |
| 2 | 访问Customer Orders | 显示订单列表 |
| 3 | 验证显示的订单 | 只有FulfilledByShopID=1的订单 |
| 4 | 验证订单#1,#2 | 应该显示 |
| 5 | 验证订单#3(上海店) | 不应该显示 |

**测试用例 4.1.2: 订单筛选功能**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 按状态筛选"Completed" | 只显示已完成订单 |
| 2 | 按状态筛选"Pending" | 只显示待支付订单 |
| 3 | 按类型筛选"InStore" | 只显示门店订单 |

### 4.2 订单详情测试

**测试用例 4.2.1: 查看订单详情**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击订单#1 | 显示订单详情 |
| 2 | 验证客户信息 | Alice Fan |
| 3 | 验证商品信息 | Abbey Road New, ¥56.00 |
| 4 | 验证订单状态 | Completed |

---

## 5. 调货引起的成本变化测试

> 这是测试Manager Dashboard实时更新能力的关键场景
>
> **重要更新：** 成本计算现在使用"Total Cost"（历史总成本），包含已售库存的成本。调货时成本会从源店转移到目标店。

### 5.1 完整调货流程与成本追踪

**初始状态记录：**

| 店铺 | Dashboard Total Cost | 库存件数（含已售） |
|------|-------------------------|-------------|
| 长沙店 | 约¥415 | 13件（11件可用+2件已售） |
| 仓库 | 约¥900 | 28件（25件可用+3件已售） |

**测试用例 5.1.1: 调货全流程成本变化**

| 步骤 | 角色 | 操作 | 长沙店成本 | 仓库成本 | 说明 |
|------|------|------|-----------|---------|------|
| 1 | manager_cs | 记录当前Total Cost | ¥415 | ¥900 | 初始值 |
| 2 | manager_cs | 提交调货申请(Opera New×2) | ¥415 | ¥900 | 无变化 |
| 3 | admin | 批准申请，选择仓库为源 | ¥415 | ¥900 | 无变化 |
| 4 | staff_wh | 确认发货 | ¥415 | ¥900 | InTransit状态成本暂不变 |
| 5 | staff_cs | 确认收货 | **¥487** | **¥828** | 2件Opera(¥36×2)转移 |
| 6 | 验证 | 刷新两边Dashboard | +¥72 | -¥72 | 成本实时转移 |

### 5.2 成本变化详细验证

**测试用例 5.2.1: 源店成本减少验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_wh 查看Dashboard | 记录Total Cost = X |
| 2 | 完成调货发出2件Opera New | 发货成功 |
| 3 | 目标店确认收货 | 收货成功 |
| 4 | 刷新仓库Dashboard | Total Cost = X - (2×36) = X - 72 |

**测试用例 5.2.2: 目标店成本增加验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs 查看Dashboard | 记录Total Cost = Y |
| 2 | 确认收货2件Opera New | 收货成功 |
| 3 | 刷新长沙店Dashboard | Total Cost = Y + (2×36) = Y + 72 |

### 5.3 Revenue Breakdown中的成本行验证

**测试用例 5.3.1: 收支明细成本行更新**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs 查看Revenue Breakdown | 找到Total Cost行 |
| 2 | 记录当前值 | Y |
| 3 | 完成调货接收 | 收货成功 |
| 4 | 刷新查看 | Total Cost = Y + 调入成本 |

### 5.4 销售后成本变化验证

> **重要：** 新版本中销售完成后Total Cost**不会减少**，因为已售库存的历史成本仍计入统计。

**测试用例 5.4.1: 销售不影响Total Cost**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs 记录Total Cost | 值Z |
| 2 | staff_cs POS销售Abbey Road New | 销售完成 |
| 3 | 刷新Dashboard | Total Cost = Z（**保持不变**） |
| 4 | 验证库存件数 | 件数不变（因为包含已售库存） |

---

## 附录：Manager Dashboard 检查清单

### A. 四个小框检查

- [ ] Total Revenue显示正确（仅统计Paid/Completed订单）
- [ ] Most Popular显示畅销单品
- [ ] Top Spender显示消费最高客户
- [ ] Total Cost显示历史库存总成本（含已售）
- [ ] Total Cost标题正确（非Inventory Cost）
- [ ] Total Cost描述正确（Historical cost, items incl. sold）

### B. 四个大框检查

- [ ] Top Spenders列表正确排序
- [ ] Top Spenders Details按钮可用
- [ ] Walk-in消费单独统计
- [ ] Stagnant Inventory显示>60天库存
- [ ] Stagnant调价按钮跳转正确
- [ ] Low Stock显示<3件库存
- [ ] Low Stock调货按钮跳转正确
- [ ] Revenue Breakdown按类型统计正确
- [ ] Revenue Breakdown Details显示订单列表
- [ ] Total Cost行显示正确（含已售库存成本）

### C. 按钮功能检查

- [ ] 所有Details按钮可点击
- [ ] 调价按钮预填正确信息
- [ ] 调货按钮预填正确信息
- [ ] 申请提交后状态正确

### D. 实时更新检查

- [ ] 新订单后Total Revenue更新
- [ ] 销售后Total Cost**保持不变**（已售库存成本仍计入）
- [ ] 调货后两边Total Cost同时变化（转移成本）
- [ ] 新客户消费后Top Spenders更新

---

**文档结束**
