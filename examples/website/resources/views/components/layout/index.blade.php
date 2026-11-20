<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="{{ __('demo.site_description') }}">
    <title>{{ __('demo.page_'.$page) }} · Moztopia Cloudflare</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/example.css') }}">
    <script src="{{ asset('assets/example.js') }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main">{{ __('demo.skip') }}</a>
<div class="app-shell">
    <aside class="sidebar" id="navigation">
        <a class="brand" href="{{ route('visitor', $query) }}" aria-label="Moztopia Cloudflare">
            <span class="brand-symbol"><x-icon.index/></span>
            <span>moztopia<span class="brand-sub">Cloudflare</span></span>
        </a>
        <div class="nav-label">{{ __('demo.explore') }}</div>
        <nav class="main-nav" aria-label="{{ __('demo.navigation') }}">
            @foreach (['visitor' => 'pin', 'ui' => 'layers', 'request' => 'code'] as $destination => $icon)
                <a href="{{ route($destination, $query) }}" @class(['nav-link', 'active' => $page === $destination]) @if($page === $destination) aria-current="page" @endif>
                    <x-icon.index :name="$icon"/>
                    {{ __('demo.page_'.$destination) }}
                </a>
            @endforeach
        </nav>
        <div class="sidebar-note">
            <span class="package-label">moztopia/cloudflare</span>
            <p>{{ __('demo.sidebar_note') }}</p>
            <a href="https://github.com/moztopia/moztopia-cloudflare">{{ __('demo.source') }}</a>
        </div>
    </aside>

    <div class="workspace">
        <header class="topbar">
            <div class="topbar-start">
                <button class="icon-button mobile-menu" type="button" data-navigation aria-controls="navigation" aria-expanded="false" aria-label="{{ __('demo.navigation') }}"><x-icon.index name="menu"/></button>
                <span class="topbar-title">{{ __('demo.example_app') }}</span>
            </div>
            <div class="preferences">
                <details class="language-menu" data-language-menu>
                    <summary aria-label="{{ __('demo.language') }}"><span aria-hidden="true">🌐</span> {{ strtoupper(app()->getLocale()) }} <span class="chevron" aria-hidden="true"></span></summary>
                    <div class="language-options">
                        @foreach ($locales as $locale => $name)
                            <a lang="{{ $locale }}" href="{{ route($page, array_merge($query, ['locale' => $locale])) }}" @if(app()->getLocale() === $locale) aria-current="true" @endif>
                                <span aria-hidden="true">{{ ['en' => '🇬🇧', 'th' => '🇹🇭', 'ar' => '🌐'][$locale] }}</span> {{ $name }}
                                @if(app()->getLocale() === $locale)<x-icon.index name="check"/>@endif
                            </a>
                        @endforeach
                    </div>
                </details>
                <button class="icon-button" type="button" data-theme-toggle aria-pressed="false" aria-label="{{ __('demo.theme') }}"><x-icon.index name="theme"/></button>
            </div>
        </header>

        <main id="main" class="main-content">
            <div class="page-heading">
                <p class="eyebrow">{{ __('demo.eyebrow') }}</p>
                <h1>{{ __('demo.page_'.$page) }}</h1>
                <p class="intro">{{ __('demo.intro_'.$page) }}</p>
            </div>

            <section class="scenario-panel" aria-label="{{ __('demo.harness') }}">
                <div class="scenario-summary">
                    <span @class(['source-badge', 'simulated' => $simulated, 'live' => !$simulated])>{{ __('demo.'.($simulated ? 'simulated' : 'live')) }}</span>
                    <p>{{ __('demo.'.($simulated ? 'simulated_note' : 'live_note')) }}</p>
                </div>
                <form class="scenario-form" action="{{ route($page) }}" method="get" data-scenario-form>
                    <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
                    <div class="select-field">
                        <label for="scenario">{{ __('demo.request_source') }}</label>
                        <div class="select-wrap">
                            <select id="scenario" name="scenario" data-scenario>
                                @foreach ($scenarioOptions as $option)
                                    <option value="{{ $option }}" @selected($scenario === $option)>{{ __('demo.scenario_'.$option) }}</option>
                                @endforeach
                            </select>
                            <span class="chevron" aria-hidden="true"></span>
                        </div>
                    </div>
                    <button class="button secondary" type="submit">{{ __('demo.run') }}</button>
                </form>
            </section>

            @yield('content')

            <footer class="page-footer">
                <span><x-icon.index name="cloud"/> {{ __('demo.footer') }}</span>
                <span class="runtime-label">Laravel · PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}.{{ PHP_RELEASE_VERSION }}</span>
            </footer>
        </main>
    </div>
</div>
<div class="toast" data-toast role="status" aria-live="polite" hidden></div>
</body>
</html>
