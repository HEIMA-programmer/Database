<?php
// includes/functions.php

// 【重构】统一在文件顶部导入依赖，移除函数内的29处重复导入
require_once __DIR__ . '/db_procedures.php';

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
 * 【Session安全修复】安全获取session中的用户属性
 * 避免直接访问 $_SESSION['user'] 可能导致的空指针异常
 *
 * @param string $key 要获取的属性名（如 'ShopID', 'ShopType', 'Role'）
 * @param mixed $default 默认值
 * @return mixed
 */
function getSessionUserAttr($key, $default = null) {
    // 优先从 $_SESSION['user'] 数组获取
    if (isset($_SESSION['user']) && isset($_SESSION['user'][$key])) {
        return $_SESSION['user'][$key];
    }
    // 回退到直接的 session 变量（兼容旧代码）
    $fallbackKey = strtolower($key);
    if ($fallbackKey === 'shopid' && isset($_SESSION['shop_id'])) {
        return $_SESSION['shop_id'];
    }
    if ($fallbackKey === 'shopname' && isset($_SESSION['shop_name'])) {
        return $_SESSION['shop_name'];
    }
    return $default;
}

/**
 * 【CSRF保护】生成CSRF令牌
 * 在表单中使用：<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 【CSRF保护】验证CSRF令牌
 * @param string|null $token 提交的令牌
 * @return bool 验证是否通过
 */
function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 【CSRF保护】要求有效的CSRF令牌，否则返回错误
 * 用于API端点的保护
 */
function requireCsrfToken() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

/**
 * 【重构】根据Unit Cost计算建议售价
 * 低价产品：低比率上浮（薄利多销）
 * 高价产品：高比率上浮（单品利润大）
 * 原先在 procurement.php 和 price_config.php 中重复定义，现统一到此处
 */
function getSuggestedSalePrice($unitCost) {
    if ($unitCost <= 20) {
        return $unitCost * 1.50;  // 低价产品：上浮50%
    } elseif ($unitCost <= 50) {
        return $unitCost * 1.60;  // 中低价产品：上浮60%
    } elseif ($unitCost <= 100) {
        return $unitCost * 1.70;  // 中价产品：上浮70%
    } else {
        return $unitCost * 1.80;  // 高价产品：上浮80%
    }
}

/**
 * 【架构重构Phase3】按店铺获取目录数据
 * 改用 DBProcedures::getCatalogByShop 和 DBProcedures::getReleaseGenres
 * 已更新以匹配 ReleaseAlbum 架构
 */
function prepareCatalogPageDataByShop($pdo, $shopId, $search = '', $genre = '', $artist = '') {

    // 【架构重构Phase3】使用 DBProcedures 替换直接SQL查询
    $items = DBProcedures::getCatalogByShop($pdo, $shopId, $search, $genre, $artist);
    $genres = DBProcedures::getReleaseGenres($pdo);
    $artists = DBProcedures::getReleaseArtists($pdo);

    return [
        'items' => $items,
        'genres' => $genres,
        'artists' => $artists
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
 * 添加商品到购物车
 * 包含库存验证、重复检查和店铺一致性验证
 *
 * 【修复】验证店铺一致性：购物车只能包含同一店铺的商品
 * 【并发安全修复】使用事务和行锁防止并发超卖
 *
 * @param PDO $pdo
 * @param int $stockId
 * @return array ['success' => bool, 'message' => string]
 */
function addToCart($pdo, $stockId) {

    // 初始化购物车
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // 检查是否已在购物车
    if (in_array($stockId, $_SESSION['cart'])) {
        return ['success' => false, 'message' => 'Item already in cart.'];
    }

    try {
        // 【并发安全修复】开启事务，使用行锁确保原子性
        $pdo->beginTransaction();

        // 使用 FOR UPDATE 锁定行，防止并发修改
        $itemInfo = DBProcedures::getStockItemInfoWithLock($pdo, $stockId);

        if (!$itemInfo) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Item not found.'];
        }

        if ($itemInfo['Status'] !== 'Available') {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Item is no longer available.'];
        }

        // 【修复】验证店铺一致性
        $itemShopId = $itemInfo['ShopID'];

        if (!empty($_SESSION['cart'])) {
            // 如果购物车不为空，检查是否与已选店铺一致
            if (isset($_SESSION['selected_shop_id']) && $_SESSION['selected_shop_id'] != $itemShopId) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Cannot add items from different stores. Please complete or clear your current cart first.'
                ];
            }
        }

        // 设置或确认选中的店铺ID
        if (!isset($_SESSION['selected_shop_id'])) {
            $_SESSION['selected_shop_id'] = $itemShopId;
        }

        $_SESSION['cart'][] = $stockId;

        // 提交事务（释放行锁）
        $pdo->commit();

        return ['success' => true, 'message' => 'Item added to cart.'];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("addToCart Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add item to cart.'];
    }
}

