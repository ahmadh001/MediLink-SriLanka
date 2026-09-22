<?php
$pageTitle = 'Database Overview';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(ROLE_ADMIN);
$db = Database::getConnection();

$tables = [
 'USER'=>['Users','bi-people','admin/users.php','Accounts, roles and account status'],
 'CLIENT'=>['Clients','bi-person-heart',null,'Patient/client profiles'],
 'PROVIDER'=>['Providers','bi-building-check','admin/providers.php','Doctor and healthcare-centre provider base'],
 'DOCTOR'=>['Doctors','bi-person-badge',null,'Doctor-specific professional details'],
 'HEALTHCARE_CENTRE'=>['Healthcare Centres','bi-hospital',null,'Healthcare-centre details'],
 'CENTRE_DOCTOR_LINK'=>['Centre ↔ Doctor','bi-diagram-3',null,'M:N centre-doctor mapping'],
 'CITY'=>['Cities','bi-geo-alt','admin/cities.php','Sri Lankan city and GPS registry'],
 'SPECIALIZATION'=>['Specializations','bi-heart-pulse','admin/specializations.php','Medical disciplines'],
 'DOCTOR_SPECIALIZATION'=>['Doctor ↔ Specialization','bi-bezier2',null,'M:N doctor-specialization mapping'],
 'SUBSCRIPTION_PLAN'=>['Plans','bi-tags','admin/plans.php','Subscription pricing and quotas'],
 'USER_SUBSCRIPTION'=>['Subscriptions','bi-receipt','admin/subscriptions.php','Subscription history and status'],
 'SCHEDULED_SLOT'=>['Scheduled Slots','bi-calendar3',null,'Provider availability and booked/blocked slots'],
 'APPOINTMENT'=>['Appointments','bi-calendar-check','admin/appointments.php','Client bookings and lifecycle']
];

$counts=[];
foreach ($tables as $name=>$meta) {
    try { $counts[$name]=(int)$db->query("SELECT COUNT(*) FROM `$name`")->fetchColumn(); }
    catch (Throwable $e) { $counts[$name]=null; }
}
$totalRecords=array_sum(array_filter($counts, 'is_int'));


$schemaMeta = [];
$totalColumns = 0;
$totalForeignKeys = 0;
foreach (array_keys($tables) as $tableName) {
    try {
        $cols = $db->query("SHOW COLUMNS FROM `{$tableName}`")->fetchAll(PDO::FETCH_ASSOC);
        $schemaMeta[$tableName]['columns'] = $cols;
        $totalColumns += count($cols);

        $fkStmt = $db->prepare("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY ORDINAL_POSITION");
        $fkStmt->execute([$tableName]);
        $fks = $fkStmt->fetchAll(PDO::FETCH_ASSOC);
        $schemaMeta[$tableName]['foreign_keys'] = $fks;
        $totalForeignKeys += count($fks);
    } catch (Throwable $e) {
        $schemaMeta[$tableName] = ['columns'=>[], 'foreign_keys'=>[]];
    }
}

