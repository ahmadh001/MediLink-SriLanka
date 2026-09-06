<?php
/**
 * Admin User Management (List, Search, Filter & Suspend/Activate)
 */

$pageTitle = 'Manage System Users';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);
$currentAdmin = getCurrentUser();

$db = Database::getConnection();

// Handle Status Toggle (Suspend / Activate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    CSRF::check();

    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newStatus = ($_POST['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE';

    // Prevent self-suspension of current logged-in admin
    if ($targetUserId === (int)$currentAdmin['user_id']) {
        setFlash('danger', 'You cannot suspend your own administrator account.');
    } else {
        $db->prepare("UPDATE `USER` SET Account_Status = ? WHERE User_ID = ?")->execute([$newStatus, $targetUserId]);
        setFlash('success', "User #{$targetUserId} status updated to {$newStatus}.");
    }
    redirect('admin/users.php');
}

// Search and Filter Params
$search = trim($_GET['q'] ?? '');
$filterRole = $_GET['role'] ?? 'ALL';
$filterStatus = $_GET['status'] ?? 'ALL';

$query = "
    SELECT u.*,
           c.Client_ID, c.City AS Client_City,
           p.Provider_ID, p.Provider_Type, p.Business_Name, p.City AS Provider_City, p.Verification_Status,
           d.Medical_License_No, hc.Registration_No,
           (SELECT COUNT(*) FROM `USER_SUBSCRIPTION` us WHERE us.User_ID = u.User_ID AND us.Status = 'ACTIVE' AND CURRENT_DATE BETWEEN us.Start_Date AND us.End_Date) AS has_active_sub
    FROM `USER` u
    LEFT JOIN `CLIENT` c ON u.User_ID = c.User_ID
    LEFT JOIN `PROVIDER` p ON u.User_ID = p.User_ID
    LEFT JOIN `DOCTOR` d ON p.Provider_ID = d.Provider_ID
    LEFT JOIN `HEALTHCARE_CENTRE` hc ON p.Provider_ID = hc.Provider_ID
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR u.Email LIKE ? OR u.Phone LIKE ? OR p.Business_Name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($filterRole !== 'ALL') {
    $query .= " AND u.Role_Type = ?";
    $params[] = $filterRole;
}

if ($filterStatus !== 'ALL') {
    $query .= " AND u.Account_Status = ?";
    $params[] = $filterStatus;
}

$query .= " ORDER BY u.Created_At DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-people-fill text-teal me-2"></i> System User Management</h2>
      <p class="text-muted small mb-0">Manage all registered patients, medical providers, and system administrator accounts.</p>
    </div>
    <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
    </a>
  </div>

  <!-- Search & Filter Card -->
  <div class="card card-custom p-4 mb-4">
    <form method="GET" action="<?= url('admin/users.php') ?>" class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label small fw-semibold">Search by Name, Email, Phone, or Business</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Search query..." value="<?= e($search) ?>">
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-semibold">User Role</label>
        <select name="role" class="form-select">
          <option value="ALL" <?= $filterRole === 'ALL' ? 'selected' : '' ?>>All Roles</option>
          <option value="CLIENT" <?= $filterRole === 'CLIENT' ? 'selected' : '' ?>>Client / Patient</option>
          <option value="PROVIDER" <?= $filterRole === 'PROVIDER' ? 'selected' : '' ?>>Healthcare Provider</option>
          <option value="SYSTEM_ADMIN" <?= $filterRole === 'SYSTEM_ADMIN' ? 'selected' : '' ?>>System Admin</option>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-semibold">Account Status</label>
        <select name="status" class="form-select">
          <option value="ALL" <?= $filterStatus === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
          <option value="ACTIVE" <?= $filterStatus === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
          <option value="SUSPENDED" <?= $filterStatus === 'SUSPENDED' ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-teal w-100 py-2">Filter</button>
        <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-secondary py-2">Reset</a>
      </div>
    </form>
  </div>

  <!-- Users Table Card -->
  <div class="card card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">Total Users (<?= count($users) ?>)</h5>
    </div>

    <?php if (empty($users)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
        <p class="mb-0">No users match the search criteria.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>User Name & Email</th>
              <th>Role & Details</th>
              <th>Location</th>
              <th>Subscription</th>
              <th>Status</th>
              <th>Registered</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><code>#<?= $u['User_ID'] ?></code></td>
                <td>
                  <div class="fw-bold"><?= e($u['First_Name'] . ' ' . $u['Last_Name']) ?></div>
                  <small class="text-muted"><?= e($u['Email']) ?> • <?= e($u['Phone']) ?></small>
                </td>
                <td>
                  <span class="badge <?= $u['Role_Type'] === 'SYSTEM_ADMIN' ? 'bg-danger' : ($u['Role_Type'] === 'PROVIDER' ? 'bg-primary' : 'bg-teal') ?>">
                    <?= e($u['Role_Type']) ?>
                  </span>
                  <?php if ($u['Role_Type'] === 'PROVIDER'): ?>
                    <small class="d-block text-muted mt-1">
                      <?= e($u['Business_Name']) ?> (<?= $u['Provider_Type'] ?>)
                    </small>
                  <?php endif; ?>
                </td>
                <td>
                  <small><?= e($u['Client_City'] ?: ($u['Provider_City'] ?: 'Sri Lanka')) ?></small>
                </td>
                <td>
                  <?php if ($u['Role_Type'] === 'SYSTEM_ADMIN'): ?>
                    <span class="badge bg-light text-dark border">Superuser</span>
                  <?php elseif ($u['has_active_sub'] > 0): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle"></i> Active</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border">None / Expired</span>
                  <?php endif; ?>
                </td>
                <td><?= renderStatusBadge($u['Account_Status']) ?></td>
                <td><small class="text-muted"><?= formatDate($u['Created_At']) ?></small></td>
                <td class="text-end">
                  <?php if ($u['User_ID'] !== (int)$currentAdmin['user_id']): ?>
                    <form method="POST" action="<?= url('admin/users.php') ?>" class="d-inline" onsubmit="return confirm('Toggle status for <?= e($u['First_Name']) ?>?');">
                      <?= CSRF::inputField() ?>
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= $u['User_ID'] ?>">
                      <input type="hidden" name="status" value="<?= $u['Account_Status'] ?>">
                      <button type="submit" class="btn btn-sm <?= $u['Account_Status'] === 'ACTIVE' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                        <?= $u['Account_Status'] === 'ACTIVE' ? 'Suspend' : 'Activate' ?>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted small">Current Admin</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
