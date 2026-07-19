/**
 * End-of-article comprehension test: tap-in-sequence ordering questions and
 * the answered-everything submit gate.
 *
 * Plain JS (no AMD build), progressive enhancement. Multichoice works
 * without JS (native radios + required); ordering needs JS, so without it
 * the server treats an empty sequence as incomplete and re-asks.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    function initOrdering(region, onchange) {
        var input = region.querySelector('input[type="hidden"]');
        var steps = Array.prototype.slice.call(region.querySelectorAll('.quiz-step'));
        var sequence = [];

        function sync() {
            input.value = sequence.join(',');
            steps.forEach(function(step) {
                var id = parseInt(step.getAttribute('data-optionid'), 10);
                var index = sequence.indexOf(id);
                step.classList.toggle('is-picked', index >= 0);
                var badge = step.querySelector('.seq');
                if (badge) {
                    badge.textContent = index >= 0 ? String(index + 1) : '·';
                }
            });
            onchange();
        }

        steps.forEach(function(step) {
            var toggle = function() {
                var id = parseInt(step.getAttribute('data-optionid'), 10);
                var index = sequence.indexOf(id);
                if (index >= 0) {
                    sequence.splice(index, 1);
                } else {
                    sequence.push(id);
                }
                sync();
            };
            step.addEventListener('click', toggle);
            step.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                    event.preventDefault();
                    toggle();
                }
            });
        });

        var reset = region.querySelector('[data-action="reset"]');
        if (reset) {
            reset.addEventListener('click', function(event) {
                event.preventDefault();
                sequence = [];
                sync();
            });
        }

        return function complete() {
            return sequence.length === steps.length;
        };
    }

    function init() {
        Array.prototype.slice.call(
            document.querySelectorAll('form[data-region="hb-quiz"]')
        ).forEach(function(form) {
            var submit = form.querySelector('button[type="submit"]');
            var radios = Array.prototype.slice.call(
                form.querySelectorAll('input[type="radio"]'));
            var radionames = radios.map(function(radio) {
                return radio.name;
            }).filter(function(name, index, all) {
                return all.indexOf(name) === index;
            });
            var orderingchecks = [];

            function update() {
                if (!submit) {
                    return;
                }
                var ready = radionames.every(function(name) {
                    return form.querySelector('input[name="' + name + '"]:checked') !== null;
                }) && orderingchecks.every(function(check) {
                    return check();
                });
                submit.disabled = !ready;
            }

            Array.prototype.slice.call(
                form.querySelectorAll('[data-region="hb-ordering"]')
            ).forEach(function(region) {
                orderingchecks.push(initOrdering(region, update));
            });

            radios.forEach(function(radio) {
                radio.addEventListener('change', update);
            });

            // Only gate when there is something to answer (result views have
            // no inputs and their button is the retry link).
            if (radios.length || orderingchecks.length) {
                update();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
