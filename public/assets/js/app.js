(() => {
  const body = document.body;
  const content = document.getElementById("spaPageContent");
  const pageTitle = document.getElementById("pageTitle");
  const pageSubtitle = document.getElementById("pageSubtitle");
  const menuParents = [...document.querySelectorAll(".nav-parent")];
  const navGroups = [...document.querySelectorAll(".nav-group")];
  const syncSubmenuHeights = () => {
    navGroups.forEach((group) => {
      const submenu = group.querySelector(".sidebar-submenu");
      const fallback = group.querySelectorAll(".sidebar-submenu > li").length * 42;
      const measured = submenu ? Math.max(submenu.scrollHeight, fallback) : fallback;
      group.style.setProperty("--submenu-height", `${measured}px`);
    });
  };
  syncSubmenuHeights();
  const overlay = document.getElementById("sidebarOverlay");
  const themeManager = window.KerangkaTheme;
  const themeModal = document.getElementById("themeSettingsModal");
  const themeRegionFieldset = document.getElementById("themeRegionFieldset");
  const detectedSystemTheme = document.getElementById("detectedSystemTheme");
  const themeModeState = document.getElementById("themeModeState");
  const themeModeInputs = [
    ...document.querySelectorAll('input[name="themeMode"]'),
  ];
  const MINI_FLYOUT_OPEN_DELAY = 120;
  const MINI_FLYOUT_CLOSE_DELAY = 180;
  let requestController = null;
  let miniFlyoutOpenTimer = null;
  let miniFlyoutCloseTimer = null;
  let pinnedMiniGroup = null;
  let historyIndex = Number.isInteger(history.state?.kitIndex) ? history.state.kitIndex : 0;
  let restoringHistory = false;
  history.replaceState({ ...history.state, kitIndex: historyIndex }, '', location.href);

  const isDesktop = () => matchMedia("(min-width: 992px)").matches;
  const sidebar = document.getElementById("sidebar");
  const sidebarToggle = document.getElementById("toggleSidebar");
  const syncSidebarAccessibility = () => {
    const closed = !isDesktop() && !body.classList.contains("sidebar-open");
    sidebar.inert = closed;
    sidebar.setAttribute("aria-hidden", String(closed));
    sidebarToggle.setAttribute("aria-expanded", String(isDesktop() ? !body.classList.contains("sidebar-mini") : !closed));
  };
  const closeMobileSidebar = () => {
    const focusedInside = sidebar.contains(document.activeElement);
    body.classList.remove("sidebar-open");
    if (focusedInside && !isDesktop()) sidebarToggle.focus();
    syncSidebarAccessibility();
  };
  syncSidebarAccessibility();
  const clearMiniFlyoutTimers = () => {
    window.clearTimeout(miniFlyoutOpenTimer);
    window.clearTimeout(miniFlyoutCloseTimer);
    miniFlyoutOpenTimer = null;
    miniFlyoutCloseTimer = null;
  };
  const closeMiniFlyouts = (except = null) => {
    clearMiniFlyoutTimers();
    navGroups.forEach((group) => {
      if (group === except) return;
      group.classList.remove("mini-flyout-open");
      if (body.classList.contains("sidebar-mini")) {
        group
          .querySelector(".nav-parent")
          ?.setAttribute("aria-expanded", "false");
      }
    });
    if (pinnedMiniGroup !== except) pinnedMiniGroup = null;
  };
  const positionMiniFlyout = (group) => {
    if (!isDesktop() || !body.classList.contains("sidebar-mini") ||
        !group.classList.contains("mini-flyout-open")) return;
    const submenu = group.querySelector(".sidebar-submenu");
    const trigger = group.querySelector(".nav-parent");
    if (!submenu || !trigger) return;
    const anchor = trigger.getBoundingClientRect();
    const scrollBounds = sidebar.querySelector(".sidebar-scroll").getBoundingClientRect();
    if (anchor.bottom <= scrollBounds.top || anchor.top >= scrollBounds.bottom) {
      closeMiniFlyouts();
      return;
    }
    const minimumTop = scrollBounds.top + 12;
    const availableHeight = Math.max(0, innerHeight - minimumTop - 16);
    group.style.setProperty("--mini-flyout-left", `${sidebar.getBoundingClientRect().right}px`);
    group.style.setProperty("--mini-flyout-max-height", `${availableHeight}px`);
    // Measure the visible panel, including its title, padding, and wrapped labels.
    const height = Math.min(submenu.scrollHeight + 2, availableHeight);
    const top = Math.min(Math.max(anchor.top, minimumTop),
      Math.max(minimumTop, innerHeight - height - 16));
    const arrowCenter = Math.min(Math.max(anchor.top + anchor.height / 2, top + 12),
      top + height - 12);
    const headerHeight = parseFloat(getComputedStyle(submenu, "::before").height);
    group.style.setProperty("--mini-flyout-top", `${top}px`);
    group.style.setProperty("--mini-flyout-arrow-y", `${arrowCenter}px`);
    group.style.setProperty("--mini-flyout-arrow-bg",
      arrowCenter - top <= headerHeight ? "var(--sidebar-user-bg)" : "var(--sidebar-bg)");
  };
  const updateMiniFlyoutPositions = () => navGroups.forEach(positionMiniFlyout);
  sidebar.querySelector(".sidebar-scroll")?.addEventListener("scroll",
    updateMiniFlyoutPositions, { passive: true });
  const openMiniFlyout = (group) => {
    if (!isDesktop() || !body.classList.contains("sidebar-mini")) return;
    closeMiniFlyouts(group);
    group.classList.add("mini-flyout-open");
    group.querySelector(".nav-parent")?.setAttribute("aria-expanded", "true");
    positionMiniFlyout(group);
  };
  const scheduleMiniFlyoutOpen = (group) => {
    clearMiniFlyoutTimers();
    miniFlyoutOpenTimer = window.setTimeout(
      () => openMiniFlyout(group),
      MINI_FLYOUT_OPEN_DELAY,
    );
  };
  const scheduleMiniFlyoutClose = (group) => {
    window.clearTimeout(miniFlyoutOpenTimer);
    window.clearTimeout(miniFlyoutCloseTimer);
    miniFlyoutCloseTimer = window.setTimeout(() => {
      if (
        pinnedMiniGroup === group ||
        group.matches(":hover") ||
        group.contains(document.activeElement)
      )
        return;
      group.classList.remove("mini-flyout-open");
      group.querySelector(".nav-parent")?.setAttribute("aria-expanded", "false");
    }, MINI_FLYOUT_CLOSE_DELAY);
  };

  document.getElementById("toggleSidebar")?.addEventListener("click", () => {
    if (isDesktop()) {
      body.classList.toggle("sidebar-mini");
      closeMiniFlyouts();
      updateMiniFlyoutPositions();
      if (!body.classList.contains("sidebar-mini")) {
        syncSubmenuHeights();
        const activeMenu = document.querySelector(
          ".sidebar-nav .spa-link.active-menu[data-menu]",
        )?.dataset.menu;
        if (activeMenu) updateNavigation(activeMenu);
      }
      localStorage.setItem(
        "kerangka-sidebar",
        body.classList.contains("sidebar-mini") ? "mini" : "full",
      );
    } else {
      body.classList.toggle("sidebar-open");
      syncSidebarAccessibility();
      if (body.classList.contains("sidebar-open")) sidebar.querySelector("a")?.focus();
    }
    syncSidebarAccessibility();
  });
  overlay?.addEventListener("click", closeMobileSidebar);
  addEventListener("resize", () => {
    syncSubmenuHeights();
    if (isDesktop()) {
      closeMobileSidebar();
      updateMiniFlyoutPositions();
    } else {
      closeMiniFlyouts();
    }
    syncSidebarAccessibility();
  });

  try {
    if (isDesktop() && localStorage.getItem("kerangka-sidebar") === "mini")
      body.classList.add("sidebar-mini");
  } catch (_) {}
  if (body.classList.contains("sidebar-mini")) closeMiniFlyouts();
  syncSidebarAccessibility();

  navGroups.forEach((group) => {
    group.addEventListener("pointerenter", () => scheduleMiniFlyoutOpen(group));
    group.addEventListener("pointerleave", () => scheduleMiniFlyoutClose(group));
    group.addEventListener("focusin", () => openMiniFlyout(group));
    group.addEventListener("focusout", (event) => {
      if (group.contains(event.relatedTarget)) return;
      scheduleMiniFlyoutClose(group);
    });
  });

  const updateActivePaths = () => {
    document.querySelectorAll(".sidebar-submenu").forEach((submenu) => {
      const activeItem = submenu.querySelector(".active-menu")?.closest("li");
      submenu.style.setProperty(
        "--active-path-height",
        activeItem ? `${activeItem.offsetTop + 21}px` : "0px",
      );
    });
  };

  const closeGroup = (group, preserveActiveBranch = false) => {
    group.classList.remove("open", "mini-flyout-open");
    if (!preserveActiveBranch) group.classList.remove("active-branch");
    group.querySelector(".nav-parent")?.setAttribute("aria-expanded", "false");
  };

  menuParents.forEach((menuParent) =>
    menuParent.addEventListener("click", () => {
      const group = menuParent.closest(".nav-group");
      if (isDesktop() && body.classList.contains("sidebar-mini")) {
        const willOpen = pinnedMiniGroup !== group;
        pinnedMiniGroup = willOpen ? group : null;
        if (willOpen) openMiniFlyout(group);
        else closeMiniFlyouts();
        menuParent.setAttribute("aria-expanded", willOpen ? "true" : "false");
        return;
      }
      const willOpen = !group.classList.contains("open");
      navGroups.forEach((otherGroup) => closeGroup(otherGroup, true));
      if (willOpen) {
        syncSubmenuHeights();
        group.classList.add("open");
        menuParent.setAttribute("aria-expanded", "true");
      }
    }),
  );

  const themeLabel = (theme) => (theme === "dark" ? "Gelap" : "Terang");
  const setThemeModeState = (config = themeManager?.getConfig()) => {
    if (themeModeState)
      themeModeState.textContent = config?.mode === "system" ? "Sistem" : "Kustom";
  };
  const selectedThemeMode = () =>
    themeModeInputs.find((input) => input.checked)?.value || "system";
  const selectedRegionTheme = (region) =>
    document.querySelector(`input[name="theme_${region}"]:checked`)?.value ||
    themeManager?.getSystemTheme() ||
    "light";
  const modalThemeConfig = () => ({
    version: 1,
    mode: selectedThemeMode(),
    themes: Object.fromEntries(
      (themeManager?.regions || []).map((region) => [
        region,
        selectedRegionTheme(region),
      ]),
    ),
  });
  const updateThemeModalPreview = () => {
    const mode = selectedThemeMode();
    const systemTheme = themeManager?.getSystemTheme() || "light";
    if (detectedSystemTheme) detectedSystemTheme.textContent = themeLabel(systemTheme);
    if (themeRegionFieldset) themeRegionFieldset.disabled = mode === "system";
    themeModal?.querySelectorAll("[data-theme-preset]").forEach((button) => {
      const regions = themeManager?.regions || [];
      const selected = mode === "custom" && regions.length > 0 &&
        regions.every((region) => selectedRegionTheme(region) === button.dataset.themePreset);
      button.setAttribute("aria-pressed", String(selected));
    });
    (themeManager?.regions || []).forEach((region) => {
      const theme = mode === "system" ? systemTheme : selectedRegionTheme(region);
      const preview = document.querySelector(`[data-theme-preview="${region}"]`);
      if (!preview) return;
      preview.style.setProperty(
        "--preview-color",
        theme === "dark" ? "#111824" : "#ffffff",
      );
      const value = preview.querySelector("strong");
      if (value) value.textContent = themeLabel(theme);
    });
  };
  const fillThemeModal = (config) => {
    const systemTheme = themeManager?.getSystemTheme() || "light";
    const normalized = config || {
      mode: "system",
      themes: Object.fromEntries(
        (themeManager?.regions || []).map((region) => [region, systemTheme]),
      ),
    };
    themeModeInputs.forEach((input) => {
      input.checked = input.value === normalized.mode;
    });
    (themeManager?.regions || []).forEach((region) => {
      const theme = normalized.themes?.[region] || systemTheme;
      document
        .querySelectorAll(`input[name="theme_${region}"]`)
        .forEach((input) => {
          input.checked = input.value === theme;
        });
    });
    updateThemeModalPreview();
  };

  themeModal?.addEventListener("show.bs.modal", () => {
    fillThemeModal(themeManager?.getConfig());
  });
  themeModal?.addEventListener("change", updateThemeModalPreview);
  themeModal?.querySelectorAll("[data-theme-preset]").forEach((button) => {
    button.addEventListener("click", () => {
      const theme = button.dataset.themePreset;
      if (theme !== "light" && theme !== "dark") return;
      fillThemeModal({
        mode: "custom",
        themes: Object.fromEntries(
          (themeManager?.regions || []).map((region) => [region, theme]),
        ),
      });
    });
  });
  document.getElementById("themeResetButton")?.addEventListener("click", () => {
    const systemTheme = themeManager?.getSystemTheme() || "light";
    fillThemeModal({
      version: 1,
      mode: "system",
      themes: Object.fromEntries(
        (themeManager?.regions || []).map((region) => [region, systemTheme]),
      ),
    });
  });
  document.getElementById("themeApplyButton")?.addEventListener("click", () => {
    const saved = themeManager?.save(modalThemeConfig());
    setThemeModeState(saved);
    bootstrap.Modal.getOrCreateInstance(themeModal).hide();
  });
  addEventListener("kerangka:theme-changed", () => {
    setThemeModeState();
    if (themeModal?.classList.contains("show") && selectedThemeMode() === "system")
      updateThemeModalPreview();
  });
  setThemeModeState();

  document
    .getElementById("fullscreenToggle")
    ?.addEventListener("click", async (event) => {
      try {
        if (document.fullscreenElement) await document.exitFullscreen();
        else await document.documentElement.requestFullscreen();
        event.currentTarget.innerHTML = `<i class="bi ${document.fullscreenElement ? "bi-fullscreen-exit" : "bi-arrows-fullscreen"}"></i>`;
      } catch (_) {}
    });

  const updateNavigation = (activeMenu) => {
    body.classList.toggle('ai-page', activeMenu === 'asisten-ai');
    closeMiniFlyouts();
    syncSubmenuHeights();
    document
      .querySelectorAll(".sidebar-nav .spa-link[data-menu]")
      .forEach((link) => {
        link.classList.toggle("active-menu", link.dataset.menu === activeMenu);
        if (link.dataset.menu === activeMenu) link.setAttribute("aria-current", "page");
        else link.removeAttribute("aria-current");
      });
    const activeLink = document.querySelector(
      `.sidebar-nav .spa-link[data-menu="${CSS.escape(String(activeMenu))}"]`,
    );
    const activeGroup = activeLink?.closest(".nav-group");
    navGroups.forEach((group) => {
      if (group === activeGroup) {
        group.classList.add("open", "active-branch");
        group
          .querySelector(".nav-parent")
          ?.setAttribute("aria-expanded", "true");
      } else {
        closeGroup(group, false);
      }
    });
    updateActivePaths();
  };

  updateActivePaths();
  updateMiniFlyoutPositions();

  document.addEventListener("keydown", (event) => {
    if (!isDesktop() && body.classList.contains("sidebar-open") && !document.querySelector(".modal.show")) {
      if (event.key === "Escape") { closeMobileSidebar(); event.preventDefault(); return; }
      if (event.key === "Tab") {
        const items = [...sidebar.querySelectorAll("a, button")].filter((item) => item.getClientRects().length);
        const first = items[0], last = items.at(-1);
        if (event.shiftKey && document.activeElement === first) { last.focus(); event.preventDefault(); }
        else if (!event.shiftKey && document.activeElement === last) { first.focus(); event.preventDefault(); }
      }
      return;
    }
    if (event.key !== "Escape" || !body.classList.contains("sidebar-mini"))
      return;
    closeMiniFlyouts();
    document.querySelector(".nav-parent:focus")?.blur();
  });

  const loadPage = async (url, push = true) => {
    if (window.KerangkaWorkspace && !await window.KerangkaWorkspace.beforeNavigate()) return false;
    requestController?.abort();
    requestController = new AbortController();
    const currentRequest = requestController;
    body.classList.add("spa-loading");
    content.setAttribute("aria-busy", "true");

    try {
      const response = await fetch(url, {
        headers: { "X-Requested-With": "XMLHttpRequest", "X-SPA-Request": "1" },
        cache: "no-store",
        signal: currentRequest.signal,
      });
      const result = await window.KerangkaHTTP.readJSON(response);
      if (response.status === 401 && result.redirect) {
        try {
          sessionStorage.setItem("kerangka-auth-notice", result.message || "Sesi Anda telah berakhir. Silakan masuk kembali.");
          sessionStorage.setItem("kerangka-intended-url", url);
        } catch (_) {}
        location.assign(result.redirect);
        return false;
      }
      if (response.status === 403) { window.KerangkaCatalog?.notify('error', result.message || 'Akses tidak tersedia.'); return false; }
      if (!response.ok || !result.success)
        throw new Error(result.message || "Halaman gagal dimuat.");

      if (currentRequest !== requestController) return;
      await window.KerangkaCatalog?.dispose(content);
      if (currentRequest !== requestController) return;
      content.innerHTML = result.content;
      if (push) history.pushState({ spa: true, kitIndex: ++historyIndex }, "", url);
      window.KerangkaCatalog?.init(content);
      pageTitle.textContent = result.pageTitle;
      pageSubtitle.textContent = result.pageSubtitle;
      document.title = `${result.title} · Kerangka SIPROSIR`;
      updateNavigation(result.activeMenu);
      closeMobileSidebar();
      content.focus({ preventScroll: true });
      scrollTo({ top: 0, behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "instant" : "smooth" });
    } catch (error) {
      if (error.name === "AbortError") return;
      if (!navigator.onLine) {
        window.KerangkaCatalog?.notify('error', 'Koneksi terputus. Halaman saat ini tetap tersedia; sambungkan lalu coba lagi.', 'Halaman belum dimuat');
        return false;
      }
      location.assign(url);
    } finally {
      if (currentRequest === requestController) {
        body.classList.remove("spa-loading");
        content.removeAttribute("aria-busy");
      }
    }
    return true;
  };

  window.KerangkaApp = { navigate: loadPage };

  document.addEventListener("click", (event) => {
    if (
      body.classList.contains("sidebar-mini") &&
      !event.target.closest(".nav-group")
    )
      closeMiniFlyouts();
    const link = event.target.closest("a.spa-link");
    if (
      !link ||
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      link.target
    )
      return;
    const target = new URL(link.href, location.href);
    if (target.origin !== location.origin) return;
    event.preventDefault();
    loadPage(target.href);
  });

  addEventListener('popstate', async (event) => {
    if (restoringHistory) { restoringHistory = false; return; }
    const nextIndex = event.state?.kitIndex;
    const loaded = await loadPage(location.href, false);
    if (!loaded && Number.isInteger(nextIndex) && nextIndex !== historyIndex) {
      restoringHistory = true; history.go(historyIndex - nextIndex);
    } else if (loaded && Number.isInteger(nextIndex)) historyIndex = nextIndex;
  });
})();
