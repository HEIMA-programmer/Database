<?php
// includes/functions.php

/**
 * 安全过滤输出，防止XSS攻击
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 格式化价格
 */
function formatPrice($amount) {
    return '¥' . number_format((float)$amount, 2);
}

/**
 * 格式化日期
 */
function formatDate($dateString) {
    return date('Y-m-d H:i', strtotime($dateString));
}

/**
 * 设置或获取 Flash 消息 (一次性提示)
 */
function flash($message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash'] = [
            'message' => $message,
            'type'    => $type // success, danger, warning, info
        ];
    } else {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
    }
    return null;
}

/**
 * 显示 Flash 消息的 HTML
 */
function displayFlash() {
    $flash = flash();
    if ($flash) {
        echo "<div class='alert alert-{$flash['type']} alert-dismissible fade show' role='alert'>
                {$flash['message']}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}

/**
 * 检查当前用户是否有特定权限
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * 【架构重构Phase3】按店铺获取目录数据
 * 改用 DBProcedures::getCatalogByShop 和 DBProcedures::getReleaseGenres
 * 已更新以匹配 ReleaseAlbum 架构
 */
function prepareCatalogPageDataByShop($pdo, $shopId, $search = '', $genre = '') {
    require_once __DIR__ . '/db_procedures.php';

    // 【架构重构Phase3】使用 DBProcedures 替换直接SQL查询
    $items = DBProcedures::getCatalogByShop($pdo, $shopId, $search, $genre);
    $genres = DBProcedures::getReleaseGenres($pdo);

    return [
        'items' => $items,
        'genres' => $genres
    ];
}

/**
 * 获取购物车商品数量（用于导航栏显示）
 */
function getCartCount() {
    if (isset($_SESSION['cart'])) {
        return count($_SESSION['cart']);
    }
    return 0;
}

/**
 * [架构重构] 动态获取特定类型的店铺ID
 * 【架构重构Phase2】改用DBProcedures替换直接表访问
 */
function getShopIdByType($pdo, $type) {
    static $cache = [];

    if (isset($cache[$type])) {
        return $cache[$type];
    }

    require_once __DIR__ . '/db_procedures.php';

    try {
        $id = DBProcedures::getShopIdByType($pdo, $type);

        if ($id) {
            $cache[$type] = $id;
            return $id;
        }

        error_log("Critical Warning: No shop found for type '$type'. Operations may fail.");
        return false; // 返回 false 让调用者处理错误
    } catch (Exception $e) {
        error_log("DB Error in getShopIdByType: " . $e->getMessage());
        return false;
    }
}

/**
 * 【新增】获取客户当前的会员等级信息
 * 在调用 completeOrder 之前调用此函数保存原始等级
 */
function getCustomerTierInfo($pdo, $customerId) {
    if (!$customerId) return null;

    try {
        $stmt = $pdo->prepare("SELECT TierID, TierName, Points FROM vw_customer_profile_info WHERE CustomerID = ?");
        $stmt->execute([$customerId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error in getCustomerTierInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * 【修复】检查会员升级状态
 * 在 completeOrder 之后调用，传入升级前的 TierID 来对比
 *
 * @param PDO $pdo
 * @param int $customerId
 * @param float $amountSpent 消费金额（用于计算积分）
 * @param int|null $oldTierId 升级前的等级ID（从 getCustomerTierInfo 获取）
 * @return array|false
 */
function checkMembershipUpgrade($pdo, $customerId, $amountSpent, $oldTierId = null) {
    if (!$customerId) return false;

    $pointsEarned = floor($amountSpent);
    if ($pointsEarned <= 0) return false;

    try {
        // 查询当前会员状态（触发器已自动更新积分和等级）
        $stmt = $pdo->prepare("SELECT TierID, TierName FROM vw_customer_profile_info WHERE CustomerID = ?");
        $stmt->execute([$customerId]);
        $currentStatus = $stmt->fetch();

        if (!$currentStatus) {
            return false;
        }

        // 对比升级前后的等级
        $upgraded = ($oldTierId !== null && $currentStatus['TierID'] > $oldTierId);
        $newTierName = $currentStatus['TierName'] ?? 'Unknown';

        return [
            'points_earned' => $pointsEarned,
            'upgraded'      => $upgraded,
            'new_tier_name' => $newTierName
        ];
    } catch (Exception $e) {
        error_log("Error in checkMembershipUpgrade: " . $e->getMessage());
        return false;
    }
}


// =============================================
// 【架构重构】业务逻辑层 - 购物车与订单计算
// =============================================

/**
 * 计算 POS 购物车总额
 *
 * @param array $prices 价格数组
 * @return float 总额
 */
function calculatePOSTotal($prices) {
    return array_sum(array_map('floatval', $prices));
}

/**
 * 添加商品到购物车
 * 包含库存验证和重复检查
 *
 * @param PDO $pdo
 * @param int $stockId
 * @return array ['success' => bool, 'message' => string]
 */
function addToCart($pdo, $stockId) {
    require_once __DIR__ . '/db_procedures.php';

    // 初始化购物车
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // 检查是否已在购物车
    if (in_array($stockId, $_SESSION['cart'])) {
        return ['success' => false, 'message' => 'Item already in cart.'];
    }

    // 验证库存状态
    $status = DBProcedures::getStockItemStatus($pdo, $stockId);

    if ($status === 'Available') {
        $_SESSION['cart'][] = $stockId;
        return ['success' => true, 'message' => 'Item added to cart.'];
    } else {
        return ['success' => false, 'message' => 'Item is no longer available.'];
    }
}

/**
 * 从购物车移除商品
 *
 * @param int $stockId
 * @return bool 是否成功移除
 */
function removeFromCart($stockId) {
    if (!isset($_SESSION['cart'])) return false;

    $key = array_search($stockId, $_SESSION['cart']);
    if ($key !== false) {
        unset($_SESSION['cart'][$key]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        return true;
    }
    return false;
}

/**
 * 清空购物车
 */
function clearCart() {
    $_SESSION['cart'] = [];
}

// =============================================
// 【架构重构】业务逻辑层 - POS 系统
// =============================================

/**
 * 获取 POS 购物车数据
 *
 * @return array
 */
function getPOSCart() {
    return $_SESSION['pos_cart'] ?? [];
}

/**
 * 添加商品到 POS 购物车
 *
 * @param int $stockId
 * @return bool
 */
function addToPOSCart($stockId) {
    if (!isset($_SESSION['pos_cart'])) {
        $_SESSION['pos_cart'] = [];
    }

    if (!in_array($stockId, $_SESSION['pos_cart'])) {
        $_SESSION['pos_cart'][] = $stockId;
        return true;
    }
    return false;
}

/**
 * 从 POS 购物车移除商品
 *
 * @param int $stockId
 * @return bool
 */
function removeFromPOSCart($stockId) {
    if (!isset($_SESSION['pos_cart'])) return false;

    $key = array_search($stockId, $_SESSION['pos_cart']);
    if ($key !== false) {
        unset($_SESSION['pos_cart'][$key]);
        $_SESSION['pos_cart'] = array_values($_SESSION['pos_cart']);
        return true;
    }
    return false;
}

/**
 * 清空 POS 购物车
 */
function clearPOSCart() {
    $_SESSION['pos_cart'] = [];
}

// =============================================
// 【架构重构】业务逻辑层 - 认证与授权
// =============================================

/**
 * 执行员工登录
 *
 * @param PDO $pdo
 * @param string $username
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function authenticateEmployee($pdo, $username, $password) {
    require_once __DIR__ . '/db_procedures.php';

    $employee = DBProcedures::getEmployeeForAuth($pdo, $username);

    if ($employee && password_verify($password, $employee['PasswordHash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $employee['EmployeeID'];
        $_SESSION['username']  = $employee['Name'];
        $_SESSION['role']      = $employee['Role'];
        $_SESSION['shop_id']   = $employee['ShopID'];
        $_SESSION['shop_name'] = $employee['ShopName'];

        // 【修复】设置 user 数组，包含 ShopID 和 ShopType 用于菜单过滤和数据筛选
        $_SESSION['user'] = [
            'EmployeeID' => $employee['EmployeeID'],
            'ShopID'     => $employee['ShopID'],
            'ShopName'   => $employee['ShopName'],
            'ShopType'   => $employee['ShopType'] ?? 'Retail',
            'Role'       => $employee['Role']
        ];

        return ['success' => true, 'role' => $employee['Role']];
    }

    return ['success' => false, 'message' => 'Invalid username or password.'];
}

/**
 * 执行客户登录
 *
 * @param PDO $pdo
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function authenticateCustomer($pdo, $email, $password) {
    require_once __DIR__ . '/db_procedures.php';

    $customer = DBProcedures::getCustomerForAuth($pdo, $email);

    if ($customer && password_verify($password, $customer['PasswordHash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $customer['CustomerID'];
        $_SESSION['username']  = $customer['Name'];
        $_SESSION['role']      = 'Customer';
        $_SESSION['tier_id']   = $customer['TierID'];

        if ($customer['Birthday']) {
            $_SESSION['birth_month'] = (int)date('m', strtotime($customer['Birthday']));
        }

        return ['success' => true];
    }

    return ['success' => false, 'message' => 'Invalid email or password.'];
}

/**
 * 执行客户注册
 *
 * @param PDO $pdo
 * @param string $name
 * @param string $email
 * @param string $password
 * @param string|null $birthday
 * @return array ['success' => bool, 'message' => string, 'customer_id' => int|null]
 */
function registerNewCustomer($pdo, $name, $email, $password, $birthday = null) {
    require_once __DIR__ . '/db_procedures.php';

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $result = DBProcedures::registerCustomer($pdo, $name, $email, $hash, $birthday);

    if ($result === false) {
        return ['success' => false, 'message' => 'System error during registration.'];
    }

    if ($result['customer_id'] == -2) {
        return ['success' => false, 'message' => 'This email is already registered.'];
    }

    if ($result['customer_id'] > 0) {
        // 自动登录
        session_regenerate_id(true);
        $_SESSION['user_id']   = $result['customer_id'];
        $_SESSION['username']  = $name;
        $_SESSION['role']      = 'Customer';
        $_SESSION['tier_id']   = $result['tier_id'];

        if ($birthday) {
            $_SESSION['birth_month'] = (int)date('m', strtotime($birthday));
        }

        return ['success' => true, 'customer_id' => $result['customer_id']];
    }

    return ['success' => false, 'message' => 'Registration failed.'];
}

/**
 * 获取当前用户的重定向目标
 *
 * @param string $role
 * @return string URL
 */
function getLoginRedirectUrl($role) {
    return match($role) {
        'Admin'    => BASE_URL . '/admin/products.php',
        'Manager'  => BASE_URL . '/manager/dashboard.php',
        'Staff'    => BASE_URL . '/staff/pos.php',
        'Customer' => BASE_URL . '/customer/catalog.php',
        default    => BASE_URL . '/index.php'
    };
}

// =============================================
// 【架构重构】数据准备函数 - 页面顶部调用
// =============================================

/**
 * 准备仪表板页面数据
 *
 * @param PDO $pdo
 * @return array
 */
function prepareDashboardData($pdo, $shopId = null) {
    require_once __DIR__ . '/db_procedures.php';

    // 如果提供了shopId，则获取店铺级别的数据
    if ($shopId !== null) {
        $kpi = DBProcedures::getShopKpiStats($pdo, $shopId);
        $topCustomers = DBProcedures::getShopTopCustomers($pdo, $shopId, 5);
        $deadStock = DBProcedures::getShopDeadStock($pdo, $shopId, 10);
        $lowStock = DBProcedures::getShopLowStock($pdo, $shopId, 10);
        $revenueByType = DBProcedures::getShopRevenueByType($pdo, $shopId);
        $expense = DBProcedures::getShopTotalExpense($pdo, $shopId);
        $popularItems = DBProcedures::getPopularItems($pdo, $shopId, 1);

        // 【架构重构Phase2】使用DBProcedures替换直接SQL查询

        // 获取Walk-in customer收入统计
        $walkInRevenue = DBProcedures::getShopWalkInRevenue($pdo, $shopId);

        // 获取库存成本
        $inventoryCost = DBProcedures::getShopInventoryCost($pdo, $shopId);

        // 获取采购统计
        $procurementStats = DBProcedures::getShopProcurementStats($pdo, $shopId);

        // 【重构】Total Expense = 当前库存的实时成本
        // 这包括了从供应商采购的成本和回购的成本，且会随着调货自动更新
        $totalInventoryCost = $inventoryCost['TotalInventoryCost'] ?? 0;

        // 【保留】历史Buyback支出（用于统计显示）
        $buybackExpense = $expense['TotalExpense'] ?? 0;

        return [
            'total_sales'      => $kpi['TotalSales'] ?? 0,
            'active_orders'    => $kpi['ActiveOrders'] ?? 0,
            'total_expense'    => $totalInventoryCost, // 【重构】使用实时库存成本
            'buyback_expense'  => $buybackExpense,
            'buyback_count'    => $expense['BuybackCount'] ?? 0,
            'procurement_cost' => $totalInventoryCost, // 【重构】显示当前库存成本
            'procurement_count'=> $procurementStats['ProcurementCount'] ?? 0,
            'inventory_count'  => $inventoryCost['InventoryCount'] ?? 0, // 【新增】当前库存数量
            'popular_item'     => !empty($popularItems) ? $popularItems[0] : null,
            'top_spender_name' => !empty($topCustomers) ? $topCustomers[0]['CustomerName'] : 'No Data',
            'top_customers'    => $topCustomers,
            'walk_in_revenue'  => $walkInRevenue,
            'dead_stock'       => $deadStock,
            'low_stock'        => $lowStock,
            'revenue_by_type'  => $revenueByType,
            'shop_id'          => $shopId
        ];
    }

    // 兼容旧版全局数据
    $kpi = DBProcedures::getKpiStats($pdo);
    $vips = DBProcedures::getTopCustomers($pdo, 5);
    $deadStock = DBProcedures::getDeadStockAlert($pdo, 10);
    $lowStock = DBProcedures::getLowStockAlert($pdo, 10);
    $shopPerformance = DBProcedures::getShopPerformance($pdo);

    return [
        'total_sales'      => $kpi['TotalSales'] ?? 0,
        'active_orders'    => $kpi['ActiveOrders'] ?? 0,
        'low_stock_count'  => count($lowStock),
        'top_vip_name'     => !empty($vips) ? $vips[0]['Name'] : 'No Data',
        'vips'             => $vips,
        'dead_stock'       => $deadStock,
        'low_stock'        => $lowStock,
        'shop_performance' => $shopPerformance
    ];
}

// =============================================
// 【架构重构】Admin 模块 - 数据准备函数
// =============================================

/**
 * 准备用户管理页面数据
 * 【修改】移除Admin角色选项，防止通过UI创建Admin账户
 */
function prepareUsersPageData($pdo) {
    require_once __DIR__ . '/db_procedures.php';

    return [
        'employees' => DBProcedures::getEmployeeList($pdo),
        'customers' => DBProcedures::getCustomerList($pdo),
        'shops'     => DBProcedures::getShopList($pdo),
        'roles'     => ['Manager', 'Staff']  // 【修改】移除Admin选项
    ];
}

/**
 * 准备供应商管理页面数据
 */
function prepareSuppliersPageData($pdo) {
    require_once __DIR__ . '/db_procedures.php';

    return [
        'suppliers' => DBProcedures::getSupplierList($pdo)
    ];
}

/**
 * 准备产品管理页面数据
 */
function prepareProductsPageData($pdo) {
    require_once __DIR__ . '/db_procedures.php';

    // 使用视图获取专辑列表
    try {
        $releases = $pdo->query("SELECT * FROM vw_admin_release_list ORDER BY ReleaseID DESC")->fetchAll();
    } catch (PDOException $e) {
        error_log("prepareProductsPageData Error: " . $e->getMessage());
        $releases = [];
    }

    return [
        'releases' => $releases
    ];
}

/**
 * 准备采购管理页面数据
 */
function prepareProcurementPageData($pdo) {
    require_once __DIR__ . '/db_procedures.php';

    $warehouseId = getShopIdByType($pdo, 'Warehouse');

    return [
        'warehouse_id'  => $warehouseId,
        'suppliers'     => DBProcedures::getSupplierList($pdo),
        'releases'      => DBProcedures::getReleaseList($pdo),
        'pending_orders'=> DBProcedures::getPendingSupplierOrders($pdo)
    ];
}

// =============================================
// 【架构重构】Staff 模块 - 数据准备函数
// =============================================

/**
 * 准备库存管理页面数据
 */
function prepareInventoryPageData($pdo, $shopId, $viewMode = 'detail') {
    require_once __DIR__ . '/db_procedures.php';

    if ($viewMode === 'summary') {
        $inventory = DBProcedures::getInventorySummary($pdo, $shopId);
        $totalItems = array_sum(array_column($inventory, 'AvailableQuantity'));
    } else {
        $inventory = DBProcedures::getInventoryDetail($pdo, $shopId);
        $totalItems = count($inventory);
    }

    return [
        'inventory'    => $inventory,
        'total_items'  => $totalItems,
        'view_mode'    => $viewMode
    ];
}

/**
 * 准备取货页面数据
 */
function preparePickupPageData($pdo, $shopId) {
    require_once __DIR__ . '/db_procedures.php';

    return [
        'orders' => DBProcedures::getBopisPendingOrders($pdo, $shopId)
    ];
}

/**
 * 准备履约页面数据
 */
function prepareFulfillmentPageData($pdo) {
    require_once __DIR__ . '/db_procedures.php';

    return [
        'paid_orders'    => DBProcedures::getOnlineOrdersAwaitingShipment($pdo),
        'shipped_orders' => DBProcedures::getOnlineOrdersShipped($pdo)
    ];
}

/**
 * 准备回购页面数据
 */
function prepareBuybackPageData($pdo, $shopId) {
    require_once __DIR__ . '/db_procedures.php';

    return [
        'releases'        => DBProcedures::getReleaseList($pdo),
        'customers'       => DBProcedures::getCustomerSimpleList($pdo),
        'recent_buybacks' => DBProcedures::getBuybackOrders($pdo, $shopId, 10)
    ];
}

// =============================================
// 【架构重构】Manager 模块 - 数据准备函数
// =============================================

/**
 * 准备调拨页面数据
 */
function prepareTransferPageData($pdo) {
    require_once __DIR__ . '/db_procedures.php';

    return [
        'shops'   => DBProcedures::getShopList($pdo),
        'pending' => DBProcedures::getPendingTransfers($pdo)
    ];
}

/**
 * 准备报表页面数据
 * 支持按店铺筛选（Manager使用）或全局数据（Admin使用）
 */
function prepareReportsPageData($pdo, $shopId = null) {
    require_once __DIR__ . '/db_procedures.php';

    if ($shopId !== null) {
        // 店铺级别数据
        return [
            'turnover_stats' => DBProcedures::getShopSalesByGenre($pdo, $shopId),
            'sales_trend'    => DBProcedures::getShopMonthlySalesTrend($pdo, $shopId, 12),
            'shop_id'        => $shopId
        ];
    }

    // 全局数据
    return [
        'turnover_stats' => DBProcedures::getSalesByGenre($pdo),
        'sales_trend'    => DBProcedures::getMonthlySalesTrend($pdo, 12),
        'shop_id'        => null
    ];
}

// =============================================
// 【架构重构】Customer 模块 - 数据准备函数
// =============================================

/**
 * 准备商品目录页面数据（分组显示）
 */
function prepareCatalogPageData($pdo, $search = '', $genre = '') {
    require_once __DIR__ . '/db_procedures.php';

    return [
        'items'  => DBProcedures::getCatalogItemsGrouped($pdo, $search, $genre),
        'genres' => DBProcedures::getCatalogGenresGrouped($pdo)
    ];
}

/**
 * 准备商品详情页面数据（原有方法，保留兼容性）
 */
function prepareProductDetailData($pdo, $stockId) {
    require_once __DIR__ . '/db_procedures.php';

    $item = DBProcedures::getProductDetail($pdo, $stockId);

    if (!$item) {
        return ['found' => false];
    }

    return [
        'found'        => true,
        'item'         => $item,
        'alternatives' => DBProcedures::getProductAlternatives($pdo, $item['ReleaseID'], $stockId, 5)
    ];
}

/**
 * 【修改】准备专辑详情页面数据（支持按店铺筛选）
 */
function prepareReleaseDetailData($pdo, $releaseId, $shopId = 0) {
    require_once __DIR__ . '/db_procedures.php';

    // 1. 获取 Release 基本信息 (此时包含的是全局统计数据)
    $release = DBProcedures::getReleaseInfo($pdo, $releaseId);

    if (!$release) {
        return ['found' => false];
    }

    // 2. 获取库存并根据 ShopID 处理统计逻辑
    $stockItems = [];

    if ($shopId > 0) {
        // ====== 针对特定店铺的逻辑 ======
        // 【架构重构Phase2】使用DBProcedures替换直接SQL查询

        // A. 获取该店铺的分组库存 (必须包含 AvailableQuantity 字段，release.php 依赖此字段)
        $stockItems = DBProcedures::getReleaseShopStockGrouped($pdo, $releaseId, $shopId);

        // B. 重新计算该店铺的统计数据 (覆盖 $release 中的全局数据)
        $totalAvailable = 0;
        $prices = [];
        $conditions = [];

        foreach ($stockItems as $item) {
            $qty = (int)$item['AvailableQuantity'];
            $totalAvailable += $qty;
            $prices[] = (float)$item['UnitPrice'];
            $conditions[] = $item['ConditionGrade'];
        }

        // 更新统计字段
        $release['TotalAvailable'] = $totalAvailable;
        
        if ($totalAvailable > 0) {
            $release['MinPrice'] = min($prices);
            $release['MaxPrice'] = max($prices);
            
            // 重新整理成色字符串
            $conditions = array_unique($conditions);
            $condOrder = ['New', 'Mint', 'NM', 'VG+', 'VG', 'G+', 'G', 'F', 'P'];
            usort($conditions, function($a, $b) use ($condOrder) {
                $posA = array_search($a, $condOrder);
                $posB = array_search($b, $condOrder);
                return (($posA === false) ? 999 : $posA) - (($posB === false) ? 999 : $posB);
            });
            $release['AvailableConditions'] = implode(', ', $conditions);
        } else {
            $release['MinPrice'] = 0;
            $release['MaxPrice'] = 0;
            $release['AvailableConditions'] = '';
        }

    } else {
        // ====== 原有逻辑 (所有店铺/仓库) ======
        // 保持调用原有存储过程，获取全局库存分组
        $stockItems = DBProcedures::getReleaseStockByCondition($pdo, $releaseId);
    }

    return [
        'found'      => true,
        'release'    => $release,
        'stockItems' => $stockItems
    ];
}

/**
 * 【修复】添加多个库存到购物车
 * 现在会根据用户选择的店铺来获取库存
 * 【重构】移除10张限制，只受available数量限制
 */
function addMultipleToCart($pdo, $releaseId, $conditionGrade, $quantity) {
    require_once __DIR__ . '/db_procedures.php';

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // 【修复】移除10张限制，只需确保数量为正整数
    $quantity = max(1, (int)$quantity);

    // 【修复】获取用户当前选择的店铺ID
    $shopId = $_SESSION['selected_shop_id'] ?? null;

    // 【架构重构Phase2】使用DBProcedures替换直接SQL查询
    $allStockIds = DBProcedures::getAvailableStockIdsByCondition($pdo, $releaseId, $conditionGrade, $shopId);

    // 排除已在购物车中的
    $availableIds = array_diff($allStockIds, $_SESSION['cart']);

    // 限制为可用数量
    $quantity = min($quantity, count($availableIds));

    if ($quantity <= 0) {
        return ['success' => false, 'message' => 'No available stock for this item or all already in cart.'];
    }

    // 取需要的数量
    $toAdd = array_slice($availableIds, 0, $quantity);

    // 添加到购物车
    $_SESSION['cart'] = array_merge($_SESSION['cart'], $toAdd);
    $addedCount = count($toAdd);

    return ['success' => true, 'message' => "$addedCount item(s) added to cart."];
}

/**
 * 准备个人资料页面数据
 */
function prepareProfilePageData($pdo, $customerId) {
    require_once __DIR__ . '/db_procedures.php';

    $user = DBProcedures::getCustomerProfile($pdo, $customerId);

    if (!$user) {
        return null;
    }

    $currentPoints = $user['Points'];
    $nextTierInfo = DBProcedures::getNextTierInfo($pdo, $currentPoints);

    $nextTarget = $nextTierInfo['MinPoints'] ?? 0;
    $nextTierName = $nextTierInfo['TierName'] ?? 'Max Level';
    $progress = 0;

    if ($nextTarget > 0) {
        $progress = min(100, ($currentPoints / $nextTarget) * 100);
    } else {
        $progress = 100;
    }

    return [
        'user'           => $user,
        'current_points' => $currentPoints,
        'next_target'    => $nextTarget,
        'next_tier_name' => $nextTierName,
        'progress'       => $progress
    ];
}

/**
 * 准备支付页面数据
 */
function preparePayPageData($pdo, $orderId, $customerId) {
    require_once __DIR__ . '/db_procedures.php';

    $order = DBProcedures::getPendingOrder($pdo, $orderId, $customerId);

    if (!$order) {
        return ['found' => false];
    }

    return [
        'found' => true,
        'order' => $order
    ];
}

/**
 * 准备订单详情页面数据
 */
function prepareOrderDetailPageData($pdo, $orderId, $customerId) {
    require_once __DIR__ . '/db_procedures.php';

    $order = DBProcedures::getCustomerOrderDetail($pdo, $orderId, $customerId);

    if (!$order) {
        return ['found' => false];
    }

    // 计算状态样式
    $statusClass = match($order['OrderStatus']) {
        'Paid' => 'bg-success',
        'Completed' => 'bg-success',
        'Shipped' => 'bg-info',
        'Pending' => 'bg-warning text-dark',
        'Cancelled' => 'bg-danger',
        default => 'bg-secondary'
    };

    return [
        'found'        => true,
        'order'        => $order,
        'items'        => DBProcedures::getOrderItems($pdo, $orderId, $customerId),
        'status_class' => $statusClass
    ];
}

/**
 * 准备订单列表页面数据
 */
function prepareOrdersPageData($pdo, $customerId) {
    require_once __DIR__ . '/db_procedures.php';

    return [
        'orders' => DBProcedures::getCustomerOrders($pdo, $customerId)
    ];
}

// =============================================
// 【架构重构】业务处理函数 - POST 请求处理
// =============================================

/**
 * 处理员工操作（增删改）
 */
function handleEmployeeAction($pdo, $action, $data) {
    require_once __DIR__ . '/db_procedures.php';

    switch ($action) {
        case 'add':
            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $result = DBProcedures::addEmployee($pdo, $data['name'], $data['username'], $hash, $data['role'], $data['shop_id']);
            if ($result) {
                return ['success' => true, 'message' => "Employee '{$data['name']}' added successfully."];
            }
            return ['success' => false, 'message' => 'Failed to add employee.'];

        case 'edit':
            $hash = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
            $result = DBProcedures::updateEmployee($pdo, $data['employee_id'], $data['name'], $data['role'], $data['shop_id'], $hash);
            if ($result) {
                return ['success' => true, 'message' => 'Employee details updated.'];
            }
            return ['success' => false, 'message' => 'Failed to update employee.'];

        case 'delete':
            if ($data['employee_id'] == $data['current_user_id']) {
                return ['success' => false, 'message' => 'You cannot delete your own account.'];
            }
            // 【修复】正确检查删除结果
            $result = DBProcedures::deleteEmployee($pdo, $data['employee_id'], $data['current_user_id']);
            if ($result) {
                return ['success' => true, 'message' => 'Employee record deleted.'];
            }
            return ['success' => false, 'message' => 'Cannot delete employee. They may be linked to transaction records.'];

        default:
            return ['success' => false, 'message' => 'Unknown action.'];
    }
}

/**
 * 处理供应商操作（增删改）
 */
function handleSupplierAction($pdo, $action, $data) {
    require_once __DIR__ . '/db_procedures.php';

    switch ($action) {
        case 'add':
            $result = DBProcedures::addSupplier($pdo, $data['name'], $data['email']);
            if ($result === -2) {
                // 【新增】重名检查
                return ['success' => false, 'message' => "Supplier '{$data['name']}' already exists. Cannot add duplicate."];
            }
            if ($result && $result > 0) {
                return ['success' => true, 'message' => 'Supplier added successfully.'];
            }
            return ['success' => false, 'message' => 'Failed to add supplier.'];

        case 'edit':
            $result = DBProcedures::updateSupplier($pdo, $data['supplier_id'], $data['name'], $data['email']);
            if ($result) {
                return ['success' => true, 'message' => 'Supplier details updated.'];
            }
            return ['success' => false, 'message' => 'Failed to update supplier.'];

        case 'delete':
            $result = DBProcedures::deleteSupplier($pdo, $data['supplier_id']);
            if ($result === 1) {
                return ['success' => true, 'message' => 'Supplier deleted successfully.', 'type' => 'warning'];
            } elseif ($result === -1) {
                return ['success' => false, 'message' => 'Cannot delete supplier: There are existing Supplier Orders linked to this supplier.'];
            }
            return ['success' => false, 'message' => 'Delete failed.'];

        default:
            return ['success' => false, 'message' => 'Unknown action.'];
    }
}

/**
 * 处理专辑操作（增改）
 */
function handleReleaseAction($pdo, $action, $data) {
    require_once __DIR__ . '/db_procedures.php';

    switch ($action) {
        case 'add':
            $result = DBProcedures::addRelease($pdo, $data['title'], $data['artist'], $data['label'], $data['year'], $data['genre'], $data['desc']);
            if ($result === -2) {
                // 【新增】重名检查
                return ['success' => false, 'message' => "Release '{$data['title']}' by '{$data['artist']}' already exists. Cannot add duplicate."];
            }
            if ($result && $result > 0) {
                return ['success' => true, 'message' => 'New release added to catalog.'];
            }
            return ['success' => false, 'message' => 'Failed to add release.'];

        case 'edit':
            $result = DBProcedures::updateRelease($pdo, $data['release_id'], $data['title'], $data['artist'], $data['label'], $data['year'], $data['genre'], $data['desc']);
            if ($result) {
                return ['success' => true, 'message' => 'Release updated successfully.'];
            }
            return ['success' => false, 'message' => 'Failed to update release.'];

        default:
            return ['success' => false, 'message' => 'Unknown action.'];
    }
}

/**
 * 处理取货确认
 * 【修复】添加事务管理
 */
function handlePickupConfirmation($pdo, $orderId, $shopId) {
    require_once __DIR__ . '/db_procedures.php';

    $order = DBProcedures::getOrderForPickupValidation($pdo, $orderId, $shopId);

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found or not ready for pickup.'];
    }

    if ($order['OrderStatus'] !== 'Paid') {
        return ['success' => false, 'message' => "Invalid order status: {$order['OrderStatus']}"];
    }

    try {
        $pdo->beginTransaction();
        $success = DBProcedures::completeOrder($pdo, $orderId);

        if ($success) {
            $pdo->commit();
            return ['success' => true, 'message' => "Order #$orderId marked as collected."];
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Failed to complete order.'];
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Failed to complete order.'];
    }
}

/**
 * 处理客户资料更新
 */
function handleProfileUpdate($pdo, $customerId, $name, $password = null) {
    require_once __DIR__ . '/db_procedures.php';

    $hash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
    $success = DBProcedures::updateCustomerProfile($pdo, $customerId, $name, $hash);

    if ($success) {
        $_SESSION['username'] = $name;
        return ['success' => true, 'message' => 'Profile updated successfully.'];
    }

    return ['success' => false, 'message' => 'Failed to update profile.'];
}

/**
 * 处理支付完成
 * 【修复】添加事务管理，确保支付操作的原子性，增强错误日志
 */
function handlePaymentCompletion($pdo, $orderId, $customerId, $paymentMethod) {
    require_once __DIR__ . '/db_procedures.php';

    // 【修复4】详细日志：支付开始
    error_log("Payment initiated: Order #$orderId, Customer #$customerId, Method: $paymentMethod");

    if (!in_array($paymentMethod, ['alipay', 'wechat', 'card'])) {
        error_log("Payment failed: Invalid payment method '$paymentMethod'");
        return ['success' => false, 'message' => 'Invalid payment method.'];
    }

    // 验证订单
    $order = DBProcedures::getPendingOrder($pdo, $orderId, $customerId);
    if (!$order) {
        error_log("Payment failed: Order #$orderId not found or already paid for customer #$customerId");
        return ['success' => false, 'message' => 'Order not found or already paid.'];
    }

    // 验证库存仍然预留
    $reservedCount = DBProcedures::getOrderReservedCount($pdo, $orderId);
    if ($reservedCount == 0) {
        error_log("Payment failed: No reserved items for order #$orderId (expired or already processed)");
        return ['success' => false, 'message' => 'Order items expired. Please create a new order.'];
    }

    error_log("Payment validation passed: Order #$orderId has $reservedCount reserved items");

    try {
        // 【修复】开启事务
        $pdo->beginTransaction();
        error_log("Payment transaction started for order #$orderId");

        // 完成订单（触发器会自动更新积分和会员等级）
        $success = DBProcedures::payOrder($pdo, $orderId);

        if ($success) {
            $pdo->commit();
            error_log("Payment completed successfully: Order #$orderId committed, trigger should have updated points");
            return ['success' => true, 'message' => 'Payment successful!', 'order_id' => $orderId];
        } else {
            $pdo->rollBack();
            error_log("Payment failed: completeOrder returned false for order #$orderId, transaction rolled back");
            return ['success' => false, 'message' => 'Payment failed. Please contact support.'];
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("Payment exception: Transaction rolled back for order #$orderId");
        }
        error_log("Payment completion error for order #$orderId: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['success' => false, 'message' => 'Payment failed. Please contact support.'];
    }
}

/**
 * 处理回购提交
 * 【修复】添加事务管理
 */
function handleBuybackSubmission($pdo, $data) {
    require_once __DIR__ . '/db_procedures.php';

    try {
        $pdo->beginTransaction();

        $buybackId = DBProcedures::processBuyback(
            $pdo,
            $data['customer_id'] ?: null,
            $data['employee_id'],
            $data['shop_id'],
            $data['release_id'],
            1, // 数量固定为1
            $data['buy_price'],
            $data['condition'],
            $data['resale_price']
        );

        if ($buybackId && $buybackId > 0) {
            $pdo->commit();
            return ['success' => true, 'message' => "Buyback processed successfully. Buyback Order #$buybackId created.", 'buyback_id' => $buybackId];
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Failed to process buyback.'];
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Failed to process buyback.'];
    }
}

/**
 * 处理采购订单创建
 * 【修复】添加ConditionGrade和SalePrice参数
 */
function handleProcurementCreatePO($pdo, $data, $warehouseId) {
    require_once __DIR__ . '/db_procedures.php';

    if (!$warehouseId) {
        return ['success' => false, 'message' => 'Warehouse not configured.'];
    }

    try {
        $pdo->beginTransaction();

        $orderId = DBProcedures::createSupplierOrder($pdo, $data['supplier_id'], $data['employee_id'], $warehouseId);

        if (!$orderId) {
            throw new Exception('Failed to create supplier order.');
        }

        // 【修复】传递ConditionGrade和SalePrice参数
        $conditionGrade = $data['condition'] ?? 'New';
        $salePrice = $data['sale_price'] ?? null;
        $lineSuccess = DBProcedures::addSupplierOrderLine($pdo, $orderId, $data['release_id'], $data['quantity'], $data['unit_cost'], $conditionGrade, $salePrice);

        if (!$lineSuccess) {
            throw new Exception('Failed to add order line.');
        }

        $pdo->commit();
        return ['success' => true, 'message' => "Supplier Order #$orderId created successfully.", 'order_id' => $orderId];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * 处理采购订单接收
 * 【修复】添加事务管理
 */
function handleProcurementReceivePO($pdo, $orderId, $warehouseId) {
    require_once __DIR__ . '/db_procedures.php';

    if (!$warehouseId) {
        return ['success' => false, 'message' => 'Warehouse not configured.'];
    }

    try {
        $pdo->beginTransaction();

        $batchNo = "BATCH-" . date('Ymd') . "-" . $orderId;
        $success = DBProcedures::receiveSupplierOrder($pdo, $orderId, $batchNo, 'New', 0.5);

        if ($success) {
            $pdo->commit();
            return ['success' => true, 'message' => "Supplier Order #$orderId received. Items added to Warehouse inventory."];
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Failed to receive order.'];
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Failed to receive order.'];
    }
}

/**
 * 处理采购订单接收（支持指定成色）
 */
function handleProcurementReceivePOWithCondition($pdo, $orderId, $warehouseId, $condition = 'New') {
    require_once __DIR__ . '/db_procedures.php';

    if (!$warehouseId) {
        return ['success' => false, 'message' => 'Warehouse not configured.'];
    }

    try {
        $pdo->beginTransaction();

        $batchNo = "BATCH-" . date('Ymd') . "-" . $orderId;
        $success = DBProcedures::receiveSupplierOrder($pdo, $orderId, $batchNo, $condition, 0.5);

        if ($success) {
            $pdo->commit();
            return ['success' => true, 'message' => "Supplier Order #$orderId received. Items added to Warehouse inventory with condition '$condition'."];
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Failed to receive order.'];
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Failed to receive order.'];
    }
}
?>