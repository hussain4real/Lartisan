<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="url('/')">
            <img
                src="{{ asset('images/lartisan-mail-logo.png') }}"
                class="brand-logo"
                width="200"
                height="50"
                alt="{{ config('app.name') }}"
            >
        </x-mail::header>
    </x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            <strong>{{ config('app.name') }}</strong><br>
            @lang('Verified local services, booking updates, secure payments, and support with clear accountability.')<br>
            © {{ date('Y') }} {{ config('app.name') }}. @lang('All rights reserved.')
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
