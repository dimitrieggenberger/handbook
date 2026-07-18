/**
 * Content accordions (hb-acc) inside articles: template libraries and other
 * long list pages author plain KSES-safe markup (.hb-acc > .acc-title +
 * .acc-body); this script adds the behaviour.
 *
 * Progressive enhancement, plain JS (no AMD build). Without JS — and in the
 * print view, which never loads this file — every drawer simply renders
 * open, so no content is ever unreachable. With JS, drawers start collapsed
 * (unless the author wrote is-open), the title toggles by click or
 * Enter/Space, and every .hb-acc-group with two or more items gets an
 * "expand all / collapse all" control injected above it.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    function labels() {
        var carrier = document.querySelector('[data-region="local-handbook-accstrings"]');
        return {
            expand: (carrier && carrier.getAttribute('data-expand')) || 'Expand all',
            collapse: (carrier && carrier.getAttribute('data-collapse')) || 'Collapse all'
        };
    }

    function enhance(acc, index) {
        var title = acc.querySelector('.acc-title');
        var body = acc.querySelector('.acc-body');
        if (!title || !body || acc.classList.contains('js-ready')) {
            return false;
        }

        // Wrap the drawer contents so the grid-rows height animation has a
        // single clipping child (authors never write these wrappers).
        var pad = document.createElement('div');
        pad.className = 'acc-pad';
        while (body.firstChild) {
            pad.appendChild(body.firstChild);
        }
        var inner = document.createElement('div');
        inner.className = 'acc-inner';
        inner.appendChild(pad);
        body.appendChild(inner);

        if (!body.id) {
            body.id = 'hb-acc-body-' + index;
        }
        title.setAttribute('role', 'button');
        title.setAttribute('tabindex', '0');
        title.setAttribute('aria-controls', body.id);

        var sync = function() {
            title.setAttribute('aria-expanded', acc.classList.contains('is-open') ? 'true' : 'false');
        };

        acc.classList.add('js-ready');
        sync();

        var toggle = function() {
            acc.classList.toggle('is-open');
            sync();
            refreshTools(acc.closest('.hb-acc-group'));
        };
        title.addEventListener('click', toggle);
        title.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                event.preventDefault();
                toggle();
            }
        });
        return true;
    }

    function refreshTools(group) {
        if (!group) {
            return;
        }
        var button = group.querySelector('.hb-acc-tools button');
        if (!button) {
            return;
        }
        var accs = group.querySelectorAll('.hb-acc');
        var open = group.querySelectorAll('.hb-acc.is-open');
        var text = labels();
        button.textContent = open.length === accs.length ? text.collapse : text.expand;
    }

    function addTools(group) {
        var accs = Array.prototype.slice.call(group.querySelectorAll('.hb-acc.js-ready'));
        if (accs.length < 2 || group.querySelector('.hb-acc-tools')) {
            return;
        }
        var button = document.createElement('button');
        button.type = 'button';
        button.addEventListener('click', function() {
            var expand = group.querySelectorAll('.hb-acc.is-open').length !== accs.length;
            accs.forEach(function(acc) {
                acc.classList.toggle('is-open', expand);
                var title = acc.querySelector('.acc-title');
                if (title) {
                    title.setAttribute('aria-expanded', expand ? 'true' : 'false');
                }
            });
            refreshTools(group);
        });
        var tools = document.createElement('div');
        tools.className = 'hb-acc-tools';
        tools.appendChild(button);
        group.insertBefore(tools, group.firstChild);
        refreshTools(group);
    }

    function init() {
        var accs = Array.prototype.slice.call(
            document.querySelectorAll('.local-handbook-page-body .hb-acc'));
        if (!accs.length) {
            return;
        }
        accs.forEach(enhance);
        Array.prototype.slice.call(
            document.querySelectorAll('.local-handbook-page-body .hb-acc-group')
        ).forEach(addTools);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
