-- ========================================
-- Database User Management SQL Examples
-- 数据库用户管理 SQL 语句示例
-- ========================================
-- 基于现有视图和存储过程配置访问权限
-- 四类用户：Admin, Manager, Staff, Customer
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

-- Customer - 客户（前台用户）
CREATE USER IF NOT EXISTS 'retro_customer'@'localhost' IDENTIFIED BY 'Customer@SecurePass000!';
CREATE USER IF NOT EXISTS 'retro_customer'@'%' IDENTIFIED BY 'Customer@SecurePass000!';

-- App - 应用程序专用账户（PHP连接用）
CREATE USER IF NOT EXISTS 'retro_app'@'localhost' IDENTIFIED BY 'App@SecurePass321!';
CREATE USER IF NOT EXISTS 'retro_app'@'%' IDENTIFIED BY 'App@SecurePass321!';


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
-- 第四部分：Manager 权限 - 基于 PHP 调用统计
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

-- 4.7 共享的管理视图（Admin 也使用）
GRANT SELECT ON retro_echo.vw_admin_employee_list TO 'role_manager';
GRANT SELECT ON retro_echo.vw_admin_customer_list TO 'role_manager';

-- 4.8 公共视图
GRANT SELECT ON retro_echo.vw_release_simple_list TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_list TO 'role_manager';

-- 4.9 Manager 可用的存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_delete_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_price_adjustment_request TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_transfer_request TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_mark_requests_viewed TO 'role_manager';

-- 4.10 login.php 公共调用 - 员工认证（Manager 需要登录）
GRANT SELECT ON retro_echo.vw_auth_employee TO 'role_manager';

-- 分配角色
GRANT 'role_manager' TO 'retro_manager'@'localhost', 'retro_manager'@'%';
SET DEFAULT ROLE 'role_manager' TO 'retro_manager'@'localhost', 'retro_manager'@'%';


-- ============================================================
-- 第五部分：Staff 权限 - 日常操作
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

-- 5.9 Staff 可用的存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_receive_supplier_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_process_buyback TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_transfer TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_confirm_transfer_dispatch TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_order_status TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_pos_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_transfer TO 'role_staff';

-- 5.10 header.php 公共调用 - Staff 专属通知
GRANT SELECT ON retro_echo.vw_staff_bopis_pending TO 'role_staff';

-- 5.11 login.php 公共调用 - 员工认证（Staff 需要登录）
GRANT SELECT ON retro_echo.vw_auth_employee TO 'role_staff';

-- 分配角色
GRANT 'role_staff' TO 'retro_staff'@'localhost', 'retro_staff'@'%';
SET DEFAULT ROLE 'role_staff' TO 'retro_staff'@'localhost', 'retro_staff'@'%';


-- ============================================================
-- 第六部分：Customer 权限 - 前台客户
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

-- 6.8 Customer 可用的存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_pay_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_release_expired_reservations TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_customer_profile TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_online_order_complete TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_get_stock_item_with_lock TO 'role_customer';

-- 6.9 header.php 公共调用 - Customer 专属通知
GRANT SELECT ON retro_echo.vw_customer_shipped_delivery TO 'role_customer';

-- 6.10 login.php 公共调用 - 客户认证
GRANT SELECT ON retro_echo.vw_auth_customer TO 'role_customer';

-- 分配角色
GRANT 'role_customer' TO 'retro_customer'@'localhost', 'retro_customer'@'%';
SET DEFAULT ROLE 'role_customer' TO 'retro_customer'@'localhost', 'retro_customer'@'%';


-- ============================================================
-- 第七部分：应用程序账户权限
-- ============================================================
-- PHP 应用使用此账户连接数据库
-- 需要足够权限执行所有业务操作，但不能修改数据库结构
-- 公共 PHP（如 register.php）需要的权限由应用账户提供

-- 所有表的 CRUD 权限
GRANT SELECT, INSERT, UPDATE, DELETE ON retro_echo.* TO 'retro_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON retro_echo.* TO 'retro_app'@'%';

-- 所有存储过程执行权限
GRANT EXECUTE ON retro_echo.* TO 'retro_app'@'localhost';
GRANT EXECUTE ON retro_echo.* TO 'retro_app'@'%';

