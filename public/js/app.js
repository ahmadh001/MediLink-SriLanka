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

  // 2. Leaflet CDN Icon Path Fix
  if (typeof L !== 'undefined' && L.Icon && L.Icon.Default) {
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
      iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
      iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
      shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });
  }

  // 3. Geolocation & Interactive Map Picker
  const mapContainer = document.getElementById('map-picker');
  const detectLocationBtn = document.getElementById('btn-detect-location');
  const addressInput = document.getElementById('address-input') || document.querySelector('input[name="address"]');
  const latInput = document.getElementById('latitude-input') || document.getElementById('latitude');
  const lngInput = document.getElementById('longitude-input') || document.getElementById('longitude');
  const citySelect = document.getElementById('city-select') || document.getElementById('city');
  const locFeedback = document.getElementById('location-feedback');
  const coordsBadge = document.getElementById('pin-coords-badge');

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

  // Reverse Geocoding helper using OpenStreetMap Nominatim
  async function fetchAddressFromCoords(lat, lng) {
    try {
      const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`, {
        headers: { 'Accept-Language': 'en' }
      });
      if (!res.ok) return null;
      const data = await res.json();
      if (!data || !data.address) return null;

      const a = data.address;
      const parts = [];
      const house = a.house_number || a.building;
      const road = a.road || a.pedestrian || a.street;
      if (house && road) {
        parts.push(`${house}, ${road}`);
      } else if (road) {
        parts.push(road);
      }

      const sub = a.suburb || a.neighbourhood || a.locality || a.village || a.residential;
      if (sub && !parts.includes(sub)) {
        parts.push(sub);
      }

      let formatted = parts.join(', ');
      if (!formatted && data.display_name) {
        formatted = data.display_name.split(',').slice(0, 3).join(',').trim();
      }

      return {
        formatted: formatted,
        city: a.city || a.town || a.county || a.state_district || a.state
      };
    } catch (err) {
      return null;
    }
  }

  // Auto-select best matching city in dropdown
  function selectMatchingCity(cityName, lat, lng) {
    if (!citySelect) return;
    if (cityName) {
      const cleanTarget = cityName.toLowerCase().replace(/district|city/g, '').trim();
      for (let i = 0; i < citySelect.options.length; i++) {
        const optVal = citySelect.options[i].value.toLowerCase();
        const optText = citySelect.options[i].text.toLowerCase();
        if (optVal.includes(cleanTarget) || cleanTarget.includes(optVal) || optText.includes(cleanTarget)) {
          citySelect.selectedIndex = i;
          return;
        }
      }
    }
    // Geometric distance fallback
    let bestIdx = -1;
    let minD = Infinity;
    for (let i = 0; i < citySelect.options.length; i++) {
      const opt = citySelect.options[i];
      const cLat = parseFloat(opt.dataset.lat);
      const cLng = parseFloat(opt.dataset.lng);
      if (!isNaN(cLat) && !isNaN(cLng)) {
        const d = Math.hypot(lat - cLat, lng - cLng);
        if (d < minD) {
          minD = d;
          bestIdx = i;
        }
      }
    }
    if (bestIdx >= 0) {
      citySelect.selectedIndex = bestIdx;
    }
  }

  if (mapContainer && typeof L !== 'undefined' && latInput && lngInput) {
    let curLat = parseFloat(latInput.value);
    let curLng = parseFloat(lngInput.value);
    if (isNaN(curLat) || curLat === 0) curLat = 6.9271;
    if (isNaN(curLng) || curLng === 0) curLng = 79.8612;

    const map = L.map('map-picker', {
      scrollWheelZoom: false
    }).setView([curLat, curLng], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const marker = L.marker([curLat, curLng], {
      draggable: true
    }).addTo(map);

    function updateLocation(lat, lng, desc, shouldReverseGeocode = false) {
      const latNum = parseFloat(lat);
      const lngNum = parseFloat(lng);
      latInput.value = latNum.toFixed(6);
      lngInput.value = lngNum.toFixed(6);

      if (coordsBadge) {
        coordsBadge.textContent = `Lat: ${latNum.toFixed(4)}, Lng: ${lngNum.toFixed(4)}`;
      }
      if (locFeedback) {
        locFeedback.innerHTML = `<span class="text-success"><i class="bi bi-geo-alt-fill me-1"></i> ${desc || 'Pin location'} (${latNum.toFixed(4)}, ${lngNum.toFixed(4)})</span>`;
      }

      if (shouldReverseGeocode) {
        fetchAddressFromCoords(latNum, lngNum).then(geo => {
          if (geo && geo.formatted) {
            if (addressInput) {
              addressInput.value = geo.formatted;
            }
            selectMatchingCity(geo.city, latNum, lngNum);
            if (locFeedback) {
              locFeedback.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Address detected: ${geo.formatted}</span>`;
            }
          }
        });
      }
    }

    // Map Click
    map.on('click', (e) => {
      marker.setLatLng(e.latlng);
      updateLocation(e.latlng.lat, e.latlng.lng, 'Pinned to clicked location', true);
    });

    // Marker Drag
    marker.on('dragend', () => {
      const pos = marker.getLatLng();
      updateLocation(pos.lat, pos.lng, 'Pinned to dragged position', true);
    });

    // City Dropdown Change
    if (citySelect) {
      citySelect.addEventListener('change', (e) => {
        const opt = citySelect.options[citySelect.selectedIndex];
        let lat = parseFloat(opt.dataset.lat);
        let lng = parseFloat(opt.dataset.lng);
        const cityName = e.target.value;

        if (isNaN(lat) || isNaN(lng)) {
          if (cityCoordinates[cityName]) {
            lat = cityCoordinates[cityName].lat;
            lng = cityCoordinates[cityName].lng;
          }
        }

        if (!isNaN(lat) && !isNaN(lng)) {
          map.flyTo([lat, lng], 13, { duration: 1.2 });
          marker.setLatLng([lat, lng]);
          updateLocation(lat, lng, `Centered on ${cityName}`, false);
        }
      });
    }

    // Detect GPS Button
    if (detectLocationBtn) {
      detectLocationBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (!navigator.geolocation) {
          if (locFeedback) {
            locFeedback.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> Geolocation is not supported by your browser.</span>';
          }
          return;
        }

        const originalText = detectLocationBtn.innerHTML;
        detectLocationBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Locating address...';
        detectLocationBtn.disabled = true;

        navigator.geolocation.getCurrentPosition(
          (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            map.flyTo([lat, lng], 15, { duration: 1.4 });
            marker.setLatLng([lat, lng]);
            updateLocation(lat, lng, 'Current GPS location detected', true);
            detectLocationBtn.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Location Detected';
            detectLocationBtn.disabled = false;
            setTimeout(() => { detectLocationBtn.innerHTML = originalText; }, 4000);
          },
          (error) => {
            detectLocationBtn.innerHTML = originalText;
            detectLocationBtn.disabled = false;
            let msg = 'Unable to retrieve your location.';
            if (error.code === error.PERMISSION_DENIED) {
              msg = 'Location permission denied. Please pick your city or click on the map.';
            }
            if (locFeedback) {
              locFeedback.innerHTML = `<span class="text-warning"><i class="bi bi-info-circle"></i> ${msg}</span>`;
            }
          },
          { timeout: 10000, enableHighAccuracy: true }
        );
      });
    }

    // Invalidate size to ensure proper tiles rendering
    setTimeout(() => { map.invalidateSize(); }, 350);
    window.addEventListener('resize', () => { map.invalidateSize(); });

    // In case radio toggle changes visible sections
    const roleRadios = document.querySelectorAll('input[name="role_type"]');
    roleRadios.forEach(r => r.addEventListener('change', () => {
      setTimeout(() => { map.invalidateSize(); }, 200);
    }));

  } else if (detectLocationBtn && latInput && lngInput) {
    // Fallback if map container is not present on the page
    detectLocationBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition((position) => {
        latInput.value = position.coords.latitude.toFixed(6);
        lngInput.value = position.coords.longitude.toFixed(6);
        fetchAddressFromCoords(position.coords.latitude, position.coords.longitude).then(geo => {
          if (geo && geo.formatted && addressInput) {
            addressInput.value = geo.formatted;
            selectMatchingCity(geo.city, position.coords.latitude, position.coords.longitude);
          }
        });
        if (locFeedback) {
          locFeedback.innerHTML = `<span class="text-success"><i class="bi bi-geo-alt-fill"></i> GPS Coordinates: ${latInput.value}, ${lngInput.value}</span>`;
        }
      });
    });

    if (citySelect) {
      citySelect.addEventListener('change', (e) => {
        const opt = citySelect.options[citySelect.selectedIndex];
        const lat = opt.dataset.lat || (cityCoordinates[e.target.value] ? cityCoordinates[e.target.value].lat : null);
        const lng = opt.dataset.lng || (cityCoordinates[e.target.value] ? cityCoordinates[e.target.value].lng : null);
        if (lat && lng) {
          latInput.value = parseFloat(lat).toFixed(6);
          lngInput.value = parseFloat(lng).toFixed(6);
        }
      });
    }
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
