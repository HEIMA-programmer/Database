<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

// 如果已登录，直接跳转
if (isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginType = $_POST['login_type'] ?? 'customer';
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        try {
            if ($loginType === 'employee') {
                // --- 员工登录逻辑 ---
                // 获取员工信息，包括角色名和所属门店ID
                $sql = "SELECT e.EmployeeID, e.Name, e.PasswordHash, e.ShopID, ur.RoleName, s.Name as ShopName
                        FROM Employee e
                        JOIN UserRole ur ON e.RoleID = ur.RoleID
                        JOIN Shop s ON e.ShopID = s.ShopID
                        WHERE e.Username = :username";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['PasswordHash'])) {
                    // Session 固定攻击防护
                    session_regenerate_id(true);

                    $_SESSION['user_id']   = $user['EmployeeID'];
                    $_SESSION['username']  = $user['Name'];
                    $_SESSION['role']      = $user['RoleName'];
                    $_SESSION['shop_id']   = $user['ShopID']; // 关键：POS系统需要知道是哪个店
                    $_SESSION['shop_name'] = $user['ShopName'];

                    flash("Welcome back, {$user['Name']}!", 'success');
                    
                    // 检查是否有重定向目标
                    $redirect = $_SESSION['redirect_url'] ?? '/index.php';
                    unset($_SESSION['redirect_url']);
                    header("Location: " . $redirect);
                    exit();
                } else {
                    $error = 'Invalid staff username or password.';
                }

            } else {
                // --- 顾客登录逻辑 ---
                $sql = "SELECT CustomerID, Name, PasswordHash, Birthday, TierID 
                        FROM Customer 
                        WHERE Email = :email";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':email' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['PasswordHash'])) {
                    session_regenerate_id(true);

                    $_SESSION['user_id']   = $user['CustomerID'];
                    $_SESSION['username']  = $user['Name'];
                    $_SESSION['role']      = 'Customer';
                    $_SESSION['tier_id']   = $user['TierID'];
                    
                    // 存储生日月份用于折扣计算 (Assignment 1.2.3)
                    if ($user['Birthday']) {
                        $_SESSION['birth_month'] = (int)date('m', strtotime($user['Birthday']));
                    }

                    flash('Welcome to Retro Echo Records!', 'success');
                    
                    $redirect = $_SESSION['redirect_url'] ?? '/customer/catalog.php';
                    unset($_SESSION['redirect_url']);
                    header("Location: " . $redirect);
                    exit();
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'System error during login. Please try again.';
        }
    }
}

// 加载头部（必须在逻辑处理之后，避免 header already sent）
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="col-md-5 col-lg-4">
        <div class="card bg-secondary text-white shadow-lg">
            <div class="card-header bg-warning text-dark fw-bold text-center py-3">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Login
            </div>
            <div class="card-body p-4">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="loginForm">
                    <div class="mb-3">
                        <label class="form-label text-warning small">I am a...</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="login_type" id="type_cust" value="customer" checked autocomplete="off">
                            <label class="btn btn-outline-light" for="type_cust">Customer</label>

                            <input type="radio" class="btn-check" name="login_type" id="type_emp" value="employee" autocomplete="off">
                            <label class="btn btn-outline-light" for="type_emp">Staff / Admin</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label" id="usernameLabel">Email Address</label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus value="<?= h($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning fw-bold text-dark">Sign In</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center small text-light-50">
                Don't have an account? <a href="#" class="text-warning text-decoration-none">Register here</a>
            </div>
        </div>
        
        <div class="mt-4 text-center text-muted border border-secondary p-2 rounded">
            <small class="d-block mb-1 text-warning">Demo Credentials:</small>
            <small class="d-block">Admin: <code>admin</code> / <code>password123</code></small>
            <small class="d-block">Staff: <code>staff_cs</code> / <code>password123</code></small>
            <small class="d-block">Customer: <code>alice@test.com</code> / <code>password123</code></small>
        </div>
    </div>
</div>

<script>
    // 简单的 JS 切换 Label，提升体验
    const radioCust = document.getElementById('type_cust');
    const radioEmp = document.getElementById('type_emp');
    const userLabel = document.getElementById('usernameLabel');

    function updateLabel() {
        if (radioEmp.checked) {
            userLabel.innerText = 'Username';
        } else {
            userLabel.innerText = 'Email Address';
        }
    }

    radioCust.addEventListener('change', updateLabel);
    radioEmp.addEventListener('change', updateLabel);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>