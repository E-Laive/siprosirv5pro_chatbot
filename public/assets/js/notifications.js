(() => {
  const alertTypes = {
    success: { title: 'Contoh berhasil', message: 'Ini simulasi notifikasi berhasil; tidak ada data operasional yang disimpan.', icon: 'bi-check-lg' },
    error: { title: 'Contoh proses gagal', message: 'Permintaan tidak dapat diproses. Silakan coba kembali.', icon: 'bi-x-lg' },
    warning: { title: 'Perlu perhatian', message: 'Periksa kembali data sebelum melanjutkan.', icon: 'bi-exclamation-lg' },
    info: { title: 'Informasi', message: 'Contoh notifikasi informasi.', icon: 'bi-info-lg' },
  };
  const toastDisposers = new Set();
  if (window.bootstrap) bootstrap.Modal.Default.backdrop = 'static';

  const showAlertToast = (type, message, title) => {
    const stack = document.getElementById('appNotificationStack');
    if (!stack) return;
    const config = alertTypes[type] || alertTypes.info;
    const alert = document.createElement('article');
    alert.className = `catalog-alert-toast ${type}`;
    alert.setAttribute('role', type === 'error' ? 'alert' : 'status');
    alert.innerHTML = `<span class="catalog-alert-icon"><i class="bi ${config.icon}" aria-hidden="true"></i></span><span class="catalog-alert-copy"><strong></strong><small></small></span><button type="button" class="catalog-alert-close" aria-label="Tutup notifikasi"><i class="bi bi-x-lg" aria-hidden="true"></i></button><span class="catalog-alert-progress" aria-hidden="true"></span>`;
    alert.querySelector('strong').textContent = title || config.title;
    alert.querySelector('small').textContent = message || config.message;
    stack.appendChild(alert);
    let timer;
    let remaining = 6000;
    let started = 0;
    let closed = false;
    const pauses = new Set();
    alert.style.setProperty('--alert-duration', '6000ms');
    const close = () => {
      if (closed) return;
      closed = true;
      clearTimeout(timer);
      toastDisposers.delete(close);
      alert.remove();
    };
    toastDisposers.add(close);
    while (toastDisposers.size > 4) toastDisposers.values().next().value();
    const schedule = () => { started = Date.now(); timer = setTimeout(close, remaining); };
    const pause = (reason) => {
      if (!pauses.size) { clearTimeout(timer); remaining = Math.max(0, remaining - (Date.now() - started)); }
      pauses.add(reason);
      alert.classList.add('paused');
    };
    const resume = (reason) => {
      pauses.delete(reason);
      if (!pauses.size && !closed) { alert.classList.remove('paused'); schedule(); }
    };
    alert.querySelector('button').addEventListener('click', close);
    alert.addEventListener('mouseenter', () => pause('pointer'));
    alert.addEventListener('mouseleave', () => resume('pointer'));
    alert.addEventListener('focusin', () => pause('focus'));
    alert.addEventListener('focusout', (event) => { if (!alert.contains(event.relatedTarget)) resume('focus'); });
    schedule();
  };


  window.KerangkaNotifications = { notify: showAlertToast, dispose: () => toastDisposers.forEach((close) => close()) };
})();
