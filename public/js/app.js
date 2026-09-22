

document.addEventListener('DOMContentLoaded', () => {
  const flashAlerts = document.querySelectorAll('.alert-dismissible');
  flashAlerts.forEach(alert => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
      if (bsAlert) bsAlert.close();
    }, 6000);
  });
  if (typeof L !== 'undefined' && L.Icon && L.Icon.Default) {
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
      iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
      iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
      shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });
  }
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
        city: a.city || a.town || a.village || a.municipality || a.county || a.state_district || a.state,
        district: a.state_district || a.county || a.state || ''
      };
    } catch (err) {
      return null;
    }
  }
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
            selectMatchingCity(geo.district || geo.city, latNum, lngNum);
            citySelect?.dispatchEvent(new Event('change', { bubbles: true }));
            if (locFeedback) {
              locFeedback.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Address detected: ${geo.formatted}</span>`;
            }
          }
        });
      }
    }
    map.on('click', (e) => {
      marker.setLatLng(e.latlng);
      updateLocation(e.latlng.lat, e.latlng.lng, 'Pinned to clicked location', true);
    });
    marker.on('dragend', () => {
      const pos = marker.getLatLng();
      updateLocation(pos.lat, pos.lng, 'Pinned to dragged position', true);
    });
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
    if (detectLocationBtn) {
      detectLocationBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (!navigator.geolocation) {
          if (locFeedback) locFeedback.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> Geolocation is not supported by your browser.</span>';
          return;
        }

        const originalText = detectLocationBtn.innerHTML;
        detectLocationBtn.disabled = true;
        detectLocationBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Improving GPS accuracy...';
        if (locFeedback) locFeedback.innerHTML = '<span class="text-info"><i class="bi bi-crosshair me-1"></i> Waiting for the most accurate location reading…</span>';

        let best = null;
        let watchId = null;
        let finished = false;
        const finish = () => {
          if (finished) return;
          finished = true;
          if (watchId !== null) navigator.geolocation.clearWatch(watchId);
          detectLocationBtn.disabled = false;
          if (!best) {
            detectLocationBtn.innerHTML = originalText;
            if (locFeedback) locFeedback.innerHTML = '<span class="text-warning"><i class="bi bi-info-circle"></i> Could not obtain a reliable location. Check browser/Windows location permission or place the pin manually.</span>';
            return;
          }

          const { latitude: lat, longitude: lng, accuracy } = best.coords;
          map.flyTo([lat, lng], accuracy <= 100 ? 17 : 15, { duration: 1.2 });
          marker.setLatLng([lat, lng]);
          latInput.value = lat.toFixed(6);
          lngInput.value = lng.toFixed(6);
          if (coordsBadge) coordsBadge.textContent = `Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)} · ±${Math.round(accuracy)} m`;

          if (accuracy > 1000) {
            if (locFeedback) locFeedback.innerHTML = `<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> Approximate location only (±${Math.round(accuracy)} m). Please confirm or move the map pin before creating the account.</span>`;
          } else {
            if (locFeedback) locFeedback.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> High-accuracy location received (±${Math.round(accuracy)} m). Detecting address…</span>`;
            fetchAddressFromCoords(lat, lng).then(geo => {
              if (!geo) return;
              if (geo.formatted && addressInput) addressInput.value = geo.formatted;
              selectMatchingCity(geo.district || geo.city, lat, lng);
              citySelect?.dispatchEvent(new Event('change', { bubbles: true }));
              if (locFeedback) locFeedback.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Location detected within ±${Math.round(accuracy)} m${geo.formatted ? `: ${geo.formatted}` : ''}</span>`;
            });
          }
          detectLocationBtn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Location Detected';
          setTimeout(() => { detectLocationBtn.innerHTML = originalText; }, 4000);
        };

        const timeoutId = setTimeout(finish, 12000);
        watchId = navigator.geolocation.watchPosition(
          (position) => {
            if (!best || position.coords.accuracy < best.coords.accuracy) best = position;
            if (locFeedback) locFeedback.innerHTML = `<span class="text-info"><i class="bi bi-crosshair me-1"></i> Improving accuracy… best reading ±${Math.round(best.coords.accuracy)} m</span>`;
            if (best.coords.accuracy <= 50) { clearTimeout(timeoutId); finish(); }
          },
          (error) => {
            clearTimeout(timeoutId);
            if (error.code === error.PERMISSION_DENIED) {
              best = null;
              if (locFeedback) locFeedback.innerHTML = '<span class="text-warning"><i class="bi bi-info-circle"></i> Location permission denied. Allow precise location, then try again.</span>';
            }
            finish();
          },
          { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 }
        );
      });
    }
    setTimeout(() => { map.invalidateSize(); }, 350);
    window.addEventListener('resize', () => { map.invalidateSize(); });
    const roleRadios = document.querySelectorAll('input[name="role_type"]');
    roleRadios.forEach(r => r.addEventListener('change', () => {
      setTimeout(() => { map.invalidateSize(); }, 200);
    }));

  } else if (detectLocationBtn && latInput && lngInput) {
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


(() => {
  const initMotion = () => {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const candidates = document.querySelectorAll('main .card-custom, main .stat-card, main .dashboard-card');
    candidates.forEach((el, i) => {
      if (i < 18) el.classList.add('ml-reveal');
    });
    if (!('IntersectionObserver' in window)) {
      document.querySelectorAll('.ml-reveal').forEach(el => el.classList.add('ml-visible'));
      return;
    }
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('ml-visible');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });
    document.querySelectorAll('.ml-reveal').forEach(el => observer.observe(el));
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initMotion);
  else initMotion();
})();


(() => {
  const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const onReady = (fn) => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  };

  onReady(() => {
    
    const navbar = document.querySelector('.navbar-custom');
    if (navbar) {
      const applyScrollState = () => {
        navbar.classList.toggle('is-scrolled', window.scrollY > 8);
      };
      applyScrollState();
      window.addEventListener('scroll', applyScrollState, { passive: true });
    }

    
    document.querySelectorAll('.navbar-custom .nav-link[href]').forEach(link => {
      try {
        const linkPath = new URL(link.href, window.location.href).pathname;
        if (linkPath === window.location.pathname) link.classList.add('active');
      } catch (e) {  }
    });

    


    
    const statEls = document.querySelectorAll('.stat-value');
    statEls.forEach(el => {
      const raw = el.textContent.trim();
      if (!/^[0-9][0-9,]*$/.test(raw)) return; // not a plain integer, leave as-is
      const target = parseInt(raw.replace(/,/g, ''), 10);
      if (isNaN(target)) return;
      if (reduced || target === 0) { el.textContent = target.toLocaleString(); return; }

      el.textContent = '0';
      const duration = 900;
      const start = performance.now();
      const easeOutExpo = t => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t));

      const step = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const value = Math.round(target * easeOutExpo(progress));
        el.textContent = value.toLocaleString();
        if (progress < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });

    
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('invalid', (e) => {
        const field = e.target;
        field.classList.add('ml-shake');
        field.addEventListener('animationend', () => field.classList.remove('ml-shake'), { once: true });
      }, true);
    });

    
    const revealSelector = [
      'main .card-custom', 'main .card-stat', 'main .plan-card', 'main .auth-card',
      'main .feature-panel', 'main .info-panel', 'main .notice-panel', 'main .cta-panel',
      'main .about-visual-card', 'main .search-results-shell'
    ].join(', ');

    if (!reduced) {
      const candidates = document.querySelectorAll(revealSelector);
      candidates.forEach((el, i) => {
        if (el.classList.contains('ml-reveal')) return; // avoid double-binding
        el.classList.add('ml-reveal');
        el.style.setProperty('--d', (i % 8) * 55 + 'ms');
      });

      if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, obs) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              entry.target.classList.add('ml-visible');
              obs.unobserve(entry.target);
            }
          });
        }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });
        document.querySelectorAll('.ml-reveal:not(.ml-visible)').forEach(el => observer.observe(el));
      } else {
        document.querySelectorAll('.ml-reveal').forEach(el => el.classList.add('ml-visible'));
      }
    }

    
    const topBtn = document.createElement('button');
    topBtn.type = 'button';
    topBtn.className = 'ml-top-btn';
    topBtn.setAttribute('aria-label', 'Back to top');
    topBtn.innerHTML = '<i class="bi bi-arrow-up"></i>';
    document.body.appendChild(topBtn);
    const toggleTopBtn = () => topBtn.classList.toggle('is-visible', window.scrollY > 480);
    window.addEventListener('scroll', toggleTopBtn, { passive: true });
    toggleTopBtn();
    topBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
    });

    
    if (!reduced) {
      document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (!link) return;
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (link.target && link.target !== '' && link.target !== '_self') return;
        if (link.hasAttribute('download') || link.hasAttribute('data-bs-toggle')) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return;

        let url;
        try { url = new URL(href, window.location.href); } catch (err) { return; }
        if (url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search) return;

        e.preventDefault();
        document.body.classList.add('ml-leaving');
        setTimeout(() => { window.location.href = url.href; }, 170);
      });
    }

    
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', () => {
        if (!form.checkValidity()) return; // native validation will block + trigger shake above
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn && !submitBtn.classList.contains('btn-loading')) {
          submitBtn.classList.add('btn-loading');
        }
      });
    });
  });
})();
(() => {
  document.querySelectorAll('[data-ml-toast]').forEach(t => {
    const close=()=>{t.style.opacity='0';t.style.transform='translateX(18px)';setTimeout(()=>t.remove(),220)};
    t.querySelector('[data-ml-toast-close]')?.addEventListener('click',close);
    setTimeout(close,6000);
  });
  let pendingForm=null;
  const modalEl=document.getElementById('mlConfirmModal');
  const modal=modalEl && typeof bootstrap!=='undefined' ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{
    if(form.dataset.confirmed==='1') return;
    e.preventDefault(); pendingForm=form;
    document.getElementById('mlConfirmText').textContent=form.dataset.confirm || 'Are you sure?';
    modal?.show();
  }));
  document.getElementById('mlConfirmYes')?.addEventListener('click',()=>{
    if(!pendingForm)return; pendingForm.dataset.confirmed='1'; modal?.hide(); pendingForm.requestSubmit();
  });
  document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{
    const b=form.querySelector('button[type="submit"]'); if(!b || form.dataset.noLoading==='1')return;
    setTimeout(()=>{b.dataset.originalHtml=b.innerHTML;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Processing…';b.disabled=true},30);
  }));
  document.querySelectorAll('[data-view-target]').forEach(btn=>btn.addEventListener('click',()=>{
    const group=btn.closest('.ml-view-toggle'); group?.querySelectorAll('.btn').forEach(b=>b.classList.remove('active'));btn.classList.add('active');
    document.querySelectorAll('[data-view-panel]').forEach(p=>p.classList.add('d-none'));
    document.querySelector(`[data-view-panel="${btn.dataset.viewTarget}"]`)?.classList.remove('d-none');
  }));
})();
(() => {
 const form=document.getElementById('registration-form'); if(!form)return;
 const steps=[...form.querySelectorAll('.reg-step')]; if(steps.length<3)return; let current=0;
 const bar=document.getElementById('reg-progress-bar');
 const updateReview=()=>{const v=n=>form.querySelector(`[name="${n}"]`)?.value?.trim()||'—';document.getElementById('review-name')&&(document.getElementById('review-name').textContent=`${v('first_name')} ${v('last_name')}`.trim());document.getElementById('review-email')&&(document.getElementById('review-email').textContent=v('email'));document.getElementById('review-city')&&(document.getElementById('review-city').textContent=v('city'));const checked=form.querySelector('input[name="role_type"]:checked');let role='Client';if(checked?.id==='role_doctor')role='Specialist Doctor';if(checked?.id==='role_centre')role='Healthcare Centre';document.getElementById('review-role')&&(document.getElementById('review-role').textContent=role)};
 form.addEventListener('input', updateReview);
 form.addEventListener('change', updateReview);
 const show=i=>{current=Math.max(0,Math.min(steps.length-1,i));steps.forEach((x,n)=>x.classList.toggle('d-none',n!==current));document.querySelectorAll('[data-reg-label]').forEach((x,n)=>x.classList.toggle('active',n<=current));if(bar)bar.style.width=((current+1)/steps.length*100)+'%';if(current===2)updateReview();window.scrollTo({top:Math.max(0,form.getBoundingClientRect().top+scrollY-100),behavior:'smooth'});setTimeout(()=>window.dispatchEvent(new Event('resize')),150)};
 const valid=()=>{for(const el of steps[current].querySelectorAll('input,select,textarea')){if(el.offsetParent!==null && !el.checkValidity()){el.reportValidity();return false}}return true};
 form.querySelectorAll('[data-reg-next]').forEach(b=>b.addEventListener('click',()=>{if(valid())show(current+1)}));
 form.querySelectorAll('[data-reg-prev]').forEach(b=>b.addEventListener('click',()=>show(current-1)));
 if(document.querySelector('.alert-danger')) show(0);
})();
(() => {document.querySelectorAll('[data-password-toggle]').forEach(btn=>btn.addEventListener('click',()=>{const input=document.getElementById(btn.dataset.passwordToggle);if(!input)return;const show=input.type==='password';input.type=show?'text':'password';const icon=btn.querySelector('i');if(icon)icon.className=show?'bi bi-eye-slash':'bi bi-eye';btn.setAttribute('aria-label',show?'Hide password':'Show password')}));})();
