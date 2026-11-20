@props(['code', 'title' => null])
<section class="code-panel" aria-label="{{ $title ?? __('demo.try_code') }}">
    <header><span><x-icon.index name="code"/>{{ $title ?? __('demo.try_code') }}</span><button type="button" data-copy data-copied="{{ __('demo.copied') }}" data-copy-failed="{{ __('demo.copy_failed') }}">{{ __('demo.copy') }}</button></header>
    <pre><code>{{ $code }}</code></pre>
    <p>{{ __('demo.code_note') }}</p>
</section>
