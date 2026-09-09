{{--
    Found in review: swapped the generic starter-kit mark (an inline SVG path, unrelated to
    this project's own branding) for the real miautrix logo. Every call site
    (app-logo.blade.php's sidebar/header brand slot, and the three auth layouts'
    login/register/confirm-password/forgot-password header) already passes its own sizing
    via `class` (size-5, size-8, size-9, ...) — object-contain here respects that instead of
    stretching the artwork to fill a non-square slot.
--}}
<img
    src="{{ asset('images/brand/miautrix-logo.png') }}"
    alt=""
    {{ $attributes->merge(['class' => 'object-contain']) }}
>
