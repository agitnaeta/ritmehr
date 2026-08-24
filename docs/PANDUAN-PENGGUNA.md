# Panduan Pengguna

Panduan memakai aplikasi Absensi & HRIS sehari-hari. Disusun per peran — cari
peranmu, lalu ikuti tugas yang ingin dikerjakan.

Untuk hal teknis (pemasangan, arsitektur, pengembangan), lihat
[PANDUAN-DEVELOPER.md](PANDUAN-DEVELOPER.md).

---

## Daftar isi

- [Masuk ke aplikasi](#masuk-ke-aplikasi)
- [Peran dan apa yang bisa dilakukan](#peran-dan-apa-yang-bisa-dilakukan)
- [Untuk Karyawan](#untuk-karyawan)
- [Untuk Manager](#untuk-manager)
- [Untuk HR](#untuk-hr)
- [Untuk Super Admin](#untuk-super-admin)
- [Absensi harian di pintu masuk](#absensi-harian-di-pintu-masuk)
- [Pertanyaan yang sering muncul](#pertanyaan-yang-sering-muncul)

---

## Masuk ke aplikasi

Ada dua pintu masuk:

| Halaman | Untuk siapa | Perlu login? |
|---|---|---|
| `/scan` | Pemindai QR di pintu masuk kantor | **Tidak** |
| `/admin/login` | HR, Manager, Super Admin | Ya |
| `/my` | Karyawan (portal layanan mandiri) | Ya |

Karyawan yang login akan otomatis diarahkan ke `/my`. Kalau mencoba membuka
halaman admin, akan dikembalikan ke portal — itu normal, bukan error.

Lupa password? Hubungi HR. Password hanya bisa diatur ulang oleh HR atau Super
Admin dari menu **Users**.

---

## Peran dan apa yang bisa dilakukan

| Peran | Bisa | Tidak bisa |
|---|---|---|
| **Karyawan** | Absen, lihat kehadiran & slip gaji sendiri, ajukan cuti, lihat kasbon, ubah kontak & password | Melihat data karyawan lain |
| **Manager** | Semua yang karyawan bisa, plus melihat data **timnya** dan menyetujui/menolak pengajuan | Mengubah data apa pun — perannya hanya melihat dan menyetujui |
| **HR** | Seluruh operasi HR: karyawan, absensi, gaji, cuti, kasbon, dokumen, pajak | Mengubah role, permission, atau alur persetujuan |
| **Super Admin** | Semuanya | — |

> Manager hanya melihat **bawahan langsung** — karyawan yang atasannya diisi
> namanya. Kalau ada anggota tim yang tidak muncul, kemungkinan kolom atasannya
> belum diisi. Minta HR mengeceknya di menu Users.

---

## Untuk Karyawan

Semua ada di portal `/my`.

### Melihat riwayat kehadiran

**Kehadiran** → menampilkan tanggal, jam masuk, jam keluar, menit
keterlambatan, lembur, dan status lokasi (di dalam atau di luar radius kantor).

Kalau ada jam yang salah atau hari yang tidak tercatat, laporkan ke HR — hanya
HR yang bisa memperbaiki data absensi.

### Melihat slip gaji

**Slip Gaji** → daftar rekap per bulan. Klik satu bulan untuk melihat
rinciannya: gaji pokok, lembur, potongan keterlambatan, potongan absen, cicilan
kasbon, pajak, dan jumlah yang diterima.

Slip bulan yang belum selesai dihitung tidak akan muncul.

### Mengajukan cuti

1. **Cuti** → lihat sisa kuotamu lebih dulu
2. **Ajukan Cuti** → pilih jenis cuti, tanggal mulai dan selesai, tulis alasan
3. Lampirkan berkas bila jenis cutinya mewajibkan (misalnya surat dokter)
4. Kirim

Yang perlu diketahui:

- **Akhir pekan dan hari libur nasional tidak mengurangi kuota.** Mengajukan
  Jumat–Senin terhitung 2 hari, bukan 4.
- **Tanggal tidak boleh bertabrakan** dengan pengajuan yang sudah ada.
- **Pengajuan yang masih menunggu belum melindungi.** Kalau tanggalnya lewat
  sebelum disetujui, hari itu tetap terhitung absen dan tetap dipotong. Ajukan
  jauh sebelum tanggalnya.
- Beberapa jenis cuti punya batas maksimal hari berturut-turut.

Pengajuan bisa dibatalkan selama belum diproses. Kuotanya kembali.

### Melihat kasbon

**Kasbon** → jumlah kasbon, cicilan yang sudah dibayar, dan sisanya. Cicilan
dipotong otomatis dari gaji.

### Mengubah profil

**Profil** → kamu hanya bisa mengubah **nomor telepon, alamat, dan foto**.
Nama, departemen, jabatan, dan status kepegawaian dikelola HR.

Ganti password juga di halaman ini — password lama wajib diisi.

### Notifikasi

**Notifikasi** → pemberitahuan soal pengajuan cuti, dokumen yang mendekati
kedaluwarsa, dan pengingat absen.

---

## Untuk Manager

Manager memakai panel admin (`/admin`), tetapi hanya bisa **melihat** dan
**menyetujui**.

### Menyetujui atau menolak pengajuan

**Persetujuan** → daftar pengajuan yang menunggu keputusanmu.

1. Klik pengajuan untuk melihat detailnya — tanggal, alasan, lampiran, dan
   riwayat langkah persetujuan
2. **Setujui** atau **Tolak**

Penolakan **wajib disertai alasan**. Alasan itu terlihat oleh pengaju.

Yang perlu diketahui:

- Kalau alurnya bertingkat (Manager → HR), persetujuanmu meneruskan pengajuan ke
  langkah berikutnya, bukan menyelesaikannya.
- Kuota cuti diperiksa **ulang** saat kamu menyetujui. Bisa terjadi sebuah
  pengajuan lolos saat diajukan tetapi ditolak saat disetujui karena kuotanya
  sudah terpakai pengajuan lain.
- Kalau dua approver menyetujui bersamaan, hanya satu yang berhasil. Itu
  disengaja.

### Melihat data tim

| Menu | Isi |
|---|---|
| **Users** | Anggota timmu |
| **Absen → Kehadiran** | Kehadiran timmu |
| **Gajian** | Komponen gaji dan rekap |
| **Kasbon** | Kasbon dan cicilan |
| **Cuti & Izin** | Pengajuan dan kalender |
| **Laporan** | Kehadiran, gaji, kasbon, headcount |

Tombol tambah, ubah, dan hapus tidak akan muncul — perannya memang hanya
melihat. Kalau butuh mengubah sesuatu, minta HR.

---

## Untuk HR

### Menambah karyawan baru

**Users** → **Add**. Yang wajib: nama, email, password, dan **foto**.

Jangan lupa mengisi:

- **Atasan** — menentukan siapa yang menyetujui pengajuannya, **dan** siapa
  manager yang bisa melihat datanya
- **Jadwal** — menentukan jam masuk, jam keluar, dan hari libur. Tanpa jadwal,
  perhitungan keterlambatan dan lembur tidak berjalan
- **Cabang** — menentukan titik dan radius geofence absensi
- **Tanggal masuk** — dipakai menghitung kuota cuti prorata dan THR

Setelah tersimpan, QR karyawan otomatis dibuat. Cetak kartunya dari tombol
**Print** di baris karyawan.

> Cetak kartu memerlukan gambar latar ID card. Kalau belum diunggah, kamu akan
> diarahkan ke **Profile Perusahaan** untuk mengunggahnya lebih dulu.

### Mengelola absensi

**Absen → Kehadiran** → bisa menambah, mengubah, dan menghapus baris presensi.

Setiap perubahan **langsung memengaruhi perhitungan gaji**: menit keterlambatan,
lembur, dan potongan absen dihitung ulang otomatis. Setelah mengoreksi absensi,
jalankan **Hitung Ulang** pada rekap gaji bulan itu.

**Absen → Jadwal** → pola jam kerja. **Setting Jadwal** untuk menetapkan jadwal
ke banyak karyawan sekaligus.

**Absen → Libur Nasional** → tanggal libur. Hari yang terdaftar di sini tidak
dihitung absen dan tidak mengurangi kuota cuti.

### Menjalankan penggajian

Urutannya:

1. **Gajian → Gaji** → pastikan komponen gaji setiap karyawan sudah benar: gaji
   pokok, tarif lembur, jenis dan besaran denda
2. Pastikan absensi bulan itu sudah bersih
3. Jalankan perhitungan rekap (lihat [PANDUAN-DEVELOPER.md](PANDUAN-DEVELOPER.md)
   untuk perintahnya, atau minta tim teknis menjadwalkannya)
4. **Gajian → Rekap Gaji** → periksa hasilnya per karyawan
5. Klik **Print** untuk slip gaji, atau **Export** untuk seluruh rekap dalam
   Excel
6. Setelah dibayarkan, klik **Bayar Cash** atau **Bayar Transfer**

Yang perlu diketahui:

- **Rekap tidak bisa dibuat manual** — hanya lewat perhitungan otomatis.
- **Bayar hanya bisa sekali.** Rekap yang sudah ditandai terbayar akan menolak
  pembayaran kedua.
- Kalau angkanya salah, koreksi sumbernya (absensi atau komponen gaji), lalu
  **Hitung Ulang** — jangan mengubah rekapnya langsung, karena akan tertimpa.

### Cuti berbayar vs tidak berbayar — perbedaan yang penting

Ini yang paling sering menimbulkan salah paham:

| Keadaan | Dihitung absen? | Dipotong gaji? |
|---|---|---|
| Cuti **berbayar** yang disetujui | tidak | tidak |
| Cuti **tidak berbayar** yang disetujui | tidak | **ya** |
| Tidak hadir tanpa pengajuan | **ya** | **ya** |
| Pengajuan masih menunggu | **ya** | **ya** |
| Pengajuan ditolak atau dibatalkan | **ya** | **ya** |

Jadi menyetujui cuti **sebelum** tanggalnya lewat itu penting. Pengajuan yang
menumpuk tanpa keputusan akan tetap memotong gaji karyawan.

### Mengelola kuota cuti

**Cuti & Izin → Jenis Cuti** → atur kuota tahunan, apakah berbayar, batas hari
berturut-turut, dan apakah wajib lampiran.

**Cuti & Izin → Saldo Cuti** → kuota per karyawan per tahun.

- **Generate** → membuat saldo untuk semua karyawan. Aman dijalankan berulang,
  tidak menggandakan.
- **Carry Over** → membawa sisa tahun lalu, dibatasi maksimal tertentu.
- Karyawan yang masuk tengah tahun otomatis mendapat kuota **prorata**.

Sisa cuti dihitung `kuota + sisa tahun lalu − terpakai`.

### Kasbon

**Kasbon → Kasbon** → terbitkan kasbon. **Pembayaran Kasbon** → catat cicilan.

Batasan yang berlaku:

- Nominal minimal Rp 1 — nol dan negatif ditolak
- Pembayaran **tidak boleh melebihi sisa** tagihan
- Kasbon yang sudah dicicil **tidak bisa dihapus**. Hapus atau sesuaikan
  cicilannya lebih dulu

Cicilan dipotong otomatis dari gaji lewat rekap.

### Dokumen karyawan

**Dokumen → Jenis Dokumen** → tentukan jenis berkas yang dibutuhkan, format yang
diizinkan, ukuran maksimal, apakah wajib, dan apakah punya masa berlaku.

**Dokumen → Dokumen Karyawan** → unggah berkas per karyawan.

**Dokumen → Kelengkapan Dokumen** → daftar siapa yang dokumennya belum lengkap.

Berkas disimpan di penyimpanan privat dan hanya bisa diunduh lewat aplikasi. HR
bisa mengunduh semuanya.

> Karyawan **belum** bisa mengunduh dokumennya sendiri dari portal — fitur itu
> belum tersedia. Kalau karyawan memintanya, kirim lewat jalur lain.

Sistem memperingatkan otomatis saat dokumen mendekati kedaluwarsa.

### Pajak & BPJS

**Pajak & BPJS → Profil Pajak Karyawan** → isi NPWP, status PTKP, dan iuran BPJS
mana yang aktif per karyawan.

> Karyawan **tanpa NPWP** dikenakan tambahan pajak **20%**. Pastikan NPWP terisi
> bagi yang memilikinya.

**Rekap Pajak Tahunan** dan **Rekap BPJS Bulanan** untuk pelaporan.

Menu **Tarif PTKP**, **Lapisan PPh 21**, dan **Tarif BPJS** berisi tarif resmi
yang disimpan **per tahun**. Jangan mengubah tarif tahun yang sudah berjalan —
tambahkan tarif tahun baru, supaya perhitungan lama tetap memakai tarif
periodenya sendiri.

> ⚠️ Tarif yang terpasang saat aplikasi dipasang mengacu peraturan pada waktu itu.
> **Verifikasi terhadap peraturan terbaru sebelum memakainya untuk penggajian
> sungguhan.**

### Organisasi

**Organisasi → Cabang** → lokasi kantor beserta koordinat dan radius absensi.
Ini yang menentukan apakah scan dianggap di dalam atau di luar kantor.

**Departemen** dan **Jabatan** → struktur perusahaan. Departemen bisa
bersarang. **Struktur Organisasi** menampilkannya sebagai bagan.

---

## Untuk Super Admin

Selain semua yang HR bisa, ada menu **Pengaturan**:

| Menu | Fungsi |
|---|---|
| **Role** | Peran dan izinnya |
| **Permission** | Daftar izin (hanya bisa dilihat) |
| **Alur Persetujuan** | Urutan persetujuan per modul |
| **Step Persetujuan** | Langkah dalam sebuah alur |

### Mengatur alur persetujuan

Satu modul (cuti, kasbon, lembur) boleh punya **satu alur aktif**. Setiap alur
berisi langkah berurutan, dan setiap langkah menentukan siapa approvernya:

- **Atasan langsung pemohon** — mengikuti kolom atasan di data karyawan
- **Berdasarkan role** — siapa pun yang punya peran tersebut
- **User tertentu** — orang yang ditunjuk

Alur tanpa langkah akan membuat pengajuan gagal. Pastikan setiap alur aktif punya
minimal satu langkah.

### Hal yang perlu kehati-hatian

- **Perubahan izin baru berlaku setelah pengguna login ulang.**
- **Jangan menghapus seluruh peran seseorang.** Pengguna tanpa peran justru
  diperlakukan sebagai admin — sisa kompatibilitas dari versi lama. Ganti
  perannya, jangan dikosongkan.
- **Audit Log** mencatat setiap perubahan data beserta nilai sebelum dan
  sesudah. Berguna saat ada sengketa angka.

---

## Absensi harian di pintu masuk

Buka `/scan` di perangkat yang diletakkan di pintu masuk — tablet, atau komputer
dengan kamera. Halaman ini **tidak perlu login**, jadi bisa dibiarkan terbuka.

Cara pakai:

1. Karyawan menunjukkan QR di kartunya ke kamera
2. Terdengar nada berhasil, jam tercatat di layar
3. Scan **pertama** hari itu = jam masuk. Scan **berikutnya** = jam keluar

Kalau terdengar nada gagal, QR-nya tidak dikenali — kemungkinan kartu lama atau
karyawan sudah tidak aktif.

Yang perlu diperhatikan:

- **Izinkan akses kamera** saat browser memintanya
- **Izinkan akses lokasi** — dipakai memeriksa apakah scan dilakukan di area
  kantor. Kalau ditolak, absensi tetap tercatat tetapi tanpa keterangan lokasi
- Perangkat harus punya koneksi ke server

---

## Pertanyaan yang sering muncul

**Kenapa slip gaji bulan ini kosong?**
Rekap gaji dihitung untuk satu bulan penuh. Bulan yang sedang berjalan belum
punya rekap sampai perhitungannya dijalankan di akhir bulan.

**Kenapa cuti saya tetap memotong gaji padahal sudah diajukan?**
Pengajuan yang belum disetujui tidak melindungi. Periksa statusnya di
**Cuti** — kalau masih menunggu, ingatkan atasanmu.

**Kenapa absensi saya ditandai di luar radius?**
Scan dilakukan di luar jangkauan lokasi kantor, atau izin lokasi ditolak saat
scan. Kalau kamu memang di kantor, minta HR memeriksa koordinat dan radius
cabang di **Organisasi → Cabang**.

**Kenapa anggota tim saya tidak muncul di daftar?**
Kolom atasannya belum menunjuk ke kamu. Minta HR mengisinya di **Users**.

**Kenapa tombol ubah tidak ada padahal saya manager?**
Peran manager memang hanya bisa melihat dan menyetujui. Perubahan data dilakukan
HR.

**Saya HR tapi tidak bisa membuka menu Role.**
Benar — pengaturan peran dan izin hanya untuk Super Admin. Ini disengaja: yang
menentukan siapa boleh menyetujui apa tidak boleh diubah oleh pelaksana.

**Kenapa muncul pesan "sudah ada alur aktif untuk modul ini"?**
Satu modul hanya boleh punya satu alur persetujuan aktif. Nonaktifkan yang lama
lebih dulu.

**Bisakah menghapus karyawan yang sudah resign?**
Sebaiknya jangan. Ubah **status kepegawaian** menjadi resign — riwayat gaji dan
absensinya tetap tersimpan, dan namanya hilang dari perhitungan yang sedang
berjalan.
