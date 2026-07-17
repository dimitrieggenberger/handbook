/**
 * Category accordion on the handbook home: smooth open/close animation for
 * the native <details> drawers, plus an "open all / close all" toggle.
 *
 * Plain JS (no AMD build). Progressive enhancement: without JS the drawers
 * still open and close natively, just without the animation. Reduced-motion
 * users get instant toggles.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var OPENMS = 280;
    var CLOSEMS = 220;

    function init() {
        var drawers = Array.prototype.slice.call(
            document.querySelectorAll('details.local-handbook-cat-acc'));
        if (!drawers.length) {
            return;
        }
        var reduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function body(details) {
            return details.querySelector('.cat-acc-body');
        }

        function finish(el) {
            el.style.transition = '';
            el.style.height = '';
            el.style.overflow = '';
            el.style.opacity = '';
        }

        function setOpen(details, target) {
            var isopen = details.hasAttribute('open');
            if (target === isopen) {
                return;
            }
            var content = body(details);
            if (reduced || !content) {
                if (target) {
                    details.setAttribute('open', '');
                } else {
                    details.removeAttribute('open');
                }
                return;
            }

            if (target) {
                // Open first (so the content has a height), then animate up to it.
                details.setAttribute('open', '');
                var height = content.scrollHeight;
                content.style.overflow = 'hidden';
                content.style.height = '0px';
                content.style.opacity = '0';
                // Two frames so the start state is committed before transitioning.
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        content.style.transition = 'height ' + OPENMS + 'ms ease, opacity '
                            + OPENMS + 'ms ease';
                        content.style.height = height + 'px';
                        content.style.opacity = '1';
                    });
                });
                window.setTimeout(function() {
                    finish(content);
                }, OPENMS + 60);
            } else {
                content.style.overflow = 'hidden';
                content.style.height = content.scrollHeight + 'px';
                content.style.opacity = '1';
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        content.style.transition = 'height ' + CLOSEMS + 'ms ease, opacity '
                            + CLOSEMS + 'ms ease';
                        content.style.height = '0px';
                        content.style.opacity = '0';
                    });
                });
                window.setTimeout(function() {
                    details.removeAttribute('open');
                    finish(content);
                }, CLOSEMS + 40);
            }
        }

        drawers.forEach(function(details) {
            var summary = details.querySelector('summary');
            if (!summary) {
                return;
            }
            summary.addEventListener('click', function(event) {
                event.preventDefault();
                setOpen(details, !details.hasAttribute('open'));
                window.setTimeout(refreshLabel, Math.max(OPENMS, CLOSEMS) + 80);
            });
        });

        // Open all / close all toggle.
        var toggle = document.querySelector('[data-action="handbook-toggleall"]');

        function anyClosed() {
            return drawers.some(function(details) {
                return !details.hasAttribute('open');
            });
        }

        function refreshLabel() {
            if (!toggle) {
                return;
            }
            toggle.textContent = anyClosed()
                ? toggle.getAttribute('data-openlabel')
                : toggle.getAttribute('data-closelabel');
        }

        if (toggle) {
            toggle.addEventListener('click', function() {
                var target = anyClosed();
                drawers.forEach(function(details) {
                    setOpen(details, target);
                });
                window.setTimeout(refreshLabel, Math.max(OPENMS, CLOSEMS) + 80);
            });
            refreshLabel();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
