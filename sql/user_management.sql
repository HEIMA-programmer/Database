-- ========================================
-- Database User Management SQL
-- 数据库用户管理
-- ========================================
-- 四类用户：Admin, Manager, Staff, Customer
-- 匿名用户默认使用 Customer 账户
-- ========================================

-- ============================================================
-- 第一部分：创建数据库用户
-- ============================================================

-- Admin - 系统管理员
CREATE USER IF NOT EXISTS 'retro_admin'@'localhost' IDENTIFIED BY 'Admin@SecurePass123!';
CREATE USER IF NOT EXISTS 'retro_admin'@'%' IDENTIFIED BY 'Admin@SecurePass123!';

-- Manager - 店铺经理
CREATE USER IF NOT EXISTS 'retro_manager'@'localhost' IDENTIFIED BY 'Manager@SecurePass456!';
CREATE USER IF NOT EXISTS 'retro_manager'@'%' IDENTIFIED BY 'Manager@SecurePass456!';

-- Staff - 店员
CREATE USER IF NOT EXISTS 'retro_staff'@'localhost' IDENTIFIED BY 'Staff@SecurePass789!';
CREATE USER IF NOT EXISTS 'retro_staff'@'%' IDENTIFIED BY 'Staff@SecurePass789!';

-- Customer - 客户（前台用户 + 匿名用户默认账户）
CREATE USER IF NOT EXISTS 'retro_customer'@'localhost' IDENTIFIED BY 'Customer@SecurePass000!';
CREATE USER IF NOT EXISTS 'retro_customer'@'%' IDENTIFIED BY 'Customer@SecurePass000!';


-- ============================================================
-- 第二部分：创建角色 (MySQL 8.0+)
-- ============================================================

CREATE ROLE IF NOT EXISTS 'role_admin';
CREATE ROLE IF NOT EXISTS 'role_manager';
CREATE ROLE IF NOT EXISTS 'role_staff';
CREATE ROLE IF NOT EXISTS 'role_customer';


-- ============================================================
-- 第三部分：Admin 权限 - 完全控制
-- ============================================================

-- Admin 拥有数据库完全权限
GRANT ALL PRIVILEGES ON retro_echo.* TO 'role_admin';

-- 分配角色
GRANT 'role_admin' TO 'retro_admin'@'localhost', 'retro_admin'@'%';
SET DEFAULT ROLE 'role_admin' TO 'retro_admin'@'localhost', 'retro_admin'@'%';


-- ============================================================
-- 第四部分：Manager 权限
-- ============================================================

-- 4.1 员工信息视图
GRANT SELECT ON retro_echo.vw_employee_shop_info TO 'role_manager';

-- 4.2 报表与统计视图
GRANT SELECT ON retro_echo.vw_shop_walk_in_revenue TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_inventory_cost TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_procurement_stats TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_inventory_by_release TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_kpi_stats TO 'role_manager';
GRANT SELECT ON retro_echo.vw_kpi_stats TO 'role_manager';

-- 4.3 销售分析视图
GRANT SELECT ON retro_echo.vw_shop_artist_profit_analysis TO 'role_manager';
GRANT SELECT ON retro_echo.vw_artist_sales_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_batch_sales_analysis TO 'role_manager';
GRANT SELECT ON retro_echo.vw_batch_sales_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_genre_sales_summary TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_monthly_sales_summary TO 'role_manager';
GRANT SELECT ON retro_echo.vw_sales_by_genre_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_monthly_sales_detail TO 'role_manager';

-- 4.4 Manager 报表视图
GRANT SELECT ON retro_echo.vw_manager_shop_performance TO 'role_manager';
GRANT SELECT ON retro_echo.vw_manager_requests_sent TO 'role_manager';
GRANT SELECT ON retro_echo.vw_report_sales_by_genre TO 'role_manager';
GRANT SELECT ON retro_echo.vw_report_top_customers TO 'role_manager';
GRANT SELECT ON retro_echo.vw_report_monthly_sales TO 'role_manager';

-- 4.5 库存管理视图
GRANT SELECT ON retro_echo.vw_stock_item_with_cost TO 'role_manager';
GRANT SELECT ON retro_echo.vw_low_stock_alert TO 'role_manager';
GRANT SELECT ON retro_echo.vw_low_stock_by_shop TO 'role_manager';
GRANT SELECT ON retro_echo.vw_dead_stock_alert TO 'role_manager';
GRANT SELECT ON retro_echo.vw_dead_stock_by_shop TO 'role_manager';
GRANT SELECT ON retro_echo.vw_popular_items TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_total_expense TO 'role_manager';

-- 4.6 客户与订单视图
GRANT SELECT ON retro_echo.vw_customer_shop_orders TO 'role_manager';
GRANT SELECT ON retro_echo.vw_customer_buyback_history TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_order_details TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_top_customers TO 'role_manager';

