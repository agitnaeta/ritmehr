{{-- UM-11 — Tombol bulk "Cetak Terpilih": kumpulkan id checkbox → buka print-selected. --}}
@if ($crud->hasAccess('print_id_cards') || backpack_user()?->can('user.view'))
  <button type="button" class="btn btn-primary" id="btnPrintSelected" data-url="{{ route('user.print.selected') }}" style="display:none">
    <i class="la la-print"></i> Cetak ID Terpilih (<span id="printSelectedCount">0</span>)
  </button>
@endif

@push('after_scripts')
<script>
(function(){
  const btn = document.getElementById('btnPrintSelected');
  if (!btn) return;
  const countEl = document.getElementById('printSelectedCount');

  // Backpack bulk: baris terpilih punya checkbox .crud_bulk_actions_line_checkbox
  function selectedIds(){
    return Array.from(document.querySelectorAll('.crud_bulk_actions_line_checkbox:checked'))
      .map(cb => cb.closest('tr')?.getAttribute('data-entry-id') || cb.value)
      .filter(Boolean);
  }

  function refresh(){
    const ids = selectedIds();
    countEl.textContent = ids.length;
    btn.style.display = ids.length > 0 ? 'inline-flex' : 'none';
  }

  // Pantau perubahan checkbox (delegasi; DataTables re-render baris).
  document.addEventListener('change', function(e){
    if (e.target.matches('.crud_bulk_actions_line_checkbox, .crud_bulk_actions_main_checkbox')) {
      setTimeout(refresh, 50);
    }
  });
  document.addEventListener('click', function(e){
    if (e.target.closest('.crud_bulk_actions_main_checkbox')) setTimeout(refresh, 80);
  });

  btn.addEventListener('click', function(){
    const ids = selectedIds();
    if (!ids.length) return;
    // Buka PDF di tab baru (jalur sinkron) atau redirect ke status (batch besar).
    const url = btn.dataset.url + '?ids=' + encodeURIComponent(ids.join(','));
    window.open(url, '_blank');
  });

  // Inisial + interval ringan untuk sinkron dengan DataTables redraw.
  setInterval(refresh, 1000);
})();
</script>
@endpush
