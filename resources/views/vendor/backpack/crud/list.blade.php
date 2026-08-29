@extends(backpack_view('blank'))

@php
  $defaultBreadcrumbs = [
    trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
    $crud->entity_name_plural => url($crud->route),
    trans('backpack::crud.list') => false,
  ];

  // if breadcrumbs aren't defined in the CrudController, use the default breadcrumbs
  $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;

  // UM-03: render breadcrumb DI DALAM header (kiri) — matikan breadcrumb bawaan theme
  $umBreadcrumbs = $breadcrumbs;
  $breadcrumbs = null;

  // Pisahkan tombol aksi (Tambah user + dropdown ⋯) dari form filter (simple_filters)
  $umTopButtons   = $crud->buttons()->where('stack', 'top');
  $umActionButtons = $umTopButtons->filter(fn ($b) => $b->name !== 'simple_filters');
  $umFilterButtons = $umTopButtons->filter(fn ($b) => $b->name === 'simple_filters');
@endphp

@section('header')
  <div class="um-page-header container-fluid animated fadeIn d-print-none" bp-section="page-header">

    {{-- Baris 1: breadcrumb (kiri) + tombol aksi (kanan) --}}
    <div class="um-header-top d-flex align-items-center flex-wrap mb-3">
      <nav aria-label="breadcrumb" class="d-none d-lg-block">
        <ol class="breadcrumb bg-transparent p-0 m-0">
          @foreach ($umBreadcrumbs as $label => $link)
            @if ($link)
              <li class="breadcrumb-item text-capitalize"><a href="{{ $link }}">{{ $label }}</a></li>
            @else
              <li class="breadcrumb-item text-capitalize active" aria-current="page">{{ $label }}</li>
            @endif
          @endforeach
        </ol>
      </nav>

      @if ($umActionButtons->count())
        <div class="um-header-actions ms-auto d-flex align-items-center gap-2" bp-section="page-header-actions">
          @foreach ($umActionButtons as $button)
            {!! $button->getHtml($entry ?? null) !!}
          @endforeach
        </div>
      @endif
    </div>

    {{-- Baris 2: heading + subheading (kiri) + Pencarian & filter (kanan) --}}
    <div class="um-header-bottom d-flex align-items-end flex-wrap gap-3 mb-2">
      <div class="um-header-title">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">{!! $crud->getHeading() ?? $crud->entity_name_plural !!}</h1>
        <p class="mb-0 text-muted" id="datatable_info_stack" bp-section="page-subheading">{!! $crud->getSubheading() ?? '' !!}</p>
      </div>

      <div class="um-header-tools ms-auto d-flex align-items-end flex-wrap gap-2">
        {{-- Pencarian (search) — DataTables akan mengganti input ini via #datatable_search_stack --}}
        @if($crud->getOperationSetting('searchableTable'))
          <div class="um-search">
            <label class="form-label mb-0 small text-muted">Pencarian</label>
            <div id="datatable_search_stack" class="d-print-none">
              <div class="input-icon">
                <span class="input-icon-addon">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path><path d="M21 21l-6 -6"></path></svg>
                </span>
                <input type="search" class="form-control" placeholder="{{ trans('backpack::crud.search') }}..."/>
              </div>
            </div>
          </div>
        @endif

        {{-- Filter (Departemen, Cabang, Status, Filter, Reset) --}}
        @foreach ($umFilterButtons as $button)
          {!! $button->getHtml($entry ?? null) !!}
        @endforeach
      </div>
    </div>

  </div>
@endsection

@section('content')
  {{-- Default box --}}
  <div class="row" bp-section="crud-operation-list">

    {{-- THE ACTUAL CONTENT --}}
    <div class="{{ $crud->getListContentClass() }}">

        <div class="{{ backpack_theme_config('classes.tableWrapper') }}">
            <table
              id="crudTable"
              class="{{ backpack_theme_config('classes.table') ?? 'table table-striped table-hover nowrap rounded card-table table-vcenter card d-table shadow-xs border-xs' }}"
              data-responsive-table="{{ (int) $crud->getOperationSetting('responsiveTable') }}"
              data-has-details-row="{{ (int) $crud->getOperationSetting('detailsRow') }}"
              data-has-bulk-actions="{{ (int) $crud->getOperationSetting('bulkActions') }}"
              data-has-line-buttons-as-dropdown="{{ (int) $crud->getOperationSetting('lineButtonsAsDropdown') }}"
              data-line-buttons-as-dropdown-minimum="{{ (int) $crud->getOperationSetting('lineButtonsAsDropdownMinimum') }}"
              data-line-buttons-as-dropdown-show-before-dropdown="{{ (int) $crud->getOperationSetting('lineButtonsAsDropdownShowBefore') }}"
              cellspacing="0">
            <thead>
              <tr>
                {{-- Table columns --}}
                @foreach ($crud->columns() as $column)
                  @php
                  $exportOnlyColumn = $column['exportOnlyColumn'] ?? false;
                  $visibleInTable = $column['visibleInTable'] ?? ($exportOnlyColumn ? false : true);
                  $visibleInModal = $column['visibleInModal'] ?? ($exportOnlyColumn ? false : true);
                  $visibleInExport = $column['visibleInExport'] ?? true;
                  $forceExport = $column['forceExport'] ?? (isset($column['exportOnlyColumn']) ? true : false);
                  @endphp
                  <th
                    data-orderable="{{ var_export($column['orderable'], true) }}"
                    data-priority="{{ $column['priority'] }}"
                    data-column-name="{{ $column['name'] }}"
                    data-visible="{{ $exportOnlyColumn ? 'false' : var_export($visibleInTable) }}"
                    data-visible-in-table="{{ var_export($visibleInTable) }}"
                    data-can-be-visible-in-table="{{ $exportOnlyColumn ? 'false' : 'true' }}"
                    data-visible-in-modal="{{ var_export($visibleInModal) }}"
                    data-visible-in-export="{{ $exportOnlyColumn ? 'true' : ($visibleInExport ? 'true' : 'false') }}"
                    data-force-export="{{ var_export($forceExport) }}"
                  >
                    {{-- Bulk checkbox --}}
                    @if($loop->first && $crud->getOperationSetting('bulkActions'))
                      	{!! View::make('crud::columns.inc.bulk_actions_checkbox')->render() !!}
                    @endif
                    {!! $column['label'] !!}
                  </th>
                @endforeach

                @if ( $crud->buttons()->where('stack', 'line')->count() )
                  <th data-orderable="false"
                      data-priority="{{ $crud->getActionsColumnPriority() }}"
                      data-visible-in-export="false"
                      data-action-column="true"
                      >{{ trans('backpack::crud.actions') }}</th>
                @endif
              </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
              <tr>
                {{-- Table columns --}}
                @foreach ($crud->columns() as $column)
                  <th>
                    {{-- Bulk checkbox --}}
                    @if($loop->first && $crud->getOperationSetting('bulkActions'))
                      	{!! View::make('crud::columns.inc.bulk_actions_checkbox')->render() !!}
                    @endif
                    {!! $column['label'] !!}
                  </th>
                @endforeach

                @if ( $crud->buttons()->where('stack', 'line')->count() )
                  <th>{{ trans('backpack::crud.actions') }}</th>
                @endif
              </tr>
            </tfoot>
          </table>
        </div>

        @if ( $crud->buttons()->where('stack', 'bottom')->count() )
            <div id="bottom_buttons" class="d-print-none text-sm-left">
                @include('crud::inc.button_stack', ['stack' => 'bottom'])
                <div id="datatable_button_stack" class="float-right float-end text-right hidden-xs"></div>
            </div>
        @endif

    </div>

  </div>

@endsection

@section('after_styles')
  {{-- DATA TABLES --}}
  @basset('https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css')
  @basset('https://cdn.datatables.net/fixedheader/3.3.1/css/fixedHeader.dataTables.min.css')
  @basset('https://cdn.datatables.net/responsive/2.4.0/css/responsive.dataTables.min.css')

  {{-- CRUD LIST CONTENT - crud_list_styles stack --}}
  @stack('crud_list_styles')
@endsection

@section('after_scripts')
  @include('crud::inc.datatables_logic')

  {{-- CRUD LIST CONTENT - crud_list_scripts stack --}}
  @stack('crud_list_scripts')

  {{-- UM-03: buang class ms-1 pada tombol "Set ulang" (reset button dirender oleh
       datatables_logic vendor). Di inline-grid ia jadi baris kedua, indent kiri tak perlu. --}}
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const strip = () => {
        const el = document.getElementById('crudTable_reset_button');
        if (el) el.classList.remove('ms-1');
      };
      strip();
      // reset button dibuat ulang tiap DataTables redraw
      if (window.jQuery) jQuery(document).on('draw.dt', strip);
    });
  </script>
@endsection
