/**
 * Injellik Recommendations for PrestaShop — back office.
 *
 * - connection test;
 * - batched jobs with a progress bar (catalog synchronisation, order import, queue flush):
 *   the controller answers {ok, done, done_count, total, message, after_id?} and the job calls it again until done;
 * - placement cards, copy buttons.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
(function () {
  'use strict';

  function post(url, action, params) {
    var body = new FormData();
    body.append('action', action);
    Object.keys(params || {}).forEach(function (key) { body.append(key, params[key]); });

    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) {
        return response.json().catch(function () {
          return { ok: false, message: 'HTTP ' + response.status };
        });
      });
  }

  function ajaxUrl(element) {
    var root = element.closest('[data-ajax-url]');
    return root ? root.getAttribute('data-ajax-url') : '';
  }

  /* Connection test */
  function initConnectionTest(form) {
    var button = form.querySelector('.js-injellik-test');
    var result = form.querySelector('.js-injellik-test-result');
    if (!button) {
      return;
    }
    button.addEventListener('click', function () {
      var label = button.innerHTML;
      button.disabled = true;
      button.textContent = button.getAttribute('data-loading');
      post(ajaxUrl(form), 'TestConnection', {
        api_url: form.querySelector('[name=api_url]').value,
        api_login: form.querySelector('[name=api_login]').value,
        api_password: form.querySelector('[name=api_password]').value
      }).then(function (data) {
        result.hidden = false;
        result.className = 'injellik-test-result js-injellik-test-result alert ' + (data.ok ? 'alert-success' : 'alert-danger');
        result.textContent = data.message;
      }).catch(function (error) {
        result.hidden = false;
        result.className = 'injellik-test-result js-injellik-test-result alert alert-danger';
        result.textContent = String(error);
      }).then(function () {
        button.disabled = false;
        button.innerHTML = label;
      });
    });
  }

  /* Batched jobs */
  function initJob(job) {
    var button = job.querySelector('.js-injellik-job-start');
    var bar = job.querySelector('.js-injellik-job-bar');
    var message = job.querySelector('.js-injellik-job-message');
    var action = job.getAttribute('data-action');
    var running = false;

    function setProgress(done, total) {
      if (!bar) {
        return;
      }
      var percent = total > 0 ? Math.min(100, Math.round(100 * done / total)) : 100;
      bar.style.width = percent + '%';
      bar.textContent = percent > 8 ? percent + ' %' : '';
    }

    function finish(ok, text) {
      running = false;
      job.classList.remove('is-running');
      if (button) {
        button.disabled = false;
      }
      if (message) {
        message.textContent = text || '';
        message.className = 'injellik-job__message js-injellik-job-message ' + (ok ? 'text-success' : 'text-danger');
      }
      if (bar) {
        bar.classList.remove('progress-bar-striped', 'active');
        bar.classList.toggle('progress-bar-danger', !ok);
        bar.classList.toggle('progress-bar-success', ok);
      }
      if (ok) {
        var next = document.querySelector('.js-injellik-next');
        if (next) {
          next.classList.remove('disabled');
        }
        if (job.getAttribute('data-reload')) {
          window.setTimeout(function () { window.location.reload(); }, 800);
        }
      }
    }

    function step(state) {
      var params = { done_count: state.done_count || 0, after_id: state.after_id || 0 };
      job.querySelectorAll('.js-injellik-job-param').forEach(function (input) { params[input.name] = input.value; });

      post(ajaxUrl(job), action, params).then(function (data) {
        if (!data.ok) {
          finish(false, data.message);
          return;
        }
        setProgress(data.done_count || 0, data.total || 0);
        if (message) {
          message.textContent = data.message || '';
        }
        if (data.done) {
          setProgress(1, 1);
          finish(true, data.message);
        } else {
          step(data);
        }
      }).catch(function (error) {
        finish(false, String(error));
      });
    }

    function start() {
      if (running) {
        return;
      }
      running = true;
      job.classList.add('is-running');
      if (button) {
        button.disabled = true;
      }
      if (bar) {
        bar.classList.remove('progress-bar-danger', 'progress-bar-success');
        bar.classList.add('progress-bar-striped', 'active');
      }
      setProgress(0, 1);
      step({});
    }

    if (button) {
      button.addEventListener('click', start);
    }
    if (job.getAttribute('data-autostart')) {
      start();
    }
  }

  /* Placement cards: collapse the settings of disabled blocks */
  function initPlacement(card) {
    var toggle = card.querySelector('.js-injellik-placement-toggle');
    if (toggle) {
      toggle.addEventListener('change', function () {
        card.classList.toggle('is-enabled', toggle.checked);
      });
    }
  }

  function initCopy(button) {
    button.addEventListener('click', function () {
      var input = button.closest('.injellik-copy').querySelector('.js-injellik-copy-source');
      input.select();
      if (navigator.clipboard) {
        navigator.clipboard.writeText(input.value);
      } else {
        document.execCommand('copy');
      }
    });
  }

  /* Keep the active tab of the configuration page in the URL hash */
  function initTabs() {
    var hash = window.location.hash;
    if (hash && window.jQuery) {
      var link = document.querySelector('.injellik-tabs a[href="' + hash + '"]');
      if (link) {
        window.jQuery(link).tab('show');
      }
    }
    document.querySelectorAll('.injellik-tabs a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, '', link.getAttribute('href'));
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-injellik-connection').forEach(initConnectionTest);
    document.querySelectorAll('.js-injellik-job').forEach(initJob);
    document.querySelectorAll('.js-injellik-placement').forEach(initPlacement);
    document.querySelectorAll('.js-injellik-copy').forEach(initCopy);
    initTabs();
  });
})();
