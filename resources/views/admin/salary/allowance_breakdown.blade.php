{{-- M20b — Allowance breakdown shown on the Salary detail page.
     $entry = Salary model. Lists this employee's active allowances + total. --}}
@php
    $lines = \App\Models\EmployeeSalaryAllowance::with('type')
        ->where('user_id', $entry->user_id)
        ->get()
        ->filter(fn ($a) => $a->type && $a->type->is_active);
    $cur = app(\App\Services\CurrencyService::class)->symbol();
@endphp

<td>
    @if($lines->isEmpty())
        <span class="text-muted">— Tidak ada tunjangan —</span>
    @else
        <table class="table table-sm mb-0" style="max-width:420px;">
            <thead>
                <tr><th>Jenis Tunjangan</th><th class="text-end">Nominal</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gaji Pokok</td>
                    <td class="text-end">{{ $cur }} {{ number_format($entry->basic_salary, 0, ',', '.') }}</td>
                </tr>
                @foreach($lines as $line)
                    <tr>
                        <td>{{ $line->type->label }}</td>
                        <td class="text-end">{{ $cur }} {{ number_format($line->amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="fw-bold border-top">
                    <td>Total Gaji</td>
                    <td class="text-end">{{ $cur }} {{ number_format($entry->amount, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</td>
