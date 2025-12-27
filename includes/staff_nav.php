<?php
/**
 * 员工导航菜单组件
 * 
 * 【修复】根据员工所属店铺类型显示不同菜单：
 * - 仓库(Warehouse): 只显示 Fulfillment 和 Inventory
 * - 门店(Retail): 显示全部菜单（POS, Buyback, Fulfillment, Inventory）
 */

// 确保已登录且是员工
if (!isset($_SESSION['user']) || $_SESSION['user']['Role'] != 'Staff') {
    return;
}

// 获取员工店铺类型
$staffShopType = $_SESSION['user']['ShopType'] ?? null;
if (!$staffShopType && isset($_SESSION['user']['EmployeeID'])) {
    // 如果session中没有，从数据库获取
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT s.Type 
        FROM Employee e 
        JOIN Shop s ON e.ShopID = s.ShopID 
        WHERE e.EmployeeID = ?
    ");
    $stmt->execute([$_SESSION['user']['EmployeeID']]);
    $staffShopType = $stmt->fetchColumn();
    $_SESSION['user']['ShopType'] = $staffShopType;
}

$isWarehouse = ($staffShopType == 'Warehouse');

// 定义菜单项
$menuItems = [];

// 仓库和门店都有的菜单
$menuItems[] = [
    'url' => '/staff/fulfillment.php',
    'icon' => 'fa-boxes-stacked',
    'label' => 'Fulfillment',
    'description' => 'Process and ship orders'
];

$menuItems[] = [
    'url' => '/staff/inventory.php',
    'icon' => 'fa-warehouse',
    'label' => 'Inventory',
    'description' => 'Manage stock items'
];

// 只有门店才有的菜单
if (!$isWarehouse) {
    // 插入到开头
    array_unshift($menuItems, [
        'url' => '/staff/pos.php',
        'icon' => 'fa-cash-register',
        'label' => 'POS',
        'description' => 'In-store sales'
    ]);
    
    array_unshift($menuItems, [
        'url' => '/staff/buyback.php',
        'icon' => 'fa-rotate-left',
        'label' => 'Buyback',
        'description' => 'Purchase records from customers'
    ]);
}

// 获取当前页面路径
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand text-warning" href="/staff/">
            <i class="fa-solid fa-record-vinyl me-2"></i>Retro Echo Staff
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#staffNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="staffNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menuItems as $item): 
                    $isActive = strpos($currentPath, $item['url']) !== false;
                ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isActive ? 'active text-warning' : '' ?>" 
                       href="<?= $item['url'] ?>"
                       title="<?= h($item['description']) ?>">
                        <i class="fa-solid <?= $item['icon'] ?> me-1"></i>
                        <?= h($item['label']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="navbar-nav">
                <span class="nav-link text-muted">
                    <i class="fa-solid <?= $isWarehouse ? 'fa-warehouse' : 'fa-store' ?> me-1"></i>
                    <?= h($_SESSION['user']['ShopName'] ?? 'Unknown Shop') ?>
                </span>
                <a class="nav-link text-danger" href="/logout.php">
                    <i class="fa-solid fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<?php if ($isWarehouse): ?>
<div class="alert alert-info bg-dark border-info mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    <strong>Warehouse Mode:</strong> You can manage fulfillment and inventory. POS and Buyback are available at retail locations only.
</div>
<?php endif; ?>