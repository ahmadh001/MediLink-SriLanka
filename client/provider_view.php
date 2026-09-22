<?php


$pageTitle = 'Provider Profile & Booking';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/subscription.php';

$providerId = (int)($_GET['id'] ?? 0);
if ($providerId <= 0) {
    setFlash('danger', 'Invalid provider requested.');
    redirect('client/search.php');
}

$db = Database::getConnection();


$pStmt = $db->prepare("
    SELECT p.*, u.First_Name, u.Last_Name, u.Email
    FROM `PROVIDER` p
    JOIN `USER` u ON p.User_ID = u.User_ID
    WHERE p.Provider_ID = ? AND p.Verification_Status = 'VERIFIED' AND u.Account_Status = 'ACTIVE'
");
$pStmt->execute([$providerId]);
$provider = $pStmt->fetch();

if (!$provider) {
    setFlash('danger', 'Provider not found or currently inactive.');
    redirect('client/search.php');
}

$providerType = $provider['Provider_Type'];
$doctor = null;
$centre = null;
$specializations = [];
$affiliatedDoctors = [];
$affiliatedCentres = [];

if ($providerType === PROVIDER_DOCTOR) {
    $dStmt = $db->prepare("SELECT * FROM `DOCTOR` WHERE Provider_ID = ?");
    $dStmt->execute([$providerId]);
    $doctor = $dStmt->fetch();

    if ($doctor) {
        $sStmt = $db->prepare("
            SELECT s.Name, s.Description
            FROM `DOCTOR_SPECIALIZATION` ds
            JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
            WHERE ds.Doctor_ID = ?
        ");
        $sStmt->execute([$doctor['Doctor_ID']]);
        $specializations = $sStmt->fetchAll();

        
        $cStmt = $db->prepare("
            SELECT hc.Centre_Name, p.City, p.Address, p.Provider_ID
            FROM `CENTRE_DOCTOR_LINK` cdl
            JOIN `HEALTHCARE_CENTRE` hc ON cdl.Centre_ID = hc.Centre_ID
            JOIN `PROVIDER` p ON hc.Provider_ID = p.Provider_ID
            WHERE cdl.Doctor_ID = ? AND cdl.Status = 'ACTIVE'
        ");
        $cStmt->execute([$doctor['Doctor_ID']]);
        $affiliatedCentres = $cStmt->fetchAll();
    }
} else { 
    $cStmt = $db->prepare("SELECT * FROM `HEALTHCARE_CENTRE` WHERE Provider_ID = ?");
    $cStmt->execute([$providerId]);
    $centre = $cStmt->fetch();

    if ($centre) {
        $adStmt = $db->prepare("
            SELECT d.Doctor_ID, d.Medical_License_No, u.First_Name, u.Last_Name,
                   GROUP_CONCAT(s.Name SEPARATOR ', ') AS Specializations
            FROM `CENTRE_DOCTOR_LINK` cdl
            JOIN `DOCTOR` d ON cdl.Doctor_ID = d.Doctor_ID
            JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
            JOIN `USER` u ON p.User_ID = u.User_ID
            LEFT JOIN `DOCTOR_SPECIALIZATION` ds ON d.Doctor_ID = ds.Doctor_ID
            LEFT JOIN `SPECIALIZATION` s ON ds.Specialization_ID = s.Specialization_ID
            WHERE cdl.Centre_ID = ? AND cdl.Status = 'ACTIVE'
            GROUP BY d.Doctor_ID
        ");
        $adStmt->execute([$centre['Centre_ID']]);
        $affiliatedDoctors = $adStmt->fetchAll();
    }
}


$slotsStmt = $db->prepare("
    SELECT s.*, 
           u.First_Name AS Doc_First, u.Last_Name AS Doc_Last, d.Medical_License_No
    FROM `SCHEDULED_SLOT` s
    LEFT JOIN `DOCTOR` d ON s.Doctor_ID = d.Doctor_ID
    LEFT JOIN `PROVIDER` p ON d.Provider_ID = p.Provider_ID
    LEFT JOIN `USER` u ON p.User_ID = u.User_ID
    WHERE s.Provider_ID = ?
      AND s.Slot_Date >= CURRENT_DATE
      AND s.Slot_Date <= DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY)
      AND s.Status = 'AVAILABLE'
    ORDER BY s.Slot_Date ASC, s.Start_Time ASC
");
$slotsStmt->execute([$providerId]);
$allAvailableSlots = $slotsStmt->fetchAll();


$slotsByDate = [];
foreach ($allAvailableSlots as $slot) {
    $slotsByDate[$slot['Slot_Date']][] = $slot;
}


$currentUser = getCurrentUser();
$userId = $currentUser['user_id'] ?? null;
$clientId = $currentUser['client_id'] ?? null;
$hasSub = hasActiveSubscription($userId);
$activeSub = getUserActiveSubscription($userId);
$quota = $clientId ? getMonthlyBookingQuota($clientId, $userId) : null;

require_once __DIR__ . '/../includes/header.php';

$displayName = $providerType === PROVIDER_DOCTOR
    ? 'Dr. ' . $provider['First_Name'] . ' ' . $provider['Last_Name']
    : ($centre['Centre_Name'] ?: $provider['Business_Name']);
$profileDescription = $providerType === PROVIDER_DOCTOR
    ? ($doctor['Professional_Bio'] ?: $provider['Description'])
    : ($centre['Description'] ?: $provider['Description']);
$bookingReady = $currentUser && $currentUser['role'] === ROLE_CLIENT && $hasSub && $quota && $quota['has_quota'];
?>

<div class="container py-4 provider-profile-v2">
  <nav aria-label="breadcrumb" class="profile-breadcrumb mb-3">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="<?= url('public/index.php') ?>">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= url('client/search.php') ?>">Find Doctors</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= e($displayName) ?></li>
    </ol>
  </nav>

  <section class="profile-hero-v2 mb-3">
    <div class="profile-avatar-v2"><i class="bi <?= $providerType === PROVIDER_DOCTOR ? 'bi-person' : 'bi-hospital' ?>"></i></div>
    <div class="profile-main-v2">
      <div class="profile-label-row">
        <span><i class="bi bi-patch-check-fill"></i> Verified <?= $providerType === PROVIDER_DOCTOR ? 'doctor' : 'healthcare centre' ?></span>
        <span><i class="bi bi-geo-alt"></i> <?= e($provider['City']) ?></span>
      </div>
      <h1><?= e($displayName) ?></h1>
      <?php if ($providerType === PROVIDER_DOCTOR && !empty($specializations)): ?>
        <div class="profile-specialties-v2">
          <?php foreach ($specializations as $sp): ?><span><?= e($sp['Name']) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="profile-facts-v2">
        <?php if ($providerType === PROVIDER_DOCTOR): ?>
          <span><i class="bi bi-award"></i><strong><?= (int)$doctor['Experience_Years'] ?> yrs</strong><small>Experience</small></span>
          <span><i class="bi bi-clock"></i><strong><?= (int)$doctor['Consultation_Duration'] ?> min</strong><small>Consultation</small></span>
          <span><i class="bi bi-card-text"></i><strong><?= e($doctor['Medical_License_No']) ?></strong><small>Medical licence</small></span>
        <?php else: ?>
          <span><i class="bi bi-card-text"></i><strong><?= e($centre['Registration_No']) ?></strong><small>Registration</small></span>
          <span><i class="bi bi-people"></i><strong><?= count($affiliatedDoctors) ?></strong><small>Linked doctors</small></span>
        <?php endif; ?>
      </div>
    </div>
    <aside class="profile-booking-state-v2">
      <span class="profile-state-title">Online booking</span>
      <?php if (!$currentUser): ?>
        <strong><i class="bi bi-box-arrow-in-right"></i> Sign in required</strong>
        <small>Sign in with a client account to reserve a slot.</small>
        <a href="<?= url('public/login.php') ?>" class="btn btn-teal btn-sm">Sign in to book</a>
      <?php elseif ($currentUser['role'] !== ROLE_CLIENT): ?>
        <strong><i class="bi bi-lock"></i> Client access only</strong>
        <small>Appointment reservations are available through client accounts.</small>
      <?php elseif (!$hasSub): ?>
        <strong><i class="bi bi-credit-card"></i> Plan required</strong>
        <small>An active client plan is required for online booking.</small>
        <a href="<?= url('client/subscription.php') ?>" class="btn btn-outline-teal btn-sm">View plans</a>
      <?php elseif (!$quota['has_quota']): ?>
        <strong><i class="bi bi-calendar-x"></i> Monthly quota used</strong>
        <small><?= (int)$quota['used'] ?> of <?= (int)$quota['limit'] ?> bookings used this month.</small>
        <a href="<?= url('client/subscription.php') ?>" class="btn btn-outline-teal btn-sm">Manage plan</a>
      <?php else: ?>
        <strong class="is-ready"><i class="bi bi-check-circle-fill"></i> Ready to book</strong>
        <small><?= (int)$quota['remaining'] ?> booking<?= (int)$quota['remaining'] === 1 ? '' : 's' ?> remaining this month.</small>
        <a href="#available-slots" class="btn btn-teal btn-sm">Choose a time</a>
      <?php endif; ?>
    </aside>
  </section>

  <div class="row g-3 mb-3">
    <div class="col-lg-7">
      <section class="profile-info-card-v2 h-100">
        <div class="profile-section-title"><i class="bi bi-person-lines-fill"></i><div><h2>About</h2><p>Provider information and consultation details</p></div></div>
        <p class="profile-bio-v2"><?= nl2br(e($profileDescription ?: 'No additional profile description has been provided.')) ?></p>
        <div class="profile-contact-v2">
          <div><i class="bi bi-geo-alt"></i><span><small>Clinic address</small><strong><?= e($provider['Address']) ?>, <?= e($provider['City']) ?></strong></span></div>
          <div><i class="bi bi-telephone"></i><span><small>Contact</small><strong><?= $currentUser ? e($provider['Contact_Number']) : 'Sign in to view' ?></strong></span></div>
        </div>
      </section>
    </div>
    <div class="col-lg-5">
      <section class="profile-info-card-v2 h-100">
        <div class="profile-section-title"><i class="bi bi-map"></i><div><h2>Location</h2><p><?= e($provider['City']) ?>, Sri Lanka</p></div></div>
        <div class="profile-map-v2">
          <iframe width="100%" height="100%" style="border:0" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=<?= urlencode($provider['Latitude'] . ',' . $provider['Longitude']) ?>&hl=en&z=15&output=embed"></iframe>
        </div>
        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $provider['Latitude'] ?>,<?= $provider['Longitude'] ?>" target="_blank" rel="noopener noreferrer" class="profile-direction-link"><i class="bi bi-sign-turn-right"></i> Get directions <i class="bi bi-arrow-up-right"></i></a>
      </section>
    </div>
  </div>

  <?php if ($providerType === PROVIDER_DOCTOR && !empty($affiliatedCentres)): ?>
    <section class="profile-linked-v2 mb-3">
      <div class="profile-section-title"><i class="bi bi-hospital"></i><div><h2>Consulting locations</h2><p>Partner healthcare centres linked to this doctor</p></div></div>
      <div class="profile-linked-grid-v2">
        <?php foreach ($affiliatedCentres as $ac): ?>
          <a href="<?= url('client/provider_view.php?id=' . $ac['Provider_ID']) ?>" class="profile-linked-item-v2"><i class="bi bi-building"></i><span><strong><?= e($ac['Centre_Name']) ?></strong><small><?= e($ac['Address']) ?>, <?= e($ac['City']) ?></small></span><i class="bi bi-chevron-right"></i></a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php elseif ($providerType === PROVIDER_CENTRE && !empty($affiliatedDoctors)): ?>
    <section class="profile-linked-v2 mb-3">
      <div class="profile-section-title"><i class="bi bi-people"></i><div><h2>Consulting doctors</h2><p>Medical practitioners linked to this centre</p></div></div>
      <div class="profile-linked-grid-v2">
        <?php foreach ($affiliatedDoctors as $ad): ?>
          <div class="profile-linked-item-v2 is-static"><i class="bi bi-person"></i><span><strong>Dr. <?= e($ad['First_Name'] . ' ' . $ad['Last_Name']) ?></strong><small><?= e($ad['Specializations'] ?: 'Consultant') ?> · <?= e($ad['Medical_License_No']) ?></small></span></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="profile-slots-v2" id="available-slots">
    <div class="slots-head-v2">
      <div class="profile-section-title mb-0"><i class="bi bi-calendar3"></i><div><h2>Available appointments</h2><p>Open times for the next 14 days</p></div></div>
      <?php if ($bookingReady): ?><span class="slots-ready-badge"><i class="bi bi-check-circle"></i> Select a time to continue</span><?php endif; ?>
    </div>

    <?php if (empty($slotsByDate)): ?>
      <div class="profile-slots-empty-v2"><i class="bi bi-calendar-x"></i><h3>No open appointments</h3><p>This provider has no online slots available during the next 14 days.</p><a href="<?= url('client/search.php') ?>" class="btn btn-outline-teal btn-sm">Find another provider</a></div>
    <?php else: ?>
      <div class="slot-days-v2">
        <?php foreach ($slotsByDate as $date => $daySlots): ?>
          <article class="slot-day-v2">
            <header><div><span><?= date('D', strtotime($date)) ?></span><strong><?= date('d', strtotime($date)) ?></strong></div><p><?= date('F Y', strtotime($date)) ?><small><?= count($daySlots) ?> open time<?= count($daySlots) === 1 ? '' : 's' ?></small></p></header>
            <div class="slot-times-v2">
              <?php foreach ($daySlots as $s): ?>
                <?php $docLabel = $s['Doc_First'] ? 'Dr. ' . $s['Doc_First'] . ' ' . $s['Doc_Last'] : ($providerType === PROVIDER_DOCTOR ? 'Dr. ' . $provider['First_Name'] . ' ' . $provider['Last_Name'] : $provider['Business_Name']); $timeLabel = formatTime($s['Start_Time']) . ' – ' . formatTime($s['End_Time']); ?>
                <?php if (!$currentUser || $currentUser['role'] !== ROLE_CLIENT): ?>
                  <a href="<?= url('public/login.php') ?>" class="slot-time-v2 is-locked"><i class="bi bi-lock"></i><span><?= $timeLabel ?></span></a>
                <?php elseif (!$hasSub || ($quota && !$quota['has_quota'])): ?>
                  <a href="<?= url('client/subscription.php') ?>" class="slot-time-v2 is-locked"><i class="bi bi-lock"></i><span><?= $timeLabel ?></span></a>
                <?php else: ?>
                  <button type="button" class="slot-time-v2" data-bs-toggle="modal" data-bs-target="#bookingModal" data-slot-id="<?= $s['Slot_ID'] ?>" data-slot-date="<?= formatDate($s['Slot_Date']) ?>" data-slot-time="<?= $timeLabel ?>" data-doctor-name="<?= e($docLabel) ?>" data-provider-name="<?= e($provider['Business_Name']) ?>"><i class="bi bi-clock"></i><span><?= $timeLabel ?></span><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
  <div class="modal-dialog booking-dialog-v2">
    <div class="modal-content booking-modal-v2">
      <form method="POST" action="<?= url('client/book.php') ?>" id="booking-form" data-no-loading="1">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="slot_id" id="modal-slot-id" value="">
        <input type="hidden" name="provider_id" value="<?= $providerId ?>">
        <div class="modal-header"><div><span class="booking-modal-kicker"><i class="bi bi-calendar-check"></i> Booking confirmation</span><h5 class="modal-title" id="bookingModalLabel">Review your appointment</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body" id="booking-modal-body">
          <p class="booking-modal-intro">Check the appointment details before reserving this time.</p>
          <div class="booking-summary-v2">
            <div><i class="bi bi-person"></i><span><small>Doctor</small><strong id="modal-doctor-name"></strong></span></div>
            <div><i class="bi bi-building"></i><span><small>Provider</small><strong id="modal-provider-name"></strong></span></div>
            <div><i class="bi bi-calendar3"></i><span><small>Date</small><strong id="modal-slot-date"></strong></span></div>
            <div><i class="bi bi-clock"></i><span><small>Time</small><strong id="modal-slot-time"></strong></span></div>
            <div><i class="bi bi-geo-alt"></i><span><small>Location</small><strong><?= e($provider['Address']) ?>, <?= e($provider['City']) ?></strong></span></div>
          </div>
          <div class="mt-3"><label for="booking-notes" class="form-label small fw-semibold">Notes for the appointment <span class="text-muted fw-normal">(optional)</span></label><textarea id="booking-notes" name="notes" class="form-control" rows="3" maxlength="1000" placeholder="Add a short note for the provider"></textarea></div>
          <div class="booking-fee-note-v2"><i class="bi bi-info-circle"></i><span>Online booking reserves the appointment time. Consultation charges are handled by the healthcare provider.</span></div>
        </div>
        <div class="modal-footer" id="booking-modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Go back</button><button type="submit" class="btn btn-teal btn-sm px-4" id="booking-confirm-btn"><i class="bi bi-check-circle me-1"></i> Confirm appointment</button></div>
      </form>
    </div>
  </div>
</div>


<style>
/* Booking confirmation modal — one layout system only (no Bootstrap centered/scrollable conflict). */
#bookingModal.modal{
  position:fixed!important;
  inset:0!important;
  top:0!important;
  right:0!important;
  bottom:0!important;
  left:0!important;
  width:100vw!important;
  height:100vh!important;
  height:100dvh!important;
  z-index:1060!important;
  overflow:hidden!important;
}
#bookingModal + .modal-backdrop,
.modal-backdrop{position:fixed!important;inset:0!important;}

#bookingModal.show{
  position:fixed!important;
  inset:0!important;
  display:flex!important;
  align-items:center;
  justify-content:center;
  padding:16px!important;
  overflow:hidden;
}
#bookingModal .booking-dialog-v2{
  flex:0 1 560px;
  width:100%;
  max-width:560px;
  height:auto;
  max-height:calc(100vh - 32px);
  max-height:calc(100dvh - 32px);
  margin:0!important;
  transform:none;
}
#bookingModal.fade .booking-dialog-v2{transform:translateY(12px) scale(.985)}
#bookingModal.show .booking-dialog-v2{transform:none}
#bookingModal .booking-modal-v2{
  width:100%;
  max-height:calc(100vh - 32px);
  max-height:calc(100dvh - 32px);
  display:block;
  overflow:hidden;
  border:0;
  border-radius:18px;
  box-shadow:0 24px 70px rgba(20,40,38,.20);
}
#bookingModal #booking-form{
  width:100%;
  max-height:calc(100vh - 32px);
  max-height:calc(100dvh - 32px);
  display:flex;
  flex-direction:column;
  overflow:hidden;
}
#bookingModal .modal-header{
  flex:0 0 auto;
  padding:.8rem 1.05rem;
}
#bookingModal .modal-body{
  flex:1 1 auto;
  min-height:0;
  overflow-y:auto!important;
  overflow-x:hidden;
  overscroll-behavior:contain;
  padding:.8rem 1.05rem;
}
#bookingModal .modal-footer{
  flex:0 0 auto;
  display:flex;
  flex-direction:row;
  justify-content:flex-end;
  gap:.5rem;
  padding:.7rem 1.05rem;
}
#bookingModal .modal-footer .btn,#bookingModal .modal-footer a.btn{
  width:auto;
  margin:0;
}
#bookingModal .booking-modal-intro{margin-bottom:.55rem}
#bookingModal .booking-summary-v2{gap:.4rem}
#bookingModal .booking-summary-v2>div{padding:.48rem .58rem}
#bookingModal .mt-3{margin-top:.65rem!important}
#bookingModal textarea{min-height:62px;max-height:82px;resize:vertical}
#bookingModal .booking-fee-note-v2{margin-top:.55rem;padding:.5rem .55rem}
.booking-success-v2{text-align:center;padding:1.2rem .5rem}
.booking-success-v2 i{font-size:2.4rem;color:var(--primary-teal,#087f78)}
.slot-time-v2.is-booked-live{opacity:.58;pointer-events:none}
.slot-time-v2.is-booked-live .bi-clock{display:none}
body.modal-open{overflow:hidden!important}

@media(max-width:575.98px){
  #bookingModal.show{padding:8px!important}
  #bookingModal .booking-dialog-v2,
  #bookingModal .booking-modal-v2,
  #bookingModal #booking-form{
    max-height:calc(100vh - 16px);
    max-height:calc(100dvh - 16px);
  }
  #bookingModal .booking-modal-v2{border-radius:14px}
  #bookingModal .modal-header{padding:.7rem .8rem}
  #bookingModal .modal-body{padding:.7rem .8rem}
  #bookingModal .modal-footer{padding:.6rem .8rem}
  #bookingModal .booking-summary-v2{grid-template-columns:1fr}
  #bookingModal .booking-summary-v2>div:last-child{grid-column:auto}
}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('booking-form'), modalEl=document.getElementById('bookingModal');
 if(!form||!modalEl)return;
 // Keep the Bootstrap modal outside transformed/positioned page containers.
 // This makes fixed positioning relative to the viewport, not the scrolled profile page.
 if(modalEl.parentElement!==document.body) document.body.appendChild(modalEl);
 const btn=document.getElementById('booking-confirm-btn'), body=document.getElementById('booking-modal-body'), footer=document.getElementById('booking-modal-footer');
 const originalBody=body.innerHTML, originalFooter=footer.innerHTML;
 let activeSlotButton=null;
 modalEl.addEventListener('show.bs.modal',e=>{activeSlotButton=e.relatedTarget||null;});
 modalEl.addEventListener('hidden.bs.modal',()=>{
   if(body.dataset.success==='1'){body.innerHTML=originalBody;footer.innerHTML=originalFooter;body.dataset.success='0';form.reset();}
 });
 form.addEventListener('submit',async e=>{
   e.preventDefault();
   const submitBtn=form.querySelector('#booking-confirm-btn');
   if(!submitBtn||submitBtn.disabled)return;
   const old=submitBtn.innerHTML;submitBtn.disabled=true;submitBtn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Reserving…';
   try{
     const res=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
     const data=await res.json();
     if(!res.ok||!data.ok)throw new Error(data.message||'Unable to reserve this appointment.');
     const booked=activeSlotButton;
     if(booked){booked.classList.add('is-booked-live');booked.disabled=true;booked.removeAttribute('data-bs-toggle');booked.removeAttribute('data-bs-target');booked.innerHTML='<i class="bi bi-check-circle"></i><span>Request sent</span>';}
     const day=booked?.closest('.slot-day-v2');
     if(day){const remaining=day.querySelectorAll('.slot-time-v2:not(.is-booked-live)').length;const small=day.querySelector('header p small');if(small)small.textContent=remaining+' open time'+(remaining===1?'':'s');}
     body.dataset.success='1';
     body.innerHTML='<div class="booking-success-v2"><i class="bi bi-check-circle-fill"></i><h4 class="fw-bold mt-3">Appointment request sent</h4><p class="text-muted mb-2">'+data.message+'</p><div class="badge text-bg-light border fs-6">'+data.reference+'</div></div>';
     footer.innerHTML='<button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button><a class="btn btn-teal btn-sm px-4" href="'+data.redirect_url+'">View receipt</a>';
   }catch(err){
     let alert=form.querySelector('.booking-ajax-error');
     if(!alert){alert=document.createElement('div');alert.className='alert alert-danger booking-ajax-error py-2 small mt-3';body.appendChild(alert);}
     alert.textContent=err.message;
     submitBtn.disabled=false;submitBtn.innerHTML=old;
   }
 });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
