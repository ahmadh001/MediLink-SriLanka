<?php
/**
 * Admin Reports & Analytics Dashboard (Demonstrating Advanced SQL Aggregates)
 */

$pageTitle = 'Analytics & Database Reports';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

// Report 1: User Distribution by Role
$userDistQuery = "
    SELECT Role_Type, Account_Status, COUNT(*) AS count
    FROM `USER`
    GROUP BY Role_Type, Account_Status
    ORDER BY Role_Type ASC
";
$userDist = $db->query($userDistQuery)->fetchAll();

// Report 2: Subscription Revenue & Counts by Plan
$planStatsQuery = "
    SELECT sp.Plan_ID, sp.Plan_Name, sp.Target_Role, sp.Price,
           COUNT(us.User_Subscription_ID) AS total_subscriptions_sold,
           SUM(CASE WHEN us.Status = 'ACTIVE' AND CURRENT_DATE BETWEEN us.Start_Date AND us.End_Date THEN 1 ELSE 0 END) AS current_active_users,
           COALESCE(SUM(sp.Price), 0) AS total_revenue_lkr
    FROM `SUBSCRIPTION_PLAN` sp
    LEFT JOIN `USER_SUBSCRIPTION` us ON sp.Plan_ID = us.Plan_ID
    GROUP BY sp.Plan_ID
    ORDER BY total_revenue_lkr DESC
";
$planStats = $db->query($planStatsQuery)->fetchAll();

// Report 3: Appointment Status Breakdown
$apptStatsQuery = "
    SELECT Status, COUNT(*) AS count,
           ROUND((COUNT(*) / (SELECT COUNT(*) FROM `APPOINTMENT`)) * 100, 1) AS percentage
    FROM `APPOINTMENT`
    GROUP BY Status
    ORDER BY count DESC
";
$apptStats = $db->query($apptStatsQuery)->fetchAll();

// Report 4: Top Providers by Booking Volume
$topProvidersQuery = "
    SELECT p.Provider_ID, p.Business_Name, p.Provider_Type, p.City,
           COUNT(a.Appointment_ID) AS total_bookings,
           SUM(CASE WHEN a.Status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_count,
           SUM(CASE WHEN a.Status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled_count
    FROM `PROVIDER` p
    LEFT JOIN `SCHEDULED_SLOT` s ON p.Provider_ID = s.Provider_ID
    LEFT JOIN `APPOINTMENT` a ON s.Slot_ID = a.Slot_ID
    GROUP BY p.Provider_ID
    ORDER BY total_bookings DESC
    LIMIT 5
";
$topProviders = $db->query($topProvidersQuery)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-bar-chart-line-fill text-teal me-2"></i> System Reports & Analytical Intelligence</h2>
      <p class="text-muted small mb-0">Aggregate business intelligence computed via native MySQL analytical queries.</p>
    </div>
    <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
    </a>
  </div>

  <div class="row g-4 mb-4">
    <!-- Plan Revenue Breakdown -->
    <div class="col-lg-8">
      <div class="card card-custom p-4 h-100">
        <h5 class="fw-bold text-teal mb-3"><i class="bi bi-cash-stack me-2"></i> Subscription Revenue by Plan (LKR)</h5>
        <div class="table-responsive">
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Plan Name</th>
                <th>Role</th>
                <th>Fee</th>
                <th>Active Subscribers</th>
                <th>Total Sold</th>
                <th class="text-end">Total Gross Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($planStats as $ps): ?>
                <tr>
                  <td><strong><?= e($ps['Plan_Name']) ?></strong></td>
                  <td><span class="badge bg-light text-dark border"><?= e($ps['Target_Role']) ?></span></td>
                  <td><?= formatLKR($ps['Price']) ?></td>
                  <td><span class="badge bg-success-subtle text-success border border-success-subtle"><?= $ps['current_active_users'] ?> Active</span></td>
                  <td><?= $ps['total_subscriptions_sold'] ?> sales</td>
                  <td class="text-end fw-bold text-teal"><?= formatLKR($ps['total_revenue_lkr']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Appointment Status Breakdown -->
    <div class="col-lg-4">
      <div class="card card-custom p-4 h-100">
        <h5 class="fw-bold text-teal mb-3"><i class="bi bi-pie-chart-fill me-2"></i> Appointment Breakdown</h5>
        <?php if (empty($apptStats)): ?>
          <p class="text-muted small mb-0">No appointment records logged yet.</p>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($apptStats as $as): ?>
              <div>
                <div class="d-flex justify-content-between small fw-semibold mb-1">
                  <span><?= renderStatusBadge($as['Status']) ?></span>
                  <span><?= $as['count'] ?> (<?= $as['percentage'] ?>%)</span>
                </div>
                <div class="progress" style="height: 8px;">
                  <div class="progress-bar bg-teal" role="progressbar" style="width: <?= $as['percentage'] ?>%;"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Top Healthcare Providers -->
    <div class="col-lg-8">
      <div class="card card-custom p-4 h-100">
        <h5 class="fw-bold text-teal mb-3"><i class="bi bi-trophy-fill me-2"></i> Top Healthcare Providers by Booking Volume</h5>
        <div class="table-responsive">
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Provider Name</th>
                <th>Type & Location</th>
                <th>Total Bookings</th>
                <th>Completed</th>
                <th>Cancelled</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topProviders as $tp): ?>
                <tr>
                  <td><strong><?= e($tp['Business_Name']) ?></strong></td>
                  <td><small class="text-muted"><?= e($tp['Provider_Type']) ?> • <?= e($tp['City']) ?></small></td>
                  <td><span class="badge bg-light text-dark border"><?= $tp['total_bookings'] ?></span></td>
                  <td><span class="text-success fw-semibold"><?= $tp['completed_count'] ?></span></td>
                  <td><span class="text-danger fw-semibold"><?= $tp['cancelled_count'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- User Role Distribution -->
    <div class="col-lg-4">
      <div class="card card-custom p-4 h-100">
        <h5 class="fw-bold text-teal mb-3"><i class="bi bi-people-fill me-2"></i> User Registry Status</h5>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Role</th>
                <th>Status</th>
                <th class="text-end">Count</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($userDist as $ud): ?>
                <tr>
                  <td><span class="small fw-semibold"><?= e($ud['Role_Type']) ?></span></td>
                  <td><?= renderStatusBadge($ud['Account_Status']) ?></td>
                  <td class="text-end fw-bold"><?= $ud['count'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
