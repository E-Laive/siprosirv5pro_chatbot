---
name: frontend-design-engineer
description: Cross-platform professional frontend design and implementation with an anti-AI-slop quality gate. Use for UI audits, improvements, new screens, redesigns, themes, typography, reusable components, data tables, charts, dialogs, and visual QA in Web (React, Vue, PHP, HTML/CSS), Mobile (Android, iOS, Flutter, React Native), or Desktop (WinForms including VB.NET DevExpress 21.2, WPF, Electron, Qt). First detect the actual stack and existing design system; preserve behavior, choose libraries conservatively, and verify code and visuals. Especially useful for Codex working in a single project without changing other projects.
---

# Frontend Design Engineer

Build production-minded, credible interfaces that serve real workflows. Do not apply a fashionable template to an unrelated product. This skill is an **engineering workflow** and **design quality gate**; it does not itself install dependencies or change a project until asked.

## 1. Start with task mode and platform detection

1. Determine user intent: **AUDIT** (read-only), **PLAN** (read-only proposal), **IMPLEMENT** (authorized code changes), or **VERIFY** (checks and reporting). Requests such as "audit", "review", "plan", or "jangan ubah kode" must remain read-only. Do not ask for permission to implement if explicitly requested to implement, except when high-risk scope changes or unapproved dependencies are needed.
2. Inspect project files before selecting advice: `.sln`/`.vbproj`/`.csproj` and DevExpress references, `package.json`/lockfile, `composer.json`, Flutter `pubspec.yaml`, Android Gradle files, iOS project, XAML, components, styles, current UI screenshots, existing design tokens, routing, and UX flows. Do not infer platform from the name alone.
3. Record installed framework versions, library licenses/availability, existing design system, theme mode behavior, fonts, chart/table/dialog implementations, data sources, localization, and business-critical workflows.
4. Select only applicable references. Mandatory baseline: `references/design-system.md`, `references/anti-slop.md`, `references/coding-workflow.md`, and `references/visual-qa.md`. For visual components consult `references/component-standards.md`. For theme/color/font requests, read `references/global-theme.md` and `references/typography-system.md`. For chart/table/dialog tasks, read their specific references and `references/library-selection.md`.
5. Load platform references conditionally: `references/web.md`, `references/mobile.md`, or `references/desktop.md`. When DevExpress WinForms is present, also read `references/devexpress-winforms.md`; inspect the **actual version**. For DevExpress v21.2 and VB.NET .NET Framework 4.7.2, constrain recommendations and generated code to APIs available in that installation.

## 2. Non-negotiable principles

- **Preserve behavior**: navigation, input validation, event handlers, business logic, data bindings, permissions, shortcuts, reporting, API/database contracts, transaction flows, loading/error/empty states, and accessibility. Never remove functionality as a cosmetic shortcut.
- **Single design authority per application**: reuse the existing global provider/token config, otherwise propose one central semantic token source. Components should consume it. Color, typography, spacing, radius, elevation, density, and component states must be consistent.
- **Theme semantics**: support Light/Dark/System when building a theme system; store explicit preferences, track OS changes in System mode, avoid first-paint flicker and duplicated theme managers. Do not add a switcher without product need or request.
- **Central typography**: use purposeful text roles, a restrained font family stack, readable sizes, tabular numerals for columns/amounts and chart labels, platform text scaling, and locale coverage. Never silently replace an existing licensed/brand font. Never bundle font binaries in this skill.
- **Library-first, not library-always**: keep compatible installed libraries. Prefer a reliable third-party library for complex features when needed, but never install dependencies purely for appearance. Evaluate compatibility, licensing, accessibility, bundle/runtime cost, performance, and maintenance first.
- **Keep library appearance under project control**: use global styling and small reusable wrappers/adapters when they meaningfully reduce duplication; avoid needless abstraction layers or business logic inside wrappers.
- **Anti-AI-slop**: no mechanical sidebar + three cards, rainbow charts, constant gradients/glow, excessive whitespace, giant pill buttons, random icons or fake metrics. Decorative effects are allowed only for a clear product/brand reason.
- **Compatibility and transparency**: never claim an API, skin, screenshot, build, accessibility audit, or device test was verified unless actually checked. Never cite current documentation as proof that a feature exists in DevExpress 21.2 or older library versions.

## 3. Preferred component strategy, not forced dependencies

| Feature | Web preference when no suitable existing solution | Mobile | WinForms DevExpress |
|---|---|---|---|
| Charts | Apache ECharts for demanding/interactive visualization; Chart.js or framework library when simpler | Existing native/framework chart library | Existing `ChartControl`, if available/licensed |
| Tables | Headless TanStack Table for custom Web UI; AG Grid for enterprise features after license review | Framework-native lists/data tables | Preserve `GridControl` and configured views |
| Alerts, confirm, simple prompt | SweetAlert2 when suitable for Web stack | Native/platform dialog | `XtraMessageBox` or existing compatible service |
| Complex modal forms | Existing accessible framework dialog; Radix Dialog in suitable React stacks | Dialog, bottom sheet, or full-screen view by task | Existing `XtraForm`/DevExpress dialog pattern |
| Notifications | Existing toast/notification service or SweetAlert2 when fitting | Snackbar/toast/native messaging | Existing DevExpress/Windows notification solution |

