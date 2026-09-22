<?php

$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole([ROLE_ADMIN, ROLE_OWNER]);
$db = Database::getConnection();

$userDist = $db->query("SELECT Role_Type, Account_Status, COUNT(*) AS count FROM `USER` GROUP BY Role_Type, Account_Status ORDER BY Role_Type, Account_Status")->fetchAll();

$planStats = $db->query("
    SELECT sp.Plan_ID, sp.Plan_Name, sp.Target_Role, sp.Price,
           COUNT(us.User_Subscription_ID) AS total_subscriptions_sold,
           SUM(CASE WHEN us.Status='ACTIVE' AND CURRENT_DATE BETWEEN us.Start_Date AND us.End_Date THEN 1 ELSE 0 END) AS current_active_users,
           COALESCE(SUM(CASE WHEN us.User_Subscription_ID IS NOT NULL THEN sp.Price ELSE 0 END),0) AS total_revenue_lkr
    FROM `SUBSCRIPTION_PLAN` sp
    LEFT JOIN `USER_SUBSCRIPTION` us ON sp.Plan_ID=us.Plan_ID
    GROUP BY sp.Plan_ID, sp.Plan_Name, sp.Target_Role, sp.Price
    ORDER BY total_revenue_lkr DESC, sp.Plan_Name
")->fetchAll();

$apptStats = $db->query("
    SELECT Status, COUNT(*) AS count,
           ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM `APPOINTMENT`),0),1) AS percentage
    FROM `APPOINTMENT`
    GROUP BY Status
    ORDER BY count DESC
")->fetchAll();

