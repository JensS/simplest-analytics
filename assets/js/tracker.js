/**
 * Simplest Analytics - Hybrid Fallback Tracker
 * Only pings the API if the page was served from cache.
 */
(function() {
    if (window.sa_tracked) return;

    var payload = JSON.stringify({
        path: window.location.pathname + window.location.search,
        ref: document.referrer || ''
    });

    // Use sendBeacon for reliability (fires even on page unload)
    if (navigator.sendBeacon) {
        navigator.sendBeacon(sa_params.api_url, payload);
    } else {
        // Fallback for older browsers
        var xhr = new XMLHttpRequest();
        xhr.open('POST', sa_params.api_url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(payload);
    }
})();
