<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
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
                $sql = "SELECT e.EmployeeID, e.Name, e.PasswordHash, e.ShopID, ur.RoleName, s.Name as ShopName
                        FROM Employee e
                        JOIN UserRole ur ON e.RoleID = ur.RoleID
                        JOIN Shop s ON e.ShopID = s.ShopID
                        WHERE e.Username = :username";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['PasswordHash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $user['EmployeeID'];
                    $_SESSION['username']  = $user['Name'];
                    $_SESSION['role']      = $user['RoleName'];
                    $_SESSION['shop_id']   = $user['ShopID'];
                    $_SESSION['shop_name'] = $user['ShopName'];
                    
                    flash("Welcome back, {$user['Name']}!", 'success');
                    $redirect = $_SESSION['redirect_url'] ?? '/index.php';
                    unset($_SESSION['redirect_url']);
                    header("Location: " . $redirect);
                    exit();
                } else {
                    $error = 'Invalid credentials.';
                }

            } else {
                $sql = "SELECT CustomerID, Name, PasswordHash, Birthday, TierID FROM Customer WHERE Email = :email";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':email' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['PasswordHash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $user['CustomerID'];
                    $_SESSION['username']  = $user['Name'];
                    $_SESSION['role']      = 'Customer';
                    $_SESSION['tier_id']   = $user['TierID'];
                    if ($user['Birthday']) {
                        $_SESSION['birth_month'] = (int)date('m', strtotime($user['Birthday']));
                    }

                    flash('Welcome to Retro Echo Records!', 'success');
                    $redirect = $_SESSION['redirect_url'] ?? (BASE_URL . '/customer/catalog.php');
                    unset($_SESSION['redirect_url']);
                    header("Location: " . $redirect);
                    exit();
                } else {
                    $error = 'Invalid credentials.';
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'System error.';
        }
    }
}
require_once __DIR__ . '/../includes/header.php';
?>

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
            <p class="text-muted small">
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