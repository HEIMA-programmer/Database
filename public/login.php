<?php
/**
 * 登录页面
 * 【架构重构】遵循理想化分层架构
 * - 通过 functions.php 的认证函数处理登录逻辑
 * - 无直接数据库访问
 */
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// 【Session管理修复】使用条件检查，避免重复调用session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 已登录用户直接跳转
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$error = '';

// =============================================
// 【业务逻辑层调用】通过 functions.php 处理认证
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 【安全】验证CSRF令牌
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $loginType = $_POST['login_type'] ?? 'customer';
        $credential = trim($_POST['username'] ?? ''); // 员工用username，客户用email
        $password  = $_POST['password'] ?? '';

        if (empty($credential) || empty($password)) {
            $error = 'Please enter both username/email and password.';
        } else {
            try {
                if ($loginType === 'employee') {
                    // 员工登录（使用 username）
                    $result = authenticateEmployee($pdo, $credential, $password);

                    if ($result['success']) {
                        flash("Welcome back, {$_SESSION['username']}!", 'success');

                        // Get login alerts for pending tasks
                        $shopId = $_SESSION['shop_id'] ?? $_SESSION['user']['ShopID'] ?? null;
                        $employeeId = $_SESSION['user_id'];
                        $alerts = getLoginAlerts($pdo, $result['role'], $shopId, $employeeId);
                        foreach ($alerts as $alert) {
                            flash('<i class="fa-solid ' . $alert['icon'] . ' me-2"></i>' . $alert['message'], $alert['type']);
                        }

                        $redirect = $_SESSION['redirect_url'] ?? getLoginRedirectUrl($result['role']);
                        unset($_SESSION['redirect_url']);
                        header("Location: " . $redirect);
                        exit();
                    } else {
                        $error = $result['message'];
                    }
                } else {
                    // 客户登录（使用 email）
                    $email = $credential;
                    $result = authenticateCustomer($pdo, $email, $password);

                    if ($result['success']) {
                        flash('Welcome to Retro Echo Records!', 'success');

                        // Get login alerts for shipped orders
                        $customerId = $_SESSION['user_id'];
                        $alerts = getLoginAlerts($pdo, 'Customer', null, null, $customerId);
                        foreach ($alerts as $alert) {
                            flash('<i class="fa-solid ' . $alert['icon'] . ' me-2"></i>' . $alert['message'], $alert['type']);
                        }

                        $redirect = $_SESSION['redirect_url'] ?? (BASE_URL . '/customer/catalog.php');
                        unset($_SESSION['redirect_url']);
                        header("Location: " . $redirect);
                        exit();
                    } else {
                        $error = $result['message'];
                    }
                }
            } catch (Exception $e) {
                error_log("Login Error: " . $e->getMessage());
                $error = 'System error.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =============================================
     【表现层】仅负责 HTML 渲染
     ============================================= -->

<div class="row justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="col-md-5 col-lg-4">

        <div class="text-center mb-4">
            <h1 class="display-4 text-warning fw-bold mb-0">Retro Echo</h1>
            <p class="text-secondary letter-spacing-2">ACCESS TERMINAL</p>
        </div>

        <div class="card bg-secondary border-0 shadow-lg">
            <div class="card-body p-4 p-md-5">
                <?php if ($error): ?>
                    <div class="alert alert-danger bg-danger text-white border-0 py-2 small mb-4">
                        <i class="fa-solid fa-circle-exclamation me-2"></i><?= h($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="mb-4">
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="login_type" id="type_cust" value="customer" checked>
                            <label class="btn btn-outline-warning" for="type_cust">Customer</label>

                            <input type="radio" class="btn-check" name="login_type" id="type_emp" value="employee">
                            <label class="btn btn-outline-warning" for="type_emp">Staff</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small text-uppercase" id="userLabel">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-user"></i></span>
                            <input type="text" class="form-control" name="username" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-muted small text-uppercase">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 py-2 fw-bold shadow-sm">
                        SIGN IN
                    </button>
                </form>
            </div>

            <div class="card-footer bg-dark border-0 text-center py-3">
                <span class="text-secondary small">Don't have an account?</span>
                <a href="register.php" class="text-warning fw-bold ms-1 text-decoration-none small">Register Now</a>
            </div>
        </div>

        <div class="text-center mt-4">
            <p class="small demo-account-text">
                Demo Accounts:<br>
                <span class="text-warning">admin</span> / password123<br>
                <span class="text-warning">alice@test.com</span> / password123
            </p>
        </div>
    </div>
</div>

<script>
    const radioCust = document.getElementById('type_cust');
    const radioEmp = document.getElementById('type_emp');
    const userLabel = document.getElementById('userLabel');

    function updateLabel() {
        userLabel.innerText = radioEmp.checked ? 'Username' : 'Email Address';
    }

    radioCust.addEventListener('change', updateLabel);
    radioEmp.addEventListener('change', updateLabel);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
