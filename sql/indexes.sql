-- ========================================
-- Indexes for Performance Optimization
-- 索引 - 优化查询性能
-- ========================================

-- ================================================
-- 1. ReleaseAlbum 表索引
-- ================================================

-- 按艺术家搜索
CREATE INDEX idx_release_artist ON ReleaseAlbum(ArtistName);

-- 按流派搜索
CREATE INDEX idx_release_genre ON ReleaseAlbum(Genre);

-- 按发行年份搜索
CREATE INDEX idx_release_year ON ReleaseAlbum(ReleaseYear);

-- 组合索引：流派+年份（用于分类浏览）
CREATE INDEX idx_release_genre_year ON ReleaseAlbum(Genre, ReleaseYear);

-- ================================================
-- 2. StockItem 表索引（核心业务表）
-- ================================================

-- 按Release查找库存
CREATE INDEX idx_stock_release ON StockItem(ReleaseID);

-- 按Shop查找库存
CREATE INDEX idx_stock_shop ON StockItem(ShopID);

-- 按状态查找
CREATE INDEX idx_stock_status ON StockItem(Status);

-- 组合索引：Shop + Status（查找店内可用库存）
CREATE INDEX idx_stock_shop_status ON StockItem(ShopID, Status);

-- 组合索引：Release + Shop + Status（库存汇总查询核心索引）
CREATE INDEX idx_stock_release_shop_status ON StockItem(ReleaseID, ShopID, Status);

-- 按来源类型查找
CREATE INDEX idx_stock_source ON StockItem(SourceType, SourceOrderID);

-- 按批次号查找
CREATE INDEX idx_stock_batch ON StockItem(BatchNo);

-- 按售出日期查找（用于周转率分析）
CREATE INDEX idx_stock_datesold ON StockItem(DateSold);

-- ================================================
-- 3. CustomerOrder 表索引
-- ================================================

-- 按客户查找订单
CREATE INDEX idx_order_customer ON CustomerOrder(CustomerID);

-- 按店铺查找订单
CREATE INDEX idx_order_shop ON CustomerOrder(FulfilledByShopID);

-- 按订单状态查找
CREATE INDEX idx_order_status ON CustomerOrder(OrderStatus);

-- 组合索引：客户 + 状态（查看客户订单历史）
CREATE INDEX idx_order_customer_status ON CustomerOrder(CustomerID, OrderStatus);

-- 组合索引：店铺 + 状态（店铺订单管理）
CREATE INDEX idx_order_shop_status ON CustomerOrder(FulfilledByShopID, OrderStatus);

-- 按订单日期排序
CREATE INDEX idx_order_date ON CustomerOrder(OrderDate DESC);

-- 按订单类型查找
CREATE INDEX idx_order_type ON CustomerOrder(OrderType);

-- ================================================
-- 4. Customer 表索引
-- ================================================

-- 按邮箱查找（登录）- 已有UNIQUE约束自动创建索引
-- CREATE UNIQUE INDEX idx_customer_email ON Customer(Email);

-- 按会员等级查找
CREATE INDEX idx_customer_tier ON Customer(TierID);

-- 按积分排序（用于排行榜）
CREATE INDEX idx_customer_points ON Customer(Points DESC);

-- 1. 先在 Customer 表中添加一个虚拟列，计算生日月份
ALTER TABLE Customer ADD COLUMN b_month INT AS (MONTH(Birthday)) VIRTUAL;

-- 2. 对这个虚拟列创建普通索引
CREATE INDEX idx_customer_birthday_month ON Customer(b_month);

-- ================================================
-- 5. Employee 表索引
-- ================================================

-- 按用户名查找（登录）- 已有UNIQUE约束自动创建索引
-- CREATE UNIQUE INDEX idx_employee_username ON Employee(Username);

-- 按店铺查找员工
CREATE INDEX idx_employee_shop ON Employee(ShopID);

-- 按角色查找
CREATE INDEX idx_employee_role ON Employee(Role);

