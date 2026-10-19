# Anti-AI-slop critique rubric

A coherent UI is not defined by maximal minimalism. Diagnose based on purpose and evidence.

| Symptom | Why it fails | Better treatment |
|---|---|---|
| Every statistic in a floating rounded card | Fragmented hierarchy and noisy repetition | Use grouped summaries, lists, or one structured panel |
| Unmotivated purple-blue gradients or glow | Visual fashion overrides brand/task | Neutral surfaces plus one deliberate accent, gradients only when brand/context supports |
| Giant headings and excessive padding in operations screens | Needlessly reduces task throughput | Use task-relevant compact density and legible hierarchy |
| Too many shadow layers / glass blur | Competing depth cues and poor legibility | Flat grouping, strong alignment, restrained elevation |
| Equal visual weight for all actions | Primary path unclear | Define primary, secondary, tertiary, destructive actions |
| Random border radii, icon styles, colors | Weak design-system coherence | Use consistent tokens and icon family |
| Placeholder fake metrics and generic copy | Product appears untrustworthy | Use user-provided data or labeled sample content |
| Low contrast gray-on-gray | Inaccessible and difficult to scan | Increase text/border/state contrast |
| Inconsistent table controls and mixed alignments | High reading effort | Align numeric data; normalize row height and spacing |
| Decorative microanimations on every interaction | Distraction and potential motion discomfort | Animate meaningful state transitions only |

## Critique loop
1. Describe where the visual hierarchy breaks and who is affected.
2. Identify whether the issue is aesthetic, functional, accessibility, or consistency.
3. Propose a minimal corrective change rather than repainting the entire product.
4. Check another screen and non-default states for consistency.
5. Confirm the change did not erase original identity, density, or functionality.

## Anti-cliche constraints for new UI
Avoid copying the same sidebar + three KPI cards + hero chart arrangement to every product. Derive structure from real task flow. Use icons only where recognition is unambiguous. Resist decorative tags/badges on normal headings. Show real edge-case states and realistic content lengths. Maintain whitespace as a grouping tool, not empty spectacle.

## Components often overlooked

- Third-party tables/charts/dialogs with default fonts/colors inconsistent with the app theme are a form of visual slop; map them to semantic tokens without replacing business functionality.
- Do not mistake an oversized, centered confirm dialog for professionalism. Use an appropriately sized dialog with clear consequences and focused buttons; routine success should usually be non-blocking.
- Avoid random font mixing or sizing, tiny gray captions, decorative bold everywhere, and micro-cards around every form field. Apply a global typography scale with real reading hierarchy.
- Use normal domain language (products, stock, transactions) and realistic label lengths. If data is missing, mark the sample explicitly; don't invent KPIs in production views.
