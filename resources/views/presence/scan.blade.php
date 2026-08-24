{{--
    Halaman pemindai QR di pintu masuk. Berdiri sendiri, tidak memakai layout
    admin: perangkat di pintu masuk tidak boleh menampilkan navigasi admin, dan
    halaman ini harus tetap ringan tanpa jQuery maupun pustaka toast.

    Gaya visual mengikuti pendekatan desain Apple: umpan balik seketika, material
    translusen untuk hierarki, tipografi dengan tracking spesifik per ukuran, dan
    menghormati preferensi gerak, transparansi, serta kontras pengguna.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b0b0f">
    <title>Scan Absensi</title>

    <style>
        /* ── Fondasi ─────────────────────────────────────────────────────────
           Font sistem lebih dulu: ia sudah membawa optical sizing dan tabel
           tracking sendiri. Seluruh jarak memakai rem agar layout ikut membesar
           saat pengguna menaikkan ukuran teks. */
        :root {
            --bg: #0b0b0f;
            --ink: #f5f5f7;
            --ink-dim: #a1a1aa;
            --ok: #30d158;
            --bad: #ff453a;
            --warn: #ffd60a;

            /* Material: translusen di atas latar gelap. */
            --mat: rgba(255, 255, 255, 0.07);
            --mat-edge: rgba(255, 255, 255, 0.14);
            --mat-blur: 24px;

            /* Kritis teredam (damping 1.0) — tanpa overshoot, karena tidak ada
               gestur bermomentum di halaman ini. Response ~0.4s. */
            --ease: cubic-bezier(0.32, 0.72, 0, 1);
            --dur: 400ms;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100%;
            background: var(--bg);
            color: var(--ink);
            font: 100%/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-height: 100dvh;
            padding: clamp(1rem, 3vw, 2rem);
            gap: clamp(1rem, 2.5vh, 1.75rem);
        }

        /* ── Kepala ────────────────────────────────────────────────────────── */
        .top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .brand {
            font-size: 0.9375rem;
            font-weight: 600;
            letter-spacing: 0.01em;   /* teks kecil: tracking positif tipis */
            color: var(--ink-dim);
        }

        /* Umpan balik hadir saat pointer TURUN, bukan saat dilepas. */
        .admin-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0.875rem;
            border-radius: 999px;
            border: 1px solid var(--mat-edge);
            background: var(--mat);
            backdrop-filter: blur(var(--mat-blur)) saturate(180%);
            color: var(--ink);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: transform 100ms ease-out, background 150ms ease-out;
            will-change: transform;
        }
        .admin-link:active { transform: scale(0.96); background: rgba(255,255,255,0.12); }
        .admin-link:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

        /* ── Jam ───────────────────────────────────────────────────────────
           Teks display: tracking negatif dan leading rapat. Angka memakai
           tabular-nums supaya lebarnya tidak bergoyang setiap detik. */
        .clock {
            font-size: clamp(3.5rem, 13vw, 8rem);
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.035em;
            font-variant-numeric: tabular-nums;
            text-align: center;
            margin: 0;
        }
        .clock-date {
            text-align: center;
            color: var(--ink-dim);
            font-size: clamp(0.875rem, 2vw, 1.0625rem);
            letter-spacing: 0.01em;
            margin: 0.375rem 0 0;
        }

        /* ── Panggung kamera ──────────────────────────────────────────────── */
        .stage {
            position: relative;
            flex: 1;
            display: grid;
            place-items: center;
            min-height: 0;
        }

        .viewport {
            position: relative;
            width: min(100%, 34rem);
            aspect-ratio: 4 / 3;
            border-radius: 1.5rem;
            overflow: hidden;
            background: #000;
            /* Permukaan besar terbaca lebih tebal: bayangan lebih dalam. */
            box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, 0.55),
                        inset 0 0 0 1px var(--mat-edge);
        }
        #preview { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* Bingkai pengarah — memberi tahu ke mana QR harus diarahkan. */
        .reticle {
            position: absolute;
            inset: 18%;
            border-radius: 1rem;
            box-shadow: inset 0 0 0 2px rgba(255,255,255,0.5);
            pointer-events: none;
            transition: box-shadow var(--dur) var(--ease);
        }
        .viewport[data-state="ok"]  .reticle { box-shadow: inset 0 0 0 3px var(--ok); }
        .viewport[data-state="bad"] .reticle { box-shadow: inset 0 0 0 3px var(--bad); }

        /* Kilat hasil pemindaian. Dipicu pada frame yang sama dengan audio dan
           haptik — jeda antar indra merusak kesan sebab-akibat. */
        .flash {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
            transition: opacity 90ms ease-out;
        }
        .viewport[data-state="ok"]  .flash { background: var(--ok); opacity: 0.28; }
        .viewport[data-state="bad"] .flash { background: var(--bad); opacity: 0.28; }

        /* ── Kartu status ─────────────────────────────────────────────────
           Material "mendatang", bukan sekadar memudar: blur dan skala bergerak
           bersamaan. Masuk dan keluar lewat jalur yang sama. */
        .status {
            position: absolute;
            left: 50%;
            bottom: 1.25rem;
            translate: -50% 0;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.75rem 1.125rem;
            border-radius: 999px;
            border: 1px solid var(--mat-edge);
            border-top-color: rgba(255,255,255,0.24);  /* tepi atas terang */
            background: rgba(20, 20, 26, 0.72);
            backdrop-filter: blur(var(--mat-blur)) saturate(180%);
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.005em;
            white-space: nowrap;
            opacity: 0;
            transform: translateY(0.75rem) scale(0.96);
            transition: opacity var(--dur) var(--ease),
                        transform var(--dur) var(--ease),
                        backdrop-filter var(--dur) var(--ease);
            will-change: transform, opacity;
        }
        .status[data-show="1"] { opacity: 1; transform: translateY(0) scale(1); }
        .status .dot { width: 0.5rem; height: 0.5rem; border-radius: 999px; background: var(--ink-dim); flex: none; }
        .status[data-kind="ok"]   .dot { background: var(--ok); }
        .status[data-kind="bad"]  .dot { background: var(--bad); }
        .status[data-kind="warn"] .dot { background: var(--warn); }

        /* ── Petunjuk ─────────────────────────────────────────────────────── */
        .hints {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .hints li {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4375rem 0.8125rem;
            border-radius: 999px;
            border: 1px solid var(--mat-edge);
            background: var(--mat);
            backdrop-filter: blur(var(--mat-blur));
            color: var(--ink-dim);
            font-size: 0.8125rem;
            letter-spacing: 0.015em;   /* teks kecil: sedikit dilonggarkan */
        }
        .hints svg { width: 0.9375rem; height: 0.9375rem; flex: none; }

        /* ── Preferensi pengguna ──────────────────────────────────────────── */
        @media (prefers-reduced-motion: reduce) {
            /* Bukan menghapus umpan balik — menggantinya dengan yang tidak
               menggerakkan bidang pandang. */
            .status {
                transform: none !important;
                transition: opacity 200ms ease;
            }
            .admin-link { transition: background 150ms ease; }
            .admin-link:active { transform: none; }
        }
        @media (prefers-reduced-transparency: reduce) {
            .admin-link, .hints li { background: #1c1c22; backdrop-filter: none; }
            .status { background: #1c1c22; backdrop-filter: none; }
        }
        @media (prefers-contrast: more) {
            :root { --ink-dim: #d4d4d8; --mat-edge: rgba(255,255,255,0.5); }
            .admin-link, .hints li, .status { background: #000; backdrop-filter: none; }
        }
    </style>
</head>
<body>
    <header class="top">
        <span class="brand">Absensi</span>
        <a class="admin-link" href="/admin" rel="nofollow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" aria-hidden="true">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>
            </svg>
            Masuk sebagai admin
        </a>
    </header>

    <div>
        <p class="clock" id="time" aria-live="off">--:--:--</p>
        <p class="clock-date" id="date"></p>
    </div>

    <main class="stage">
        <div class="viewport" id="viewport" data-state="idle">
            <video id="preview" playsinline muted></video>
            <div class="reticle" aria-hidden="true"></div>
            <div class="flash" aria-hidden="true"></div>
            <p class="status" id="status" role="status" aria-live="polite" data-show="0">
                <span class="dot" aria-hidden="true"></span><span id="status-text"></span>
            </p>
        </div>
    </main>

    <ul class="hints">
        <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            Izinkan akses kamera
        </li>
        <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Izinkan akses lokasi
        </li>
        <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4zM19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
            Tunggu sampai berbunyi
        </li>
    </ul>

    <audio id="audioPlayer" preload="auto">
        <source src="{{ asset('/sound/login.mp3') }}" type="audio/mpeg">
    </audio>
    <audio id="audioPlayerFailed" preload="auto">
        <source src="{{ asset('/sound/failed.mp3') }}" type="audio/mpeg">
    </audio>

    {{-- rawgit.com berhenti beroperasi sejak 2019; CDN lama mengembalikan 404
         sehingga Instascan tidak pernah termuat dan seluruh blok skrip mati —
         termasuk jam dan pemutar audio. jsdelivr menyajikan berkas yang sama. --}}
    <script src="https://cdn.jsdelivr.net/gh/schmich/instascan-builds@master/instascan.min.js"></script>
    <script>
        (function () {
            'use strict';

            const RECORD_URL = @json(route('presence.record'));
            const CSRF = document.querySelector('meta[name="csrf-token"]').content;

            const viewport = document.getElementById('viewport');
            const statusEl = document.getElementById('status');
            const statusText = document.getElementById('status-text');
            const okSound = document.getElementById('audioPlayer');
            const badSound = document.getElementById('audioPlayerFailed');

            let resetTimer = null;

            /* Ketiga indra dipicu dalam satu frame. Jeda di antaranya merusak
               kesan bahwa pemindaianlah yang menyebabkan umpan balik ini. */
            function feedback(kind, message) {
                clearTimeout(resetTimer);

                requestAnimationFrame(() => {
                    viewport.dataset.state = kind === 'ok' ? 'ok' : (kind === 'bad' ? 'bad' : 'idle');
                    statusEl.dataset.kind = kind;
                    statusEl.dataset.show = '1';
                    statusText.textContent = message;

                    const sound = kind === 'ok' ? okSound : (kind === 'bad' ? badSound : null);
                    if (sound) { sound.currentTime = 0; sound.play().catch(() => {}); }

                    if (navigator.vibrate) {
                        navigator.vibrate(kind === 'ok' ? 18 : [12, 40, 12]);
                    }
                });

                // Kembali ke keadaan diam lewat jalur yang sama dengan saat masuk.
                resetTimer = setTimeout(() => {
                    viewport.dataset.state = 'idle';
                    statusEl.dataset.show = '0';
                }, kind === 'ok' ? 2400 : 3600);
            }

            function hold(message) {
                clearTimeout(resetTimer);
                statusEl.dataset.kind = 'warn';
                statusEl.dataset.show = '1';
                statusText.textContent = message;
            }

            // ── Jam ──────────────────────────────────────────────────────────
            const timeEl = document.getElementById('time');
            const dateEl = document.getElementById('date');
            const pad = (n) => String(n).padStart(2, '0');

            function tick() {
                const now = new Date();
                timeEl.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
                dateEl.textContent = now.toLocaleDateString('id-ID', {
                    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
                });
                setTimeout(tick, 1000 - (Date.now() % 1000));   // tetap sejalan dengan detik jam dinding
            }
            tick();

            // ── Lokasi ───────────────────────────────────────────────────────
            function currentPosition() {
                return new Promise((resolve, reject) => {
                    if (!navigator.geolocation) return reject(new Error('unsupported'));
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true, timeout: 8000, maximumAge: 30000,
                    });
                });
            }

            // ── Pemindaian ───────────────────────────────────────────────────
            let busy = false;

            async function submit(qr) {
                if (busy) return;                 // satu pemindaian pada satu waktu
                busy = true;

                try {
                    const pos = await currentPosition().catch(() => null);
                    if (!pos) {
                        feedback('bad', 'Lokasi diperlukan — izinkan akses lokasi');
                        return;
                    }

                    const res = await fetch(RECORD_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                        },
                        body: JSON.stringify({
                            qr,
                            lat: pos.coords.latitude,
                            lng: pos.coords.longitude,
                        }),
                    });

                    if (!res.ok) {
                        feedback('bad', 'QR tidak dikenali');
                        return;
                    }

                    const data = await res.json().catch(() => null);
                    const masuk = data && data.out ? 'Absen keluar tercatat' : 'Absen masuk tercatat';
                    feedback('ok', masuk);
                } catch (e) {
                    feedback('bad', 'Gagal menyimpan — periksa koneksi');
                } finally {
                    setTimeout(() => { busy = false; }, 1200);   // hindari pemindaian ganda
                }
            }

            if (typeof Instascan === 'undefined') {
                hold('Pustaka pemindai gagal dimuat — periksa koneksi internet');
                return;
            }

            const scanner = new Instascan.Scanner({
                video: document.getElementById('preview'),
                mirror: false,
                scanPeriod: 3,
            });

            scanner.addListener('scan', submit);

            Instascan.Camera.getCameras().then((cameras) => {
                if (!cameras.length) {
                    hold('Kamera tidak ditemukan');
                    return;
                }
                // Kamera belakang bila ada — perangkat di pintu masuk biasanya
                // diarahkan ke luar.
                const back = cameras.find((c) => /back|rear|environment/i.test(c.name || '')) || cameras[0];
                return scanner.start(back);
            }).catch(() => {
                hold('Akses kamera ditolak — izinkan lalu muat ulang halaman');
            });
        })();
    </script>
</body>
</html>
