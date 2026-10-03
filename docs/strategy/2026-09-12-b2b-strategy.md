# Strategi B2B RitmeHR — Direct Go-To-Market

> Status: DRAFT untuk review Capt
> Tanggal: 2026-09-12
> Relasi: partnership (dokumen terpisah) = salah satu channel. B2B direct = mesin utama.

---

## 1. Filosofi

**"Open source core, paid trust layer."**

RitmeHR gratis (MIT) — perusahaan TIDAK bayar untuk software-nya. Mereka bayar untuk **kepastian**: install yang benar, migrasi data, training, update, backup, support, SLA. Ini keunggulan struktural vs SaaS per-user: kompetitor jual akses ke sistem mereka (data di vendor, bayar terus per kepala); kita jual kepemilikan + layanan di atasnya (data di server klien, bayar flat per perusahaan).

---

## 2. Posisi Pasar & Kompetitor

### Positioning map

```
                KONTROL & KEPEMILIKAN DATA
                          ▲
        SaaS HR (Talenta, │  RitmeHR (+paid layer)
        Gadjian, GreatDay,│  = data sendiri, biaya rendah,
        LinovHR, HashMicro)│   kustom penuh, Rp 0 lisensi
        = mudah, tapi data │
        di vendor, per-user│
                          │
  Mahal ◀────────────┼────────────▶ Murah
                          │
                          │  Excel / spreadsheet
                          │  = murah, tapi gak scalable,
                          │   rawan salah input
                          ▼
```

### Tabel kompetitor (kualitatif — angka harga perlu verifikasi sebelum jadi materi sales)

| Kompetitor | Model | Target | Kekuatan | Kelemahan (= peluang kita) |
|---|---|---|---|---|
| Talenta (Mekari) | SaaS per-user + add-on | 50–5000 | Ekosistem Mekari, brand besar | Mahal per-user, data di vendor, fitur add-on berbayar |
| Gadjian | SaaS per-user | 10–200 | Payroll & PPh 21 kuat | Harga per-user, lock-in, kustomisasi terbatas |
| GreatDay HR | SaaS per-user | 20–500 | Brand + ekosistem BPJS | Per-user cost, vendor lock |
| LinovHR | SaaS per-user | 100–5000 | Fitur enterprise | Kompleks + mahal, implementasi lama |
| HashMicro | SaaS per-user | SME–corp | Cakupan ERP | Mahal, overkill buat HR-only |

**Diferensiasi RitmeHR:** satu-satunya HRIS open source full-featured yang dirancang *khusus Indonesia* (PPh 21, BPJS, absensi QR + geofence). Kompetitor open-source global (OrangeHRM, Odoo HR) gak native PPh 21/BPJS. Ini moat.

---

## 3. ICP & Segmentasi

### 3 Segmen prioritas

| Segmen | Profil | Pain | Buyer | Kecepatan deal |
|---|---|---|---|---|
| **S1 — UMKM modern** | 20–150 karyawan, founder tech-savvy, multi-cabang / ritel / jasa | Capek Excel, SaaS mahal, data gak pegang | Founder / COO | Sedang (2–6 minggu) |
| **S2 — Startup scale-up** | 15–80 karyawan, engineer in-house | Budget ketat, butuh HR tool cepat, gak mau SaaS per-user | COO / Founder | **Cepat (1–3 minggu)** — engineer bisa self-host + jadi champion internal |
| **S3 — Perusahaan multi-cabang** | 30–300 karyawan, retail/manufaktur/F&B, banyak cabang | Absensi QR + geofence = wajib; payroll terpusat antar-cabang | HR Director / Ops Manager | Lambat (1–3 bulan, consultative) |

### Not-target dulu
- Enterprise >500 karyawan — butuh SSO, SLA ketat, enterprise sales cycle. Paket Enterprise bisa menyusul.
- Mikro <15 karyawan — value kecil, harga sensitif. Tangkap lewat konten, bukan sales.