$topProviders = $db->query("
    SELECT p.Provider_ID, p.Business_Name, p.Provider_Type, p.City,
           COUNT(a.Appointment_ID) AS total_bookings,
           SUM(CASE WHEN a.Status='COMPLETED' THEN 1 ELSE 0 END) AS completed_count,
           SUM(CASE WHEN a.Status='CANCELLED' THEN 1 ELSE 0 END) AS cancelled_count
    FROM `PROVIDER` p
    LEFT JOIN `SCHEDULED_SLOT` s ON p.Provider_ID=s.Provider_ID
    LEFT JOIN `APPOINTMENT` a ON s.Slot_ID=a.Slot_ID
    GROUP BY p.Provider_ID, p.Business_Name, p.Provider_Type, p.City
    ORDER BY total_bookings DESC, p.Business_Name
    LIMIT 5
")->fetchAll();

$summary = $db->query("
    SELECT
      (SELECT COUNT(*) FROM `USER`) AS users,
      (SELECT COUNT(*) FROM `PROVIDER`) AS providers,
      (SELECT COUNT(*) FROM `APPOINTMENT`) AS appointments,
      (SELECT COUNT(*) FROM `USER_SUBSCRIPTION` WHERE Status='ACTIVE' AND CURRENT_DATE BETWEEN Start_Date AND End_Date) AS active_subscriptions
")->fetch();
$totalRevenue = array_sum(array_map(fn($p) => (float)$p['total_revenue_lkr'], $planStats));
$completed = 0;
foreach ($apptStats as $row) if ($row['Status'] === 'COMPLETED') $completed = (int)$row['count'];
$completionRate = ((int)$summary['appointments'] > 0) ? round($completed * 100 / (int)$summary['appointments'], 1) : 0;
$maxPlanRevenue = max(1, ...array_map(fn($p) => (float)$p['total_revenue_lkr'], $planStats ?: [['total_revenue_lkr'=>0]]));

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.reports-shell{--report-border:#e5eceb;--report-soft:#f6faf9}.reports-hero{background:linear-gradient(135deg,#f7fbfa 0%,#fff 68%);border:1px solid var(--report-border);border-radius:22px;padding:1.4rem}.report-kpi{border:1px solid var(--report-border);border-radius:18px;background:#fff;padding:1rem;height:100%}.report-kpi .icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:var(--report-soft);color:var(--primary-teal,#087f78);font-size:1.15rem}.report-kpi .value{font-size:1.55rem;line-height:1.1;font-weight:750}.report-panel{border:1px solid var(--report-border);border-radius:20px;background:#fff;padding:1.25rem;height:100%}.report-panel-title{font-size:1rem;font-weight:700;margin:0}.metric-bar{height:8px;background:#edf2f1;border-radius:999px;overflow:hidden}.metric-bar>span{display:block;height:100%;background:var(--primary-teal,#087f78);border-radius:inherit}.plan-row{padding:.9rem 0;border-bottom:1px solid #eef2f1}.plan-row:last-child{border-bottom:0;padding-bottom:0}.provider-rank{display:flex;gap:.85rem;padding:.9rem 0;border-bottom:1px solid #eef2f1}.provider-rank:last-child{border-bottom:0}.rank-no{width:34px;height:34px;border-radius:10px;background:var(--report-soft);display:grid;place-items:center;font-weight:700;color:var(--primary-teal,#087f78);flex:0 0 auto}.registry-row{display:grid;grid-template-columns:1fr auto auto;gap:.65rem;align-items:center;padding:.7rem 0;border-bottom:1px solid #eef2f1}.registry-row:last-child{border-bottom:0}.report-note{background:var(--report-soft);border:1px solid var(--report-border);border-radius:14px;padding:.8rem 1rem}.reports-shell .text-teal{color:var(--primary-teal,#087f78)!important}@media(max-width:767.98px){.reports-hero{padding:1.1rem}.report-panel{padding:1rem}.registry-row{grid-template-columns:1fr auto}.registry-row .status-cell{grid-column:1/2}}
</style>

<div class="container py-4 reports-shell">
  <section class="reports-hero mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div>
        <div class="small text-teal fw-semibold mb-2"><i class="bi bi-graph-up-arrow me-1"></i> ADMIN ANALYTICS</div>
        <h2 class="fw-bold mb-1">Reports & Analytics</h2>
        <p class="text-muted mb-0">A read-only overview of users, appointments, subscriptions and provider activity from the live database.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('admin/database.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-database me-1"></i> Database</a>
        <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-grid me-1"></i> Dashboard</a>
      </div>
    </div>
  </section>

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="report-kpi"><div class="d-flex justify-content-between gap-2"><div><div class="text-muted small mb-2">Registered users</div><div class="value"><?= number_format((int)$summary['users']) ?></div></div><div class="icon"><i class="bi bi-people"></i></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="report-kpi"><div class="d-flex justify-content-between gap-2"><div><div class="text-muted small mb-2">Appointments</div><div class="value"><?= number_format((int)$summary['appointments']) ?></div><div class="small text-muted mt-1"><?= $completionRate ?>% completed</div></div><div class="icon"><i class="bi bi-calendar2-check"></i></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="report-kpi"><div class="d-flex justify-content-between gap-2"><div><div class="text-muted small mb-2">Active subscriptions</div><div class="value"><?= number_format((int)$summary['active_subscriptions']) ?></div></div><div class="icon"><i class="bi bi-credit-card"></i></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="report-kpi"><div class="d-flex justify-content-between gap-2"><div><div class="text-muted small mb-2">Recorded revenue</div><div class="value"><?= formatLKR($totalRevenue) ?></div></div><div class="icon"><i class="bi bi-cash-stack"></i></div></div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <section class="report-panel">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2"><div><h3 class="report-panel-title">Subscription performance</h3><p class="text-muted small mb-0">Recorded sales and revenue grouped by plan.</p></div><i class="bi bi-bar-chart text-teal fs-5"></i></div>
        <?php if (!$planStats): ?><div class="text-muted small py-4 text-center">No subscription plans available.</div><?php else: ?>
          <?php foreach ($planStats as $ps): $pct=((float)$ps['total_revenue_lkr']/$maxPlanRevenue)*100; ?>
            <div class="plan-row">
              <div class="d-flex justify-content-between gap-3 mb-2"><div><div class="fw-semibold"><?= e($ps['Plan_Name']) ?> <span class="badge bg-light text-dark border ms-1"><?= e($ps['Target_Role']) ?></span></div><div class="small text-muted"><?= (int)$ps['total_subscriptions_sold'] ?> sold · <?= (int)$ps['current_active_users'] ?> active · <?= formatLKR($ps['Price']) ?> / plan</div></div><div class="fw-bold text-teal text-nowrap"><?= formatLKR($ps['total_revenue_lkr']) ?></div></div>
              <div class="metric-bar"><span style="width:<?= max(0,min(100,$pct)) ?>%"></span></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </div>
    <div class="col-lg-5">
      <section class="report-panel">
        <div class="d-flex justify-content-between align-items-start mb-3"><div><h3 class="report-panel-title">Appointment status</h3><p class="text-muted small mb-0">Current status distribution.</p></div><i class="bi bi-pie-chart text-teal fs-5"></i></div>
        <?php if (!$apptStats): ?><div class="text-muted small py-4 text-center">No appointment records yet.</div><?php else: ?>
          <div class="d-flex flex-column gap-3">
          <?php foreach ($apptStats as $as): $pct=(float)($as['percentage'] ?? 0); ?>
            <div><div class="d-flex justify-content-between align-items-center gap-2 small mb-2"><span><?= renderStatusBadge($as['Status']) ?></span><span class="fw-semibold"><?= (int)$as['count'] ?> <span class="text-muted fw-normal">(<?= number_format($pct,1) ?>%)</span></span></div><div class="metric-bar"><span style="width:<?= max(0,min(100,$pct)) ?>%"></span></div></div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <section class="report-panel">
        <div class="d-flex justify-content-between align-items-start mb-2"><div><h3 class="report-panel-title">Provider booking activity</h3><p class="text-muted small mb-0">Top five providers by appointment volume.</p></div><i class="bi bi-building-check text-teal fs-5"></i></div>
        <?php if (!$topProviders): ?><div class="text-muted small py-4 text-center">No provider activity available.</div><?php else: ?>
          <?php foreach ($topProviders as $i=>$tp): ?>
            <div class="provider-rank"><div class="rank-no"><?= $i+1 ?></div><div class="flex-grow-1 min-w-0"><div class="d-flex justify-content-between gap-3"><div><div class="fw-semibold"><?= e($tp['Business_Name']) ?></div><div class="small text-muted"><?= e($tp['Provider_Type']) ?><?= $tp['City'] ? ' · '.e($tp['City']) : '' ?></div></div><div class="text-end"><div class="fw-bold"><?= (int)$tp['total_bookings'] ?></div><div class="small text-muted">bookings</div></div></div><div class="small mt-2"><span class="text-success me-3"><i class="bi bi-check-circle me-1"></i><?= (int)$tp['completed_count'] ?> completed</span><span class="text-danger"><i class="bi bi-x-circle me-1"></i><?= (int)$tp['cancelled_count'] ?> cancelled</span></div></div></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </div>
    <div class="col-lg-5">
      <section class="report-panel">
        <div class="d-flex justify-content-between align-items-start mb-2"><div><h3 class="report-panel-title">User registry</h3><p class="text-muted small mb-0">Accounts grouped by role and status.</p></div><i class="bi bi-person-vcard text-teal fs-5"></i></div>
        <?php if (!$userDist): ?><div class="text-muted small py-4 text-center">No user records available.</div><?php else: ?>
          <?php foreach ($userDist as $ud): ?><div class="registry-row"><div><div class="fw-semibold small"><?= e($ud['Role_Type']) ?></div></div><div class="status-cell"><?= renderStatusBadge($ud['Account_Status']) ?></div><div class="fw-bold text-end"><?= (int)$ud['count'] ?></div></div><?php endforeach; ?>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <div class="report-note small text-muted mt-4"><i class="bi bi-info-circle me-1 text-teal"></i> Revenue shown here is derived from recorded subscription plan purchases in the project database. This page is analytical/read-only and does not represent an external payment gateway settlement report.</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
