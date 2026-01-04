-- ========================================
-- Database User Management SQL
-- 数据库用户管理（阿里云 RDS 兼容版）
-- ========================================
-- 四类用户：Admin, Manager, Staff, Customer
-- 匿名用户默认使用 Customer 账户
-- Admin 改为普通账号，通过 SQL 精细化授权
-- ========================================

-- ============================================================
-- 第一部分：创建数据库用户（普通账号）
-- 注意：在阿里云 RDS 控制台创建账号，或使用有权限的账号执行
-- ============================================================

-- Admin - 系统管理员（普通账号，非高权限账号）
CREATE USER IF NOT EXISTS 'retro_admin'@'%' IDENTIFIED BY '77070912Yan';

-- Manager - 店铺经理
CREATE USER IF NOT EXISTS 'retro_manager'@'%' IDENTIFIED BY '77070912Yan';

-- Staff - 店员
CREATE USER IF NOT EXISTS 'retro_staff'@'%' IDENTIFIED BY '77070912Yan';

-- Customer - 客户（前台用户 + 匿名用户默认账户）
CREATE USER IF NOT EXISTS 'retro_customer'@'%' IDENTIFIED BY '77070912Yan';


-- ============================================================
-- 第二部分：创建角色 (MySQL 8.0+)
-- ============================================================

CREATE ROLE IF NOT EXISTS 'role_admin';
CREATE ROLE IF NOT EXISTS 'role_manager';
CREATE ROLE IF NOT EXISTS 'role_staff';
CREATE ROLE IF NOT EXISTS 'role_customer';


-- ============================================================
-- 第三部分：Admin 权限（精细化授权，非 ALL PRIVILEGES）
-- ============================================================

-- 3.1 Admin 视图权限
GRANT SELECT ON retro_echo.vw_supplier_list TO 'role_admin';
GRANT SELECT ON retro_echo.vw_warehouse_stock TO 'role_admin';
GRANT SELECT ON retro_echo.vw_retail_shops TO 'role_admin';
GRANT SELECT ON retro_echo.vw_other_shops_inventory TO 'role_admin';
GRANT SELECT ON retro_echo.vw_release_list_with_cost TO 'role_admin';
GRANT SELECT ON retro_echo.vw_transfer_request_info TO 'role_admin';
GRANT SELECT ON retro_echo.vw_shop_stock_count TO 'role_admin';
GRANT SELECT ON retro_echo.vw_all_shops_inventory_summary TO 'role_admin';
GRANT SELECT ON retro_echo.vw_all_shops_inventory_detail TO 'role_admin';
GRANT SELECT ON retro_echo.vw_stock_summary TO 'role_admin';
GRANT SELECT ON retro_echo.vw_stock_detail TO 'role_admin';
GRANT SELECT ON retro_echo.vw_admin_release_list TO 'role_admin';
GRANT SELECT ON retro_echo.vw_admin_supplier_orders TO 'role_admin';
GRANT SELECT ON retro_echo.vw_admin_all_requests TO 'role_admin';
GRANT SELECT ON retro_echo.vw_stock_price_by_condition TO 'role_admin';
GRANT SELECT ON retro_echo.vw_admin_employee_list TO 'role_admin';
GRANT SELECT ON retro_echo.vw_admin_customer_list TO 'role_admin';
GRANT SELECT ON retro_echo.vw_release_simple_list TO 'role_admin';
GRANT SELECT ON retro_echo.vw_shop_list TO 'role_admin';

-- 3.2 Admin 公共文件视图权限 (header.php, login.php)
GRANT SELECT ON retro_echo.vw_admin_pending_requests TO 'role_admin';
GRANT SELECT ON retro_echo.vw_auth_employee TO 'role_admin';
GRANT SELECT ON retro_echo.vw_auth_customer TO 'role_admin';

