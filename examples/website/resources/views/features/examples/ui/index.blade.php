@extends('components.layout.index')

@section('content')
<div class="content-grid">
    <div class="primary-column demo-stack">
        <section class="preview-card" aria-labelledby="delivery-title">
            <div class="preview-label"><x-icon.index name="layers"/>{{ __('demo.application_decision') }}</div>
            <div class="delivery-visual" aria-hidden="true"><span>{{ $flag }}</span><div class="parcel"><span></span></div></div>
            <div class="preview-body"><p class="eyebrow">{{ __('demo.example_store') }}</p><h2 id="delivery-title">{{ __('demo.'.$deliveryKey) }}</h2><p>{{ __('demo.delivery_note') }}</p><a class="button" href="{{ route('visitor', $query, false) }}">{{ __('demo.view_context') }}</a></div>
        </section>
        <section class="event-card"><div class="calendar-icon" aria-hidden="true"><x-icon.index name="layers"/></div><div><p class="eyebrow">{{ __('demo.event') }}</p><h2>{{ $eventTime }}</h2><p><bdi>{{ $visitor->timezone ?? 'UTC' }}</bdi> · {{ __('demo.'.($visitor->timezone ? 'local_time' : 'utc_fallback')) }}</p></div></section>
    </div>
    <div class="secondary-column">
        <x-code.index :code="$snippet" :title="__('demo.blade_example')"/>
        <section class="explanation"><h2>{{ __('demo.your_rules') }}</h2><p>{{ __('demo.rules_note') }}</p><p class="cache-note">{{ __('demo.cache_note') }}</p></section>
    </div>
</div>
@endsection
