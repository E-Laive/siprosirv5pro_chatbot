# Reusable component standards

Favor a small, cohesive system of components over copying disconnected visual fragments. If the project already has a coherent design system, adopt its names and variants rather than inventing duplicates.

## Contracts by component

| Component | Required behavior and states | Common anti-pattern |
|---|---|---|
| Button | Primary/secondary/tertiary/destructive; hover/focus/disabled/loading; stable icon/label spacing; real event behavior | All buttons primary, random radius, fake clickable cards |
| Inputs/select/search | Visible label, help/error, focus, validation, keyboard behavior, correct input type; consistent control height | Placeholder as sole label; silent errors; every input with its own styling |
| Tabs/navigation | Current state, route/state persistence where expected, keyboard interaction, manageable mobile adaptation | Overdecorated tab bar; icon-only critical actions |
| Modal/dialog | Purpose-built width and focus, cancel, dirty form, loading/error, escape semantics | giant SweetAlert2 as multi-field form |
| Tooltip/popover | Contextual, accessible trigger, keyboard/touch fallback, suitable portal/z-index | Hide essential information behind hover |
| Toast/status | Concise actionable text, non-blocking by default, correct semantics and duration | Success modal on every minor action |
| Loading/empty/error | Honest state, recovery action, stable layout, screen-reader messaging where appropriate | Blank screen, fake metrics, endless spinner |
| File/date inputs | Locale, timezone, errors, preview/progress/validation as relevant | Undefined date parsing, silent upload failures |
| Sidebar/toolbar | Information architecture driven by user tasks; clear active item and compact density | Generic dashboard template regardless of workflow |
| Charts and tables | See dedicated reference; matched font/theme, scale/data states | rainbow palette, decorative chart cards, unreadable tables |

## Responsive/adaptive density

- Define spacing scale and content width globally. Tailor compact/comfortable density by usage rather than arbitrarily shrinking font. POS and inventory forms may need compact forms and high-visibility keyboard interactions.
- Respect minimum usable touch targets (Android guidance ~48dp, iOS ~44pt), desktop DPI scaling and enlarged text. Mobile is not just a narrow Web screen.
- Align components to a baseline; prefer page grids and semantic grouping over excessive nested floating cards. Avoid shifting button positions between closely related forms.
- Preserve all user-facing states and original access restrictions. An inaccessible hidden action is not "cleaner" UX.

## Check component consistency

- Normal, hover (pointer), focused (keyboard), active, selected, disabled, loading, success/warning/error, validation, empty, long content, dark theme and OS accessibility changes.
- One shared typography system, visual language and icon family. Numeric data uses tabular figures where supported.
- Keep wrappers thin. A component called `AppButton` must not embed billing logic or API calls; composition and callbacks remain explicit.