### Persona pembeli
1. **Founder / COO** (S1, S2) — pegang budget, paling cepat mutusin, respect sama open source
2. **HR Manager / Director** (S3) — butuh yakinkin ke bos; kasih mereka one-pager TCO buat presentasi internal
3. **IT / DevOps lead** — influencer + champion self-host; kasih mereka akses repo + docs, mereka yang jualin ke atas

---

## 4. Offering: 3 Paket

### Community — Rp 0
Self-host mandiri. Source, docs, komunitas. Support komunitas.

### Business — untuk S1/S2/S3
Yang dibayar: **kepastian**, bukan fitur.
- Managed install (server klien atau VPS managed)
- Update & backup terjadwal
- Training HR admin + karyawan (online)
- Support email/WA dengan SLA (jam kerja)
- Onboarding 30 hari + migrasi data dari Excel/SaaS lama
- Pricing: **flat per perusahaan** (bukan per-user — ini pembeda utama vs kompetitor)
  - Opsi yang gue rekomendasikan: banded flat, mis. 1–100 karyawan / 101–250 / 250+ + onboarding fee one-time
  - Angka final → keputusan Capt (lihat §10)

### Enterprise — S3 besar / korporasi (menyusul)
On-prem/VPC, SSO, SLA 99.9%, dedicated support, custom module, audit trail lanjutan. Quote custom.

> **Keputusan penting:** Business = "managed instance". Siapa yang host? Opsi: (a) VPS kita sendiri, (b) hosting partner (sinergi sama strategi partnership Tier C), (c) infra klien tapi kita yang manage. Ini nentuin feasibility — lihat §10.

---

## 5. GTM — 3 Channel

### 5.1 Direct outbound (UTAMA — mulai duluan)
- **Taktik:** account-based. Pilih target per segmen → riset (LinkedIn, website, pain spesifik) → email personal + LinkedIn DM → kalender demo.
- **Sequence 3 langkah:** intro value (TCO angle, 200 kata) → ke-3 hari: case/pilot offer → ke-10: follow-up terakhir (archive).
- **Skala:** 10–15 target/minggu, kualitas > kuantitas.
- **Prioritas segmen:** S2 dulu (fastest win — engineer bisa deploy sendiri), lalu S1, S3 paling akhir karena cycle-nya panjang.

### 5.2 Inbound / demo-led
- Konten marketing udah ada (Rp 0 lisensi campaign, video 53 detik) → landing page pricing + CTA "Book demo 30 menit"
- **Pilot-led motion:** 14 hari trial terkelola (instance trial + data dummy) → konversi ke Business
- Perlu: halaman pricing + halaman /demo dulu (§8)

### 5.3 Partnership (komplementer, paralel)
- Snapshot dari strategi partnership: hosting (referral), consultant/payroll (implementation). B2B direct tetap mesin utama; partner nambah volume.

---

## 6. Sales Motion (Pilot-Led, 6 Langkah)

1. **Qualify** — ICP? Budget? Pain konkret (payroll manual? multi-cabang? SaaS mahal?)
2. **Demo 30 menit** — live instance + video yang udah ada; fokus ke pain mereka
3. **Pilot 14 hari** — install di infra/inisiatif mereka, seed data dummy, QR absen beneran
4. **Review** — 2 rekap singkat: yang jalan, yang kurang
5. **Proposal** — paket Business/Enterprise + onboarding plan
6. **Close & onboarding** — 2 minggu, 1 champion internal per klien

**Tooling:** spreadsheet pipeline (no CRM — tahap ini cukup). Kolom: nama, segmen, kontak, status (prospect→contacted→meeting→demo→pilot→proposal→won/lost), source, next action.

---

## 7. Objection Handling B2B

