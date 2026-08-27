<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sertifikat — {{ $user->name }} — {{ $training->title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Georgia', 'Times New Roman', serif; background: #eceff3; color: #1a2027; }
        .toolbar { padding: 12px 18px; background: #fff; border-bottom: 1px solid #ddd; }
        .toolbar .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; background: #0d6efd; color: #fff; text-decoration: none; font-family: sans-serif; font-size: 14px; }
        .toolbar .btn.secondary { background: #6c757d; }
        .cert-wrap { display: flex; justify-content: center; padding: 24px; }
        .cert {
            width: 1000px; max-width: 100%; aspect-ratio: 1.414/1; background: #fff;
            border: 14px solid #0d3b66; padding: 60px 70px; text-align: center; position: relative;
            box-shadow: 0 4px 24px rgba(0,0,0,.12);
        }
        .cert::before {
            content: ''; position: absolute; inset: 18px; border: 2px solid #c9a227; pointer-events: none;
        }
        .cert h1 { font-size: 42px; letter-spacing: 6px; color: #0d3b66; margin: 10px 0 4px; text-transform: uppercase; }
        .cert .sub { font-size: 15px; letter-spacing: 3px; color: #c9a227; text-transform: uppercase; margin-bottom: 30px; }
        .cert .given { font-size: 15px; color: #555; }
        .cert .name { font-size: 36px; color: #1a2027; margin: 14px 0; border-bottom: 2px solid #eee; display: inline-block; padding: 0 30px 8px; }
        .cert .body { font-size: 16px; color: #444; line-height: 1.6; margin: 14px auto; max-width: 640px; }
        .cert .title-line { font-weight: bold; color: #0d3b66; font-size: 20px; }
        .cert .score { font-size: 15px; color: #198754; font-weight: bold; margin-top: 6px; }
        .cert .footer { display: flex; justify-content: space-between; margin-top: 46px; font-size: 13px; color: #555; font-family: sans-serif; }
        .cert .footer .block { text-align: center; flex: 1; }
        .cert .footer .sig { border-top: 1px solid #999; margin: 40px 24px 6px; padding-top: 6px; }
        .cert .certno { position: absolute; bottom: 26px; left: 0; right: 0; font-size: 11px; color: #999; font-family: sans-serif; letter-spacing: 1px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .cert-wrap { padding: 0; }
            .cert { box-shadow: none; width: 100%; border-width: 12px; }
            @page { size: A4 landscape; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="#" class="btn" onclick="window.print();return false;">🖨️ Cetak / Simpan PDF</a>
        <a href="{{ route('portal.training.show', $training->id) }}" class="btn secondary">Kembali</a>
    </div>

    <div class="cert-wrap">
        <div class="cert">
            <div class="sub">{{ $company->name ?? 'Perusahaan' }}</div>
            <h1>Sertifikat</h1>
            <div class="sub">Kelulusan Pelatihan</div>

            <div class="given">Diberikan kepada</div>
            <div class="name">{{ $user->name }}</div>

            <div class="body">
                atas keberhasilannya menyelesaikan dan <b>LULUS</b> pelatihan
                <div class="title-line">{{ $training->title }}</div>
                <div class="score">Nilai: {{ $enrollment->score }} / 100 (KKM {{ $training->passing_score }})</div>
            </div>

            <div class="footer">
                <div class="block">
                    <div class="sig">Tanggal</div>
                    {{ optional($enrollment->passed_at ?? $enrollment->certificate_issued_at)->format('d F Y') }}
                </div>
                <div class="block">
                    <div class="sig">Pelatih / HR</div>
                    {{ $training->trainer?->name ?? ($company->name ?? '-') }}
                </div>
            </div>

            <div class="certno">No. Sertifikat: {{ $enrollment->certificate_no }}</div>
        </div>
    </div>
</body>
</html>
