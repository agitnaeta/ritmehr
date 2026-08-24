@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Notifikasi <small>{{ $unreadCount }} belum dibaca</small></h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-9">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <a href="{{ backpack_url('notification') }}"
                       class="btn btn-sm {{ $unreadOnly ? 'btn-outline-secondary' : 'btn-secondary' }}">Semua</a>
                    <a href="{{ backpack_url('notification?unread=1') }}"
                       class="btn btn-sm {{ $unreadOnly ? 'btn-secondary' : 'btn-outline-secondary' }}">Belum Dibaca</a>
                </div>
                @if($unreadCount > 0)
                    <form method="POST" action="{{ backpack_url('notification/mark-all-read') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary">
                            <i class="la la-check-double"></i> Tandai Semua Dibaca
                        </button>
                    </form>
                @endif
            </div>
            <div class="card-body p-0">
                @forelse($notifications as $notification)
                    <a href="{{ backpack_url('notification/' . $notification->id . '/read') }}"
                       class="d-block p-3 border-bottom text-body text-decoration-none
                              {{ $notification->isRead() ? '' : 'bg-light' }}">
                        <div class="d-flex">
                            <div class="me-3"><i class="la {{ $notification->icon() }} la-2x text-muted"></i></div>
                            <div class="flex-grow-1">
                                <div>
                                    <strong>{{ $notification->title }}</strong>
                                    @unless($notification->isRead())
                                        <span class="badge bg-primary">Baru</span>
                                    @endunless
                                </div>
                                <div class="text-muted">{{ $notification->body }}</div>
                                <small class="text-muted">{{ $notification->created_at?->diffForHumans() }}</small>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="p-4 text-center text-muted">Belum ada notifikasi.</div>
                @endforelse
            </div>
        </div>

        {{ $notifications->links() }}
    </div>
</div>
@endsection
