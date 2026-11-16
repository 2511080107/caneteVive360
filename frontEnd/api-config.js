(function () {
    'use strict';

    var script = document.currentScript;
    var base = '../backEnd/api';

    try {
        if (script && script.src) {
            base = new URL('../backEnd/api/', script.src).href.replace(/\/$/, '');
        }
    } catch (error) {
        base = '../backEnd/api';
    }

    function parseResponse(response) {
        return response.text().then(function (text) {
            var data = null;

            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (error) {
                    data = null;
                }
            }

            if (!response.ok) {
                var message = (data && (data.error || data.mensaje)) || ('Error ' + response.status + ' al contactar el servidor.');
                var failure = new Error(message);
                failure.status = response.status;
                failure.data = data;
                throw failure;
            }

            return data;
        });
    }

    function buildUrl(path, params) {
        var url = new URL(base + '/' + String(path).replace(/^\/+/, ''));

        if (params) {
            Object.keys(params).forEach(function (key) {
                var value = params[key];

                if (value !== '' && value !== null && value !== undefined) {
                    url.searchParams.set(key, value);
                }
            });
        }

        return url.toString();
    }

    window.CANETE360_API_BASE = base;
    window.CANETE360_REGISTRO_ENDPOINT = base + '/rutas.php';

    window.CaneteAPI = {
        base: base,

        get: function (path, params) {
            return fetch(buildUrl(path, params), {
                headers: { Accept: 'application/json' }
            }).then(parseResponse);
        },

        post: function (path, body) {
            return fetch(buildUrl(path), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8',
                    Accept: 'application/json'
                },
                body: JSON.stringify(body)
            }).then(parseResponse);
        },

        put: function (path, body) {
            return fetch(buildUrl(path), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8',
                    Accept: 'application/json'
                },
                body: JSON.stringify(body)
            }).then(parseResponse);
        },

        del: function (path, id) {
            return fetch(buildUrl(path, { id: id }), {
                method: 'DELETE',
                headers: { Accept: 'application/json' }
            }).then(parseResponse);
        }
    };
})();
