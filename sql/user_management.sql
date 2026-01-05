GRANT SELECT ON retro_echo.vw_supplier_list TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_warehouse_stock TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_retail_shops TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_other_shops_inventory TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_release_list_with_cost TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_transfer_request_info TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_shop_stock_count TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_all_shops_inventory_summary TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_all_shops_inventory_detail TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_stock_summary TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_stock_detail TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_admin_release_list TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_admin_supplier_orders TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_admin_all_requests TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_stock_price_by_condition TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_admin_employee_list TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_admin_customer_list TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_release_simple_list TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_shop_list TO 'retro_admin'@'%';

GRANT SELECT ON retro_echo.vw_admin_pending_requests TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_auth_employee TO 'retro_admin'@'%';
GRANT SELECT ON retro_echo.vw_auth_customer TO 'retro_admin'@'%';

GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_supplier_order TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_supplier_order_line TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_employee TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_delete_employee TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_supplier TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_supplier TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_delete_supplier TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_release TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_release TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_respond_to_request TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_stock_price TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_get_shop_id_by_type TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_transfer_request_source TO 'retro_admin'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_initiate_warehouse_dispatch TO 'retro_admin'@'%';

GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee_session TO 'retro_admin'@'%';

GRANT SELECT ON retro_echo.vw_employee_shop_info TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_walk_in_revenue TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_inventory_cost TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_procurement_stats TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_inventory_by_release TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_kpi_stats TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_stock_item_with_cost TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_artist_profit_analysis TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_artist_sales_detail TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_batch_sales_analysis TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_batch_sales_detail TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_genre_sales_summary TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_monthly_sales_summary TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_low_stock_alert TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_dead_stock_alert TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_manager_shop_performance TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_report_sales_by_genre TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_report_top_customers TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_kpi_stats TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_report_monthly_sales TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_manager_requests_sent TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_popular_items TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_total_expense TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_dead_stock_by_shop TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_low_stock_by_shop TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_customer_shop_orders TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_customer_buyback_history TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_order_details TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_top_customers TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_sales_by_genre_detail TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_monthly_sales_detail TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_admin_employee_list TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_admin_customer_list TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_release_simple_list TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_shop_list TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_stock_price_map TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_stock_summary TO 'retro_manager'@'%';
GRANT SELECT ON retro_echo.vw_stock_detail TO 'retro_manager'@'%';

GRANT SELECT ON retro_echo.vw_auth_employee TO 'retro_manager'@'%';

GRANT EXECUTE ON PROCEDURE retro_echo.sp_add_employee TO 'retro_manager'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee TO 'retro_manager'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_delete_employee TO 'retro_manager'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_price_adjustment_request TO 'retro_manager'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_transfer_request TO 'retro_manager'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_mark_requests_viewed TO 'retro_manager'@'%';

GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee_session TO 'retro_manager'@'%';

GRANT SELECT ON retro_echo.vw_cart_items_detail TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_employee_shop_info TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_pos_stock_grouped TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_pos_all_releases TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_order_line_detail TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_pos_available_stock_ids TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_pos_cart_item_validation TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_fulfillment_pending_transfers_grouped TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_fulfillment_incoming_transfers_grouped TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_fulfillment_orders TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_fulfillment_order_status_counts TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_release_list_with_cost TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_customer_list_with_points TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_stock_price_map TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_recent_buybacks_detail TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_transfer_validation TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_order_shop_validation TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_stock_summary TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_stock_detail TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_warehouse_pending_receipts TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_customer_lookup TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_order_for_pickup TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_customer_simple_list TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_staff_pos_history TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_staff_pickup_history TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_customer_my_orders_list TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_customer_profile_info TO 'retro_staff'@'%';

GRANT SELECT ON retro_echo.vw_staff_bopis_pending TO 'retro_staff'@'%';
GRANT SELECT ON retro_echo.vw_auth_employee TO 'retro_staff'@'%';

GRANT EXECUTE ON PROCEDURE retro_echo.sp_receive_supplier_order TO 'retro_staff'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_process_buyback TO 'retro_staff'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_transfer TO 'retro_staff'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_order TO 'retro_staff'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_order TO 'retro_staff'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_confirm_transfer_dispatch TO 'retro_staff'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_order_status TO 'retro_staff'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_pos_order TO 'retro_staff'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_transfer TO 'retro_staff'@'%';

GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee_session TO 'retro_staff'@'%';

GRANT SELECT ON retro_echo.vw_cart_item_validation TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_cart_items_detail TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_release_shop_stock_grouped TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_available_stock_ids TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_checkout_cart_items TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_customer_order_detail TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_order_shop_validation TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_catalog_by_shop_grouped TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_release_info TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_release_genres TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_release_tracks TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_release_basic TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_customer_order_history TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_product_detail TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_product_alternatives TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_customer_pending_order TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_order_reserved_items TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_membership_tier_rules TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_release_stock_by_condition TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_customer_my_orders_list TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_customer_profile_info TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_shop_list TO 'retro_customer'@'%';

GRANT SELECT ON retro_echo.vw_customer_shipped_delivery TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_auth_employee TO 'retro_customer'@'%';
GRANT SELECT ON retro_echo.vw_auth_customer TO 'retro_customer'@'%';

GRANT EXECUTE ON PROCEDURE retro_echo.sp_pay_order TO 'retro_customer'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_complete_order TO 'retro_customer'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_cancel_order TO 'retro_customer'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_release_expired_reservations TO 'retro_customer'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_customer_profile TO 'retro_customer'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_create_online_order_complete TO 'retro_customer'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_get_stock_item_with_lock TO 'retro_customer'@'%';

GRANT EXECUTE ON PROCEDURE retro_echo.sp_register_customer TO 'retro_customer'@'%';

GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_employee_session TO 'retro_customer'@'%';
GRANT EXECUTE ON PROCEDURE retro_echo.sp_update_customer_session TO 'retro_customer'@'%';

GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'retro_admin'@'%';
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'retro_manager'@'%';
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'retro_staff'@'%';
GRANT SELECT (EmployeeID, CurrentSessionID) ON retro_echo.Employee TO 'retro_customer'@'%';

GRANT SELECT (CustomerID, CurrentSessionID) ON retro_echo.Customer TO 'retro_admin'@'%';
GRANT SELECT (CustomerID, CurrentSessionID) ON retro_echo.Customer TO 'retro_manager'@'%';
GRANT SELECT (CustomerID, CurrentSessionID) ON retro_echo.Customer TO 'retro_staff'@'%';
GRANT SELECT (CustomerID, CurrentSessionID) ON retro_echo.Customer TO 'retro_customer'@'%';

GRANT SELECT (RequestID, RequestedByEmployeeID, Status, ViewedByRequesterAt)
    ON retro_echo.ManagerRequest TO 'retro_manager'@'%';



GRANT SELECT (RequestID, RequestedByEmployeeID, Status, ViewedByRequesterAt)
    ON retro_echo.ManagerRequest TO 'retro_manager'@'%';

FLUSH PRIVILEGES;

SELECT '数据库用户权限配置完成！' AS Message;