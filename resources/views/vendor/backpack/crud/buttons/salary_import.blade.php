@if ($crud->hasAccess('create'))
  <a href="{{ route('salary.import.form') }}" class="btn btn-outline-primary text-capitalize">
      <i class="la la-upload"></i> Import Excel
  </a>
@endif
