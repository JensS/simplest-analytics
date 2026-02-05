/**
 * The Simplest Analytics - JS Fallback Tracker
 * This script only loads when the page was served from cache.
 */
(function() {
    // Generate a unique ID for this specific page view
    var pageviewId = Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
    var startTime = Date.now();

    // Add listener to send duration when user leaves
    document.addEventListener('pagehide', function() {
        var duration = Math.round((Date.now() - startTime) / 1000);
        var payload = JSON.stringify({
            pageview_id: window.sa_pageview_id || pageviewId,
            duration: duration
        });
        navigator.sendBeacon(sa_params.api_url.replace('/track', '/duration'), payload);
    });

    // Only track initial view if server-side tracking hasn't already occurred.
    // The `window.sa_tracked` variable is set by PHP in the footer on server-tracked requests.
    if (window.sa_tracked) {
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

