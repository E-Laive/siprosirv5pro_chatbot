# Web implementation standards

- Inspect framework, design tokens, routing, component library, build tooling and existing CSS conventions before changes.
- Prefer semantic HTML, correct button/link roles, explicit labels, sensible heading order and visible focus indicators.
- Verify desktop, tablet and mobile at representative widths (e.g. 360, 768, 1280 CSS px) and zoom/long-text behavior; numbers are examples, not universal breakpoints.
- Define navigation and overflow behavior intentionally; tables may need horizontal scroll with clear context or prioritized column stacking, not arbitrarily tiny text.
- Avoid cumulative layout shift; provide skeleton/loading/empty/error states where required.
- Use flex/grid according to content; avoid arbitrary absolute positioning for normal layouts.
- Preserve route semantics, API contracts, form validation, loading/error handlers, and feature flags.
- Check keyboard-only navigation, escape-close dialogs, focus management, color contrast, touch interactions, and prefers-reduced-motion.
- Run the project's normal lint/typecheck/build/test commands if available; note which checks actually succeeded.

## Additional Web-specific decisions

- For **typography**, default to Inter only in a new enterprise Web UI without a brand specification and with an acceptable loading/license strategy; prefer existing local/system fonts otherwise. Use the existing token/provider system for sizes, weights and tabular numerals.
- For **charts**, consider ECharts as default for sophisticated dashboards; translate semantic theme tokens into chart theme/series, gridlines, legends and tooltips, and preserve dataset/axis semantics.
- For **data tables**, favor existing grid; if new and design control matters, TanStack Table is a good headless choice, but verify the installed major API. For enterprise feature needs consider AG Grid with edition/license review.
- For **dialogs**, SweetAlert2 is suitable for short notifications, confirm and simple prompts; complex forms should use accessible framework dialog primitives or dedicated routes. Test portal layering, focus and scroll lock.
- Prefer framework-native ThemeProvider/store and CSS custom properties already in the codebase. If tokens must be introduced, `assets/theme-token-examples/tokens.example.json` and the read-only/one-way generation example may help; never duplicate established theme state.
