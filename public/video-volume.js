(() => {
    const volume = 0.8;

    document.querySelectorAll('video[data-start-volume]').forEach((video) => {
        video.volume = volume;

        video.addEventListener('play', () => {
            video.volume = volume;
            if (video.dataset.autoplayMutedFallback !== 'true') video.muted = false;
        });

        const enableSoundFromUserAction = () => {
            video.volume = volume;
            video.muted = false;
            delete video.dataset.autoplayMutedFallback;
        };
        video.addEventListener('pointerdown', enableSoundFromUserAction);
        video.addEventListener('keydown', enableSoundFromUserAction);

        if (video.autoplay) {
            video.muted = false;
            video.play().catch(() => {
                video.dataset.autoplayMutedFallback = 'true';
                video.volume = volume;
                video.muted = true;
                video.play().catch(() => {});
            });
        }
    });

    document.querySelectorAll('iframe[data-start-volume]').forEach((frame) => {
        const url = new URL(frame.src, window.location.href);
        const host = url.hostname.toLowerCase();
        const isYouTube = host === 'youtu.be' || host.endsWith('youtube.com') || host.endsWith('youtube-nocookie.com');
        const isVimeo = host === 'vimeo.com' || host.endsWith('.vimeo.com');
        if (!isYouTube && !isVimeo) return;

        const sendVolume = () => {
            if (!frame.contentWindow) return;
            if (isYouTube) {
                frame.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'setVolume', args: [80] }), '*');
                frame.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'unMute', args: [] }), '*');
            } else {
                frame.contentWindow.postMessage(JSON.stringify({ method: 'setVolume', value: volume }), '*');
            }
        };

        let attempts = 0;
        const timer = window.setInterval(() => {
            sendVolume();
            if (++attempts >= 12) window.clearInterval(timer);
        }, 500);
        frame.addEventListener('pointerdown', sendVolume);
    });
})();