-- 4.7 共享的管理视图
GRANT SELECT ON retro_echo.vw_admin_employee_list TO 'role_manager';
GRANT SELECT ON retro_echo.vw_admin_customer_list TO 'role_manager';

-- 4.8 公共视图
GRANT SELECT ON retro_echo.vw_release_simple_list TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_list TO 'role_manager';

-- 4.9 登录认证视图
GRANT SELECT ON retro_echo.vw_auth_employee TO 'role_manager';

-- 4.10 Manager 可用的存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_delete_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_price_adjustment_request TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_transfer_request TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_mark_requests_viewed TO 'role_manager';

-- 分配角色
GRANT 'role_manager' TO 'retro_manager'@'localhost', 'retro_manager'@'%';
SET DEFAULT ROLE 'role_manager' TO 'retro_manager'@'localhost', 'retro_manager'@'%';


-- ============================================================
-- 第五部分：Staff 权限
-- ============================================================

-- 5.1 购物车相关视图
GRANT SELECT ON retro_echo.vw_cart_items_detail TO 'role_staff';

-- 5.2 员工信息视图
GRANT SELECT ON retro_echo.vw_employee_shop_info TO 'role_staff';

-- 5.3 POS 相关视图
GRANT SELECT ON retro_echo.vw_pos_stock_grouped TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_all_releases TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_available_stock_ids TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_cart_item_validation TO 'role_staff';

-- 5.4 订单和客户查询
GRANT SELECT ON retro_echo.vw_order_line_detail TO 'role_staff';
GRANT SELECT ON retro_echo.vw_order_for_pickup TO 'role_staff';
GRANT SELECT ON retro_echo.vw_order_shop_validation TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_lookup TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_simple_list TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_list_with_points TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_my_orders_list TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_profile_info TO 'role_staff';

-- 5.5 Fulfillment 视图
GRANT SELECT ON retro_echo.vw_fulfillment_pending_transfers_grouped TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_incoming_transfers_grouped TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_orders TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_order_status_counts TO 'role_staff';

-- 5.6 库存与调拨视图
GRANT SELECT ON retro_echo.vw_stock_summary TO 'role_staff';
GRANT SELECT ON retro_echo.vw_stock_detail TO 'role_staff';
GRANT SELECT ON retro_echo.vw_stock_price_map TO 'role_staff';
GRANT SELECT ON retro_echo.vw_transfer_validation TO 'role_staff';
GRANT SELECT ON retro_echo.vw_warehouse_pending_receipts TO 'role_staff';

-- 5.7 回购业务视图
GRANT SELECT ON retro_echo.vw_release_list_with_cost TO 'role_staff';
GRANT SELECT ON retro_echo.vw_recent_buybacks_detail TO 'role_staff';

-- 5.8 Staff 历史记录视图
GRANT SELECT ON retro_echo.vw_staff_pos_history TO 'role_staff';
GRANT SELECT ON retro_echo.vw_staff_pickup_history TO 'role_staff';

-- 5.9 header.php 通知视图
GRANT SELECT ON retro_echo.vw_staff_bopis_pending TO 'role_staff';

-- 5.10 登录认证视图
GRANT SELECT ON retro_echo.vw_auth_employee TO 'role_staff';

-- 5.11 Staff 可用的存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_receive_supplier_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_process_buyback TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_transfer TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_confirm_transfer_dispatch TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_order_status TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_pos_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_transfer TO 'role_staff';

-- 分配角色
GRANT 'role_staff' TO 'retro_staff'@'localhost', 'retro_staff'@'%';
SET DEFAULT ROLE 'role_staff' TO 'retro_staff'@'localhost', 'retro_staff'@'%';


-- ============================================================
-- 第六部分：Customer 权限（含匿名用户所需权限）
-- ============================================================

-- 6.1 购物车相关
GRANT SELECT ON retro_echo.vw_cart_item_validation TO 'role_customer';
GRANT SELECT ON retro_echo.vw_cart_items_detail TO 'role_customer';
GRANT SELECT ON retro_echo.vw_checkout_cart_items TO 'role_customer';

-- 6.2 商品浏览（库存查询）
GRANT SELECT ON retro_echo.vw_release_shop_stock_grouped TO 'role_customer';
GRANT SELECT ON retro_echo.vw_available_stock_ids TO 'role_customer';
GRANT SELECT ON retro_echo.vw_catalog_by_shop_grouped TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_stock_by_condition TO 'role_customer';

-- 6.3 订单相关
GRANT SELECT ON retro_echo.vw_customer_order_detail TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_order_history TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_my_orders_list TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_pending_order TO 'role_customer';
GRANT SELECT ON retro_echo.vw_order_shop_validation TO 'role_customer';
GRANT SELECT ON retro_echo.vw_order_reserved_items TO 'role_customer';

