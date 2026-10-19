# Desktop implementation standards

## Common
Preserve event handlers, bindings, keyboard shortcuts, tab order, window state, dialogs, resizing rules, accessibility metadata and OS conventions. Desktop applications frequently benefit from dense but legible controls and stable keyboard-first workflows.

## WinForms / VB.NET
- Inspect `.Designer.vb`, `.resx`, control names, event wiring (`Handles`, `AddHandler`), anchors/docking, and font autoscaling before editing. Do not replace components in ways that disconnect events.
- Consider DPI scaling, AutoScaleMode, high-DPI monitors, layout panels, minimum window sizes, and long localized labels.
- Favor TableLayoutPanel/FlowLayoutPanel, consistent control heights, margins, focus, tab order, and native feedback over manually positioning every element without a resizing strategy.
- Ensure DataGridView density, sorting, selected row states, editable cells, scroll, and input validation remain intact.
- Test design at 100% and higher DPI scaling if available; distinguish untested configurations.

## DevExpress WinForms
If project references DevExpress WinForms assemblies, additionally read `devexpress-winforms.md`; its rules override generic suggestions to replace DevExpress editors or grids with built-in WinForms controls.

## WPF / XAML
Use centralized resource dictionaries, coherent styles/templates and MVVM bindings. Verify command bindings, validation templates, virtualization, keyboard navigation, and window resize behavior.

## Qt / Electron and others
Retain native patterns and event models; don't introduce web-style decoration that fights desktop density. Electron uses web accessibility conventions plus desktop shortcuts/window constraints.