| Keberatan | Jawaban |
|---|---|
| "Open source = gak ada support?" | Paket Business = support + SLA. Komunitas + docs. 403 tests = kualitas teruji. |
| "Self-host ribet, IT kami kecil" | Managed install dari kami / hosting partner. Training termasuk. |
| "Migrasi dari Excel / dari SaaS lain?" | Skrip impor data karyawan + riwayat gaji. Pilot 14 hari buat buktiin. |
| "Data aman?" | Di server kamu, bukan server vendor. Enkripsi, audit trail, backup. Tanpa lock-in. |
| "Kalau maintainer-nya berhenti?" | MIT — source publik tetap ada, bisa diambil siapa pun. Gak ada vendor yang bisa matiin produk kamu. |
| "Kenapa bukan Talenta aja?" | TCO: Rp 0 lisensi + flat per perusahaan vs per-user terus-menerus. Data kamu. Kustom penuh. |

---

## 8. KPI 90 Hari

| Metrik | Target |
|---|---|
| Outbound: contacted → meeting | 80 → 20 |
| Demo diberikan | 10 |
| Pilot dimulai | 5 |
| **Paid conversion (Business)** | **3** (1 S2, 1 S1, 1 S3) |
| MRR dari B2B | ≥ Rp 3–4,5 jt (estimasi, tergantung pricing final) |

---

## 9. Roadmap 90 Hari

### Wave 1 (Minggu 1–2) — Fondasi
- Pipeline tracker (spreadsheet) + template sequence email/LinkedIn
- Halaman pricing (3 paket) + CTA demo
- Pilot kit (panduan 14 hari, seed data, checklist review)
- Keputusan hosting Business instance (§10.3)

### Wave 2 (Minggu 3–8) — Outbound
- Minggu 3–6: outbound S2 (startup) — 10–15/minggu
- Minggu 5–8: outbound S1 (UMKM) — 10–15/minggu
- Demo + pilot paralel untuk yang hangat
- Partnership paralel (hosting follow-up dari strategi sebelumnya)

### Wave 3 (Minggu 9–12) — Konversi & Inbound
- Outbound S3 (multi-cabang) — 5–10/minggu
- Follow-up semua pipeline, closing target 3 paid
- Landing page inbound live + mulai konten case study dari pilot yang sukses

---

## 10. Keputusan yang Perlu Capt

1. **Pricing Business** — banded flat berapa? (estimasi: 1–100 karyawan mulai Rp 1–1,5 jt/bln + onboarding once; bisa di-breakdown per tier. Ini yang paling nentuin MRR & positioning)
2. **Segmen pertama** — S2 (startup, deal 1–3 minggu) atau S1 (UMKM, volume lebih besar)? Rekomendasi gue: S2 dulu buat first 3 wins, S1 menyusul.
3. **Hosting Business instance** — VPS kita sendiri / hosting partner / infra klien? Ini feasibility check paling kritis buat offering Business.
4. **Halaman pricing** — buat sekarang atau nunggu pricing fix?

---

## 12. Strategi Komunitas & Jaringan — "Komunitas = Distribution"

> Prinsip: komunitas bukan sekadar marketing — ini jalur **rekrut reseller/konsultan** dan **sumber leads B2B yang hangat**. Anggota yang udah kenal kamu = yang paling gampang diajak jadi mitra.

### 12.1 Manfaat komunitas (kenapa ini jalur terbaik)

| Manfaat | Kenapa |
|---|---|
| Trust instan | Orang beli dari orang yang dikenal. Anggota komunitas udah kenal kamu |
| Rekrut reseller/konsultan | Kamu gak perlu cari mitra dari nol — mereka udah berkumpul |
| Leads hangat | Rekomendasi dalam komunitas > cold email 10x |
| Feedback produk | Uji fitur baru sama orang yang paham konteks HR Indonesia |
| Ownership | Orang yang bantu bangun = orang yang rela promosiin |

### 12.2 Dua lapis komunitas

