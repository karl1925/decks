@if ($slide->media_url)
<div class="deck-media-frame">
    @if (preg_match('/\.(mp4|webm|ogg)(?:\?.*)?$/i', $slide->media_url))
        <video src="{{ $slide->media_url }}" autoplay playsinline controls data-start-volume="0.8"></video>
    @else
        <iframe src="{{ $slide->media_url }}{{ str_contains($slide->media_url,'?') ? '&' : '?' }}autoplay=1&mute=1&muted=1&playsinline=1&enablejsapi=1&origin={{ urlencode(request()->getSchemeAndHttpHost()) }}" title="{{ $slide->title }}" data-start-volume="0.8" allow="autoplay; accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    @endif
</div>
@endif
