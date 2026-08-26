# WhatsApp Notifications — WAHA (self-hosted)

> **Status:** WAHA adalah satu-satunya provider WhatsApp (Fonnte sudah dihapus).
> WAHA berjalan sebagai container sendiri — tidak ada ketergantungan SaaS pihak ketiga.
> Dokumen resmi: https://waha.devlike.pro/docs/overview/quick-start/

## 1. Jalankan container WAHA

Cara cepat (Docker):

```bash
docker run -it --rm \
  -p 3000:3000 \
  --name waha \
  devlikeapro/waha
```

Atau via `docker-compose.yml` (disarankan untuk produksi — jalan bareng app):

```yaml
services:
  waha:
    image: devlikeapro/waha
    container_name: waha
    restart: unless-stopped
    ports:
      - "3000:3000"
    environment:
      # Amankan API dengan key (opsional tapi disarankan).
      - WAHA_API_KEY=ganti-dengan-key-rahasia
    volumes:
      - waha-sessions:/app/.sessions

volumes:
  waha-sessions:
```

> Jika app Laravel juga di dalam docker network yang sama, base URL-nya
> `http://waha:3000` (nama service). Kalau app di host, pakai `http://localhost:3000`.

## 2. Pairing nomor WhatsApp (sekali saja)

1. Buka dashboard WAHA: `http://localhost:3000` (atau URL server).
2. Start session `default` (atau nama lain).
3. Scan **QR code** pakai WhatsApp di HP (Linked Devices) — sama seperti WhatsApp Web.
4. Status session harus `WORKING`.

## 3. Hubungkan ke aplikasi (Pengaturan)

Login super admin → **Pengaturan → Notifikasi (WhatsApp)**:

| Field | Isi |
|-------|-----|
| Aktifkan WhatsApp | ✅ |
| Provider WhatsApp | **WAHA (self-hosted)** |
| WAHA Base URL | `http://waha:3000` (atau `http://localhost:3000`) |
| WAHA Session | `default` |
| WAHA API Key | isi jika `WAHA_API_KEY` di-set di container |

Simpan, lalu klik **Kirim Tes** (isi nomor tujuan `08xx`) untuk verifikasi koneksi
sungguhan. Panel **Status Integrasi** akan menampilkan `Aktif — WAHA: <url>`.

## 4. Cara kerja di kode

- `App\Services\Notifications\WahaWhatsAppGateway` — kirim via `POST {base}/api/sendText`
  body `{session, chatId, text}`, header `X-Api-Key` bila diisi.
- Nomor dinormalisasi ke format `62xxxx@c.us` otomatis (`08xx`/`+62xx`/`62xx` diterima).
- Pemilihan gateway ada di `AppServiceProvider`:
  `whatsapp_enabled` → jika aktif & `waha_url` terisi pakai `WahaWhatsAppGateway`,
  selain itu fallback ke `LogWhatsAppGateway` (mode log).
- Semua notifikasi lewat `NotificationService` → gateway aktif. Kegagalan gateway
  **tidak** memutus aksi bisnis (di-log, return false).

## Troubleshooting

- **Kirim Tes gagal / mode log:** cek `whatsapp_enabled` aktif dan WAHA URL
  terisi. Lihat `storage/logs/laravel.log` tag `[WhatsApp:waha]`.
- **401/403 dari WAHA:** API key tidak cocok dengan `WAHA_API_KEY` container.
- **Pesan tak sampai walau 2xx:** pastikan session WAHA berstatus `WORKING`
  (scan ulang QR bila logout).
