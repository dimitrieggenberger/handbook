/**
 * Live handbook search: as the user types into a [data-livesearch] input, the
 * matching published pages render as cards inside [data-region="livesearch"].
 *
 * Plain JS on purpose (no AMD build step). Progressive enhancement: without
 * JS the form still submits to search.php. Read-only endpoint (ajax.php),
 * gated by the view capability server-side.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var MINCHARS = 2;
    var DEBOUNCEMS = 250;

    function init() {
        var container = document.querySelector('[data-region="livesearch"]');
        var input = document.querySelector('[data-livesearch]');
        if (!container || !input) {
            return;
        }
        var ajaxurl = container.getAttribute('data-ajaxurl');
        // Anything the live results should replace while active (e.g. the
        // static results block on search.php).
        var staticresults = document.querySelector('[data-region="static-results"]');

        var timer = null;
        var controller = null;
        var lastquery = '';

        function setStaticHidden(hidden) {
            if (staticresults) {
                staticresults.style.display = hidden ? 'none' : '';
            }
        }

        function clearResults() {
            container.innerHTML = '';
            container.classList.remove('is-active', 'is-loading');
            setStaticHidden(false);
        }

        function run(query) {
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();
            container.classList.add('is-active', 'is-loading');
            setStaticHidden(true);

            var url = ajaxurl + (ajaxurl.indexOf('?') === -1 ? '?' : '&')
                + 'q=' + encodeURIComponent(query);
            fetch(url, {signal: controller.signal, credentials: 'same-origin'})
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function(data) {
                    // A stale response for an outdated query is dropped.
                    if (input.value.trim() !== query) {
                        return;
                    }
                    container.classList.remove('is-loading');
                    container.innerHTML = data.html || '';
                })
                .catch(function(error) {
                    if (error.name !== 'AbortError') {
                        container.classList.remove('is-loading');
                    }
                });
        }

        input.addEventListener('input', function() {
            var query = input.value.trim();
            if (timer) {
                clearTimeout(timer);
            }
            if (query.length < MINCHARS) {
                lastquery = '';
                clearResults();
                return;
            }
            if (query === lastquery) {
                return;
            }
            timer = setTimeout(function() {
                lastquery = query;
                run(query);
            }, DEBOUNCEMS);
        });

        // Escape clears the live panel.
        input.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                input.value = '';
                lastquery = '';
                clearResults();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
