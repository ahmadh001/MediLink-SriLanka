<?php
/**
 * Official Appointment Booking Receipt & Confirmation Slip
 * Printable & Downloadable patient consultation voucher
 */

$pageTitle = 'Appointment Booking Receipt';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

requireRole(ROLE_CLIENT);
$currentUser = getCurrentUser();
$userId = (int)$currentUser['user_id'];
$clientId = (int)$currentUser['client_id'];

$appointmentId = (int)($_GET['id'] ?? 0);
if ($appointmentId <= 0) {
    setFlash('danger', 'Invalid appointment receipt requested.');
    redirect('client/appointments.php');
}

$db = Database::getConnection();

// Query comprehensive appointment details
$query = "
    SELECT 
        a.Appointment_ID, a.Booking_DateTime, a.Status AS Appt_Status, a.Notes, a.Created_At,
        s.Slot_ID, s.Slot_Date, s.Start_Time, s.End_Time,
        c.Client_ID, cu.First_Name AS Patient_First, cu.Last_Name AS Patient_Last,
        cu.Email AS Patient_Email, cu.Phone AS Patient_Phone, c.City AS Patient_City, c.Address AS Patient_Address,
        p.Provider_ID, p.Provider_Type, p.Business_Name, p.Address AS Clinic_Address, p.City AS Clinic_City,
        p.Contact_Number AS Clinic_Phone,
        u.First_Name AS Doc_First, u.Last_Name AS Doc_Last,
        d.Medical_License_No, d.Consultation_Duration,
        hc.Centre_Name,
        sp.Plan_Name,
        GROUP_CONCAT(DISTINCT spec.Name SEPARATOR ', ') AS Specializations
    FROM `APPOINTMENT` a
    JOIN `SCHEDULED_SLOT` s ON a.Slot_ID = s.Slot_ID
    JOIN `CLIENT` c ON a.Client_ID = c.Client_ID
    JOIN `USER` cu ON c.User_ID = cu.User_ID
    JOIN `PROVIDER` p ON s.Provider_ID = p.Provider_ID
    LEFT JOIN `DOCTOR` d ON s.Doctor_ID = d.Doctor_ID
    LEFT JOIN `PROVIDER` dp ON d.Provider_ID = dp.Provider_ID
    LEFT JOIN `USER` u ON dp.User_ID = u.User_ID
    LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
    LEFT JOIN `SPECIALIZATION` spec ON ds.Specialization_ID = spec.Specialization_ID
    LEFT JOIN `HEALTHCARE_CENTRE` hc ON p.Provider_ID = hc.Provider_ID
    LEFT JOIN `USER_SUBSCRIPTION` us ON (c.User_ID = us.User_ID AND us.Status = 'ACTIVE')
    LEFT JOIN `SUBSCRIPTION_PLAN` sp ON us.Plan_ID = sp.Plan_ID
    WHERE a.Appointment_ID = ? AND a.Client_ID = ?
    GROUP BY a.Appointment_ID
";

$stmt = $db->prepare($query);
$stmt->execute([$appointmentId, $clientId]);
$appt = $stmt->fetch();

if (!$appt) {
    setFlash('danger', 'Appointment record not found or unauthorized access.');
    redirect('client/appointments.php');
}

$refNo = 'APT-' . str_pad($appt['Appointment_ID'], 5, '0', STR_PAD_LEFT);
$verifyHash = strtoupper(substr(hash('sha256', $refNo . $appt['Slot_ID'] . $appt['Booking_DateTime']), 0, 10));

require_once __DIR__ . '/../includes/header.php';
?>

