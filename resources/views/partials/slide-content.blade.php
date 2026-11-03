@php($data = $slide->layout_data ?? [])
@php($contentHtml = \App\Support\DeckHtml::sanitize($slide->content ?? ''))
@php($layout = $slide->layout_key ?? 'standard')
@php($image = $data['image_url'] ?? null)
@php($table = $data['table'] ?? null)
@php($cards = $data['cards'] ?? [])
@php($isVideo = $slide->type === 'video')

@if ($slide->type === 'picture')
    <section class="deck-picture-only"><img src="{{ $image }}" alt="{{ $slide->title }}"></section>
@elseif ($slide->type === 'video_only')
    <section class="deck-video-only">@include('partials.slide-media')</section>
@elseif ($layout === 'cover')
    <section class="deck-cover-slide {{ $slide->title === 'E.L.E.V.A.T.E.' ? 'deck-cover-with-art' : '' }}">
        <div class="cover-copy">
            <div class="cover-kicker"><span></span> CYBERSECURITY AWARENESS</div>
            <h1>{{ $slide->title }}</h1>
            <div class="cover-subtitle">{!! $contentHtml !!}</div>
            <div class="cover-rule"></div>
            <div class="cover-speaker">
                <span class="cover-speaker-label">{{ $data['speaker_label'] ?? 'RESOURCE SPEAKER' }}</span>
                <strong>{{ $data['speaker_name'] ?? '' }}</strong>
                <span>{{ $data['speaker_role'] ?? '' }}</span>
                <span>{{ $data['speaker_org'] ?? '' }}</span>
                <span>{{ $data['speaker_region'] ?? '' }}</span>
            </div>
        </div>
        @if($slide->title === 'E.L.E.V.A.T.E.')
        <div class="cover-art" aria-hidden="true">
            <div class="cover-orbit orbit-one"></div><div class="cover-orbit orbit-two"></div><div class="cover-orbit orbit-three"></div>
            <svg class="shield-svg" viewBox="0 0 260 300" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M130 12L231 52V132C231 204 188 258 130 286C72 258 29 204 29 132V52L130 12Z" stroke="currentColor" stroke-width="3"/><path d="M130 35L210 67V132C210 190 177 233 130 259C83 233 50 190 50 132V67L130 35Z" stroke="currentColor" stroke-opacity=".45" stroke-width="1.5"/><path d="M130 88C113.4 88 100 101.4 100 118V132H92V190H168V132H160V118C160 101.4 146.6 88 130 88ZM112 118C112 108.1 120.1 100 130 100C139.9 100 148 108.1 148 118V132H112V118Z" fill="currentColor"/><circle cx="130" cy="157" r="7" fill="#13231f"/><path d="M130 164V175" stroke="#13231f" stroke-width="4" stroke-linecap="round"/><path d="M19 108H52M208 108H241M38 221L63 202M222 221L197 202M130 0V35M130 265V300" stroke="currentColor" stroke-opacity=".38" stroke-width="2"/><circle cx="19" cy="108" r="4" fill="#b9f36b"/><circle cx="241" cy="108" r="4" fill="#b9f36b"/><circle cx="38" cy="221" r="4" fill="#b9f36b"/><circle cx="222" cy="221" r="4" fill="#b9f36b"/></svg>
            <span class="cover-art-caption">PROTECT · PREVENT · EMPOWER</span>
        </div>
        @endif
    </section>
@elseif ($layout === 'video_focus')
    <section class="deck-video-focus"><div class="deck-eyebrow">NOW PLAYING</div><h1>{{ $slide->title }}</h1>@include('partials.slide-media')@if($contentHtml)<div class="deck-body">{!! $contentHtml !!}</div>@endif</section>
@elseif ($layout === 'video_split')
    <section class="deck-video-split"><div class="video-split-media">@include('partials.slide-media')</div><div class="video-split-copy"><div class="deck-eyebrow">WATCH &amp; FOLLOW ALONG</div><h1>{{ $slide->title }}</h1><div class="deck-body">{!! $contentHtml !!}</div></div></section>
@elseif ($layout === 'split')
    <section class="deck-split {{ $slide->title === 'Join Decks' ? 'deck-split-qr' : '' }}">
        <div class="deck-split-copy"><div class="deck-eyebrow">{{ $isVideo ? 'WATCH & FOLLOW ALONG' : 'A MOMENT TO LEARN' }}</div><h1>{{ $slide->title }}</h1><div class="deck-body">{!! $contentHtml !!}</div>@if($isVideo)@include('partials.slide-media')@endif</div>
        <div class="deck-split-visual">@if($image)<figure><img src="{{ $image }}" alt="{{ $data['image_caption'] ?? $slide->title }}">@if(!empty($data['image_caption']))<figcaption>{{ $data['image_caption'] }}</figcaption>@endif</figure>@elseif($cards)<div class="feature-cards compact-cards">@foreach($cards as $card)<article class="feature-card"><span class="feature-card-icon">{{ $card['icon'] ?? '✦' }}</span><h2>{!! \App\Support\DeckHtml::sanitize($card['title'] ?? '') !!}</h2><div class="feature-card-copy">{!! \App\Support\DeckHtml::sanitize($card['body'] ?? '') !!}</div></article>@endforeach</div>@else<div class="visual-placeholder"><span>✳</span><small>VISUAL GOES HERE</small></div>@endif</div>
    </section>
