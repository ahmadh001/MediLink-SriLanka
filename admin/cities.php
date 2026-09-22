<?php


$pageTitle = 'Manage Cities & GPS Hubs';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    CSRF::check();
    $action = $_POST['action'];

    if ($action === 'create_city') {
        $cityName = trim($_POST['city_name'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $latitude = floatval($_POST['latitude'] ?? 0);
        $longitude = floatval($_POST['longitude'] ?? 0);

        if (empty($cityName) || empty($district)) {
            setFlash('danger', 'City Name and District are required.');
        } elseif ($latitude == 0 || $longitude == 0) {
            setFlash('danger', 'Valid GPS coordinates are required.');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO `CITY` (City_Name, District, Latitude, Longitude) VALUES (?, ?, ?, ?)");
                $stmt->execute([$cityName, $district, $latitude, $longitude]);
                setFlash('success', "City '{$cityName}' added successfully to the national directory.");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('danger', "City '{$cityName}' already exists.");
                } else {
                    setFlash('danger', "Database error: " . $e->getMessage());
                }
            }
        }
        redirect('admin/cities.php');

    } elseif ($action === 'update_city') {
        $cityId = (int)($_POST['city_id'] ?? 0);
        $cityName = trim($_POST['city_name'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $latitude = floatval($_POST['latitude'] ?? 0);
        $longitude = floatval($_POST['longitude'] ?? 0);

        if ($cityId > 0 && !empty($cityName) && !empty($district)) {
            try {
                $stmt = $db->prepare("UPDATE `CITY` SET City_Name = ?, District = ?, Latitude = ?, Longitude = ? WHERE City_ID = ?");
                $stmt->execute([$cityName, $district, $latitude, $longitude, $cityId]);
                setFlash('success', "City '{$cityName}' updated successfully.");
            } catch (Exception $e) {
                setFlash('danger', "Error updating city: " . $e->getMessage());
            }
        }
        redirect('admin/cities.php');

    } elseif ($action === 'delete_city') {
        $cityId = (int)($_POST['city_id'] ?? 0);
        if ($cityId > 0) {
            try {
                
                $cCheck = $db->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM `CLIENT` WHERE City = (SELECT City_Name FROM `CITY` WHERE City_ID = ?)) AS client_count,
                        (SELECT COUNT(*) FROM `PROVIDER` WHERE City = (SELECT City_Name FROM `CITY` WHERE City_ID = ?)) AS provider_count
                ");
                $cCheck->execute([$cityId, $cityId]);
                $counts = $cCheck->fetch();

                if (($counts['client_count'] + $counts['provider_count']) > 0) {
                    setFlash('warning', "Cannot delete city: {$counts['client_count']} patients and {$counts['provider_count']} providers are registered in this city.");
                } else {
                    $delStmt = $db->prepare("DELETE FROM `CITY` WHERE City_ID = ?");
                    $delStmt->execute([$cityId]);
                    setFlash('success', 'City removed successfully.');
                }
            } catch (Exception $e) {
                setFlash('danger', "Error deleting city: " . $e->getMessage());
            }
        }
        redirect('admin/cities.php');
    }
}


$citiesQuery = "
    SELECT c.*,
           (SELECT COUNT(*) FROM `CLIENT` cl WHERE cl.City = c.City_Name) AS total_patients,
           (SELECT COUNT(*) FROM `PROVIDER` pr WHERE pr.City = c.City_Name) AS total_providers
    FROM `CITY` c
    ORDER BY c.City_Name ASC
";
$cities = $db->query($citiesQuery)->fetchAll();


