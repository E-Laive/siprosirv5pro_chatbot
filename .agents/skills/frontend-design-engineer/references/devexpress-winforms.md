# DevExpress WinForms design and implementation rules

Apply this file in addition to `desktop.md` for any WinForms project using DevExpress controls. Target existing installed DevExpress version and .NET target; do not assume APIs, skins, icon assets, or designer features available in newer releases. Read existing `.vbproj`/`.csproj`, package/assembly references, form designers, resources, and actual DevExpress types before suggesting code.

## DevExpress 21.2 compatibility baseline (WinForms VB.NET)

For projects using DevExpress WinForms **v21.2** and **VB.NET .NET Framework 4.7.2**, treat these as hard compatibility constraints, not upgrade targets. This section overrides generic examples when they disagree with the inspected project.

- Read actual `DevExpress.*.v21.2` assembly references and exact patch/build before coding. Keep all referenced DevExpress libraries on a consistent 21.2 release; do not silently install or substitute 22.x-26.x libraries.
- Prefer existing v21.2 controls and patterns (`XtraForm`, `XtraEditors`, `GridControl`/`GridView`, `LayoutControl`, `BarManager` or `RibbonControl`, `AccordionControl`) when those assemblies/controls are already in use; do not assume every product suite or control is licensed or referenced.
- Select skins, SVG support, properties and methods only after confirming availability in local 21.2 assemblies or the archived DevExpress v21.2 documentation. Never copy examples from current DevExpress documentation without version checking.
- Do not assume newer Fluent/HTML-CSS-inspired WinForms capabilities, newly introduced skins, templates, API overloads, or built-in behaviors are available in v21.2. Offer a 21.2-compatible fallback before suggesting any upgrade.
- Keep DevExpress WinForms designer-generated properties, resources, `InitializeComponent`, repository items, appearance settings, skin registration, event handlers, layout groups and DPI behavior intact. Prefer edits in normal `.vb` files and existing supported properties; back up designer files before targeted edits.
- Validate solution build against **.NET Framework 4.7.2** and local DevExpress v21.2 assemblies. Clearly mark any unresolved compile or GUI testing due to an unavailable Windows/Visual Studio environment.
- Prioritize clean enterprise UX over pretending to recreate a newer DevExpress demo exactly. A successful 21.2 design uses restrained skin, reliable typography, aligned forms and compact grids without needing an upgrade.

## Mandatory discovery