$relations = [
 ['USER','1 : 0..1','CLIENT','User_ID','Account becomes a client profile'],
 ['USER','1 : 0..1','PROVIDER','User_ID','Account becomes a provider profile'],
 ['PROVIDER','1 : 0..1','DOCTOR','Provider_ID','Doctor subtype'],
 ['PROVIDER','1 : 0..1','HEALTHCARE_CENTRE','Provider_ID','Healthcare-centre subtype'],
 ['HEALTHCARE_CENTRE','M : N','DOCTOR','CENTRE_DOCTOR_LINK','Affiliated doctors'],
 ['DOCTOR','M : N','SPECIALIZATION','DOCTOR_SPECIALIZATION','Doctor disciplines'],
 ['USER','1 : M','USER_SUBSCRIPTION','User_ID','Subscription history'],
 ['SUBSCRIPTION_PLAN','1 : M','USER_SUBSCRIPTION','Plan_ID','Plan subscriptions'],
 ['PROVIDER','1 : M','SCHEDULED_SLOT','Provider_ID','Provider availability'],
 ['DOCTOR','1 : M','SCHEDULED_SLOT','Doctor_ID','Optional doctor-specific slots'],
 ['CLIENT','1 : M','APPOINTMENT','Client_ID','Client bookings'],
 ['SCHEDULED_SLOT','1 : 0..1','APPOINTMENT','Slot_ID UNIQUE','One appointment per slot']
];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4 admin-db-overview">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
      <span class="badge bg-dark mb-2"><i class="bi bi-database-fill me-1"></i> Database Control Centre</span>
      <h2 class="fw-bold mb-1">MediLink Data Model</h2>
      <p class="text-muted mb-0">Live record counts, entity mapping and safe shortcuts to application-level CRUD pages.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <span class="badge bg-light text-dark border p-2"><strong><?= count($tables) ?></strong> Tables</span>
      <span class="badge bg-light text-dark border p-2"><strong><?= number_format($totalColumns) ?></strong> Columns</span>
      <span class="badge bg-light text-dark border p-2"><strong><?= number_format($totalForeignKeys) ?></strong> Foreign Keys</span>
      <span class="badge bg-light text-dark border p-2"><strong><?= number_format($totalRecords) ?></strong> Records</span>
      <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>
  </div>

  <div class="alert alert-info border-0 shadow-sm small mb-4">
    <i class="bi bi-shield-check me-2"></i><strong>Safe admin design:</strong> this screen shows the database structure and live counts, while edits are routed through validated MediLink CRUD pages instead of exposing raw SQL editing.
  </div>

  <div class="row g-3 mb-5">
    <?php foreach ($tables as $name=>$meta): ?>
      <div class="col-sm-6 col-lg-4 col-xl-3">
        <div class="card card-custom h-100 p-3 db-entity-card">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="db-icon"><i class="bi <?= $meta[1] ?>"></i></div>
            <span class="badge bg-light text-dark border"><?= $counts[$name] === null ? 'N/A' : number_format($counts[$name]) ?></span>
          </div>
          <h6 class="fw-bold mb-1"><?= e($meta[0]) ?></h6>
          <code class="small d-block mb-2"><?= e($name) ?></code>
          <p class="text-muted small flex-grow-1 mb-3"><?= e($meta[3]) ?></p>
          <div class="db-schema-mini mb-3">
            <span><i class="bi bi-layout-three-columns me-1"></i><?= count($schemaMeta[$name]['columns'] ?? []) ?> columns</span>
            <span><i class="bi bi-key me-1"></i><?= count($schemaMeta[$name]['foreign_keys'] ?? []) ?> FKs</span>
          </div>
          <button class="btn btn-sm btn-light border w-100 mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#schema-<?= e($name) ?>" aria-expanded="false">
            <i class="bi bi-code-square me-1"></i>Schema details
          </button>
          <div class="collapse" id="schema-<?= e($name) ?>">
            <div class="db-schema-detail mb-2">
              <?php foreach (($schemaMeta[$name]['columns'] ?? []) as $column): ?>
                <div><code><?= e($column['Field']) ?></code><span><?= e($column['Type']) ?><?= $column['Key'] === 'PRI' ? ' · PK' : '' ?></span></div>
              <?php endforeach; ?>
              <?php if (empty($schemaMeta[$name]['columns'])): ?><small class="text-muted">Schema metadata unavailable.</small><?php endif; ?>
            </div>
          </div>
          <?php if ($meta[2]): ?>
            <a href="<?= url($meta[2]) ?>" class="btn btn-sm btn-outline-teal w-100">Open Management <i class="bi bi-arrow-right ms-1"></i></a>
          <?php else: ?>
            <span class="small text-muted"><i class="bi bi-link-45deg me-1"></i>Managed through related workflow</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card card-custom p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div><h5 class="fw-bold mb-1"><i class="bi bi-diagram-3 text-teal me-2"></i>Relationship & Mapping View</h5><p class="text-muted small mb-0">A demo-friendly view of the core EER mappings implemented by foreign keys and junction tables.</p></div>
      <span class="badge bg-teal text-white">EER / Mapping</span>
    </div>
    <div class="table-responsive">
      <table class="table table-custom align-middle mb-0">
        <thead><tr><th>Parent / Source</th><th>Cardinality</th><th>Child / Target</th><th>Key / Junction</th><th>Purpose</th></tr></thead>
        <tbody>
        <?php foreach ($relations as $r): ?>
          <tr><td><code><?= e($r[0]) ?></code></td><td><span class="badge bg-light text-dark border"><?= e($r[1]) ?></span></td><td><code><?= e($r[2]) ?></code></td><td><code><?= e($r[3]) ?></code></td><td class="small text-muted"><?= e($r[4]) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-4"><a class="card card-custom p-4 h-100 text-decoration-none text-dark" href="<?= url('admin/providers.php') ?>"><i class="bi bi-patch-check fs-3 text-teal"></i><h6 class="fw-bold mt-3">Provider Verification</h6><p class="small text-muted mb-0">Review provider records and approval status.</p></a></div>
    <div class="col-lg-4"><a class="card card-custom p-4 h-100 text-decoration-none text-dark" href="<?= url('admin/appointments.php') ?>"><i class="bi bi-calendar-check fs-3 text-teal"></i><h6 class="fw-bold mt-3">Booking Data</h6><p class="small text-muted mb-0">Inspect appointment lifecycle linked to scheduled slots.</p></a></div>
    <div class="col-lg-4"><a class="card card-custom p-4 h-100 text-decoration-none text-dark" href="<?= url('admin/reports.php') ?>"><i class="bi bi-bar-chart fs-3 text-teal"></i><h6 class="fw-bold mt-3">Reports & Analytics</h6><p class="small text-muted mb-0">Use live application data for demo-ready system reporting.</p></a></div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
