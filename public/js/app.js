/**
 * MediLink Sri Lanka - Client JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Auto-dismiss Flash Alerts after 5 seconds
  const flashAlerts = document.querySelectorAll('.alert-dismissible');
  flashAlerts.forEach(alert => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
      if (bsAlert) bsAlert.close();
    }, 6000);
  });

  // 2. Geolocation Auto-Detection Button
  const detectLocationBtn = document.getElementById('btn-detect-location');
  const latInput = document.getElementById('latitude-input') || document.getElementById('latitude');
  const lngInput = document.getElementById('longitude-input') || document.getElementById('longitude');
  const citySelect = document.getElementById('city-select') || document.getElementById('city');
  const locFeedback = document.getElementById('location-feedback');

  if (detectLocationBtn && latInput && lngInput) {
    detectLocationBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (!navigator.geolocation) {
        if (locFeedback) {
          locFeedback.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> Geolocation is not supported by your browser.</span>';
        }
        return;
      }

      detectLocationBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Locating...';
      detectLocationBtn.disabled = true;

      navigator.geolocation.getCurrentPosition(
        (position) => {
          const lat = position.coords.latitude.toFixed(6);
          const lng = position.coords.longitude.toFixed(6);
          latInput.value = lat;
          lngInput.value = lng;
          detectLocationBtn.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Location Detected';
          detectLocationBtn.disabled = false;
          if (locFeedback) {
            locFeedback.innerHTML = `<span class="text-success"><i class="bi bi-geo-alt-fill"></i> GPS Coordinates: ${lat}, ${lng}</span>`;
          }
        },
        (error) => {
          detectLocationBtn.innerHTML = '<i class="bi bi-geo-alt"></i> Detect My Location';
          detectLocationBtn.disabled = false;
          let msg = 'Unable to retrieve your location.';
          if (error.code === error.PERMISSION_DENIED) {
            msg = 'Location permission denied. Please select your city from the dropdown.';
          }
          if (locFeedback) {
            locFeedback.innerHTML = `<span class="text-warning"><i class="bi bi-info-circle"></i> ${msg}</span>`;
          }
        },
        { timeout: 10000, enableHighAccuracy: true }
      );
    });
  }

  // 3. City Selection coordinate auto-fill preset
  const cityCoordinates = {
    'Colombo': { lat: 6.9271, lng: 79.8612 },
    'Kandy': { lat: 7.2906, lng: 80.6337 },
    'Galle': { lat: 6.0535, lng: 80.2210 },
    'Negombo': { lat: 7.2008, lng: 79.8737 },
    'Gampaha': { lat: 7.0840, lng: 79.9939 },
    'Kurunegala': { lat: 7.4818, lng: 80.3609 },
    'Matara': { lat: 5.9549, lng: 80.5550 },
    'Jaffna': { lat: 9.6615, lng: 80.0255 },
    'Anuradhapura': { lat: 8.3114, lng: 80.4037 },
    'Batticaloa': { lat: 7.7310, lng: 81.6747 },
    'Ratnapura': { lat: 6.6828, lng: 80.4000 },
    'Badulla': { lat: 6.9934, lng: 81.0550 },
    'Kalutara': { lat: 6.5854, lng: 79.9607 }
  };

  if (citySelect && latInput && lngInput) {
    citySelect.addEventListener('change', (e) => {
      const selectedCity = e.target.value;
      if (cityCoordinates[selectedCity]) {
        // Only override if lat/lng are empty or default
        if (!latInput.value || latInput.value === '0.00000000' || latInput.dataset.manual !== 'true') {
          latInput.value = cityCoordinates[selectedCity].lat.toFixed(6);
          lngInput.value = cityCoordinates[selectedCity].lng.toFixed(6);
          if (locFeedback) {
            locFeedback.innerHTML = `<span class="text-muted"><i class="bi bi-geo"></i> Preset coordinates set for ${selectedCity}</span>`;
          }
        }
      }
    });

    // Mark manual edits
    latInput.addEventListener('input', () => { latInput.dataset.manual = 'true'; });
    lngInput.addEventListener('input', () => { lngInput.dataset.manual = 'true'; });
  }

  // 4. Booking Modal Slot Selection Helper
  const bookingModal = document.getElementById('bookingModal');
  if (bookingModal) {
    bookingModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;

      const slotId = button.getAttribute('data-slot-id');
      const date = button.getAttribute('data-slot-date');
      const time = button.getAttribute('data-slot-time');
      const doctorName = button.getAttribute('data-doctor-name');
      const providerName = button.getAttribute('data-provider-name');

      const modalSlotInput = bookingModal.querySelector('#modal-slot-id');
      const modalDate = bookingModal.querySelector('#modal-slot-date');
      const modalTime = bookingModal.querySelector('#modal-slot-time');
      const modalDoctor = bookingModal.querySelector('#modal-doctor-name');
      const modalProvider = bookingModal.querySelector('#modal-provider-name');

      if (modalSlotInput) modalSlotInput.value = slotId;
      if (modalDate) modalDate.textContent = date;
      if (modalTime) modalTime.textContent = time;
      if (modalDoctor) modalDoctor.textContent = doctorName;
      if (modalProvider) modalProvider.textContent = providerName;
    });
  }
});
