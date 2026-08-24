{{-- Filter bar for the free Backpack edition (addFilter() is PRO-only).
     Populated by App\Traits\HasSimpleFilters. --}}
@php $simpleFilters = $crud->get('simple_filters') ?? []; @endphp

@if(count($simpleFilters))
<form method="GET" class="d-inline-flex flex-wrap align-items-end gap-2 me-2">
    @foreach($simpleFilters as $filter)
        <div>
            <label class="form-label mb-0 small text-muted">{{ $filter['label'] }}</label>
            @if(($filter['type'] ?? 'select') === 'select')
                <select name="{{ $filter['name'] }}" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach(($filter['options'] ?? []) as $key => $label)
                        <option value="{{ $key }}" @selected((string) $filter['value'] === (string) $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            @else
                <input type="{{ $filter['type'] ?? 'text' }}"
                       name="{{ $filter['name'] }}"
                       value="{{ $filter['value'] }}"
                       placeholder="{{ $filter['placeholder'] ?? '' }}"
                       class="form-control form-control-sm">
            @endif
        </div>
    @endforeach

    <div>
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="la la-filter"></i> Filter
        </button>
        <a href="{{ url($crud->route) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
</form>
@endif
