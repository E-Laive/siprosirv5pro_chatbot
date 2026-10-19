# Mobile implementation standards

- Discover native/platform design conventions, navigation model, existing themes, permission requirements, form factors and target OS versions.
- Follow Android Material or iOS Human Interface Guidelines when relevant; cross-platform apps still need platform-adaptive interactions.
- Respect safe areas, cutouts, keyboard insets, gesture/navigation bars, orientation changes, dynamic type/font scaling and compact screen sizes.
- Use comfortably reachable targets and sufficient spacing; common guidance is at least approximately 48 dp Android / 44 pt iOS for touch targets, subject to platform-specific rules.
- Design loading, permission denied, offline, no-results, destructive-confirmation and recoverable-error states.
- Avoid hiding primary tasks in abstract icon-only controls; label important actions.
- Preserve camera/scanner permissions, lifecycle behavior, deep links, navigation stack, accessibility labels and back gestures.
- Verify light/dark appearance if supported, screen reader order, large font sizes, and a real device or emulator when available.
- Flutter/React Native: use project theme tokens, correct platform widgets, avoid excessive nested view trees, and preserve state/navigation logic.

## Additional Mobile-specific decisions

- Theme and typography must come from the central MaterialTheme / ThemeData / iOS asset catalog / React Native provider already used by the app. Persist Light/Dark/System preference only if the product supports an explicit setting; honor OS changes and font scale.
- Prefer framework-native dialogs, bottom sheets, full-screen forms and snackbars to SweetAlert2 (which targets Web). Match native dismissal/keyboard/back-button semantics.
- For charts, inspect the mobile project's package/library compatibility and GPU/rendering constraints. Ensure labels, selection targets, screen-reader alternatives and empty-data messaging survive narrow screens. For data tables use adaptive scroll, lists, or focused detail views rather than shrinking text until illegible.
- Do not bundle extra fonts solely for a generic corporate look. Android default Roboto, iOS system SF and Flutter platform text themes are good starting points when brand rules are absent.
