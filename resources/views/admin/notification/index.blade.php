@extends(backpack_view('blank'))

@section('header')
    <x-admin.page-header
        :breadcrumb="['Admin' => backpack_url('dashboard'), 'Notifikasi' => false]"
        heading="Notifikasi"
        :subheading="$unreadCount . ' belum dibaca'">

        @if($unreadCount > 0)
            <x-slot:actions>
                <form method="POST" action="{{ backpack_url('notification/mark-all-read') }}">
                    @csrf
                    <button class="btn btn-outline-primary">
                        <i class="la la-check-double"></i> Tandai Semua Dibaca
                    </button>
                </form>
            </x-slot:actions>
        @endif

        <x-slot:tools>
            <div class="btn-group">
                <a href="{{ backpack_url('notification') }}"
                   class="btn {{ $unreadOnly ? 'btn-outline-secondary' : 'btn-secondary' }}">Semua</a>
                <a href="{{ backpack_url('notification?unread=1') }}"
                   class="btn {{ $unreadOnly ? 'btn-secondary' : 'btn-outline-secondary' }}">Belum Dibaca</a>
            </div>
        </x-slot:tools>
    </x-admin.page-header>
@endsection

@section('content')
<div class="row">
    <div class="col-md-9">
        <div class="card">
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
