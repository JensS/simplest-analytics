/**
 * The Simplest Analytics - JS Fallback Tracker
 * Tracks pageviews for cached pages where server-side tracking didn't run.
 */
(function() {
    // Generate a unique ID for this specific page view
    var pageviewId = Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
    var startTime = Date.now();

    // Check if server-side tracking ran for THIS request (not a cached page).
    // window.sa_sig is a Unix timestamp set by PHP. If it's within 10 seconds
    // of current time, PHP processed this request and already tracked it.
    // If stale or missing, this is a cached page - JS should track.
    var now = Math.floor(Date.now() / 1000);
    var serverTracked = window.sa_sig && (now - window.sa_sig) < 10;

    // Add listener to send duration when user leaves
    document.addEventListener('pagehide', function() {
        var duration = Math.round((Date.now() - startTime) / 1000);
        var payload = JSON.stringify({
            pageview_id: serverTracked ? window.sa_pageview_id : pageviewId,
            duration: duration
        });
        navigator.sendBeacon(sa_params.api_url.replace('/track', '/duration'), payload);
    });

    // Only track initial view if server-side tracking hasn't already occurred.
    if (serverTracked) {
        return;
    }

    var payload = JSON.stringify({
        path: window.location.pathname + window.location.search,
        ref: document.referrer || '',
        pageview_id: pageviewId
    });

    // Use sendBeacon if available, otherwise fall back to XHR.
    if (navigator.sendBeacon) {
        navigator.sendBeacon(sa_params.api_url, payload);
    } else {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', sa_params.api_url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(payload);
    }
})();

