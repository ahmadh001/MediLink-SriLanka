<?php


$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();


$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM `USER`) AS total_users,
        (SELECT COUNT(*) FROM `CLIENT`) AS total_clients,
        (SELECT COUNT(*) FROM `PROVIDER` WHERE Provider_Type = 'DOCTOR') AS total_doctors,
        (SELECT COUNT(*) FROM `PROVIDER` WHERE Provider_Type = 'HEALTHCARE_CENTRE') AS total_centres,
        (SELECT COUNT(*) FROM `PROVIDER` WHERE Verification_Status = 'PENDING') AS pending_verifications,
        (SELECT COUNT(*) FROM `USER_SUBSCRIPTION` WHERE Status = 'ACTIVE' AND CURRENT_DATE BETWEEN Start_Date AND End_Date) AS active_subscriptions,
        (SELECT COALESCE(SUM(sp.Price), 0) FROM `USER_SUBSCRIPTION` us JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID) AS total_subscription_revenue,
        (SELECT COUNT(*) FROM `APPOINTMENT`) AS total_appointments,
        (SELECT COUNT(*) FROM `APPOINTMENT` WHERE Status = 'COMPLETED') AS completed_appointments,
        (SELECT COUNT(*) FROM `APPOINTMENT` WHERE Status = 'CANCELLED') AS cancelled_appointments,
        (SELECT COUNT(*) FROM `APPOINTMENT` WHERE MONTH(Booking_DateTime) = MONTH(CURRENT_DATE()) AND YEAR(Booking_DateTime) = YEAR(CURRENT_DATE())) AS month_appointments
";
$stats = $db->query($statsQuery)->fetch();


