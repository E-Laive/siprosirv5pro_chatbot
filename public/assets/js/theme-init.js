(() => {
  const STORAGE_KEY = "kerangka-theme-config-v1";
  const LEGACY_CONTENT_KEY = "kerangka-theme";
  const LEGACY_SIDEBAR_KEY = "kerangka-sidebar-theme";
  const REGIONS = ["sidebar", "topbar", "content", "footer"];
  const systemPreference = matchMedia("(prefers-color-scheme: dark)");

  const validTheme = (theme) => theme === "dark" || theme === "light";
  const getSystemTheme = () => (systemPreference.matches ? "dark" : "light");
  const systemConfig = () => ({
    version: 1,
    mode: "system",
    themes: Object.fromEntries(REGIONS.map((region) => [region, getSystemTheme()])),
  });

  const normalizeConfig = (candidate) => {
    const fallback = getSystemTheme();
    const mode = candidate?.mode === "custom" ? "custom" : "system";
    const themes = Object.fromEntries(
      REGIONS.map((region) => [
        region,
        validTheme(candidate?.themes?.[region])
          ? candidate.themes[region]
          : fallback,
      ]),
    );
    return { version: 1, mode, themes };
  };

  const legacyConfig = () => {
    const content = localStorage.getItem(LEGACY_CONTENT_KEY);
    const sidebar = localStorage.getItem(LEGACY_SIDEBAR_KEY);
    if (!validTheme(content) && !validTheme(sidebar)) return null;
    const contentTheme = validTheme(content) ? content : getSystemTheme();
    return normalizeConfig({
      mode: "custom",
      themes: {
        sidebar: validTheme(sidebar) ? sidebar : contentTheme,
        topbar: contentTheme,
        content: contentTheme,
        footer: contentTheme,
      },
    });
  };

  const readConfig = () => {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
      if (saved?.version === 1) return normalizeConfig(saved);
      const migrated = legacyConfig();
      if (migrated) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(migrated));
        return migrated;
      }
    } catch (_) {}
    return systemConfig();
  };

  const resolvedThemes = (config) => {
    const normalized = normalizeConfig(config);
    if (normalized.mode === "system") {
      const theme = getSystemTheme();
      return Object.fromEntries(REGIONS.map((region) => [region, theme]));
    }
    return normalized.themes;
  };

  const applyConfig = (config) => {
    const normalized = normalizeConfig(config);
    const themes = resolvedThemes(normalized);
    const root = document.documentElement;
    root.dataset.themeMode = normalized.mode;
    root.dataset.systemTheme = getSystemTheme();
    root.dataset.theme = themes.content;
    root.dataset.bsTheme = themes.content;
    root.style.colorScheme = themes.content;
    REGIONS.forEach((region) => {
      root.dataset[`${region}Theme`] = themes[region];
    });
    return normalized;
  };

  const saveConfig = (config) => {
    const normalized = normalizeConfig(config);
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized));
      localStorage.removeItem(LEGACY_CONTENT_KEY);
      localStorage.removeItem(LEGACY_SIDEBAR_KEY);
    } catch (_) {}
    applyConfig(normalized);
    activeConfig = normalized;
    dispatchEvent(new CustomEvent("kerangka:theme-changed", { detail: normalized }));
    return normalized;
  };

  const cloneConfig = (config) => JSON.parse(JSON.stringify(normalizeConfig(config)));
  let activeConfig = applyConfig(readConfig());

  window.KerangkaTheme = {
    regions: [...REGIONS],
    getConfig: () => cloneConfig(activeConfig),
    getResolvedThemes: () => ({ ...resolvedThemes(activeConfig) }),
    getSystemTheme,
    save: (config) => {
      activeConfig = saveConfig(config);
      return cloneConfig(activeConfig);
    },
    reset: () => {
      activeConfig = saveConfig(systemConfig());
      return cloneConfig(activeConfig);
    },
  };

  const syncRegions = () => {
    const themes = resolvedThemes(activeConfig);
    for (const [selector, theme] of [[".sidebar", themes.sidebar], [".topbar", themes.topbar], [".app-footer", themes.footer]]) {
      document.querySelectorAll(selector).forEach((element) => {
        element.dataset.bsTheme = theme;
        element.style.colorScheme = theme;
      });
    }
  };
  document.addEventListener("DOMContentLoaded", syncRegions);
  addEventListener("kerangka:theme-changed", syncRegions);

  systemPreference.addEventListener("change", () => {
    document.documentElement.dataset.systemTheme = getSystemTheme();
    if (activeConfig.mode !== "system") return;
    applyConfig(activeConfig);
    dispatchEvent(
      new CustomEvent("kerangka:theme-changed", { detail: cloneConfig(activeConfig) }),
    );
  });
})();
