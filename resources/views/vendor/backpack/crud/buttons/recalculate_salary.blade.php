@if ($crud->hasAccess('print_salary'))
  <a href="{{ url($crud->route.'/'.$entry->getKey().'/recalculate-salary') }}" class="btn btn-sm btn-link text-capitalize">
      <i class="la la-calculator"></i> Hitung Ulang</a>
@endif
