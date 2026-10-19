# Global typography system

Treat typography as an application-wide design contract, not a separate choice on every screen. **Inspect existing typography first.** Preserve an established brand font and verify the license/availability before introducing a new one. Connect fonts to semantic text roles and the same single source of truth as light/dark colors and spacing.

## Recommended starting families (only for projects without an existing coherent font)

| Context | Default | Why / caveats |
|---|---|---|
| Enterprise Web, admin/POS/inventory/reporting | Inter, with `system-ui, sans-serif` fallback | Clear screen hierarchy and numerals; avoid network font dependencies when performance/privacy rules prohibit them |
| Brand-led Web/landing | Existing brand choice; optionally Plus Jakarta Sans for display and Inter for body | Use a second face only if it genuinely strengthens hierarchy |
| Android native | Platform Roboto / Material typography | Respect native dynamic font scale and Compose/XML typography contracts |
| iOS native | System SF typography | Use system font APIs; don't package Apple proprietary fonts |
| Flutter | Platform system font or approved family via `TextTheme` | Do not fight platform dynamic type |
| DevExpress WinForms 21.2 | Segoe UI unless project already uses a cohesive font | Use .NET Framework-compatible text/Appearance APIs, test skin/DPI |

Do not add `@import` for remote fonts, a new font package, or downloadable font binaries without checking performance, policy, license, existing deployment and offline behavior. Prefer local/system fallback until availability is verified.

## Define semantic roles and tokens

Define `font-family-ui`, optional `font-family-display`, `font-family-mono`, `font-weight-regular`, `font-weight-medium`, `font-weight-semibold`, and deliberate `font-size`, `line-height`, and letter-spacing roles. Use component roles rather than arbitrary `font-size: 13px` scattered throughout implementation.

Illustrative, adjustable Web roles for a data-heavy app:

| Role | Suggested starting size | Relative weight | Line height |
|---|---|---|---|
| Page title | 24px (1.5rem) | 600-700 | 1.25 |
| Section title | 18-20px | 600 | 1.3 |
| Body / form value | 14-16px | 400 | 1.45-1.55 |
| Label / grid cell | 13-14px | 400-500 | 1.35-1.5 |
| Help text / metadata | 12-13px | 400 | 1.45 |
| Numeric summary | 22-28px | 600 | 1.2-1.3 |

Mobile examples use **sp** for Android Compose typography and compatible dynamic type on iOS/Flutter, not literal Web `px`. WinForms uses actual `Font`/Appearance units and OS DPI scaling, not CSS `rem`. Treat all above as **starting values**, not fixed minimums; increase when readability, distance, accessibility or form factor requires it. Avoid making enterprise density an excuse for unreadably small text.

## One place for font choices

- Web: define a project-wide CSS variable or existing framework font token and semantic role classes/components. Ensure portal dialogs/tooltips/third-party widgets inherit it. Load only necessary font weights/subsets with appropriate fallback and caching.
- React/Vue: map app theme/tokens to Text/Heading/Input/Table components. Avoid each component importing or redefining font families; ensure SSR/hydration consistent where applicable.
- Flutter: define app-wide `TextTheme` at `ThemeData`; use role styles from theme, permit text scaling, and preserve contrast in both color schemes.
- Android: define shared Compose `Typography` or XML text appearances/styles; honor `fontScale` and text autosizing where appropriate.
- iOS: use text styles/dynamic type with approved brand overrides, not every view's arbitrary fixed size.
- DevExpress: establish project-wide font policy in existing skin/Appearance/controller path where feasible, then make targeted overrides. Avoid cascading invasive designer edits. Verify controls' captions, grids, repository editors, menus and ribbon rendering; respect AutoScaleMode and existing font usage.

## Numeric & data typography

- Use tabular numbers (`font-variant-numeric: tabular-nums` or equivalent platform feature where available) for amounts, quantities, percentages, KPI comparisons, invoice numbers and aligned data columns. When the chosen font lacks tabular numerals, use an appropriate fallback or tested alignment strategy.
- Right-align monetary/quantity values; align signs/decimals consistently. Keep labels left aligned. Use locale-aware number/date/currency formatting; avoid handcrafted format strings when locale changes.
- In dense tables, use a legible base size and sufficient row height; never hide readability problems behind low-contrast small fonts.
- Chart axes, legends, tooltips and data labels must share project typography; test labels at small viewports and varying data lengths.
- Preserve superscripts, diacritics, non-Latin characters and correct fallback fonts where localized content requires them.

## Light/Dark and quality checks

Font family should generally remain stable between light/dark modes; semantic **text colors and contrast** change. Do not simulate light fonts with opacity so low that dark mode becomes washed out. Test regular, secondary, disabled, danger, focus and link states.

QA scenarios: two-line heading, long product name, uppercase acronym, narrow table, zero/negative currency, large number, 125%-200% browser zoom, Android increased font scale, Windows 100%/125%/150% DPI, modal/form overflow and app locale. If actual rendering is unavailable, record it as untested. Compare fonts across chart/table/dialog/notification and regular screens, not just a hero heading.
