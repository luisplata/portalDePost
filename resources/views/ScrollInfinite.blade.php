@if (count($packs))
    @foreach ($packs as $pack)
    <div class="col">
            <a href="{{ env('HOME') }}content/{{ $pack->ConvertNameNormalToUrl() }}" class="imagee">
                @if($pack->isVideo == "1")
                <video class="card-img" src="{{ $pack->url_video }}" muted autoplay loop></video>
                @else
                <img class="card-img" src="{{ $pack->imagen }}" alt="" />
                @endif
                </a>
            <a href="{{ env('HOME') }}content/{{ $pack->ConvertNameNormalToUrl() }}" class="text-center">
                <h3>{{ $pack->nombre }}</h3>
            </a>
    </div>
    @endforeach
@endif  