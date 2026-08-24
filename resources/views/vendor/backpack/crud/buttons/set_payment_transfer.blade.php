{{-- Form POST, bukan tautan: operasi ini mengubah data. --}}
@if ($crud->hasAccess('setPayment'))
  <form method="POST" action="{{ url($crud->route.'/'.$entry->getKey().'/set-payment') }}" class="d-inline">
      @csrf
      <input type="hidden" name="method" value="transfer">
      <button type="submit" class="btn btn-sm btn-link text-capitalize"
              onclick="return confirm('Bayar gaji ini via transfer?')">
          <i class="la la-send"></i>Bayar Transfer</button>
  </form>
@endif
