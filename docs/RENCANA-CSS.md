# Rencana Perubahan CSS

Analisa keadaan CSS proyek ini dan rencana menjadikannya terlihat seperti
aplikasi enterprise. Seluruh angka di dokumen ini hasil pemeriksaan langsung,
bukan perkiraan.

---

## 1. Keadaan sekarang

### Dari mana style datang

| Permukaan | Sumber style | Ukuran |
|---|---|---|
| **Admin** (`/admin/*`) | 9 stylesheet: Tabler core `1.0.0-beta19`, `style.css` tema Backpack, `animate.compat.css`, `noty.css`, `line-awesome`, `common.css` CRUD, `color-adjustments.css`, `colors.css`, dan `app.css` proyek | Tabler saja 529 KB |
| **Portal karyawan** (`/my/*`) | Bootstrap 5.3.3 + line-awesome dari jsdelivr, ditambah 6 baris `<style>` inline | — |
| **Halaman scan** (`/scan`) | Berdiri sendiri, 224 baris CSS inline, tema gelap | — |
| **Cetak** (slip gaji, ID card, detail kasbon) | `<style>` inline di masing-masing view | — |

### `resources/css/app.css` — 19 baris, 4 aturan

Satu-satunya CSS milik proyek. Isinya tambalan utilitas, bukan sistem desain:

```css
.text-right { text-align: right; }
.pull-right { justify-content: right; text-align: left; }
.disabled-input { background-color: #f0f0f0; color: #777; … }
.company-logo { width: 100px; height: auto; }
```

Dimuat lewat `vite_styles` di [config/backpack/ui.php](../config/backpack/ui.php),
ter-build menjadi `public/build/assets/app-su1sT2EQ.css` — **220 byte**.

> Catatan: hook-nya **sudah berfungsi**. Jadi ada jalur resmi untuk menyuntikkan
> CSS ke seluruh halaman admin tanpa menyentuh vendor. Rencana ini memakainya.

### Sebaran CSS inline

| Tempat | Jumlah |
|---|---|
Berkas blade dengan blok `<style>` | 9 |
Atribut `style="…"` inline | 11 |
View Backpack yang di-override | 16 |

Blok terbesar: `presence/scan.blade.php` (224 baris), `user/detail.blade.php`
(45), `salary-recap/print.blade.php` (39), `loan/table-detail.blade.php` (28).

---

## 2. Masalah sesungguhnya

Bukan "CSS-nya sedikit". Masalahnya **tiga bahasa desain yang tidak saling
kenal** dalam satu produk:

| Permukaan | Bahasa desain | Akibat |
|---|---|---|
| Admin | Tabler | Radius, spasi, tipografi, dan palet khas Tabler |
| Portal | Bootstrap default apa adanya | Terlihat seperti aplikasi lain sama sekali |
| Scan | Sistem gelap buatan sendiri | Terlihat seperti aplikasi ketiga |

Karyawan yang absen di `/scan`, lalu membuka slip gaji di `/my`, lalu HR yang
membuka `/admin` — ketiganya melihat **tiga produk berbeda**. Itu satu-satunya
hal yang paling membuat sebuah aplikasi tidak terasa enterprise.

### Temuan lain

| Temuan | Dampak |
|---|---|
| **Tidak ada design token milik proyek** | Setiap keputusan warna, spasi, radius diambil ulang per tempat. Tidak ada yang bisa diubah terpusat |
| **Tidak ada skala tipografi** | Ukuran, tracking, dan leading mengikuti default Tabler dan Bootstrap. Teks besar terlihat renggang, teks kecil terlihat sempit |
| **`animate.compat.css` dimuat di setiap halaman admin** | Pustaka animasi berbasis keyframes, dimuat global, hampir tidak dipakai. Keyframes juga tidak bisa diinterupsi |
| **`noty@3.2.0-beta-deprecated`** | Namanya sendiri menyatakan deprecated |
| **Tabler dipatok di `1.0.0-beta19`** | Versi beta di jalur produksi |
| **Portal tidak memuat `app.css`** | Layout-nya di luar Backpack, jadi token apa pun yang ditambahkan tidak sampai ke sana kecuali disengaja |
| **Tidak ada dukungan mode gelap yang konsisten** | Tabler mendukungnya, tetapi tidak ada pemilih tema; sementara `/scan` justru gelap |