-- 3.3 Admin 存储过程权限
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_supplier_order TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_supplier_order_line TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_employee TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_delete_employee TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_supplier TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_supplier TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_delete_supplier TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_release TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_release TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_respond_to_request TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_stock_price TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_get_shop_id_by_type TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_transfer_request_source TO 'role_admin';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_initiate_warehouse_dispatch TO 'role_admin';

-- 3.4 Admin Session 管理
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee_session TO 'role_admin';

-- 分配角色
GRANT 'role_admin' TO 'retro_admin'@'%';
SET DEFAULT ROLE 'role_admin' TO 'retro_admin'@'%';


-- ============================================================
-- 第四部分：Manager 权限
-- ============================================================

-- 4.1 Manager 视图权限
GRANT SELECT ON retro_echo.vw_employee_shop_info TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_walk_in_revenue TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_inventory_cost TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_procurement_stats TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_inventory_by_release TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_kpi_stats TO 'role_manager';
GRANT SELECT ON retro_echo.vw_stock_item_with_cost TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_artist_profit_analysis TO 'role_manager';
GRANT SELECT ON retro_echo.vw_artist_sales_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_batch_sales_analysis TO 'role_manager';
GRANT SELECT ON retro_echo.vw_batch_sales_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_genre_sales_summary TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_monthly_sales_summary TO 'role_manager';
GRANT SELECT ON retro_echo.vw_low_stock_alert TO 'role_manager';
GRANT SELECT ON retro_echo.vw_dead_stock_alert TO 'role_manager';
GRANT SELECT ON retro_echo.vw_manager_shop_performance TO 'role_manager';
GRANT SELECT ON retro_echo.vw_report_sales_by_genre TO 'role_manager';
GRANT SELECT ON retro_echo.vw_report_top_customers TO 'role_manager';
GRANT SELECT ON retro_echo.vw_kpi_stats TO 'role_manager';
GRANT SELECT ON retro_echo.vw_report_monthly_sales TO 'role_manager';
GRANT SELECT ON retro_echo.vw_manager_requests_sent TO 'role_manager';
GRANT SELECT ON retro_echo.vw_popular_items TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_total_expense TO 'role_manager';
GRANT SELECT ON retro_echo.vw_dead_stock_by_shop TO 'role_manager';
GRANT SELECT ON retro_echo.vw_low_stock_by_shop TO 'role_manager';
GRANT SELECT ON retro_echo.vw_customer_shop_orders TO 'role_manager';
GRANT SELECT ON retro_echo.vw_customer_buyback_history TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_order_details TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_top_customers TO 'role_manager';
GRANT SELECT ON retro_echo.vw_sales_by_genre_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_monthly_sales_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_admin_employee_list TO 'role_manager';
GRANT SELECT ON retro_echo.vw_admin_customer_list TO 'role_manager';
GRANT SELECT ON retro_echo.vw_release_simple_list TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_list TO 'role_manager';
GRANT SELECT ON retro_echo.vw_stock_price_map TO 'role_manager';
GRANT SELECT ON retro_echo.vw_stock_summary TO 'role_manager';
GRANT SELECT ON retro_echo.vw_stock_detail TO 'role_manager';

-- 4.2 Manager 公共文件视图权限 (login.php)
GRANT SELECT ON retro_echo.vw_auth_employee TO 'role_manager';

-- 4.3 Manager 存储过程权限
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_delete_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_price_adjustment_request TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_transfer_request TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_mark_requests_viewed TO 'role_manager';

-- 4.4 Manager Session 管理
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee_session TO 'role_manager';

-- 分配角色
GRANT 'role_manager' TO 'retro_manager'@'%';
SET DEFAULT ROLE 'role_manager' TO 'retro_manager'@'%';


-- ============================================================
-- 第五部分：Staff 权限
-- ============================================================

