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
-- 第四部分：Manager 权限 - 基于现有视图
-- ============================================================

-- 4.1 Manager 专属视图
GRANT SELECT ON retro_echo.vw_manager_shop_performance TO 'role_manager';
GRANT SELECT ON retro_echo.vw_manager_requests_sent TO 'role_manager';

-- 4.2 可访问的报表视图
GRANT SELECT ON retro_echo.vw_report_sales_by_genre TO 'role_manager';
GRANT SELECT ON retro_echo.vw_report_top_customers TO 'role_manager';
GRANT SELECT ON retro_echo.vw_report_monthly_sales TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_kpi_stats TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_walk_in_revenue TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_inventory_cost TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_procurement_stats TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_top_customers TO 'role_manager';

-- 4.3 库存管理视图
GRANT SELECT ON retro_echo.vw_inventory_summary TO 'role_manager';
GRANT SELECT ON retro_echo.vw_low_stock_alert TO 'role_manager';
GRANT SELECT ON retro_echo.vw_low_stock_by_shop TO 'role_manager';
GRANT SELECT ON retro_echo.vw_dead_stock_alert TO 'role_manager';
GRANT SELECT ON retro_echo.vw_dead_stock_by_shop TO 'role_manager';
GRANT SELECT ON retro_echo.vw_warehouse_stock TO 'role_manager';
GRANT SELECT ON retro_echo.vw_other_shops_inventory TO 'role_manager';

-- 4.4 供应商管理视图（采购时需要）
GRANT SELECT ON retro_echo.vw_supplier_list TO 'role_manager';

-- 4.5 Manager 报表分析视图（新增）
GRANT SELECT ON retro_echo.vw_shop_artist_profit_analysis TO 'role_manager';
GRANT SELECT ON retro_echo.vw_artist_sales_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_batch_sales_analysis TO 'role_manager';
GRANT SELECT ON retro_echo.vw_batch_sales_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_genre_sales_summary TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_monthly_sales_summary TO 'role_manager';
GRANT SELECT ON retro_echo.vw_sales_by_genre_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_monthly_sales_detail TO 'role_manager';

-- 4.6 Manager 回购和订单分析视图（新增）
GRANT SELECT ON retro_echo.vw_recent_buybacks_detail TO 'role_manager';
GRANT SELECT ON retro_echo.vw_shop_order_details TO 'role_manager';
GRANT SELECT ON retro_echo.vw_warehouse_pending_receipts TO 'role_manager';
GRANT SELECT ON retro_echo.vw_stock_price_map TO 'role_manager';

-- 4.8 Manager 可用的存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_supplier_order TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_supplier_order_line TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_receive_supplier_order TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_price_adjustment_request TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_transfer_request TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_transfer_request_source TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_transfer TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee TO 'role_manager';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_mark_requests_viewed TO 'role_manager';

-- 分配角色
GRANT 'role_manager' TO 'retro_manager'@'localhost', 'retro_manager'@'%';
SET DEFAULT ROLE 'role_manager' TO 'retro_manager'@'localhost', 'retro_manager'@'%';


-- ============================================================
-- 第五部分：Staff 权限 - 日常操作
-- ============================================================

-- 5.1 Staff 专属视图（POS/库存/取货）
GRANT SELECT ON retro_echo.vw_staff_pos_lookup TO 'role_staff';
GRANT SELECT ON retro_echo.vw_staff_pos_history TO 'role_staff';
GRANT SELECT ON retro_echo.vw_staff_bopis_pending TO 'role_staff';
GRANT SELECT ON retro_echo.vw_staff_pickup_history TO 'role_staff';
GRANT SELECT ON retro_echo.vw_staff_inventory_detail TO 'role_staff';

-- 5.2 POS 相关视图
GRANT SELECT ON retro_echo.vw_pos_stock_grouped TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_all_releases TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_available_stock_ids TO 'role_staff';
GRANT SELECT ON retro_echo.vw_pos_cart_item_validation TO 'role_staff';

-- 5.3 订单和客户查询
GRANT SELECT ON retro_echo.vw_customer_lookup TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_simple_list TO 'role_staff';
GRANT SELECT ON retro_echo.vw_order_for_pickup TO 'role_staff';
GRANT SELECT ON retro_echo.vw_order_line_detail TO 'role_staff';

-- 5.4 Fulfillment 视图
GRANT SELECT ON retro_echo.vw_fulfillment_pending_transfers_grouped TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_incoming_transfers_grouped TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_orders TO 'role_staff';
GRANT SELECT ON retro_echo.vw_fulfillment_order_status_counts TO 'role_staff';

-- 5.5 公共视图
GRANT SELECT ON retro_echo.vw_product_detail TO 'role_staff';
GRANT SELECT ON retro_echo.vw_release_simple_list TO 'role_staff';
GRANT SELECT ON retro_echo.vw_shop_list TO 'role_staff';
GRANT SELECT ON retro_echo.vw_employee_shop_info TO 'role_staff';

-- 5.6 回购业务视图（Buyback 需要查看专辑成本和客户积分）
GRANT SELECT ON retro_echo.vw_release_list_with_cost TO 'role_staff';
GRANT SELECT ON retro_echo.vw_customer_list_with_points TO 'role_staff';

-- 5.7 Staff 回购和库存辅助视图（新增）
GRANT SELECT ON retro_echo.vw_recent_buybacks_detail TO 'role_staff';
GRANT SELECT ON retro_echo.vw_stock_price_map TO 'role_staff';
GRANT SELECT ON retro_echo.vw_shop_stock_count TO 'role_staff';
GRANT SELECT ON retro_echo.vw_order_shop_validation TO 'role_staff';