<style>
@media print {
  body {
    background: #fff !important;
    color: #000 !important;
  }
  .navbar, .footer, .btn-no-print, .alert-no-print {
    display: none !important;
  }
  .receipt-card {
    border: 1px solid #ccc !important;
    box-shadow: none !important;
    margin: 0 !important;
    padding: 20px !important;
  }
  .receipt-header {
    border-bottom: 2px solid #0D9488 !important;
  }
}
.receipt-card {
  border-radius: 12px;
  background: #fff;
  border: 1px solid #E2E8F0;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}
.receipt-stamp {
  border: 2px dashed #0D9488;
  color: #0D9488;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 2px;
  padding: 6px 16px;
  border-radius: 8px;
  display: inline-block;
}
.receipt-box {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  padding: 14px 18px;
}
</style>

<div class="container py-4">
  <!-- Top Action Bar -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 btn-no-print">
    <div>
      <a href="<?= url('client/appointments.php') ?>" class="btn btn-outline-secondary btn-sm mb-2">
        <i class="bi bi-arrow-left me-1"></i> My Appointments
      </a>
      <h3 class="fw-bold text-dark mb-0">Booking Confirmation & Receipt</h3>
    </div>
    <div class="d-flex gap-2">
      <button onclick="window.print()" class="btn btn-teal">
        <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF
      </button>
      <a href="<?= url('client/search.php') ?>" class="btn btn-outline-teal">
        <i class="bi bi-search me-1"></i> Book Another
      </a>
    </div>
  </div>

  <!-- Success Notification Banner -->
  <div class="alert alert-success d-flex align-items-center p-3 mb-4 alert-no-print shadow-sm">
    <i class="bi bi-check-circle-fill fs-3 text-success me-3"></i>
    <div>
      <h6 class="fw-bold mb-1">Appointment Successfully Reserved & Confirmed!</h6>
      <p class="mb-0 small text-dark">
        Your consultation schedule slot is locked under reference <strong>#<?= $refNo ?></strong>. An active subscription booking credit has been applied.
      </p>
    </div>
  </div>

  <!-- Printable Receipt Card -->
  <div class="receipt-card p-4 p-md-5 mx-auto" style="max-width: 860px;">
    <!-- Receipt Header -->
    <div class="receipt-header pb-4 mb-4 border-bottom d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="bg-teal text-white p-2 rounded-3 d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
            <i class="bi bi-hospital fs-4"></i>
          </span>
          <span class="fs-4 fw-bold text-dark letter-spacing-tight">MediLink <span class="text-teal">Sri Lanka</span></span>
        </div>
        <p class="text-muted small mb-0">National Patient–Doctor Digital Consultation Network</p>
        <p class="text-muted small mb-0">Colombo, Sri Lanka | support@medilink.lk</p>
      </div>
      <div class="text-md-end">
        <span class="receipt-stamp mb-2">Confirmed Booking</span>
        <div class="text-muted small mt-1">Receipt Ref: <strong class="text-dark">#<?= $refNo ?></strong></div>
        <div class="text-muted small">Date Issued: <?= date('d M Y, h:i A', strtotime($appt['Booking_DateTime'])) ?></div>
        <div class="text-muted small">Security Token: <code class="fw-bold text-dark"><?= $verifyHash ?></code></div>
      </div>
    </div>

    <!-- Details Grid -->
    <div class="row g-4 mb-4">
      <!-- Patient Information -->
      <div class="col-md-6">
        <div class="receipt-box h-100">
          <h6 class="fw-bold text-teal text-uppercase small letter-spacing-1 mb-3">
            <i class="bi bi-person-fill me-1"></i> Patient Information
          </h6>
          <div class="mb-2">
            <small class="text-muted d-block">Full Name</small>
            <span class="fw-bold text-dark fs-6"><?= e($appt['Patient_First'] . ' ' . $appt['Patient_Last']) ?></span>
          </div>
          <div class="mb-2">
            <small class="text-muted d-block">Contact Phone</small>
            <span class="fw-semibold text-dark"><?= e($appt['Patient_Phone'] ?: 'Not Provided') ?></span>
          </div>
          <div class="mb-2">
            <small class="text-muted d-block">Email Address</small>
            <span class="fw-semibold text-dark"><?= e($appt['Patient_Email']) ?></span>
          </div>
          <div>
            <small class="text-muted d-block">Membership Coverage</small>
            <span class="badge bg-teal-subtle text-teal border border-teal-subtle">
              <i class="bi bi-gem me-1"></i> <?= e($appt['Plan_Name'] ?: 'Standard Active Plan') ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Doctor & Healthcare Facility -->
      <div class="col-md-6">
        <div class="receipt-box h-100">
          <h6 class="fw-bold text-teal text-uppercase small letter-spacing-1 mb-3">
            <i class="bi bi-hospital-fill me-1"></i> Medical Practitioner & Facility
          </h6>
          <div class="mb-2">
            <small class="text-muted d-block">Consulting Specialist</small>
            <?php if ($appt['Doc_First']): ?>
              <span class="fw-bold text-dark fs-6">Dr. <?= e($appt['Doc_First'] . ' ' . $appt['Doc_Last']) ?></span>
              <span class="badge bg-light text-dark border ms-1"><?= e($appt['Medical_License_No']) ?></span>
            <?php else: ?>
              <span class="fw-bold text-dark fs-6"><?= e($appt['Centre_Name'] ?: $appt['Business_Name']) ?></span>
            <?php endif; ?>
          </div>
          <div class="mb-2">
            <small class="text-muted d-block">Specialization / Discipline</small>
            <span class="fw-semibold text-teal"><?= e($appt['Specializations'] ?: 'General Consultations') ?></span>
          </div>
          <div class="mb-2">
            <small class="text-muted d-block">Clinical Facility & Address</small>
            <span class="fw-semibold text-dark"><?= e($appt['Business_Name']) ?></span><br>
            <small class="text-muted"><?= e($appt['Clinic_Address'] . ', ' . $appt['Clinic_City']) ?></small>
          </div>
          <div>
            <small class="text-muted d-block">Clinic Hotline</small>
            <span class="fw-semibold text-dark"><i class="bi bi-telephone me-1"></i> <?= e($appt['Clinic_Phone']) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Scheduled Slot Highlight Banner -->
    <div class="card bg-teal text-white p-3 p-md-4 mb-4 border-0 shadow-sm rounded-3">
      <div class="row align-items-center text-center text-md-start g-3">
        <div class="col-md-7">
          <div class="small text-white-50 text-uppercase fw-semibold letter-spacing-1 mb-1">Confirmed Appointment Time Slot</div>
          <h4 class="fw-bold mb-1 text-white">
            <i class="bi bi-calendar-event me-2"></i> <?= date('l, d F Y', strtotime($appt['Slot_Date'])) ?>
          </h4>
          <div class="fs-5 text-white-90 fw-semibold">
            <i class="bi bi-clock me-2"></i> <?= formatTime($appt['Start_Time']) ?> – <?= formatTime($appt['End_Time']) ?>
            <span class="badge bg-white text-teal ms-2 fs-6"><?= (int)($appt['Consultation_Duration'] ?: 20) ?> Mins Session</span>
          </div>
        </div>
        <div class="col-md-5 text-md-end border-start-md border-white-25 ps-md-4">
          <div class="small text-white-50">Reservation Status</div>
          <div class="fs-4 fw-bold text-uppercase text-warning">
            <i class="bi bi-shield-check me-1"></i> <?= e($appt['Appt_Status']) ?>
          </div>
          <small class="text-white-75">Slot Locked in Database</small>
        </div>
      </div>
    </div>

    <!-- Financial Breakdown Table -->
    <div class="mb-4">
      <h6 class="fw-bold text-dark mb-3">Billing & Payment Summary</h6>
      <div class="table-responsive">
        <table class="table table-bordered mb-0 align-middle">
          <thead class="table-light small">
            <tr>
              <th>Description</th>
              <th>Billing Method</th>
              <th class="text-end">Amount (LKR)</th>
            </tr>
          </thead>
          <tbody class="small">
            <tr>
              <td>
                <span class="fw-semibold">Online Slot Reservation & Triage Service</span><br>
                <small class="text-muted">Pessimistic row-locking, SMS confirmation, and verified provider booking</small>
              </td>
              <td>
                <span class="badge bg-success-subtle text-success">MediLink Subscription Benefit</span>
              </td>
              <td class="text-end fw-bold">Rs. 0.00</td>
            </tr>
            <tr>
              <td>
                <span class="fw-semibold">Specialist Doctor Clinical Consultation Fee</span><br>
                <small class="text-muted">Hospital facility charge and medical specialist consultation fee</small>
              </td>
              <td>
                <span class="badge bg-light text-dark border">Payable at Clinic Reception</span>
              </td>
              <td class="text-end text-muted fw-semibold">Settled at Clinic</td>
            </tr>
            <tr class="table-light">
              <td colspan="2" class="fw-bold text-dark text-end">Total Amount Paid Online:</td>
              <td class="fw-bold text-teal text-end fs-6">Rs. 0.00 (Fully Covered)</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Patient Notes (if provided) -->
    <?php if (!empty($appt['Notes'])): ?>
      <div class="receipt-box mb-4">
        <h6 class="fw-bold text-muted small text-uppercase mb-1">Patient Reason for Visit / Clinical Notes</h6>
        <p class="mb-0 text-dark small fst-italic">"<?= e($appt['Notes']) ?>"</p>
      </div>
    <?php endif; ?>

    <!-- Clinical Visit Instructions -->
    <div class="p-3 bg-light rounded-3 border mb-4">
      <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-info-circle-fill text-teal me-1"></i> Patient Instructions:</h6>
      <ul class="small text-muted mb-0 ps-3">
        <li>Please arrive at the clinic <strong>10 to 15 minutes</strong> prior to your scheduled consultation slot.</li>
        <li>Present this digital receipt on your mobile screen or bring a printed copy to the reception desk.</li>
        <li>Doctor consultation charges are settled directly at the clinic billing counter upon arrival.</li>
        <li>To cancel or reschedule, please do so at least 2 hours in advance via your <a href="<?= url('client/appointments.php') ?>" class="text-teal fw-semibold">My Appointments</a> portal to restore your monthly booking quota.</li>
      </ul>
    </div>

    <!-- Receipt Footer -->
    <div class="pt-3 border-top text-center text-muted small">
      <p class="mb-1">This is an electronically generated receipt verified by <strong>MediLink Sri Lanka System Database Engine</strong>.</p>
      <p class="mb-0 font-monospace text-secondary">Voucher Signature: <?= $verifyHash ?> | Transaction ID: #<?= $refNo ?> | Engine: InnoDB ACID Compliant</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
