# Design system standards

## Start with product context
Define primary workflows, user sophistication, brand constraints, content density, input modality, and critical states before selecting appearance. Extract existing tokens/styles first; extend rather than replace a coherent established system.

## Tokens (illustrative defaults, not hardcoded mandates)
- Spacing: consistent 4-unit rhythm, e.g. 4/8/12/16/24/32 logical units; use 2 or 6 sparingly for fine alignment.
- Typography: one or two typefaces maximum; platform/system fonts preferred; define body, caption, label, title, and numeric styles. Use tabular figures for numbers in aligned data tables.
- Color: semantic roles (`surface`, `surface-raised`, `text-primary`, `text-muted`, `border`, `accent`, `danger`, `warning`, `success`, `focus`) rather than scattered hex codes. Contrast should be adequate in actual states.
- Corners: a small radius scale (e.g. 4/8/12) mapped to components. Do not default everything to pills.
- Elevation: separate elements primarily through grouping, alignment, border and background; shadow only where depth communicates hierarchy.
- Layout: specify content width, grid behavior, density, breakpoint/adaptive rules and overflow handling. Don't equate responsiveness with shrinking text.
- Motion: subtle, informative and interruptible, honor reduced-motion.

## Component specifications
For each reusable component define anatomy, variants, dimensions, padding, typography, icon placement, states (idle, hover if applicable, focus, pressed, disabled, loading, error, empty), keyboard/touch behavior and accessibility label semantics. Audit button sets, forms, tables, sidebars, dialogs, toasts, tabs, search, filters, pagination, and status indicators.

## Data-centric UX
Align amounts and dates appropriately, maintain scanability and meaningful default sorting, support empty/loading/error states, use actual data labels, expose destructive actions explicitly, avoid hiding important functionality in hover-only controls.

## Change discipline
Prefer editing established components/tokens over rewriting the entire UI. Document necessary divergences from platform patterns or preexisting design systems.

## Expanded architecture (v2)
- For a new or incoherent theme system, read `global-theme.md` and `typography-system.md` together: one authoritative token/configuration source supplies semantic colors, font roles, spacing, radius and component density. Do not copy values into each button/table/dialog.
- For component variants, use `component-standards.md`; for actual data and overlays, use `charts-and-tables.md` and `dialogs-and-notifications.md`.
- For a Web project that needs a new canonical token source, `assets/theme-token-examples/tokens.example.json` and `scripts/generate_web_theme.py` illustrate **one-way token generation**; do not introduce this structure if a working Tailwind/CSS/theme setup already exists.
