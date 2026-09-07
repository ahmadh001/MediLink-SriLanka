<?php
/**
 * Admin City Management & GPS Coordinates Hub
 * Patient–Doctor Subscription Booking System (Sri Lanka)
 */

$pageTitle = 'Manage Cities & GPS Hubs';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

// Handle City CRUD Operations
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
                // Check if any clients or providers are assigned to this city
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

// Fetch all cities with patient & provider count metrics
$citiesQuery = "
    SELECT c.*,
           (SELECT COUNT(*) FROM `CLIENT` cl WHERE cl.City = c.City_Name) AS total_patients,
           (SELECT COUNT(*) FROM `PROVIDER` pr WHERE pr.City = c.City_Name) AS total_providers
    FROM `CITY` c
    ORDER BY c.City_Name ASC
";
$cities = $db->query($citiesQuery)->fetchAll();

// Edit city pre-selection
$editCity = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $eStmt = $db->prepare("SELECT * FROM `CITY` WHERE City_ID = ?");
    $eStmt->execute([$editId]);
    $editCity = $eStmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-geo-alt-fill text-teal me-2"></i> Sri Lankan City & GPS Hubs</h2>
      <p class="text-muted small mb-0">Manage registered cities, districts, and calibrate GPS coordinates used for Haversine proximity search.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Admin Dashboard
      </a>
      <a href="<?= url('admin/providers.php') ?>" class="btn btn-teal btn-sm">
        <i class="bi bi-hospital me-1"></i> Providers Directory
      </a>
    </div>
  </div>

  <div class="row g-4">
    <!-- Form: Add or Edit City -->
    <div class="col-lg-4">
      <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3 text-teal">
          <i class="bi bi-<?= $editCity ? 'pencil-square' : 'plus-circle-fill' ?> me-2"></i>
          <?= $editCity ? 'Edit City Coordinates' : 'Add New Sri Lankan City' ?>
        </h5>

        <form method="POST" action="<?= url('admin/cities.php') ?>">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="<?= $editCity ? 'update_city' : 'create_city' ?>">
          <?php if ($editCity): ?>
            <input type="hidden" name="city_id" value="<?= $editCity['City_ID'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label small fw-semibold">City Name *</label>
            <input type="text" name="city_name" class="form-control" placeholder="e.g. Trincomalee" value="<?= e($editCity['City_Name'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">District *</label>
            <input type="text" name="district" class="form-control" placeholder="e.g. Trincomalee" value="<?= e($editCity['District'] ?? '') ?>" required>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Latitude *</label>
              <input type="number" step="0.000001" name="latitude" class="form-control" placeholder="6.9271" value="<?= e($editCity['Latitude'] ?? '') ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Longitude *</label>
              <input type="number" step="0.000001" name="longitude" class="form-control" placeholder="79.8612" value="<?= e($editCity['Longitude'] ?? '') ?>" required>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-teal w-100 py-2 fw-semibold">
              <i class="bi bi-check2-circle me-1"></i> <?= $editCity ? 'Save Changes' : 'Add City to Registry' ?>
            </button>
            <?php if ($editCity): ?>
              <a href="<?= url('admin/cities.php') ?>" class="btn btn-outline-secondary py-2">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- Master Table: Cities List -->
    <div class="col-lg-8">
      <div class="card card-custom p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0"><i class="bi bi-pin-map text-teal me-2"></i> Registered Cities (<?= count($cities) ?>)</h5>
          <span class="badge bg-light text-dark border">Dynamic GPS Hubs</span>
        </div>

        <div class="table-responsive">
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>City & District</th>
                <th>GPS Coordinates</th>
                <th>Patients</th>
                <th>Providers</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cities as $c): ?>
                <tr>
                  <td>
                    <div class="fw-bold text-dark"><?= e($c['City_Name']) ?></div>
                    <small class="text-muted"><i class="bi bi-geo"></i> <?= e($c['District']) ?> District</small>
                  </td>
                  <td>
                    <small class="font-monospace text-secondary">
                      Lat: <?= number_format($c['Latitude'], 4) ?><br>
                      Lng: <?= number_format($c['Longitude'], 4) ?>
                    </small>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border"><?= $c['total_patients'] ?></span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border"><?= $c['total_providers'] ?></span>
                  </td>
                  <td class="text-end">
                    <div class="d-flex justify-content-end gap-1">
                      <a href="<?= url('admin/cities.php?edit=' . $c['City_ID']) ?>" class="btn btn-outline-teal btn-sm" title="Edit City">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <form method="POST" action="<?= url('admin/cities.php') ?>" onsubmit="return confirm('Delete city <?= e($c['City_Name']) ?>?')">
                        <?= CSRF::inputField() ?>
                        <input type="hidden" name="action" value="delete_city">
                        <input type="hidden" name="city_id" value="<?= $c['City_ID'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete City" <?= ($c['total_patients'] + $c['total_providers']) > 0 ? 'disabled' : '' ?>>
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
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
