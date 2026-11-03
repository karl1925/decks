<!doctype html>
<html lang="en"><head>
    @include('partials.favicon')<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Presentation screen · Decks</title><link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('layout.css').'?v=20261103-facebook-login-slide' }}"><link rel="stylesheet" href="{{ asset('video.css') }}"><link rel="stylesheet" href="{{ asset('master.css') }}"><link rel="stylesheet" href="{{ asset('presentation-outline.css').'?v=20261103-pause-controls' }}"><link rel="stylesheet" href="{{ asset('leaderboard.css') }}"><link rel="stylesheet" href="{{ asset('theme.css').'?v=20261103-master-full-viewport' }}"></head>
<body class="client-bg master-client {{ $eventBackgroundUrl ? 'event-background-active' : '' }}" data-status-url="{{ $masterStatusUrl }}" data-presented-group="{{ $presented_group['label'] ?? '' }}" data-presented-group-version="{{ $presented_group['version'] ?? 0 }}" @if($eventBackgroundUrl) style="--event-background-image: url('{{ $eventBackgroundUrl }}'); --event-background-opacity: {{ number_format($eventBackgroundOpacity / 100, 2, '.', '') }};" @endif>
<main class="master-stage" id="master-stage">
@if($live && $presented_group)
    @include('partials.group-roster-slide')
@elseif($live && $presented_chapter)
    @include('partials.chapter-overview',['presented_chapter'=>$presented_chapter])
@elseif($live && $presented_topic)
    @include('partials.topic-overview',['presented_topic'=>$presented_topic])
@elseif (! $live || ! $current)
    <section class="master-waiting"><span class="master-mark">✦</span><div class="eyebrow">{{ $pause_message ? 'SESSION PAUSED' : 'STUDENT ROOM' }}</div><h1>{{ $pause_message ?: 'Waiting for the presentation to begin.' }}</h1><p>{{ $pause_message ? 'Your host will resume the presentation shortly.' : 'This screen will update when the host starts presenting.' }}</p>@if($pause_ends_at)<div class="pause-countdown-display"><span>Resuming in</span><strong class="pause-countdown" data-end="{{ $pause_ends_at }}">--:--</strong></div>@endif</section>
@elseif ($current->type === 'group_activity')
    <section class="master-timer-screen master-group-activity"><div class="eyebrow">GROUP ACTIVITY</div><h1>{{ $current->title }}</h1><div class="master-group-activity-instructions">{{ $current->content ?: 'Work together with your group and add your shared output in each section.' }}</div>@if($section_timer)<div class="master-clock" id="master-section-clock" data-end="{{ $section_timer['ends_at_ms'] }}" data-phase="{{ $section_timer['phase'] }}">{{ $section_timer['phase']==='ready' ? sprintf('%02d:00',$section_timer['duration_minutes']) : ($section_timer['phase']==='finished' ? '00:00' : '--:--') }}</div><p class="master-timer-caption">TIME REMAINING</p>@endif</section>
@elseif ($current->type === 'assessment')
    <section class="master-timer-screen" data-slide-id="{{ $current->id }}" data-slide-version="{{ $current_version }}" data-phase="{{ $assessment['phase'] }}">
        @if($assessment['phase'] === 'waiting')
            <div class="eyebrow">ASSESSMENT ROOM</div><h1>Assessment will begin soon</h1><p>Students can get ready on their own devices.</p>
        @elseif($assessment['phase'] === 'lobby')
            <div class="eyebrow">ASSESSMENT READY</div><h1>{{ $assessment['title'] }}</h1><p>Students can review the instructions. The timer begins when the host starts it.</p>
        @elseif($assessment['phase'] === 'finished')
            <div class="eyebrow">ASSESSMENT COMPLETE</div><h1>Time is up</h1><div class="master-clock">00:00</div>
        @else
            <div class="eyebrow"><span class="live-dot"></span> ASSESSMENT IN PROGRESS</div><p class="master-assessment-title">{{ $assessment['title'] }}</p><div class="master-clock" id="master-clock" data-end="{{ $assessment['ends_at_ms'] }}">--:--</div><p class="master-timer-caption">TIME REMAINING</p>
        @endif
        @if($assessment['instructions'])<div class="assessment-custom-instructions master-assessment-instructions"><strong>Instructions</strong><div>{!! \App\Support\DeckHtml::sanitize($assessment['instructions']) !!}</div></div>@endif
        @if($assessment['phase']==='finished')@include('partials.assessment-end-leaderboards',['assessment_results'=>$assessment_results])@endif
    </section>
@else
    <section class="client-slide master-slide" data-slide-id="{{ $current->id }}" data-slide-version="{{ $current_version }}">
        <div class="slide-topline"><span>NOW PRESENTING</span><span>
            @if($section_timer)
                <span class="master-section-clock"><span>{{ $section_timer['title'] }}</span><strong id="master-section-clock" data-end="{{ $section_timer['ends_at_ms'] }}" data-phase="{{ $section_timer['phase'] }}">{{ $section_timer['phase']==='ready' ? sprintf('%02d:00',$section_timer['duration_minutes']) : ($section_timer['phase']==='finished' ? '00:00' : '--:--') }}</strong></span>
            @else
                <span class="live-dot"></span> LIVE SESSION
            @endif
        </span></div>
        <div class="client-slide-body">@include('partials.slide-content',['slide'=>$current])</div>
        <div class="client-slide-footer"><span>Decks PRESENTATION</span><span>{{ $current->title }}</span></div>
    </section>
