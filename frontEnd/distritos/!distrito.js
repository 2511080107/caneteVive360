(function () {
    'use strict';

    var triggers = document.querySelectorAll('[data-registro]');
    if (!triggers.length) {
        return;
    }

    function openPanel(trigger) {
        var distrito = trigger.getAttribute('data-distrito') || '';
        var query = distrito ? '?' + new URLSearchParams({ distrito: distrito }).toString() : '';
        window.location.href = '../panel-reserva.html' + query;
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            openPanel(trigger);
        });
    });
})();
