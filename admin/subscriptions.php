<?php


$pageTitle = 'Subscription Audit & Revenue Ledger';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();


$metricStmt = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM `USER_SUBSCRIPTION`) AS total_purchased,
        (SELECT COUNT(*) FROM `USER_SUBSCRIPTION` WHERE Status = 'ACTIVE' AND CURRENT_DATE BETWEEN Start_Date AND End_Date) AS current_active,
        (SELECT COUNT(*) FROM `USER_SUBSCRIPTION` WHERE Status = 'EXPIRED' OR End_Date < CURRENT_DATE) AS expired_count,
        (SELECT COALESCE(SUM(sp.Price), 0) FROM `USER_SUBSCRIPTION` us JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID) AS total_revenue
");
$metrics = $metricStmt->fetch();


$revStmt = $db->query("SELECT DATE(us.Created_At) d, COALESCE(SUM(sp.Price),0) revenue FROM `USER_SUBSCRIPTION` us JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID=sp.Plan_ID WHERE us.Created_At >= DATE_SUB(CURRENT_DATE, INTERVAL 29 DAY) GROUP BY DATE(us.Created_At) ORDER BY d");
$revRows = $revStmt->fetchAll();
$revMap = []; foreach ($revRows as $rr) { $revMap[$rr['d']] = (float)$rr['revenue']; }
$revenueTrend=[]; for($i=29;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i day"));$revenueTrend[]=['date'=>$d,'value'=>$revMap[$d]??0];}
$maxRevenue=max(1,max(array_column($revenueTrend,'value')));


$statusFilter = $_GET['status'] ?? 'ALL';
$search = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? 'ALL';

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
if ($roleFilter !== 'ALL') {
    $query .= " AND sp.Target_Role = ?";
    $params[] = $roleFilter;
}
if ($search !== '') {
    $query .= " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR u.Email LIKE ? OR p.Business_Name LIKE ? OR us.Payment_Reference_No LIKE ? OR sp.Plan_Name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$query .= " ORDER BY us.Created_At DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 admin-subscriptions-v2">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
      <div class="text-uppercase small fw-bold text-teal mb-1">Finance & subscriptions</div>
      <h2 class="fw-bold mb-1">Subscription management</h2>
      <p class="text-muted mb-0">Review plan activations, subscriber periods and recorded subscription revenue.</p>
    </div>
    <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-grid me-2"></i>Admin dashboard</a>
  </div>

  <div class="row g-3 mb-4">
    <?php $summaryCards = [
      ['bi-cash-stack','Recorded revenue',formatLKR($metrics['total_revenue']),'All subscription records'],
      ['bi-check-circle','Active',$metrics['current_active'],'Currently within plan period'],
      ['bi-hourglass-bottom','Expired',$metrics['expired_count'],'Expired or past end date'],
      ['bi-receipt','Purchases',$metrics['total_purchased'],'All recorded activations']
    ]; foreach($summaryCards as $c): ?>
    <div class="col-sm-6 col-xl-3"><div class="card card-custom h-100 p-3 admin-sub-stat">
      <div class="admin-sub-icon"><i class="bi <?= $c[0] ?>"></i></div>
      <div class="small text-muted mt-3"><?= e($c[1]) ?></div><div class="fs-3 fw-bold mt-1"><?= e((string)$c[2]) ?></div><div class="small text-muted"><?= e($c[3]) ?></div>
    </div></div><?php endforeach; ?>
  </div>

  <div class="card card-custom p-4 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3"><div><h5 class="fw-bold mb-1">30-day recorded revenue</h5><p class="text-muted small mb-0">Daily total based on subscription plan prices stored with recorded activations.</p></div><i class="bi bi-graph-up fs-3 text-teal"></i></div>
    <div class="ml-mini-chart" aria-label="30-day recorded subscription revenue">
      <?php foreach($revenueTrend as $point): $h=max(5,round(($point['value']/$maxRevenue)*100)); ?><span style="height:<?= $h ?>%" title="<?= e($point['date']) ?> — <?= formatLKR($point['value']) ?>"></span><?php endforeach; ?>
    </div>
  </div>

  <div class="card card-custom p-3 p-lg-4 mb-4">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-lg-6"><label class="form-label small fw-semibold">Search records</label><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Name, email, plan or reference"></div></div>
      <div class="col-sm-5 col-lg-2"><label class="form-label small fw-semibold">Status</label><select class="form-select" name="status"><option value="ALL">All statuses</option><option value="ACTIVE" <?= $statusFilter==='ACTIVE'?'selected':'' ?>>Active</option><option value="EXPIRED" <?= $statusFilter==='EXPIRED'?'selected':'' ?>>Expired</option></select></div>
      <div class="col-sm-5 col-lg-2"><label class="form-label small fw-semibold">Plan role</label><select class="form-select" name="role"><option value="ALL">All roles</option><option value="CLIENT" <?= $roleFilter==='CLIENT'?'selected':'' ?>>Client</option><option value="PROVIDER" <?= $roleFilter==='PROVIDER'?'selected':'' ?>>Provider</option></select></div>
      <div class="col-sm-2 col-lg-2 d-grid"><button class="btn btn-teal"><i class="bi bi-funnel me-1"></i>Apply</button></div>
      <?php if($search!=='' || $statusFilter!=='ALL' || $roleFilter!=='ALL'): ?><div class="col-12"><a class="small text-decoration-none" href="<?= url('admin/subscriptions.php') ?>"><i class="bi bi-x-circle me-1"></i>Clear filters</a></div><?php endif; ?>
    </form>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-0">Subscription records</h5><small class="text-muted"><?= count($subscriptions) ?> result<?= count($subscriptions)===1?'':'s' ?></small></div></div>

  <?php if(!$subscriptions): ?>
    <div class="card card-custom text-center p-5"><div class="admin-sub-empty"><i class="bi bi-receipt"></i></div><h5 class="fw-bold mt-3">No subscription records found</h5><p class="text-muted mb-3">Try changing the search or filters.</p><a href="<?= url('admin/subscriptions.php') ?>" class="btn btn-outline-secondary mx-auto">Clear filters</a></div>
  <?php else: ?>
    <div class="row g-3">
    <?php foreach($subscriptions as $s): ?>
      <div class="col-12"><article class="card card-custom p-3 p-lg-4 admin-sub-record">
        <div class="row g-3 align-items-center">
          <div class="col-lg-4">
            <div class="d-flex gap-3 align-items-start"><div class="admin-sub-avatar"><i class="bi <?= $s['Target_Role']==='PROVIDER'?'bi-building':'bi-person' ?>"></i></div><div class="min-w-0"><div class="d-flex flex-wrap gap-2 align-items-center"><h6 class="fw-bold mb-0"><?= e($s['First_Name'].' '.$s['Last_Name']) ?></h6><?= renderStatusBadge($s['Status']) ?></div><div class="text-muted small text-truncate mt-1"><?= e($s['Email']) ?></div><?php if($s['Business_Name']): ?><div class="small mt-1"><i class="bi bi-building me-1 text-muted"></i><?= e($s['Business_Name']) ?></div><?php endif; ?></div></div>
          </div>
          <div class="col-sm-6 col-lg-2"><div class="admin-sub-label">Plan</div><div class="fw-semibold text-teal"><?= e($s['Plan_Name']) ?></div><div class="small text-muted"><?= e($s['Target_Role']) ?></div></div>
          <div class="col-sm-6 col-lg-2"><div class="admin-sub-label">Recorded price</div><div class="fw-bold"><?= formatLKR($s['Price']) ?></div><div class="small text-muted"><?= formatDateTime($s['Created_At']) ?></div></div>
          <div class="col-sm-6 col-lg-2"><div class="admin-sub-label">Plan period</div><div class="small fw-semibold"><?= formatDate($s['Start_Date']) ?></div><div class="small text-muted">to <?= formatDate($s['End_Date']) ?></div></div>
          <div class="col-sm-6 col-lg-2"><div class="admin-sub-label">Reference</div><code class="admin-sub-ref"><?= e($s['Payment_Reference_No'] ?: '—') ?></code></div>
        </div>
      </article></div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<style>
.admin-subscriptions-v2 .admin-sub-stat{border:1px solid var(--border-color,#e8ecef)}
.admin-sub-icon,.admin-sub-avatar,.admin-sub-empty{display:grid;place-items:center;background:rgba(8,127,120,.09);color:var(--primary-teal,#087f78);border-radius:14px}
.admin-sub-icon{width:42px;height:42px;font-size:1.1rem}.admin-sub-avatar{width:46px;height:46px;flex:0 0 46px;font-size:1.15rem}.admin-sub-empty{width:58px;height:58px;margin:auto;font-size:1.4rem}
.admin-sub-record{transition:transform .18s ease,box-shadow .18s ease}.admin-sub-record:hover{transform:translateY(-2px)}
.admin-sub-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.055em;color:#7a858c;font-weight:700;margin-bottom:.25rem}.admin-sub-ref{font-size:.78rem;overflow-wrap:anywhere;color:#334155}.min-w-0{min-width:0}
@media(max-width:767.98px){.admin-sub-record:hover{transform:none}.ml-mini-chart{min-height:120px}}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
