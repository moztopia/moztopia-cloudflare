@props(['label', 'value' => null, 'code' => false])
<div class="value-item">
    <dt>{{ $label }}</dt>
    <dd @class(['mono' => $code, 'muted' => $value === null])>
        @if($value !== null)<bdi>{{ $value }}</bdi>@else<span class="null-value">{{ __('demo.unavailable') }}</span>@endif
    </dd>
</div>
