# Bug List — Modul 02 Absensi

Test case: [../test-cases/02-absensi.md](../test-cases/02-absensi.md)

| Hasil suite | 12 PASS / 6 FAIL |
|---|---|
| Bug modul ini | BUG-005 (Libur Nasional) |
| Bug lintas modul | BUG-003, BUG-004 |

---

## BUG-005 — Libur Nasional tanpa validasi → HTTP 500

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Status** | Terkonfirmasi |
| **Test case** | `national-holiday/V-empty`, `HOL-V-01` |

### Reproduksi

Buka **Absen → Libur Nasional → Add**, langsung klik Simpan tanpa mengisi apa pun.

### Hasil aktual

```
HTTP 500
SQLSTATE[HY000]: General error: 1364 Field 'date' doesn't have a default value
```

### Akar masalah

[NationalHolidayRequest.php](../../app/Http/Requests/NationalHolidayRequest.php)
mengembalikan array kosong — seluruh aturannya dikomentari:

```php
public function rules()
{
    return [
        // 'name' => 'required|min:5|max:255'
    ];
}
```

Aturan yang dikomentari menyebut `name`, sedangkan form memakai `date` dan
`info`. Jadi mengaktifkannya kembali apa adanya **tetap salah sasaran**.

### Perbaikan

```php
public function rules()
{
    return [
        'date' => 'required|date|unique:national_holidays,date',
        'info' => 'required|string|max:255',
    ];
}

public function messages()
{
    return [
        'date.required' => 'Tanggal libur wajib diisi.',
        'date.unique'   => 'Tanggal libur ini sudah terdaftar.',
        'info.required' => 'Keterangan libur wajib diisi.',
    ];
}
```

Untuk update, gunakan `Rule::unique('national_holidays','date')->ignore($id)`.

Detail lengkap: [lintas-modul.md § BUG-005](lintas-modul.md#bug-005--delapan-entity-tanpa-validasi-server--http-500).

---

## BUG-003 — Manager bisa menulis ke seluruh modul absensi

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `schedule/A-mgr-write`, `national-holiday/A-mgr-write`, `presence/A-mgr-write` |

Manager mendapat HTTP 200 pada ketiga form create, padahal hanya punya
`presence.view` dan `schedule.view` — keduanya baca saja.

Dampaknya nyata: manager dapat **menyisipkan atau mengubah baris presensi**, yang
langsung memengaruhi perhitungan gaji, denda keterlambatan, dan potongan absen.
Manager juga bisa mengubah jadwal kerja dan menambah hari libur nasional, yang
mengubah hitungan hari kerja seluruh perusahaan.

Permission yang sudah ada tinggal ditegakkan: `presence.create`, `presence.edit`,
`presence.delete`, `schedule.edit`, `schedule.mass_update`,
`national_holiday.edit`.

Perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

---

## BUG-004 — Manager melihat seluruh presensi perusahaan

| | |
|---|---|
| **Severity** | 🟠 Tinggi |
| **Test case** | `presence/A-mgr-scope` |

Manager melihat **110 dari 110** baris presensi — identik dengan super_admin,
bukan hanya timnya.

Perbaikan: [lintas-modul.md § BUG-004](lintas-modul.md#bug-004--scoping-tim-manager-tidak-diterapkan).

---

## BUKAN bug — sudah diverifikasi

### Edit jadwal ditolak "Kolom hari libur harus diisi"

Muncul di suite sebagai `schedule/U-edit` FAIL, tetapi **bukan bug aplikasi**.

`day_off` adalah sekumpulan **checkbox**, bukan `<select>`. Baris uji yang
dibuat harness lewat POST mentah mengirim `day_off=Minggu` sebagai string,
menghasilkan baris dengan nilai tidak sah yang kemudian tidak lolos validasi
saat diedit.

Dibuktikan dengan dua pemeriksaan:

| Uji | Hasil |
|---|---|
| Edit jadwal seed `Reguler 08-17` (id=1), ubah nama, Simpan | ✅ tersimpan |
| Buat jadwal lewat form UI lalu edit | ✅ tersimpan |

Pelajaran untuk harness: entity dengan field checkbox/multi-select tidak bisa
dibuat lewat POST datar — gunakan interaksi form.

### Geofence

Seluruh 110 baris presensi demo menunjukkan `outside=0` dan **nol** teks
"Di Luar Radius" di UI. Regresi lama (setiap baris hasil import/seeder ditandai
di luar radius karena observer hanya menghitung saat *update*) sudah tidak
muncul.

---

## Belum teruji otomatis

Butuh perangkat keras atau simulasi yang belum tersedia di harness:

| Area | Hambatan |
|---|---|
| Scan QR sungguhan (`SCAN-C-01`…`03`) | Perlu kamera fisik atau injeksi stream video |
| Geofence dalam/luar radius (`SCAN-C-05`…`08`) | Perlu mock geolokasi per cabang |
| Mass update jadwal (`SCHM-U-01`) | Interaksi pilih-banyak |
| Filter bar presensi (`PRES-R-04`, `PRES-R-05`) | Parameter GET `HasSimpleFilters` |
| Presensi untuk user tanpa jadwal (`PRES-C-06`) | Perlu akun uji khusus — dulu pernah membuat `calculateExtraTime()` crash |
