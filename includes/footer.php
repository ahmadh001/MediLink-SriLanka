<?php

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';
?>
</main>

<footer class="footer-custom">
  <div class="container">
    <div class="footer-main">
      <div class="footer-brand">
        <a class="footer-logo" href="<?= url('public/index.php') ?>" aria-label="<?= APP_NAME ?> home">
          <span class="footer-logo-icon"><i class="bi bi-hospital"></i></span>
          <span><?= APP_NAME ?></span>
        </a>
        <p>Find healthcare providers and manage appointments in one simple place.</p>
      </div>

      <nav class="footer-links" aria-label="Footer utility navigation">
        <a href="<?= url('public/privacy.php') ?>">Privacy</a>
        <a href="<?= url('public/terms.php') ?>">Terms</a>
        <a href="<?= url('public/support.php') ?>">Support</a>
      </nav>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</span>
      <span class="footer-note"><i class="bi bi-shield-check"></i> Secure appointment platform</span>
    </div>
  </div>
</footer>

<!-- App-wide styled confirmation modal -->
<div class="modal fade ml-confirm-modal" id="mlConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body p-4">
    <div class="d-flex gap-3"><div class="ml-confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div><div><h5 class="fw-bold mb-1" id="mlConfirmTitle">Please confirm</h5><p class="text-muted mb-0" id="mlConfirmText">Are you sure?</p></div></div>
  </div><div class="modal-footer border-0 pt-0 px-4 pb-4"><button class="btn btn-light" data-bs-dismiss="modal">Keep current</button><button class="btn btn-danger" id="mlConfirmYes">Confirm</button></div></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= url('public/js/app.js') ?>"></script>
</body>
</html>
