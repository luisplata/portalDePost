@if (count($packs))
    @foreach ($packs as $pack)
    <div class="col">
            <a href="{{ env('HOME') }}content/{{ $pack->ConvertNameNormalToUrl() }}" class="imagee"><img class="card-img" src="{{ $pack->imagen }}" alt="" /></a>
            <a href="{{ env('HOME') }}content/{{ $pack->ConvertNameNormalToUrl() }}" class="text-center">
                <h3>{{ $pack->nombre }}</h3>
            </a>
    </div>
    @endforeach
@endif  