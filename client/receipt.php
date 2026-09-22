<?php
$pageTitle = 'Appointment Confirmation';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

requireRole(ROLE_CLIENT);
$currentUser = getCurrentUser();
$clientId = (int)$currentUser['client_id'];
$appointmentId = (int)($_GET['id'] ?? 0);
if ($appointmentId <= 0) {
    setFlash('danger', 'Invalid appointment confirmation requested.');
    redirect('client/appointments.php');
}

$db = Database::getConnection();
$query = "
    SELECT
        a.Appointment_ID, a.Booking_DateTime, a.Status AS Appt_Status, a.Notes, a.Created_At,
        s.Slot_ID, s.Slot_Date, s.Start_Time, s.End_Time,
        c.Client_ID, cu.First_Name AS Patient_First, cu.Last_Name AS Patient_Last,
        cu.Email AS Patient_Email, cu.Phone AS Patient_Phone,
        p.Provider_ID, p.Provider_Type, p.Business_Name, p.Address AS Clinic_Address, p.City AS Clinic_City,
        p.Contact_Number AS Clinic_Phone,
        u.First_Name AS Doc_First, u.Last_Name AS Doc_Last,
        d.Medical_License_No, d.Consultation_Duration,
        hc.Centre_Name,
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
    WHERE a.Appointment_ID = ? AND a.Client_ID = ?
    GROUP BY a.Appointment_ID
";
$stmt = $db->prepare($query);
$stmt->execute([$appointmentId, $clientId]);
$appt = $stmt->fetch();
if (!$appt) {
    setFlash('danger', 'Appointment record not found or you do not have access to it.');
    redirect('client/appointments.php');
}

