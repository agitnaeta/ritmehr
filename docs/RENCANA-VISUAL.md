# Rencana Visual — Adaptasi dari Referensi Desain

Rencana untuk menutup jarak antara tampilan sekarang dan referensi desain yang
diberikan. Melengkapi [RENCANA-CSS.md](RENCANA-CSS.md), yang mengurus arsitektur
CSS-nya.

---

## 1. Kenapa percobaan pertama belum cukup

Yang saya kerjakan pada percobaan pertama adalah **penggantian token**: warna,
radius, kolom isian, tombol. Itu perlu, tetapi bukan yang membuat referensi
terasa seperti referensi.

Karakter referensi datang dari **komposisi**, dan komposisinya belum saya sentuh
sama sekali. Selisihnya, diukur langsung dari halaman `/my` yang berjalan:

| Properti | Sekarang | Referensi | Selisihnya |
|---|---|---|---|
| Latar halaman | `#f8fafc` abu nyaris putih | biru pekat tersaturasi | **beda total** |
| Navigasi | bar gelap lebar penuh, radius `0` | tidak ada bar sama sekali | **beda total** |
| Wadah konten | transparan, radius `0`, lebar 1140px | lembar mengapung, radius besar | **beda total** |
| Kartu | radius `10px`, **`box-shadow: none`**, border `1px` | radius besar, bayangan dalam, **tanpa border** | beda |
| Padding kartu | `16px` | jauh lebih lega | beda |
| Judul halaman | `22px` / berat `650` | jauh lebih besar / berat `800` | beda |
| Bentuk organik | hanya di halaman login | pembentuk kedalaman utama | beda |

Empat baris pertama adalah **perbedaan struktural**, bukan perbedaan gaya. Tidak
ada nilai token yang bisa memperbaikinya — yang perlu diubah adalah susunan
halamannya.

---

## 2. Ketegangan yang harus diselesaikan lebih dulu

Ada konflik nyata di antara dua keputusan yang sudah diambil, dan menutup mata
terhadapnya akan menghasilkan tampilan yang setengah-setengah:

> **Referensinya adalah layar otentikasi yang lapang. Aplikasi ini adalah alat
> kerja padat data.**

Kerapatan yang dipilih **padat** — baris tabel 36px, teks 13px — supaya HR bisa
membandingkan banyak baris tanpa menggulir. Referensinya justru bekerja karena
kelapangan: padding besar, tipografi tebal berukuran besar, banyak ruang kosong.

Tabel presensi 110 baris **tidak bisa** terlihat seperti mockup login itu. Kalau
dipaksa, salah satu pasti kalah: entah datanya jadi sulit dibandingkan, atau
tampilannya tetap tidak menyerupai referensi.

### Jalan keluarnya: dua tingkat permukaan

| Tingkat | Permukaan | Perlakuan |
|---|---|---|
| **Panggung** | login, reset password, `/scan`, keadaan kosong, layar sukses | Referensi diterapkan **penuh**: bidang berwarna, lembar mengapung, bentuk organik, tipografi display tebal |
| **Kerja** | beranda portal, tabel, formulir, seluruh `/admin` | Mewarisi **materialnya** — radius, bayangan, kolom terisi, tombol navy, judul tebal — tetapi bidangnya tetap netral dan tabelnya tetap padat |

Yang menyatukan keduanya bukan kelapangan, melainkan **material dan tipografi**:
radius yang sama, bayangan yang sama, kolom isian yang sama, keluarga warna yang
sama. Itulah yang membuat produk terasa satu, tanpa memaksa tabel gaji jadi
poster.

Halaman `/scan` masuk tingkat Panggung — ia memang bukan alat kerja, melainkan
layar yang dipandang sekilas di pintu masuk.

---

## 3. Perubahan struktural yang diusulkan

### 3.1 Kerangka portal — perubahan terbesar

Sekarang: bar gelap lebar penuh, lalu `.container` transparan di atas latar abu.
Susunan itu sama persis dengan sebelum perubahan apa pun.

Usulan: **bidang biru di atas, lembar putih mengapung di bawahnya.**

```
┌─────────────────────────────────────────────┐
│  bidang biru bergradien + bentuk organik    │  ← tinggi ~180px
│   ╭───────────────────────────────────╮     │
│   │  navigasi mengapung (pil, radius  │     │  ← bukan bar lebar penuh
│   │  besar, translusen)               │     │
│   ╰───────────────────────────────────╯     │
│   Halo, Ahmad Fauzi        ← display tebal  │  ← judul DI ATAS bidang biru
├─────────────────────────────────────────────┤
│   ╭───────────────────────────────────╮     │
│   │                                   │     │
│   │   lembar putih, radius 24px,      │     │  ← naik menimpa bidang biru
│   │   bayangan dalam, tanpa border    │     │
│   │                                   │     │
│   ╰───────────────────────────────────╯     │
└─────────────────────────────────────────────┘
```

Yang berubah:

- **Bidang biru** setinggi ~180px di atas, bergradien `--brand-panel-from` →
  `--brand-panel-to`, dengan dua bentuk organik. Sama seperti panel login
