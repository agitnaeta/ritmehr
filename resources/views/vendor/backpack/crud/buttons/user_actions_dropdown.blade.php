{{-- UM-03: Dropdown "..." untuk Export / Import / Cetak Semua ID --}}
<div class="btn-group" role="group">
  <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="userActionsDropdownBtn">
    <i class="la la-ellipsis-h"></i>
  </button>
  <ul class="dropdown-menu dropdown-menu-end">
    @if ($crud->hasAccess('user_export'))
      <li>
        <a class="dropdown-item" href="{{ url($crud->route.'/export') }}">
          <i class="la la-download me-1"></i> User Export
        </a>
      </li>
    @endif
    @if ($crud->hasAccess('create'))
      <li>
        <a class="dropdown-item" href="{{ route('user.import.form') }}">
          <i class="la la-upload me-1"></i> Import Excel
        </a>
      </li>
    @endif
    @if ($crud->hasAccess('print_id_cards'))
      <li>
        <a class="dropdown-item" href="{{ route('user.print.all') }}">
          <i class="la la-print me-1"></i> Cetak Semua ID
        </a>
      </li>
    @endif
  </ul>
</div>

{{-- Ensure Bootstrap dropdown initialises even when blade is injected late --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('userActionsDropdownBtn');
    if (btn && typeof bootstrap !== 'undefined') {
        new bootstrap.Dropdown(btn);
    }
});
</script>
