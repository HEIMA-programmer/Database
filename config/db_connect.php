<?php
// ========== Session 初始化（必须在数据库连接前）==========
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========== BASE_URL 配置 ==========
// 云环境（SAE）：DocumentRoot 已经是 public 目录，BASE_URL 为空
// 本地环境（XAMPP）：需要包含 /public 路径
if (getenv('DB_HOST')) {
    // 云环境：DocumentRoot = /var/www/html/public
    $baseUrl = '';
} else {
    // 本地开发环境
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $scriptPath = str_replace('\\', '/', $scriptPath);

    if (strpos($scriptPath, '/public') !== false) {
        $baseUrl = substr($scriptPath, 0, strpos($scriptPath, '/public') + 7);
    } else {
        $baseUrl = $scriptPath . '/public';
    }
    $baseUrl = rtrim($baseUrl, '/');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}

if (!defined('PAYMENT_TIMEOUT_MINUTES')) {
    define('PAYMENT_TIMEOUT_MINUTES', 15);
}

if (!defined('SHIPPING_FEE')) {
    define('SHIPPING_FEE', 15.00);
}

date_default_timezone_set('Asia/Shanghai');

// ========== 数据库配置 ==========
$host    = getenv('DB_HOST') ?: '127.0.0.1';
$dbname  = getenv('DB_NAME') ?: 'retro_echo';
$port    = getenv('DB_PORT') ?: '3306';
$charset = 'utf8mb4';

// ========== 角色隔离开关 ==========
// true: 使用角色账户（生产环境）
// false: 使用单一账户（开发环境，向后兼容）
$enableRoleIsolation = (bool)(getenv('DB_ROLE_ISOLATION') ?: false);

// ========== 根据角色选择数据库账户 ==========
if ($enableRoleIsolation) {
    // 角色账户凭据（生产环境应使用环境变量）
    $dbCredentials = [
        'Admin'    => [
            'user' => getenv('DB_USER_ADMIN') ?: 'retro_admin',
            'pass' => getenv('DB_PASS_ADMIN') ?: 'Admin@SecurePass123!'
        ],
        'Manager'  => [
            'user' => getenv('DB_USER_MANAGER') ?: 'retro_manager',
            'pass' => getenv('DB_PASS_MANAGER') ?: 'Manager@SecurePass456!'
        ],
        'Staff'    => [
            'user' => getenv('DB_USER_STAFF') ?: 'retro_staff',
            'pass' => getenv('DB_PASS_STAFF') ?: 'Staff@SecurePass789!'
        ],
        'Customer' => [
            'user' => getenv('DB_USER_CUSTOMER') ?: 'retro_customer',
            'pass' => getenv('DB_PASS_CUSTOMER') ?: 'Customer@SecurePass000!'
        ]
    ];

    // 根据 session 中的角色选择账户，默认使用 Customer（匿名用户）
    $role = $_SESSION['role'] ?? 'Customer';

    if (isset($dbCredentials[$role])) {
        $username = $dbCredentials[$role]['user'];
        $password = $dbCredentials[$role]['pass'];
    } else {
        // 未知角色，使用 Customer 账户
        $username = $dbCredentials['Customer']['user'];
        $password = $dbCredentials['Customer']['pass'];
    }
} else {
    // 向后兼容：使用单一账户（开发环境）
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
}

// ========== 建立数据库连接 ==========
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());

    $errorMessage = "Could not connect to the database server.";
    include __DIR__ . '/../includes/error.php';
    exit();
}
?>
