@props(['name' => 'cloud'])
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.7', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }} aria-hidden="true">
    @switch($name)
        @case('pin')
            <path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>
            @break
        @case('layers')
            <path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5"/>
            @break
        @case('code')
            <path d="m8 6-6 6 6 6m8-12 6 6-6 6m-3-15-2 18"/>
            @break
        @case('theme')
            <circle cx="12" cy="12" r="8"/><path d="M12 4v16a8 8 0 0 1 0-16Z" fill="currentColor" stroke="none"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('check')
            <path d="m5 12 4 4L19 6"/>
            @break
        @default
            <path d="M7 18a5 5 0 1 1 .7-10A6.5 6.5 0 0 1 20 10a4 4 0 0 1-1 8H7Z"/>
    @endswitch
</svg>
