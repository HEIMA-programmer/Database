<?php

class DBProcedures {

    // =============================================
    // 【视图查询方法】读操作
    // =============================================

    // ----------------
    // 购物车相关
    // ----------------


    /**
     * 【并发安全修复】使用行锁获取并验证库存状态
     * 调用存储过程 sp_get_stock_item_with_lock，在事务中锁定行防止并发超卖
     *
     * 【架构说明】虽然FOR UPDATE必须用于基表而非视图，
     * 但通过存储过程封装保持了分层架构的一致性
     *
     * @param PDO $pdo
     * @param int $stockId
     * @return array|false 库存信息或false
     */
    public static function getStockItemInfoWithLock($pdo, $stockId) {
        try {
            // 调用存储过程获取带锁的库存信息
            $stmt = $pdo->prepare("CALL sp_get_stock_item_with_lock(?)");
            $stmt->execute([$stockId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getStockItemInfoWithLock Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 目录和商品相关
    // ----------------


    // 【清理】getCatalogItemsGrouped 和 getCatalogGenresGrouped 已被 getCatalogByShop 和 getReleaseGenres 替代，已删除

    /**
     * 【新增】获取专辑按条件分组的库存详情
     */
    public static function getReleaseStockByCondition($pdo, $releaseId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_release_stock_by_condition WHERE ReleaseID = ?");
            $stmt->execute([$releaseId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getReleaseStockByCondition Error: " . $e->getMessage());
            return [];
        }
    }


    /**
     * 获取商品详情
     */
    public static function getProductDetail($pdo, $stockId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_product_detail WHERE StockItemID = ?");
            $stmt->execute([$stockId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getProductDetail Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取同款商品的其他库存
     */
    public static function getProductAlternatives($pdo, $releaseId, $excludeStockId, $limit = 5) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_product_alternatives WHERE ReleaseID = ? AND StockItemID != ? LIMIT ?");
            $stmt->execute([$releaseId, $excludeStockId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getProductAlternatives Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // POS 系统相关
    // ----------------



    /**
     * 获取 POS 购物车商品详情（用于结账验证）
     */
    public static function getPOSCartItems($pdo, $stockIds) {
        if (empty($stockIds)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($stockIds), '?'));
            $stmt = $pdo->prepare("SELECT StockItemID, UnitPrice, Title, Status FROM vw_staff_pos_lookup WHERE StockItemID IN ($placeholders)");
            $stmt->execute($stockIds);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getPOSCartItems Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // 客户相关
    // ----------------

    /**
     * 通过邮箱查找客户
     */
    public static function getCustomerByEmail($pdo, $email) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_lookup WHERE Email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getCustomerByEmail Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取客户个人资料
     */
    public static function getCustomerProfile($pdo, $customerId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_profile_info WHERE CustomerID = ?");
            $stmt->execute([$customerId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getCustomerProfile Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取下一会员等级信息
     */
    public static function getNextTierInfo($pdo, $currentPoints) {
        try {
            $stmt = $pdo->prepare("SELECT TierID, TierName, MinPoints FROM vw_membership_tier_rules WHERE MinPoints > ? ORDER BY MinPoints ASC LIMIT 1");
            $stmt->execute([$currentPoints]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getNextTierInfo Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 订单相关
    // ----------------

    /**
     * 获取客户订单列表
     */
    public static function getCustomerOrders($pdo, $customerId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_my_orders_list WHERE CustomerID = ? ORDER BY OrderDate DESC");
            $stmt->execute([$customerId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCustomerOrders Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取待支付订单
     */
    public static function getPendingOrder($pdo, $orderId, $customerId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_pending_order WHERE OrderID = ? AND CustomerID = ?");
            $stmt->execute([$orderId, $customerId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getPendingOrder Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取订单预留商品数量
     */
    public static function getOrderReservedCount($pdo, $orderId) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vw_order_reserved_items WHERE OrderID = ? AND StockStatus = 'Reserved'");
            $stmt->execute([$orderId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("getOrderReservedCount Error: " . $e->getMessage());
            return 0;
        }
    }


    /**
     * 获取待取货订单列表 (BOPIS)
     */
    public static function getBopisPendingOrders($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_staff_bopis_pending WHERE ShopID = ?");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getBopisPendingOrders Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【新增】获取POS历史交易记录
     */
    public static function getPosHistory($pdo, $shopId, $limit = 20) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_staff_pos_history WHERE ShopID = ? LIMIT ?");
            $stmt->execute([$shopId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getPosHistory Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【新增】获取Pickup历史记录
     */
    public static function getPickupHistory($pdo, $shopId, $limit = 20) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_staff_pickup_history WHERE ShopID = ? LIMIT ?");
            $stmt->execute([$shopId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getPickupHistory Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // 库存相关
    // ----------------

    /**
     * 获取库存汇总
     */
    public static function getInventorySummary($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_inventory_summary WHERE ShopID = ? ORDER BY Title, ConditionGrade");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getInventorySummary Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取库存详细列表
     */
    public static function getInventoryDetail($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_staff_inventory_detail WHERE ShopID = ? AND Status = 'Available' ORDER BY AcquiredDate DESC");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getInventoryDetail Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get inventory summary for all shops (Admin view)
     * Uses vw_all_shops_inventory_summary view
     */
    public static function getInventorySummaryAllShops($pdo) {
        try {
            $stmt = $pdo->query("
                SELECT * FROM vw_all_shops_inventory_summary
                ORDER BY ShopName, Title, ConditionGrade
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getInventorySummaryAllShops Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get inventory detail for all shops (Admin view)
     * Uses vw_all_shops_inventory_detail view
     */
    public static function getInventoryDetailAllShops($pdo) {
        try {
            $stmt = $pdo->query("
                SELECT * FROM vw_all_shops_inventory_detail
                ORDER BY ShopName, AcquiredDate DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getInventoryDetailAllShops Error: " . $e->getMessage());
            return [];
        }
    }


    // ----------------
    // 认证相关
    // ----------------

    /**
     * 员工认证
     */
    public static function getEmployeeForAuth($pdo, $username) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_auth_employee WHERE Username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getEmployeeForAuth Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 客户认证
     */
    public static function getCustomerForAuth($pdo, $email) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_auth_customer WHERE Email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getCustomerForAuth Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 管理与报表相关
    // ----------------

    /**
     * 获取 KPI 统计数据
     */
    public static function getKpiStats($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_kpi_stats")->fetch();
        } catch (PDOException $e) {
            error_log("getKpiStats Error: " . $e->getMessage());
            return ['TotalSales' => 0, 'ActiveOrders' => 0, 'LowStockCount' => 0];
        }
    }

    /**
     * 获取顶级客户列表
     */
    public static function getTopCustomers($pdo, $limit = 5) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_report_top_customers LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getTopCustomers Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取死库存预警
     */
    public static function getDeadStockAlert($pdo, $limit = 10) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_dead_stock_alert LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getDeadStockAlert Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取低库存预警
     */
    public static function getLowStockAlert($pdo, $limit = 10) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_low_stock_alert LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getLowStockAlert Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺业绩
     */
    public static function getShopPerformance($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_manager_shop_performance ORDER BY Revenue DESC")->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopPerformance Error: " . $e->getMessage());
            return [];
        }
    }

    // 【清理】getPendingTransfers 已删除 - 功能由其他方法替代

    /**
     * 获取员工列表
     */
    public static function getEmployeeList($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_admin_employee_list ORDER BY Role ASC, Name ASC")->fetchAll();
        } catch (PDOException $e) {
            error_log("getEmployeeList Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺员工列表（Manager用）
     * 只返回指定店铺的Staff和当前Manager自己
     */
    public static function getEmployeesByShop($pdo, $shopId, $currentEmployeeId) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_admin_employee_list
                WHERE (ShopID = ? AND Role = 'Staff') OR EmployeeID = ?
                ORDER BY Role ASC, Name ASC
            ");
            $stmt->execute([$shopId, $currentEmployeeId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getEmployeesByShop Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 通过ID获取员工信息
     */
    public static function getEmployeeById($pdo, $employeeId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_admin_employee_list WHERE EmployeeID = ?");
            $stmt->execute([$employeeId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getEmployeeById Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取客户列表
     */
    public static function getCustomerList($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_admin_customer_list ORDER BY Points DESC")->fetchAll();
        } catch (PDOException $e) {
            error_log("getCustomerList Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺列表
     */
    public static function getShopList($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_shop_list")->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopList Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取专辑列表
     */
    public static function getReleaseList($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_release_simple_list")->fetchAll();
        } catch (PDOException $e) {
            error_log("getReleaseList Error: " . $e->getMessage());
            return [];
        }
    }

    // 【清理】getCustomerSimpleList 已删除 - 功能由 getCustomerListSimple 替代
    // 【清理】getBuybackOrders 已删除 - 功能由其他方法替代

    // =============================================
    // 【存储过程调用】写操作
    // =============================================

    // ----------------
    // 供应商订单流程
    // ----------------

    /**
     * 创建供应商订单
     */
    public static function createSupplierOrder($pdo, $supplierId, $employeeId, $shopId) {
        try {
            $stmt = $pdo->prepare("CALL sp_create_supplier_order(?, ?, ?, @order_id)");
            $stmt->execute([$supplierId, $employeeId, $shopId]);
            $result = $pdo->query("SELECT @order_id AS order_id")->fetch();
            return $result['order_id'] > 0 ? $result['order_id'] : false;
        } catch (PDOException $e) {
            error_log("createSupplierOrder Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加供应商订单行
     * 【修复】添加ConditionGrade和SalePrice参数
     */
    public static function addSupplierOrderLine($pdo, $orderId, $releaseId, $quantity, $unitCost, $conditionGrade = 'New', $salePrice = null) {
        try {
            $stmt = $pdo->prepare("CALL sp_add_supplier_order_line(?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$orderId, $releaseId, $quantity, $unitCost, $conditionGrade, $salePrice]);
        } catch (PDOException $e) {
            error_log("addSupplierOrderLine Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 接收供应商订单并生成库存
     * 【修复】智能事务管理 - 仅在没有活动事务时才开启新事务
     */
    public static function receiveSupplierOrder($pdo, $orderId, $batchNo, $conditionGrade = 'New', $markupRate = 0.50) {
        $ownTransaction = false;
        try {
            // 【修复】检查是否已在事务中，避免嵌套事务
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTransaction = true;
            }

            $stmt = $pdo->prepare("CALL sp_receive_supplier_order(?, ?, ?, ?)");
            $result = $stmt->execute([$orderId, $batchNo, $conditionGrade, $markupRate]);

            if ($result && $ownTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("receiveSupplierOrder Error: " . $e->getMessage());
            throw $e; // 重新抛出让外层处理
        }
    }

    // ----------------
    // 回购流程
    // ----------------

    /**
     * 处理客户回购
     * 【修复】智能事务管理 - 仅在没有活动事务时才开启新事务
     */
    public static function processBuyback($pdo, $customerId, $employeeId, $shopId, $releaseId, $quantity, $unitPrice, $conditionGrade, $resalePrice) {
        $ownTransaction = false;
        try {
            // 【修复】检查是否已在事务中，避免嵌套事务
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTransaction = true;
            }

            $stmt = $pdo->prepare("CALL sp_process_buyback(?, ?, ?, ?, ?, ?, ?, ?, @buyback_id)");
            $stmt->execute([$customerId, $employeeId, $shopId, $releaseId, $quantity, $unitPrice, $conditionGrade, $resalePrice]);
            $result = $pdo->query("SELECT @buyback_id AS buyback_id")->fetch();

            if ($result['buyback_id'] > 0) {
                if ($ownTransaction) {
                    $pdo->commit();
                }
                return $result['buyback_id'];
            }
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            return false;
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("processBuyback Error: " . $e->getMessage());
            throw $e; // 重新抛出让外层处理
        }
    }

    // ----------------
    // 库存调拨流程
    // ----------------


    /**
     * 完成库存调拨
     * 【修复】智能事务管理 - 仅在没有活动事务时才开启新事务
     */
    public static function completeTransfer($pdo, $transferId, $receivedByEmployeeId) {
        $ownTransaction = false;
        try {
            // 【修复】检查是否已在事务中，避免嵌套事务
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTransaction = true;
            }

            $stmt = $pdo->prepare("CALL sp_complete_transfer(?, ?)");
            $result = $stmt->execute([$transferId, $receivedByEmployeeId]);

            if ($result && $ownTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("completeTransfer Error: " . $e->getMessage());
            throw $e; // 重新抛出让外层处理
        }
    }

    // ----------------
    // 销售流程
    // ----------------

    /**
     * 创建客户订单
     */
    public static function createCustomerOrder($pdo, $customerId, $shopId, $employeeId, $orderType = 'InStore') {
        try {
            $stmt = $pdo->prepare("CALL sp_create_customer_order(?, ?, ?, ?, @order_id)");
            $stmt->execute([$customerId, $shopId, $employeeId, $orderType]);
            $result = $pdo->query("SELECT @order_id AS order_id")->fetch();
            return $result['order_id'] > 0 ? $result['order_id'] : false;
        } catch (PDOException $e) {
            error_log("createCustomerOrder Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加订单商品
     */
    public static function addOrderItem($pdo, $orderId, $stockItemId, $priceAtSale) {
        try {
            $stmt = $pdo->prepare("CALL sp_add_order_item(?, ?, ?)");
            return $stmt->execute([$orderId, $stockItemId, $priceAtSale]);
        } catch (PDOException $e) {
            error_log("addOrderItem Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 支付订单（状态从 Pending 改为 Paid）
     */
    public static function payOrder($pdo, $orderId) {
        try {
            $stmt = $pdo->prepare("CALL sp_pay_order(?)");
            return $stmt->execute([$orderId]);
        } catch (PDOException $e) {
            error_log("payOrder Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 完成订单
     * 【修复】执行存储过程后验证订单状态是否真正更新为Completed
     * 【调试增强】增加详细日志输出
     */
    public static function completeOrder($pdo, $orderId) {
        try {
            error_log("=== completeOrder START: orderId=$orderId ===");

            // 通过视图检查订单当前状态
            $preCheck = $pdo->prepare("SELECT OrderStatus, OrderType FROM vw_order_shop_validation WHERE OrderID = ?");
            $preCheck->execute([$orderId]);
            $preResult = $preCheck->fetch();
            error_log("PRE-CHECK: OrderID=$orderId, Status=" . ($preResult['OrderStatus'] ?? 'NULL') . ", Type=" . ($preResult['OrderType'] ?? 'NULL'));
            $preCheck->closeCursor();

            // 执行存储过程
            $stmt = $pdo->prepare("CALL sp_complete_order(?)");
            $executeResult = $stmt->execute([$orderId]);
            error_log("sp_complete_order execute result: " . ($executeResult ? 'true' : 'false'));

            // 检查是否有错误信息
            $errorInfo = $stmt->errorInfo();
            if ($errorInfo[0] !== '00000') {
                error_log("sp_complete_order error: " . json_encode($errorInfo));
            }

            // 清除可能的多结果集
            while ($stmt->nextRowset()) {}
            $stmt->closeCursor();

            // 通过视图验证订单状态是否真的变成了Completed
            $checkStmt = $pdo->prepare("SELECT OrderStatus, OrderType FROM vw_order_shop_validation WHERE OrderID = ?");
            $checkStmt->execute([$orderId]);
            $result = $checkStmt->fetch();
            $checkStmt->closeCursor();

            error_log("POST-CHECK: OrderID=$orderId, Status=" . ($result['OrderStatus'] ?? 'NULL') . ", Type=" . ($result['OrderType'] ?? 'NULL'));

            if (!$result || $result['OrderStatus'] !== 'Completed') {
                error_log("completeOrder Verification Failed: Order $orderId status is " . ($result['OrderStatus'] ?? 'NULL') . " (expected: Completed)");
                return false;
            }

            error_log("=== completeOrder SUCCESS: orderId=$orderId ===");
            return true;
        } catch (PDOException $e) {
            error_log("completeOrder PDOException: " . $e->getMessage());
            error_log("completeOrder Stack: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * 取消订单
     */
    public static function cancelOrder($pdo, $orderId) {
        try {
            $stmt = $pdo->prepare("CALL sp_cancel_order(?)");
            return $stmt->execute([$orderId]);
        } catch (PDOException $e) {
            error_log("cancelOrder Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 用户管理流程
    // ----------------

    /**
     * 注册新客户
     * @return array ['customer_id' => int, 'tier_id' => int] 或 false
     *         customer_id = -2 表示邮箱已存在
     */
    public static function registerCustomer($pdo, $name, $email, $passwordHash, $birthday = null) {
        try {
            $stmt = $pdo->prepare("CALL sp_register_customer(?, ?, ?, ?, @customer_id, @tier_id)");
            $stmt->execute([$name, $email, $passwordHash, $birthday]);
            $result = $pdo->query("SELECT @customer_id AS customer_id, @tier_id AS tier_id")->fetch();
            return [
                'customer_id' => $result['customer_id'],
                'tier_id' => $result['tier_id']
            ];
        } catch (PDOException $e) {
            error_log("registerCustomer Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新客户资料
     */
    public static function updateCustomerProfile($pdo, $customerId, $name, $passwordHash = null) {
        try {
            $stmt = $pdo->prepare("CALL sp_update_customer_profile(?, ?, ?)");
            return $stmt->execute([$customerId, $name, $passwordHash]);
        } catch (PDOException $e) {
            error_log("updateCustomerProfile Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加员工
     */
    public static function addEmployee($pdo, $name, $username, $passwordHash, $role, $shopId) {
        try {
            $stmt = $pdo->prepare("CALL sp_add_employee(?, ?, ?, ?, ?, @employee_id)");
            $stmt->execute([$name, $username, $passwordHash, $role, $shopId]);
            $result = $pdo->query("SELECT @employee_id AS employee_id")->fetch();
            return $result['employee_id'] > 0 ? $result['employee_id'] : false;
        } catch (PDOException $e) {
            error_log("addEmployee Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新员工信息
     */
    public static function updateEmployee($pdo, $employeeId, $name, $role, $shopId, $passwordHash = null) {
        try {
            $stmt = $pdo->prepare("CALL sp_update_employee(?, ?, ?, ?, ?)");
            return $stmt->execute([$employeeId, $name, $role, $shopId, $passwordHash]);
        } catch (PDOException $e) {
            error_log("updateEmployee Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除员工
     */
    public static function deleteEmployee($pdo, $employeeId, $currentUserId) {
        try {
            $stmt = $pdo->prepare("CALL sp_delete_employee(?, ?)");
            return $stmt->execute([$employeeId, $currentUserId]);
        } catch (PDOException $e) {
            error_log("deleteEmployee Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 供应商相关 (Admin)
    // ----------------

    /**
     * 获取供应商列表
     * 【架构重构】改用视图替换直接表访问
     */
    public static function getSupplierList($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_supplier_list")->fetchAll();
        } catch (PDOException $e) {
            error_log("getSupplierList Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 添加供应商
     */
    public static function addSupplier($pdo, $name, $email) {
        try {
            $stmt = $pdo->prepare("CALL sp_add_supplier(?, ?, @supplier_id)");
            $stmt->execute([$name, $email]);
            $result = $pdo->query("SELECT @supplier_id AS supplier_id")->fetch();
            // 【修改】返回实际的ID值（包括-2表示重名）
            return $result['supplier_id'];
        } catch (PDOException $e) {
            error_log("addSupplier Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新供应商
     */
    public static function updateSupplier($pdo, $supplierId, $name, $email) {
        try {
            $stmt = $pdo->prepare("CALL sp_update_supplier(?, ?, ?)");
            return $stmt->execute([$supplierId, $name, $email]);
        } catch (PDOException $e) {
            error_log("updateSupplier Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除供应商
     * @return int 1=成功, -1=有依赖不能删除, 0=失败
     */
    public static function deleteSupplier($pdo, $supplierId) {
        try {
            $stmt = $pdo->prepare("CALL sp_delete_supplier(?, @result)");
            $stmt->execute([$supplierId]);
            $result = $pdo->query("SELECT @result AS result")->fetch();
            return (int)$result['result'];
        } catch (PDOException $e) {
            error_log("deleteSupplier Error: " . $e->getMessage());
            return 0;
        }
    }

    // ----------------
    // 专辑/产品相关 (Admin)
    // ----------------

    /**
     * 添加专辑
     */
    public static function addRelease($pdo, $title, $artist, $label, $year, $genre, $desc) {
        try {
            $stmt = $pdo->prepare("CALL sp_add_release(?, ?, ?, ?, ?, ?, @release_id)");
            $stmt->execute([$title, $artist, $label, $year, $genre, $desc]);
            $result = $pdo->query("SELECT @release_id AS release_id")->fetch();
            // 【修改】返回实际的ID值（包括-2表示重名）
            return $result['release_id'];
        } catch (PDOException $e) {
            error_log("addRelease Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新专辑
     */
    public static function updateRelease($pdo, $releaseId, $title, $artist, $label, $year, $genre, $desc) {
        try {
            $stmt = $pdo->prepare("CALL sp_update_release(?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$releaseId, $title, $artist, $label, $year, $genre, $desc]);
        } catch (PDOException $e) {
            error_log("updateRelease Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 采购相关 (Admin)
    // ----------------

    /**
     * 获取待处理供应商订单
     */
    public static function getPendingSupplierOrders($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_admin_supplier_orders WHERE Status = 'Pending' ORDER BY OrderDate DESC")->fetchAll();
        } catch (PDOException $e) {
            error_log("getPendingSupplierOrders Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取已完成的供应商订单历史记录
     */
    public static function getReceivedSupplierOrders($pdo, $limit = 50) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_admin_supplier_orders WHERE Status = 'Received' ORDER BY ReceivedDate DESC LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getReceivedSupplierOrders Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取仓库待收货的供应商订单（warehouse staff用）
     * Uses vw_warehouse_pending_receipts view
     */
    public static function getWarehousePendingReceipts($pdo, $warehouseShopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_warehouse_pending_receipts
                WHERE DestinationShopID = ?
            ");
            $stmt->execute([$warehouseShopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getWarehousePendingReceipts Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // 订单履约相关 (Staff)
    // ----------------

    // 【清理】getOnlineOrdersAwaitingShipment 和 getOnlineOrdersShipped 已删除 - 功能由 getWarehouseOnlineOrders 替代

    /**
     * 获取订单用于取货验证
     * 【架构重构】使用视图替换直接表访问
     */
    public static function getOrderForPickupValidation($pdo, $orderId, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT OrderID, TotalAmount, OrderStatus, FulfillmentType
                FROM vw_order_for_pickup
                WHERE OrderID = ? AND ShopID = ? AND OrderStatus = 'Paid' AND FulfillmentType = 'Pickup'
            ");
            $stmt->execute([$orderId, $shopId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getOrderForPickupValidation Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 报表相关 (Manager)
    // ----------------

    /**
     * 获取按流派销售报表
     */
    public static function getSalesByGenre($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_report_sales_by_genre ORDER BY AvgDaysToSell ASC")->fetchAll();
        } catch (PDOException $e) {
            error_log("getSalesByGenre Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取月度销售趋势
     */
    public static function getMonthlySalesTrend($pdo, $limit = 12) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_report_monthly_sales ORDER BY SalesMonth DESC LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getMonthlySalesTrend Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取订单详情（客户端）
     * 【架构重构】使用视图替换直接表访问
     */
    public static function getCustomerOrderDetail($pdo, $orderId, $customerId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_order_detail WHERE OrderID = ? AND CustomerID = ?");
            $stmt->execute([$orderId, $customerId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getCustomerOrderDetail Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取订单商品明细
     */
    public static function getOrderItems($pdo, $orderId, $customerId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_order_history WHERE OrderID = ? AND CustomerID = ?");
            $stmt->execute([$orderId, $customerId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getOrderItems Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // 辅助函数
    // ----------------


    // =============================================
    // 【Manager/Admin重构】新增数据获取方法
    // =============================================

    /**
     * 【架构重构Phase3】获取店铺的KPI统计（限定店铺）
     * 改用 vw_shop_kpi_stats 视图
     * 营业额只统计已确认收入（Paid/Completed状态的订单）
     */
    public static function getShopKpiStats($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT TotalSales, ActiveOrders
                FROM vw_shop_kpi_stats
                WHERE ShopID = ?
            ");
            $stmt->execute([$shopId]);
            $result = $stmt->fetch();
            return $result ?: ['TotalSales' => 0, 'ActiveOrders' => 0];
        } catch (PDOException $e) {
            error_log("getShopKpiStats Error: " . $e->getMessage());
            return ['TotalSales' => 0, 'ActiveOrders' => 0];
        }
    }

    /**
     * 获取最受欢迎单品
     */
    public static function getPopularItems($pdo, $shopId = null, $limit = 1) {
        try {
            $sql = "SELECT * FROM vw_popular_items";
            $params = [];
            // Note: 该视图不包含店铺过滤，这里返回全局数据
            $sql .= " LIMIT ?";
            $params[] = $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getPopularItems Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺总支出（Buyback）
     */
    public static function getShopTotalExpense($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_shop_total_expense WHERE ShopID = ?");
            $stmt->execute([$shopId]);
            $result = $stmt->fetch();
            return $result ? $result : ['TotalExpense' => 0, 'BuybackCount' => 0];
        } catch (PDOException $e) {
            error_log("getShopTotalExpense Error: " . $e->getMessage());
            return ['TotalExpense' => 0, 'BuybackCount' => 0];
        }
    }

    /**
     * 获取店铺Top消费者
     */
    public static function getShopTopCustomers($pdo, $shopId, $limit = 5) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_shop_top_customers WHERE ShopID = ? ORDER BY TotalSpent DESC LIMIT ?");
            $stmt->execute([$shopId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopTopCustomers Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺死库存（>60天，按condition分组）
     */
    public static function getShopDeadStock($pdo, $shopId, $limit = 10) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_dead_stock_by_shop WHERE ShopID = ? ORDER BY MaxDaysInStock DESC LIMIT ?");
            $stmt->execute([$shopId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopDeadStock Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺低库存（<3件，按condition分组）
     */
    public static function getShopLowStock($pdo, $shopId, $limit = 10) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_low_stock_by_shop WHERE ShopID = ? ORDER BY AvailableQuantity ASC LIMIT ?");
            $stmt->execute([$shopId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopLowStock Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺收入明细（按类型分组）
     */
    public static function getShopRevenueByType($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_shop_revenue_by_type WHERE ShopID = ?");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopRevenueByType Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取客户在某店铺的订单历史
     */
    public static function getCustomerShopOrders($pdo, $customerId, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_shop_orders WHERE CustomerID = ? AND ShopID = ? ORDER BY OrderDate DESC");
            $stmt->execute([$customerId, $shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCustomerShopOrders Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取客户在某店铺的Buyback历史
     */
    public static function getCustomerBuybackHistory($pdo, $customerId, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_buyback_history WHERE CustomerID = ? AND ShopID = ? ORDER BY BuybackDate DESC");
            $stmt->execute([$customerId, $shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCustomerBuybackHistory Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺某类型的订单明细
     */
    public static function getShopOrderDetails($pdo, $shopId, $orderCategory = null) {
        try {
            $sql = "SELECT * FROM vw_shop_order_details WHERE ShopID = ?";
            $params = [$shopId];
            if ($orderCategory !== null) {
                $sql .= " AND OrderCategory = ?";
                $params[] = $orderCategory;
            }
            $sql .= " ORDER BY OrderDate DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopOrderDetails Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺Buyback明细
     */
    public static function getShopBuybackDetails($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_buyback_history WHERE ShopID = ? ORDER BY BuybackDate DESC");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopBuybackDetails Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取Manager发出的申请
     */
    public static function getManagerRequestsSent($pdo, $employeeId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_manager_requests_sent WHERE RequestedByEmployeeID = ? ORDER BY CreatedAt DESC");
            $stmt->execute([$employeeId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getManagerRequestsSent Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取Admin待处理的申请
     */
    public static function getAdminPendingRequests($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_admin_pending_requests ORDER BY CreatedAt ASC")->fetchAll();
        } catch (PDOException $e) {
            error_log("getAdminPendingRequests Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取Admin所有申请
     */
    public static function getAdminAllRequests($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_admin_all_requests ORDER BY CreatedAt DESC")->fetchAll();
        } catch (PDOException $e) {
            error_log("getAdminAllRequests Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 创建调价申请
     */
    public static function createPriceAdjustmentRequest($pdo, $employeeId, $shopId, $releaseId, $conditionGrade, $quantity, $currentPrice, $requestedPrice, $reason) {
        try {
            $stmt = $pdo->prepare("CALL sp_create_price_adjustment_request(?, ?, ?, ?, ?, ?, ?, ?, @request_id)");
            $stmt->execute([$employeeId, $shopId, $releaseId, $conditionGrade, $quantity, $currentPrice, $requestedPrice, $reason]);
            $result = $pdo->query("SELECT @request_id AS request_id")->fetch();
            return $result['request_id'] > 0 ? $result['request_id'] : false;
        } catch (PDOException $e) {
            error_log("createPriceAdjustmentRequest Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 创建调货申请
     */
    public static function createTransferRequest($pdo, $employeeId, $fromShopId, $toShopId, $releaseId, $conditionGrade, $quantity, $reason) {
        try {
            $stmt = $pdo->prepare("CALL sp_create_transfer_request(?, ?, ?, ?, ?, ?, ?, @request_id)");
            $stmt->execute([$employeeId, $fromShopId, $toShopId, $releaseId, $conditionGrade, $quantity, $reason]);
            $result = $pdo->query("SELECT @request_id AS request_id")->fetch();
            return $result['request_id'] > 0 ? $result['request_id'] : false;
        } catch (PDOException $e) {
            error_log("createTransferRequest Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin审批申请
     * 【修复】智能事务管理 - 仅在没有活动事务时才开启新事务
     */
    public static function respondToRequest($pdo, $requestId, $adminId, $approved, $responseNote) {
        $ownTransaction = false;
        try {
            // 【修复】检查是否已在事务中，避免嵌套事务
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTransaction = true;
            }

            $stmt = $pdo->prepare("CALL sp_respond_to_request(?, ?, ?, ?)");
            $result = $stmt->execute([$requestId, $adminId, $approved, $responseNote]);

            if ($result && $ownTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("respondToRequest Error: " . $e->getMessage());
            throw $e; // 重新抛出让外层处理
        }
    }

    /**
     * 更新库存价格
     */
    public static function updateStockPrice($pdo, $shopId, $releaseId, $conditionGrade, $newPrice) {
        try {
            $stmt = $pdo->prepare("CALL sp_update_stock_price(?, ?, ?, ?)");
            return $stmt->execute([$shopId, $releaseId, $conditionGrade, $newPrice]);
        } catch (PDOException $e) {
            error_log("updateStockPrice Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取库存价格信息（按Release和Condition分组）
     */
    public static function getStockPriceByCondition($pdo, $releaseId = null, $shopId = null) {
        try {
            $sql = "SELECT * FROM vw_stock_price_by_condition WHERE 1=1";
            $params = [];
            if ($releaseId !== null) {
                $sql .= " AND ReleaseID = ?";
                $params[] = $releaseId;
            }
            if ($shopId !== null) {
                $sql .= " AND ShopID = ?";
                $params[] = $shopId;
            }
            $sql .= " ORDER BY Title, ShopName, FIELD(ConditionGrade, 'New', 'Mint', 'NM', 'VG+', 'VG')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getStockPriceByCondition Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取按流派销售明细（店铺级别）
     */
    public static function getSalesByGenreDetail($pdo, $shopId, $genre) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_sales_by_genre_detail WHERE ShopID = ? AND Genre = ? ORDER BY OrderDate DESC");
            $stmt->execute([$shopId, $genre]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getSalesByGenreDetail Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺按流派销售统计
     */
    public static function getShopSalesByGenre($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    Genre,
                    COUNT(DISTINCT OrderID) AS TotalOrders,
                    COUNT(*) AS ItemsSold,
                    SUM(PriceAtSale) AS TotalRevenue,
                    AVG(PriceAtSale) AS AvgPrice,
                    AVG(DaysToSell) AS AvgDaysToSell
                FROM vw_sales_by_genre_detail
                WHERE ShopID = ?
                GROUP BY Genre
                ORDER BY TotalRevenue DESC
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopSalesByGenre Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取月度销售明细（店铺级别）
     */
    public static function getMonthlySalesDetail($pdo, $shopId, $month) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_monthly_sales_detail WHERE ShopID = ? AND SalesMonth = ? ORDER BY OrderDate DESC");
            $stmt->execute([$shopId, $month]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getMonthlySalesDetail Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺月度销售趋势
     */
    public static function getShopMonthlySalesTrend($pdo, $shopId, $limit = 12) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    SalesMonth,
                    COUNT(DISTINCT OrderID) AS OrderCount,
                    SUM(PriceAtSale) AS MonthlyRevenue
                FROM vw_monthly_sales_detail
                WHERE ShopID = ?
                GROUP BY SalesMonth
                ORDER BY SalesMonth DESC
                LIMIT ?
            ");
            $stmt->execute([$shopId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopMonthlySalesTrend Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺按艺术家利润分析
     * Uses vw_shop_artist_profit_analysis view
     */
    public static function getShopArtistProfitAnalysis($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_shop_artist_profit_analysis
                WHERE ShopID = ?
                ORDER BY GrossProfit DESC
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopArtistProfitAnalysis Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取按艺术家销售明细
     * Uses vw_artist_sales_detail view
     */
    public static function getArtistSalesDetail($pdo, $shopId, $artistName) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_artist_sales_detail
                WHERE ShopID = ? AND ArtistName = ?
            ");
            $stmt->execute([$shopId, $artistName]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getArtistSalesDetail Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺批次售卖分析
     * Uses vw_shop_batch_sales_analysis view
     */
    public static function getShopBatchSalesAnalysis($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_shop_batch_sales_analysis
                WHERE ShopID = ?
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopBatchSalesAnalysis Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取批次售卖明细
     * Uses vw_batch_sales_detail view
     */
    public static function getBatchSalesDetail($pdo, $shopId, $batchNo) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_batch_sales_detail
                WHERE ShopID = ? AND BatchNo = ?
            ");
            $stmt->execute([$shopId, $batchNo]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getBatchSalesDetail Error: " . $e->getMessage());
            return [];
        }
    }

    // =============================================
    // 【架构重构Phase2】新增包装方法
    // =============================================

    // ----------------
    // 购物车验证相关
    // ----------------

    /**
     * 验证购物车商品可用性
     * 替换 cart.php 中的直接表访问
     */
    public static function validateCartItem($pdo, $stockItemId, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_cart_item_validation
                WHERE StockItemID = ? AND ShopID = ? AND Status = 'Available'
            ");
            $stmt->execute([$stockItemId, $shopId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("validateCartItem Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取购物车商品详情（带店铺信息）
     * 替换 cart.php 中的购物车数据获取
     */
    public static function getCartItemsDetail($pdo, $stockIds) {
        if (empty($stockIds)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($stockIds), '?'));
            $sql = "SELECT * FROM vw_cart_items_detail WHERE StockItemID IN ($placeholders) AND Status = 'Available'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($stockIds);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCartItemsDetail Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构】获取结账购物车商品详情（含店铺地址）
     * 替换 checkout.php 中的购物车数据获取
     */
    public static function getCheckoutCartItems($pdo, $stockIds) {
        if (empty($stockIds)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($stockIds), '?'));
            $sql = "SELECT * FROM vw_checkout_cart_items WHERE StockItemID IN ($placeholders) AND Status = 'Available'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($stockIds);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCheckoutCartItems Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // 员工店铺信息
    // ----------------

    /**
     * 获取员工及其店铺信息
     * 替换 pos.php, fulfillment.php, buyback.php 中的员工信息查询
     */
    public static function getEmployeeShopInfo($pdo, $employeeId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_employee_shop_info WHERE EmployeeID = ?");
            $stmt->execute([$employeeId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getEmployeeShopInfo Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // POS库存相关
    // ----------------

    /**
     * 获取POS分组库存列表
     * 替换 pos.php 中的库存分组查询
     */
    public static function getPosStockGrouped($pdo, $shopId, $search = '') {
        try {
            // 【修改】首先获取有库存的release分组
            $sql = "SELECT * FROM vw_pos_stock_grouped WHERE ShopID = ?";
            $params = [$shopId];

            if (!empty($search)) {
                $sql .= " AND (Title LIKE ? OR ArtistName LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stockItems = $stmt->fetchAll();

            // 创建已有库存的release+condition组合的键集
            $existingKeys = [];
            foreach ($stockItems as $item) {
                $key = $item['ReleaseID'] . '_' . $item['ConditionGrade'];
                $existingKeys[$key] = true;
            }

            // 【新增】获取所有release（包括无库存的）
            $sqlAll = "SELECT * FROM vw_pos_all_releases WHERE 1=1";
            $paramsAll = [];

            if (!empty($search)) {
                $sqlAll .= " AND (Title LIKE ? OR ArtistName LIKE ?)";
                $paramsAll[] = "%$search%";
                $paramsAll[] = "%$search%";
            }

            $stmtAll = $pdo->prepare($sqlAll);
            $stmtAll->execute($paramsAll);
            $allReleases = $stmtAll->fetchAll();

            // 为没有库存的release创建一个默认条目（显示为0库存）
            foreach ($allReleases as $release) {
                // 检查这个release是否已经有库存条目
                $hasStock = false;
                foreach ($stockItems as $item) {
                    if ($item['ReleaseID'] == $release['ReleaseID']) {
                        $hasStock = true;
                        break;
                    }
                }

                // 如果没有任何库存，添加一个"N/A"条目显示为0
                if (!$hasStock) {
                    $stockItems[] = [
                        'ShopID' => $shopId,
                        'ReleaseID' => $release['ReleaseID'],
                        'Title' => $release['Title'],
                        'ArtistName' => $release['ArtistName'],
                        'ConditionGrade' => 'N/A',
                        'Quantity' => 0,
                        'UnitPrice' => $release['SuggestedPrice'],
                        'StockItemIds' => ''
                    ];
                }
            }

            // 【修复】按库存优先排序：有货的在前面，无货的在后面
            usort($stockItems, function($a, $b) {
                // 首先按是否有库存排序（有库存的在前）
                $aHasStock = ($a['Quantity'] ?? 0) > 0;
                $bHasStock = ($b['Quantity'] ?? 0) > 0;
                if ($aHasStock && !$bHasStock) return -1;
                if (!$aHasStock && $bHasStock) return 1;
                // 其次按标题排序
                return strcmp($a['Title'], $b['Title']);
            });

            return $stockItems;
        } catch (PDOException $e) {
            error_log("getPosStockGrouped Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取订单行详情
     * 替换 pos.php 中的订单明细查询
     */
    public static function getOrderLineDetail($pdo, $orderId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_order_line_detail WHERE OrderID = ?");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getOrderLineDetail Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取订单基础信息（用于API验证和显示）
     * 【修复】使用 vw_customer_my_orders_list 替代 vw_staff_pos_history
     * vw_staff_pos_history 只包含 InStore 订单，无法验证 Online 订单
     */
    public static function getOrderBasicInfo($pdo, $orderId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_my_orders_list WHERE OrderID = ?");
            $stmt->execute([$orderId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getOrderBasicInfo Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 【架构重构Phase2】验证POS添加商品
     * 替换 pos.php 中的add_item验证查询
     */
    public static function validatePosCartItem($pdo, $stockItemId, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_pos_cart_item_validation
                WHERE StockItemID = ? AND ShopID = ?
            ");
            $stmt->execute([$stockItemId, $shopId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("validatePosCartItem Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 【架构重构Phase2】获取POS可用库存ID（按组过滤）
     * 替换 pos.php 中的add_multiple库存ID查询
     */
    public static function getPosAvailableStockIds($pdo, $shopId, $releaseId, $conditionGrade, $unitPrice) {
        try {
            $stmt = $pdo->prepare("
                SELECT StockItemID FROM vw_pos_available_stock_ids
                WHERE ShopID = ? AND ReleaseID = ? AND ConditionGrade = ? AND UnitPrice = ?
            ");
            $stmt->execute([$shopId, $releaseId, $conditionGrade, $unitPrice]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("getPosAvailableStockIds Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase2】获取简单客户列表
     * 替换 pos.php 中的客户下拉框查询
     * 【视图优化】改用 vw_customer_simple_list，删除冗余别名视图
     */
    public static function getCustomerListSimple($pdo, $limit = 100) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_simple_list LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCustomerListSimple Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // Fulfillment调拨相关
    // ----------------


    /**
     * 确认调拨发货
     * 替换 fulfillment.php 中的调拨发货操作
     * 【修复】智能事务管理 - 仅在没有活动事务时才开启新事务，避免嵌套事务问题
     */
    public static function confirmTransferDispatch($pdo, $transferId, $employeeId) {
        $ownTransaction = false;
        try {
            // 【修复】检查是否已在事务中，避免嵌套事务
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTransaction = true;
            }

            $stmt = $pdo->prepare("CALL sp_confirm_transfer_dispatch(?, ?)");
            $result = $stmt->execute([$transferId, $employeeId]);

            if ($result && $ownTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("confirmTransferDispatch Error: " . $e->getMessage());
            throw $e; // 重新抛出让外层处理
        }
    }


    /**
     * 【架构重构】取消调拨
     * 替换 fulfillment.php 中的 DELETE FROM InventoryTransfer
     * 【修复】智能事务管理 - 仅在没有活动事务时才开启新事务
     */
    public static function cancelTransfer($pdo, $transferId, $shopId) {
        $ownTransaction = false;
        try {
            // 【修复】检查是否已在事务中，避免嵌套事务
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTransaction = true;
            }

            $stmt = $pdo->prepare("CALL sp_cancel_transfer(?, ?)");
            $result = $stmt->execute([$transferId, $shopId]);

            if ($result && $ownTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("cancelTransfer Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 【架构重构Phase2】获取待发货调拨分组列表（源店铺视角）
     * 替换 fulfillment.php 中的待发货调拨分组查询
     */
    public static function getFulfillmentPendingTransfersGrouped($pdo, $fromShopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_fulfillment_pending_transfers_grouped WHERE FromShopID = ?");
            $stmt->execute([$fromShopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getFulfillmentPendingTransfersGrouped Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase2】获取待接收调拨分组列表（目标店铺视角）
     * 替换 fulfillment.php 中的待接收调拨分组查询
     */
    public static function getFulfillmentIncomingTransfersGrouped($pdo, $toShopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_fulfillment_incoming_transfers_grouped WHERE ToShopID = ?");
            $stmt->execute([$toShopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getFulfillmentIncomingTransfersGrouped Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase2】获取订单履行列表（按状态过滤）
     * 替换 fulfillment.php 中的订单列表查询
     */
    public static function getFulfillmentOrders($pdo, $shopId, $statusFilter = '') {
        try {
            $sql = "SELECT * FROM vw_fulfillment_orders WHERE FulfilledByShopID = ?";
            $params = [$shopId];

            switch ($statusFilter) {
                case 'pending':
                    $sql .= " AND OrderStatus IN ('Pending', 'Paid')";
                    break;
                case 'shipping':
                    $sql .= " AND OrderStatus = 'Shipped'";
                    break;
                case 'completed':
                    $sql .= " AND OrderStatus = 'Completed'";
                    break;
                case 'cancelled':
                    $sql .= " AND OrderStatus = 'Cancelled'";
                    break;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getFulfillmentOrders Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase2】获取订单状态统计
     * 替换 fulfillment.php 中的状态统计查询
     */
    public static function getFulfillmentOrderStatusCounts($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT OrderStatus, cnt FROM vw_fulfillment_order_status_counts WHERE FulfilledByShopID = ?");
            $stmt->execute([$shopId]);
            $result = [];
            while ($row = $stmt->fetch()) {
                $result[$row['OrderStatus']] = $row['cnt'];
            }
            return $result;
        } catch (PDOException $e) {
            error_log("getFulfillmentOrderStatusCounts Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase3】验证订单属于指定店铺
     * 改用 vw_order_shop_validation 视图
     * 替换 fulfillment.php 中的订单验证查询
     */
    public static function validateOrderBelongsToShop($pdo, $orderId, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT FulfilledByShopID, OrderStatus FROM vw_order_shop_validation WHERE OrderID = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if ($order && $order['FulfilledByShopID'] == $shopId) {
                return $order;
            }
            return false;
        } catch (PDOException $e) {
            error_log("validateOrderBelongsToShop Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 【架构重构Phase3】验证调拨属于指定店铺（源店铺）
     * 改用 vw_transfer_validation 视图
     * 替换 fulfillment.php 中的调拨验证查询
     */
    public static function validateTransferFromShop($pdo, $transferId, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_transfer_validation WHERE TransferID = ? AND FromShopID = ?");
            $stmt->execute([$transferId, $shopId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("validateTransferFromShop Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 【架构重构Phase3】验证调拨属于指定店铺（目标店铺）
     * 改用 vw_transfer_validation 视图
     * 替换 fulfillment.php 中的调拨验证查询
     */
    public static function validateTransferToShop($pdo, $transferId, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_transfer_validation WHERE TransferID = ? AND ToShopID = ?");
            $stmt->execute([$transferId, $shopId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("validateTransferToShop Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // Warehouse库存调配
    // ----------------

    /**
     * 获取仓库库存列表
     * 替换 warehouse_dispatch.php 中的仓库库存查询
     */
    public static function getWarehouseStock($pdo, $warehouseId = null) {
        try {
            if ($warehouseId !== null) {
                $stmt = $pdo->prepare("SELECT * FROM vw_warehouse_stock WHERE ShopID = ?");
                $stmt->execute([$warehouseId]);
            } else {
                $stmt = $pdo->query("SELECT * FROM vw_warehouse_stock");
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getWarehouseStock Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取零售店铺列表
     * 替换 warehouse_dispatch.php 中的零售店铺查询
     */
    public static function getRetailShops($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_retail_shops")->fetchAll();
        } catch (PDOException $e) {
            error_log("getRetailShops Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 发起仓库库存调配（带确认流程）
     * 创建调拨记录，需要仓库员工确认发货后才能完成
     * 【修复】智能事务管理 - 仅在没有活动事务时才开启新事务
     */
    public static function initiateWarehouseDispatch($pdo, $warehouseId, $targetShopId, $releaseId, $conditionGrade, $quantity, $employeeId) {
        $ownTransaction = false;
        try {
            // 【修复】检查是否已在事务中，避免嵌套事务
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTransaction = true;
            }

            $stmt = $pdo->prepare("CALL sp_initiate_warehouse_dispatch(?, ?, ?, ?, ?, ?, @initiated_count)");
            $stmt->execute([$warehouseId, $targetShopId, $releaseId, $conditionGrade, $quantity, $employeeId]);
            $result = $pdo->query("SELECT @initiated_count AS initiated_count")->fetch();
            $count = (int)$result['initiated_count'];

            if ($count > 0) {
                if ($ownTransaction) {
                    $pdo->commit();
                }
                return $count;
            }
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            return 0;
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("initiateWarehouseDispatch Error: " . $e->getMessage());
            throw $e; // 重新抛出让外层处理
        }
    }

    // ----------------
    // Buyback相关
    // ----------------

    /**
     * 【架构重构Phase2】获取专辑列表（含基础成本）
     * 替换 buyback.php 中的专辑列表查询
     */
    public static function getReleaseListWithCost($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_release_list_with_cost")->fetchAll();
        } catch (PDOException $e) {
            error_log("getReleaseListWithCost Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase2】获取客户列表（含积分）
     * 替换 buyback.php 中的客户下拉框查询
     */
    public static function getCustomerListWithPoints($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_customer_list_with_points")->fetchAll();
        } catch (PDOException $e) {
            error_log("getCustomerListWithPoints Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase2】获取库存价格映射
     * 替换 buyback.php 中的价格映射查询
     * 【修复】添加 shopId 参数，确保只获取当前店铺的价格
     */
    public static function getStockPriceMap($pdo, $shopId) {
        try {
            $result = [];
            $stmt = $pdo->prepare("SELECT * FROM vw_stock_price_map WHERE ShopID = ?");
            $stmt->execute([$shopId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $key = $row['ReleaseID'] . '_' . $row['ConditionGrade'];
                $result[$key] = $row['CurrentPrice'];
            }
            return $result;
        } catch (PDOException $e) {
            error_log("getStockPriceMap Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase2】获取最近回购详情
     * 替换 buyback.php 中的最近回购详情查询
     */
    public static function getRecentBuybacksDetail($pdo, $shopId, $limit = 15) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_recent_buybacks_detail WHERE ShopID = ? LIMIT ?");
            $stmt->execute([$shopId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getRecentBuybacksDetail Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // Admin申请处理相关
    // ----------------

    /**
     * 【架构重构Phase2】获取调货申请详情
     * 替换 requests.php 中的申请信息查询
     */
    public static function getTransferRequestInfo($pdo, $requestId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_transfer_request_info WHERE RequestID = ?");
            $stmt->execute([$requestId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getTransferRequestInfo Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 【架构重构Phase3】检查店铺库存数量
     * 改用 vw_shop_stock_count 视图
     * 替换 requests.php 中的库存验证查询
     */
    public static function getShopStockCount($pdo, $shopId, $releaseId, $conditionGrade) {
        try {
            $stmt = $pdo->prepare("
                SELECT AvailableCount as available
                FROM vw_shop_stock_count
                WHERE ShopID = ? AND ReleaseID = ? AND ConditionGrade = ?
            ");
            $stmt->execute([$shopId, $releaseId, $conditionGrade]);
            $result = $stmt->fetch();
            return $result ? (int)$result['available'] : 0;
        } catch (PDOException $e) {
            error_log("getShopStockCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 【架构重构Phase3】获取店铺库存分组列表
     * 改用 vw_shop_inventory_by_release 视图（已包含Title和ArtistName）
     * 替换 manager/requests.php 中的库存查询
     */
    public static function getShopInventoryGrouped($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT ReleaseID, ShopName, ConditionGrade, AvailableQuantity AS Quantity, UnitPrice, Title, ArtistName
                FROM vw_shop_inventory_by_release
                WHERE ShopID = ?
                ORDER BY Title, ConditionGrade
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopInventoryGrouped Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase3】更新调货申请的源店铺
     * 改用 sp_update_transfer_request_source 存储过程
     * 替换 requests.php 中的ToShopID更新
     */
    public static function updateTransferRequestSource($pdo, $requestId, $sourceShopId) {
        try {
            $stmt = $pdo->prepare("CALL sp_update_transfer_request_source(?, ?)");
            return $stmt->execute([$requestId, $sourceShopId]);
        } catch (PDOException $e) {
            error_log("updateTransferRequestSource Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取其他店铺的同款库存
     * 替换 admin/requests.php 中的跨店库存查询
     */
    public static function getOtherShopsInventory($pdo, $releaseId, $conditionGrade, $excludeShopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_other_shops_inventory
                WHERE ReleaseID = ? AND ConditionGrade = ? AND ShopID != ?
                ORDER BY AvailableQuantity DESC
            ");
            $stmt->execute([$releaseId, $conditionGrade, $excludeShopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getOtherShopsInventory Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // Checkout相关
    // ----------------

    /**
     * 创建完整的在线订单
     * 替换 checkout.php 中的订单创建流程
     * 【修复】智能事务管理 - 仅在没有活动事务时才开启新事务
     */
    public static function createOnlineOrderComplete($pdo, $customerId, $shopId, $stockItemIds, $fulfillmentType, $shippingAddress, $shippingCost) {
        $ownTransaction = false;
        try {
            // 【修复】检查是否已在事务中，避免嵌套事务
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTransaction = true;
            }

            // 将数组转换为逗号分隔的字符串
            $stockIdsStr = implode(',', $stockItemIds);

            $stmt = $pdo->prepare("CALL sp_create_online_order_complete(?, ?, ?, ?, ?, ?, @order_id, @total_amount)");
            $stmt->execute([$customerId, $shopId, $stockIdsStr, $fulfillmentType, $shippingAddress, $shippingCost]);
            $result = $pdo->query("SELECT @order_id AS order_id, @total_amount AS total_amount")->fetch();

            if ($result['order_id'] > 0) {
                if ($ownTransaction) {
                    $pdo->commit();
                }
                return [
                    'order_id' => $result['order_id'],
                    'total_amount' => $result['total_amount']
                ];
            } elseif ($result['order_id'] == -2) {
                if ($ownTransaction) {
                    $pdo->rollBack();
                }
                return ['error' => 'no_available_items'];
            }
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            return false;
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("createOnlineOrderComplete Error: " . $e->getMessage());
            throw $e; // 重新抛出让外层处理
        }
    }

    // ----------------
    // POS订单相关
    // ----------------

    /**
     * 创建POS门店订单
     * 替换 pos.php 中的订单创建流程
     * 【修复】智能事务管理 - 仅在没有活动事务时才开启新事务
     */
    public static function createPosOrder($pdo, $customerId, $employeeId, $shopId, $stockItemIds) {
        $ownTransaction = false;
        try {
            // 【修复】检查是否已在事务中，避免嵌套事务
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownTransaction = true;
            }

            // 将数组转换为逗号分隔的字符串
            $stockIdsStr = implode(',', $stockItemIds);

            $stmt = $pdo->prepare("CALL sp_create_pos_order(?, ?, ?, ?, @order_id, @total_amount)");
            $stmt->execute([$customerId, $employeeId, $shopId, $stockIdsStr]);
            $result = $pdo->query("SELECT @order_id AS order_id, @total_amount AS total_amount")->fetch();

            if ($result['order_id'] > 0) {
                if ($ownTransaction) {
                    $pdo->commit();
                }
                return [
                    'order_id' => $result['order_id'],
                    'total_amount' => $result['total_amount']
                ];
            } elseif ($result['order_id'] == -2) {
                if ($ownTransaction) {
                    $pdo->rollBack();
                }
                return ['error' => 'no_available_items'];
            }
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            return false;
        } catch (PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("createPosOrder Error: " . $e->getMessage());
            throw $e; // 重新抛出让外层处理
        }
    }

    // ----------------
    // 通用订单操作
    // ----------------

    /**
     * 更新订单状态
     * 通用订单状态更新，替换各页面中的直接UPDATE
     */
    public static function updateOrderStatus($pdo, $orderId, $newStatus, $employeeId = null) {
        try {
            $stmt = $pdo->prepare("CALL sp_update_order_status(?, ?, ?)");
            return $stmt->execute([$orderId, $newStatus, $employeeId]);
        } catch (PDOException $e) {
            error_log("updateOrderStatus Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 店铺ID查询
    // ----------------

    /**
     * 按类型获取店铺ID
     * 替换 functions.php 中的 getShopIdByType
     */
    public static function getShopIdByType($pdo, $shopType) {
        try {
            $stmt = $pdo->prepare("CALL sp_get_shop_id_by_type(?, @shop_id)");
            $stmt->execute([$shopType]);
            $result = $pdo->query("SELECT @shop_id AS shop_id")->fetch();
            return $result['shop_id'];
        } catch (PDOException $e) {
            error_log("getShopIdByType Error: " . $e->getMessage());
            return null;
        }
    }

    // ----------------
    // Dashboard数据相关
    // ----------------

    /**
     * 获取店铺Walk-in顾客收入
     * 替换 functions.php:prepareDashboardData 中的walk-in收入查询
     */
    public static function getShopWalkInRevenue($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_shop_walk_in_revenue WHERE ShopID = ?");
            $stmt->execute([$shopId]);
            $result = $stmt->fetch();
            return $result ?: ['OrderCount' => 0, 'TotalSpent' => 0];
        } catch (PDOException $e) {
            error_log("getShopWalkInRevenue Error: " . $e->getMessage());
            return ['OrderCount' => 0, 'TotalSpent' => 0];
        }
    }

    /**
     * 获取店铺库存成本
     * 替换 functions.php:prepareDashboardData 中的库存成本计算
     */
    public static function getShopInventoryCost($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_shop_inventory_cost WHERE ShopID = ?");
            $stmt->execute([$shopId]);
            $result = $stmt->fetch();
            return $result ?: ['TotalInventoryCost' => 0, 'InventoryCount' => 0];
        } catch (PDOException $e) {
            error_log("getShopInventoryCost Error: " . $e->getMessage());
            return ['TotalInventoryCost' => 0, 'InventoryCount' => 0];
        }
    }

    /**
     * 获取店铺采购统计
     * 替换 functions.php:prepareDashboardData 中的采购统计查询
     */
    public static function getShopProcurementStats($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_shop_procurement_stats WHERE ShopID = ?");
            $stmt->execute([$shopId]);
            $result = $stmt->fetch();
            return $result ?: ['ProcurementCount' => 0];
        } catch (PDOException $e) {
            error_log("getShopProcurementStats Error: " . $e->getMessage());
            return ['ProcurementCount' => 0];
        }
    }

    // ----------------
    // Release详情相关
    // ----------------

    /**
     * 获取专辑店铺库存分组
     * 替换 functions.php:prepareReleaseDetailData 中的库存分组查询
     */
    public static function getReleaseShopStockGrouped($pdo, $releaseId, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM vw_release_shop_stock_grouped
                WHERE ReleaseID = ? AND ShopID = ?
                ORDER BY FIELD(ConditionGrade, 'New', 'Mint', 'NM', 'VG+', 'VG', 'G+', 'G', 'F', 'P'), UnitPrice
            ");
            $stmt->execute([$releaseId, $shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getReleaseShopStockGrouped Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // 购物车库存相关
    // ----------------

    /**
     * 获取可用库存ID列表（用于添加到购物车）
     * 替换 functions.php:addMultipleToCart 中的库存ID查询
     */
    public static function getAvailableStockIdsByCondition($pdo, $releaseId, $conditionGrade, $shopId = null) {
        try {
            if ($shopId !== null) {
                $stmt = $pdo->prepare("
                    SELECT StockItemID FROM vw_available_stock_ids
                    WHERE ReleaseID = ? AND ConditionGrade = ? AND ShopID = ?
                ");
                $stmt->execute([$releaseId, $conditionGrade, $shopId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT StockItemID FROM vw_available_stock_ids
                    WHERE ReleaseID = ? AND ConditionGrade = ?
                ");
                $stmt->execute([$releaseId, $conditionGrade]);
            }
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("getAvailableStockIdsByCondition Error: " . $e->getMessage());
            return [];
        }
    }

    // ================================================
    // 【架构重构Phase3】新增方法 - 消除 functions.php 直接表访问
    // ================================================

    /**
     * 【架构重构Phase3】按店铺获取目录数据
     * 【修复】配合修改后的视图，直接按ShopID查询，每个店铺显示所有15张专辑
     * 替换 functions.php:prepareCatalogPageDataByShop 中的直接表访问
     */
    public static function getCatalogByShop($pdo, $shopId, $search = '', $genre = '', $artist = '') {
        try {
            $sql = "
                SELECT ReleaseID, Title, Genre, Year, ArtistName, TotalAvailable, MinPrice, MaxPrice, AvailableConditions
                FROM vw_catalog_by_shop_grouped
                WHERE ShopID = ?
            ";
            $params = [$shopId];

            if ($search) {
                $sql .= " AND (Title LIKE ? OR ArtistName LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            if ($genre) {
                $sql .= " AND Genre = ?";
                $params[] = $genre;
            }

            if ($artist) {
                $sql .= " AND ArtistName = ?";
                $params[] = $artist;
            }

            $sql .= " ORDER BY TotalAvailable DESC, Title ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCatalogByShop Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase3】获取所有专辑流派列表
     * 替换 functions.php:prepareCatalogPageDataByShop 中的流派查询
     */
    public static function getReleaseGenres($pdo) {
        try {
            $stmt = $pdo->query("SELECT Genre FROM vw_release_genres");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("getReleaseGenres Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取所有专辑艺术家列表
     */
    public static function getReleaseArtists($pdo) {
        try {
            $stmt = $pdo->query("SELECT DISTINCT ArtistName FROM vw_release_basic ORDER BY ArtistName ASC");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("getReleaseArtists Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase3】获取专辑基本信息
     * 替换 functions.php:getReleaseDetailsByShop 中的 ReleaseAlbum 查询
     */
    public static function getReleaseInfo($pdo, $releaseId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_release_info WHERE ReleaseID = ?");
            $stmt->execute([$releaseId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getReleaseInfo Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 【新增】获取专辑曲目列表
     * 用于专辑详情页显示曲目信息
     */
    public static function getReleaseTracks($pdo, $releaseId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_release_tracks WHERE ReleaseID = ? ORDER BY TrackNumber");
            $stmt->execute([$releaseId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getReleaseTracks Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【架构重构Phase3】验证订单是否可取消
     * 替换 cancel_order.php 中的直接 CustomerOrder 查询
     */
    public static function validateOrderForCancel($pdo, $orderId, $customerId) {
        try {
            $stmt = $pdo->prepare("
                SELECT OrderID, CustomerID, OrderStatus, OrderType
                FROM vw_order_shop_validation
                WHERE OrderID = ? AND CustomerID = ?
            ");
            $stmt->execute([$orderId, $customerId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("validateOrderForCancel Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 【新增】库存成本明细相关
    // ----------------

    /**
     * 获取店铺已售商品成本明细
     * 通过视图 vw_stock_item_with_cost 获取成本信息
     */
    public static function getShopSoldInventoryCost($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    StockItemID,
                    Title,
                    ArtistName,
                    ConditionGrade,
                    UnitCost,
                    UnitCost AS TotalCost,
                    1 AS Quantity,
                    DateSold,
                    SourceType
                FROM vw_stock_item_with_cost
                WHERE ShopID = ? AND Status = 'Sold'
                ORDER BY DateSold DESC
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopSoldInventoryCost Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取店铺当前库存成本明细
     * 通过视图 vw_stock_item_with_cost 获取成本信息
     */
    public static function getShopCurrentInventoryCost($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    StockItemID,
                    Title,
                    ArtistName,
                    ConditionGrade,
                    UnitCost,
                    UnitCost AS TotalCost,
                    1 AS Quantity,
                    AcquiredDate,
                    SourceType
                FROM vw_stock_item_with_cost
                WHERE ShopID = ? AND Status = 'Available'
                ORDER BY Title, ConditionGrade
            ");
            $stmt->execute([$shopId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getShopCurrentInventoryCost Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // Notification Counts
    // ----------------

    /**
     * Get pending pickup orders count for a shop
     * Used for staff navigation badge
     */
    public static function getPendingPickupCount($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM vw_staff_bopis_pending WHERE ShopID = ?");
            $stmt->execute([$shopId]);
            $result = $stmt->fetch();
            return (int)($result['cnt'] ?? 0);
        } catch (PDOException $e) {
            error_log("getPendingPickupCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get pending fulfillment orders count (Pending + Paid status)
     * Used for staff navigation badge
     */
    public static function getPendingFulfillmentCount($pdo, $shopId) {
        try {
            $counts = self::getFulfillmentOrderStatusCounts($pdo, $shopId);
            return ($counts['Pending'] ?? 0) + ($counts['Paid'] ?? 0);
        } catch (PDOException $e) {
            error_log("getPendingFulfillmentCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get pending requests count (for Admin)
     * Used for Admin navigation badge
     */
    public static function getAdminPendingRequestsCount($pdo) {
        try {
            $result = $pdo->query("SELECT COUNT(*) AS cnt FROM vw_admin_pending_requests")->fetch();
            return (int)($result['cnt'] ?? 0);
        } catch (PDOException $e) {
            error_log("getAdminPendingRequestsCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get pending transfer out count for a shop
     * Used for staff fulfillment navigation badge
     */
    public static function getPendingTransferOutCount($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM vw_fulfillment_pending_transfers_grouped WHERE FromShopID = ?");
            $stmt->execute([$shopId]);
            $result = $stmt->fetch();
            return (int)($result['cnt'] ?? 0);
        } catch (PDOException $e) {
            error_log("getPendingTransferOutCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count of responded requests (Approved/Rejected) for manager
     * Used for manager navigation badge
     */
    public static function getManagerRespondedRequestsCount($pdo, $employeeId) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS cnt FROM vw_manager_requests_sent
                WHERE RequestedByEmployeeID = ? AND Status IN ('Approved', 'Rejected')
            ");
            $stmt->execute([$employeeId]);
            $result = $stmt->fetch();
            return (int)($result['cnt'] ?? 0);
        } catch (PDOException $e) {
            error_log("getManagerRespondedRequestsCount Error: " . $e->getMessage());
            return 0;
        }
    }

    // ----------------
    // Pagination Support with Search and Filter
    // ----------------

    /**
     * Build WHERE clause and params for inventory filters
     */
    private static function buildInventoryFilterConditions($filters, $hasShopId = true) {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = "(Title LIKE ? OR ArtistName LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['batch'])) {
            $conditions[] = "BatchNo = ?";
            $params[] = $filters['batch'];
        }

        return ['conditions' => $conditions, 'params' => $params];
    }

    /**
     * Build ORDER BY clause for inventory sorting
     */
    private static function buildInventoryOrderBy($sort, $viewMode) {
        switch ($sort) {
            case 'price_asc':
                return $viewMode === 'summary' ? 'ORDER BY MinPrice ASC' : 'ORDER BY UnitPrice ASC';
            case 'price_desc':
                return $viewMode === 'summary' ? 'ORDER BY MaxPrice DESC' : 'ORDER BY UnitPrice DESC';
            case 'days_asc':
                return 'ORDER BY DaysInStock ASC';
            case 'days_desc':
                return 'ORDER BY DaysInStock DESC';
            default:
                return 'ORDER BY Title, ConditionGrade';
        }
    }

    /**
     * Get inventory count for a shop (for pagination) with filters
     */
    public static function getShopInventoryCount($pdo, $shopId, $viewMode = 'summary', $filters = []) {
        try {
            $filter = self::buildInventoryFilterConditions($filters);
            $params = [$shopId];
            $whereExtra = '';

            if (!empty($filter['conditions'])) {
                $whereExtra = ' AND ' . implode(' AND ', $filter['conditions']);
                $params = array_merge($params, $filter['params']);
            }

            if ($viewMode === 'summary') {
                $sql = "SELECT COUNT(DISTINCT CONCAT(ReleaseID, '-', ConditionGrade)) AS cnt FROM vw_stock_summary WHERE ShopID = ?" . $whereExtra;
            } else {
                $sql = "SELECT COUNT(*) AS cnt FROM vw_stock_detail WHERE ShopID = ?" . $whereExtra;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return (int)($result['cnt'] ?? 0);
        } catch (PDOException $e) {
            error_log("getShopInventoryCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get paginated inventory summary for a shop with filters
     */
    public static function getInventorySummaryPaginated($pdo, $shopId, $limit = 20, $offset = 0, $filters = []) {
        try {
            $filter = self::buildInventoryFilterConditions($filters);
            $orderBy = self::buildInventoryOrderBy($filters['sort'] ?? '', 'summary');
            $params = [$shopId];
            $whereExtra = '';

            if (!empty($filter['conditions'])) {
                $whereExtra = ' AND ' . implode(' AND ', $filter['conditions']);
                $params = array_merge($params, $filter['params']);
            }

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare("
                SELECT * FROM vw_stock_summary
                WHERE ShopID = ? $whereExtra
                $orderBy
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getInventorySummaryPaginated Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get paginated inventory detail for a shop with filters
     */
    public static function getInventoryDetailPaginated($pdo, $shopId, $limit = 20, $offset = 0, $filters = []) {
        try {
            $filter = self::buildInventoryFilterConditions($filters);
            $orderBy = self::buildInventoryOrderBy($filters['sort'] ?? '', 'detail');
            $params = [$shopId];
            $whereExtra = '';

            if (!empty($filter['conditions'])) {
                $whereExtra = ' AND ' . implode(' AND ', $filter['conditions']);
                $params = array_merge($params, $filter['params']);
            }

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare("
                SELECT * FROM vw_stock_detail
                WHERE ShopID = ? $whereExtra
                $orderBy
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getInventoryDetailPaginated Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get inventory count for all shops (for admin pagination) with filters
     */
    public static function getAllShopsInventoryCount($pdo, $viewMode = 'summary', $filters = []) {
        try {
            $filter = self::buildInventoryFilterConditions($filters, false);
            $where = '';

            if (!empty($filter['conditions'])) {
                $where = 'WHERE ' . implode(' AND ', $filter['conditions']);
            }

            if ($viewMode === 'summary') {
                $sql = "SELECT COUNT(DISTINCT CONCAT(ShopID, '-', ReleaseID, '-', ConditionGrade)) AS cnt FROM vw_stock_summary $where";
            } else {
                $sql = "SELECT COUNT(*) AS cnt FROM vw_stock_detail $where";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($filter['params']);
            $result = $stmt->fetch();
            return (int)($result['cnt'] ?? 0);
        } catch (PDOException $e) {
            error_log("getAllShopsInventoryCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get paginated inventory summary for all shops (admin) with filters
     */
    public static function getInventorySummaryAllShopsPaginated($pdo, $limit = 20, $offset = 0, $filters = []) {
        try {
            $filter = self::buildInventoryFilterConditions($filters, false);
            $orderBy = self::buildInventoryOrderBy($filters['sort'] ?? '', 'summary');
            $where = '';

            if (!empty($filter['conditions'])) {
                $where = 'WHERE ' . implode(' AND ', $filter['conditions']);
            }

            $params = $filter['params'];
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare("
                SELECT * FROM vw_all_shops_inventory_summary
                $where
                $orderBy
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getInventorySummaryAllShopsPaginated Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get paginated inventory detail for all shops (admin) with filters
     */
    public static function getInventoryDetailAllShopsPaginated($pdo, $limit = 20, $offset = 0, $filters = []) {
        try {
            $filter = self::buildInventoryFilterConditions($filters, false);
            $orderBy = self::buildInventoryOrderBy($filters['sort'] ?? '', 'detail');
            $where = '';

            if (!empty($filter['conditions'])) {
                $where = 'WHERE ' . implode(' AND ', $filter['conditions']);
            }

            $params = $filter['params'];
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare("
                SELECT * FROM vw_all_shops_inventory_detail
                $where
                $orderBy
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getInventoryDetailAllShopsPaginated Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get distinct batch numbers for a shop's inventory
     */
    public static function getInventoryBatches($pdo, $shopId) {
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT BatchNo FROM vw_stock_detail
                WHERE ShopID = ? AND BatchNo IS NOT NULL AND BatchNo != ''
                ORDER BY BatchNo DESC
            ");
            $stmt->execute([$shopId]);
            return array_column($stmt->fetchAll(), 'BatchNo');
        } catch (PDOException $e) {
            error_log("getInventoryBatches Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get distinct batch numbers for all shops' inventory
     */
    public static function getInventoryBatchesAllShops($pdo) {
        try {
            $stmt = $pdo->query("
                SELECT DISTINCT BatchNo FROM vw_stock_detail
                WHERE BatchNo IS NOT NULL AND BatchNo != ''
                ORDER BY BatchNo DESC
            ");
            return array_column($stmt->fetchAll(), 'BatchNo');
        } catch (PDOException $e) {
            error_log("getInventoryBatchesAllShops Error: " . $e->getMessage());
            return [];
        }
    }

    // ----------------
    // Customer Notification Helpers
    // ----------------

    /**
     * Get count of shipped delivery orders awaiting confirmation for a customer
     * Uses vw_customer_shipped_delivery view
     */
    public static function getCustomerShippedDeliveryCount($pdo, $customerId) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt FROM vw_customer_shipped_delivery
                WHERE CustomerID = ?
            ");
            $stmt->execute([$customerId]);
            $result = $stmt->fetch();
            return (int)($result['cnt'] ?? 0);
        } catch (PDOException $e) {
            error_log("getCustomerShippedDeliveryCount Error: " . $e->getMessage());
            return 0;
        }
    }
}
?>
