// No iframe or provider request exists until an explicit user action.
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-load-video]');
    if (!button) return;
    const container = button.closest('[data-content-video]');
    const source = container?.dataset.contentVideo;
    if (!source || !/^https:\/\/(www\.youtube-nocookie\.com\/embed\/[A-Za-z0-9_-]{11}|player\.vimeo\.com\/video\/\d+\?dnt=1)$/.test(source)) return;
    const iframe = document.createElement('iframe');
    iframe.src = source;
    iframe.title = container.dataset.videoTitle || 'Video';
    iframe.allow = 'fullscreen; picture-in-picture';
    iframe.setAttribute('allowfullscreen', '');
    iframe.referrerPolicy = 'no-referrer';
    container.classList.add('ratio', 'ratio-16x9');
    container.replaceChildren(iframe);
});