-- 6.4 专辑详情视图
GRANT SELECT ON retro_echo.vw_release_info TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_genres TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_tracks TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_basic TO 'role_customer';

-- 6.5 商品详情视图
GRANT SELECT ON retro_echo.vw_product_detail TO 'role_customer';
GRANT SELECT ON retro_echo.vw_product_alternatives TO 'role_customer';

-- 6.6 会员与个人资料
GRANT SELECT ON retro_echo.vw_membership_tier_rules TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_profile_info TO 'role_customer';

-- 6.7 公共视图
GRANT SELECT ON retro_echo.vw_shop_list TO 'role_customer';

-- 6.8 header.php 通知视图
GRANT SELECT ON retro_echo.vw_customer_shipped_delivery TO 'role_customer';

-- 6.9 登录认证视图（匿名用户登录需要）
GRANT SELECT ON retro_echo.vw_auth_customer TO 'role_customer';
GRANT SELECT ON retro_echo.vw_auth_employee TO 'role_customer';

-- 6.10 Customer 可用的存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_pay_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_release_expired_reservations TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_customer_profile TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_online_order_complete TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_get_stock_item_with_lock TO 'role_customer';

-- 6.11 匿名用户注册所需存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_register_customer TO 'role_customer';

-- 6.12 登录后更新 session（匿名用户登录员工账户时需要）
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee TO 'role_customer';

-- 分配角色
GRANT 'role_customer' TO 'retro_customer'@'localhost', 'retro_customer'@'%';
SET DEFAULT ROLE 'role_customer' TO 'retro_customer'@'localhost', 'retro_customer'@'%';


-- ============================================================
-- 第七部分：权限管理常用命令
-- ============================================================

-- 查看用户权限
-- SHOW GRANTS FOR 'retro_customer'@'localhost';
-- SHOW GRANTS FOR 'role_customer';

-- 撤销权限示例
-- REVOKE SELECT ON retro_echo.vw_customer_order_history FROM 'role_customer';

-- 修改密码
-- ALTER USER 'retro_customer'@'localhost' IDENTIFIED BY 'NewPassword123!';

-- 锁定/解锁账户
-- ALTER USER 'retro_customer'@'localhost' ACCOUNT LOCK;
-- ALTER USER 'retro_customer'@'localhost' ACCOUNT UNLOCK;

-- 删除用户
-- DROP USER IF EXISTS 'retro_customer'@'localhost';

-- 删除角色
-- DROP ROLE IF EXISTS 'role_customer';


-- ============================================================
-- 第八部分：架构说明
-- ============================================================
/*
连接切换架构：

┌─────────────────────────────────────────────────────────────────┐
│  初始状态（匿名用户）                                            │
│  └── 使用 retro_customer 账户连接                               │
│      - 可以浏览商品目录                                          │
│      - 可以注册新账户 (sp_register_customer)                     │
│      - 可以登录 (vw_auth_customer, vw_auth_employee)            │
├─────────────────────────────────────────────────────────────────┤
│  登录后（根据角色切换连接）                                       │
│  ├── Customer 登录 → 保持 retro_customer 连接                   │
│  ├── Staff 登录    → 切换到 retro_staff 连接                    │
│  ├── Manager 登录  → 切换到 retro_manager 连接                  │
│  └── Admin 登录    → 切换到 retro_admin 连接                    │
└─────────────────────────────────────────────────────────────────┘

权限分配说明（基于 PHP 调用统计）：

┌─────────────────────────────────────────────────────────────────┐
│  Admin (role_admin)                                             │
│  └── 完全权限：ALL PRIVILEGES                                    │
├─────────────────────────────────────────────────────────────────┤
│  Manager (role_manager)                                         │
│  └── 视图：店铺报表、销售分析、库存预警、员工/客户列表等          │
│  └── 存储：员工管理、调价/调货请求等                             │
├─────────────────────────────────────────────────────────────────┤
│  Staff (role_staff)                                             │
│  └── 视图：POS、履约、库存、回购、客户查询等                     │
│  └── 存储：订单处理、调货确认、回购处理等                        │
├─────────────────────────────────────────────────────────────────┤
│  Customer (role_customer) - 同时作为匿名用户默认账户             │
│  └── 视图：商品目录、购物车、订单、个人资料、认证视图             │
│  └── 存储：下单、支付、注册、资料更新等                          │
│  └── 特殊：包含 vw_auth_employee 用于员工登录验证                │
└─────────────────────────────────────────────────────────────────┘

注意事项：
- Customer 账户包含 vw_auth_employee 权限，仅用于登录验证
- 员工登录成功后应立即切换到对应角色的数据库连接
- 视图内部的 WHERE 条件实现行级数据隔离
*/


-- ============================================================
-- 应用权限更改
-- ============================================================
FLUSH PRIVILEGES;

SELECT '数据库用户权限配置完成！' AS Message;