These are **decision defaults, not installation instructions**. Do not use SweetAlert2 for multi-field transactional forms or nested data grids. Do not replace DevExpress with Web controls. Consult `references/charts-and-tables.md`, `references/dialogs-and-notifications.md`, and `references/library-selection.md`.

## 4. Workflow

1. **Discover**: inventory screen(s), framework, constraints, usage, current theme and components. Capture before screenshots when possible.
2. **Audit**: rank functional, layout, typography, density, color, accessibility, performance, and consistency findings by severity. Identify exact files and preserved behavior.
3. **Plan**: propose minimum-scope improvements, reusable components/token updates, existing-library reuse, risks, and acceptance criteria. For audit-only, stop here; never edit files.
4. **Implement** (only in IMPLEMENT mode): use focused changes; prefer existing design and theme infrastructure. Keep UI-only diffs distinct from business logic. Verify any newly introduced dependency and explain why.
5. **Verify**: run available lint/typecheck/build/test, representative interactions, responsiveness/resize/DPI, Light/Dark/System if relevant, chart/table/dialog states, and before/after screenshot review when tools permit. Fix important failures, then report exact checks and limitations.

Read `references/coding-workflow.md` for precise reporting and change-scope policy. Use `references/visual-qa.md` for acceptance conditions. Optional read-only scanning and token generation tools are documented below.

## 5. Targeted instructions

### Web
Use semantic markup, keyboard interaction, layout responsive to content, framework-appropriate state, and tokens passed through chart/table/modal portals. Tailwind, shadcn/ui, Radix UI, React, Vue, or Bootstrap may already be configured; match the installed versions and patterns. Never paste examples from a different major library version blindly.

### Mobile
Respect Material/Cupertino conventions, safe areas, touch targets, screen readers, dynamic type/scaled text, keyboard insets, navigation state, and platform light/dark preferences. A shared design language must not force a Web modal onto Android/iOS.

### Desktop / DevExpress
For WinForms retain designer-generated wiring, `DataSource`, repository items, skin settings, event handlers, and original business flow. In VB.NET projects using DevExpress 21.2 + .NET Framework 4.7.2, **do not** assume DevExpress 22+ properties, newer controls, .NET-only APIs, or themes. Prefer supported `LayoutControl`, `GridControl`, `ChartControl`, `XtraEditors`, and skin configuration already present. Verify rendering at Windows scaling where available.

## 6. Safe, optional supporting assets

- `assets/theme-token-examples/tokens.example.json` is an **illustrative canonical token source**, not a mandate to replace a project's current theme.
- `scripts/generate_web_theme.py` can generate a basic CSS-variable stylesheet from that JSON with **standard-library Python only**. Use only in a compatible Web project if a new token source is actually required; never overwrite an existing stylesheet without approval.
- `scripts/audit_style_literals.py` is an optional **read-only heuristic** that lists potential hardcoded color literals. Its output is leads for manual review, not authoritative defects. Avoid reporting values in assets/generated/vendor code as issues.

## 7. Output contract

- **Audit/plan**: platform and versions detected; prioritized findings with evidence; component/library decisions; proposed token/theme/font architecture; files to change; risks; acceptance checklist; explicit "No files changed".
- **Implementation**: UI changes and file paths; preserved behaviors; libraries reused/added and why; Light/Dark and responsive states; actual command/test/screenshot results; unverified items; follow-up issues.
- Never substitute a mockup for working code; never imply tests passed when not run.

## Reference index

- `references/design-system.md` — layout/color/spacing design system fundamentals.
- `references/global-theme.md` — centralized colors and Light/Dark/System.
- `references/typography-system.md` — fonts, type scale, numeric alignment, accessibility.
- `references/component-standards.md` — buttons, forms, nav, tabs, states, component quality.
- `references/charts-and-tables.md` — data visualization, grids, performance, theme integration.
- `references/dialogs-and-notifications.md` — SweetAlert2 vs framework dialog, confirm, toast, focus.
- `references/library-selection.md` — version/license/dependency decision rubric.
- `references/anti-slop.md` — detect/repair common AI design failures.
- `references/coding-workflow.md` — Codex modes, implementation discipline, handoff.
- `references/web.md` — browser-specific guidance.
- `references/mobile.md` — native/cross-platform guidance.
- `references/desktop.md` — desktop GUI guidance.
- `references/devexpress-winforms.md` — DevExpress WinForms 21.2 baseline and safety.
- `references/visual-qa.md` — evidence-based visual and functional QA.
