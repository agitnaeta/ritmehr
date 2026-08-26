{{--
    Formulir login. Menimpa partial bawaan tema Tabler untuk memakai kolom
    isian bergaya terisi dan tombol navy dari referensi desain.

    Isi fungsionalnya TIDAK berubah — nama kolom, CSRF, remember me, dan
    tautan lupa password sama seperti aslinya, termasuk syarat kemunculannya.
    Yang berubah hanya penyajiannya.
--}}
<form method="POST" action="{{ route('backpack.auth.login') }}" autocomplete="off" novalidate>
    @csrf

    <div class="mb-3">
        <label class="form-label" for="{{ $username }}">
            {{ trans('backpack::base.'.strtolower(config('backpack.base.authentication_column_name'))) }}
        </label>
        <div class="field {{ $errors->has($username) ? 'is-invalid' : '' }}">
            <svg class="field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            <input autofocus tabindex="1" type="text" name="{{ $username }}"
                   value="{{ old($username) }}" id="{{ $username }}"
                   autocomplete="username" class="form-control">
        </div>
        @if ($errors->has($username))
            <span class="field-error">{{ $errors->first($username) }}</span>
        @endif
    </div>

    <div class="mb-3">
        <label class="form-label" for="password">{{ trans('backpack::base.password') }}</label>
        <div class="field {{ $errors->has('password') ? 'is-invalid' : '' }}">
            <svg class="field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input tabindex="2" type="password" name="password" id="password"
                   autocomplete="current-password" class="form-control">
            @if(backpack_theme_config('options.showPasswordVisibilityToggler'))
                <button type="button" class="field__toggle" data-password-toggle
                        aria-controls="password" aria-pressed="false">
                    {{ trans('backpack.theme-tabler::theme-tabler.password-show') }}
                </button>
            @endif
        </div>
        @if ($errors->has('password'))
            <span class="field-error">{{ $errors->first('password') }}</span>
        @endif
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <label class="form-check mb-0">
            <input name="remember" tabindex="3" type="checkbox" class="form-check-input">
            <span class="form-check-label">{{ trans('backpack::base.remember_me') }}</span>
        </label>

        {{-- Syaratnya dipertahankan apa adanya: tautan hanya muncul bila
             pemulihan password benar-benar aktif. --}}
        @if (backpack_users_have_email() && backpack_email_column() == 'email' && config('backpack.base.setup_password_recovery_routes', true))
            <a tabindex="4" href="{{ route('backpack.auth.password.reset') }}">
                {{ trans('backpack::base.forgot_your_password') }}
            </a>
        @endif
    </div>

    <button tabindex="5" type="submit" class="btn-auth">{{ trans('backpack::base.login') }}</button>
</form>

@push('after_scripts')
<script>
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        const input = document.getElementById(btn.getAttribute('aria-controls'));
        const show = @json(trans('backpack.theme-tabler::theme-tabler.password-show'));
        const hide = @json(trans('backpack.theme-tabler::theme-tabler.password-hide'));
        btn.addEventListener('click', () => {
            const kini = input.type === 'password';
            input.type = kini ? 'text' : 'password';
            btn.textContent = kini ? hide : show;
            btn.setAttribute('aria-pressed', String(kini));
            input.focus();
        });
    });
</script>
@endpush