-- 5.1 Staff 视图权限
GRANT SELECT ON retro_echo.vw_cart_items_detail TO 'role_staff';
GRANT SELECT ON retro_echo.vw_employee_shop_info TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_stock_grouped TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_all_releases TO 'role_staff';
GRANT SELECT ON retro_echo.vw_order_line_detail TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_available_stock_ids TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_cart_item_validation TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_pending_transfers_grouped TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_incoming_transfers_grouped TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_orders TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_order_status_counts TO 'role_staff';
GRANT SELECT ON retro_echo.vw_release_list_with_cost TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_list_with_points TO 'role_staff';
GRANT SELECT ON retro_echo.vw_stock_price_map TO 'role_staff';
GRANT SELECT ON retro_echo.vw_recent_buybacks_detail TO 'role_staff';
GRANT SELECT ON retro_echo.vw_transfer_validation TO 'role_staff';
GRANT SELECT ON retro_echo.vw_order_shop_validation TO 'role_staff';
GRANT SELECT ON retro_echo.vw_stock_summary TO 'role_staff';
GRANT SELECT ON retro_echo.vw_stock_detail TO 'role_staff';
GRANT SELECT ON retro_echo.vw_warehouse_pending_receipts TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_lookup TO 'role_staff';
GRANT SELECT ON retro_echo.vw_order_for_pickup TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_simple_list TO 'role_staff';
GRANT SELECT ON retro_echo.vw_staff_pos_history TO 'role_staff';
GRANT SELECT ON retro_echo.vw_staff_pickup_history TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_my_orders_list TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_profile_info TO 'role_staff';

-- 5.2 Staff 公共文件视图权限 (header.php, login.php)
GRANT SELECT ON retro_echo.vw_staff_bopis_pending TO 'role_staff';
GRANT SELECT ON retro_echo.vw_auth_employee TO 'role_staff';

-- 5.3 Staff 存储过程权限
GRANT EXECUTE ON PROCEDURE retro_echo.sp_receive_supplier_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_process_buyback TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_transfer TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_confirm_transfer_dispatch TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_order_status TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_pos_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_transfer TO 'role_staff';

-- 5.4 Staff Session 管理
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee_session TO 'role_staff';

-- 分配角色
GRANT 'role_staff' TO 'retro_staff'@'%';
SET DEFAULT ROLE 'role_staff' TO 'retro_staff'@'%';


-- ============================================================
-- 第六部分：Customer 权限（含匿名用户所需权限）
-- ============================================================

-- 6.1 Customer 视图权限
GRANT SELECT ON retro_echo.vw_cart_item_validation TO 'role_customer';
GRANT SELECT ON retro_echo.vw_cart_items_detail TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_shop_stock_grouped TO 'role_customer';
GRANT SELECT ON retro_echo.vw_available_stock_ids TO 'role_customer';
GRANT SELECT ON retro_echo.vw_checkout_cart_items TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_order_detail TO 'role_customer';
GRANT SELECT ON retro_echo.vw_order_shop_validation TO 'role_customer';
GRANT SELECT ON retro_echo.vw_catalog_by_shop_grouped TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_info TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_genres TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_tracks TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_basic TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_order_history TO 'role_customer';
GRANT SELECT ON retro_echo.vw_product_detail TO 'role_customer';
GRANT SELECT ON retro_echo.vw_product_alternatives TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_pending_order TO 'role_customer';
GRANT SELECT ON retro_echo.vw_order_reserved_items TO 'role_customer';
GRANT SELECT ON retro_echo.vw_membership_tier_rules TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_stock_by_condition TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_my_orders_list TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_profile_info TO 'role_customer';
GRANT SELECT ON retro_echo.vw_shop_list TO 'role_customer';

-- 6.2 Customer 公共文件视图权限 (header.php, login.php)
GRANT SELECT ON retro_echo.vw_customer_shipped_delivery TO 'role_customer';
GRANT SELECT ON retro_echo.vw_auth_employee TO 'role_customer';
GRANT SELECT ON retro_echo.vw_auth_customer TO 'role_customer';

