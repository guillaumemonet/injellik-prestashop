/**
 * Injellik Recommendations for PrestaShop.
 *
 * - loads the recommendation blocks when they come into view;
 * - reports the clicks on recommended products (CLICK action + block statistics).
 *
 * Events dispatched on document: "injellik:loaded" (detail: {element, placement}) after a block is inserted.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
(function () {
  'use strict';

  var config = window.injellik || {};

  function load(placeholder) {
    if (placeholder.getAttribute('data-injellik-state')) {
      return;
    }
    placeholder.setAttribute('data-injellik-state', 'loading');

    var body = new FormData();
    body.append('payload', placeholder.getAttribute('data-injellik-payload') || '');
    body.append('signature', placeholder.getAttribute('data-injellik-signature') || '');

    fetch(config.renderUrl, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (response) { return response.ok ? response.json() : { html: '' }; })
      .then(function (data) {
        if (!data || !data.html) {
          placeholder.parentNode && placeholder.parentNode.removeChild(placeholder);
          return;
        }
        var wrapper = document.createElement('div');
        wrapper.innerHTML = data.html;
        var block = wrapper.firstElementChild;
        if (!block) {
          return;
        }
        placeholder.parentNode.replaceChild(block, placeholder);
        document.dispatchEvent(new CustomEvent('injellik:loaded', {
          detail: { element: block, placement: block.getAttribute('data-injellik-placement') }
        }));
      })
      .catch(function () {
        placeholder.parentNode && placeholder.parentNode.removeChild(placeholder);
      });
  }

  function scan(root) {
    var placeholders = (root || document).querySelectorAll('.js-injellik-reco:not([data-injellik-state])');
    if (!placeholders.length || !config.renderUrl) {
      return;
    }
    if (!('IntersectionObserver' in window)) {
      Array.prototype.forEach.call(placeholders, load);
      return;
    }
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          observer.unobserve(entry.target);
          load(entry.target);
        }
      });
    }, { rootMargin: '300px 0px' });
    Array.prototype.forEach.call(placeholders, function (placeholder) { observer.observe(placeholder); });
  }

  function trackClick(event) {
    var link = event.target.closest ? event.target.closest('a[href]') : null;
    var block = link && link.closest('.injellik-reco[data-injellik-placement]');
    var miniature = block && link.closest('[data-id-product]');
    if (!miniature || !config.trackUrl) {
      return;
    }
    var data = new FormData();
    data.append('id_product', miniature.getAttribute('data-id-product'));
    data.append('placement', block.getAttribute('data-injellik-placement'));
    data.append('token', config.token || '');
    if (config.flush) {
      data.append('flush', '1');
    }
    beacon(data);
  }

  function beacon(data) {
    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(config.trackUrl, data);
      } else {
        fetch(config.trackUrl, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true }).catch(function () {});
      }
    } catch (e) {
      // Tracking must never disturb the page.
    }
  }

  // Servers that cannot send the events after the response (no PHP-FPM): ask for it in a request nobody waits for.
  var flushTimer = null;
  function flush() {
    if (!config.flush || !config.trackUrl) {
      return;
    }
    clearTimeout(flushTimer);
    flushTimer = setTimeout(function () {
      var data = new FormData();
      data.append('flush', '1');
      data.append('token', config.token || '');
      beacon(data);
    }, 1000);
  }

  function init() {
    scan(document);
    document.addEventListener('click', trackClick, true);
    flush();
    // Blocks re-rendered by the theme (cart update, product variant change...).
    if (window.prestashop && typeof window.prestashop.on === 'function') {
      ['updatedCart', 'updatedProduct', 'updateProductList'].forEach(function (name) {
        window.prestashop.on(name, function () { setTimeout(scan, 0); });
      });
      window.prestashop.on('updatedCart', flush);
    }
  }

  window.injellikScan = scan;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