$refNo = 'APT-' . str_pad($appt['Appointment_ID'], 5, '0', STR_PAD_LEFT);
$doctorName = $appt['Doc_First'] ? 'Dr. ' . trim($appt['Doc_First'] . ' ' . $appt['Doc_Last']) : ($appt['Centre_Name'] ?: $appt['Business_Name']);
$facilityName = $appt['Centre_Name'] ?: $appt['Business_Name'];
$statusClass = in_array(strtoupper((string)$appt['Appt_Status']), ['CONFIRMED','COMPLETED'], true) ? 'success' : 'teal';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.confirm-shell{max-width:900px;margin:0 auto}.confirm-hero{background:linear-gradient(135deg,#f0fdfa 0%,#fff 70%);border:1px solid #ccfbf1;border-radius:22px;padding:28px}.confirm-icon{width:56px;height:56px;border-radius:18px;display:grid;place-items:center;background:#0f766e;color:#fff;font-size:1.55rem;flex:0 0 auto}.confirm-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:22px;height:100%}.confirm-label{font-size:.76rem;text-transform:uppercase;letter-spacing:.07em;color:#64748b;font-weight:700;margin-bottom:5px}.confirm-value{color:#0f172a;font-weight:650}.time-panel{border-radius:18px;background:#0f766e;color:#fff;padding:22px}.note-panel{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px}.confirm-actions .btn{min-height:44px}.print-only{display:none}
@media(max-width:767.98px){.confirm-hero{padding:20px}.confirm-card{padding:18px}.confirm-actions .btn{width:100%}}
@media print{.navbar,.footer,.no-print{display:none!important}.print-only{display:block}.container{max-width:100%!important}.confirm-shell{max-width:100%;margin:0}.confirm-hero,.confirm-card,.time-panel,.note-panel{box-shadow:none!important;break-inside:avoid}.confirm-hero{background:#fff!important;border:1px solid #bbb}.time-panel{background:#fff!important;color:#000!important;border:1px solid #bbb}.time-panel .text-white-50{color:#555!important}body{background:#fff!important}}
</style>

<div class="container py-4 py-lg-5">
  <div class="confirm-shell">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3 no-print">
      <a href="<?= url('client/appointments.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> My Appointments</a>
      <button type="button" class="btn btn-sm btn-outline-teal" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print confirmation</button>
    </div>

    <section class="confirm-hero mb-4">
      <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
        <div class="confirm-icon"><i class="bi bi-check2"></i></div>
        <div class="flex-grow-1">
          <div class="text-teal fw-semibold small mb-1">APPOINTMENT CONFIRMATION</div>
          <h1 class="h3 fw-bold mb-1">Your appointment is booked</h1>
          <p class="text-muted mb-0">Keep this confirmation for your records. You can manage the appointment from My Appointments.</p>
        </div>
        <div class="text-md-end">
          <div class="confirm-label">Reference</div>
          <div class="fs-5 fw-bold text-dark"><?= e($refNo) ?></div>
          <span class="badge bg-<?= e($statusClass) ?>-subtle text-<?= e($statusClass) ?> mt-1"><?= e(ucfirst(strtolower((string)$appt['Appt_Status']))) ?></span>
        </div>
      </div>
    </section>

    <section class="time-panel mb-4">
      <div class="row align-items-center g-3">
        <div class="col-md-7">
          <div class="small text-white-50 fw-semibold mb-1">DATE</div>
          <div class="h4 fw-bold mb-0"><i class="bi bi-calendar3 me-2"></i><?= date('l, d F Y', strtotime($appt['Slot_Date'])) ?></div>
        </div>
        <div class="col-md-5 text-md-end">
          <div class="small text-white-50 fw-semibold mb-1">TIME</div>
          <div class="h5 fw-bold mb-0"><i class="bi bi-clock me-2"></i><?= formatTime($appt['Start_Time']) ?> – <?= formatTime($appt['End_Time']) ?></div>
        </div>
      </div>
    </section>

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <section class="confirm-card">
          <div class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-person-badge fs-5 text-teal"></i><h2 class="h6 fw-bold mb-0">Doctor & specialty</h2></div>
          <div class="confirm-label">Doctor</div><div class="confirm-value fs-5 mb-3"><?= e($doctorName) ?></div>
          <div class="confirm-label">Specialty</div><div class="confirm-value mb-3"><?= e($appt['Specializations'] ?: 'General consultation') ?></div>
          <?php if (!empty($appt['Medical_License_No'])): ?><div class="confirm-label">Medical licence</div><div class="confirm-value"><?= e($appt['Medical_License_No']) ?></div><?php endif; ?>
        </section>
      </div>
      <div class="col-md-6">
        <section class="confirm-card">
          <div class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-geo-alt fs-5 text-teal"></i><h2 class="h6 fw-bold mb-0">Location</h2></div>
          <div class="confirm-label">Provider / centre</div><div class="confirm-value fs-5 mb-3"><?= e($facilityName) ?></div>
          <div class="confirm-label">Address</div><div class="confirm-value mb-3"><?= e(trim(($appt['Clinic_Address'] ?: '') . (($appt['Clinic_Address'] && $appt['Clinic_City']) ? ', ' : '') . ($appt['Clinic_City'] ?: ''))) ?></div>
          <?php if (!empty($appt['Clinic_Phone'])): ?><div class="confirm-label">Contact</div><div class="confirm-value"><i class="bi bi-telephone me-1"></i><?= e($appt['Clinic_Phone']) ?></div><?php endif; ?>
        </section>
      </div>
    </div>

    <section class="confirm-card mb-4">
      <div class="row g-3">
        <div class="col-md-6"><div class="confirm-label">Booked for</div><div class="confirm-value"><?= e(trim($appt['Patient_First'].' '.$appt['Patient_Last'])) ?></div></div>
        <div class="col-md-6"><div class="confirm-label">Booking created</div><div class="confirm-value"><?= date('d M Y, h:i A', strtotime($appt['Created_At'] ?: $appt['Booking_DateTime'])) ?></div></div>
      </div>
      <?php if (!empty($appt['Notes'])): ?>
        <div class="note-panel mt-3"><div class="confirm-label"><i class="bi bi-journal-text me-1"></i>Booking notes</div><div class="text-dark"><?= nl2br(e($appt['Notes'])) ?></div></div>
      <?php endif; ?>
    </section>

    <div class="note-panel mb-4">
      <div class="d-flex gap-2"><i class="bi bi-info-circle text-teal mt-1"></i><div><strong class="d-block mb-1">Before your visit</strong><span class="text-muted small">Check the appointment details above and use My Appointments if you need to manage the booking. Any consultation or facility charges are handled according to the provider's own arrangements.</span></div></div>
    </div>

    <div class="confirm-actions d-flex flex-column flex-sm-row gap-2 justify-content-center no-print">
      <a href="<?= url('client/appointments.php') ?>" class="btn btn-teal px-4"><i class="bi bi-calendar2-check me-2"></i>View My Appointments</a>
      <a href="<?= url('client/search.php') ?>" class="btn btn-outline-teal px-4"><i class="bi bi-search me-2"></i>Find Another Doctor</a>
    </div>
    <div class="print-only text-center small text-muted mt-4">MediLink Sri Lanka · Appointment reference <?= e($refNo) ?></div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