### Yang **bukan** masalah — sudah diperiksa

Supaya tidak dikerjakan sia-sia:

| Dugaan | Kenyataan |
|---|---|
| "CDN pihak ketiga berbahaya di produksi" | **Tidak.** Basset sudah mirror keempat aset CDN ke `storage/app/public/basset/`. Di luar `dev_mode` — yang aktif hanya karena `APP_ENV=local` — basset menyajikan salinan lokal. Terbukti di [BassetManager.php:239](../vendor/backpack/basset/src/BassetManager.php#L239) |
| "`app.css` tidak pernah dimuat" | **Dimuat.** Lewat `vite_styles`, terverifikasi ada di HTML halaman admin |
| "Perlu mengganti Tabler" | **Tidak, dan jangan.** Tabler mengekspos **880 CSS custom property** (`--tblr-*`). Menimpanya jauh lebih murah dan tahan upgrade dibanding melawannya |
| "CSS inline di view cetak perlu dipindah" | Untuk PDF dompdf, CSS inline justru **benar** — dompdf tidak memuat stylesheet eksternal dengan andal |

---

## 3. Arah yang dipilih

**Satu lapis token, tiga permukaan.** Bukan menulis ulang, bukan mengganti
framework.

```
resources/css/
├── tokens.css      ← sumber tunggal: warna, tipografi, spasi, radius, bayangan, gerak
├── base.css        ← reset tipis, tipografi, focus-visible, utilitas cetak
├── admin.css       ← menimpa token --tblr-* + komponen khas admin
├── portal.css      ← menimpa token Bootstrap dengan token yang sama
└── app.css         ← titik masuk, meng-import keempatnya
```

Prinsipnya diambil dari skill `animate`: **perluas token yang sudah ada, jangan
bikin sistem paralel.** Jadi `tokens.css` mendefinisikan token proyek sekali,
lalu `admin.css` memetakannya ke `--tblr-*` dan `portal.css` memetakannya ke
variabel Bootstrap. Satu perubahan warna merek berlaku di ketiga permukaan.

---

## 4. Rencana bertahap

Diurutkan menurut rasio dampak terhadap risiko. Setiap tahap bisa dirilis
sendiri.

### Tahap 1 — Fondasi token *(risiko: sangat rendah)*

Membuat `tokens.css` dan menyambungkannya. Belum mengubah tampilan apa pun
secara mencolok — ini menyiapkan tanah.

- Palet: satu warna merek, satu netral bertingkat 11 langkah, empat warna status
  (sukses, peringatan, bahaya, info). **Terbatas dan tenang** — enterprise bukan
  berarti banyak warna
- Skala tipografi dengan **tracking dan leading spesifik per ukuran**: teks
  display bertracking negatif dan leading rapat, teks kecil bertracking positif
  tipis. Ini aturan dari skill `apple-design`, dan justru paling terasa pada UI
  padat data
- Skala spasi berbasis `rem` supaya layout ikut membesar bersama pengaturan
  ukuran teks pengguna
- Radius, bayangan, dan token gerak: `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)`,
  durasi 150–250 ms untuk UI

**Perlu keputusanmu:** warna merek. Sekarang memakai default Tabler (biru-ungu).

### Tahap 2 — Permukaan admin *(risiko: rendah)*

Menimpa token `--tblr-*` dengan token proyek, lalu merapikan yang paling terlihat:

- Tabel data: tinggi baris konsisten, angka memakai `tabular-nums` supaya kolom
  rupiah tidak bergoyang, header tabel yang jelas hierarkinya
- Kartu dan panel: satu tingkat bayangan, bukan campur border dan shadow
- Tombol: `:active { transform: scale(0.97) }` — umpan balik seketika saat
  ditekan, aturan yang berulang di seluruh skill Emil
- `:focus-visible` yang terlihat jelas di seluruh kontrol
- Badge status (Lunas / Belum, Hadir / Terlambat) dengan warna yang konsisten
  antar modul

### Tahap 3 — Portal karyawan *(risiko: rendah)*

Ini yang **paling mengubah kesan produk**, karena portal saat ini Bootstrap apa
adanya.

- Memuat token yang sama, memetakan ke variabel Bootstrap
- Menyamakan kartu, tabel, dan tombol dengan admin
- Kartu slip gaji dirapikan sebagai dokumen keuangan, bukan tabel biasa

### Tahap 4 — Cetak *(risiko: rendah, dampak tinggi ke persepsi)*

Slip gaji dan kartu karyawan adalah **dokumen yang keluar dari perusahaan** —
sering justru satu-satunya yang dilihat pihak luar.

- Satu stylesheet cetak bersama, disisipkan inline untuk dompdf
- Tipografi dokumen: hierarki jelas, angka `tabular-nums`, kop yang rapi
- Slip gaji yang terlihat resmi

### Tahap 5 — Konsolidasi *(risiko: sedang)*

- Memindahkan blok `<style>` dari `user/detail`, `org_chart`, dan
  `loan/table-detail` ke stylesheet
- Mempertimbangkan menyelaraskan `/scan` dengan token yang sama. **Catatan:**
  halaman itu sengaja gelap dan berdiri sendiri karena dipasang di perangkat
  pintu masuk. Menyatukan warnanya masuk akal; memaksanya jadi terang tidak
- Menghapus `animate.compat.css` bila memang tidak dipakai — perlu diperiksa
  dulu, bukan dihapus buta

### Tahap 6 — Mode gelap *(opsional)*

Tabler sudah mendukungnya. Setelah token terpusat, ini jadi menambahkan satu
blok `[data-bs-theme="dark"]` plus pemilih tema. Baru masuk akal setelah Tahap
1–3 selesai.

---

## 4b. Status pengerjaan

| Tahap | Status |
|---|---|
| **Tahap 1 — Fondasi token** | ✅ Selesai |
| **Tahap 3 — Portal karyawan** | ✅ Selesai |
| Tahap 2 — Admin | Belum |
| Tahap 4 — Cetak | Belum |
| Tahap 5 — Konsolidasi | Belum |
| Tahap 6 — Mode gelap | Ditunda sesuai keputusan |

Berkas yang dibuat: `resources/css/tokens.css`, `base.css`, `portal.css`;
`app.css` ditulis ulang sebagai titik masuk admin. `portal.css` ditambahkan
sebagai input Vite dan dimuat di layout portal **setelah** Bootstrap, karena
urutannya menentukan — ia menimpa variabel Bootstrap.

### Koreksi terhadap spesifikasi di Tahap 1

Spesifikasi awal dokumen ini menetapkan satu warna per status. Pengukuran
membuktikan itu tidak cukup: sebagai **warna teks di atas putih**, tiga dari
empat warna status gagal ambang AA 4,5:1.

| Token | Kontras di atas putih | Sebagai teks |
|---|---|---|
| `--ok` `#16a34a` | 3,30:1 | gagal |
| `--warn` `#d97706` | 3,19:1 | gagal |
| `--info` `#0891b2` | 3,68:1 | gagal |
| `--danger` `#dc2626` | 4,83:1 | lolos |

Token dipecah menjadi dua peran: `--x` untuk isian dan ikon (ambang non-teks
3:1), dan `--x-text` yang lebih gelap khusus untuk teks. Nilai `-text` sudah
diukur lolos: `--ok-text` 5,02:1 · `--warn-text` 5,02:1 · `--info-text` 5,36:1
· `--danger-text` 6,47:1 · `--brand-text` 6,70:1.

### Kegagalan kontras yang ditemukan di kode yang sudah ada

Beranda portal memakai utilitas Bootstrap `text-warning` dan `text-info` untuk
angka. Diukur di atas putih:

| Utilitas Bootstrap | Kontras | Status |
|---|---|---|
| `text-warning` `#ffc107` | **1,63:1** | gagal parah |
| `text-info` `#0dcaf0` | **1,96:1** | gagal parah |

Keduanya kini diarahkan ke varian `-text`. Ini kegagalan aksesibilitas nyata,
bukan soal selera.

### Hasil verifikasi

| Pemeriksaan | Hasil |
|---|---|
| Kontras warna yang **dirender** pada 7 elemen beranda | **7/7 lolos AA** |
| `prefers-reduced-motion` | transisi dipangkas ke satu nilai, perpindahan dibuang |
| `prefers-contrast: more` | `--border` menggelap ke `#94a3b8`, teks sekunder ke `#334155` |
| Console error di portal | nol |
| PHPUnit | 150/150 |
| Suite CRUD browser | 146/146 |

### Catatan penerapan

`npm run build` **menjadi wajib** saat pemasangan: portal memuat stylesheet-nya
lewat Vite, dan `public/build` tidak disertakan repositori. Sudah ditambahkan ke
langkah pemasangan di README.

---

## 5. Yang sengaja **tidak** saya rencanakan

| Tidak dikerjakan | Alasan |
|---|---|
| Mengganti Tabler atau Bootstrap | Biaya sangat besar, manfaat kecil. Keduanya mengekspos token yang bisa ditimpa |
| Memasang Tailwind | Menambah bahasa desain **keempat** di proyek yang masalahnya justru terlalu banyak bahasa |
| Menulis ulang 16 view Backpack yang di-override | Sebagian besar soal fungsi, bukan tampilan |
| Mengubah CSS inline di view cetak jadi eksternal | dompdf tidak andal memuat stylesheet eksternal |
| Menghapus CDN | Basset sudah menanganinya di produksi |
| Animasi di mana-mana | Skill `find-animation-opportunities` menuntut penolakan mayoritas kandidat. Tabel data dan angka keuangan **tidak boleh** bergerak untuk gaya |

---

## 6. Cara memverifikasi

Perubahan CSS tidak tertangkap PHPUnit, jadi verifikasinya visual dan terukur:

- **Tangkapan layar sebelum/sesudah** per permukaan, lewat harness Playwright
  yang sudah ada
- **Kontras** diuji terhadap WCAG AA pada teks dan kontrol
- **`prefers-reduced-motion`, `prefers-contrast`, `prefers-reduced-transparency`**
  diuji lewat `browser.newContext()`, seperti yang sudah dilakukan pada `/scan`
- **Suite CRUD 146 pemeriksaan** dijalankan ulang: perubahan CSS tidak boleh
  memecahkan selector yang dipakai pengujian (`#crudTable`, `.dataTables_info`)
- **Cetak PDF** diperiksa hasil render-nya, bukan hanya kode statusnya

---

## 7. Keputusan yang sudah diambil

| Keputusan | Pilihan |
|---|---|
| **Warna merek** | Netral profesional + biru tenang `#2563EB` |
| **Kerapatan** | Padat — tinggi baris tabel 36px, padding sel 8/12, teks tabel 13px |
| **Mode gelap** | Token disiapkan berpasangan sejak Tahap 1; pemilih tema belum dibuat |
| **Urutan** | Portal karyawan lebih dulu, lalu admin, lalu cetak |

Urutan eksekusi menjadi: **Tahap 1 (token) → Tahap 3 (portal) → Tahap 2 (admin)
→ Tahap 4 (cetak) → Tahap 5 (konsolidasi)**. Tahap 6 ditunda.

### Spesifikasi token — Tahap 1

Nilai konkret, supaya tahap ini bisa dieksekusi tanpa menebak.

**Warna.** Netral 11 langkah, satu aksi utama, empat status:

| Peran | Terang | Gelap (disiapkan, belum aktif) |
|---|---|---|
| Aksi utama | `#2563EB` | `#3B82F6` |
| Latar halaman | `#F8FAFC` | `#0B1120` |
| Latar permukaan | `#FFFFFF` | `#111827` |
| Teks utama | `#0F172A` | `#F1F5F9` |
| Teks sekunder | `#64748B` | `#94A3B8` |
| Garis batas | `#E2E8F0` | `#1F2937` |
| Sukses | `#16A34A` | `#22C55E` |
| Peringatan | `#D97706` | `#F59E0B` |
| Bahaya | `#DC2626` | `#EF4444` |
| Info | `#0891B2` | `#06B6D4` |

Netral bertingkat `--n-50` sampai `--n-950`; kontras teks utama terhadap latar
permukaan wajib lolos **WCAG AA**, diuji bukan diasumsikan.

**Tipografi.** Tracking dan leading spesifik per ukuran — aturan dari skill
`apple-design`, dan paling terasa justru pada UI padat data:

| Peran | Ukuran | Leading | Tracking |
|---|---|---|---|
| Display | `clamp(1.75rem, 3vw, 2.5rem)` | `1.1` | `-0.02em` |
| Judul halaman | `1.375rem` | `1.25` | `-0.01em` |
| Judul kartu | `1rem` | `1.4` | `-0.005em` |
| Isi | `0.875rem` | `1.55` | `0` |
| Tabel | `0.8125rem` | `1.45` | `0` |
| Label kecil | `0.75rem` | `1.4` | `+0.01em` |

Angka keuangan dan jam memakai `font-variant-numeric: tabular-nums` supaya
lebar kolom tidak bergoyang.

**Spasi.** Skala `rem` berbasis 4px: `0.25 · 0.5 · 0.75 · 1 · 1.5 · 2 · 3 · 4`.
Berbasis `rem` agar layout ikut membesar bersama pengaturan ukuran teks pengguna.

**Radius.** `4px` kontrol · `6px` kartu · `8px` panel · `999px` pil.

**Bayangan.** Tiga tingkat saja, dan **jangan** dicampur dengan border pada
elemen yang sama:

```css
--shadow-sm: 0 1px 2px rgb(15 23 42 / 0.06);
--shadow-md: 0 2px 8px rgb(15 23 42 / 0.08);
--shadow-lg: 0 8px 24px rgb(15 23 42 / 0.12);
```

**Gerak.** Diambil dari skill `animate`, bukan dikarang:

```css
--ease-out: cubic-bezier(0.23, 1, 0.32, 1);
--ease-in-out: cubic-bezier(0.77, 0, 0.175, 1);
--dur-press: 120ms;   /* umpan balik tekan */
--dur-ui: 180ms;      /* dropdown, popover */
--dur-panel: 240ms;   /* panel, drawer */
```

Tidak ada `ease-in` pada UI. Tidak ada durasi di atas 300ms untuk elemen UI.

### Batasan yang harus dijaga saat eksekusi

| Jangan | Alasan |
|---|---|
| Mengubah selector `#crudTable`, `.dataTables_info` | Dipakai suite pengujian 146 pemeriksaan |
| Menyentuh CSS inline di view cetak | dompdf tidak andal memuat stylesheet eksternal |
| Menambah pustaka CSS baru | Masalah proyek ini justru terlalu banyak bahasa desain |
| Menganimasikan tabel data atau angka keuangan | Data yang dibaca dan ditindaklanjuti tidak boleh bergerak untuk gaya |
| Menumpuk bayangan di atas border | Pilih salah satu per elemen |
