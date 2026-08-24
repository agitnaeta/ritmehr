# Bug List — Modul 12 Audit Log

Test case: [../test-cases/12-audit-log.md](../test-cases/12-audit-log.md)

| Hasil suite | 1 PASS / 0 FAIL |
|---|---|
| Bug modul ini | BUG-010 — ✅ **sudah diperbaiki** |
| Bug lintas modul | BUG-003 ✅ sudah diperbaiki |

---

## BUG-010 — Hash password tersimpan di audit log

| | |
|---|---|
| **Severity** | 🔴 Kritis (kerahasiaan kredensial) |
| **Status** | ✅ **SUDAH DIPERBAIKI** — termasuk pembersihan entri lama |
| **Test case** | `AUD-X-08` |

### Reproduksi

```sql
SELECT id, auditable_type, action FROM audit_logs
WHERE new_values LIKE '%$2y$%' OR old_values LIKE '%$2y$%';
```

### Hasil aktual (sebelum perbaikan)

**5 entri** memuat hash bcrypt utuh di kolom `new_values`, semuanya
`action=create` pada model `App\Models\User`:

```
id=29 kolom "password" = $2y$12$emNHdbShG3hMbcoVq2De3eo6b0FY...
id=30 kolom "password" = $2y$12$.WTq1IxUkTB1viAV0DV.PuQVx4ad...
id=31 kolom "password" = $2y$12$GURogILOgPDKLufoP3nip.jZ57UB...
```

### Mengapa ini kritis

`audit_logs` adalah tabel **kedua** yang menyimpan salinan data, dan dibaca oleh
lebih banyak orang daripada tabel aslinya. Menyalin hash password ke sana
memperluas permukaan serangan tanpa manfaat apa pun: satu dump `audit_logs`
saja — tanpa menyentuh tabel `users` — sudah cukup untuk memanen hash yang bisa
di-crack offline.

Perbaikan [BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission)
memang sudah mempersempit dampaknya: manager kini mendapat 403 di audit log.
Tetapi `hr_admin` masih boleh membacanya, dan hash tidak seharusnya berada di
sana bagi siapa pun.

### Akar masalah

[Auditable.php](../../app/Traits/Auditable.php) menyalin **seluruh** atribut
tanpa daftar pengecualian:

```php
static::created(function ($model) {
    AuditLog::log('create', $model, null, $model->getAttributes());  // ← semua kolom
});
```

`getAttributes()` dan `getDirty()` tidak menghormati `$hidden`, sehingga
`password` dan `remember_token` ikut terbawa.

### Perbaikan

Satu filter dipasang di trait, memakai `$hidden` model sebagai dasar — untuk
`User` itu sudah berisi `password` dan `remember_token`:

```php
public function auditableValues(array $values): array
{
    $rahasia = array_merge(
        $this->getHidden(),
        property_exists($this, 'auditExclude') ? $this->auditExclude : [],
    );

    return array_diff_key($values, array_flip($rahasia));
}
```

Ketiga hook (`created`, `updated`, `deleted`) kini melewatkan nilainya melalui
filter itu. Tambahan `$auditExclude` disediakan untuk model yang perlu
menyembunyikan kolom dari audit **tanpa** menyembunyikannya dari JSON.

Empat model memakai trait ini: `User`, `Salary`, `SalaryRecap`, `Presence`.

### Pembersihan entri lama

Kelima entri yang sudah tercatat dibersihkan **tanpa menghapus barisnya** —
hanya kunci `password` dan `remember_token` yang dibuang dari JSON, sehingga
jejak audit tetap utuh:

```
entri terdampak: 5 → dibersihkan semuanya
sisa entri berisi hash: 0
total audit_logs tetap: 184
```

### Verifikasi

Membuat user baru lalu memeriksa entri auditnya:

```
kolom tercatat: id, name, email, created_at, updated_at, employment_status
ada password?       ✅ tidak
ada remember_token? ✅ tidak
ada hash bcrypt?    ✅ tidak
```

---