$editCity = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $eStmt = $db->prepare("SELECT * FROM `CITY` WHERE City_ID = ?");
    $eStmt->execute([$editId]);
    $editCity = $eStmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.city-stat{border:1px solid var(--bs-border-color);border-radius:1rem;background:#fff;padding:1rem 1.1rem;height:100%}
.city-stat .icon{width:42px;height:42px;border-radius:.8rem;display:grid;place-items:center;background:rgba(8,127,120,.09);color:var(--primary-teal,#087f78);font-size:1.1rem}
.city-card{border:1px solid var(--bs-border-color);border-radius:1rem;background:#fff;padding:1.15rem;height:100%;transition:transform .18s ease,box-shadow .18s ease}
.city-card:hover{transform:translateY(-2px);box-shadow:0 .55rem 1.4rem rgba(20,45,55,.07)}
.city-coord{font-family:var(--bs-font-monospace);font-size:.78rem;background:var(--bs-tertiary-bg);border-radius:.65rem;padding:.55rem .7rem;color:var(--bs-secondary-color)}
.city-search{max-width:360px}
@media (prefers-reduced-motion:reduce){.city-card{transition:none}.city-card:hover{transform:none}}
</style>

<div class="container py-4 py-lg-5">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
    <div>
      <div class="text-teal small fw-semibold text-uppercase mb-2"><i class="bi bi-geo-alt me-1"></i> Location directory</div>
      <h2 class="fw-bold mb-1">Cities & GPS Hubs</h2>
      <p class="text-muted mb-0">Maintain the city coordinates used by MediLink's location-based provider search.</p>
    </div>
    <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
  </div>

  <?php
    $totalPatients = array_sum(array_map(fn($c) => (int)$c['total_patients'], $cities));
    $totalProviders = array_sum(array_map(fn($c) => (int)$c['total_providers'], $cities));
    $districtCount = count(array_unique(array_filter(array_column($cities, 'District'))));
  ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="city-stat d-flex align-items-center gap-3"><div class="icon"><i class="bi bi-buildings"></i></div><div><div class="fs-4 fw-bold lh-1"><?= count($cities) ?></div><small class="text-muted">Cities</small></div></div></div>
    <div class="col-6 col-xl-3"><div class="city-stat d-flex align-items-center gap-3"><div class="icon"><i class="bi bi-map"></i></div><div><div class="fs-4 fw-bold lh-1"><?= $districtCount ?></div><small class="text-muted">Districts</small></div></div></div>
    <div class="col-6 col-xl-3"><div class="city-stat d-flex align-items-center gap-3"><div class="icon"><i class="bi bi-people"></i></div><div><div class="fs-4 fw-bold lh-1"><?= $totalPatients ?></div><small class="text-muted">Clients linked</small></div></div></div>
    <div class="col-6 col-xl-3"><div class="city-stat d-flex align-items-center gap-3"><div class="icon"><i class="bi bi-hospital"></i></div><div><div class="fs-4 fw-bold lh-1"><?= $totalProviders ?></div><small class="text-muted">Providers linked</small></div></div></div>
  </div>

  <div class="row g-4 align-items-start">
    <div class="col-lg-4">
      <div class="card card-custom p-4 position-sticky" style="top:92px">
        <div class="d-flex align-items-center gap-2 mb-3">
          <span class="city-stat icon p-0" style="width:40px;height:40px"><i class="bi bi-<?= $editCity ? 'pencil' : 'plus-lg' ?>"></i></span>
          <div><h5 class="fw-bold mb-0"><?= $editCity ? 'Edit city' : 'Add city' ?></h5><small class="text-muted">Name, district and GPS point</small></div>
        </div>
        <form method="POST" action="<?= url('admin/cities.php') ?>">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="<?= $editCity ? 'update_city' : 'create_city' ?>">
          <?php if ($editCity): ?><input type="hidden" name="city_id" value="<?= (int)$editCity['City_ID'] ?>"><?php endif; ?>
          <div class="mb-3"><label class="form-label small fw-semibold">City name</label><input type="text" name="city_name" class="form-control" placeholder="e.g. Jaffna" value="<?= e($editCity['City_Name'] ?? '') ?>" required></div>
          <div class="mb-3"><label class="form-label small fw-semibold">District</label><input type="text" name="district" class="form-control" placeholder="e.g. Jaffna" value="<?= e($editCity['District'] ?? '') ?>" required></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Latitude</label><input type="number" step="0.000001" name="latitude" class="form-control" placeholder="9.6615" value="<?= e($editCity['Latitude'] ?? '') ?>" required></div>
            <div class="col-6"><label class="form-label small fw-semibold">Longitude</label><input type="number" step="0.000001" name="longitude" class="form-control" placeholder="80.0255" value="<?= e($editCity['Longitude'] ?? '') ?>" required></div>
          </div>
          <p class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>Coordinates power distance and nearby-provider calculations.</p>
          <div class="d-flex gap-2"><button class="btn btn-teal flex-grow-1" type="submit"><i class="bi bi-check2 me-1"></i><?= $editCity ? 'Save changes' : 'Add city' ?></button><?php if ($editCity): ?><a class="btn btn-outline-secondary" href="<?= url('admin/cities.php') ?>">Cancel</a><?php endif; ?></div>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div><h5 class="fw-bold mb-1">Registered locations</h5><p class="small text-muted mb-0">Edit coordinates or remove unused cities.</p></div>
        <div class="input-group city-search"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input id="citySearch" class="form-control" type="search" placeholder="Search city or district"></div>
      </div>
      <?php if (!$cities): ?>
        <div class="card card-custom p-5 text-center"><i class="bi bi-geo-alt fs-2 text-muted"></i><h5 class="mt-3">No cities yet</h5><p class="text-muted mb-0">Add the first location using the form.</p></div>
      <?php else: ?>
        <div class="row g-3" id="cityGrid">
          <?php foreach ($cities as $c): $linked=(int)$c['total_patients']+(int)$c['total_providers']; ?>
          <div class="col-md-6 city-item" data-search="<?= e(strtolower($c['City_Name'].' '.$c['District'])) ?>">
            <div class="city-card">
              <div class="d-flex justify-content-between gap-2 mb-3">
                <div><h6 class="fw-bold mb-1"><i class="bi bi-geo-alt text-teal me-1"></i><?= e($c['City_Name']) ?></h6><div class="small text-muted"><?= e($c['District']) ?> District</div></div>
                <div class="dropdown"><button class="btn btn-sm btn-light border" data-bs-toggle="dropdown" aria-label="City actions"><i class="bi bi-three-dots"></i></button><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?= url('admin/cities.php?edit=' . $c['City_ID']) ?>"><i class="bi bi-pencil me-2"></i>Edit</a><?php if ($linked===0): ?><form method="POST" action="<?= url('admin/cities.php') ?>" data-confirm="Delete city <?= e($c['City_Name']) ?>?"><?= CSRF::inputField() ?><input type="hidden" name="action" value="delete_city"><input type="hidden" name="city_id" value="<?= (int)$c['City_ID'] ?>"><button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Delete</button></form><?php else: ?><span class="dropdown-item text-muted disabled"><i class="bi bi-lock me-2"></i>In use</span><?php endif; ?></div></div>
              </div>
              <div class="city-coord mb-3"><i class="bi bi-crosshair me-1"></i><?= number_format((float)$c['Latitude'],6) ?>, <?= number_format((float)$c['Longitude'],6) ?></div>
              <div class="d-flex gap-2 flex-wrap"><span class="badge bg-light text-dark border"><i class="bi bi-person me-1"></i><?= (int)$c['total_patients'] ?> clients</span><span class="badge bg-light text-dark border"><i class="bi bi-hospital me-1"></i><?= (int)$c['total_providers'] ?> providers</span><?php if ($linked>0): ?><span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">In use</span><?php else: ?><span class="badge bg-light text-muted border">Unused</span><?php endif; ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="cityNoMatch" class="card card-custom p-4 text-center d-none"><p class="mb-0 text-muted">No city matches your search.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
(() => { const q=document.getElementById('citySearch'); if(!q)return; const items=[...document.querySelectorAll('.city-item')], empty=document.getElementById('cityNoMatch'); q.addEventListener('input',()=>{const v=q.value.trim().toLowerCase();let shown=0;items.forEach(el=>{const ok=!v||el.dataset.search.includes(v);el.classList.toggle('d-none',!ok);if(ok)shown++;}); if(empty)empty.classList.toggle('d-none',shown!==0);}); })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
