# Global theme architecture: Web, Mobile, and Desktop

## Non-negotiable architecture

- Design with one authoritative theme/token source **per application**; individual components consume semantic tokens and must not create competing theme definitions.
- Before making changes, locate existing palette, CSS variables, theme providers, app state management, preferences storage, and platform theme mechanisms. Extend what exists; do not introduce a parallel theme provider or duplicate preference keys.
- Centralize design tokens (colors, typography, spacing, border radii, elevation, density, motion). Prefer semantic roles (`background`, `surface`, `surface-elevated`, `text`, `text-muted`, `border`, `primary`, `on-primary`, `focus`, `success`, `warning`, `danger`) over raw colors in components.
- Maintain separate semantic color mappings for light and dark. Shared non-color tokens may be reused across modes. Provide correct interactive states and component variants in both modes.
- Treat `light`, `dark`, and `system` as preference values. Resolve effective mode from current OS/browser appearance for `system`; respond to system changes while in system mode. Respect existing application policy and user requirements: do not introduce a toggle or change the default merely for a cosmetic task.
- Persist explicitly selected theme in appropriate platform preferences; avoid unnecessary personally identifying data. On first run, use project conventions and user/product requirements, typically `system`. Avoid flash of incorrect theme at startup.
- Theme updates must propagate across pages, screens, overlays, dialogs, menus, toasts, SVG/icons, forms, tables, charts, navigation and error/loading/empty states, without reload when framework permits.
- No scattered hardcoded hex colors, duplicated constants, inline overrides, or component-local dark-mode booleans except for documented one-off illustrations or integration constraints. Existing exceptions should be inventoried and migrated incrementally.
- If user asks to change a single color/style, prefer editing the token at the authoritative definition and confirming consumers update. Avoid wholesale redesign unless requested.
- Consider contrast (minimum WCAG AA for normal interactive text where applicable), focus, disabled, hover, selected, status semantics, and system high-contrast/reduced-motion settings; avoid relying only on hue to convey state.

## Platform implementation guidance

### Web
- Plain CSS: declare semantic custom properties on `:root`/`[data-theme]` (or equivalent); define both schemes centrally, use CSS variables in components. Use `prefers-color-scheme` for system choice; attach/detach a media query listener only while preference is `system`.
- React/Next/Vue/Svelte: reuse app-wide ThemeProvider/context/store/composable already present. Avoid nesting independent providers. For SSR, coordinate server-rendered markup and initial preference to minimize theme flicker/hydration mismatches. Persist preference in existing storage pattern (e.g., localStorage/cookie when appropriate). Update `color-scheme` to style browser native controls.
- Tailwind: align semantic classes/custom properties and dark selector to project Tailwind version/configuration; do not assume latest-version directives.
- Test first paint, OS mode changes, route transitions, modals/portals, charts, and print styles.

### Mobile
- Android native: centralize colors in resources/themes and follow project Compose `MaterialTheme` or XML theme architecture; choose effective dark mode using app preference + platform setting, observing configuration changes.
- iOS/SwiftUI: centralize colors via asset catalog semantic colors and app theme settings; prefer environment color scheme behavior and don't override system mode without user choice.
- Flutter: configure `MaterialApp(theme: ..., darkTheme: ..., themeMode: ...)` from one app-level controller and persist selection with the project's chosen mechanism. Components use `Theme.of(context).colorScheme` and theme extensions, not scattered color literals.
- React Native: use central theme provider/context and `Appearance`/`useColorScheme` as supported; coordinate Navigation and UI library themes, system updates, and persistence.
- Check status/navigation bars, keyboard, splash screen, third-party widgets and modal portals for both schemes and system transitions.

### Desktop / DevExpress WinForms 21.2
- Respect installed DevExpress 21.2 libraries and .NET Framework 4.7.2 compatibility; use a central application-level skin/theme policy with an existing DevExpress look-and-feel manager or project's equivalent where applicable.
- Ensure form and dialog inheritance follows application theme, prefer DevExpress skinnable controls, test custom-drawn components; do not hand-edit generated Designer.vb in a risky manner.
- Do not assume newer DevExpress skin APIs or use web CSS/ThemeProvider concepts in WinForms. Map `system` to a supported Windows preference only when reliably available; otherwise document supported fallback.

## Cross-platform distinction
"One point" means a single authoritative configuration **within each project**, not a magical shared physical file between unrelated Web and Mobile codebases. If user explicitly requests one brand identity across separate apps, maintain a canonical design-token source (e.g. versioned JSON) and generate platform-native outputs through a build process. Do not introduce cross-repository synchronization or services without authorization.

## Verification matrix
- First launch without preference; explicit Light; explicit Dark; System when OS light; System when OS dark; OS transition while System; reopen after selection.
- Test UI states: default, hover/pressed/focus/selected/disabled, validation, empty/loading/error, dialogs, overlays, charts/tables, icons and third-party components.
- Check contrast, typography, unintended hardcoded colors, persistent preference, theme flash/flicker, and layout regressions in both schemes.
- Clearly distinguish tests executed from tests proposed. If platform/device is unavailable, do not claim verified behavior.

## Explicit token-source integration

- Treat theme tokens as the canonical **source** and platform styles as its consumers. When generating derived files, do not manually edit both sides: update the source and regenerate. The optional JSON/CSS example in this skill is one pattern, not the default migration plan for every project.
- Integrate typography roles from `typography-system.md`. Fonts generally stay the same across modes while `text`, `text-muted`, `border`, `focus` and other semantic colors shift.
- Third-party ECharts tooltips and legends, TanStack/AG Grid styling, SweetAlert2 popups/toasts, portal dialogs and overlay containers must use the effective theme. Test real overlays after a theme switch, not only the base page.
- When the user requests "one point" for separate Web and Android/iOS codebases, explain that synchronization requires a shared token contract plus native code generation or explicit package/update process. It is *not* automatically the same physical CSS file on Mobile.
