{{-- Shared payslip styles (M04 portal look). Used by both the employee
     self-service print (/my/salary) and the admin print so both are identical. --}}
<style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; font-size: 13px; }
    .sheet { max-width: 720px; margin: 0 auto 32px; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 16px; }
    .company { font-size: 18px; font-weight: 700; }
    .company-logo { max-height: 48px; width: auto; margin-bottom: 6px; display: block; }
    .doc-title { text-align: right; }
    .doc-title h1 { font-size: 16px; margin: 0; }
    .doc-title .period { color: #6b7280; }
    .meta { display: flex; flex-wrap: wrap; gap: 4px 32px; margin-bottom: 16px; }
    .meta div { min-width: 200px; }
    .meta .label { color: #6b7280; font-size: 11px; text-transform: uppercase; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th, td { padding: 6px 8px; text-align: left; }
    .section-title { font-weight: 700; margin: 14px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #e5e7eb; }
    .earn { color: #047857; }
    .ded { color: #b91c1c; }
    td.num { text-align: right; font-variant-numeric: tabular-nums; }
    tr.sub td { border-top: 1px solid #e5e7eb; font-weight: 700; }
    tr.detail td { color: #6b7280; padding-left: 20px; }
    .net { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding: 12px 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; }
    .net .amt { font-size: 20px; font-weight: 800; color: #047857; }
    .foot { margin-top: 28px; color: #9ca3af; font-size: 11px; text-align: center; }
    .toolbar { max-width: 720px; margin: 0 auto 16px; display: flex; gap: 8px; }
    .btn { border: 1px solid #111827; background: #111827; color: #fff; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; }
    .btn.secondary { background: #fff; color: #111827; }
    .page-break { page-break-before: always; }
    @media print {
        .toolbar { display: none !important; }
        body { padding: 0; }
    }
</style>
