# Bug List — Modul 15 Dashboard & Laporan

Test case: [../test-cases/15-dashboard-laporan.md](../test-cases/15-dashboard-laporan.md)

| Hasil render | Dashboard bersih — **nol JS error** |
|---|---|
| Bug lintas modul | BUG-004 |

Modul baca-saja, tidak ada CRUD. Renderingnya sehat; masalahnya ada pada
**cakupan angka** bagi manager.

---

## BUG-004 — Angka dashboard dan laporan tidak ter-scope tim

| | |
|---|---|
| **Severity** | 🟠 Tinggi |
| **Status** | Turunan dari BUG-004 |
| **Test case** | `DASH-A-02` |

Manager punya `report.view` dan `report.export`, jadi **boleh** melihat laporan
— itu benar. Yang keliru adalah angkanya mencakup seluruh perusahaan.

Karena `/admin/user` dan `/admin/presence` belum ter-scope
([BUG-004](lintas-modul.md#bug-004--scoping-tim-manager-tidak-diterapkan)),
seluruh agregat yang dibangun di atasnya juga tidak:

| Halaman | Yang bocor ke manager |
|---|---|
| `/admin/dashboard` | Total payroll, sisa kasbon, headcount seluruh perusahaan |
| `/admin/report/attendance` | Kehadiran semua karyawan |
| `/admin/report/salary` | Bruto, potongan, netto seluruh perusahaan |
| `/admin/report/loan` | Saldo kasbon semua karyawan |
| `/admin/report/headcount` | Rincian per departemen |

Kartu **Total Gaji** dan **Sisa Kasbon** paling sensitif — keduanya angka
keuangan agregat yang tidak seharusnya dilihat manager lini.

### Perbaikan

Perbaikan utamanya di modul sumber. Setelah `UserCrudController` dan
`PresenceCrudController` ter-scope,
[DashboardService](../../app/Services/DashboardService.php) perlu mengikuti
scope yang sama — jangan biarkan agregat memakai query terpisah yang melewati
pembatasan.

```php
// DashboardService — terima scope, jangan hitung dari seluruh tabel
protected function scopedUsers(): Builder
{
    $me = backpack_user();
    $q  = User::employed();

    if (! $me->can('user.view_all')) {
        $q->where(fn ($w) => $w->where('manager_id', $me->id)->orWhere('id', $me->id));
    }
    return $q;
}
```

Perhatikan **cache 5 menit**: bila kunci cache tidak menyertakan id user,
angka manager bisa tersaji dari cache milik super_admin — atau sebaliknya.
Kunci cache harus dibedakan per scope:

```php
Cache::remember("dashboard.today.{$me->id}", 300, fn () => …);
```

Ini penting dan mudah terlewat: memperbaiki query tanpa memperbaiki kunci cache
akan menghasilkan kebocoran yang **muncul-hilang** dan sangat sulit dilacak.

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| Dashboard termuat tanpa JS error | ✅ |
| Chart.js ter-render — canvas 593×178, dua seri, sumbu Sep 25–Agt 26 berlabel | ✅ |
| Empat kartu hari ini terpisah: Hadir · Belum Absen · Terlambat · Di Luar Radius | ✅ |
| Panel Headcount: Total Aktif 5 · Teknologi 2 · HRD 2 · Operasional 1 | ✅ |
| Empty state rapi: "Tidak ada keterlambatan bulan ini." | ✅ |
| Sisa Kasbon Rp 2.000.000 cocok dengan `/admin/loan/recap` | ✅ |
| `/admin/report/attendance` · `/salary` · `/loan` · `/headcount` | ✅ 200 |
| `/admin/leave-report`, `/admin/tax-report/annual`, `/tax-report/bpjs` | ✅ 200 |
| `/admin/notification/unread-count` | ✅ 200 JSON |
| Employee dialihkan ke `/my` | ✅ |

Tangkapan layar: [dashboard-super-admin.png](../screenshots/dashboard-super-admin.png),
[dashboard-manager.png](../screenshots/dashboard-manager.png)

---

## BUKAN bug — sudah diverifikasi

| Pengamatan | Penjelasan |
|---|---|
| "Hadir 0 dari 5" dan "Total Gaji Rp 0, 0 rekap" | Data demo sengaja ditaruh di **bulan sebelumnya** — rekap gaji mengukur satu bulan penuh, sehingga bulan berjalan yang baru separuh terbaca seperti absen massal |
| Grafik tren datar sampai Jul 26 | Hanya satu bulan data demo yang terisi |
| Angka hari ini tertinggal beberapa menit | Cache 5 menit yang disengaja; panggil `DashboardService::flushCache()` bila perlu segar |

---

## Belum teruji — kebenaran angka

Harness memverifikasi bahwa dashboard **ter-render**, bukan bahwa angkanya
**benar**. Seluruh test case konsistensi masih ⬜:

| Test case | Hal yang perlu dipastikan |
|---|---|
| `DASH-X-01` | **Cuti dihitung terpisah dari absen** — inti perbaikan M2 |
| `DASH-X-02`…`X-04` | Angka cocok dengan modul sumbernya |
| `DASH-X-05`, `X-06` | Headcount hanya `active` + `probation` (`User::employed()`) |
| `DASH-X-08`…`X-10` | Perilaku cache 5 menit dan `flushCache()` |
| `RPT-A-04` | Laporan kehadiran memisahkan cuti dari absen |
| `NOT-X-02` | **Kegagalan kirim notifikasi tidak me-rollback aksi pemicunya** |
| `NOT-X-03` | `FONNTE_TOKEN` kosong → `LogWhatsAppGateway`, tidak berpura-pura terkirim |
| `NOT-S-01`…`S-08` | Tujuh command terjadwal, termasuk tidak mengirim di akhir pekan |

`DASH-X-01` paling layak diprioritaskan: memisahkan cuti dari absen adalah
perbaikan payroll utama modul M2, dan dashboard adalah satu-satunya tempat
pengguna melihatnya sebagai angka harian.

`NOT-X-02` juga penting — jaminan bahwa notifikasi yang gagal terkirim tidak
membatalkan aksi bisnisnya hanya bisa dibuktikan dengan sengaja merusak
konfigurasi mail.
