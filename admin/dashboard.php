<?php
/**
 * System Admin Dashboard - MediLink Sri Lanka
 */

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

// Aggregate KPI Queries
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

// Pending Provider Verifications
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

// Recent System Appointments
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

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2">
        <h2 class="fw-bold mb-0">System Administrator Portal</h2>
        <span class="badge bg-danger">Superuser Access</span>
      </div>
      <p class="text-muted small mb-0">Platform performance, revenue metrics, provider approvals, and user governance.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= url('admin/providers.php') ?>" class="btn btn-teal btn-sm">
        <i class="bi bi-patch-check me-1"></i> Verifications (<?= $stats['pending_verifications'] ?>)
      </a>
      <a href="<?= url('admin/reports.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-bar-graph me-1"></i> System Reports
      </a>
    </div>
  </div>

  <!-- Primary KPI Stats Grid -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card card-custom p-3 card-stat">
        <div class="small text-muted fw-semibold">Total Registered Users</div>
        <div class="stat-value text-dark"><?= $stats['total_users'] ?></div>
        <small class="text-muted"><?= $stats['total_clients'] ?> Clients • <?= $stats['total_doctors'] + $stats['total_centres'] ?> Providers</small>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-custom p-3 card-stat stat-success">
        <div class="small text-muted fw-semibold">Active Subscriptions</div>
        <div class="stat-value text-success"><?= $stats['active_subscriptions'] ?></div>
        <small class="text-muted">Total Rev: <?= formatLKR($stats['total_subscription_revenue']) ?></small>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-custom p-3 card-stat stat-warning">
        <div class="small text-muted fw-semibold">Pending Verifications</div>
        <div class="stat-value text-warning"><?= $stats['pending_verifications'] ?></div>
        <small class="text-muted">Requires admin review</small>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-custom p-3 card-stat">
        <div class="small text-muted fw-semibold">Monthly Appointments</div>
        <div class="stat-value text-teal"><?= $stats['month_appointments'] ?></div>
        <small class="text-muted"><?= $stats['total_appointments'] ?> Lifetime Bookings</small>
      </div>
    </div>
  </div>

  <!-- Quick Admin Navigation Cards -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <a href="<?= url('admin/users.php') ?>" class="card card-custom p-3 text-decoration-none text-dark h-100 hover-teal">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle bg-light p-3 text-teal"><i class="bi bi-people fs-4"></i></div>
          <div>
            <h6 class="fw-bold mb-0">Manage Users</h6>
            <small class="text-muted">Status & Roles</small>
          </div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?= url('admin/providers.php') ?>" class="card card-custom p-3 text-decoration-none text-dark h-100 hover-teal">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle bg-light p-3 text-teal"><i class="bi bi-patch-check fs-4"></i></div>
          <div>
            <h6 class="fw-bold mb-0">Provider Verifications</h6>
            <small class="text-muted">SLMC & PHSRC</small>
          </div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?= url('admin/plans.php') ?>" class="card card-custom p-3 text-decoration-none text-dark h-100 hover-teal">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle bg-light p-3 text-teal"><i class="bi bi-gem fs-4"></i></div>
          <div>
            <h6 class="fw-bold mb-0">Subscription Plans</h6>
            <small class="text-muted">Pricing & Quotas</small>
          </div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?= url('admin/specializations.php') ?>" class="card card-custom p-3 text-decoration-none text-dark h-100 hover-teal">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle bg-light p-3 text-teal"><i class="bi bi-heart-pulse fs-4"></i></div>
          <div>
            <h6 class="fw-bold mb-0">Specializations</h6>
            <small class="text-muted">Medical Disciplines</small>
          </div>
        </div>
      </a>
    </div>
  </div>

  <div class="row g-4">
    <!-- Pending Provider Approvals Table -->
    <div class="col-lg-6">
      <div class="card card-custom p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-warning me-2"></i> Pending Provider Verifications</h5>
          <a href="<?= url('admin/providers.php') ?>" class="btn btn-outline-secondary btn-sm">All Providers</a>
        </div>

        <?php if (empty($pendingProviders)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-check-circle fs-2 text-success d-block mb-1"></i>
            <small>All provider registrations are currently verified.</small>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Provider</th>
                  <th>License / Reg</th>
                  <th>City</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pendingProviders as $pp): ?>
                  <tr>
                    <td>
                      <div class="fw-bold"><?= e($pp['Business_Name']) ?></div>
                      <small class="text-muted"><?= e($pp['First_Name'] . ' ' . $pp['Last_Name']) ?> (<?= $pp['Provider_Type'] ?>)</small>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border"><?= e($pp['Medical_License_No'] ?: $pp['Registration_No']) ?></span>
                    </td>
                    <td><small><?= e($pp['City']) ?></small></td>
                    <td class="text-end">
                      <a href="<?= url('admin/providers.php') ?>" class="btn btn-teal btn-sm">Review</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Platform Bookings -->
    <div class="col-lg-6">
      <div class="card card-custom p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0"><i class="bi bi-calendar-check text-teal me-2"></i> Recent Platform Bookings</h5>
          <a href="<?= url('admin/reports.php') ?>" class="btn btn-outline-secondary btn-sm">Analytics</a>
        </div>

        <?php if (empty($recentAppointments)): ?>
          <p class="text-muted small mb-0">No booking activity recorded yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Provider</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentAppointments as $ra): ?>
                  <tr>
                    <td><span class="fw-semibold"><?= e($ra['Patient_First'] . ' ' . $ra['Patient_Last']) ?></span></td>
                    <td><small class="text-truncate d-inline-block" style="max-width: 140px;"><?= e($ra['Business_Name']) ?></small></td>
                    <td><small><?= formatDate($ra['Slot_Date']) ?></small></td>
                    <td><?= renderStatusBadge($ra['Status']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
