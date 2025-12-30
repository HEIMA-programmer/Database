# Staff 端功能测试方案

> 版本: 2.0
> 关联主文件: [WEB_TESTING_PLAN.md](WEB_TESTING_PLAN.md)
> 主线角色: Alice Fan

---

## 目录

1. [POS销售测试](#1-pos销售测试)
2. [Buyback回购测试](#2-buyback回购测试)
3. [Fulfillment订单履行测试](#3-fulfillment订单履行测试)
4. [Pickup自提处理测试](#4-pickup自提处理测试)
5. [Inventory库存查询测试](#5-inventory库存查询测试)
6. [权限边界测试](#6-权限边界测试)

---

## 1. POS销售测试

**测试路径:** `/public/staff/pos.php`
**适用店铺:** 仅零售店(Retail) - 长沙店、上海店
**不适用:** 仓库(Warehouse)

### 1.1 POS界面元素测试

| 元素 | 功能 | 测试验证 |
|------|------|---------|
| 搜索框 | 按专辑/艺术家搜索库存 | 输入关键字返回结果 |
| 库存列表 | 显示本店可用库存 | 只显示Available状态 |
| 购物车区域 | 已添加商品列表 | 显示商品和小计 |
| 客户选择 | 选择绑定会员 | 下拉显示注册客户 |
| Complete Sale按钮 | 完成销售 | 创建已完成订单 |

### 1.2 为Alice办理POS销售 (主线测试)

**测试用例 1.2.1: 绑定会员的POS销售**

| 步骤 | 操作 | 预期结果 | 数据验证 |
|------|------|---------|---------|
| 1 | staff_cs 登录 | 进入长沙店Staff界面 | ShopID=1 |
| 2 | 访问POS页面 | 显示POS销售界面 | 显示搜索框和购物车 |
| 3 | 搜索"Abbey Road" | 显示Abbey Road可用库存 | New×2, Mint×1, VG+×1 |
| 4 | 点击New成色的Add Item | 购物车添加1件 | StockItemID=1或2 |
| 5 | 验证购物车显示 | Abbey Road New ¥56.00 | 小计¥56.00 |
| 6 | 选择客户"Alice Fan" | 客户绑定成功 | 显示VIP标签 |
| 7 | 验证折扣计算 | 5% VIP折扣 | 折扣¥2.80，应付¥53.20 |
| 8 | 点击Complete Sale | 销售完成 | 订单状态直接Completed |

**数据验证点：**
- CustomerOrder创建：OrderType='InStore', OrderStatus='Completed'
- StockItem状态变更：Available → Sold
- StockItem.DateSold设置为当前时间
- Alice积分增加：+53积分(floor(53.20))

**测试用例 1.2.2: 批量添加同规格商品**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 搜索"Dark Side" | 显示Dark Side可用库存 |
| 2 | 对New成色点击Add Multiple | 弹出数量输入框 |
| 3 | 输入数量2 | 添加2件到购物车 |
| 4 | 验证购物车 | 2×Dark Side New, 小计¥128.00 |
| 5 | 完成销售(绑定Bob) | Gold折扣10% |
| 6 | 验证最终金额 | ¥128 × 0.9 = ¥115.20 |

### 1.3 Walk-in顾客销售

**测试用例 1.3.1: 不绑定会员的销售**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 添加商品到购物车 | 购物车显示商品 |
| 2 | 客户选择保持空白/Walk-in | 不选择客户 |
| 3 | 验证无折扣 | 显示原价，无折扣行 |
| 4 | 完成销售 | 销售成功 |
| 5 | 验证订单 | CustomerID为NULL |
| 6 | 验证积分 | 无积分赠送 |

### 1.4 POS购物车操作测试

**测试用例 1.4.1: 移除单件商品**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 添加3件不同商品 | 购物车3件 |
| 2 | 点击中间商品的Remove | 该商品移除 |
| 3 | 验证购物车 | 剩余2件，金额更新 |

**测试用例 1.4.2: 清空购物车**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 添加多件商品 | 购物车有商品 |
| 2 | 点击Clear Cart | 确认对话框 |
| 3 | 确认清空 | 购物车为空 |

### 1.5 POS按钮功能汇总

| 按钮 | 功能 | POST Action | 预期行为 |
|------|------|-------------|---------|
| Search | 搜索库存 | search | 返回匹配的可用库存列表 |
| Add Item | 添加单件 | add_item | 购物车+1件 |
| Add Multiple | 批量添加 | add_multiple | 购物车+N件同规格 |
| Remove Item | 移除商品 | remove_item | 从购物车删除 |
| Clear Cart | 清空购物车 | clear_cart | 清空所有商品 |
| Complete Sale | 完成销售 | complete_sale | 创建订单并完成 |

---

## 2. Buyback回购测试

**测试路径:** `/public/staff/buyback.php`
**适用店铺:** 仅零售店(Retail) - 长沙店、上海店
**不适用:** 仓库(Warehouse) - 仓库员工无法访问回购功能

> **重要说明：** 回购入库的库存会添加到**处理回购的零售店**，而非仓库。回购是二手唱片的收购渠道，与采购不同。

### 2.1 Buyback表单元素

| 元素 | 类型 | 说明 |
|------|------|------|
| Customer Name | 下拉选择 | 可选择注册客户或Walk-in |
| Release Album | 下拉选择 | 所有专辑列表 |
| Condition | 下拉选择 | New/Mint/NM/VG+/VG |
| Quantity | 数字输入 | 回购数量 |
| Purchase Price | 数字输入 | 回购单价(支付给客户) |
| Resale Price | 数字输入 | 建议转售价 |
| Submit | 按钮 | 提交回购 |

### 2.2 Alice的回购操作 (主线测试)

**测试用例 2.2.1: 为Alice办理回购**

| 步骤 | 操作 | 预期结果 | 数据验证 |
|------|------|---------|---------|
| 1 | staff_sh 登录上海店 | 进入上海店Staff | ShopID=2 |
| 2 | 访问Buyback页面 | 显示回购表单 | 表单元素齐全 |
| 3 | 选择客户"Alice Fan" | 客户选择成功 | CustomerID=1 |
| 4 | 选择Album: Thriller | 专辑选择 | ReleaseID=3 |
| 5 | 选择Condition: VG | 成色选择 | ConditionGrade='VG' |
| 6 | 输入Quantity: 2 | 数量填写 | Quantity=2 |
| 7 | 输入Purchase Price: ¥10 | 回购单价 | UnitPrice=10 |
| 8 | 输入Resale Price: ¥20.63 | 转售价 | 匹配现有VG价格 |
| 9 | 点击Submit | 回购完成 | 成功消息显示 |

**数据验证点：**
- BuybackOrder创建：Status='Completed', TotalPayment=¥20
- BuybackOrderLine创建：Quantity=2, UnitPrice=¥10
- 生成2件StockItem：
  - ReleaseID=3（Thriller）
  - **ShopID=2（上海店，即处理回购的店铺）**
  - ConditionGrade='VG'
  - SourceType='Buyback'
  - SourceOrderID=BuybackOrderID
  - Status='Available'
  - UnitPrice=使用现有相同Release+Condition的价格，或根据输入的转售价
- Alice积分增加：+10 (floor(20×0.5))

**测试用例 2.2.2: 回购成功消息验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 完成回购后 | 显示成功消息 |
| 2 | 验证消息内容 | "Buyback completed! Order #X - Customer earned 10 points." |

### 2.3 Walk-in回购

**测试用例 2.3.1: 无会员绑定的回购**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | Customer选择Walk-in | 不绑定客户 |
| 2 | 填写其他回购信息 | 表单填写 |
| 3 | 提交回购 | 回购成功 |
| 4 | 验证积分 | 无积分赠送(CustomerID为NULL) |
| 5 | 验证库存生成 | 正常生成StockItem |

### 2.4 回购价格一致性测试

**测试用例 2.4.1: 回购已有价格的专辑**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 确认长沙店已有Opera VG+（Bob回购） | 现有价格¥40.32 |
| 2 | 在上海店回购Opera VG+ | 输入转售价¥45 |
| 3 | 提交回购 | 回购成功 |
| 4 | **验证新库存价格** | **应为¥40.32（使用现有价格）** |
| 5 | **验证入库店铺** | **ShopID=2（上海店）** |

**说明：** 系统应自动使用现有相同Release+Condition的价格，保证价格一致性。回购入库的库存归属于处理回购的店铺。

**初始回购数据参考：**
- BuybackOrderID=1：Bob在长沙店(ShopID=1)回购Opera VG+×3，入库到长沙店
- 生成库存ID 56-58，ShopID=1（长沙店），UnitPrice=¥40.32

### 2.5 回购按钮功能

| 按钮 | 功能 | 预期行为 |
|------|------|---------|
| Submit Buyback | 提交回购 | 创建BuybackOrder和StockItem |
| Clear/Reset | 重置表单 | 清空所有输入(如有) |

---

## 3. Fulfillment订单履行测试

**测试路径:** `/public/staff/fulfillment.php`

### 3.1 四个Tab页面测试

| Tab名称 | 功能 | 显示内容 |
|---------|------|---------|
| Customer Orders | 顾客订单发货 | Paid状态的运输订单 |
| Transfers | 调拨发货(作为源店) | Pending状态的调拨请求 |
| Receiving | 调拨收货(作为目标店) | InTransit状态的调拨 |
| Pickup Queue | 自提队列 | 在Pickup页面处理 |

### 3.2 Customer Orders Tab测试

**测试用例 3.2.1: 查看待发货订单**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | staff_wh 登录仓库 | 进入仓库Staff |
| 2 | 访问Fulfillment页面 | 默认Customer Orders Tab |
| 3 | 查看订单列表 | 显示FulfilledByShopID=3的Paid订单 |

**测试用例 3.2.2: 为Alice的订单发货 (主线)**

| 步骤 | 操作 | 预期结果 | 状态变化 |
|------|------|---------|---------|
| 1 | 找到Alice的待发货订单 | 显示订单信息 | OrderStatus=Paid |
| 2 | 点击Ship按钮 | 发货确认 | OrderStatus=Shipped |
| 3 | 点击Complete按钮 | 送达确认 | OrderStatus=Completed |
| 4 | 验证Alice积分 | 积分增加 | 触发器自动处理 |

### 3.3 Transfers Tab测试（源店发货）

**测试用例 3.3.1: 确认调拨发货**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | Admin批准调货申请(从仓库到长沙) | Transfer状态Pending |
| 2 | staff_wh 访问Fulfillment→Transfers | 显示待发货调拨 |
| 3 | 验证显示内容 | 调拨ID、专辑、数量、目标店 |
| 4 | 点击Confirm Transfer | 发货确认 |
| 5 | 验证状态变化 | Transfer=InTransit, Stock=InTransit |

**测试用例 3.3.2: 取消调拨**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 找到待发货调拨 | 显示Cancel按钮 |
| 2 | 点击Cancel | 确认对话框 |
| 3 | 确认取消 | 调拨取消 |
| 4 | 验证库存 | 保持Available状态 |

### 3.4 Receiving Tab测试（目标店收货）

**测试用例 3.4.1: 确认调拨收货**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 源店已确认发货 | Transfer=InTransit |
| 2 | staff_cs 访问Fulfillment→Receiving | 显示待收货调拨 |
| 3 | 验证显示内容 | 调拨ID、专辑、数量、来源店 |
| 4 | 点击Receive Transfer | 收货确认 |
| 5 | 验证状态变化 | Transfer=Completed |
| 6 | 验证库存变化 | Stock.ShopID从源店→目标店 |
| 7 | 验证库存状态 | Status=Available |

### 3.5 Fulfillment按钮功能汇总

| Tab | 按钮 | 功能 | 状态变化 |
|-----|------|------|---------|
| Customer Orders | Ship | 发货 | Order: Paid→Shipped |
| Customer Orders | Complete | 完成 | Order: Shipped→Completed, Stock→Sold |
| Transfers | Confirm | 确认发货 | Transfer: Pending→InTransit |
| Transfers | Cancel | 取消调拨 | Transfer: 删除或Cancelled |
| Receiving | Receive | 确认收货 | Transfer: InTransit→Completed |

---

## 4. Pickup自提处理测试

**测试路径:** `/public/staff/pickup.php`
**适用店铺:** 零售店(Retail)

### 4.1 Pickup Queue界面

**显示内容：**
- 订单号
- "Ready for Pickup"徽章
- 客户名称
- 订单日期
- Mark as Collected按钮

### 4.2 Alice自提订单处理 (主线)

**测试用例 4.2.1: 处理Alice的自提订单**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | Alice下单选择Pickup并支付 | OrderStatus=Paid, FulfillmentType=Pickup |
| 2 | staff_cs 访问Pickup页面 | 显示Alice的订单 |
| 3 | 验证订单卡片信息 | 订单号、Alice Fan、日期 |
| 4 | 点击Mark as Collected | 自提完成 |
| 5 | 验证订单状态 | OrderStatus=Completed |
| 6 | 验证库存状态 | Reserved→Sold |
| 7 | 验证积分 | Alice积分增加 |

### 4.3 历史自提记录

**测试用例 4.3.1: 查看自提历史**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 完成自提后 | 订单从Queue移除 |
| 2 | 查看历史记录表格 | 显示已完成的自提订单 |
| 3 | 验证显示内容 | 订单号、日期、客户、件数、金额 |

---

## 5. Inventory库存查询测试

**测试路径:** `/public/staff/inventory.php`

### 5.1 两种视图模式

| 模式 | 功能 | 显示内容 |
|------|------|---------|
| Summary | 汇总视图 | 按Release+Condition分组统计 |
| Detail | 明细视图 | 逐件显示库存 |

### 5.2 Summary视图测试

**测试用例 5.2.1: 汇总视图验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | staff_cs 访问Inventory | 默认Summary视图 |
| 2 | 查看Abbey Road行 | 显示分组统计 |
| 3 | 验证New成色 | 数量2件，价格¥56.00 |
| 4 | 验证Mint成色 | 数量1件，价格¥53.20 |
| 5 | 验证低库存标识 | <3件显示红色徽章 |

**显示列：**
- 专辑名称
- 流派
- 成色
- 数量(带颜色)
- 价格范围
- 平均价格

### 5.3 Detail视图测试

**测试用例 5.3.1: 明细视图验证**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 切换到Detail视图 | 显示逐件库存 |
| 2 | 验证单件信息 | BatchNo、专辑、成色、售价 |
| 3 | 验证天数显示 | >60天显示警告 |
| 4 | 验证状态显示 | Available/Reserved/InTransit |

**显示列：**
- 批号(BatchNo)
- 专辑名称
- 成色
- 售价
- 入库天数
- 状态

### 5.4 筛选功能测试

**测试用例 5.4.1: 按成色筛选**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 选择筛选条件"New" | 筛选应用 |
| 2 | 验证结果 | 只显示New成色库存 |
| 3 | 清除筛选 | 恢复显示所有 |

**测试用例 5.4.2: 搜索功能**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 搜索"Abbey" | 应用搜索 |
| 2 | 验证结果 | 只显示Abbey Road库存 |

---

## 6. 权限边界测试

### 6.1 仓库Staff限制测试

**测试用例 6.1.1: 仓库Staff无法访问POS**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | staff_wh 登录 | 进入仓库Staff界面 |
| 2 | 直接访问 /staff/pos.php | 拒绝访问或重定向 |
| 3 | 验证错误提示 | "POS is only available at retail locations" |

**测试用例 6.1.2: 仓库Staff无法访问Buyback**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | staff_wh 登录 | 进入仓库Staff界面 |
| 2 | 直接访问 /staff/buyback.php | 拒绝访问或重定向 |
| 3 | 验证错误提示 | "Buyback is only available at retail locations" |

### 6.2 只能查看本店数据

**测试用例 6.2.1: 库存只显示本店**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | staff_cs 登录长沙店 | 进入长沙店Staff |
| 2 | 访问Inventory | 显示库存列表 |
| 3 | 验证显示 | 只有ShopID=1的库存 |
| 4 | 不应显示 | 上海店或仓库的库存 |

**测试用例 6.2.2: 订单只显示本店**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | staff_cs 访问Fulfillment | 显示订单列表 |
| 2 | 验证显示 | 只有FulfilledByShopID=1的订单 |

### 6.3 Staff无法访问Manager/Admin页面

**测试用例 6.3.1: Staff访问Manager页面**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | staff_cs 登录 | Staff身份 |
| 2 | 直接访问 /manager/dashboard.php | 拒绝访问 |
| 3 | 直接访问 /admin/requests.php | 拒绝访问 |

---

## 附录：Staff功能检查清单

### A. POS销售检查

- [ ] 搜索库存功能正常
- [ ] Add Item添加单件成功
- [ ] Add Multiple批量添加成功
- [ ] Remove Item移除商品成功
- [ ] Clear Cart清空购物车成功
- [ ] 客户选择功能正常
- [ ] VIP折扣5%计算正确
- [ ] Gold折扣10%计算正确
- [ ] Walk-in无折扣
- [ ] Complete Sale创建Completed订单
- [ ] 绑定客户积分增加
- [ ] 库存状态变为Sold

### B. Buyback回购检查

- [ ] 客户选择功能正常
- [ ] 专辑/成色选择正常
- [ ] 数量/价格输入正常
- [ ] 提交回购成功
- [ ] 生成正确的StockItem
- [ ] 绑定客户获得积分
- [ ] Walk-in无积分
- [ ] 价格一致性检查生效

### C. Fulfillment检查

- [ ] Customer Orders Tab显示正确
- [ ] Ship按钮状态变更正确
- [ ] Complete按钮状态变更正确
- [ ] Transfers Tab显示待发调拨
- [ ] Confirm Transfer状态变更正确
- [ ] Receiving Tab显示待收调拨
- [ ] Receive Transfer状态和ShopID变更正确

### D. 权限检查

- [ ] 仓库Staff无法访问POS
- [ ] 仓库Staff无法访问Buyback
- [ ] 只能看到本店库存
- [ ] 只能看到本店订单
- [ ] 无法访问Manager页面
- [ ] 无法访问Admin页面

---

**文档结束**
