@extends('portal.layout')
@section('title', 'Notifikasi')
@section('heading', 'Notifikasi')

@section('content')
<div class="card">
    <div class="card-header text-end">
        <form method="POST" action="{{ route('portal.notifications.mark_all_read') }}">
            @csrf
            <button class="btn btn-sm btn-outline-primary">Tandai Semua Dibaca</button>
        </form>
    </div>
    <div class="card-body p-0">
        @forelse($notifications as $n)
            <div class="p-3 border-bottom {{ $n->isRead() ? '' : 'bg-light' }}">
                <div class="d-flex">
                    <i class="la {{ $n->icon() }} la-2x text-muted me-3"></i>
                    <div class="flex-grow-1">
                        <strong>{{ $n->title }}</strong>
                        @unless($n->isRead())<span class="badge bg-primary">Baru</span>@endunless
                        <div class="text-muted">{{ $n->body }}</div>
                        <small class="text-muted">{{ $n->created_at?->diffForHumans() }}</small>
                    </div>
                    @unless($n->isRead())
                        <form method="POST" action="{{ route('portal.notifications.read', $n->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-link">Tandai dibaca</button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="p-4 text-center text-muted">Belum ada notifikasi.</div>
        @endforelse
    </div>
</div>

<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
