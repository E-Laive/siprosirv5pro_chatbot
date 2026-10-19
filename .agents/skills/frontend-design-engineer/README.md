# Frontend Design Engineer — Cross-Platform Anti-AI Slop

A reusable Skill for **Codex** to audit and improve Web, Desktop, and Mobile interfaces. Built for responsible redesign: global design/typography/theme tokens, version-aware library choices, chart/table/dialog/notification guidance, DevExpress WinForms 21.2 safeguards, and honest visual QA.

## Install only in one Codex project (Windows)

1. Extract this ZIP, which contains one `frontend-design-engineer` folder.
2. In the **target project's root**, create `.agents\skills\`.
3. Copy the **whole** `frontend-design-engineer` folder (not just SKILL.md) to `.agents\skills\`.
4. Reopen or refresh the project in Codex if the Skill is not discovered.

Example:

```text
D:\PROJECT\ALULAFLY\
  .agents\skills\frontend-design-engineer\
    SKILL.md
    references\
    scripts\
    assets\
    agents\
```

This is **project-local**; it does not add the Skill to every Codex project. To remove, delete only `.agents\skills\frontend-design-engineer` from that project. Do not overwrite project data or merge this folder into source code.

## Try a safe read-only audit first

```text
$frontend-design-engineer
Audit satu halaman/form target saya. Deteksi platform, library, dan tema global.
Periksa typography, chart/table/dialog, spacing, anti-AI-slop, dan aksesibilitas.
Jangan mengubah kode apa pun. Berikan temuan prioritas, rencana dan checklist QA.
```

## When ready for implementation

```text
$frontend-design-engineer
Implementasikan hanya perbaikan UI di halaman/form yang saya tentukan.
Pertahankan event handler, data binding, API, business logic, dan fitur.
Gunakan design tokens/typography global, reuse library yang terpasang,
dan cek Light/Dark/System bila proyek mendukungnya.
Laporkan file berubah, build/test aktual, serta batasan visual QA.
```

## Optional tools

From the skill folder, if Python 3 is installed:

```sh
python scripts/audit_style_literals.py <project-root> --limit 80
python scripts/generate_web_theme.py assets/theme-token-examples/tokens.example.json <new-output-css-file>
python -m unittest discover -s tests -p "test_*.py" -v
```

The audit is read-only and heuristic. The CSS generator **refuses to overwrite existing CSS files**. Example tokens must be tailored to your application's actual theme; installing the Skill does not automatically install Inter, SweetAlert2, ECharts, TanStack Table, Radix, or any other dependency.

## Supported defaults, not forced library choices

- Web: existing libraries first; Apache ECharts charts, TanStack Table data tables, SweetAlert2 brief alerts/confirms, suitable accessible framework dialog for complex forms.
- Mobile: native/framework widgets and central platform theme/typography.
- WinForms: protect VB.NET .NET Framework 4.7.2 and DevExpress 21.2, use compatible controls/skin.

See `SKILL.md` for the authoritative process and each `references/*.md` file for deeper rules.