-- 6.3 Customer 存储过程权限
GRANT EXECUTE ON PROCEDURE retro_echo.sp_pay_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_release_expired_reservations TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_customer_profile TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_online_order_complete TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_get_stock_item_with_lock TO 'role_customer';

-- 6.4 Customer 注册权限 (register.php)
GRANT EXECUTE ON PROCEDURE retro_echo.sp_register_customer TO 'role_customer';

-- 6.5 Customer Session 管理
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee_session TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_customer_session TO 'role_customer';

-- 分配角色
GRANT 'role_customer' TO 'retro_customer'@'%';
SET DEFAULT ROLE 'role_customer' TO 'retro_customer'@'%';


-- ============================================================
-- 第七部分：Session 列级权限（解决直接表查询问题）
-- ============================================================

-- 所有角色都需要访问 Session 信息（登录/登出验证）
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'role_admin';
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'role_manager';
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'role_staff';
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'role_customer';

GRANT SELECT (CustomerID, CurrentSessionID) ON retro_echo.Customer TO 'role_admin';
GRANT SELECT (CustomerID, CurrentSessionID) ON retro_echo.Customer TO 'role_manager';
GRANT SELECT (CustomerID, CurrentSessionID) ON retro_echo.Customer TO 'role_staff';
GRANT SELECT (CustomerID, CurrentSessionID) ON retro_echo.Customer TO 'role_customer';

-- Manager 需要查询 ManagerRequest 通知计数
GRANT SELECT (RequestID, RequestedByEmployeeID, Status, ViewedByRequesterAt)
    ON retro_echo.ManagerRequest TO 'role_manager';


-- ============================================================
-- 第八部分：权限管理常用命令
-- ============================================================

-- 查看用户权限
-- SHOW GRANTS FOR 'retro_customer'@'%';
-- SHOW GRANTS FOR 'role_customer';

-- 撤销权限示例
-- REVOKE SELECT ON retro_echo.vw_customer_order_history FROM 'role_customer';

-- 修改密码
-- ALTER USER 'retro_customer'@'%' IDENTIFIED BY 'NewPassword123!';

-- 锁定/解锁账户
-- ALTER USER 'retro_customer'@'%' ACCOUNT LOCK;
-- ALTER USER 'retro_customer'@'%' ACCOUNT UNLOCK;

-- 删除用户
-- DROP USER IF EXISTS 'retro_customer'@'%';

-- 删除角色
-- DROP ROLE IF EXISTS 'role_customer';


-- ============================================================
-- 第九部分：架构说明
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
│  Admin (role_admin) - 普通账号，精细化授权                       │
│  └── 视图：供应商、仓库、库存、员工/客户管理、申请审批等          │
│  └── 存储：供应商订单、员工管理、申请审批、库存调配等             │
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

公共文件权限说明：
- header.php: 各角色有不同的通知视图权限
- login.php: 所有角色都有 vw_auth_employee/vw_auth_customer
- register.php: Customer 角色有 sp_register_customer

列级权限说明：
- Employee.CurrentSessionID: 所有角色都可查询（登出验证）
- Customer.CurrentSessionID: 所有角色都可查询（登出验证）
- ManagerRequest: Manager 可查询通知计数相关列
*/



-- ============================================================
-- 第九部分：Session 列级权限（补充）
-- ============================================================

-- 所有角色都需要访问 Session 信息（登录/登出验证）
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'role_admin';
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'role_manager';
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'role_staff';
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'role_customer';


-- Manager 需要查询 ManagerRequest 通知计数
GRANT SELECT (RequestID, RequestedByEmployeeID, Status, ViewedByRequesterAt) 
    ON retro_echo.ManagerRequest TO 'role_manager';


-- ============================================================
-- 应用权限更改
-- ============================================================
FLUSH PRIVILEGES;

SELECT '数据库用户权限配置完成！' AS Message;
