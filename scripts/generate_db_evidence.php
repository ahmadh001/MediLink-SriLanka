<?php
/**
 * Database Screenshot & Evidence Generator
 * Produces styled HTML snapshots and renders PNG screenshots via headless Edge
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

$tables = [
    'USER' => 'Central authentication table with password hashes, phone numbers, and NIC numbers.',
    'CLIENT' => 'Patient profile with date of birth, gender, and GPS geolocation coordinates.',
    'PROVIDER' => 'Base healthcare provider with business name, clinic address, coordinates, and consultation fee.',
    'DOCTOR' => 'Specialist doctor subtype with SLMC license, experience, and consultation duration.',
    'HEALTHCARE_CENTRE' => 'Healthcare facility subtype with PHSRC ministry registration and overview.',
    'CENTRE_DOCTOR_LINK' => 'M:N associative entity linking healthcare centres with consulting specialist doctors.',
    'CITY' => 'National city and GPS registry storing pre-calibrated latitudes and longitudes across Sri Lanka.',
    'SPECIALIZATION' => 'Medical field catalog (Cardiology, Pediatrics, Dermatology, Neurology, etc.).',
    'DOCTOR_SPECIALIZATION' => 'M:N associative entity mapping doctors to multiple clinical specializations (3NF).',
    'SUBSCRIPTION_PLAN' => 'Tiered plans defining pricing in LKR, duration, monthly booking quotas, and search radii.',
    'USER_SUBSCRIPTION' => 'Subscription history and active memberships linking users to plans with payment tokens.',
    'SCHEDULED_SLOT' => 'Provider calendar slots with composite start/end times and availability statuses.',
    'APPOINTMENT' => 'Client bookings enforcing a 1:1 UNIQUE constraint on Slot_ID preventing double-booking.'
];

$edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
if (!file_exists($edgePath)) {
    $edgePath = 'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe';
}

$htmlDir = __DIR__ . '/../evidence_screenshots/html';
$imgDir = __DIR__ . '/../evidence_screenshots';

echo "=== Generating Database Evidence Pages ===\n";

$allPages = [];
$idx = 1;

foreach ($tables as $table => $desc) {
    // 1. Describe table
    $descStmt = $db->query("DESCRIBE `$table`");
    $columns = $descStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Sample records
    $dataStmt = $db->query("SELECT * FROM `$table` LIMIT 10");
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    $numStr = sprintf('%02d', $idx);
    $filename = "{$numStr}_{$table}.html";
    $pngName = "{$numStr}_{$table}.png";
    $filepath = "{$htmlDir}/{$filename}";

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Table: <?= $table ?> | MediLink Database Evidence</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
      <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; color: #212529; padding: 25px; }
        .card { border-radius: 10px; border: 1px solid #dee2e6; box-shadow: 0 4px 12px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .table thead th { background: #0f766e; color: #fff; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-key { background: #fef08a; color: #854d0e; font-weight: 700; border: 1px solid #fde047; }
        .badge-fk { background: #e0f2fe; color: #0369a1; font-weight: 700; border: 1px solid #bae6fd; }
        .badge-uni { background: #fce7f3; color: #9d174d; font-weight: 700; border: 1px solid #fbcfe8; }
        .code-pill { font-family: 'Consolas', monospace; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; }
      </style>
    </head>
    <body>
      <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
          <div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-dark fs-6">Table #<?= $idx ?></span>
              <h2 class="fw-bold mb-0 text-dark">`<?= $table ?>`</h2>
            </div>
            <p class="text-muted mb-0 mt-1"><?= htmlspecialchars($desc) ?></p>
          </div>
          <div class="text-end">
            <span class="badge bg-success fs-6"><i class="bi bi-database-check me-1"></i> MySQL (InnoDB)</span>
            <div class="small text-muted mt-1">Database: <code>patient_doctor_booking</code></div>
          </div>
        </div>

        <h5 class="fw-bold text-teal mb-2"><i class="bi bi-diagram-3-fill text-success me-2"></i> Table Schema & Column Specifications</h5>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-bordered align-middle">
            <thead>
              <tr>
                <th>Column Name</th>
                <th>Data Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($columns as $c): ?>
                <tr>
                  <td class="fw-bold"><?= htmlspecialchars($c['Field']) ?></td>
                  <td><code><?= htmlspecialchars($c['Type']) ?></code></td>
                  <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['Null']) ?></span></td>
                  <td>
                    <?php if ($c['Key'] === 'PRI'): ?>
                      <span class="badge badge-key">PRIMARY KEY</span>
                    <?php elseif ($c['Key'] === 'UNI'): ?>
                      <span class="badge badge-uni">UNIQUE</span>
                    <?php elseif ($c['Key'] === 'MUL'): ?>
                      <span class="badge badge-fk">INDEX / FK</span>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td><?= $c['Default'] !== null ? htmlspecialchars($c['Default']) : '<span class="text-muted">NULL</span>' ?></td>
                  <td><small class="text-muted"><?= htmlspecialchars($c['Extra'] ?: '-') ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <h5 class="fw-bold text-teal mb-2"><i class="bi bi-table text-primary me-2"></i> Live Data Sample (First <?= count($rows) ?> Records)</h5>
        <div class="table-responsive">
          <table class="table table-sm table-striped table-bordered align-middle font-monospace" style="font-size: 0.8rem;">
            <thead class="table-dark">
              <tr>
                <?php if (!empty($rows)): foreach (array_keys($rows[0]) as $colName): ?>
                  <th><?= htmlspecialchars($colName) ?></th>
                <?php endforeach; endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <?php foreach ($r as $val): ?>
                    <td class="text-truncate" style="max-width: 250px;">
                      <?= $val !== null ? htmlspecialchars((string)$val) : '<span class="text-muted">NULL</span>' ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </body>
    </html>
    <?php
    $htmlContent = ob_get_clean();
    file_put_contents($filepath, $htmlContent);

    $allPages[] = [
        'idx' => $idx,
        'title' => "Table: {$table}",
        'html' => $filepath,
        'png' => "{$imgDir}/{$pngName}",
        'pngName' => $pngName
    ];
    $idx++;
}

// -------------------------------------------------------------
// Complex SQL Queries Evidence Page
// -------------------------------------------------------------
$queries = [
    [
        'title' => 'Haversine GPS Spherical Proximity Calculation (Nearest Doctors)',
        'sql' => "SELECT p.Provider_ID, p.Business_Name, p.City, p.Consultation_Fee,
       ROUND(6371 * ACOS(
           LEAST(1.0, GREATEST(-1.0,
               COS(RADIANS(6.8961)) * COS(RADIANS(p.Latitude)) * COS(RADIANS(p.Longitude) - RADIANS(79.8572)) +
               SIN(RADIANS(6.8961)) * SIN(RADIANS(p.Latitude))
           ))
       ), 2) AS distance_km
FROM PROVIDER p
WHERE p.Verification_Status = 'VERIFIED'
ORDER BY distance_km ASC;"
    ],
    [
        'title' => 'M:N Multi-Specialization & Healthcare Centre Affiliations',
        'sql' => "SELECT d.Doctor_ID, CONCAT('Dr. ', u.First_Name, ' ', u.Last_Name) AS Doctor_Name,
       d.Medical_License_No, p.Consultation_Fee,
       GROUP_CONCAT(DISTINCT s.Name ORDER BY s.Name SEPARATOR ', ') AS Specialities,
       GROUP_CONCAT(DISTINCT hc.Centre_Name ORDER BY hc.Centre_Name SEPARATOR '; ') AS Affiliated_Centres
FROM DOCTOR d
JOIN PROVIDER p ON d.Provider_ID = p.Provider_ID
JOIN `USER` u ON p.User_ID = u.User_ID
LEFT JOIN DOCTOR_SPECIALIZATION ds ON d.Doctor_ID = ds.Doctor_ID
LEFT JOIN SPECIALIZATION s ON ds.Specialization_ID = s.Specialization_ID
LEFT JOIN CENTRE_DOCTOR_LINK cdl ON d.Doctor_ID = cdl.Doctor_ID
LEFT JOIN HEALTHCARE_CENTRE hc ON cdl.Centre_ID = hc.Centre_ID
GROUP BY d.Doctor_ID;"
    ],
    [
        'title' => '1:1 Slot Reservation & Appointment Verification (Row Lock Protection)',
        'sql' => "SELECT a.Appointment_ID, a.Client_ID, CONCAT(cu.First_Name, ' ', cu.Last_Name) AS Patient_Name,
       cu.NIC_No AS Patient_NIC, s.Slot_ID, s.Slot_Date, s.Start_Time, s.End_Time,
       p.Business_Name AS Clinic, p.Consultation_Fee, a.Status AS Appt_Status
FROM APPOINTMENT a
JOIN `CLIENT` c ON a.Client_ID = c.Client_ID
JOIN `USER` cu ON c.User_ID = cu.User_ID
JOIN SCHEDULED_SLOT s ON a.Slot_ID = s.Slot_ID
JOIN PROVIDER p ON s.Provider_ID = p.Provider_ID;"
    ]
];

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SQL Query Executions | MediLink Database Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; color: #212529; padding: 25px; }
    .card { border-radius: 10px; border: 1px solid #dee2e6; box-shadow: 0 4px 12px rgba(0,0,0,0.06); margin-bottom: 25px; }
    pre { background: #1e293b; color: #f8fafc; padding: 15px; border-radius: 8px; font-size: 0.85rem; }
    .table thead th { background: #0f766e; color: #fff; font-size: 0.85rem; }
  </style>
</head>
<body>
  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
      <div>
        <span class="badge bg-primary fs-6">Live Query Evidence</span>
        <h2 class="fw-bold mb-0 text-dark">Advanced Relational Query Demonstration</h2>
        <p class="text-muted mb-0 mt-1">Real-time execution of Haversine GPS distances, M:N joins, and 1:1 transactional slot reservations.</p>
      </div>
      <div class="text-end">
        <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i> Executed Successfully</span>
      </div>
    </div>

    <?php foreach ($queries as $q): 
      $qStmt = $db->query($q['sql']);
      $qRows = $qStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
      <div class="mb-4">
        <h5 class="fw-bold text-dark"><i class="bi bi-terminal-fill text-teal me-2"></i> <?= htmlspecialchars($q['title']) ?></h5>
        <pre><code><?= htmlspecialchars($q['sql']) ?></code></pre>
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-striped align-middle font-monospace" style="font-size: 0.85rem;">
            <thead class="table-light">
              <tr>
                <?php if (!empty($qRows)): foreach (array_keys($qRows[0]) as $k): ?>
                  <th><?= htmlspecialchars($k) ?></th>
                <?php endforeach; endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($qRows as $qr): ?>
                <tr>
                  <?php foreach ($qr as $qv): ?>
                    <td><?= htmlspecialchars((string)$qv) ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</body>
</html>
<?php
$queryHtml = ob_get_clean();
$queryHtmlPath = "{$htmlDir}/14_ADVANCED_QUERIES.html";
$queryPngPath = "{$imgDir}/14_ADVANCED_QUERIES.png";
file_put_contents($queryHtmlPath, $queryHtml);

$allPages[] = [
    'idx' => 14,
    'title' => "Advanced SQL Query Executions",
    'html' => $queryHtmlPath,
    'png' => $queryPngPath,
    'pngName' => '14_ADVANCED_QUERIES.png'
];

echo "HTML Evidence generated (" . count($allPages) . " pages).\n";
echo "Now capturing PNG screenshots with Microsoft Edge...\n";

// Render screenshots using headless Edge
foreach ($allPages as $p) {
    $src = "file:///" . str_replace('\\', '/', realpath($p['html']));
    $dst = $p['png'];
    $cmd = "\"{$edgePath}\" --headless --disable-gpu --screenshot=\"{$dst}\" --window-size=1280,850 \"{$src}\"";
    exec($cmd, $out, $ret);
    echo " [" . ($ret === 0 ? "OK" : "ERR") . "] Saved screenshot: {$p['pngName']}\n";
}

// Generate Master Index Gallery
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MediLink Sri Lanka — Database Evidence Gallery</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; }
    .gallery-card { background: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden; transition: transform 0.2s; }
    .gallery-card:hover { transform: translateY(-4px); border-color: #14b8a6; }
    .gallery-card img { width: 100%; height: auto; border-bottom: 1px solid #334155; display: block; }
    .badge-teal { background: #0f766e; color: #fff; }
  </style>
</head>
<body>
  <div class="container-fluid" style="max-width: 1400px;">
    <div class="text-center mb-5">
      <span class="badge badge-teal px-3 py-2 fs-6 mb-2"><i class="bi bi-database-check me-1"></i> Verified Database Submission Evidence</span>
      <h1 class="fw-bold text-white">MediLink Sri Lanka — Database Screenshots & Schema Verification</h1>
      <p class="text-muted fs-6">Comprehensive documentation of all 12 normalized tables, column constraints, sample data, and live SQL queries.</p>
    </div>

    <div class="row g-4">
      <?php foreach ($allPages as $p): ?>
        <div class="col-md-6 col-lg-4">
          <div class="gallery-card h-100 d-flex flex-column shadow">
            <a href="<?= $p['pngName'] ?>" target="_blank" title="Click to view full image">
              <img src="<?= $p['pngName'] ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
            </a>
            <div class="p-3 mt-auto">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge bg-secondary font-monospace">Evidence #<?= $p['idx'] ?></span>
                <a href="html/<?= basename($p['html']) ?>" target="_blank" class="btn btn-outline-info btn-sm py-0 px-2" style="font-size: 0.75rem;">View Interactive HTML</a>
              </div>
              <h6 class="fw-bold text-white mb-0"><?= htmlspecialchars($p['title']) ?></h6>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
<?php
$indexHtml = ob_get_clean();
file_put_contents("{$imgDir}/index.html", $indexHtml);

echo "=== Completed! Evidence Gallery available at evidence_screenshots/index.html ===\n";
