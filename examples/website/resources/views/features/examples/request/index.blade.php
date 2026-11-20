@extends('components.layout.index')

@section('content')
<div class="content-grid">
    <div class="primary-column demo-stack">
        <section class="panel" aria-labelledby="trust-title">
            <div class="panel-heading"><h2 id="trust-title">{{ __('demo.request_metadata') }}</h2><span @class(['state-label', 'positive' => $context->trusted])>{{ __('demo.'.($context->trusted ? 'trusted' : 'untrusted')) }}</span></div>
            <dl class="values-grid two-columns">
                <x-value.index :label="__('demo.client_ip')" :value="$context->clientIp" :code="true"/>
                <x-value.index :label="__('demo.scheme')" :value="$context->scheme" :code="true"/>
                <x-value.index :label="__('demo.ray_id')" :value="$context->rayId" :code="true"/>
                <x-value.index :label="__('demo.country_marker')" :value="$visitor->countryMarker" :code="true"/>
            </dl>
        </section>
        <section class="panel header-panel" aria-labelledby="headers-title">
            <div class="panel-heading"><h2 id="headers-title">{{ __('demo.incoming_headers') }}</h2><span class="muted">{{ __('demo.selected_only') }}</span></div>
            <div class="table-wrap"><table><thead><tr><th scope="col">{{ __('demo.header') }}</th><th scope="col">{{ __('demo.value') }}</th></tr></thead><tbody>
                @foreach ($headers as $name => $value)
                    <tr><th scope="row"><code>{{ $name }}</code></th><td><bdi>{{ $value ?? __('demo.not_received') }}</bdi></td></tr>
                @endforeach
            </tbody></table></div>
        </section>
    </div>
    <div class="secondary-column">
        <x-code.index :code="$snippet"/>
        <section class="explanation"><h2>{{ __('demo.trust_title') }}</h2><p>{{ __('demo.trust_note') }}</p><p>{{ __('demo.no_secrets') }}</p></section>
    </div>
</div>
@endsection