@elseif ($layout === 'image_focus')
    <section class="deck-image-focus">
        <div class="image-focus-frame">@if($image)<img src="{{ $image }}" alt="{{ $data['image_caption'] ?? $slide->title }}">@else<div class="visual-placeholder"><span>✳</span><small>ADD A PICTURE IN SLIDE SETTINGS</small></div>@endif<div class="image-focus-overlay"><div class="deck-eyebrow">{{ $isVideo ? 'WATCH & FOLLOW ALONG' : 'A MOMENT TO LEARN' }}</div><h1>{{ $slide->title }}</h1></div></div>
        @if($isVideo)@include('partials.slide-media')@endif @if($contentHtml)<div class="deck-body">{!! $contentHtml !!}</div>@endif @if(!empty($data['image_caption']))<p class="image-caption">{{ $data['image_caption'] }}</p>@endif
    </section>
@elseif ($layout === 'data_table')
    <section class="deck-data-table"><div class="deck-eyebrow">{{ $isVideo ? 'WATCH & FOLLOW ALONG' : 'KEY INFORMATION' }}</div><h1>{{ $slide->title }}</h1>@if($contentHtml)<div class="deck-body">{!! $contentHtml !!}</div>@endif @if($isVideo)@include('partials.slide-media')@endif
        @if($table)<div class="table-wrap"><table><thead><tr>@foreach($table['headers'] ?? [] as $header)<th>{!! \App\Support\DeckHtml::sanitize($header) !!}</th>@endforeach</tr></thead><tbody>@foreach($table['rows'] ?? [] as $row)<tr>@foreach($row as $cell)<td>{!! \App\Support\DeckHtml::sanitize($cell) !!}</td>@endforeach</tr>@endforeach</tbody></table></div>@endif
    </section>
@elseif ($layout === 'feature_grid')
    <section class="deck-feature-grid"><div class="deck-eyebrow">{{ $isVideo ? 'WATCH & FOLLOW ALONG' : 'THE BIG IDEAS' }}</div><h1>{{ $slide->title }}</h1>@if($contentHtml)<div class="deck-body">{!! $contentHtml !!}</div>@endif @if($isVideo)@include('partials.slide-media')@endif
        @if($cards)<div class="feature-cards">@foreach($cards as $card)<article class="feature-card"><span class="feature-card-icon">{{ $card['icon'] ?? '✦' }}</span><h2>{!! \App\Support\DeckHtml::sanitize($card['title'] ?? '') !!}</h2><div class="feature-card-copy">{!! \App\Support\DeckHtml::sanitize($card['body'] ?? '') !!}</div></article>@endforeach</div>@endif
    </section>
@elseif ($layout === 'modular')
    <section class="deck-modular"><header><div class="deck-eyebrow">{{ $isVideo ? 'WATCH & FOLLOW ALONG' : 'A MOMENT TO LEARN' }}</div><h1>{{ $slide->title }}</h1>@if($contentHtml)<div class="deck-body">{!! $contentHtml !!}</div>@endif</header>
        @if($isVideo)@include('partials.slide-media')@endif
        @if($image)<figure class="modular-image"><img src="{{ $image }}" alt="{{ $data['image_caption'] ?? $slide->title }}">@if(!empty($data['image_caption']))<figcaption>{{ $data['image_caption'] }}</figcaption>@endif</figure>@endif
        @if($table)<div class="table-wrap"><table><thead><tr>@foreach($table['headers'] ?? [] as $header)<th>{!! \App\Support\DeckHtml::sanitize($header) !!}</th>@endforeach</tr></thead><tbody>@foreach($table['rows'] ?? [] as $row)<tr>@foreach($row as $cell)<td>{!! \App\Support\DeckHtml::sanitize($cell) !!}</td>@endforeach</tr>@endforeach</tbody></table></div>@endif
        @if($cards)<div class="feature-cards compact-cards">@foreach($cards as $card)<article class="feature-card"><span class="feature-card-icon">{{ $card['icon'] ?? '✦' }}</span><h2>{!! \App\Support\DeckHtml::sanitize($card['title'] ?? '') !!}</h2><div class="feature-card-copy">{!! \App\Support\DeckHtml::sanitize($card['body'] ?? '') !!}</div></article>@endforeach</div>@endif
    </section>
@elseif ($layout === 'leaderboard')
    @include('partials.leaderboard-content', ['leaderboard' => $leaderboard ?? null])
@else
    <section class="deck-standard"><div class="deck-eyebrow">{{ $isVideo ? 'WATCH & FOLLOW ALONG' : 'A MOMENT TO LEARN' }}</div><h1>{{ $slide->title }}</h1>@if($contentHtml)<div class="deck-body prose">{!! $contentHtml !!}</div>@endif @if($isVideo)@include('partials.slide-media')@endif</section>
@endif