- Identify WinForms language (VB.NET or C#), target (.NET Framework 4.x versus modern .NET), DevExpress assembly versions, and current theme mechanism.
- Inventory forms, shared base forms, modules for skin/style setup, `*.Designer.vb` / `*.Designer.cs`, `.resx`, `UserControl`s, custom editors, and third-party styling.
- Record bindings (`BindingSource`, `DataTable`, `DataSet`, collections), unbound columns, repository items, command callbacks, `Handles` / `AddHandler` wiring, and form lifecycle event dependencies.
- Identify usage of `GridControl`/`GridView`/`BandedGridView`, `LayoutControl`, `XtraForm`, `RibbonForm`, `RibbonControl`, `BarManager`, `AccordionControl`, `NavigationFrame`, `TileControl`, `XtraTabControl`, `DockManager`, `SearchControl`, `XtraEditors`, `DXValidationProvider`, `SplashScreenManager`, `AlertControl`, and `SvgImageCollection` only when present. Do not recommend migrating existing controls merely for styling.

## Design system integration

- Prefer a coherent DevExpress skin/palette and consistent fonts over painting each control with ad-hoc background colors. Pick a supported skin that fits the application and verify it exists in the installed version. Keep contrast and state legibility. Do not mix multiple visual systems across screens.
- Use `LayoutControl` and its layout items/groups where already present; prefer layout groups and consistent spacing to magic pixel `Location` edits. Preserve controls' `Name`, binding, and designer initialization order.
- Keep spacing, density, input heights, icons, text capitalization, border emphasis, error treatments, and visual hierarchy consistent. Use compact density for POS/inventory/admin workflows where scanning and tabular work dominate. Distinguish action hierarchy (primary/secondary/destructive), don't make every command brightly colored.
- Prefer installed DevExpress SVG icon facilities, e.g. existing `SvgImageCollection`, where supported; use a coherent icon family at correct sizes. Do not embed arbitrary emoji, large hero illustrations, fake metrics, or repeated gradients into routine desktop screens.
- Respect WinForms DPI font/autoscaling and DevExpress scaling support for the specific release; test at 100%, 125%, and 150% when possible. Avoid hard-coded pixel positions and bitmap-only icons that blur.

## Control-specific UX

- `GridControl`/`GridView`: keep underlying `DataSource` and column `FieldName`, types, sorting/filtering/grouping, summaries, focused row, selection modes, repository item links, edit rules, validation, and row/cell style events. Improve readability via header hierarchy, row density, alignment (numbers right, text left), focused-row visibility, restrained gridlines, and empty/loading states. Verify horizontal scrolling, long values, and editor popups; never remove columns without approval.
- `LayoutControl`: preserve `LayoutControlItem` binding and control ownership, layout groups, hidden items, tab order and responsive sizing; favor consistent item padding and captions. Avoid mixing ad-hoc panels and layout managers without a reason.
- `RibbonControl`/`BarManager`: preserve existing item names, categories, shortcut keys, bar item `ItemClick` events, `Command` connections, permissions, and quick-access affordances. Simplify visual grouping without hiding frequent actions; do not duplicate ribbon plus giant toolbar merely for aesthetics.
- `XtraEditors` such as `TextEdit`, `LookUpEdit`, `SearchLookUpEdit`, `DateEdit`, `SpinEdit`, `SimpleButton`, `CheckEdit`: preserve masks, display/edit formats, data validation, accessibility names, read-only states, null handling and lookup configuration. Harmonize editor density, prompts and error placement rather than changing input types blindly.
- `NavigationFrame`/`AccordionControl`/`DockManager`: retain navigation identifiers, page state, dock persistence and keyboard access. Show current-location cues and a restrained sidebar footprint.
- Dialogs, alerts, and busy states: keep task feedback clear; do not add animations, overlays, or modal popups unless they help task completion.

## Code safety: especially VB.NET .NET Framework 4.7.2

- Inspect compiler/target constraints. Do not use unsupported C# syntax, unavailable APIs, or newer .NET-only features in VB.NET Framework projects.
- Treat designer files as generated code. Prefer adjusting properties via the designer or carefully targeted changes that preserve `InitializeComponent`, `BeginInit`/`EndInit`, `SuspendLayout`/`ResumeLayout`, event handlers, designer serialization, and resource references. Never wholesale regenerate the designer because a mockup looks better.
- Do not break existing `Handles control.Event` event wiring by renaming or swapping controls. Avoid removing declarations and fields used by other forms.
- Separate visual tweaks from changes to database queries, business rules, networking, or DevExpress-specific data processing. Do not introduce an incompatible DevExpress NuGet/assembly version as a styling shortcut.
- When documenting code, use actual assembly namespaces observed in project; verify uncertain members against local references or official version-matched documentation.

## Anti-slop prescriptions for DevExpress WinForms

- Avoid web-dashboard mimicry: oversized floating KPI cards, empty space above data grids, giant pill buttons, emoji icons, random gradients, gratuitous glass/blur, every row boxed as a card, huge round navigation rails, and five competing accent colors.
- Prefer native, compact, aligned desktop design: clear menu/toolbar hierarchy, coherent skin, stable data grid, purposeful grouping, keyboard-first actions, quiet background, and descriptive labels.
- Do not overwrite business-important dense forms with trendy minimalism that slows skilled operators.

## Visual QA for DevExpress

If tooling allows, capture before/after screenshots of (1) normal window, (2) maximized/resize/minimum supported, (3) dense table & editor state, (4) popups/dropdowns, (5) focus/hover/disabled/validation states, and (6) Windows DPI scaling. Check design-time loading if Visual Studio is available. Execute build and smoke tests when possible; do not claim verification for unavailable Windows GUI / DevExpress designer. Document all untested conditions.

## Change-report template

Provide: current DevExpress and framework versions; controls/forms changed; intended visual correction; explicitly preserved event handlers, data bindings and permissions; build and GUI checks truly performed; risks related to designer serialization, DPI or DevExpress version.

## v2 component and typography decisions

- Typography: default to Segoe UI for a fresh unbranded WinForms design; preserve existing project font when coherent. Prefer a central policy and supported Appearance/skin mechanisms, not mass mutation of every `.Designer.vb` Font assignment. Check skin metrics, grid row height, repository editor text, tooltips and DPI scaling.
- `ChartControl`: if already licensed/referenced, retain it and its Series/bindings. Derive chart legend/axis font and restrained palette from the existing application skin/design policy only through actual v21.2-supported Appearance settings. Never write examples against current DevExpress v26 documentation without proving 21.2 API availability.
- Modal/notifications: reuse `XtraMessageBox`, existing `XtraForm` dialog workflow and any installed notification controls; do not attempt to embed SweetAlert2. Confirm ownership, modal result, default/cancel button and Enter/Escape behavior.
- For global Light/Dark, prefer a single supported application skin/look-and-feel entry point and inherited settings. Avoid per-form independent skin selection. Inspect existing `DefaultLookAndFeel`/`UserLookAndFeel` use rather than creating a second theme manager.
