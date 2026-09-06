<?php
/**
 * Client Profile & Location Management
 */

$pageTitle = 'My Profile & GPS Location';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_CLIENT);
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$clientId = $currentUser['client_id'];

$db = Database::getConnection();
$cities = getSriLankanCities();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::check();
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = $_POST['dob'] ?: null;
        $gender = $_POST['gender'] ?? 'OTHER';
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? 'Colombo');
        $latitude = floatval($_POST['latitude'] ?? 6.9271);
        $longitude = floatval($_POST['longitude'] ?? 79.8612);

        try {
            $db->beginTransaction();

            $uStmt = $db->prepare("UPDATE `USER` SET First_Name = ?, Last_Name = ?, Phone = ? WHERE User_ID = ?");
            $uStmt->execute([$firstName, $lastName, $phone, $userId]);

            $cStmt = $db->prepare("UPDATE `CLIENT` SET Date_of_Birth = ?, Gender = ?, Address = ?, City = ?, Latitude = ?, Longitude = ? WHERE Client_ID = ?");
            $cStmt->execute([$dob, $gender, $address, $city, $latitude, $longitude, $clientId]);

            $db->commit();

            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name'] = $lastName;
            $_SESSION['user']['full_name'] = $firstName . ' ' . $lastName;
            $_SESSION['user']['phone'] = $phone;

            setFlash('success', 'Profile and coordinates updated successfully.');
            redirect('client/profile.php');

        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Error updating profile: ' . $e->getMessage());
        }

    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $uStmt = $db->prepare("SELECT Password_Hash FROM `USER` WHERE User_ID = ?");
        $uStmt->execute([$userId]);
        $userRow = $uStmt->fetch();

        if (!password_verify($currentPass, $userRow['Password_Hash'])) {
            setFlash('danger', 'Current password entered is incorrect.');
        } elseif (strlen($newPass) < 6) {
            setFlash('danger', 'New password must be at least 6 characters long.');
        } elseif ($newPass !== $confirmPass) {
            setFlash('danger', 'New passwords do not match.');
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE `USER` SET Password_Hash = ? WHERE User_ID = ?")->execute([$newHash, $userId]);
            setFlash('success', 'Password updated successfully.');
            redirect('client/profile.php');
        }
    }
}

// Fetch Profile Data
$dataStmt = $db->prepare("SELECT u.*, c.* FROM `USER` u JOIN `CLIENT` c ON u.User_ID = c.User_ID WHERE u.User_ID = ?");
$dataStmt->execute([$userId]);
$profile = $dataStmt->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold mb-1"><i class="bi bi-person-gear text-teal me-2"></i> Patient Profile & Location</h2>
      <p class="text-muted small mb-0">Update your contact information and GPS coordinates for proximity calculations.</p>
    </div>
    <a href="<?= url('client/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card card-custom p-4">
        <form method="POST" action="<?= url('client/profile.php') ?>">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="update_profile">

          <h5 class="fw-bold text-teal mb-3"><i class="bi bi-person-fill me-2"></i> Personal Information</h5>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">First Name</label>
              <input type="text" name="first_name" class="form-control" value="<?= e($profile['First_Name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Last Name</label>
              <input type="text" name="last_name" class="form-control" value="<?= e($profile['Last_Name']) ?>" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email Address (Read-only)</label>
              <input type="email" class="form-control bg-light" value="<?= e($profile['Email']) ?>" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Phone Number</label>
              <input type="tel" name="phone" class="form-control" value="<?= e($profile['Phone']) ?>" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Date of Birth</label>
              <input type="date" name="dob" class="form-control" value="<?= e($profile['Date_of_Birth']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Gender</label>
              <select name="gender" class="form-select">
                <option value="MALE" <?= $profile['Gender'] === 'MALE' ? 'selected' : '' ?>>Male</option>
                <option value="FEMALE" <?= $profile['Gender'] === 'FEMALE' ? 'selected' : '' ?>>Female</option>
                <option value="OTHER" <?= $profile['Gender'] === 'OTHER' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
          </div>

          <!-- Location and Coordinates -->
          <hr class="my-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-teal mb-0"><i class="bi bi-geo-alt-fill me-2"></i> Address & GPS Coordinates</h5>
            <button type="button" id="btn-detect-location" class="btn btn-outline-teal btn-sm">
              <i class="bi bi-crosshair me-1"></i> Detect My Current Location
            </button>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Street / Residential Address</label>
            <input type="text" name="address" class="form-control" value="<?= e($profile['Address']) ?>">
          </div>

          <div class="row g-3 mb-2">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">City / District</label>
              <select name="city" id="city-select" class="form-select">
                <?php foreach ($cities as $cName => $cData): ?>
                  <option value="<?= $cName ?>" <?= $profile['City'] === $cName ? 'selected' : '' ?>><?= $cName ?> (<?= $cData['district'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Latitude</label>
              <input type="number" step="0.000001" name="latitude" id="latitude-input" class="form-control" value="<?= e($profile['Latitude']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Longitude</label>
              <input type="number" step="0.000001" name="longitude" id="longitude-input" class="form-control" value="<?= e($profile['Longitude']) ?>" required>
            </div>
          </div>

          <div id="location-feedback" class="small mb-4">
            <span class="text-muted"><i class="bi bi-info-circle"></i> Accurate coordinates ensure doctor search results show the exact distance in km.</span>
          </div>

          <button type="submit" class="btn btn-teal px-4 py-2 fw-semibold">
            <i class="bi bi-check-circle me-1"></i> Save Profile Details
          </button>
        </form>
      </div>
    </div>

    <!-- Password Change Card -->
    <div class="col-lg-4">
      <div class="card card-custom p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock-fill text-teal me-2"></i> Update Password</h5>
        <form method="POST" action="<?= url('client/profile.php') ?>">
          <?= CSRF::inputField() ?>
          <input type="hidden" name="action" value="change_password">

          <div class="mb-3">
            <label class="form-label small fw-semibold">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>

          <button type="submit" class="btn btn-outline-secondary w-100 py-2">
            Change Password
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