- **Navigasi jadi pil mengapung** di atas bidang itu — translusen dengan
  `backdrop-filter`, radius besar, bukan bar gelap lebar penuh
- **Judul halaman pindah ke atas bidang biru**, berwarna putih, ukuran naik ke
  `2rem` dan berat `800`. Ini yang paling meniru "SIGN IN" tebal di referensi
- **Lembar putih naik menimpa** bidang biru sekitar 40px, radius `24px`, bayangan
  `--shadow-lg`, tanpa border. Ini pola "kartu mengapung" dari referensi
- **Kartu di dalam lembar melepas border-nya** dan jadi panel bernada — kartu di
  dalam kartu dengan dua border terlihat kotor

### 3.2 Kartu dan panel

| Sekarang | Usulan |
|---|---|
| border `1px` + `box-shadow: none` | tanpa border + `--shadow-sm`, naik ke `--shadow-md` saat di-hover |
| radius `10px` | `16px` untuk panel dalam lembar |
| padding `16px` | `24px` — kelapangan referensi diambil di sini, bukan di tabel |

### 3.3 Tipografi

| Peran | Sekarang | Usulan |
|---|---|---|
| Judul halaman | `22px` / `650` | `2rem` / `800` / tracking `-0.03em` |
| Judul kartu | `1rem` / `600` | `1.0625rem` / `700` |
| Tabel | `13px` | **tetap `13px`** — kerapatan dipertahankan |

Naik hanya pada judul. Tabel tidak disentuh; di sanalah kerapatan dibayar.

### 3.4 Halaman `/scan`

Sudah paling dekat. Yang kurang:

- Bentuk organik besar seperti panel login, sekarang gradiennya masih terlalu
  halus untuk terlihat
- Jam bisa lebih tebal — berat `800`, tracking lebih rapat
- Pratinjau kamera radius `24px` supaya sekeluarga dengan lembar portal

### 3.5 Admin (menyusul)

Sidebar Tabler **dipertahankan** — 15 menu memang butuh navigasi tegak, dan
mengganti pil mengapung di sana akan merusak kemampuan pakainya. Yang diubah:

- Sidebar jadi navy dari skala token, item aktif jadi pil membulat
- Topbar translusen dengan `backdrop-filter`
- Area konten mendapat perlakuan lembar yang sama
- Tabel tetap padat dan tenang

---

## 4. Yang tetap **tidak** diambil dari referensi

| Elemen referensi | Alasan |
|---|---|
| Bidang biru di seluruh halaman kerja | Melelahkan untuk dipakai delapan jam, dan kontras teks tabel di atasnya jadi sulit |
| Bentuk organik di belakang tabel | Hiasan di atas data yang sedang dibaca mengganggu, bukan membantu |
| "Sign Up" | Pendaftaran ditutup, `/admin/register` mengembalikan 403 |
| "Sign in with other" | Tidak ada penyedia OAuth |
| Pembatas "Or" + tombol sekunder | Tidak ada aksi kedua untuk ditawarkan |

---

## 5. Cara saya usul mengerjakannya

Ada satu hal yang tidak bisa saya putuskan dari kode: **seberapa jauh** bidang
biru itu masuk ke halaman kerja. Terlalu sedikit, tampilannya tetap seperti
Bootstrap. Terlalu banyak, alat kerjanya jadi berat.

Karena itu saya usul **membangun tiga varian kerangka portal** dan menaruhnya di
belakang pemilih, supaya kamu bisa membandingkannya langsung di layar, bukan
menilai dari deskripsi:

| Varian | Arahnya |
|---|---|
| **Tenang** | Bidang biru hanya setinggi navigasi. Lembar putih dominan. Paling dekat ke sekarang, paling nyaman dipakai lama |
| **Seimbang** | Bidang biru ~180px memuat navigasi dan judul. Lembar naik menimpanya. Ini yang digambarkan di §3.1 |
| **Tegas** | Bidang biru mengisi separuh atas dengan bentuk organik jelas, kartu ringkasan mengapung di atasnya. Paling menyerupai referensi, paling berani |

Ketiganya memakai token yang sama dan sama-sama memenuhi ambang kontras AA.
Setelah kamu pilih satu, varian itu yang diterapkan ke seluruh halaman portal,
lalu diteruskan ke admin.

Prototipe hidup di route terpisah dan tidak menyentuh halaman produksi sampai
kamu memilih.

---

## 6. Urutan setelah varian dipilih

1. Terapkan kerangka pilihan ke layout portal
2. Kartu dan panel: lepas border, naikkan padding dan radius
3. Tipografi judul dinaikkan
4. `/scan` dipertegas bentuk organiknya
5. Admin: sidebar, topbar, dan area konten
6. Verifikasi: kontras AA pada bidang biru, `prefers-reduced-motion`,
   `prefers-reduced-transparency` untuk `backdrop-filter`, PHPUnit, suite CRUD

---

## 7. Catatan kejujuran

Percobaan pertama saya berhenti di tingkat token dan saya sampaikan sebagai
"sudah diadaptasi". Itu terlalu cepat. Token menyiapkan bahannya, tetapi yang
membuat sebuah tampilan menyerupai referensi adalah susunannya — dan itu belum
dikerjakan sampai dokumen ini dijalankan.
