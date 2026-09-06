<?php
/**
 * Admin Subscriptions Ledger & Audit History
 */

$pageTitle = 'Subscription Audit & Revenue Ledger';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

// Fetch summary metrics
$metricStmt = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM `USER_SUBSCRIPTION`) AS total_purchased,
        (SELECT COUNT(*) FROM `USER_SUBSCRIPTION` WHERE Status = 'ACTIVE' AND CURRENT_DATE BETWEEN Start_Date AND End_Date) AS current_active,
        (SELECT COUNT(*) FROM `USER_SUBSCRIPTION` WHERE Status = 'EXPIRED' OR End_Date < CURRENT_DATE) AS expired_count,
        (SELECT COALESCE(SUM(sp.Price), 0) FROM `USER_SUBSCRIPTION` us JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID) AS total_revenue
");
$metrics = $metricStmt->fetch();

// Filter
$statusFilter = $_GET['status'] ?? 'ALL';

$query = "
    SELECT us.*, 
           sp.Plan_Name, sp.Price, sp.Target_Role, sp.Max_Book_per_Month, sp.Search_Radius_KM,
           u.First_Name, u.Last_Name, u.Email, u.Role_Type,
           p.Business_Name
    FROM `USER_SUBSCRIPTION` us
    JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID
    JOIN `USER` u ON us.User_ID = u.User_ID
    LEFT JOIN `PROVIDER` p ON u.User_ID = p.User_ID
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'ALL') {
    $query .= " AND us.Status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY us.Created_At DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-credit-card-fill text-teal me-2"></i> Subscription Ledger & Audit</h2>
      <p class="text-muted small mb-0">System-wide transaction history and recurring membership audit records.</p>
    </div>
    <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
    </a>
  </div>

  <!-- Metric Badges -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card card-custom p-3">
        <small class="text-muted fw-semibold">Total Revenue (LKR)</small>
        <div class="stat-value text-teal"><?= formatLKR($metrics['total_revenue']) ?></div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-custom p-3 card-stat stat-success">
        <small class="text-muted fw-semibold">Active Subscriptions</small>
        <div class="stat-value text-success"><?= $metrics['current_active'] ?></div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-custom p-3 card-stat stat-warning">
        <small class="text-muted fw-semibold">Expired Subscriptions</small>
        <div class="stat-value text-warning"><?= $metrics['expired_count'] ?></div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card card-custom p-3 card-stat">
        <small class="text-muted fw-semibold">All Time Purchases</small>
        <div class="stat-value text-dark"><?= $metrics['total_purchased'] ?></div>
      </div>
    </div>
  </div>

  <!-- Table Card -->
  <div class="card card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">Subscription Records (<?= count($subscriptions) ?>)</h5>
      
      <div class="d-flex gap-2">
        <a href="<?= url('admin/subscriptions.php?status=ALL') ?>" class="btn btn-sm <?= $statusFilter === 'ALL' ? 'btn-teal' : 'btn-outline-secondary' ?>">All</a>
        <a href="<?= url('admin/subscriptions.php?status=ACTIVE') ?>" class="btn btn-sm <?= $statusFilter === 'ACTIVE' ? 'btn-teal' : 'btn-outline-secondary' ?>">Active</a>
        <a href="<?= url('admin/subscriptions.php?status=EXPIRED') ?>" class="btn btn-sm <?= $statusFilter === 'EXPIRED' ? 'btn-teal' : 'btn-outline-secondary' ?>">Expired</a>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Reference No</th>
            <th>Subscriber / Business</th>
            <th>Plan & Role</th>
            <th>Price</th>
            <th>Active Period</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subscriptions as $s): ?>
            <tr>
              <td><code class="fw-bold text-dark"><?= e($s['Payment_Reference_No']) ?></code></td>
              <td>
                <div class="fw-bold"><?= e($s['First_Name'] . ' ' . $s['Last_Name']) ?></div>
                <small class="text-muted"><?= e($s['Email']) ?><?= $s['Business_Name'] ? ' • ' . e($s['Business_Name']) : '' ?></small>
              </td>
              <td>
                <div class="fw-semibold text-teal"><?= e($s['Plan_Name']) ?></div>
                <small class="badge bg-light text-dark border"><?= e($s['Target_Role']) ?></small>
              </td>
              <td><strong><?= formatLKR($s['Price']) ?></strong></td>
              <td><small><?= formatDate($s['Start_Date']) ?> – <?= formatDate($s['End_Date']) ?></small></td>
              <td><?= renderStatusBadge($s['Status']) ?></td>
              <td><small class="text-muted"><?= formatDateTime($s['Created_At']) ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
