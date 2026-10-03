# Strategi Partnership RitmeHR

> Status: DRAFT untuk review Capt
> Tanggal: 2026-09-12
> Tujuan: dapet partner yang **nyata** (bukan sekadar MoU di atas kertas) — partner yang bawa leads, implementasi, dan delivery capacity.

---

## 1. Definisi "Punya Partner"

Target 90 hari pertama (measurable):

| Metrik | Target |
|---|---|
| Partner aktif (kontrak/komitmen verbal jelas) | **3** |
| — 1 hosting/cloud (listing / managed package) | |
| — 2 implementation partner (consultant/payroll/software house) | |
| Leads masuk via partner | **10** |
| Implementasi selesai via partner | **3** |

Partner aktif = bukan cuma "kenalan". Ada deliverable nyata: halaman listing, atau klien yang di-handle, atau demo instance jalan.

---

## 2. Yang Kita Tawarkan (Ammunition)

- HRIS all-in-one **open source MIT** — 14 modul (absensi QR, payroll PPh 21/BPJS, cuti, kasbon, portal, rekrutmen, kinerja, dll)
- **Rp 0 lisensi** — bukan versi trial, full product
- Production-ready: 403 test passing, Laravel 12, dokumentasi lengkap
- Selling point partner: klien mereka dapet sistem HR modern **tanpa biaya lisensi**, data di server sendiri, tanpa lock-in SaaS

**Konsekuensi strategis:** lisensi gratis → partner adalah jalur monetisasi (services), bukan cuma saluran distribusi. Dua-duanya dirancang di bawah.

---

## 3. Tipe Partner & Prioritas

### 🥇 Tier A — HR Consultant / Biro Payroll / HR Outsourcing
**Kenapa ini prioritas #1:** mereka **setiap hari** ngobrol sama klien yang butuh HR system. Mereka sekarang kerja pakai Excel atau langganan SaaS mahal atas nama klien. RitmeHR kasih mereka tool profesional + revenue stream baru (fee implementasi) tanpa biaya lisensi.

- Value prop: tool yang bisa mereka deploy ke klien, training + technical backstop dari kita, bisa custom per klien (MIT)
- Bentuk: Implementation Partner (lihat §4)
- Contoh target: konsultan HR & biro jasa payroll skala 5–50 karyawan di Jabodetabek/Surabaya/Bandung (riset kontak menyusul), praktisi HR freelance yang handle payroll klien
- **Yang kita butuh dari mereka:** komitmen install + support klien mereka sendiri

### 🥇 Tier B — Software House / Laravel Dev Shop
- Value prop: RitmeHR = base code solid (403 tests) untuk custom HR project klien. Mereka hemat 80% waktu build dari nol, klien dapet sistem lengkap, mereka jual services customisasi
- Bentuk: Implementation Partner (bisa juga jadi Referral)
- Contoh target: software house skala 10–100 orang yang kerjain project UMKM/korporasi (riset menyusul)

### 🥈 Tier C — Hosting / Cloud Provider (yang udah ada draft emailnya)
- Benar sebagai **distribusi skala** (listing, one-click install, managed package), tapi konversinya lambat tanpa marketplace aktif
- Value prop per provider beda-beda:
  - **Qwords** — punya program VIP Partner resmi → masuk program mereka
  - **Biznet Gio** — halaman Partnership (Reseller/Afiliasi/Developer) → daftar
  - **IDCloudHost** — draft email personal udah ada (mufid@cloudhost.asia) → offer: **managed HRIS hosting package** (mereka jual paket hosting + RitmeHR terinstall, revenue share) + demo instance
  - **Exabytes** — sales@exabytes.co.id, ada menu "Kerjasama"
  - **IndoWebsite** — info@7ion.co.id, form partnership
- Catatan jujur: provider tanpa app marketplace ("one-click install") value-nya tipis → ganti angle ke **managed package + demo instance**
- Bentuk: Referral Partner / listing

### 🥉 Tier D — Asosiasi & Komunitas (volume, effort rendah)
- Apindo / HIPMI / Kadin chapter, komunitas HR (HRD Forum, grup LinkedIn HR), inkubator, coworking
- Value prop: member benefit program — "anggota dapat HRIS modern Rp 0 lisensi + prioritas onboarding"
- Bentuk: Referral Partner, branding doang (badge "Official Partner")
- Efek samping: credibility + social proof buat RitmeHR

**Peringkat effort vs dampak:**
```
Dampak tinggi ┤  A (consultant)   B (software house)
             ┤
             ┤  C (hosting)
             ┤  D (asosiasi)
             └──────────────────────────────
              Effort rendah            tinggi
```

---

## 4. Program Partner — 2 Tier, Simpel

### Referral Partner (ringan)
- **Cara kerja:** rekomendasiin RitmeHR ke klien/kontak → kasih kode referral → kita yang onboarding + implementasi
- **Benefit:** badge "Referral Partner" di ritmehr.com/partner, listing nama+brand, komisi % (hanya dari services berbayar yang kita closing via referral mereka — bukan dari lisensi, karena Rp 0)
- **Syarat:** minimal 1 referral valid per kuartal (biar bukan partner tidur)
- **Cocok untuk:** Tier C & D

### Implementation Partner (sertifikasi)
- **Cara kerja:** mereka install, custom, training, support klien mereka sendiri pakai RitmeHR. Kita sediakan technical backstop (channel partner + SLA best effort) + update rilis
- **Benefit:** sertifikat "RitmeHR Certified Implementation Partner", listing prioritas di halaman partner, co-branding, kita rekomendasikan mereka balik (referral timbal balik) ke klien yang butuh implementer lokal, akses early ke fitur baru
- **Syarat:** lulus onboarding call + demo install sendiri + 1 implementasi referensi
- **Cocok untuk:** Tier A & B

