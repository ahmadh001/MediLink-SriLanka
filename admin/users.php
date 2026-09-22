<?php


$pageTitle = 'Manage System Users';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);
$currentAdmin = getCurrentUser();

$db = Database::getConnection();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    CSRF::check();

    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newStatus = ($_POST['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE';

    
    $targetRoleStmt = $db->prepare("SELECT Role_Type FROM `USER` WHERE User_ID = ?");
    $targetRoleStmt->execute([$targetUserId]);
    $targetRole = $targetRoleStmt->fetchColumn();

    if ($targetRole === ROLE_OWNER) {
        setFlash('danger', 'Owner accounts can only be managed by the Owner.');
    } elseif ($targetUserId === (int)$currentAdmin['user_id']) {
        setFlash('danger', 'You cannot suspend your own administrator account.');
    } else {
        $db->prepare("UPDATE `USER` SET Account_Status = ? WHERE User_ID = ?")->execute([$newStatus, $targetUserId]);
        setFlash('success', "User #{$targetUserId} status updated to {$newStatus}.");
    }
    redirect('admin/users.php');
}


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
    WHERE u.Role_Type <> 'OWNER'
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


$visibleTotal = count($users);
$visibleActive = 0;
$visibleSuspended = 0;
$visibleClients = 0;
$visibleProviders = 0;
$visibleAdmins = 0;
foreach ($users as $summaryUser) {
    if ($summaryUser['Account_Status'] === 'ACTIVE') $visibleActive++;
    if ($summaryUser['Account_Status'] === 'SUSPENDED') $visibleSuspended++;
    if ($summaryUser['Role_Type'] === 'CLIENT') $visibleClients++;
    if ($summaryUser['Role_Type'] === 'PROVIDER') $visibleProviders++;
    if ($summaryUser['Role_Type'] === 'SYSTEM_ADMIN') $visibleAdmins++;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 admin-users-v2">
  <div class="admin-users-head mb-4">
    <div>
      <div class="section-kicker mb-2"><i class="bi bi-people"></i> Account administration</div>
      <h2 class="fw-bold mb-1">System users</h2>
      <p class="text-muted mb-0">Search, review and control access for clients, healthcare providers and administrators.</p>
    </div>
    <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary">
      <i class="bi bi-grid me-1"></i> Dashboard
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg"><div class="user-stat"><span class="user-stat-icon"><i class="bi bi-people"></i></span><div><strong><?= $visibleTotal ?></strong><small>Results</small></div></div></div>
    <div class="col-6 col-lg"><div class="user-stat"><span class="user-stat-icon"><i class="bi bi-person-check"></i></span><div><strong><?= $visibleActive ?></strong><small>Active</small></div></div></div>
    <div class="col-6 col-lg"><div class="user-stat"><span class="user-stat-icon"><i class="bi bi-person-heart"></i></span><div><strong><?= $visibleClients ?></strong><small>Clients</small></div></div></div>
    <div class="col-6 col-lg"><div class="user-stat"><span class="user-stat-icon"><i class="bi bi-hospital"></i></span><div><strong><?= $visibleProviders ?></strong><small>Providers</small></div></div></div>
    <div class="col-6 col-lg"><div class="user-stat"><span class="user-stat-icon"><i class="bi bi-shield-lock"></i></span><div><strong><?= $visibleAdmins ?></strong><small>Admins</small></div></div></div>
  </div>

  <div class="card card-custom admin-filter-card mb-4">
    <form method="GET" action="<?= url('admin/users.php') ?>" class="row g-3 align-items-end">
      <div class="col-lg-5">
        <label class="form-label small fw-semibold">Search users</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="search" name="q" class="form-control" placeholder="Name, email, phone or provider..." value="<?= e($search) ?>">
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label small fw-semibold">Role</label>
        <select name="role" class="form-select">
          <option value="ALL" <?= $filterRole === 'ALL' ? 'selected' : '' ?>>All roles</option>
          <option value="CLIENT" <?= $filterRole === 'CLIENT' ? 'selected' : '' ?>>Client</option>
          <option value="PROVIDER" <?= $filterRole === 'PROVIDER' ? 'selected' : '' ?>>Provider</option>
          <option value="SYSTEM_ADMIN" <?= $filterRole === 'SYSTEM_ADMIN' ? 'selected' : '' ?>>System admin</option>
        </select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small fw-semibold">Status</label>
        <select name="status" class="form-select">
          <option value="ALL" <?= $filterStatus === 'ALL' ? 'selected' : '' ?>>All statuses</option>
          <option value="ACTIVE" <?= $filterStatus === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
          <option value="SUSPENDED" <?= $filterStatus === 'SUSPENDED' ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>
      <div class="col-lg-2 d-flex gap-2">
        <button type="submit" class="btn btn-teal flex-grow-1"><i class="bi bi-funnel me-1"></i> Apply</button>
        <?php if ($search !== '' || $filterRole !== 'ALL' || $filterStatus !== 'ALL'): ?>
          <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-secondary" title="Clear filters"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
    <div>
      <h5 class="fw-bold mb-0">User directory</h5>
      <small class="text-muted"><?= $visibleTotal ?> account<?= $visibleTotal === 1 ? '' : 's' ?> shown<?= $visibleSuspended ? ' · ' . $visibleSuspended . ' suspended' : '' ?></small>
    </div>
  </div>

  <?php if (empty($users)): ?>
    <div class="card card-custom admin-empty-state text-center">
      <span class="empty-icon"><i class="bi bi-person-x"></i></span>
      <h5 class="fw-bold mt-3 mb-1">No matching users</h5>
      <p class="text-muted mb-3">Try a different search term or clear the current filters.</p>
      <div><a href="<?= url('admin/users.php') ?>" class="btn btn-outline-teal">Clear filters</a></div>
    </div>
  <?php else: ?>
    <div class="admin-user-list">
      <?php foreach ($users as $u): ?>
        <?php
          $isCurrentAdmin = (int)$u['User_ID'] === (int)$currentAdmin['user_id'];
          $isProvider = $u['Role_Type'] === 'PROVIDER';
          $roleLabel = $u['Role_Type'] === 'SYSTEM_ADMIN' ? 'System Admin' : ($isProvider ? 'Provider' : 'Client');
          $roleIcon = $u['Role_Type'] === 'SYSTEM_ADMIN' ? 'bi-shield-lock' : ($isProvider ? 'bi-hospital' : 'bi-person-heart');
          $location = $u['Client_City'] ?: ($u['Provider_City'] ?: 'Sri Lanka');
        ?>
        <article class="card card-custom admin-user-card">
          <div class="admin-user-main">
            <div class="user-avatar" aria-hidden="true"><?= e(strtoupper(substr($u['First_Name'] ?: 'U', 0, 1) . substr($u['Last_Name'] ?: '', 0, 1))) ?></div>
            <div class="user-identity min-w-0">
              <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                <h6 class="fw-bold mb-0 text-truncate"><?= e(trim($u['First_Name'] . ' ' . $u['Last_Name'])) ?></h6>
                <span class="role-chip"><i class="bi <?= $roleIcon ?>"></i> <?= e($roleLabel) ?></span>
                <?php if ($isCurrentAdmin): ?><span class="badge bg-light text-dark border">You</span><?php endif; ?>
              </div>
              <div class="user-contact text-muted small">
                <span><i class="bi bi-envelope"></i> <?= e($u['Email']) ?></span>
                <?php if (!empty($u['Phone'])): ?><span><i class="bi bi-telephone"></i> <?= e($u['Phone']) ?></span><?php endif; ?>
              </div>
              <?php if ($isProvider && !empty($u['Business_Name'])): ?>
                <div class="small mt-2"><i class="bi bi-building me-1 text-teal"></i><strong><?= e($u['Business_Name']) ?></strong><?php if (!empty($u['Provider_Type'])): ?> <span class="text-muted">· <?= e(ucwords(strtolower(str_replace('_', ' ', $u['Provider_Type'])))) ?></span><?php endif; ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="admin-user-meta">
            <div class="meta-item"><span>Location</span><strong><i class="bi bi-geo-alt"></i> <?= e($location) ?></strong></div>
            <div class="meta-item"><span>Subscription</span><strong><?php if ($u['Role_Type'] === 'SYSTEM_ADMIN'): ?><i class="bi bi-shield-check"></i> Admin access<?php elseif ($u['has_active_sub'] > 0): ?><i class="bi bi-check-circle text-success"></i> Active<?php else: ?><i class="bi bi-dash-circle text-muted"></i> None / expired<?php endif; ?></strong></div>
            <div class="meta-item"><span>Registered</span><strong><i class="bi bi-calendar3"></i> <?= formatDate($u['Created_At']) ?></strong></div>
          </div>

          <div class="admin-user-actions">
            <div class="mb-2"><?= renderStatusBadge($u['Account_Status']) ?></div>
            <code class="user-id">#<?= (int)$u['User_ID'] ?></code>
            <?php if (!$isCurrentAdmin): ?>
              <form method="POST" action="<?= url('admin/users.php') ?>" data-confirm="<?= $u['Account_Status'] === 'ACTIVE' ? 'Suspend' : 'Activate' ?> <?= e(trim($u['First_Name'] . ' ' . $u['Last_Name'])) ?>?">
                <?= CSRF::inputField() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="user_id" value="<?= (int)$u['User_ID'] ?>">
                <input type="hidden" name="status" value="<?= e($u['Account_Status']) ?>">
                <button type="submit" class="btn btn-sm <?= $u['Account_Status'] === 'ACTIVE' ? 'btn-outline-danger' : 'btn-outline-success' ?> w-100">
                  <i class="bi <?= $u['Account_Status'] === 'ACTIVE' ? 'bi-person-slash' : 'bi-person-check' ?> me-1"></i><?= $u['Account_Status'] === 'ACTIVE' ? 'Suspend' : 'Activate' ?>
                </button>
              </form>
            <?php else: ?>
              <span class="small text-muted"><i class="bi bi-lock me-1"></i>Protected session</span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<style>
.admin-users-v2{max-width:1280px}.admin-users-head{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem}.section-kicker{display:inline-flex;align-items:center;gap:.45rem;color:var(--primary-teal);font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em}.user-stat{height:100%;display:flex;align-items:center;gap:.8rem;padding:1rem;background:#fff;border:1px solid var(--border-color,#e7ecef);border-radius:16px}.user-stat-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:rgba(8,127,120,.09);color:var(--primary-teal);font-size:1.05rem}.user-stat strong{display:block;font-size:1.25rem;line-height:1.1}.user-stat small{display:block;color:#6c757d;margin-top:.2rem}.admin-filter-card{padding:1.1rem}.admin-filter-card .input-group-text{background:#fff;border-right:0}.admin-filter-card .input-group .form-control{border-left:0}.admin-user-list{display:grid;gap:.8rem}.admin-user-card{padding:1rem 1.1rem;display:grid;grid-template-columns:minmax(280px,1.35fr) minmax(360px,1fr) 150px;gap:1rem;align-items:center}.admin-user-main{display:flex;align-items:center;gap:.9rem;min-width:0}.user-avatar{flex:0 0 48px;width:48px;height:48px;border-radius:15px;display:grid;place-items:center;background:rgba(8,127,120,.1);color:var(--primary-teal);font-weight:800}.min-w-0{min-width:0}.role-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.24rem .52rem;border-radius:999px;background:#f4f7f7;color:#536063;font-size:.72rem;font-weight:700}.user-contact{display:flex;flex-wrap:wrap;gap:.4rem 1rem}.user-contact span{display:inline-flex;align-items:center;gap:.35rem}.admin-user-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:.55rem}.meta-item{padding:.65rem .7rem;border-radius:12px;background:#f8fafb;min-width:0}.meta-item span{display:block;color:#7a858a;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.22rem}.meta-item strong{display:block;font-size:.78rem;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.admin-user-actions{text-align:right}.admin-user-actions form{margin-top:.55rem}.user-id{display:block;color:#7b8589;font-size:.72rem}.admin-empty-state{padding:3.5rem 1.5rem}.empty-icon{width:60px;height:60px;margin:auto;border-radius:18px;display:grid;place-items:center;background:#f3f6f7;color:#879297;font-size:1.45rem}
@media(max-width:991.98px){.admin-user-card{grid-template-columns:1fr}.admin-user-meta{grid-template-columns:repeat(3,1fr)}.admin-user-actions{text-align:left;display:flex;align-items:center;gap:.7rem;flex-wrap:wrap}.admin-user-actions .mb-2{margin-bottom:0!important}.admin-user-actions form{margin-top:0;margin-left:auto}.admin-user-actions form .btn{width:auto!important}.user-id{display:inline}}
@media(max-width:575.98px){.admin-users-head{align-items:flex-start;flex-direction:column}.admin-user-card{padding:1rem}.admin-user-main{align-items:flex-start}.admin-user-meta{grid-template-columns:1fr}.user-contact{flex-direction:column;gap:.25rem}.admin-user-actions form{width:100%;margin-left:0}.admin-user-actions form .btn{width:100%!important}}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
