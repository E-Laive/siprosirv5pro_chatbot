# Library selection and integration policy

## Select by evidence, not brand preference

1. Inventory packages, lockfile, installed assemblies, UI libraries, current wrappers, license entitlements, existing production usage, and framework version.
2. Ask whether the existing tool already solves the problem and whether a change would materially improve user functionality, accessibility, performance, or maintenance.
3. If a new library is justified, compare candidates on: framework/API-version compatibility, license/enterprise features, bundle/installer size, maintenance/security, accessibility, data-scale requirements, offline behavior, SSR (Web), theme support and QA availability.
4. Prefer existing framework components for simple inputs/forms/dialogs. Prefer mature libraries for complex charts and data grids when building equivalent features would be expensive and risky.
5. Add new dependencies only when the user authorized implementation and the benefit is clear. Do not silently upgrade major versions or rewrite all existing consumers. Run a minimal integration proof before spreading a new dependency across pages.
6. Explain the decision and compatibility trade-offs in the deliverable. If official documentation currently targets a different major version, check the **installed version** before copying its API.

## Preferred defaults, conditional

- Apache ECharts: strong Web chart capabilities; style through a semantic palette mapped into chart options. Chart.js can be more appropriate for simple standalone JS.
- TanStack Table: headless table logic suitable when owning markup, state and styles is valuable; version-aware API is essential. AG Grid may suit enterprise requirements, but inspect which capabilities need a commercial license.
- SweetAlert2: Web alerts, toasts, confirms and simple prompts; **not** the primary foundation for long multi-field forms. Prefer an existing notification system when already suitable.
- Radix Dialog: accessible UI primitive in compatible React projects; do not introduce React or Radix in PHP/Bootstrap/Vue apps just for modal styling. Vue may use Reka UI or installed headless primitives.
- Mobile: prioritize the platform's native/framework UI; do not attempt to embed SweetAlert2 in native apps.
- DevExpress WinForms: preserve existing licensed `GridControl`, `ChartControl`, XtraEditors, XtraMessageBox, XtraForm patterns and 21.2 assembly compatibility. Never replace a DevExpress grid with WinForms DataGridView for appearance alone.

## Wrapper rules

Introduce `AppChart`, `AppDataTable`, `AppDialog`/`AppAlert`, `AppToast` only if used across multiple consumers or needed to keep shared behavior/tokens consistent. Keep wrappers narrow: token mapping, default localization, accessibility, error states and adapter API. Do **not** hide core data transformations and business logic in styling wrappers. Existing stable provider/wrapper names win over these examples.

## Official reference pointers (always check major version)

- Codex skills: https://developers.openai.com/codex/skills
- Apache ECharts styling: https://echarts.apache.org/handbook/en/concepts/style/
- TanStack Table: https://tanstack.com/table/latest/docs/overview
- SweetAlert2: https://sweetalert2.github.io/
- Radix Dialog: https://www.radix-ui.com/primitives/docs/components/dialog
- DevExpress WinForms reference: https://docs.devexpress.com/WindowsForms/ (current docs are NOT proof of v21.2 availability)
