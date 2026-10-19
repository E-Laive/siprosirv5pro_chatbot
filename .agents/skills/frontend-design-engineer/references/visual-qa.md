# Visual QA and implementation acceptance

## Evidence and cycle
1. Capture baseline screenshot(s) or document inability to access a rendered UI.
2. Check primary viewport/device plus a narrow, wide, and high-DPI/large-text case relevant to platform.
3. Capture result(s) if tooling permits, compare against baseline and design intent, fix major defects, and recheck.
4. Run applicable automated checks and representative functional flows. Report only checks actually executed.

## Review checklist
- Hierarchy: clear primary task, content grouping, action priority, and scanable data.
- Geometry: consistent gutters, baseline alignment, component height, density, predictable radius and borders.
- Typography: labels, long text, numbers, empty values, wrapping, truncation and font scaling.
- Semantics: sufficient contrast (WCAG AA targets generally 4.5:1 for normal text and 3:1 for large text), focus visibility, meaningful labels and tab/screen-reader order.
- State: focus, hover where relevant, pressed, selected, disabled, loading, error, validation, empty, offline and destructive confirmation.
- Robustness: resizing, overflow, long localization, different data volumes, zoom/DPI scaling, keyboard coverage and platform-specific insets.
- Restraint: remove unnecessary glow, ornamental gradients, superfluous cards/badges and mismatched icons without flattening intentional brand identity.
- Function: existing navigation, handlers, validation, data operations, permissions, backend integration and shortcuts still work.

## Severity rubric
- Blocker: primary workflow broken, unreachable controls, data loss risk.
- Major: content clipped, inaccessible primary controls, serious viewport failure, misleading state.
- Moderate: visual inconsistency, unclear hierarchy, difficult scanning, weak secondary states.
- Minor: small spacing and optical alignment imperfections.

## Completion template
**Scope:** platform/screens/files worked on.
**Design direction:** core principles and token decisions.
**Changes:** specific implemented improvements.
**Preserved:** important original behavior not altered.
**Checks:** exact builds/tests/device widths/screenshots inspected; mark not run transparently.
**Remaining issues:** risks and next iterations.

## v2 focused checks

| Area | Evidence to collect |
|---|---|
| Typography | Global family/roles, inconsistent weights, tabular numbers, long text, dynamic text scale, real font availability |
| Themes | Light/Dark/System, first paint, OS change, persisted choice, overlays, third-party charts/toasts, focus and semantic statuses |
| Chart | data integrity, axis units/zero/negative/missing cases, legend/tooltip, zoom, keyboard/alternative description, resize |
| Table | row keys, type formatting, sort/filter/pagination coherence, focus/edit/selection, large data and empty states |
| Dialog | destructive intent, focus trap/return, Escape/overlay dirty state, cancellation, form validation, dark mode and mobile keyboard |
| Desktop | DevExpress designer stability, DataSource/editor binding, existing handlers, DPI and skin, controls at multiple window sizes |

If only a static audit was possible, label these as **recommendations to test**, not completed QA. Avoid "all tests passed" when no test command was run.
