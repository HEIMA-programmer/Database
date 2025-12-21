<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../includes/header.php';

// --- Action: Add Employee ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    try {
        $sql = "INSERT INTO Employee (Name, Username, PasswordHash, RoleID, ShopID, HireDate) 
                VALUES (:name, :user, :pass, :role, :shop, CURDATE())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => trim($_POST['name']),
            ':user' => trim($_POST['username']),
            ':pass' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            ':role' => $_POST['role_id'],
            ':shop' => $_POST['shop_id']
        ]);
        flash("Employee '{$_POST['name']}' added successfully.", 'success');
    } catch (PDOException $e) {
        flash("Error adding employee: " . $e->getMessage(), 'danger');
    }
    header("Location: users.php");
    exit();
}

// --- Action: Edit Employee (New Feature) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    try {
        $id = $_POST['employee_id'];
        $name = trim($_POST['name']);
        $role = $_POST['role_id'];
        $shop = $_POST['shop_id'];
        
        // 检查是否在修改密码
        if (!empty($_POST['password'])) {
            $passHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE Employee SET Name = ?, RoleID = ?, ShopID = ?, PasswordHash = ? WHERE EmployeeID = ?";
            $pdo->prepare($sql)->execute([$name, $role, $shop, $passHash, $id]);
        } else {
            $sql = "UPDATE Employee SET Name = ?, RoleID = ?, ShopID = ? WHERE EmployeeID = ?";
            $pdo->prepare($sql)->execute([$name, $role, $shop, $id]);
        }
        
        flash("Employee details updated.", 'success');
    } catch (PDOException $e) {
        flash("Error updating employee: " . $e->getMessage(), 'danger');
    }
    header("Location: users.php");
    exit();
}

// --- Action: Delete Employee ---
if (isset($_POST['delete_employee'])) {
    $empId = $_POST['employee_id'];
    if ($empId != $_SESSION['user_id']) { // 防止删除自己
        try {
            $pdo->prepare("DELETE FROM Employee WHERE EmployeeID = ?")->execute([$empId]);
            flash("Employee record deleted.", 'warning');
        } catch (PDOException $e) {
            flash("Cannot delete employee. They may be linked to transaction records.", 'danger');
        }
    } else {
        flash("You cannot delete your own account.", 'danger');
    }
    header("Location: users.php");
    exit();
}

// --- Data Queries ---
$employees = $pdo->query("SELECT * FROM vw_admin_employee_list ORDER BY RoleID ASC, Name ASC")->fetchAll();
$customers = $pdo->query("SELECT * FROM vw_admin_customer_list ORDER BY Points DESC")->fetchAll();
$shops = $pdo->query("SELECT * FROM Shop")->fetchAll();
$roles = $pdo->query("SELECT * FROM UserRole")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-users-gear me-2"></i>User Management</h2>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addEmpModal">
        <i class="fa-solid fa-user-plus me-2"></i>New Employee
    </button>
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
                                $badge = match($e['RoleName']) {
                                    'Admin' => 'bg-danger',
                                    'Manager' => 'bg-warning text-dark',
                                    default => 'bg-info text-dark'
                                };
                                ?>
                                <span class="badge <?= $badge ?>"><?= h($e['RoleName']) ?></span>
                            </td>
                            <td><?= h($e['ShopName']) ?></td>
                            <td><?= $e['HireDate'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info me-1 edit-btn"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editEmpModal"
                                        data-id="<?= $e['EmployeeID'] ?>"
                                        data-name="<?= h($e['Name']) ?>"
                                        data-role="<?= $e['RoleID'] ?>"
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
                            <th>Joined</th>
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
                            <td><?= $c['Birthday'] ? 'DOB: '.h($c['Birthday']) : 'N/A' ?></td>
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
                            <select name="role_id" class="form-select bg-dark text-white border-secondary">
                                <?php foreach($roles as $r): ?>
                                    <option value="<?= $r['RoleID'] ?>"><?= h($r['RoleName']) ?></option>
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
                            <select name="role_id" id="edit_role" class="form-select bg-dark text-white border-secondary">
                                <?php foreach($roles as $r): ?>
                                    <option value="<?= $r['RoleID'] ?>"><?= h($r['RoleName']) ?></option>
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
    // JS Logic to populate Edit Modal
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