@endif
</main>
<script>
const masterStatusUrl=document.body.dataset.statusUrl;let masterSlide=document.querySelector('[data-slide-id]')?.dataset.slideId||'{{ $current?->id ?? '' }}';let masterVersion=Number(document.querySelector('[data-slide-version]')?.dataset.slideVersion||'{{ $current_version }}');let masterLive={{ $live ? 'true' : 'false' }};let masterChapterId=String(document.querySelector('[data-chapter-id]')?.dataset.chapterId||'');let masterChapterVersion=Number(document.querySelector('[data-chapter-version]')?.dataset.chapterVersion||0);let masterTopicId=String(document.querySelector('[data-topic-id]')?.dataset.topicId||'');let masterTopicVersion=Number(document.querySelector('[data-topic-version]')?.dataset.topicVersion||0);let masterPresentedGroup=document.body.dataset.presentedGroup||'';let masterPresentedGroupVersion=Number(document.body.dataset.presentedGroupVersion||0);let masterPhase='{{ $assessment['phase'] ?? '' }}';let masterAssessmentEnd={{ $assessment['ends_at_ms'] ?? 'null' }};let masterLeaderboardVersion=Number(document.querySelector('[data-leaderboard-version]')?.dataset.leaderboardVersion||0);let masterAssessmentResultsVersion=Number(document.querySelector('[data-assessment-results-version]')?.dataset.assessmentResultsVersion||0);let masterSectionPhase='{{ $section_timer['phase'] ?? '' }}';let masterSectionEnd={{ $section_timer['ends_at_ms'] ?? 'null' }};const clock=document.getElementById('master-clock');const sectionClock=document.getElementById('master-section-clock');
async function syncMaster(){try{const response=await fetch(masterStatusUrl,{headers:{Accept:'application/json'}});if(!response.ok)return;const data=await response.json();const slide=String(data.current?.id||'');const version=Number(data.current_version||0);const chapterId=String(data.presented_chapter?.id||'');const chapterVersion=Number(data.presented_chapter?.version||0);const topicId=String(data.presented_topic?.id||'');const topicVersion=Number(data.presented_topic?.version||0);const phase=data.assessment?.phase||'';const assessmentEnd=data.assessment?.ends_at_ms??null;const leaderboardVersion=Number(data.leaderboard_version||0);const assessmentResultsVersion=Number(data.assessment_results?.version||0);const sectionPhase=data.section_timer?.phase||'';const sectionEnd=data.section_timer?.ends_at_ms??null;const presentedGroup=String(data.presented_group?.label||'');const presentedGroupVersion=Number(data.presented_group?.version||0);if(data.live!==masterLive||slide!==masterSlide||version!==masterVersion||chapterId!==masterChapterId||chapterVersion!==masterChapterVersion||topicId!==masterTopicId||topicVersion!==masterTopicVersion||presentedGroup!==masterPresentedGroup||presentedGroupVersion!==masterPresentedGroupVersion||phase!==masterPhase||leaderboardVersion!==masterLeaderboardVersion||assessmentResultsVersion!==masterAssessmentResultsVersion||sectionPhase!==masterSectionPhase)location.reload();if(assessmentEnd!==masterAssessmentEnd){masterAssessmentEnd=assessmentEnd;if(clock&&assessmentEnd!==null)clock.dataset.end=String(assessmentEnd)}if(sectionEnd!==masterSectionEnd){masterSectionEnd=sectionEnd;if(sectionClock&&sectionEnd!==null)sectionClock.dataset.end=String(sectionEnd)}}catch(error){}}
const pauseCountdown=document.querySelector('.pause-countdown');if(pauseCountdown){function updatePauseCountdown(){const seconds=Math.max(0,Math.ceil((Number(pauseCountdown.dataset.end)-Date.now())/1000));pauseCountdown.textContent=String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0')}updatePauseCountdown();setInterval(updatePauseCountdown,1000)}
setInterval(syncMaster,2000);
if(clock){function updateMasterClock(){const seconds=Math.max(0,Math.floor((Number(clock.dataset.end)-Date.now())/1000));clock.textContent=String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0');if(seconds===0)location.reload()}updateMasterClock();setInterval(updateMasterClock,1000)}
if(sectionClock&&sectionClock.dataset.phase==='running'){function updateSectionClock(){const seconds=Math.max(0,Math.floor((Number(sectionClock.dataset.end)-Date.now())/1000));sectionClock.textContent=String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0');if(seconds===0)location.reload()}updateSectionClock();setInterval(updateSectionClock,1000)}
</script><script src="{{ asset('theme.js').'?v=20261103-event-view-toggle' }}"></script><script src="{{ asset('video-volume.js').'?v=20261027-volume-80' }}"></script></body></html>
