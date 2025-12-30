# Admin 端功能测试方案

> 版本: 2.0
> 关联主文件: [WEB_TESTING_PLAN.md](WEB_TESTING_PLAN.md)

---

## 目录

1. [Users用户管理测试](#1-users用户管理测试)
2. [Products产品管理测试](#2-products产品管理测试)
3. [Suppliers供应商管理测试](#3-suppliers供应商管理测试)
4. [Procurement采购管理测试](#4-procurement采购管理测试)
5. [Requests申请审批测试](#5-requests申请审批测试)
6. [Warehouse Dispatch仓库调配测试](#6-warehouse-dispatch仓库调配测试)

---

## 1. Users用户管理测试

**测试路径:** `/public/admin/users.php`

### 1.1 界面Tab测试

| Tab名称 | 显示内容 |
|---------|---------|
| Employees | 员工列表（管理员、经理、员工） |
| Customers | 客户列表（注册会员） |

### 1.2 Employees Tab测试

**显示列：**
- 姓名/用户名
- 角色(Admin/Manager/Staff)
- 所属店铺
- 入职日期
- Actions按钮

**测试用例 1.2.1: 查看员工列表**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | admin登录 | 进入Admin界面 |
| 2 | 访问Users页面 | 默认Employees Tab |
| 3 | 验证员工数量 | 7名员工 |
| 4 | 验证admin行 | Super Admin, Admin, 长沙店 |
| 5 | 验证manager_cs行 | Changsha Manager, Manager, 长沙店 |

**测试用例 1.2.2: 添加新员工**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击New Employee | 弹出模态框 |
| 2 | 填写Name: New Staff | 输入成功 |
| 3 | 填写Username: new_staff | 输入成功 |
| 4 | 填写Password: password123 | 输入成功 |
| 5 | 选择Role: Staff | 角色选择 |
| 6 | 选择Shop: Shanghai Branch | 店铺选择 |
| 7 | 点击Save | 员工创建成功 |
| 8 | 验证列表 | 新员工显示 |
| 9 | 用新账号登录 | 登录成功，权限正确 |

**测试用例 1.2.3: 编辑员工信息**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击某员工的Edit | 弹出编辑模态框 |
| 2 | 修改Name | 名称变更 |
| 3 | 修改Shop | 店铺变更 |
| 4 | 点击Save | 保存成功 |
| 5 | 验证更新 | 列表显示新信息 |

**测试用例 1.2.4: 删除员工**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击某员工的Delete | 确认对话框 |
| 2 | 确认删除 | 员工删除 |
| 3 | 验证列表 | 该员工不再显示 |

**测试用例 1.2.5: 防止删除自己**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 尝试删除admin账号 | 点击Delete |
| 2 | 验证结果 | 显示错误"Cannot delete your own account" |
| 3 | 验证账号 | admin仍存在 |

### 1.3 Customers Tab测试

**显示列：**
- 姓名
- Email
- 等级(Tier)
- 当前积分
- 注册日期
- Actions按钮

**测试用例 1.3.1: 查看客户列表**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 切换到Customers Tab | 显示客户列表 |
| 2 | 验证Alice Fan行 | alice@test.com, VIP, 1500积分 |
| 3 | 验证Bob Collector行 | bob@test.com, Gold, 6200积分 |

### 1.4 Users按钮功能汇总

| 按钮 | 功能 | 位置 |
|------|------|------|
| New Employee | 添加员工 | 页面顶部 |
| Edit | 编辑信息 | 每行Actions |
| Delete | 删除用户 | 每行Actions |
| Tab切换 | Employees/Customers | 页面顶部 |

---

## 2. Products产品管理测试

**测试路径:** `/public/admin/products.php`

### 2.1 产品列表显示

**显示列：**
- 专辑名称
- 艺术家
- 流派
- 基础成本(BaseUnitCost)
- Actions按钮

**测试用例 2.1.1: 查看产品列表**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 访问Products页面 | 显示专辑列表 |
| 2 | 验证专辑数量 | 15张专辑 |
| 3 | 验证Abbey Road行 | The Beatles, Rock, ¥35.00 |
| 4 | 验证Thriller行 | Michael Jackson, Pop, ¥25.00 |

### 2.2 添加新专辑

**测试用例 2.2.1: 创建新专辑**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击New Release | 弹出模态框 |
| 2 | 填写Title: Test Album | 输入成功 |
| 3 | 填写Artist: Test Artist | 输入成功 |
| 4 | 填写Label: Test Label | 输入成功 |
| 5 | 填写Year: 2024 | 输入成功 |
| 6 | 选择Genre: Rock | 选择成功 |
| 7 | 填写Description | 输入成功 |
| 8 | 填写BaseUnitCost: 30.00 | 输入成功 |
| 9 | 点击Create | 专辑创建成功 |
| 10 | 验证列表 | 新专辑显示 |

### 2.3 编辑专辑信息

**测试用例 2.3.1: 修改专辑基本信息**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击某专辑的Edit Release | 弹出编辑模态框 |
| 2 | 修改Description | 描述变更 |
| 3 | 点击Save | 保存成功 |

### 2.4 价格管理

**测试用例 2.4.1: 编辑专辑价格**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击某专辑的Edit Prices | 弹出价格编辑界面 |
| 2 | 查看成色行 | 显示New/Mint/NM/VG+/VG等 |
| 3 | 每行显示 | 各店库存情况和当前价格 |
| 4 | 修改某成色价格 | 输入新价格 |
| 5 | 点击Update | 价格更新 |
| 6 | **验证所有同规格库存** | **价格同步更新** |

### 2.5 Products按钮功能汇总

| 按钮 | 功能 | 作用 |
|------|------|------|
| New Release | 添加专辑 | 创建新ReleaseAlbum记录 |
| Edit Release | 编辑信息 | 修改专辑基本信息 |
| Edit Prices | 编辑价格 | 按成色管理库存价格 |
| Update | 确认更新 | 保存价格变更 |

---

## 3. Suppliers供应商管理测试

**测试路径:** `/public/admin/suppliers.php`

### 3.1 供应商列表

**显示列：**
- ID
- 公司名称
- 联系Email
- Actions按钮

**测试用例 3.1.1: 查看供应商列表**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 访问Suppliers页面 | 显示供应商列表 |
| 2 | 验证数量 | 4个供应商 |
| 3 | 验证Sony Music CN | sales@sonymusic.cn |
| 4 | 验证Universal Records | contact@universal.com |

### 3.2 添加供应商

**测试用例 3.2.1: 创建新供应商**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 点击New Supplier | 弹出模态框 |
| 2 | 填写Name: New Supplier | 输入成功 |
| 3 | 填写Email: new@supplier.com | 输入成功 |
| 4 | 点击Save | 供应商创建成功 |

### 3.3 删除供应商

**测试用例 3.3.1: 删除无订单的供应商**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 创建一个新供应商 | 供应商创建 |
| 2 | 点击Delete | 确认对话框 |
| 3 | 确认删除 | 供应商删除成功 |

**测试用例 3.3.2: 无法删除有订单的供应商**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 尝试删除Sony Music CN | 点击Delete |
| 2 | 验证结果 | 显示错误"供应商有关联订单，无法删除" |

---

## 4. Procurement采购管理测试

**测试路径:** `/public/admin/procurement.php`

### 4.1 采购界面显示

**显示内容：**
- Pending Orders列表（待确认订单）
- Create New PO表单

### 4.2 创建采购订单

**测试用例 4.2.1: 创建新采购订单**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 进入Procurement页面 | 显示采购界面 |
| 2 | 选择Release: Abbey Road | 专辑选择 |
| 3 | 选择Condition: New | 成色选择 |
| 4 | 验证Unit Cost显示 | 自动计算: ¥35×1.0=¥35 |
| 5 | 输入Quantity: 5 | 数量填写 |
| 6 | 验证Sale Price显示 | 建议售价¥56 (35×1.6) |
| 7 | 选择Destination: 长沙店 | 目标店铺 |
| 8 | 选择Supplier: Sony Music | 供应商选择 |
| 9 | 点击Create PO | 订单创建 |
| 10 | 验证Pending列表 | 新订单显示 |

**成本计算规则：**
```
Unit Cost = BaseUnitCost × Condition系数
Condition系数: New=1.0, Mint=0.95, NM=0.85, VG+=0.70, VG=0.55
```

**建议售价计算规则：**
```
成本≤¥20: ×1.50
成本¥21-50: ×1.60
成本¥51-100: ×1.70
成本>¥100: ×1.80
```

### 4.3 接收采购订单

**测试用例 4.3.1: 接收订单生成库存**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 找到Pending状态订单 | 显示订单详情 |
| 2 | 点击Receive Order | 接收处理 |
| 3 | 验证订单状态 | Pending → Received |
| 4 | 验证ReceivedDate | 设置为当前时间 |
| 5 | **验证库存生成** | **生成5件StockItem** |

**生成的StockItem验证：**
- ReleaseID = 专辑ID
- ShopID = 目标店铺ID
- ConditionGrade = 选择的成色
- UnitPrice = 售价
- SourceType = 'Supplier'
- SourceOrderID = 采购订单ID
- Status = 'Available'

### 4.4 采购价格一致性

**测试用例 4.4.1: 新采购更新现有库存价格**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 确认长沙店Abbey Road New当前价格 | ¥56.00 |
| 2 | 创建采购: Abbey Road New, 售价¥60 | 订单创建 |
| 3 | 接收订单 | 生成新库存 |
| 4 | **验证所有Abbey Road New价格** | **全部变为¥60** |

**说明：** 存储过程 `sp_receive_supplier_order` 自动更新同Release+Condition的现有库存价格，保证价格一致性。

### 4.5 Procurement按钮功能

| 按钮 | 功能 | 作用 |
|------|------|------|
| Create PO | 创建采购订单 | 生成SupplierOrder记录 |
| View Detail | 查看订单详情 | 展开订单行信息 |
| Receive Order | 接收订单 | 状态变更+生成库存 |

---

## 5. Requests申请审批测试

**测试路径:** `/public/admin/requests.php`

### 5.1 申请列表显示

**过滤菜单：**
- All Requests
- Pending (待审批)
- Approved (已批准)
- Rejected (已驳回)

**申请卡片显示：**
- 申请类型徽章(Price蓝色/Transfer紫色)
- 专辑 - 艺术家
- 成色 + 数量
- 价格信息(调价) 或 源店信息(调货)
- 理由说明
- 状态徽章 + 创建日期
- 审批操作按钮

### 5.2 调价申请审批

**测试用例 5.2.1: 批准调价申请**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs提交调价申请 | Abbey Road New ¥56→¥45, 数量2 |
| 2 | admin访问Requests | 显示Pending申请 |
| 3 | 展开申请详情 | 显示完整信息 |
| 4 | 验证当前价格 | ¥56.00 |
| 5 | 验证目标价格 | ¥45.00 |
| 6 | 输入审批意见 | "同意促销" |
| 7 | 点击Approve | 申请批准 |
| 8 | 验证申请状态 | Approved |
| 9 | **验证库存价格** | **2件Abbey Road New变为¥45** |

**测试用例 5.2.2: 拒绝调价申请**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 找到待审批调价申请 | 显示申请 |
| 2 | 输入拒绝理由 | "价格过低" |
| 3 | 点击Reject | 申请拒绝 |
| 4 | 验证状态 | Rejected |
| 5 | 验证库存价格 | **保持不变** |

### 5.3 调货申请审批

**测试用例 5.3.1: 批准调货申请**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | manager_cs提交调货申请 | Opera New×2到长沙店 |
| 2 | admin访问Requests | 显示申请 |
| 3 | 展开申请详情 | 显示其他店铺库存 |
| 4 | 查看"Available Stock" | 仓库有2件Opera New |
| 5 | 选择Source Shop: 仓库 | 选择源店 |
| 6 | 点击Approve | 申请批准 |
| 7 | 验证申请状态 | Approved |
| 8 | **验证Transfer创建** | **生成2条InventoryTransfer** |
| 9 | 验证Transfer状态 | Pending(等待发货) |

**测试用例 5.3.2: 库存不足无法批准**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 提交调货申请: 数量10 | 申请创建 |
| 2 | admin查看申请 | 显示其他店铺库存 |
| 3 | 源店只有3件可用 | 库存不足 |
| 4 | 尝试批准 | 显示错误"Source shop only has 3 available items" |

### 5.4 申请审批按钮功能

| 按钮 | 功能 | 适用场景 |
|------|------|---------|
| Approve | 批准申请 | 调价/调货 |
| Reject | 拒绝申请 | 调价/调货 |
| Source Shop下拉 | 选择源店 | 仅调货申请 |
| Filter菜单 | 筛选申请 | All/Pending/Approved/Rejected |

---

## 6. Warehouse Dispatch仓库调配测试

**测试路径:** `/public/admin/warehouse_dispatch.php`

### 6.1 调配界面

**功能：** 将仓库库存直接调配至零售店（无需Manager申请）

**表单字段：**
- Release (下拉选择)
- Condition Grade (下拉选择)
- Quantity (数字输入)
- Target Shop (下拉，仅显示零售店)

### 6.2 直接调配测试

**测试用例 6.2.1: 从仓库调配库存**

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 选择Release: Hotel California | 专辑选择 |
| 2 | 选择Condition: New | 成色选择 |
| 3 | 输入Quantity: 1 | 数量填写 |
| 4 | 选择Target Shop: 长沙店 | 目标店铺 |
| 5 | 点击Dispatch Stock | 调配执行 |
| 6 | 验证成功消息 | "Successfully dispatched 1 item(s) to Changsha Flagship Store" |
| 7 | 验证Transfer创建 | 生成InventoryTransfer(Pending) |

### 6.3 库存表格显示

**按Condition分组显示：**
- Condition
- Available Qty
- Unit Price
- Actions

---

## 附录：Admin功能检查清单

### A. Users管理检查

- [ ] 查看员工列表正确
- [ ] 添加新员工成功
- [ ] 编辑员工信息成功
- [ ] 删除员工成功
- [ ] 无法删除自己
- [ ] 查看客户列表正确

### B. Products管理检查

- [ ] 查看专辑列表正确
- [ ] 添加新专辑成功
- [ ] 编辑专辑信息成功
- [ ] 编辑价格功能正常
- [ ] 价格更新同步所有库存

### C. Suppliers管理检查

- [ ] 查看供应商列表正确
- [ ] 添加新供应商成功
- [ ] 编辑供应商成功
- [ ] 删除无订单供应商成功
- [ ] 无法删除有订单供应商

### D. Procurement采购检查

- [ ] 创建采购订单成功
- [ ] 成本自动计算正确
- [ ] 建议售价计算正确
- [ ] 接收订单生成库存
- [ ] 采购时价格同步更新

### E. Requests审批检查

- [ ] 查看待审批申请
- [ ] 批准调价申请成功
- [ ] 批准后价格更新
- [ ] 拒绝申请成功
- [ ] 批准调货申请成功
- [ ] 批准后创建Transfer
- [ ] 库存不足提示正确

### F. Warehouse Dispatch检查

- [ ] 直接调配库存成功
- [ ] 创建Transfer记录正确
- [ ] 成功消息显示正确

---

**文档结束**