-- 5.8 Staff 可用的存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_pos_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_process_buyback TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_order TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_order_status TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_confirm_transfer_dispatch TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_transfer TO 'role_staff';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_initiate_warehouse_dispatch TO 'role_staff';

-- 分配角色
GRANT 'role_staff' TO 'retro_staff'@'localhost', 'retro_staff'@'%';
SET DEFAULT ROLE 'role_staff' TO 'retro_staff'@'localhost', 'retro_staff'@'%';


-- ============================================================
-- 第六部分：Customer 权限 - 前台客户
-- ============================================================

-- 6.1 Customer 专属视图
GRANT SELECT ON retro_echo.vw_customer_order_history TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_my_orders_list TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_profile_info TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_pending_order TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_buyback_history TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_order_detail TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_shop_orders TO 'role_customer';
GRANT SELECT ON retro_echo.vw_customer_shipped_delivery TO 'role_customer';

-- 6.2 购物车相关
GRANT SELECT ON retro_echo.vw_cart_item_validation TO 'role_customer';
GRANT SELECT ON retro_echo.vw_cart_items_detail TO 'role_customer';
GRANT SELECT ON retro_echo.vw_checkout_cart_items TO 'role_customer';

-- 6.3 商品浏览（公共目录）
GRANT SELECT ON retro_echo.vw_product_detail TO 'role_customer';
GRANT SELECT ON retro_echo.vw_product_alternatives TO 'role_customer';
GRANT SELECT ON retro_echo.vw_catalog_by_shop_grouped TO 'role_customer';
GRANT SELECT ON retro_echo.vw_popular_items TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_simple_list TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_stock_by_condition TO 'role_customer';
GRANT SELECT ON retro_echo.vw_available_stock_ids TO 'role_customer';
GRANT SELECT ON retro_echo.vw_stock_price_by_condition TO 'role_customer';
GRANT SELECT ON retro_echo.vw_shop_list TO 'role_customer';
GRANT SELECT ON retro_echo.vw_retail_shops TO 'role_customer';
GRANT SELECT ON retro_echo.vw_membership_tier_rules TO 'role_customer';

-- 6.4 专辑详情视图（新增）
GRANT SELECT ON retro_echo.vw_release_info TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_tracks TO 'role_customer';
GRANT SELECT ON retro_echo.vw_release_genres TO 'role_customer';

-- 6.5 Customer 可用的存储过程
GRANT EXECUTE ON PROCEDURE retro_echo.sp_register_customer TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_customer_profile TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_customer_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_online_order_complete TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_order_item TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_pay_order TO 'role_customer';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_order TO 'role_customer';

-- 分配角色
GRANT 'role_customer' TO 'retro_customer'@'localhost', 'retro_customer'@'%';
SET DEFAULT ROLE 'role_customer' TO 'retro_customer'@'localhost', 'retro_customer'@'%';


-- ============================================================
-- 第七部分：应用程序账户权限
-- ============================================================
-- PHP 应用使用此账户连接数据库
-- 需要足够权限执行所有业务操作，但不能修改数据库结构

-- 所有表的 CRUD 权限
GRANT SELECT, INSERT, UPDATE, DELETE ON retro_echo.* TO 'retro_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON retro_echo.* TO 'retro_app'@'%';

-- 所有存储过程执行权限
GRANT EXECUTE ON retro_echo.* TO 'retro_app'@'localhost';
GRANT EXECUTE ON retro_echo.* TO 'retro_app'@'%';

-- 注意：不授予 CREATE, DROP, ALTER 等 DDL 权限


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
权限继承关系（从高到低）:

┌─────────────────────────────────────────────────────────────┐
│  Admin (role_admin)                                         │
│  └── 完全权限：ALL PRIVILEGES                                │
│      包含以下所有角色的权限                                    │
│      + Admin专属视图 (vw_admin_*)                            │
│      + 管理类存储过程 (sp_respond_to_request等)               │
├─────────────────────────────────────────────────────────────┤
│  Manager (role_manager)                                     │
│  └── 经理专属视图 (vw_manager_*)                             │
│  └── 报表视图 (vw_report_*, vw_shop_*_summary)               │
│  └── 销售分析视图 (vw_*_sales_detail, vw_*_analysis)         │
│  └── 库存预警视图 (vw_low_stock_*, vw_dead_stock_*)          │
│  └── 采购/调拨存储过程                                        │
├─────────────────────────────────────────────────────────────┤
│  Staff (role_staff)                                         │
│  └── POS 销售视图 (vw_staff_*, vw_pos_*)                     │
│  └── 履约视图 (vw_fulfillment_*)                             │
│  └── 回购辅助视图 (vw_recent_buybacks_detail等)              │
│  └── 订单处理存储过程                                         │
├─────────────────────────────────────────────────────────────┤
│  Customer (role_customer)                                   │
│  └── 个人订单/资料视图 (vw_customer_*)                        │
│  └── 商品目录视图 (vw_catalog_*, vw_product_*)               │
│  └── 专辑详情视图 (vw_release_info, vw_release_tracks)       │
│  └── 下单/支付存储过程                                        │
└─────────────────────────────────────────────────────────────┘

注意：
- 隔离本质是对数据库对象（视图/存储过程）的访问控制
- 用户只能通过被授权的视图查询数据
- 用户只能通过被授权的存储过程修改数据
- 视图内部的WHERE条件可进一步实现行级隔离
- 内部认证视图 (vw_auth_*) 仅供应用账户使用
*/


-- ============================================================
-- 应用权限更改
-- ============================================================
FLUSH PRIVILEGES;

SELECT '数据库用户权限配置完成！' AS Message;
