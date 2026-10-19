# Charts & data tables: professional enterprise UX

## General

A data visualization and a table are **functional tools**, not decoration. Establish user task (compare, diagnose, reconcile, edit, export), data source, data scale, latency and required interactions before selecting a component. Preserve row identities, formats, data-binding events, data security and report semantics.

- Reuse project libraries. If none are suitable, prefer Apache ECharts for advanced Web visualization, Chart.js for simple charts, TanStack Table for headless Web tables, or AG Grid for enterprise grids when license/features warrant it. Check library **installed major version** and APIs before coding.
- Mobile: prefer existing chart/list/table packages or platform-native UI; adapt interactions to small screens and touch. WinForms DevExpress 21.2: use licensed and already referenced `GridControl` and `ChartControl`; never assume a control or skin is installed just because DevExpress is present.
- For all reusable chart/table components, pass shared design tokens into their rendering and overlays; ensure floating dropdowns, editors and chart tooltip portals follow app Light/Dark theme.

## Chart selection and credibility

| User task | Typical chart | Avoid |
|---|---|---|
| Trend over time | Line/area with honest axis and clear time granularity | Smoothed curves suggesting unobserved precision |
| Compare categories | Sorted horizontal/vertical bars | Incomparable axis truncation |
| Part-to-whole, few stable categories | Stacked bar or limited pie/donut | Dozens of barely distinguishable slices |
| Many values or high density | Heatmap/scatter where justified | Decorative 3D plots |

- Never fabricate metrics, trend lines, labels or confidence; label placeholders distinctly. Include source and units where users need them. Display date/time/locale conventions consistently; verify time-zone boundaries.
- Use a limited and distinguishable series palette: e.g. token roles `chart-1...chart-n`, status tokens for true semantic statuses, and neutral gridlines. Do not encode meaning **only** in color; use legends, shapes, labels, patterns or accessible summaries.
- Apply token-mapped font family and text color to axis labels, tooltip, legend, title and annotation; add data labels only when they aid comprehension.
- Ensure tooltip can be reached/understood via keyboard or provide an accessible textual/tabular alternative for key chart data. Respect reduced motion and avoid autoplay distraction.
- Handle zero baseline appropriately for bars, explicit units, missing data, outliers, negative values and long categories. Do not shorten axes misleadingly.
- Reuse chart instances correctly on rerender or theme change; update theme options or recreate/dispose only according to installed API; check resize observer availability and cleanup.

## Data tables

- Maintain the correct data types, stable unique keys, editing semantics, validation, row selection and original columns. No removal or renaming of business columns without authorization.
- Align text left, numeric/currency right, statuses consistently; use tabular figures and locale formatting. Respect RTL/localization and long values.
- Provide discoverable sorting/filtering/search, pagination/virtualization when justified, predictable row density, visible selected/focused row, sticky columns/headers only when helpful, and accessible action buttons.
- Handle empty, loading, error, partial data, denied access, no results from filters, invalid edits and optimistic rollback if present.
- For high-volume data, choose **server-side** filtering/sorting/pagination if downloading all data would be costly; don't silently sort only the current fetched page when users expect whole-dataset sorting. Virtualization reduces DOM rendering, not server/query or network costs.
- Consider export/download controls, column visibility persistence, keyboard grid patterns, responsive overflow and touch interactions if product needs them. Never silently discard data or reset filters on cosmetic changes.
- For financial/POS/inventory tables, prioritize scan speed and keyboard data entry over oversized card layouts.

## DevExpress WinForms-specific

- Preserve `GridControl` DataSource/FieldName, repository editors, summaries, data masks, sort/group/filter state, focused row, permissions and event wiring.
- ChartControl: preserve Series/DataSource/ArgumentDataMember/ValueDataMembers, data transformations, chart-type-specific options and export/report hooks. Style axes, legend and labels via actual v21.2-available Appearance and palette capabilities.
- Do not switch to a new GridView type or ChartControl series type solely to chase aesthetics. Verify build using installed DevExpress v21.2 assemblies before claiming compatibility.

## Acceptance scenarios

Light/Dark/System; small and large datasets; long titles; negative and zero values; hover/focus/keyboard; empty/loading/error; sorting/filtering with pagination consistency; disabled/edit state; multiple locales; slow response; chart/table resize and theme switch; export if applicable. Report checks actually run.
