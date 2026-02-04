/**
 * The Simplest Analytics - JS Fallback Tracker
 * This script only loads when the page was served from cache.
 */
(function() {
    var payload = JSON.stringify({
        path: window.location.pathname + window.location.search,
        ref: document.referrer || ''
    });

    if (navigator.sendBeacon) {
        navigator.sendBeacon(sa_params.api_url, payload);
    } else {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', sa_params.api_url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(payload);
    }
})();
