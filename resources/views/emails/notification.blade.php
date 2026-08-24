@component('mail::message')
# {{ $subjectLine }}

{{ $bodyText }}

@if($actionUrl)
@component('mail::button', ['url' => $actionUrl])
Buka Detail
@endcomponent
@endif

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