## BUG-003 (varian) — Manager bisa membaca seluruh jejak audit

| | |
|---|---|
| **Severity** | 🔴 Kritis — kerahasiaan |
| **Status** | Terkonfirmasi |
| **Test case** | `AUD-A-03` |

### Reproduksi

Login `budi@demo.test` (role `manager`), buka `/admin/audit-log`.

### Hasil aktual

HTTP 200. Tabel terisi **184 entri** — seluruh jejak audit perusahaan.

### Hasil diharapkan

HTTP 403. Role `manager` **tidak punya** permission `audit.view`; hanya
`super_admin` dan `hr_admin` yang memilikinya.

### Mengapa ini lebih serius daripada kebocoran BUG-003 lainnya

Kebocoran akses lain memberi manager jalan ke satu modul. Audit log memberi
jalan ke **riwayat perubahan seluruh modul sekaligus** — termasuk data yang
manager tidak berhak lihat lewat modul aslinya.

Setiap entri memuat nilai **lama dan baru**. Jadi meskipun nanti BUG-004
diperbaiki dan manager hanya melihat timnya di `/admin/user`, jejak audit tetap
membocorkan perubahan data karyawan di luar timnya — lengkap dengan nilai
sebelum dan sesudah, siapa yang mengubah, kapan, dan dari IP mana.

Artinya: **memperbaiki BUG-004 tanpa memperbaiki ini tidak menutup kebocoran
datanya.** Keduanya harus diperbaiki bersama.

### Perbaikan

Modul ini termasuk yang paling mudah ditambal karena hanya perlu satu guard,
mengikuti pola yang sudah dipakai
[AuditLogCrudController](../../app/Http/Controllers/Admin/AuditLogCrudController.php)
untuk menutup create/update/delete:

```php
public function setup()
{
    if (! backpack_user()->can('audit.view')) {
        abort(403, 'Anda tidak berhak melihat jejak audit.');
    }

    CRUD::setModel(\App\Models\AuditLog::class);
    // …
    $this->crud->denyAccess(['create', 'update', 'delete']);
}
```

Sembunyikan pula item sidebar-nya di
[menu_items.blade.php](../../resources/views/vendor/backpack/ui/inc/menu_items.blade.php):

```blade
@if(backpack_auth()->check() && backpack_user()->can('audit.view'))
    <x-backpack::menu-item title="Audit Log" icon="la la-history" :link="backpack_url('audit-log')" />
@endif
```

Ingat: `@can` bawaan Spatie membaca guard `web` sehingga selalu false untuk
admin yang login lewat guard `backpack`. Gunakan `backpack_user()->can(...)`.

### Verifikasi

Login `budi@demo.test` → `/admin/audit-log` harus **403**, dan item sidebar
"Audit Log" tidak muncul.

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| `audit-log/create` ditutup **404** | ✅ |
| Tabel list dimuat AJAX — 184 entri | ✅ |
| Read-only (create/update/delete di-`denyAccess`) | ✅ |
| Employee dialihkan dari `/admin/audit-log` ke `/my` | ✅ |

Jejak audit **aktif dan terisi** — 184 entri terbentuk dari aktivitas seeder dan
pengujian, yang berarti trait `Auditable` bekerja.

---

## Belum teruji

| Test case | Hal yang perlu dipastikan |
|---|---|
| `AUD-X-01`…`X-03` | Create, update, delete tercatat dengan nilai lama vs baru |
| `AUD-X-04` | `user_id` mencatat pelaku yang benar |
| `AUD-X-05` | Aksi lewat command — pelaku sistem, bukan null yang membingungkan |
| `AUD-X-09`…`X-12` | `audit:prune --days=90` menghapus yang lama, menyisakan yang baru |
| `AUD-X-14` | Prune pada puluhan ribu entri tidak kehabisan memori |

`AUD-X-08` sudah dijalankan dan menemukan BUG-010 — kecurigaannya terbukti benar.
Sisanya berpusat pada command `audit:prune`, yang belum pernah dieksekusi.
