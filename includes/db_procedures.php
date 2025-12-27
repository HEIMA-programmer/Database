<?php
/**
 * Database Procedures & Views Access Layer
 * 数据库访问层 - 封装所有视图查询和存储过程调用
 *
 * 【架构重构】这是 PHP 与数据库之间的唯一桥梁
 * 所有"读"操作通过视图，所有"写"操作通过存储过程
 *
 * 使用示例:
 * require_once __DIR__ . '/db_procedures.php';
 * $items = DBProcedures::getCartItems($pdo, [1, 2, 3]);
 * $result = DBProcedures::completeOrder($pdo, $orderId, $pointsEarned);
 */

class DBProcedures {

    // =============================================
    // 【视图查询方法】读操作
    // =============================================

    // ----------------
    // 购物车相关
    // ----------------

    /**
     * 获取购物车商品详情（从目录视图）
     */
    public static function getCartItems($pdo, $stockIds) {
        if (empty($stockIds)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($stockIds), '?'));
            $sql = "SELECT * FROM vw_customer_catalog WHERE StockItemID IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($stockIds);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCartItems Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 检查库存状态
     */
    public static function getStockItemStatus($pdo, $stockId) {
        try {
            $stmt = $pdo->prepare("SELECT Status FROM vw_stock_item_status WHERE StockItemID = ?");
            $stmt->execute([$stockId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("getStockItemStatus Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 目录和商品相关
    // ----------------

    /**
     * 获取商品目录（支持搜索和筛选）- 原始方法保留兼容性
     */
    public static function getCatalogItems($pdo, $search = '', $genre = '') {
        try {
            $sql = "SELECT * FROM vw_customer_catalog WHERE 1=1";
            $params = [];

            if (!empty($search)) {
                $sql .= " AND (Title LIKE :search OR ArtistName LIKE :search)";
                $params[':search'] = "%$search%";
            }
            if (!empty($genre)) {
                $sql .= " AND Genre = :genre";
                $params[':genre'] = $genre;
            }

            $sql .= " ORDER BY Title ASC, ConditionGrade ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCatalogItems Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【新增】获取分组后的商品目录（按专辑分组）
     */
    public static function getCatalogItemsGrouped($pdo, $search = '', $genre = '') {
        try {
            $sql = "SELECT * FROM vw_customer_catalog_grouped WHERE 1=1";
            $params = [];

            if (!empty($search)) {
                $sql .= " AND (Title LIKE :search OR ArtistName LIKE :search)";
                $params[':search'] = "%$search%";
            }
            if (!empty($genre)) {
                $sql .= " AND Genre = :genre";
                $params[':genre'] = $genre;
            }

            $sql .= " ORDER BY Title ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getCatalogItemsGrouped Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 【新增】获取分组后目录中的流派列表
     */
    public static function getCatalogGenresGrouped($pdo) {
        try {
            return $pdo->query("SELECT DISTINCT Genre FROM vw_customer_catalog_grouped ORDER BY Genre")->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("getCatalogGenresGrouped Error: " . $e->getMessage());
            return [];
        }
    }

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
     * 【新增】获取专辑基本信息
     */
    public static function getReleaseInfo($pdo, $releaseId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_customer_catalog_grouped WHERE ReleaseID = ?");
            $stmt->execute([$releaseId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getReleaseInfo Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 【修复】获取指定条件的可用库存ID列表
     * @param int|null $shopId 店铺ID，如果为null则查询所有店铺
     */
    public static function getAvailableStockIds($pdo, $releaseId, $conditionGrade, $quantity, $shopId = null) {
        try {
            $sql = "
                SELECT s.StockItemID
                FROM StockItem s
                WHERE s.ReleaseID = ?
                  AND s.ConditionGrade = ?
                  AND s.Status = 'Available'
            ";
            $params = [$releaseId, $conditionGrade];

            // 如果指定了店铺ID，则过滤店铺
            if ($shopId !== null) {
                $sql .= " AND s.ShopID = ?";
                $params[] = $shopId;
            }

            $sql .= " ORDER BY s.StockItemID LIMIT ?";
            $params[] = $quantity;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("getAvailableStockIds Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取目录中的流派列表
     */
    public static function getCatalogGenres($pdo) {
        try {
            return $pdo->query("SELECT DISTINCT Genre FROM vw_customer_catalog ORDER BY Genre")->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("getCatalogGenres Error: " . $e->getMessage());
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
     * POS 商品搜索（精确ID或模糊标题）
     */
    public static function searchPOSItems($pdo, $shopId, $searchTerm) {
        if (empty($searchTerm)) return [];
        try {
            $items = [];

            if (is_numeric($searchTerm)) {
                $stmt = $pdo->prepare("SELECT * FROM vw_staff_pos_lookup WHERE ShopID = :shop AND Status = 'Available' AND StockItemID = :id LIMIT 20");
                $stmt->execute([':shop' => $shopId, ':id' => (int)$searchTerm]);
                $items = $stmt->fetchAll();
            }

            if (empty($items)) {
                $stmt = $pdo->prepare("SELECT * FROM vw_staff_pos_lookup WHERE ShopID = :shop AND Status = 'Available' AND Title LIKE :q LIMIT 20");
                $stmt->execute([':shop' => $shopId, ':q' => "%$searchTerm%"]);
                $items = $stmt->fetchAll();
            }

            return $items;
        } catch (PDOException $e) {
            error_log("searchPOSItems Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取 POS 购物车商品价格
     */
    public static function getPOSCartPrices($pdo, $stockIds) {
        if (empty($stockIds)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($stockIds), '?'));
            $stmt = $pdo->prepare("SELECT UnitPrice FROM vw_staff_pos_lookup WHERE StockItemID IN ($placeholders)");
            $stmt->execute($stockIds);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("getPOSCartPrices Error: " . $e->getMessage());
            return [];
        }
    }

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
     * 获取取货订单信息
     */
    public static function getOrderForPickup($pdo, $orderId, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_order_for_pickup WHERE OrderID = ? AND ShopID = ?");
            $stmt->execute([$orderId, $shopId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getOrderForPickup Error: " . $e->getMessage());
            return false;
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
     * 检查库存物品信息（用于调拨验证）
     */
    public static function getStockItemForTransfer($pdo, $stockId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_stock_item_status WHERE StockItemID = ? AND Status = 'Available'");
            $stmt->execute([$stockId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("getStockItemForTransfer Error: " . $e->getMessage());
            return false;
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

    /**
     * 获取待处理调拨列表
     */
    public static function getPendingTransfers($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_manager_pending_transfers ORDER BY TransferDate DESC")->fetchAll();
        } catch (PDOException $e) {
            error_log("getPendingTransfers Error: " . $e->getMessage());
            return [];
        }
    }

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

    /**
     * 获取客户简单列表
     */
    public static function getCustomerSimpleList($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_customer_simple_list")->fetchAll();
        } catch (PDOException $e) {
            error_log("getCustomerSimpleList Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取回购订单列表
     */
    public static function getBuybackOrders($pdo, $shopId, $limit = 10) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vw_buyback_orders WHERE ShopName = (SELECT Name FROM Shop WHERE ShopID = ?) ORDER BY BuybackDate DESC LIMIT ?");
            $stmt->execute([$shopId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("getBuybackOrders Error: " . $e->getMessage());
            return [];
        }
    }

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
     */
    public static function addSupplierOrderLine($pdo, $orderId, $releaseId, $quantity, $unitCost) {
        try {
            $stmt = $pdo->prepare("CALL sp_add_supplier_order_line(?, ?, ?, ?)");
            return $stmt->execute([$orderId, $releaseId, $quantity, $unitCost]);
        } catch (PDOException $e) {
            error_log("addSupplierOrderLine Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 接收供应商订单并生成库存
     */
    public static function receiveSupplierOrder($pdo, $orderId, $batchNo, $conditionGrade = 'New', $markupRate = 0.50) {
        try {
            $stmt = $pdo->prepare("CALL sp_receive_supplier_order(?, ?, ?, ?)");
            return $stmt->execute([$orderId, $batchNo, $conditionGrade, $markupRate]);
        } catch (PDOException $e) {
            error_log("receiveSupplierOrder Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 回购流程
    // ----------------

    /**
     * 处理客户回购
     */
    public static function processBuyback($pdo, $customerId, $employeeId, $shopId, $releaseId, $quantity, $unitPrice, $conditionGrade, $resalePrice) {
        try {
            $stmt = $pdo->prepare("CALL sp_process_buyback(?, ?, ?, ?, ?, ?, ?, ?, @buyback_id)");
            $stmt->execute([$customerId, $employeeId, $shopId, $releaseId, $quantity, $unitPrice, $conditionGrade, $resalePrice]);
            $result = $pdo->query("SELECT @buyback_id AS buyback_id")->fetch();
            return $result['buyback_id'] > 0 ? $result['buyback_id'] : false;
        } catch (PDOException $e) {
            error_log("processBuyback Error: " . $e->getMessage());
            return false;
        }
    }

    // ----------------
    // 库存调拨流程
    // ----------------

    /**
     * 发起库存调拨
     */
    public static function initiateTransfer($pdo, $stockItemId, $fromShopId, $toShopId, $employeeId) {
        try {
            $stmt = $pdo->prepare("CALL sp_initiate_transfer(?, ?, ?, ?, @transfer_id)");
            $stmt->execute([$stockItemId, $fromShopId, $toShopId, $employeeId]);
            $result = $pdo->query("SELECT @transfer_id AS transfer_id")->fetch();
            return $result['transfer_id'] > 0 ? $result['transfer_id'] : false;
        } catch (PDOException $e) {
            error_log("initiateTransfer Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 完成库存调拨
     */
    public static function completeTransfer($pdo, $transferId, $receivedByEmployeeId) {
        try {
            $stmt = $pdo->prepare("CALL sp_complete_transfer(?, ?)");
            return $stmt->execute([$transferId, $receivedByEmployeeId]);
        } catch (PDOException $e) {
            error_log("completeTransfer Error: " . $e->getMessage());
            return false;
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
     */
    public static function completeOrder($pdo, $orderId) {
        try {
            $stmt = $pdo->prepare("CALL sp_complete_order(?)");
            return $stmt->execute([$orderId]);
        } catch (PDOException $e) {
            error_log("completeOrder Error: " . $e->getMessage());
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
     */
    public static function getSupplierList($pdo) {
        try {
            return $pdo->query("SELECT * FROM Supplier ORDER BY Name ASC")->fetchAll();
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
            return $result['supplier_id'] > 0 ? $result['supplier_id'] : false;
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
            return $result['release_id'] > 0 ? $result['release_id'] : false;
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

    // ----------------
    // 订单履约相关 (Staff)
    // ----------------

    /**
     * 获取待发货在线订单
     */
    public static function getOnlineOrdersAwaitingShipment($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_staff_online_orders_pending WHERE OrderStatus = 'Paid'")->fetchAll();
        } catch (PDOException $e) {
            error_log("getOnlineOrdersAwaitingShipment Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取已发货在线订单
     */
    public static function getOnlineOrdersShipped($pdo) {
        try {
            return $pdo->query("SELECT * FROM vw_staff_online_orders_pending WHERE OrderStatus = 'Shipped'")->fetchAll();
        } catch (PDOException $e) {
            error_log("getOnlineOrdersShipped Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 发货订单
     */
    public static function shipOrder($pdo, $orderId) {
        try {
            $stmt = $pdo->prepare("CALL sp_ship_order(?, @result)");
            $stmt->execute([$orderId]);
            $result = $pdo->query("SELECT @result AS result")->fetch();
            return (int)$result['result'] === 1;
        } catch (PDOException $e) {
            error_log("shipOrder Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取订单用于取货验证
     */
    public static function getOrderForPickupValidation($pdo, $orderId, $shopId) {
        try {
            $stmt = $pdo->prepare("SELECT OrderID, TotalAmount, OrderStatus FROM CustomerOrder WHERE OrderID = ? AND FulfilledByShopID = ? AND OrderStatus = 'Paid'");
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
     */
    public static function getCustomerOrderDetail($pdo, $orderId, $customerId) {
        try {
            $stmt = $pdo->prepare("SELECT co.*, s.Name as ShopName FROM CustomerOrder co LEFT JOIN Shop s ON co.FulfilledByShopID = s.ShopID WHERE co.OrderID = ? AND co.CustomerID = ?");
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

    /**
     * 检查库存数量
     */
    public static function getAvailableStock($pdo, $releaseId, $shopId, $conditionGrade) {
        try {
            $stmt = $pdo->prepare("SELECT fn_get_available_stock(?, ?, ?) AS qty");
            $stmt->execute([$releaseId, $shopId, $conditionGrade]);
            $result = $stmt->fetch();
            return $result['qty'] ?? 0;
        } catch (PDOException $e) {
            error_log("getAvailableStock Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 更新客户会员等级
     */
    public static function updateCustomerTier($pdo, $customerId) {
        try {
            $stmt = $pdo->prepare("CALL sp_update_customer_tier(?)");
            return $stmt->execute([$customerId]);
        } catch (PDOException $e) {
            error_log("updateCustomerTier Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
