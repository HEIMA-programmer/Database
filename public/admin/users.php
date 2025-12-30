<?php
/**
 * 【架构重构】用户管理页面
 * 表现层 - 仅负责数据展示和用户交互
 * 业务逻辑已下沉到 functions.php 和 db_procedures.php
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Admin');

// ========== POST 请求处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['add_employee']) ? 'add' :
             (isset($_POST['edit_employee']) ? 'edit' :
             (isset($_POST['delete_employee']) ? 'delete' : ''));

    $data = [
        'employee_id'     => $_POST['employee_id'] ?? null,
        'name'            => trim($_POST['name'] ?? ''),
        'username'        => trim($_POST['username'] ?? ''),
        'password'        => $_POST['password'] ?? '',
        'role'            => $_POST['role'] ?? 'Staff',
        'shop_id'         => $_POST['shop_id'] ?? null,
        'current_user_id' => $_SESSION['user_id']
    ];

    $result = handleEmployeeAction($pdo, $action, $data);
    flash($result['message'], $result['success'] ? 'success' : 'danger');

    header("Location: users.php");
    exit();
}

// ========== 数据准备 ==========
$pageData = prepareUsersPageData($pdo);
$employees = $pageData['employees'];
$customers = $pageData['customers'];
$shops     = $pageData['shops'];
$roles     = $pageData['roles'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<!-- 【修复】移除顶部按钮，New Employee按钮移至员工tab内部 -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-users-gear me-2"></i>User Management</h2>
</div>

<ul class="nav nav-tabs border-secondary mb-4" id="userTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active text-light" id="emp-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button">Employees (<?= count($employees) ?>)</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-secondary" id="cust-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button">Customers (<?= count($customers) ?>)</button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="employees">
        <!-- 【修复】New Employee按钮移至员工tab内部 -->
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addEmpModal">
                <i class="fa-solid fa-user-plus me-2"></i>New Employee
            </button>
        </div>
        <div class="card bg-dark border-secondary">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Name / Username</th>
                            <th>Role</th>
                            <th>Assigned Shop</th>
                            <th>Hire Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $e): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= h($e['Name']) ?></div>
                                <div class="small text-muted">@<?= h($e['Username']) ?></div>
                            </td>
                            <td>
                                <?php
                                $badge = match($e['Role']) {
                                    'Admin' => 'bg-danger',
                                    'Manager' => 'bg-warning text-dark',
                                    default => 'bg-info text-dark'
                                };
                                ?>
                                <span class="badge <?= $badge ?>"><?= h($e['Role']) ?></span>
                            </td>
                            <td><?= h($e['ShopName']) ?></td>
                            <td><?= $e['HireDate'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info me-1 edit-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editEmpModal"
                                        data-id="<?= $e['EmployeeID'] ?>"
                                        data-name="<?= h($e['Name']) ?>"
                                        data-role="<?= h($e['Role']) ?>"
                                        data-shop="<?= $e['ShopID'] ?>">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>

                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                    <input type="hidden" name="delete_employee" value="1">
                                    <input type="hidden" name="employee_id" value="<?= $e['EmployeeID'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" <?= $e['EmployeeID'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="customers">
        <div class="card bg-dark border-secondary">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name / Email</th>
                            <th>Membership</th>
                            <th>Points</th>
                            <th>DOB</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>#<?= $c['CustomerID'] ?></td>
                            <td>
                                <div class="fw-bold"><?= h($c['Name']) ?></div>
                                <div class="small text-muted"><?= h($c['Email']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary border border-warning text-warning">
                                    <?= h($c['TierName']) ?>
                                </span>
                            </td>
                            <td class="text-info fw-bold"><?= $c['Points'] ?> pts</td>
                            <td><?= $c['Birthday'] ? h($c['Birthday']) : 'N/A' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addEmpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Onboard New Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_employee" value="1">
                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label>Role</label>
                            <select name="role" class="form-select bg-dark text-white border-secondary">
                                <?php foreach($roles as $r): ?>
                                    <option value="<?= h($r) ?>"><?= h($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                            <label>Assigned Shop</label>
                            <select name="shop_id" class="form-select bg-dark text-white border-secondary">
                                <?php foreach($shops as $s): ?>
                                    <option value="<?= $s['ShopID'] ?>"><?= h($s['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-warning text-dark fw-bold">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editEmpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Edit Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_employee" value="1">
                    <input type="hidden" name="employee_id" id="edit_emp_id">

                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control bg-dark text-white border-secondary" required>
                    </div>

                    <div class="mb-3">
                        <label>Reset Password (Optional)</label>
                        <input type="password" name="password" class="form-control bg-dark text-white border-secondary" placeholder="Leave blank to keep current">
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <label>Role</label>
                            <select name="role" id="edit_role" class="form-select bg-dark text-white border-secondary">
                                <?php foreach($roles as $r): ?>
                                    <option value="<?= h($r) ?>"><?= h($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                            <label>Assigned Shop</label>
                            <select name="shop_id" id="edit_shop" class="form-select bg-dark text-white border-secondary">
                                <?php foreach($shops as $s): ?>
                                    <option value="<?= $s['ShopID'] ?>"><?= h($s['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-info text-dark fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_emp_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_role').value = this.dataset.role;
            document.getElementById('edit_shop').value = this.dataset.shop;
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