-- ================================================
-- 6. SupplierOrder 表索引
-- ================================================

-- 按供应商查找订单
CREATE INDEX idx_supplier_order_supplier ON SupplierOrder(SupplierID);

-- 按状态查找
CREATE INDEX idx_supplier_order_status ON SupplierOrder(Status);

-- 按创建员工查找
CREATE INDEX idx_supplier_order_employee ON SupplierOrder(CreatedByEmployeeID);

-- 按目标店铺查找
CREATE INDEX idx_supplier_order_shop ON SupplierOrder(DestinationShopID);

-- 按订单日期排序
CREATE INDEX idx_supplier_order_date ON SupplierOrder(OrderDate DESC);

-- ================================================
-- 7. BuybackOrder 表索引
-- ================================================

-- 按客户查找回购记录
CREATE INDEX idx_buyback_customer ON BuybackOrder(CustomerID);

-- 按处理员工查找
CREATE INDEX idx_buyback_employee ON BuybackOrder(ProcessedByEmployeeID);

-- 按店铺查找
CREATE INDEX idx_buyback_shop ON BuybackOrder(ShopID);

-- 按状态查找
CREATE INDEX idx_buyback_status ON BuybackOrder(Status);

-- 按回购日期排序
CREATE INDEX idx_buyback_date ON BuybackOrder(BuybackDate DESC);

-- ================================================
-- 8. InventoryTransfer 表索引
-- ================================================

-- 按库存项查找调拨记录
CREATE INDEX idx_transfer_stock ON InventoryTransfer(StockItemID);

-- 按源店铺查找
CREATE INDEX idx_transfer_from ON InventoryTransfer(FromShopID);

-- 按目标店铺查找
CREATE INDEX idx_transfer_to ON InventoryTransfer(ToShopID);

-- 按状态查找
CREATE INDEX idx_transfer_status ON InventoryTransfer(Status);

-- 组合索引：状态 + 日期（待处理调拨）
CREATE INDEX idx_transfer_status_date ON InventoryTransfer(Status, TransferDate DESC);

-- 按授权员工查找
CREATE INDEX idx_transfer_auth_emp ON InventoryTransfer(AuthorizedByEmployeeID);

-- 按接收员工查找
CREATE INDEX idx_transfer_recv_emp ON InventoryTransfer(ReceivedByEmployeeID);

-- ================================================
-- 9. OrderLine 表索引
-- ================================================

-- 按订单查找订单行（复合主键已包含）
-- 按库存项查找（用于检查是否已售出）
CREATE INDEX idx_orderline_stock ON OrderLine(StockItemID);

-- ================================================
-- 10. Track 表索引
-- ================================================

-- 按Release查找曲目
CREATE INDEX idx_track_release ON Track(ReleaseID);

-- 按曲目编号排序
CREATE INDEX idx_track_number ON Track(TrackNumber);

-- ================================================
-- 11. SupplierOrderLine 表索引
-- ================================================

-- 按Release查找（查看某个专辑的进货记录）
CREATE INDEX idx_supplier_line_release ON SupplierOrderLine(ReleaseID);

-- ================================================
-- 12. BuybackOrderLine 表索引
-- ================================================

-- 按Release查找（查看某个专辑的回购记录）
CREATE INDEX idx_buyback_line_release ON BuybackOrderLine(ReleaseID);

-- ================================================
-- 性能优化说明
-- ================================================

/*
索引设计原则：
1. 主键和唯一约束会自动创建索引
2. 外键字段通常需要创建索引以加速JOIN操作
3. WHERE、ORDER BY、GROUP BY常用字段应创建索引
4. 高选择性字段（值分布广）更适合创建索引
5. 频繁更新的表要谨慎创建过多索引
6. 组合索引遵循最左前缀原则

查询优化建议：
- 使用 EXPLAIN 分析查询计划
- 避免在索引列上使用函数
- 合理使用覆盖索引减少回表
- 定期分析表统计信息：ANALYZE TABLE
- 监控慢查询日志
*/