/**
 * 从购物车移除商品
 * 【修复】当购物车清空时，同步清除selected_shop_id
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

        // 【修复】当购物车清空时，清除店铺选择
        if (empty($_SESSION['cart'])) {
            unset($_SESSION['selected_shop_id']);
        }
        return true;
    }
    return false;
}

/**
 * 清空购物车
 * 【修复】同时清除selected_shop_id，确保session一致性
 */
function clearCart() {
    $_SESSION['cart'] = [];
    unset($_SESSION['selected_shop_id']);
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

    $customer = DBProcedures::getCustomerForAuth($pdo, $email);

    if ($customer && password_verify($password, $customer['PasswordHash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $customer['CustomerID'];
        $_SESSION['username']  = $customer['Name'];
        $_SESSION['role']      = 'Customer';
        $_SESSION['tier_id']   = $customer['TierID'];

        if ($customer['Birthday']) {
            $_SESSION['birth_month'] = (int)date('m', strtotime($customer['Birthday']));

            // Check if today is the customer's birthday
            $today = date('m-d');
            $birthday = date('m-d', strtotime($customer['Birthday']));
            if ($today === $birthday) {
                $_SESSION['birthday_greeting'] = true;
                $_SESSION['birthday_name'] = $customer['Name'];
            }
        }

        // 【修复】设置 user 数组，保持与员工登录一致的结构
        // 客户没有 ShopID 和 ShopType，设置默认值避免空指针
        $_SESSION['user'] = [
            'CustomerID' => $customer['CustomerID'],
            'ShopID'     => null,
            'ShopName'   => null,
            'ShopType'   => null,
            'Role'       => 'Customer'
        ];

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

        // 【修复】设置 user 数组，保持与员工登录一致的结构
        $_SESSION['user'] = [
            'CustomerID' => $result['customer_id'],
            'ShopID'     => null,
            'ShopName'   => null,
            'ShopType'   => null,
            'Role'       => 'Customer'
        ];

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

    return [
        'employees' => DBProcedures::getEmployeeList($pdo),
        'customers' => DBProcedures::getCustomerList($pdo),
        'shops'     => DBProcedures::getShopList($pdo),
        'roles'     => ['Manager', 'Staff']  // 【修改】移除Admin选项
    ];
}

/**
 * 准备Manager用户管理页面数据
 * 只显示自己和本店铺的Staff，只能新增Staff角色
 * 【修改】添加customers数据，与Admin Users界面保持一致
 */
function prepareManagerUsersPageData($pdo, $shopId, $employeeId) {
    return [
        'employees' => DBProcedures::getEmployeesByShop($pdo, $shopId, $employeeId),
        'customers' => DBProcedures::getCustomerList($pdo),
        'shop_id'   => $shopId,
        'roles'     => ['Staff']  // Manager只能新增Staff
    ];
}

/**
 * 处理Manager员工操作（增删改）
 * 限制：只能操作本店铺的Staff，不能删除自己
 */
function handleManagerEmployeeAction($pdo, $action, $data, $managerShopId) {
    switch ($action) {
        case 'add':
            // Manager只能新增Staff
            if ($data['role'] !== 'Staff') {
                return ['success' => false, 'message' => 'You can only add Staff members.'];
            }
            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $result = DBProcedures::addEmployee($pdo, $data['name'], $data['username'], $hash, 'Staff', $managerShopId);
            if ($result) {
                return ['success' => true, 'message' => "Staff member '{$data['name']}' added successfully."];
            }
            return ['success' => false, 'message' => 'Failed to add staff member.'];

        case 'edit':
            // 验证是本店铺的Staff
            $employee = DBProcedures::getEmployeeById($pdo, $data['employee_id']);
            if (!$employee) {
                return ['success' => false, 'message' => 'Employee not found.'];
            }
            // Manager不能编辑自己的角色和店铺
            if ($data['employee_id'] == $data['current_user_id']) {
                // 只允许修改姓名和密码
                $hash = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
                $result = DBProcedures::updateEmployee($pdo, $data['employee_id'], $data['name'], $employee['Role'], $employee['ShopID'], $hash);
            } else {
                // 只能编辑本店铺的Staff
                if ($employee['ShopID'] != $managerShopId || $employee['Role'] !== 'Staff') {
                    return ['success' => false, 'message' => 'You can only edit staff members in your shop.'];
                }
                $hash = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
                $result = DBProcedures::updateEmployee($pdo, $data['employee_id'], $data['name'], 'Staff', $managerShopId, $hash);
            }
            if ($result) {
                return ['success' => true, 'message' => 'Employee details updated.'];
            }
            return ['success' => false, 'message' => 'Failed to update employee.'];

        case 'delete':
            if ($data['employee_id'] == $data['current_user_id']) {
                return ['success' => false, 'message' => 'You cannot delete your own account.'];
            }
            // 验证是本店铺的Staff
            $employee = DBProcedures::getEmployeeById($pdo, $data['employee_id']);
            if (!$employee || $employee['ShopID'] != $managerShopId || $employee['Role'] !== 'Staff') {
                return ['success' => false, 'message' => 'You can only delete staff members in your shop.'];
            }
            $result = DBProcedures::deleteEmployee($pdo, $data['employee_id'], $data['current_user_id']);
            if ($result) {
                return ['success' => true, 'message' => 'Staff member dismissed.'];
            }
            return ['success' => false, 'message' => 'Cannot delete staff. They may be linked to transaction records.'];

        default:
            return ['success' => false, 'message' => 'Unknown action.'];
    }
}

/**
 * 准备供应商管理页面数据
 */
function prepareSuppliersPageData($pdo) {

    return [
        'suppliers' => DBProcedures::getSupplierList($pdo)
    ];
}

/**
 * 准备产品管理页面数据
 */
function prepareProductsPageData($pdo) {

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
 * 【修改】添加分页支持用于Order History
 */
function prepareProcurementPageData($pdo, $historyPage = 1, $historyPerPage = 15) {

    $warehouseId = getShopIdByType($pdo, 'Warehouse');

    // Get total count for pagination
    $historyTotal = DBProcedures::getReceivedSupplierOrdersCount($pdo);
    $historyOffset = ($historyPage - 1) * $historyPerPage;

    return [
        'warehouse_id'    => $warehouseId,
        'suppliers'       => DBProcedures::getSupplierList($pdo),
        'releases'        => DBProcedures::getReleaseList($pdo),
        'pending_orders'  => DBProcedures::getPendingSupplierOrders($pdo),
        'received_orders' => DBProcedures::getReceivedSupplierOrdersPaginated($pdo, $historyPerPage, $historyOffset),
        'history_pagination' => getPaginationData($historyPage, $historyTotal, $historyPerPage)
    ];
}

// =============================================
// 【架构重构】Staff 模块 - 数据准备函数
// =============================================

/**
 * 准备库存管理页面数据
 */
function prepareInventoryPageData($pdo, $shopId, $viewMode = 'detail') {

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
 * Prepare inventory page data for all shops (Admin view)
 */
function prepareInventoryPageDataAllShops($pdo, $viewMode = 'detail') {

    if ($viewMode === 'summary') {
        $inventory = DBProcedures::getInventorySummaryAllShops($pdo);
        $totalItems = array_sum(array_column($inventory, 'AvailableQuantity'));
    } else {
        $inventory = DBProcedures::getInventoryDetailAllShops($pdo);
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

    return [
        'orders' => DBProcedures::getBopisPendingOrders($pdo, $shopId)
    ];
}

// =============================================
// 【架构重构】Manager 模块 - 数据准备函数
// =============================================

/**
 * 准备报表页面数据
 * 支持按店铺筛选（Manager使用）或全局数据（Admin使用）
 */
function prepareReportsPageData($pdo, $shopId = null) {

    if ($shopId !== null) {
        // 店铺级别数据
        return [
            'turnover_stats'     => DBProcedures::getShopSalesByGenre($pdo, $shopId),
            'sales_trend'        => DBProcedures::getShopMonthlySalesTrend($pdo, $shopId, 12),
            'artist_profit'      => DBProcedures::getShopArtistProfitAnalysis($pdo, $shopId),
            'batch_sales'        => DBProcedures::getShopBatchSalesAnalysis($pdo, $shopId),
            'shop_id'            => $shopId
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
 * 准备商品详情页面数据（原有方法，保留兼容性）
 */
function prepareProductDetailData($pdo, $stockId) {

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

    // 3. 【新增】获取专辑曲目列表
    $tracks = DBProcedures::getReleaseTracks($pdo, $releaseId);

    return [
        'found'      => true,
        'release'    => $release,
        'stockItems' => $stockItems,
        'tracks'     => $tracks
    ];
}

/**
 * 【修复】添加多个库存到购物车
 * 现在会根据用户选择的店铺来获取库存
 * 【重构】移除10张限制，只受available数量限制
 */
function addMultipleToCart($pdo, $releaseId, $conditionGrade, $quantity) {

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
 * 处理采购订单创建
 * 【修复】添加ConditionGrade和SalePrice参数
 */
function handleProcurementCreatePO($pdo, $data, $warehouseId) {

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
 * 处理采购订单接收（支持指定成色）
 */
function handleProcurementReceivePOWithCondition($pdo, $orderId, $warehouseId, $condition = 'New') {

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

// ----------------
// Pagination Helper
// ----------------

/**
 * Generate pagination data for display
 * @param int $currentPage Current page number
 * @param int $totalItems Total number of items
 * @param int $perPage Items per page
 * @param string $baseUrl Base URL for pagination links
 * @return array Pagination data including page numbers and navigation
 */
function getPaginationData($currentPage, $totalItems, $perPage, $baseUrl = '?') {
    $totalPages = max(1, ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));

    // Calculate visible page range
    $range = 2; // Show 2 pages on each side of current
    $startPage = max(1, $currentPage - $range);
    $endPage = min($totalPages, $currentPage + $range);

    // Build page numbers array
    $pages = [];
    if ($startPage > 1) {
        $pages[] = 1;
        if ($startPage > 2) {
            $pages[] = '...';
        }
    }
    for ($i = $startPage; $i <= $endPage; $i++) {
        $pages[] = $i;
    }
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $pages[] = '...';
        }
        $pages[] = $totalPages;
    }

    return [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'per_page' => $perPage,
        'pages' => $pages,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'prev_page' => $currentPage - 1,
        'next_page' => $currentPage + 1,
        'offset' => ($currentPage - 1) * $perPage,
        'base_url' => $baseUrl
    ];
}

/**
 * Prepare paginated inventory data for a shop with search and filter
 * @param array $filters ['search' => string, 'batch' => string, 'sort' => string]
 */
function prepareInventoryPageDataPaginated($pdo, $shopId, $viewMode, $page = 1, $perPage = 20, $filters = []) {
    // Get total count with filters
    $totalItems = DBProcedures::getShopInventoryCount($pdo, $shopId, $viewMode, $filters);

    // Calculate offset
    $offset = ($page - 1) * $perPage;

    // Get paginated data with filters
    if ($viewMode === 'summary') {
        $inventory = DBProcedures::getInventorySummaryPaginated($pdo, $shopId, $perPage, $offset, $filters);
    } else {
        $inventory = DBProcedures::getInventoryDetailPaginated($pdo, $shopId, $perPage, $offset, $filters);
    }

    // Get batch list for filter dropdown
    $batches = DBProcedures::getInventoryBatches($pdo, $shopId);

    return [
        'inventory' => $inventory,
        'total_items' => $totalItems,
        'pagination' => getPaginationData($page, $totalItems, $perPage),
        'batches' => $batches
    ];
}

/**
 * Prepare paginated inventory data for all shops (admin) with search and filter
 * @param array $filters ['search' => string, 'batch' => string, 'sort' => string]
 */
function prepareInventoryPageDataAllShopsPaginated($pdo, $viewMode, $page = 1, $perPage = 20, $filters = []) {
    // Get total count with filters
    $totalItems = DBProcedures::getAllShopsInventoryCount($pdo, $viewMode, $filters);

    // Calculate offset
    $offset = ($page - 1) * $perPage;

    // Get paginated data with filters
    if ($viewMode === 'summary') {
        $inventory = DBProcedures::getInventorySummaryAllShopsPaginated($pdo, $perPage, $offset, $filters);
    } else {
        $inventory = DBProcedures::getInventoryDetailAllShopsPaginated($pdo, $perPage, $offset, $filters);
    }

    // Get batch list for filter dropdown
    $batches = DBProcedures::getInventoryBatchesAllShops($pdo);

    return [
        'inventory' => $inventory,
        'total_items' => $totalItems,
        'pagination' => getPaginationData($page, $totalItems, $perPage),
        'batches' => $batches
    ];
}

// ----------------
// Notification Badge Helpers
// ----------------

/**
 * Get notification counts for Staff navigation
 * Returns counts for Fulfillment, Pickup, Transfers (outgoing), Receiving (incoming), and Procurement (supplier receipts)
 */
function getStaffNotificationCounts($pdo, $shopId) {
    return [
        'fulfillment' => DBProcedures::getPendingFulfillmentCount($pdo, $shopId),
        'pickup' => DBProcedures::getPendingPickupCount($pdo, $shopId),
        'transfers' => DBProcedures::getPendingTransferOutCount($pdo, $shopId),
        'receiving' => DBProcedures::getIncomingTransferCount($pdo, $shopId),
        'procurement' => DBProcedures::getPendingSupplierReceiptCount($pdo, $shopId)
    ];
}

/**
 * Get notification counts for Admin navigation
 * Returns count for pending requests
 */
function getAdminNotificationCounts($pdo) {
    return [
        'requests' => DBProcedures::getAdminPendingRequestsCount($pdo)
    ];
}

/**
 * Get notification counts for Manager navigation
 * Returns count for responded requests
 */
function getManagerNotificationCounts($pdo, $employeeId) {
    return [
        'requests' => DBProcedures::getManagerRespondedRequestsCount($pdo, $employeeId)
    ];
}

/**
 * Get notification counts for Customer navigation
 * Returns count for shipped delivery orders awaiting confirmation
 */
function getCustomerNotificationCounts($pdo, $customerId) {
    return [
        'shipped_orders' => DBProcedures::getCustomerShippedDeliveryCount($pdo, $customerId)
    ];
}

/**
 * Get login alerts for pending tasks based on user role
 * Returns array of alert messages with type and icon
 */
function getLoginAlerts($pdo, $role, $shopId = null, $employeeId = null, $customerId = null) {
    $alerts = [];

    if ($role === 'Staff' && $shopId) {
        $counts = getStaffNotificationCounts($pdo, $shopId);
        if ($counts['pickup'] > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'fa-box-open',
                'message' => $counts['pickup'] . ' order(s) awaiting pickup'
            ];
        }
        if ($counts['fulfillment'] > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'fa-truck-fast',
                'message' => $counts['fulfillment'] . ' order(s) ready for fulfillment'
            ];
        }
        if ($counts['transfers'] > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-right-left',
                'message' => $counts['transfers'] . ' pending transfer(s) to process'
            ];
        }
    }

    if ($role === 'Manager' && $employeeId) {
        $counts = getManagerNotificationCounts($pdo, $employeeId);
        if ($counts['requests'] > 0) {
            $alerts[] = [
                'type' => 'success',
                'icon' => 'fa-envelope-circle-check',
                'message' => $counts['requests'] . ' request(s) have been responded to'
            ];
        }
    }

    if ($role === 'Admin') {
        $counts = getAdminNotificationCounts($pdo);
        if ($counts['requests'] > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-clipboard-check',
                'message' => $counts['requests'] . ' pending request(s) from managers'
            ];
        }
    }

    if ($role === 'Customer' && $customerId) {
        $counts = getCustomerNotificationCounts($pdo, $customerId);
        if ($counts['shipped_orders'] > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'fa-truck',
                'message' => $counts['shipped_orders'] . ' shipped order(s) awaiting your confirmation'
            ];
        }
    }

    return $alerts;
}
?>