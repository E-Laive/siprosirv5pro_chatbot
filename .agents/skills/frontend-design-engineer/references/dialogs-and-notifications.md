# Dialogs, alerts, confirmations and notifications

## Select interaction by user task

| Interaction | Web default | Mobile default | DevExpress WinForms |
|---|---|---|---|
| Confirm destructive operation | SweetAlert2 or existing accessible confirm service | Native/platform AlertDialog | Existing `XtraMessageBox` or dialog service |
| Brief success/error notice | Inline feedback or toast; SweetAlert2 if it fits installed stack | Snackbar/toast/inline | Existing notification/AlertControl pattern where available |
| One-field prompt | SweetAlert2 may fit; validate and localize | Native small dialog | Supported XtraForm/input dialog |
| Edit many fields, transactional form | Existing framework dialog primitive (e.g. Radix Dialog for compatible React), dedicated page as complexity grows | Full-screen route or sheet when appropriate | Existing XtraForm/LayoutControl |
| Grid details, multi-step workflow | Page or dedicated workflow, not large SweetAlert2 HTML content | Dedicated screen / stepper | Existing XtraForm / separate task form |

**Do not use SweetAlert2 as the universal modal rendering framework.** SweetAlert2 is a strong Web choice for short decisions. It is not the default for complex product-edit forms, grids, deeply nested focus/validation, or native Mobile.

## UX & safety requirements

1. Distinguish alert (information), confirmation (commit a decision), prompt (simple user input), dialog form (multi-field), toast (transient non-blocking notice). Don't interrupt users with an alert for every normal success.
2. Use clear action text: `Batal`, `Hapus produk`, `Simpan`; identify the object and consequence of destructive actions. Make cancellation obviously available.
3. Preserve user form state. Do not close a dirty modal on overlay/Escape without a deliberate discard policy; avoid closing on successful-submit intention before the server confirms success.
4. Separate validation errors inline near affected fields; use a summary for multi-error forms. Don't put ten fields and all validation into one SweetAlert2 HTML string.
5. Manage focus: on open focus a logical control; trap focus for a true blocking modal; support Escape when safe; restore focus to opener; ensure accessible name/description and appropriate modality.
6. Prevent unnecessary stacking, duplicate submission and z-index/portal conflicts. Handle loading, disabled actions, error retries, canceled requests and timeouts.
7. Use button hierarchy: destructive action distinguishable but not a neon fill for every decision; keep sizes, font, corner radius, overlay density and focus ring consistent with theme tokens.
8. For touch devices, test bottom sheets/full-screen dialogs, safe area, keyboard insets, tap targets and back gesture. For desktop test tab order, Enter/Escape behavior, owner window/modal state and DPI.

## Global adapter contract

When multiple screens need dialogs and the app has no comparable service, consider `AppDialog.confirm`, `AppDialog.alert`, `AppDialog.prompt`, and `AppToast.show` or equivalent. Keep underlying API private to adapters and return typed outcomes (confirmed/canceled, valid input). Don't force one wrapper for every custom content dialog; framework-native rendering may be better. Keep localization and default focus/error behavior centralized.

### Example interface (illustrative, not a drop-in library)

```ts
const approved = await appDialog.confirm({
  title: 'Hapus produk?',
  message: 'Produk ini akan dihapus secara permanen.',
  confirmLabel: 'Hapus produk',
  variant: 'destructive',
});
if (!approved) return;
await deleteProduct(productId); // execute only after confirmation
appToast.success('Produk berhasil dihapus');
```

Do not create a phantom `appDialog` API without implementing and testing its actual adapter. An adapter should not swallow errors or pretend a cancellation is a failure.

## SweetAlert2-specific guidance (Web)

- First check if SweetAlert2 (or alternative) already exists. If installed, centralize popup/toast `customClass`, width, button labels, localization, focus and color treatment; use CSS variables from Global Theme rather than hardcoded appearance per call.
- Use only verified options for the installed SweetAlert2 version. Avoid opaque HTML string construction with unsanitized user input; prefer safe text or framework dialog rendering for dynamic rich content.
- Do not duplicate confirm UI if a framework dialog is already an established convention. Avoid multiple simultaneous overlay managers with competing scroll locks.
- Test with keyboard-only, touch, screen reader, Light/Dark/System and browser zoom. Pay attention to focus restoration when action opens a second page/dialog.

## Failure mode checklist

Modal too wide/narrow; content clipped at small viewport; button hierarchy reversed; autofocus on destructive action; overlay click loses work; toast disappears before message is understood; no accessible name; disabled primary action without explanation; form submit duplicated; dialog appears behind another portal; dark-mode popup still bright; multiple libraries rendering inconsistent overlays.
