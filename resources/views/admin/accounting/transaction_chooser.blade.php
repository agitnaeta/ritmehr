@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Catat Transaksi <small class="text-muted">pilih jenis pencatatan</small></h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-10">
        <div class="row">
            <div class="col-md-6 mb-3">
                <a href="{{ backpack_url('accounting/journal/create?kind=expense') }}" class="text-decoration-none">
                    <div class="card h-100 border-danger" id="card-expense">
                        <div class="card-body text-center py-4">
                            <i class="la la-arrow-circle-up text-danger" style="font-size:3rem"></i>
                            <h4 class="mt-2 mb-1">Pengeluaran</h4>
                            <p class="text-muted mb-0">Uang keluar — bayar listrik, beli ATK, sewa, dll.</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 mb-3">
                <a href="{{ backpack_url('accounting/journal/create?kind=income') }}" class="text-decoration-none">
                    <div class="card h-100 border-success" id="card-income">
                        <div class="card-body text-center py-4">
                            <i class="la la-arrow-circle-down text-success" style="font-size:3rem"></i>
                            <h4 class="mt-2 mb-1">Pemasukan</h4>
                            <p class="text-muted mb-0">Uang masuk — pendapatan, penerimaan lain.</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 mb-3">
                <a href="{{ backpack_url('accounting/journal/create?kind=transfer') }}" class="text-decoration-none">
                    <div class="card h-100 border-info" id="card-transfer">
                        <div class="card-body text-center py-4">
                            <i class="la la-exchange-alt text-info" style="font-size:3rem"></i>
                            <h4 class="mt-2 mb-1">Transfer</h4>
                            <p class="text-muted mb-0">Pindah dana antar kas/bank — mis. tarik tunai.</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 mb-3">
                <a href="{{ backpack_url('accounting/journal/create?kind=general') }}" class="text-decoration-none">
                    <div class="card h-100 border-secondary" id="card-general">
                        <div class="card-body text-center py-4">
                            <i class="la la-cogs text-secondary" style="font-size:3rem"></i>
                            <h4 class="mt-2 mb-1">Lanjutan (Jurnal Umum)</h4>
                            <p class="text-muted mb-0">Untuk akuntan — input debit/kredit manual.</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
