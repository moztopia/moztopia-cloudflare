@extends('components.layout.index')

@section('content')
<div class="content-grid">
    <div class="primary-column">
        <section class="location-card" aria-labelledby="location-title">
            <div class="location-header"><span class="eyebrow">{{ __('demo.location') }}</span><span @class(['state-label', 'positive' => $visitor->countryCode !== null])>{{ __('demo.'.($visitor->countryCode ? 'available' : 'unknown')) }}</span></div>
            <div class="location-identity"><span class="country-flag" aria-hidden="true">{{ $flag }}</span><div><h2 id="location-title">{{ $countryName ?? __('demo.unknown_country') }}</h2><p><bdi>{{ $visitor->city ?? __('demo.city_unknown') }}</bdi></p></div><span class="country-code mono">{{ $visitor->countryCode ?? '—' }}</span></div>
            <dl class="values-grid">
                <x-value.index :label="__('demo.country_code')" :value="$visitor->countryCode" :code="true"/>
                <x-value.index :label="__('demo.continent')" :value="$visitor->continentCode" :code="true"/>
                <x-value.index :label="__('demo.region')" :value="$visitor->region"/>
                <x-value.index :label="__('demo.region_code')" :value="$visitor->regionCode" :code="true"/>
                <x-value.index :label="__('demo.city')" :value="$visitor->city"/>
                <x-value.index :label="__('demo.timezone')" :value="$visitor->timezone" :code="true"/>
            </dl>
        </section>
        <section class="notice"><x-icon.index name="pin"/><div><h2>{{ __('demo.'.($visitor->countryMarker === 'T1' ? 'tor_title' : 'location_note_title')) }}</h2><p>{{ __('demo.'.($visitor->countryMarker === 'T1' ? 'tor_note' : 'location_note')) }}</p></div></section>
    </div>
    <div class="secondary-column">
        <x-code.index :code="$snippet"/>
        <section class="explanation"><h2>{{ __('demo.headers_title') }}</h2><p>{{ __('demo.headers_note') }}</p><a href="{{ route('request', $query, false) }}">{{ __('demo.inspect_headers') }}</a></section>
    </div>
</div>
@endsection
