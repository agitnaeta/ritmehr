@php
    $badge = match($loan->status) {
        'approved'  => 'success',
        'pending'   => 'warning',
        'rejected'  => 'danger',
        default     => 'secondary',
    };
@endphp
<span class="badge bg-{{ $badge }}">{{ $loan->statusLabel() }}</span>
@if($loan->status === 'rejected' && $loan->rejection_reason)
    <div class="small text-muted">{{ $loan->rejection_reason }}</div>
@endif
