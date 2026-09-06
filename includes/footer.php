<?php
/**
 * Footer Component
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';
?>
</main>

<footer class="footer-custom">
  <div class="container">
    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <h5 class="text-white fw-bold d-flex align-items-center mb-3">
          <i class="bi bi-hospital me-2 text-info"></i> <?= APP_NAME ?>
        </h5>
        <p class="small text-secondary">
          Sri Lanka's dedicated subscription-gated medical mediator platform. Bridging verified healthcare providers, specialists, and patients across the island.
        </p>
        <div class="d-flex gap-2 text-secondary small">
          <span class="badge bg-dark border border-secondary"><i class="bi bi-shield-check text-success me-1"></i> SLMC Standards</span>
        </div>
      </div>

      <div class="col-6 col-lg-2">
        <h6 class="text-white fw-semibold mb-3">Quick Links</h6>
        <ul class="list-unstyled small d-flex flex-column gap-2">
          <li><a href="<?= url('public/index.php') ?>">Home</a></li>
          <li><a href="<?= url('public/plans.php') ?>">Subscription Plans</a></li>
          <li><a href="<?= url('public/about.php') ?>">About & Disclaimer</a></li>
          <li><a href="<?= url('client/search.php') ?>">Find a Specialist</a></li>
        </ul>
      </div>

      <div class="col-6 col-lg-3">
        <h6 class="text-white fw-semibold mb-3">Key Healthcare Hubs</h6>
        <ul class="list-unstyled small d-flex flex-column gap-2 text-secondary">
          <li><i class="bi bi-geo-alt text-info me-1"></i> Colombo 07 & Greater Colombo</li>
          <li><i class="bi bi-geo-alt text-info me-1"></i> Kandy Central Medical Zone</li>
          <li><i class="bi bi-geo-alt text-info me-1"></i> Galle Coastal Healthcare</li>
          <li><i class="bi bi-geo-alt text-info me-1"></i> Negombo & Gampaha Hubs</li>
        </ul>
      </div>

      <div class="col-lg-3">
        <h6 class="text-white fw-semibold mb-3">Important Notice</h6>
        <p class="small text-secondary mb-2">
          MediLink manages subscription-gated discovery and appointment scheduling only. Consultation and medical fees are settled directly with the healthcare provider.
        </p>
        <small class="text-secondary"><i class="bi bi-shield-check text-success me-1"></i> Certified Sri Lankan Healthcare Mediator • MOH Standards</small>
      </div>
    </div>

    <hr class="border-secondary my-3">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-secondary">
      <div>&copy; <?= date('Y') ?> <?= APP_NAME ?> (PVT) Ltd. All Rights Reserved.</div>
      <div><i class="bi bi-lock-fill text-teal me-1"></i> 256-Bit SSL Encrypted Healthcare Portal • Ministry of Health Compliant</div>
    </div>
  </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= url('public/js/app.js') ?>"></script>
</body>
</html>
