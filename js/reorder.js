/**
 * Drag-and-drop for the category reorder list (category.php?reorder=1).
 *
 * Plain JS, no AMD. Rows with .reorder-row become draggable; dropping
 * saves the full order via a background POST (action=saveorder). The
 * up/down arrow links remain the no-JS and touch fallback, and the
 * pinned featured row (.is-featured-row) is never draggable — it always
 * stays first.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    function init() {
        var list = document.querySelector('[data-region="handbook-reorder"]');
        if (!list) {
            return;
        }
        var dragging = null;

        function rows() {
            return Array.prototype.slice.call(list.querySelectorAll('.reorder-row'));
        }

        function rowAfter(y) {
            var candidates = rows().filter(function(r) {
                return r !== dragging;
            });
            for (var i = 0; i < candidates.length; i++) {
                var box = candidates[i].getBoundingClientRect();
                if (y < box.top + box.height / 2) {
                    return candidates[i];
                }
            }
            return null;
        }

        function renumber() {
            var offset = list.querySelectorAll('.is-featured-row').length;
            rows().forEach(function(row, i) {
                var badge = row.querySelector('.reorder-num');
                if (badge) {
                    badge.textContent = String(offset + i + 1);
                }
            });
        }

        function save() {
            var params = new URLSearchParams();
            params.set('action', 'saveorder');
            params.set('order', rows().map(function(r) {
                return r.getAttribute('data-pageid');
            }).join(','));
            params.set('sesskey', list.getAttribute('data-sesskey'));
            params.set('ajax', '1');
            fetch(list.getAttribute('data-action-url'), {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params.toString(),
                credentials: 'same-origin'
            }).then(function(resp) {
                if (!resp.ok) {
                    throw new Error('saveorder failed: ' + resp.status);
                }
                return resp.json();
            }).catch(function() {
                // Persisting failed (session expired, network) — reload so
                // the visible order never lies about what is stored.
                window.location.reload();
            });
        }

        rows().forEach(function(row) {
            row.setAttribute('draggable', 'true');
            row.addEventListener('dragstart', function(e) {
                dragging = row;
                row.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
                try {
                    e.dataTransfer.setData('text/plain', row.getAttribute('data-pageid'));
                } catch (ex) {
                    // IE11 quirk; harmless.
                }
            });
            row.addEventListener('dragend', function() {
                if (!dragging) {
                    return;
                }
                dragging.classList.remove('is-dragging');
                dragging = null;
                renumber();
                save();
            });
        });

        list.addEventListener('dragover', function(e) {
            if (!dragging) {
                return;
            }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var after = rowAfter(e.clientY);
            if (after === null) {
                list.appendChild(dragging);
            } else if (after !== dragging) {
                list.insertBefore(dragging, after);
            }
        });
        list.addEventListener('drop', function(e) {
            e.preventDefault();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
