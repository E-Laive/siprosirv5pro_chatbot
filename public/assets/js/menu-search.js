(() => {
  const searchModal = document.getElementById('menuSearchModal');
  if (!searchModal) return;
  const searchInput = document.getElementById('menuSearchInput');
  let activeIndex = 0;

  const visibleLinks = () => [...searchModal.querySelectorAll('[data-menu-search]:not([hidden])')];

  const syncActiveResult = (scroll = false) => {
    const links = visibleLinks();
    if (!links.length) {
      activeIndex = -1;
      return;
    }
    activeIndex = Math.max(0, Math.min(activeIndex, links.length - 1));
    links.forEach((link, idx) => {
      const active = idx === activeIndex;
      link.classList.toggle('is-active', active);
      link.setAttribute('aria-current', active ? 'true' : 'false');
      if (active && scroll) link.scrollIntoView({ block: 'nearest' });
    });
  };

  const filterMenus = () => {
    const term = searchInput.value.trim().toLocaleLowerCase('id-ID');
    const allLinks = [...searchModal.querySelectorAll('[data-menu-search]')];
    allLinks.forEach((a) => {
      a.hidden = !a.dataset.menuSearch.includes(term);
      a.classList.remove('is-active');
      a.removeAttribute('aria-current');
    });
    const count = visibleLinks().length;
    activeIndex = count ? 0 : -1;
    syncActiveResult(false);
    document.getElementById('menuSearchCount').textContent = `${count} menu ditemukan. Gunakan panah Atas/Bawah atau Tab untuk memilih hasil.`;
    searchModal.querySelector('[data-menu-search-empty]').hidden = !!count;
  };

  searchModal.addEventListener('show.bs.modal', filterMenus);
  searchInput.addEventListener('input', filterMenus);
  searchModal.addEventListener('shown.bs.modal', () => {
    searchInput.focus();
    searchInput.select();
  });

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      if (!document.querySelector('.modal.show,.offcanvas.show')) {
        bootstrap.Modal.getOrCreateInstance(searchModal).show();
      }
      return;
    }
    if (!searchModal.classList.contains('show')) return;
    const links = visibleLinks();
    if (!links.length) return;

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      activeIndex = (activeIndex + 1) % links.length;
      syncActiveResult(true);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      activeIndex = (activeIndex - 1 + links.length) % links.length;
      syncActiveResult(true);
    } else if (event.target.id === 'menuSearchInput' && event.key === 'Enter') {
      event.preventDefault();
      const targetLink = links[activeIndex] || links[0];
      targetLink?.click();
    }
  });
})();
