@php
    $adService = app(\App\Services\AdService::class);
    $ads = $adService->presentPublic($adService->selectForPlacement($key));
    $adVariant = isset($variant) ? $variant : 'sidebar';
    $adUuid = request()->cookie('anonymous_user_id');
@endphp

@if (! empty($ads))
    @if ($adVariant === 'grid')
        <section id="banner" data-ad-slot="{{ $key }}">
            <div class="row row-cols-2 row-cols-sm-3">
                @foreach ($ads as $ad)
                    <div class="col">
                        <a href="{{ url('/ads/' . $ad['campaign_key']) }}"
                           data-campaign-key="{{ $ad['campaign_key'] }}"
                           data-placement="{{ $key }}"
                           data-uuid="{{ $adUuid }}">
                            <div class="card bg-dark text-center">
                                <img src="{{ $ad['image_url'] }}" class="card-img"
                                     alt="{{ $ad['alt_text'] ?: $ad['headline'] }}">
                                <div class="card-body">
                                    <h2 class="card-title">{{ $ad['headline'] }}</h2>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @else
        <section data-ad-slot="{{ $key }}">
            <header class="major">
                <h2>Publicity</h2>
            </header>
            <style>
                .custom-img {
                    width: 100%;
                    height: auto;
                    aspect-ratio: 16 / 9;
                    object-fit: cover;
                }
            </style>
            <div class="mini-posts">
                @foreach ($ads as $ad)
                    <article>
                        <a href="{{ url('/ads/' . $ad['campaign_key']) }}"
                           data-campaign-key="{{ $ad['campaign_key'] }}"
                           data-placement="{{ $key }}"
                           data-uuid="{{ $adUuid }}">
                            <div class="card bg-dark text-white center">
                                <img src="{{ $ad['image_url'] }}" class="card-img custom-img"
                                     alt="{{ $ad['alt_text'] ?: $ad['headline'] }}">
                                <div class="card-img-overlay text-center">
                                    <h5 class="card-title">{{ $ad['headline'] }}</h5>
                                </div>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @once('ads-tracking-script')
        <script src="{{ asset('js/ads.js') }}"
                data-endpoint="{{ url('/api/ads/impression') }}"
                defer></script>
    @endonce
@endif
