/* analytics_track.js — 9sty analytics tracker */
(function () {
  'use strict';

  // Allow overriding endpoint
  var endpoint = window.__9sAnalyticsEndpoint || (function () {
    var scripts = document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].src || '';
      if (src.indexOf('analytics_track.js') !== -1) {
        return src.replace(/analytics_track\.js.*$/, 'analytics_track.php');
      }
    }
    return '/analytics_track.php';
  })();

  function newId() {
    return 'v_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 8);
  }

  function getVisit() {
    try {
      var existing = sessionStorage.getItem('9s_visit_id');
      if (existing) return { id: existing, isNew: 0 };
      var vid = newId();
      sessionStorage.setItem('9s_visit_id', vid);
      return { id: vid, isNew: 1 };
    } catch (e) {
      return { id: newId(), isNew: 1 };
    }
  }

  function getPostId() {
    var el = document.querySelector('[data-post-id]');
    if (el) return el.getAttribute('data-post-id');
    var meta = document.querySelector('meta[name="post-id"]');
    if (meta) return meta.getAttribute('content');
    return '';
  }

  function send() {
    var visit = getVisit();
    var payload = new URLSearchParams({
      visit_id:       visit.id,
      is_new_session: visit.isNew,
      page_url:       window.location.href,
      page_title:     document.title || '',
      post_id:        getPostId(),
      referrer:       document.referrer || ''
    }).toString();

    if (navigator.sendBeacon) {
      try {
        navigator.sendBeacon(endpoint, new Blob([payload], { type: 'application/x-www-form-urlencoded' }));
        return;
      } catch (e) { /* fall through */ }
    }
    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: payload,
      keepalive: true,
      credentials: 'omit'
    }).catch(function () {});
  }

  if (document.readyState === 'complete') send();
  else window.addEventListener('load', send);
})();