-- 注意：不授予 CREATE, DROP, ALTER 等 DDL 权限
-- 注意：register.php 的 sp_register_customer 由应用账户执行（匿名用户注册）


-- ============================================================
-- 第八部分：权限管理常用命令
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
-- 第九部分：权限继承说明
-- ============================================================
/*
权限分配说明（基于 PHP 调用统计）:

┌─────────────────────────────────────────────────────────────────────────────┐
│  Admin (role_admin)                                                         │
│  └── 完全权限：ALL PRIVILEGES                                                │
│      视图：vw_supplier_list, vw_warehouse_stock, vw_retail_shops,           │
│           vw_other_shops_inventory, vw_release_list_with_cost,              │
│           vw_transfer_request_info, vw_shop_stock_count,                    │
│           vw_all_shops_inventory_summary, vw_all_shops_inventory_detail,    │
│           vw_stock_summary, vw_stock_detail, vw_admin_release_list,         │
│           vw_admin_supplier_orders, vw_admin_all_requests,                  │
│           vw_stock_price_by_condition, vw_admin_employee_list,              │
│           vw_admin_customer_list, vw_release_simple_list, vw_shop_list      │
│      存储：sp_create_supplier_order, sp_add_supplier_order_line,            │
│           sp_add_employee, sp_update_employee, sp_delete_employee,          │
│           sp_add_supplier, sp_update_supplier, sp_delete_supplier,          │
│           sp_add_release, sp_update_release, sp_respond_to_request,         │
│           sp_update_stock_price, sp_get_shop_id_by_type,                    │
│           sp_update_transfer_request_source, sp_initiate_warehouse_dispatch │
├─────────────────────────────────────────────────────────────────────────────┤
│  Manager (role_manager)                                                     │
│  └── 视图：vw_employee_shop_info, vw_shop_walk_in_revenue,                  │
│           vw_shop_inventory_cost, vw_shop_procurement_stats,                │
│           vw_shop_inventory_by_release, vw_shop_kpi_stats, vw_kpi_stats,    │
│           vw_stock_item_with_cost, vw_shop_artist_profit_analysis,          │
│           vw_artist_sales_detail, vw_shop_batch_sales_analysis,             │
│           vw_batch_sales_detail, vw_shop_genre_sales_summary,               │
│           vw_shop_monthly_sales_summary, vw_low_stock_alert,                │
│           vw_dead_stock_alert, vw_manager_shop_performance,                 │
│           vw_report_sales_by_genre, vw_report_top_customers,                │
│           vw_report_monthly_sales, vw_manager_requests_sent,                │
│           vw_popular_items, vw_shop_total_expense, vw_dead_stock_by_shop,   │
│           vw_low_stock_by_shop, vw_customer_shop_orders,                    │
│           vw_customer_buyback_history, vw_shop_order_details,               │
│           vw_shop_top_customers, vw_sales_by_genre_detail,                  │
│           vw_monthly_sales_detail, vw_admin_employee_list,                  │
│           vw_admin_customer_list, vw_release_simple_list, vw_shop_list,     │
│           vw_auth_employee (login.php)                                      │
│  └── 存储：sp_add_employee, sp_update_employee, sp_delete_employee,         │
│           sp_create_price_adjustment_request, sp_create_transfer_request,   │
│           sp_mark_requests_viewed                                           │
├─────────────────────────────────────────────────────────────────────────────┤
│  Staff (role_staff)                                                         │
│  └── 视图：vw_cart_items_detail, vw_employee_shop_info,                     │
│           vw_pos_stock_grouped, vw_pos_all_releases, vw_order_line_detail,  │
│           vw_pos_available_stock_ids, vw_pos_cart_item_validation,          │
│           vw_fulfillment_pending_transfers_grouped,                         │
│           vw_fulfillment_incoming_transfers_grouped, vw_fulfillment_orders, │
│           vw_fulfillment_order_status_counts, vw_release_list_with_cost,    │
│           vw_customer_list_with_points, vw_stock_price_map,                 │
│           vw_recent_buybacks_detail, vw_transfer_validation,                │
│           vw_order_shop_validation, vw_stock_summary, vw_stock_detail,      │
│           vw_warehouse_pending_receipts, vw_customer_lookup,                │
│           vw_order_for_pickup, vw_customer_simple_list,                     │
│           vw_staff_pos_history, vw_staff_pickup_history,                    │
│           vw_customer_my_orders_list, vw_customer_profile_info,             │
│           vw_staff_bopis_pending (header.php), vw_auth_employee (login.php) │
│  └── 存储：sp_receive_supplier_order, sp_process_buyback,                   │
│           sp_complete_transfer, sp_complete_order, sp_cancel_order,         │
│           sp_confirm_transfer_dispatch, sp_update_order_status,             │
│           sp_create_pos_order, sp_cancel_transfer                           │
├─────────────────────────────────────────────────────────────────────────────┤
│  Customer (role_customer)                                                   │
│  └── 视图：vw_cart_item_validation, vw_cart_items_detail,                   │
│           vw_release_shop_stock_grouped, vw_available_stock_ids,            │
│           vw_checkout_cart_items, vw_customer_order_detail,                 │
│           vw_order_shop_validation, vw_catalog_by_shop_grouped,             │
│           vw_release_info, vw_release_genres, vw_release_tracks,            │
│           vw_release_basic, vw_customer_order_history, vw_product_detail,   │
│           vw_product_alternatives, vw_customer_pending_order,               │
│           vw_order_reserved_items, vw_membership_tier_rules,                │
│           vw_release_stock_by_condition, vw_customer_my_orders_list,        │
│           vw_customer_profile_info, vw_shop_list,                           │
│           vw_customer_shipped_delivery (header.php),                        │
│           vw_auth_customer (login.php)                                      │
│  └── 存储：sp_pay_order, sp_complete_order, sp_cancel_order,                │
│           sp_release_expired_reservations, sp_update_customer_profile,      │
│           sp_create_online_order_complete, sp_get_stock_item_with_lock      │
├─────────────────────────────────────────────────────────────────────────────┤
│  App (retro_app) - 应用程序账户                                              │
│  └── 所有表 SELECT, INSERT, UPDATE, DELETE                                  │
│  └── 所有存储过程 EXECUTE                                                    │
│  └── 用于 register.php 的 sp_register_customer（匿名用户注册）               │
│  └── 用于 login.php 的 sp_update_employee（更新登录时间）                    │
└─────────────────────────────────────────────────────────────────────────────┘

公共 PHP 文件权限分配说明：
┌─────────────────────────────────────────────────────────────────────────────┐
│  header.php - 根据用户类型分配                                               │
│  ├── vw_customer_shipped_delivery  → Customer（显示发货通知）                │
│  ├── vw_warehouse_pending_receipts → Admin（仓库待收货通知）                 │
│  ├── vw_staff_bopis_pending        → Staff（BOPIS待处理通知）                │
│  └── vw_admin_pending_requests     → Admin（待审批请求通知）                 │
├─────────────────────────────────────────────────────────────────────────────┤
│  login.php - 认证视图和登录存储过程                                          │
│  ├── vw_auth_employee → Manager, Staff（员工登录认证）                       │
│  ├── vw_auth_customer → Customer（客户登录认证）                             │
│  └── sp_update_employee → App账户执行（更新最后登录时间）                    │
├─────────────────────────────────────────────────────────────────────────────┤
│  register.php - 注册存储过程                                                 │
│  └── sp_register_customer → App账户执行（匿名用户需要注册）                  │
└─────────────────────────────────────────────────────────────────────────────┘

注意：
- 隔离本质是对数据库对象（视图/存储过程）的访问控制
- 用户只能通过被授权的视图查询数据
- 用户只能通过被授权的存储过程修改数据
- 视图内部的WHERE条件可进一步实现行级隔离
- 公共 PHP 调用的对象根据用途分配给对应用户角色
- 匿名操作（如注册）由应用账户 retro_app 执行
*/


-- ============================================================
-- 应用权限更改
-- ============================================================
FLUSH PRIVILEGES;

SELECT '数据库用户权限配置完成！' AS Message;
