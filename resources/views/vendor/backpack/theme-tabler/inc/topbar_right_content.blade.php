{{-- M13 — Language switcher. Shows the active locale; switching persists to
     the user + session via /locale/{locale}. --}}
<li class="nav-item dropdown">
    <a class="nav-link" href="#" data-bs-toggle="dropdown" aria-label="{{ __('common.language') }}" title="{{ __('common.language') }}">
        <i class="la la-language la-lg"></i>
        <span class="text-uppercase small fw-bold">{{ app()->getLocale() }}</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end">
        <a class="dropdown-item {{ app()->getLocale() === 'id' ? 'active' : '' }}"
           href="{{ url('/locale/id') }}">🇮🇩 Indonesia</a>
        <a class="dropdown-item {{ app()->getLocale() === 'en' ? 'active' : '' }}"
           href="{{ url('/locale/en') }}">🇬🇧 English</a>
    </div>
</li>

{{-- Notification bell (M3). Rendered on every admin page, so it must stay
     cheap: one count query plus at most five rows for the dropdown. --}}

@php
    $notifUser = backpack_auth()->check() ? backpack_user() : null;
    $notifUnread = 0;
    $notifRecent = collect();

    if ($notifUser) {
        $notifService = app(\App\Services\NotificationService::class);
        $notifUnread = $notifService->unreadCount($notifUser);
        $notifRecent = $notifService->getUnread($notifUser, 5);
    }
@endphp

@if($notifUser)
<li class="nav-item dropdown">
    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown"
       aria-label="Notifikasi" title="Notifikasi">
        <i class="la la-bell la-lg"></i>
        @if($notifUnread > 0)
            <span class="badge bg-danger position-absolute"
                  style="top:.25rem; right:.1rem; font-size:.65rem;">
                {{ $notifUnread > 99 ? '99+' : $notifUnread }}
            </span>
        @endif
    </a>

    <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 22rem;">
        <div class="dropdown-header d-flex justify-content-between align-items-center">
            <span>Notifikasi</span>
            <span class="badge bg-secondary">{{ $notifUnread }} baru</span>
        </div>

        @forelse($notifRecent as $n)
            <a class="dropdown-item d-flex py-2 border-top text-wrap"
               href="{{ backpack_url('notification/' . $n->id . '/read') }}">
                <i class="la {{ $n->icon() }} la-lg me-2 mt-1 text-muted"></i>
                <span>
                    <strong class="d-block">{{ $n->title }}</strong>
                    <small class="text-muted">{{ \Illuminate\Support\Str::limit($n->body, 90) }}</small>
                    <small class="d-block text-muted">{{ $n->created_at?->diffForHumans() }}</small>
                </span>
            </a>
        @empty
            <div class="dropdown-item text-muted border-top py-3">Tidak ada notifikasi baru.</div>
        @endforelse

        <a class="dropdown-item text-center border-top py-2"
           href="{{ backpack_url('notification') }}">Lihat semua</a>
    </div>
</li>
@endif