> Aturan main (dari pelajaran outreach kemarin): **jangan lead dengan revenue share** di email pertama. Yang dijual duluan: value buat klien mereka + tool profesional. Uang dibahas pas meeting.

---

## 5. Partner Kit (Aset yang Harus Dibuat)

| Aset | Status | Untuk |
|---|---|---|
| Draft email hosting (generic + IDCloudHost) | ✅ ada | Tier C |
| Video "Rp 0 Lisensi" | ✅ ada | semua tier (lampiran follow-up) |
| One-pager partner PDF (value prop + tier + kode referral) | ❌ buat | email follow-up |
| Deck partner 6 slide | ❌ buat | meeting |
| Demo instance khusus partner (login dibagikan) | ❌ buat | demo live |
| Halaman ritmehr.com/partner (tier, benefit, form daftar) | ❌ buat | landing semua outreach |
| Template email per tier (A/B/C/D) | ❌ buat | gelombang outreach |
| Kode referral (format simpel: `RHR-{nama partner}`) | ❌ buat | tracking |

---

## 6. Monetisasi (Karena Lisensi Rp 0)

Partner = dua revenue stream yang bisa diaktifkan:

1. **Implementasi & custom** — kita (atau implementation partner) charge klien untuk setup, training, migrasi data, customisasi. Persentase untuk kita kalau klien datang dari referral partner.
2. **Managed hosting** — hosting partner jual paket "RitmeHR Managed" (VPS + install + maintenance). Revenue share kecil per paket. Ini yang paling realistis buat Tier C.

Yang **tidak** kita kejar dulu: white-label penuh (kompleks, nanti), SaaS subscription model (bentrok sama positioning Rp 0).

---

## 7. Eksekusi — 3 Gelombang, 90 Hari

### Wave 1 (Minggu 1–2): Fondasi
- Buat halaman /partner + one-pager + template email Tier A/B/C/D
- Siapin demo instance partner
- Kickoff internal: set kode referral

### Wave 2 (Minggu 3–8): Outreach bertingkat
- **Minggu 3–4 — Tier C (hosting):** follow-up yang udah ada (IDCloudHost) + kirim personal ke Qwords, Biznet Gio, Exabytes, IndoWebsite. Follow-up 3 hari kerja, lampiran one-pager
- **Minggu 5–8 — Tier A (consultant/payroll):** riset kontak (LinkedIn + email), 10–15 email personal. Ini gelombang dengan konversi tertinggi → prioritas effort
- **Minggu 7–8 — Tier B (software house):** 5–10 target

### Wave 3 (Minggu 9–12): Asosiasi + follow-up
- Tier D: kirim penawaran member benefit ke 3–5 asosiasi/komunitas
- Follow-up semua yang udah dihubungi (max 2x per kontak)
- Closing: minimal 3 partner aktif (target §1)

### Cadence
- Weekly: review pipeline (Airtable/spreadsheet partner tracker), balas semua inbound < 24 jam
- Follow-up rule: hari kerja ke-3, lalu ke-10 (2x max, lalu archive)

---

## 8. Tracking & KPI

Partner tracker simpel (Airtable atau spreadsheet — gak perlu CRM berat):

| Kolom | Isi |
|---|---|
| Partner | nama + tier + tipe |
| Kontak | email/WA + PIC |
| Status | prospecting → contacted → meeting → proposal → active → dormant |
| Source | gelombang mana |
| Referral code | RHR-xxx |
| Deliverable | listing? MoU? implementasi? |
| Terakhir follow-up | tanggal |

KPI mingguan: pipeline breakdown (berapa contacted → meeting → proposal), leads via referral, implementasi selesai.

---

## 9. Anti-Patterns (Jangan Dilakukan)

- ❌ Lead dengan revenue share / komisi di email pertama → bahas saat meeting
- ❌ Minta "hosting gratis buat kita" → itu request, bukan partnership
- ❌ White-label di percakapan awal → kompleks, buat nanti
- ❌ Email ke support@ → mati di ticket queue. Selalu PIC partnership/sales
- ❌ Email panjang > 250 kata → founder terima 100+ email/hari
- ❌ Nada mengemis ("mohon bantuan") → kita yang bawa value, bukan minta tolong
- ❌ 50 email generic → 10 email personal yang riset dulu, konversinya jauh lebih tinggi

---

## 10. Aset Existing yang Dipakai Ulang

- `docs/partnership-email-draft.md` — draft hosting (generic + IDCloudHost) → base untuk Tier C
- `docs/marketing/2026-09-07-rp0-lisensi-campaign.md` — angle Rp 0 lisensi → narasi one-pager partner
- `docs/marketing/video-rp0/final_vertical_vo_720.mp4` — video promosi → follow-up attachment
- Skill `business-outreach` → referensi kontak hosting + template email

---

## Keputusan yang Perlu Capt

1. **Prioritas Tier A dulu atau tetap hosting dulu?** (Gue rekomendasikan: parallel — hosting udah punya draft, tinggal kirim; sambil itu riset kontak Tier A karena konversinya paling tinggi)
2. **Halaman /partner** — buat di ritmehr.com atau cukup one-pager dulu?
3. **Kode referral manual atau butuh fitur kecil di app?** (rekomendasi: manual dulu, spreadsheet; fitur app nanti kalau partner udah >5)