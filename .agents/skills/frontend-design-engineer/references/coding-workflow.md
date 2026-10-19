# Codex / coding agent workflow

## Modes

- **AUDIT**: read project files and optionally run read-only local checks. Do not edit, format or install; report issues and suggested changes, with exact paths and severity.
- **PLAN**: audit plus proposed scoped implementation, dependency decision, visual direction and acceptance criteria. Still no edits.
- **IMPLEMENT**: user has requested an implementation; edit relevant files in reviewable steps, preserve behavior, run available checks, and deliver results. For potentially destructive migrations, unapproved third-party packages, framework changes or database/API alterations, stop and discuss instead of silently proceeding.
- **VERIFY**: inspect code and render/build test if environment permits. Distinguish truly tested items from manual checks the user must perform.

## Discovery details

1. Check repository status and current working tree (avoid overwriting user changes). Identify project root, entrypoints, theme system, UI component conventions and quality commands from README/scripts.
2. Detect stack/version from actual manifests and binaries. For DevExpress, inspect `.vbproj` assembly references and exact 21.2 build, and take extra care with `Designer.vb` / `.resx`.
3. Locate 1-3 representative screens or reusable components; understand task frequency and density. Read event handlers/binding/route contracts before replacing any UI.
4. Identify existing charts/tables/dialogs/fonts and all styling/token sources. Map the smallest set of shared components that can fix the requested scope.
5. Optionally capture baseline screenshots. If GUI access is absent, ask for a screenshot only when necessary and continue with code-based audit meanwhile.

## Planning output template

**Context:** stack/version and task mode.
**Findings:** severity, evidence, impacted workflow and files.
**Proposal:** smallest changes, design token decisions, what already works and should stay.
**Dependencies:** reuse / optional addition with cost, license and compatibility rationale.
**Validation:** exact states and tests to run.
**Permission:** say 'No files changed' in AUDIT/PLAN.

## Implementation discipline

1. Establish acceptance conditions and screenshot baseline when possible.
2. Reuse semantic tokens. For a missing theme system, implement one app-level provider/config; don't create duplicate managers or hardcode per-component palettes.
3. Implement structure, then type scale and spacing, then color/skin, then interactive states and accessibility, then responsiveness. Avoid broad formatting churn unrelated to the task.
4. Change UI components first, while preserving callbacks, stable keys, state transitions, validations, API contracts, data-binding/masks, and devexpress designer/event names.
5. Use a Web chart/table/dialog wrapper only when multiple screens share behavior; keep library-specific configuration and token mapping there, not business logic.
6. Add dependencies only when approved/necessary and compatible with existing major framework versions. Verify package lock updates and license requirements.
7. Review the diff; ensure no columns/actions disappeared and no unrelated files were modified. Do not use fake hardcoded data in a production path.

## Verification ladder

- Static: lint/formatter/typecheck where scripts exist; inspect errors and dependency versions.
- Build: run project build only when environment supports it; for .NET Framework 4.7.2 and DevExpress 21.2 this typically requires suitable Windows SDK/Visual Studio and local DevExpress assemblies.
- Interaction: test primary flow, edit/submit/cancel, validation, navigation, keyboard shortcuts, modal focus and destruction confirmation, sort/filter/page, and chart updates.
- Visual: relevant screen widths/device densities; Light/Dark/System where applicable; zoom/font-scale; overflow; screenshot comparison and fix significant issues.
- Accessibility/performance: contrast, focus/labels, reduced motion, slow and large-data states where relevant.

Never use 'verified' as a synonym for 'reviewed code'. Report actual commands, screenshot sizes/devices and result. If verification is impossible, list **not run** items with reason.

## Scope and implementation safety

- Audit-only means no code modification. Respect ignored/generated/vendor directories and uncommitted work.
- Don't rewrite unrelated screens, workflows or backend. A redesign is not an excuse for a framework migration.
- If the user asked to update a single form, avoid a global theme rewrite unless the existing one causes inconsistency; explain trade-offs.
- A design system describes defaults; retain product personality and accessibility exceptions. No superficial 'make modern' treatment that damages productivity.

## Codex installation and test example

For **one project only** (Windows):

```text
D:\PATH\TO\PROJECT\
  .agents\
    skills\
      frontend-design-engineer\
        SKILL.md
        references\
        agents\
        assets\
        scripts\
```

Copy the full skill folder into `.agents/skills/`, then reopen/reload the project in Codex if discovery does not update. Example audit prompt:

```text
$frontend-design-engineer
Audit hanya halaman/form yang saya sebutkan. Deteksi stack dan library.
Periksa anti-AI-slop, tipografi, theme global, chart/table/dialog,
aksesibilitas, dan visual QA. Jangan ubah file apa pun dulu.
Tampilkan masalah prioritas, usulan, risiko, dan file yang terdampak.
```

For implementation, explicitly change "Jangan ubah file" to "Implementasikan perbaikan yang sudah saya pilih" and name the target screen. Project-local installation avoids propagating the skill into unrelated repositories.
