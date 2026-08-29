# UM-02 — Kontras `.form-control` / `.form-select` (abu-di-abu)

**Poin Capt #2** · Tipe: CSS · Urgensi: Tinggi · Risiko: Rendah
**Status:** [x] DONE — `resources/css/admin.css` (+build) · test `tests/browser/um-02-field-contrast.mjs` 4 PASS / 0 FAIL

---

## Konteks
Field filter (dropdown Departemen/Cabang/Status) dan input di dalam bar bernada
tampak menyatu dengan background — abu di atas abu, tanpa garis → terlihat aneh /
nyaris tak terlihat sebagai field.

## Akar masalah (terverifikasi)
- `resources/css/admin.css:287-292`:
  ```css
  .form-control, .form-select {
      background-color: var(--field-bg);   /* --n-100 #f1f5f9 */
      border: 1px solid var(--field-border); /* transparent */
  }
  ```
- `resources/css/tokens.css:57,60`: `--field-bg: var(--n-100)`, `--field-border: transparent`.
- Toolbar filter memakai latar `--bg-sunken` (`tokens.css:102` = `--n-100` juga).
  Field abu di atas bar abu + border transparan = **tak ada batas visual**.
- Pola bug ini SUDAH diperbaiki di portal (`portal.css:737-755`) — tiru pendekatan itu.

## Rencana solusi
File yang disentuh:
1. `resources/css/admin.css`
   - Untuk field DI DALAM toolbar/kartu bernada, override:
     ```css
     .form-control, .form-select {
         background-color: var(--bg-surface);          /* putih */
         border-color: var(--field-border-visible, var(--border-strong));
     }
     ```
     scoped agar tak merusak field yang memang di kartu putih. Pertimbangkan
     selektor `.filters .form-select`, `.dataTables_wrapper .form-control`, dsb.
   - Pastikan panah `<select>` (background-image SVG) tetap muncul — JANGAN pakai
     properti singkat `background`.
2. `npm run build` (Vite) setelah edit.

## Rencana test UI
`tests/browser/um-02-field-contrast.mjs`:
- TC1: `getComputedStyle` field filter → `background-color` ≠ warna bar induk,
  `border` bukan transparent (ada kontras nyata).
- TC2: screenshot toolbar → `vision_analyze` konfirmasi field terlihat jelas berbatas.
- TC3: field di form Create tetap normal (tak over-styled).

## Definition of Done
Kontras field terbukti via `getComputedStyle` + visual; `npm run build` sukses;
test PASS; update Status + centang README.