$pendingProviders = $db->query("
    SELECT p.Provider_ID, p.Provider_Type, p.Business_Name, p.City, p.Contact_Number, p.Created_At,
           d.Medical_License_No, hc.Registration_No,
           u.First_Name, u.Last_Name, u.Email
    FROM `PROVIDER` p
    JOIN `USER` u ON p.User_ID = u.User_ID
    LEFT JOIN `DOCTOR` d ON p.Provider_ID = d.Provider_ID
    LEFT JOIN `HEALTHCARE_CENTRE` hc ON p.Provider_ID = hc.Provider_ID
    WHERE p.Verification_Status = 'PENDING'
    ORDER BY p.Created_At DESC
    LIMIT 5
")->fetchAll();



$revenueTrend = $db->query("
    SELECT DATE(us.Start_Date) AS revenue_date, COALESCE(SUM(sp.Price), 0) AS revenue
    FROM `USER_SUBSCRIPTION` us
    JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID
    WHERE us.Start_Date >= DATE_SUB(CURRENT_DATE(), INTERVAL 29 DAY)
    GROUP BY DATE(us.Start_Date)
    ORDER BY revenue_date ASC
")->fetchAll();
$revenueMax = 0;
foreach ($revenueTrend as $point) {
    $revenueMax = max($revenueMax, (float)$point['revenue']);
}

$recentAppointments = $db->query("
    SELECT a.Appointment_ID, a.Booking_DateTime, a.Status,
           s.Slot_Date, s.Start_Time,
           pu.First_Name AS Patient_First, pu.Last_Name AS Patient_Last,
           p.Business_Name, p.City
    FROM `APPOINTMENT` a
    JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
    JOIN `PROVIDER` p ON s.Provider_ID = p.Provider_ID
    JOIN `CLIENT` c ON a.Client_ID = c.Client_ID
    JOIN `USER` pu ON c.User_ID = pu.User_ID
    ORDER BY a.Booking_DateTime DESC
    LIMIT 5
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.admin-v2 .admin-kicker{font-size:.78rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary-teal,#087f78)}
.admin-v2 .admin-hero{border:1px solid #e8eeed;border-radius:22px;background:linear-gradient(135deg,#fff 0%,#f7fbfa 100%);padding:1.35rem}
.admin-v2 .metric-card,.admin-v2 .panel-card,.admin-v2 .admin-link{border:1px solid #e7eceb;border-radius:18px;background:#fff;box-shadow:0 8px 28px rgba(26,55,52,.045)}
.admin-v2 .metric-card{padding:1rem;height:100%}.admin-v2 .metric-icon{width:42px;height:42px;border-radius:13px;background:#f0f8f7;color:var(--primary-teal,#087f78);display:grid;place-items:center;font-size:1.15rem}
.admin-v2 .metric-value{font-size:1.75rem;line-height:1;font-weight:750;letter-spacing:-.04em}.admin-v2 .admin-link{display:flex;align-items:center;gap:.8rem;padding:.9rem;text-decoration:none;color:inherit;transition:.18s ease}.admin-v2 .admin-link:hover{transform:translateY(-2px);border-color:#b9d9d5}.admin-v2 .admin-link-icon{width:40px;height:40px;border-radius:12px;background:#f3f7f6;display:grid;place-items:center;color:var(--primary-teal,#087f78)}
.admin-v2 .panel-card{padding:1.15rem;height:100%}.admin-v2 .review-item,.admin-v2 .booking-item{padding:.9rem 0;border-bottom:1px solid #edf1f0}.admin-v2 .review-item:last-child,.admin-v2 .booking-item:last-child{border-bottom:0}.admin-v2 .revenue-chart{height:150px;display:flex;align-items:flex-end;gap:5px;padding-top:1rem}.admin-v2 .revenue-bar{flex:1;min-width:4px;border-radius:5px 5px 2px 2px;background:linear-gradient(180deg,#149b90,#087f78);opacity:.82}.admin-v2 .revenue-empty{height:150px;display:grid;place-items:center;color:#788582}.admin-v2 .section-title{font-size:1rem;font-weight:700}.admin-v2 .security-chip{border:1px solid #f0d5d5;background:#fff8f8;color:#a13b3b;border-radius:999px;padding:.35rem .65rem;font-size:.75rem;font-weight:700}
@media(max-width:767.98px){.admin-v2 .admin-hero{padding:1rem}.admin-v2 .metric-value{font-size:1.5rem}.admin-v2 .revenue-chart{height:120px}}
</style>
<div class="container py-4 admin-v2">
  <section class="admin-hero mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div><div class="admin-kicker mb-1">Administration workspace</div><h2 class="fw-bold mb-1">System overview</h2><p class="text-muted mb-0">Review platform activity, provider approvals, subscriptions and core records.</p></div>
      <div class="d-flex flex-wrap gap-2 align-items-center"><span class="security-chip"><i class="bi bi-shield-lock me-1"></i> Admin access</span><a href="<?= url('admin/providers.php') ?>" class="btn btn-teal btn-sm"><i class="bi bi-patch-check me-1"></i> Reviews <?= (int)$stats['pending_verifications'] ?></a><a href="<?= url('admin/reports.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart me-1"></i> Reports</a></div>
    </div>
  </section>

  <div class="row g-3 mb-4">
    <?php $metrics=[
      ['bi-people','Registered users',$stats['total_users'],$stats['total_clients'].' clients · '.($stats['total_doctors']+$stats['total_centres']).' providers'],
      ['bi-credit-card','Active subscriptions',$stats['active_subscriptions'],formatLKR($stats['total_subscription_revenue']).' recorded revenue'],
      ['bi-patch-exclamation','Pending reviews',$stats['pending_verifications'],'Provider verification queue'],
      ['bi-calendar2-check','Appointments this month',$stats['month_appointments'],$stats['total_appointments'].' lifetime bookings']
    ]; foreach($metrics as $m): ?>
      <div class="col-6 col-xl-3"><div class="metric-card"><div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div class="small text-muted fw-semibold"><?= e($m[1]) ?></div><div class="metric-icon"><i class="bi <?= e($m[0]) ?>"></i></div></div><div class="metric-value mb-2"><?= e((string)$m[2]) ?></div><div class="small text-muted"><?= e((string)$m[3]) ?></div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="fw-bold mb-0">Quick management</h5><small class="text-muted d-none d-md-block">Core administration areas</small></div>
  <div class="row g-2 mb-4">
    <?php $links=[['users.php','bi-people','Users','Roles & account status'],['providers.php','bi-patch-check','Providers','Verification & records'],['appointments.php','bi-calendar-check','Appointments','Platform bookings'],['subscriptions.php','bi-receipt','Subscriptions','Activation history'],['plans.php','bi-gem','Plans','Pricing & quotas'],['specializations.php','bi-heart-pulse','Specializations','Medical disciplines'],['cities.php','bi-geo-alt','Cities','Service locations'],['database.php','bi-database','Database','Schema & mapping']]; foreach($links as $l): ?>
      <div class="col-6 col-lg-3"><a class="admin-link h-100" href="<?= url('admin/'.$l[0]) ?>"><span class="admin-link-icon"><i class="bi <?= $l[1] ?>"></i></span><span><span class="d-block fw-semibold small"><?= e($l[2]) ?></span><span class="d-block text-muted" style="font-size:.73rem"><?= e($l[3]) ?></span></span></a></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-xl-5"><section class="panel-card"><div class="d-flex justify-content-between align-items-center"><div><div class="section-title">30-day subscription revenue</div><small class="text-muted">Based on subscription start dates</small></div><i class="bi bi-graph-up-arrow text-teal fs-4"></i></div>
      <?php if(empty($revenueTrend) || $revenueMax<=0): ?><div class="revenue-empty"><div class="text-center"><i class="bi bi-bar-chart fs-3 d-block mb-2"></i><small>No subscription revenue in this period.</small></div></div><?php else: ?><div class="revenue-chart" aria-label="30 day revenue activity"><?php foreach($revenueTrend as $point): $h=max(5,round(((float)$point['revenue']/$revenueMax)*100)); ?><div class="revenue-bar" style="height:<?= $h ?>%" title="<?= e($point['revenue_date']) ?> · <?= e(formatLKR($point['revenue'])) ?>"></div><?php endforeach; ?></div><?php endif; ?>
      <div class="d-flex justify-content-between small text-muted"><span>30 days ago</span><span>Today</span></div></section></div>
    <div class="col-xl-7"><section class="panel-card"><div class="d-flex justify-content-between align-items-center mb-2"><div><div class="section-title">Provider verification queue</div><small class="text-muted">Most recent pending registrations</small></div><a href="<?= url('admin/providers.php') ?>" class="btn btn-outline-secondary btn-sm">View all</a></div>
      <?php if(empty($pendingProviders)): ?><div class="text-center py-5 text-muted"><i class="bi bi-check-circle text-success fs-2 d-block mb-2"></i><small>No provider registrations are waiting for review.</small></div><?php else: foreach($pendingProviders as $pp): ?><div class="review-item d-flex justify-content-between align-items-center gap-3"><div class="min-w-0"><div class="fw-semibold text-truncate"><?= e($pp['Business_Name']) ?></div><div class="small text-muted"><?= e(ucwords(strtolower(str_replace('_',' ',$pp['Provider_Type'])))) ?> · <?= e($pp['City']) ?></div><div class="small text-muted text-truncate"><?= e($pp['Medical_License_No'] ?: $pp['Registration_No']) ?></div></div><a href="<?= url('admin/providers.php') ?>" class="btn btn-teal btn-sm flex-shrink-0">Review</a></div><?php endforeach; endif; ?>
    </section></div>
  </div>

  <section class="panel-card"><div class="d-flex justify-content-between align-items-center mb-2"><div><div class="section-title">Recent platform bookings</div><small class="text-muted">Latest appointment activity across MediLink</small></div><a href="<?= url('admin/appointments.php') ?>" class="btn btn-outline-secondary btn-sm">All appointments</a></div>
    <?php if(empty($recentAppointments)): ?><div class="text-center py-4 text-muted"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i><small>No booking activity recorded yet.</small></div><?php else: ?><div class="row g-0"><?php foreach($recentAppointments as $ra): ?><div class="col-12 col-lg-6"><div class="booking-item <?= $loop??'' ?>"><div class="d-flex justify-content-between gap-3"><div><div class="fw-semibold"><?= e($ra['Patient_First'].' '.$ra['Patient_Last']) ?></div><div class="small text-muted"><?= e($ra['Business_Name']) ?> · <?= e($ra['City']) ?></div><div class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= e(formatDate($ra['Slot_Date'])) ?> · <?= e(date('g:i A',strtotime($ra['Start_Time']))) ?></div></div><div><?= renderStatusBadge($ra['Status']) ?></div></div></div></div><?php endforeach; ?></div><?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
