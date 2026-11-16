(function () {
    'use strict';

    var accentPattern = /[\u0300-\u036f]/g;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[character];
        });
    }

    function normalize(value) {
        return String(value === null || value === undefined ? '' : value)
            .normalize('NFD')
            .replace(accentPattern, '')
            .toLowerCase()
            .trim();
    }

    function badgeClass(categoria) {
        var name = normalize(categoria);

        if (name.indexOf('aventura') !== -1) {
            return 'badge-aventura';
        }

        if (name.indexOf('gastronom') !== -1) {
            return 'badge-gastronomia';
        }

        if (name.indexOf('cultur') !== -1) {
            return 'badge-cultura';
        }

        return 'badge-neutral';
    }

    function formatMinutes(total) {
        total = Math.max(0, Number(total) || 0);

        var hours = Math.floor(total / 60);
        var minutes = total % 60;

        if (hours && minutes) {
            return hours + ' h ' + minutes + ' min';
        }

        if (hours) {
            return hours + ' h';
        }

        return minutes + ' min';
    }

    function formatDate(value) {
        if (!value) {
            return 'Sin fecha';
        }

        var date = new Date(String(value).replace(' ', 'T'));

        if (isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleDateString('es-PE', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        }) + ' · ' + date.toLocaleTimeString('es-PE', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatDateOnly(value) {
        if (!value) {
            return 'Sin fecha';
        }

        var match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));

        if (!match) {
            return formatDate(value);
        }

        var date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

        return date.toLocaleDateString('es-PE', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function truncate(value, max) {
        var text = String(value === null || value === undefined ? '' : value);
        max = max || 90;

        if (text.length <= max) {
            return text;
        }

        return text.slice(0, max - 1).trim() + '…';
    }

    function debounce(fn, wait) {
        var timer = null;

        return function () {
            var context = this;
            var args = arguments;

            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, wait);
        };
    }

    function toast(message, type) {
        var stack = document.querySelector('.toast-stack');

        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            stack.setAttribute('role', 'status');
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }

        var item = document.createElement('div');
        item.className = 'toast ' + (type === 'error' ? 'error' : type === 'success' ? 'success' : '');
        item.textContent = message;
        stack.appendChild(item);

        setTimeout(function () {
            item.style.opacity = '0';
            item.style.transform = 'translateY(6px)';

            setTimeout(function () {
                item.remove();
            }, 250);
        }, 4200);
    }

    function setAlert(element, message, type) {
        if (!element) {
            return;
        }

        if (!message) {
            element.hidden = true;
            element.textContent = '';
            return;
        }

        element.hidden = false;
        element.textContent = message;
        element.className = 'alert ' + (type === 'info' ? 'alert-info' : 'alert-error');
    }

    window.CaneteUI = {
        escapeHtml: escapeHtml,
        normalize: normalize,
        badgeClass: badgeClass,
        formatMinutes: formatMinutes,
        formatDate: formatDate,
        formatDateOnly: formatDateOnly,
        truncate: truncate,
        debounce: debounce,
        toast: toast,
        setAlert: setAlert
    };
})();