**Lapis A — Komunitas yang kamu punya / akses (eksisting):**
- Komunitas offline (HIPMI, Apindo, Kadin, komunitas HR, UMKM lokal)
- Komunitas online (Telegram/WA/Discord) — kalau ada, ini aset paling cepat
- **Aksi:** jangan jualan di sini duluan. **Bangun hubungan** — jadi resource (share template, insight, tool gratis). Orang yang kamu bantu akan jadi champion.

**Lapis B — Komunitas baru seputar RitmeHR / open source HRIS:**
- Punya positioning "open source HRIS Indonesia" → komunitas = tempat ngumpul founder/HR yang pengen lepas dari SaaS mahal
- Bentuk yang realistis: **Telegram group** dulu (murah, cepat), nanti forum (Discourse) kalau udah besar
- Ini yang nanti jadi kumpulan calon **reseller & konsultan** — sekaligus tempat beta test fitur baru

### 12.3 Model ekonomi komunitas → partner

```
Komunitas (trust + knowledge)
   │
   ├──► Member yang mau jualan       = RESELLER (referral, komisi dari services)
   ├──► Member yang mau deliver      = KONSULTAN / implementation partner (fee proyek)
   └──► Member yang cuma butuh tool  = LEADS / user (trial, pilot, B2B deal)
```

- **Reseller:** rekomendasiin + closing → komisi dari services (karena lisensi Rp 0, komisi gak dari lisensi)
- **Konsultan:** install, custom, training, support klien mereka sendiri → fee proyek mereka, kita backstop
- **Beta tester:** roadmap + feedback, jadi champion komunitas

### 12.4 Program "Komunitas → Mitra" (3 tahap)

| Tahap | Aktivitas | Output |
|---|---|---|
| **1. Bangun** (1–3 bulan) | Aktif bantu di komunitas yang ada + buka Telegram RitmeHR; share insight, template, konten | Kenalan, trust, calon mitra kerangkeng (cold list) |
| **2. Rekrut** (3–6 bulan) | Dari yang aktif, tawari jadi Reseller / Implementation Partner (program 2-tier dari strategi partnership) | 3–5 mitra dari komunitas (bukan cold outreach!) |
| **3. Scaling** (6–12 bulan) | Mitra bawa leads; komunitas jadi sumber referral terus-menerus | Leads hangat recurring, community as a moat |

### 12.5 Aset yang perlu dibuat

| Aset | Status | Untuk |
|---|---|---|
| Telegram community RitmeHR | ❌ buka | Lapis B |
| Konten value (template, insight, tool) buat dibagi di komunitas | ❌ | Lapis A (hubungan) |
| One-pager "Jadi Reseller/Konsultan RitmeHR" | ❌ | rekrut mitra |
| Referral program + kode referral | ❌ | reseller |
| Beta program (fitur baru dicoba member) | ❌ | engagement + feedback |

### 12.6 Hubungan dengan B2B & partnership

- **B2B direct** = mesin utama (revenue + kontrol) — tetap prioritas
- **Partnership** (hosting/consultant/software house) = volume + delivery capacity — jalur paralel
- **Komunitas** = sumber mitra & leads paling hangat — nutrisi semua jalur, effort rendah, compounding

---

## 13. Aset yang Perlu Dibuat (minus yang sudah ada)

| Aset | Status | Untuk |
|---|---|---|
| Video Rp 0 lisensi + konten marketing | ✅ ada | inbound, demo |
| Draft email hosting + kontak | ✅ ada | partner Tier C |
| Halaman pricing + /demo | ❌ | inbound B2B |
| Sequence email B2B (3 langkah) + LinkedIn DM | ❌ | outbound |
| One-pager TCO (buat presentasi internal buyer) | ❌ | objection + S3 |
| Pilot kit 14 hari | ❌ | pilot motion |
| Sales deck 6 slide | ❌ | demo/meeting |
| Pipeline tracker spreadsheet | ❌ | operasional |