# UM-06 — Halaman Show bertab: Profil + Foto + Riwayat Pelatihan

**Poin Capt #6** · Tipe: View (struktural) · Urgensi: Sedang · Risiko: Sedang
**Status:** [x] DONE — mockup di-ACC Capt; Show 3-tab (Profil/Foto&QR/Pelatihan), relasi trainingEnrollments, label ID.
Test `tests/browser/um-06-user-show-tabs.mjs` (5 PASS) + regresi 86 PHPUnit PASS.

---

## Konteks
Halaman `/admin/user/{id}/show` masih tabel key-value mentah dari
`autoSetupShowOperation()`. Capt ingin: **foto karyawan** terlihat jelas dan
**riwayat pelatihan** yang pernah diikuti — dibungkus jadi (minimal) 2 tab.

## Akar masalah (terverifikasi)
- `UserCrudController::setupShowOperation()` (`:64-104`) pakai `autoSetupShowOperation()`
  → dump mentah; foto & QR di-inject bercampur.
- Relasi pelatihan ADA di model `TrainingEnrollment` (`user()`, `training()`) TAPI:
  - `User` belum punya relasi `trainingEnrollments()`.
  - Show tak menampilkan pelatihan sama sekali.

## Rencana solusi
File yang disentuh:
1. `app/Models/User.php` — tambah relasi:
   ```php
   public function trainingEnrollments() {
       return $this->hasMany(\App\Models\TrainingEnrollment::class);
   }
   ```
2. `app/Http/Controllers/Admin/UserCrudController.php`
   - Override `show($id)` penuh (pola: `$this->crud->hasAccessOrFail('show')`,
     ambil user + eager-load `trainingEnrollments.training`, department/position/branch),
     `return view('admin.user.show', [...])`.
3. `resources/views/admin/user/show.blade.php` (BARU)
   - `@extends(backpack_view('blank'))` + Bootstrap `nav-tabs`:
     - **Tab Profil**: kartu data karyawan berbahasa ID + badge status.
     - **Tab Foto & QR**: foto besar + QR code.
     - **Tab Riwayat Pelatihan**: tabel enrollment (Pelatihan, Status Lulus/Tidak/
       Terkunci via `statusLabel()`, Skor, No. Sertifikat, tanggal). Kosong → empty state.
   - Tombol Kembali / Ubah / Hapus (gate `backpack_user()?->can('user.edit'/'user.delete')`).

## Mockup (WAJIB sebelum koding)
- `docs/review-version-4/mockup/um-06-show-profil.html`
- `docs/review-version-4/mockup/um-06-show-pelatihan.html`
- Render via `browser_navigate file://...` + `browser_vision`, kirim PNG ke Capt via
  `MEDIA:` untuk approval. Baru koding setelah di-ACC.

## Rencana test UI
`tests/browser/um-06-user-show-tabs.mjs`:
- TC1: buka Show user yang punya foto → tab Foto menampilkan `<img>` termuat.
- TC2: klik tab Riwayat Pelatihan → tabel enrollment tampil (seed data pelatihan
  untuk user tsb; assert baris & label status ID).
- TC3: user tanpa pelatihan → empty state, tak error.
- TC4: label berbahasa Indonesia (tak ada "Employment status"/"Join date").
- Verifikasi VISUAL: screenshot tiap tab → `vision_analyze`.

## Definition of Done
Mockup di-ACC Capt; Show bertab berfungsi; relasi pelatihan tampil; label ID;
test PASS; update Status + centang README.
