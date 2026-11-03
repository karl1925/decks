@if ($selectedChapter)
    <section class="outline-detail">
        <div class="outline-detail-header">
            <div>
                <div class="eyebrow muted-eyebrow">CHAPTER OUTLINE</div>
                <h2>{{ $selectedChapter->title }}</h2>
                <p>Explore the chapter topics, discussions, and assessments.</p>
            </div>
            <button class="button ghost" type="button" onclick="openOutlineAdder('chapter', @js($selectedChapter->title))">＋ Add to chapter</button>
        </div>

        @foreach ($selectedChapter->decks as $deck)
            @include('partials.outline-detail-deck', ['deck' => $deck])
        @endforeach

        @if ($selectedChapter->topics->isEmpty())
            <div class="outline-detail-empty">This chapter has no topics yet. Use the + control in the sidebar to build its outline.</div>
        @else
            @foreach ($selectedChapter->topics as $topic)
                <article class="outline-detail-topic">
                    <div class="outline-detail-kind">TOPIC</div>
                    <h3>{{ $topic->title }}</h3>
                    @foreach ($topic->decks as $deck)
                        @include('partials.outline-detail-deck', ['deck' => $deck])
                    @endforeach

                </article>
            @endforeach
        @endif
    </section>
@elseif ($selectedSection)
    <section class="outline-detail">
        <div class="outline-detail-header">
            <div>
                <div class="eyebrow muted-eyebrow">SPECIAL SECTION · {{ strtoupper($selectedSection->section_type) }}</div>
                <h2>{{ $selectedSection->title }}</h2>
                <p>
                    @if ($selectedSection->duration_minutes)
                        Timer length: {{ $selectedSection->duration_minutes }} minutes.
                    @else
                        No timer set for this section.
                    @endif
                    This section is separate from the chapter outline.
                </p>
            </div>
            <button class="button ghost" type="button" onclick="openOutlineAdder('section', @js($selectedSection->title))">＋ Add deck</button>
        </div>

        @foreach ($selectedSection->decks as $deck)
            @include('partials.outline-detail-deck', ['deck' => $deck])
        @endforeach

        @if ($selectedSection->duration_minutes)
            @if ($currentSection?->id === $selectedSection->id)
                <section class="section-timer-control">
                    <div>
                        <div class="eyebrow muted-eyebrow">SECTION TIMER</div>
                        <strong>{{ $sectionTimerActive ? 'Timer is running' : ($state->section_timer_ends_at ? 'Timer complete' : 'Ready to start') }}</strong>
                    </div>
                    @if ($sectionTimerActive)
                        <div class="admin-assessment-actions">
                            <div class="admin-countdown" id="section-countdown" data-end="{{ \Illuminate\Support\Carbon::parse($state->section_timer_ends_at)->valueOf() }}">--:--</div>
                            <form method="post" action="{{ route('section.timer.adjust', $selectedSection) }}" class="adjust-timer-form">
                                @csrf
                                <label>Remaining minutes<input type="number" name="remaining_minutes" min="1" max="180" value="{{ max(1, (int) ceil((\Illuminate\Support\Carbon::parse($state->section_timer_ends_at)->valueOf() - now()->valueOf()) / 60000)) }}" required></label>
                                <button class="button ghost compact-button">Adjust timer</button>
                            </form>
                            <form method="post" action="{{ route('section.timer.stop', $selectedSection) }}">
                                @csrf
                                <button class="button danger-button">Stop timer</button>
                            </form>
                        </div>
                    @else
                        <form method="post" action="{{ route('section.timer.start', $selectedSection) }}">
                            @csrf
                            <button class="button primary" @disabled($selectedSection->decks->isEmpty())>{{ $state->section_timer_ends_at ? 'Restart section timer' : 'Start section timer' }} <span>↗</span></button>
                        </form>
                    @endif
                </section>
            @else
                <p class="outline-detail-empty">Present a deck in this section to start its timer.</p>
            @endif
        @endif
    </section>
@endif
