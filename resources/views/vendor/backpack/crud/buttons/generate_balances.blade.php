@if ($crud->hasAccess('generate_balances'))
<div class="btn-group">
    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
        <i class="la la-magic"></i> Generate Saldo
    </button>
    <div class="dropdown-menu p-3" style="min-width: 20rem;">
        <form method="POST" action="{{ url($crud->route.'/generate') }}" class="mb-3">
            @csrf
            <label class="form-label">Buat saldo tahunan</label>
            <div class="input-group">
                <input type="number" name="year" class="form-control"
                       value="{{ now()->year }}" min="2000" max="2100">
                <button class="btn btn-primary" type="submit">Generate</button>
            </div>
            <small class="text-muted">Melewati karyawan yang saldonya sudah ada.</small>
        </form>

        <form method="POST" action="{{ url($crud->route.'/carry-over') }}">
            @csrf
            <label class="form-label">Carry-over sisa cuti</label>
            <div class="row g-1">
                <div class="col">
                    <input type="number" name="from_year" class="form-control form-control-sm"
                           value="{{ now()->year - 1 }}" placeholder="Dari">
                </div>
                <div class="col">
                    <input type="number" name="to_year" class="form-control form-control-sm"
                           value="{{ now()->year }}" placeholder="Ke">
                </div>
                <div class="col">
                    <input type="number" name="max_carry" class="form-control form-control-sm"
                           placeholder="Maks">
                </div>
            </div>
            <button class="btn btn-sm btn-outline-primary mt-2 w-100" type="submit">Terapkan</button>
            <small class="text-muted">Kosongkan "Maks" untuk membawa seluruh sisa.</small>
        </form>
    </div>
</div>
@endif
