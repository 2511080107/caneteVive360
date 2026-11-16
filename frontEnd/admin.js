(function () {
    'use strict';

    var UI = window.CaneteUI;
    var API = window.CaneteAPI;

    var resources = {
        distritos: {
            endpoint: 'distritos.php',
            listKey: 'distritos',
            key: 'id_distrito',
            singular: 'distrito',
            plural: 'Distritos',
            label: function (row) { return row.nombre; },
            columns: [
                { key: 'nombre', label: 'Nombre' },
                { key: 'descripcion', label: 'Descripción', truncate: 100 },
                { key: 'total_atractivos', label: 'Atractivos' },
                { key: 'total_servicios', label: 'Servicios' }
            ],
            fields: [
                { name: 'nombre', label: 'Nombre', type: 'text', required: true, maxlength: 80, full: true },
                { name: 'descripcion', label: 'Descripción', type: 'textarea', rows: 4, maxlength: 2000, full: true }
            ]
        },
        categorias: {
            endpoint: 'categorias.php',
            listKey: 'categorias',
            key: 'id_categoria',
            singular: 'categoría',
            plural: 'Categorías',
            label: function (row) { return row.nombre; },
            columns: [
                { key: 'nombre', label: 'Nombre' },
                { key: 'total_atractivos', label: 'Atractivos' }
            ],
            fields: [
                { name: 'nombre', label: 'Nombre', type: 'text', required: true, maxlength: 60, full: true }
            ]
        },
        atractivos: {
            endpoint: 'atractivos.php',
            listKey: 'atractivos',
            key: 'id_atractivo',
            singular: 'atractivo',
            plural: 'Atractivos',
            label: function (row) { return row.nombre; },
            columns: [
                { key: 'nombre', label: 'Nombre' },
                { render: function (row) { return UI.escapeHtml(row.distrito); }, label: 'Distrito' },
                {
                    render: function (row) {
                        return '<span class="badge ' + UI.badgeClass(row.categoria) + '">' + UI.escapeHtml(row.categoria) + '</span>';
                    },
                    label: 'Categoría'
                },
                { key: 'direccion', label: 'Dirección', truncate: 70 },
                {
                    render: function (row) {
                        if (row.latitud === null || row.longitud === null) {
                            return '—';
                        }

                        return UI.escapeHtml(row.latitud + ', ' + row.longitud);
                    },
                    label: 'Coordenadas'
                }
            ],
            fields: [
                { name: 'nombre', label: 'Nombre', type: 'text', required: true, maxlength: 120, full: true },
                { name: 'id_distrito', label: 'Distrito', type: 'select', required: true, source: 'distritos', valueKey: 'id_distrito', labelKey: 'nombre' },
                { name: 'id_categoria', label: 'Categoría', type: 'select', required: true, source: 'categorias', valueKey: 'id_categoria', labelKey: 'nombre' },
                { name: 'descripcion', label: 'Descripción', type: 'textarea', rows: 4, maxlength: 5000, full: true },
                { name: 'direccion', label: 'Dirección', type: 'text', maxlength: 180, full: true },
                { name: 'latitud', label: 'Latitud', type: 'number', step: '0.0000001', min: -90, max: 90 },
                { name: 'longitud', label: 'Longitud', type: 'number', step: '0.0000001', min: -180, max: 180 }
            ]
        },
        servicios: {
            endpoint: 'servicios.php',
            listKey: 'servicios',
            key: 'id_servicio',
            singular: 'servicio',
            plural: 'Servicios',
            label: function (row) { return row.tipo_servicio; },
            columns: [
                { key: 'tipo_servicio', label: 'Gerencia / servicio' },
                { render: function (row) { return UI.escapeHtml(row.distrito || 'Sin distrito'); }, label: 'Distrito' },
                { key: 'telefono', label: 'Teléfono' },
                { key: 'direccion', label: 'Dirección', truncate: 70 },
                { key: 'total_rutas', label: 'Rutas' }
            ],
            fields: [
                { name: 'tipo_servicio', label: 'Gerencia / servicio', type: 'text', required: true, maxlength: 40, full: true },
                { name: 'id_distrito', label: 'Distrito base', type: 'select', nullable: true, source: 'distritos', valueKey: 'id_distrito', labelKey: 'nombre', placeholder: 'Sin distrito' },
                { name: 'telefono', label: 'Teléfono', type: 'tel', maxlength: 20 },
                { name: 'direccion', label: 'Dirección', type: 'text', maxlength: 180, full: true }
            ]
        },
        turistas: {
            endpoint: 'turistas.php',
            listKey: 'turistas',
            key: 'id_turista',
            singular: 'turista',
            plural: 'Turistas',
            label: function (row) { return row.nombres + ' ' + row.apellidos; },
            columns: [
                {
                    render: function (row) {
                        return '<strong>' + UI.escapeHtml(row.nombres + ' ' + row.apellidos) + '</strong>';
                    },
                    label: 'Turista'
                },
                { key: 'correo', label: 'Correo' },
                { key: 'total_rutas', label: 'Rutas' }
            ],
            fields: [
                { name: 'nombres', label: 'Nombres', type: 'text', required: true, maxlength: 100 },
                { name: 'apellidos', label: 'Apellidos', type: 'text', required: true, maxlength: 100 },
                { name: 'correo', label: 'Correo electrónico', type: 'email', required: true, maxlength: 120, full: true }
            ]
        }
    };

    var state = {
        data: {
            distritos: [],
            categorias: [],
            atractivos: [],
            servicios: [],
            turistas: [],
            rutas: []
        },
        modal: null,
        saving: false
    };

    var els = {};

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        cacheElements();
        bindEvents();
        buildShell();
        reloadAll();
    }

    function cacheElements() {
        els.pageAlert = document.getElementById('page-alert');
        els.tabs = document.getElementById('admin-tabs');
        els.panels = document.getElementById('admin-panels');
        els.modal = document.getElementById('modal');
        els.modalTitle = document.getElementById('modal-title');
        els.modalForm = document.getElementById('modal-form');
        els.modalClose = document.getElementById('modal-close');
        els.modalCancel = document.getElementById('modal-cancel');
        els.modalSave = document.getElementById('modal-save');
    }

    function bindEvents() {
        els.tabs.addEventListener('click', function (event) {
            var tab = event.target.closest('.tab[data-tab]');

            if (tab) {
                activateTab(tab.getAttribute('data-tab'));
            }
        });

        els.panels.addEventListener('click', function (event) {
            var newButton = event.target.closest('[data-new]');
            var editButton = event.target.closest('[data-edit]');
            var deleteButton = event.target.closest('[data-delete]');
            var deleteRouteButton = event.target.closest('[data-delete-route]');

            if (newButton) {
                openModal(newButton.getAttribute('data-new'), null);
                return;
            }

            if (editButton) {
                openModal(editButton.getAttribute('data-edit'), Number(editButton.getAttribute('data-id')));
                return;
            }

            if (deleteButton) {
                deleteRecord(deleteButton.getAttribute('data-delete'), Number(deleteButton.getAttribute('data-id')));
                return;
            }

            if (deleteRouteButton) {
                deleteRoute(Number(deleteRouteButton.getAttribute('data-delete-route')));
            }
        });

        els.panels.addEventListener('input', function (event) {
            var search = event.target.closest('[data-search]');

            if (!search) {
                return;
            }

            var key = search.getAttribute('data-search');

            if (key === 'rutas') {
                renderRutas();
            } else {
                renderResource(key);
            }
        });

        els.modalForm.addEventListener('submit', function (event) {
            event.preventDefault();
            saveModal();
        });

        els.modalClose.addEventListener('click', closeModal);
        els.modalCancel.addEventListener('click', closeModal);

        els.modal.addEventListener('click', function (event) {
            if (event.target === els.modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !els.modal.hidden) {
                closeModal();
            }
        });
    }

    function buildShell() {
        var keys = Object.keys(resources);

        els.tabs.innerHTML = keys.map(function (key, index) {
            return '<button type="button" class="tab' + (index === 0 ? ' active' : '') + '" data-tab="' + key + '" role="tab" aria-selected="' + (index === 0 ? 'true' : 'false') + '">' +
                resources[key].plural + '</button>';
        }).join('') + '<button type="button" class="tab" data-tab="rutas" role="tab" aria-selected="false">Rutas</button>';

        els.panels.innerHTML = keys.map(function (key, index) {
            var cfg = resources[key];
            var headers = cfg.columns.map(function (column) {
                return '<th>' + UI.escapeHtml(column.label) + '</th>';
            }).join('');

            return '<div class="tab-panel' + (index === 0 ? ' active' : '') + '" data-panel="' + key + '" role="tabpanel">' +
                '<div class="toolbar">' +
                    '<input class="grow" type="search" data-search="' + key + '" placeholder="Buscar en ' + UI.escapeHtml(cfg.plural.toLowerCase()) + '">' +
                    '<button type="button" class="btn btn-primary" data-new="' + key + '">Nuevo ' + UI.escapeHtml(cfg.singular) + '</button>' +
                '</div>' +
                '<div class="table-wrap">' +
                    '<table>' +
                        '<thead><tr>' + headers + '<th>Acciones</th></tr></thead>' +
                        '<tbody data-body="' + key + '"></tbody>' +
                    '</table>' +
                '</div>' +
                '<div class="empty" data-empty="' + key + '" hidden>No hay registros para mostrar.</div>' +
            '</div>';
        }).join('') +
            '<div class="tab-panel" data-panel="rutas" role="tabpanel">' +
                '<div class="toolbar">' +
                    '<input class="grow" type="search" data-search="rutas" placeholder="Buscar rutas por turista, correo o gerencia">' +
                    '<a class="btn btn-ghost" href="gerencias.html">Ver gerencias</a>' +
                '</div>' +
                '<div class="table-wrap">' +
                    '<table>' +
                        '<thead><tr>' +
                            '<th>Ruta</th><th>Turista</th><th>Gerencia</th><th>Atractivos</th>' +
                            '<th>Duración</th><th>Fecha visita</th><th>Acciones</th>' +
                        '</tr></thead>' +
                        '<tbody data-body="rutas"></tbody>' +
                    '</table>' +
                '</div>' +
                '<div class="empty" data-empty="rutas" hidden>No hay rutas registradas.</div>' +
            '</div>';
    }

    function activateTab(key) {
        els.tabs.querySelectorAll('.tab').forEach(function (tab) {
            var active = tab.getAttribute('data-tab') === key;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        els.panels.querySelectorAll('.tab-panel').forEach(function (panel) {
            panel.classList.toggle('active', panel.getAttribute('data-panel') === key);
        });
    }

    function reloadAll() {
        return loadAll().then(function () {
            Object.keys(resources).forEach(renderResource);
            renderRutas();
            UI.setAlert(els.pageAlert, '');
        }).catch(function (error) {
            var message = 'No se pudieron cargar los datos. Verifica que el backend PHP y MySQL estén activos. ' + error.message;
            UI.setAlert(els.pageAlert, message, 'error');
            UI.toast(message, 'error');
        });
    }

    function loadAll() {
        var tasks = Object.keys(resources).map(function (key) {
            var cfg = resources[key];

            return API.get(cfg.endpoint).then(function (data) {
                state.data[key] = data[cfg.listKey] || [];
            });
        });

        tasks.push(API.get('rutas.php').then(function (data) {
            state.data.rutas = data.rutas || [];
        }));

        return Promise.all(tasks);
    }

    function renderResource(key) {
        var cfg = resources[key];
        var search = document.querySelector('[data-search="' + key + '"]');
        var query = UI.normalize(search ? search.value : '');
        var rows = state.data[key] || [];
        var body = document.querySelector('[data-body="' + key + '"]');
        var empty = document.querySelector('[data-empty="' + key + '"]');

        if (!body) {
            return;
        }

        var filtered = query
            ? rows.filter(function (row) {
                return UI.normalize(JSON.stringify(row)).indexOf(query) !== -1;
            })
            : rows;

        body.innerHTML = filtered.map(function (row) {
            var cells = cfg.columns.map(function (column) {
                return '<td>' + renderColumn(column, row) + '</td>';
            }).join('');

            var id = Number(row[cfg.key]);

            return '<tr>' + cells +
                '<td><div class="table-actions">' +
                    '<button type="button" class="btn btn-small btn-ghost" data-edit="' + key + '" data-id="' + id + '">Editar</button>' +
                    '<button type="button" class="btn btn-small btn-danger" data-delete="' + key + '" data-id="' + id + '">Eliminar</button>' +
                '</div></td>' +
            '</tr>';
        }).join('');

        var isEmpty = filtered.length === 0;
        if (empty) {
            empty.hidden = !isEmpty;
            empty.textContent = rows.length === 0
                ? 'No hay registros cargados todavía.'
                : 'No hay coincidencias con la búsqueda.';
        }
    }

    function renderColumn(column, row) {
        if (column.render) {
            return column.render(row);
        }

        var value = row[column.key];

        if (value === null || value === undefined || value === '') {
            return '—';
        }

        if (column.truncate) {
            return '<span title="' + UI.escapeHtml(value) + '">' + UI.escapeHtml(UI.truncate(value, column.truncate)) + '</span>';
        }

        return UI.escapeHtml(value);
    }

    function findRow(key, id) {
        return (state.data[key] || []).find(function (row) {
            return Number(row[resources[key].key]) === Number(id);
        });
    }

    function openModal(key, id) {
        var cfg = resources[key];
        var row = id ? findRow(key, id) : null;

        if (id && !row) {
            UI.toast('No se encontró el registro solicitado.', 'error');
            return;
        }

        state.modal = { key: key, id: id, row: row };

        els.modalTitle.textContent = row
            ? 'Editar ' + cfg.singular + ' #' + id
            : 'Nuevo ' + cfg.singular;

        els.modalForm.innerHTML = cfg.fields.map(function (field) {
            return buildField(field, row);
        }).join('');

        els.modal.hidden = false;
        els.modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        var firstInput = els.modalForm.querySelector('input, select, textarea');

        if (firstInput) {
            firstInput.focus();
        }
    }

    function buildField(field, row) {
        var id = 'field-' + field.name;
        var value = row && row[field.name] !== null && row[field.name] !== undefined
            ? row[field.name]
            : (field.default !== undefined ? field.default : '');
        var required = field.required ? ' required' : '';
        var attributes = '';

        if (field.maxlength) {
            attributes += ' maxlength="' + field.maxlength + '"';
        }

        if (field.type === 'number') {
            attributes += ' step="' + (field.step || 'any') + '"';

            if (field.min !== undefined) {
                attributes += ' min="' + field.min + '"';
            }

            if (field.max !== undefined) {
                attributes += ' max="' + field.max + '"';
            }
        }

        var control;

        if (field.type === 'textarea') {
            control = '<textarea id="' + id + '" name="' + field.name + '" rows="' + (field.rows || 3) + '"' +
                attributes + required + '>' + UI.escapeHtml(value) + '</textarea>';
        } else if (field.type === 'select') {
            var options = (state.data[field.source] || []).map(function (item) {
                return '<option value="' + UI.escapeHtml(item[field.valueKey]) + '"' +
                    (String(item[field.valueKey]) === String(value) ? ' selected' : '') + '>' +
                    UI.escapeHtml(item[field.labelKey]) + '</option>';
            }).join('');

            control = '<select id="' + id + '" name="' + field.name + '"' + required + '>' +
                ((!field.required || field.nullable)
                    ? '<option value="">' + UI.escapeHtml(field.placeholder || 'Sin seleccionar') + '</option>'
                    : '') +
                options +
            '</select>';
        } else {
            control = '<input id="' + id + '" name="' + field.name + '" type="' + (field.type || 'text') + '" value="' +
                UI.escapeHtml(value) + '"' + attributes + required + '>';
        }

        return '<div class="field' + (field.full ? ' full' : '') + '">' +
            '<label for="' + id + '">' + UI.escapeHtml(field.label) + '</label>' +
            control +
        '</div>';
    }

    function closeModal() {
        if (state.saving) {
            return;
        }

        els.modal.hidden = true;
        els.modal.setAttribute('aria-hidden', 'true');
        els.modalForm.innerHTML = '';
        state.modal = null;
        document.body.style.overflow = '';
    }

    function saveModal() {
        if (!state.modal || state.saving) {
            return;
        }

        if (!els.modalForm.checkValidity()) {
            els.modalForm.reportValidity();
            return;
        }

        var cfg = resources[state.modal.key];
        var payload = {};

        cfg.fields.forEach(function (field) {
            var input = els.modalForm.elements[field.name];
            var value = input.value.trim();

            if (field.type === 'number') {
                payload[field.name] = value === '' ? null : Number(value);
            } else {
                payload[field.name] = value === '' ? null : value;
            }
        });

        if (state.modal.id) {
            payload[cfg.key] = state.modal.id;
        }

        state.saving = true;
        els.modalSave.disabled = true;
        els.modalSave.textContent = 'Guardando...';

        var request = state.modal.id
            ? API.put(cfg.endpoint, payload)
            : API.post(cfg.endpoint, payload);

        request.then(function () {
            UI.toast(state.modal.id ? 'Registro actualizado correctamente.' : 'Registro creado correctamente.', 'success');
            state.saving = false;
            els.modalSave.disabled = false;
            els.modalSave.textContent = 'Guardar';
            closeModal();
            reloadAll();
        }).catch(function (error) {
            state.saving = false;
            els.modalSave.disabled = false;
            els.modalSave.textContent = 'Guardar';
            UI.toast(error.message, 'error');
        });
    }

    function deleteRecord(key, id) {
        var cfg = resources[key];
        var row = findRow(key, id);

        if (!row) {
            return;
        }

        var label = cfg.label ? cfg.label(row) : '#' + id;

        if (!window.confirm('¿Eliminar ' + cfg.singular + ' "' + label + '"? Esta acción no se puede deshacer.')) {
            return;
        }

        API.del(cfg.endpoint, id).then(function (response) {
            UI.toast(response.mensaje || 'Registro eliminado.', 'success');
            reloadAll();
        }).catch(function (error) {
            UI.toast(error.message, 'error');
        });
    }

    function renderRutas() {
        var search = document.querySelector('[data-search="rutas"]');
        var query = UI.normalize(search ? search.value : '');
        var body = document.querySelector('[data-body="rutas"]');
        var empty = document.querySelector('[data-empty="rutas"]');

        if (!body) {
            return;
        }

        var rows = state.data.rutas || [];
        var filtered = query
            ? rows.filter(function (row) {
                return UI.normalize(JSON.stringify(row)).indexOf(query) !== -1;
            })
            : rows;

        body.innerHTML = filtered.map(function (ruta) {
            return '<tr>' +
                '<td><strong>#' + ruta.id_ruta + '</strong><br><span class="text-small muted">' + UI.escapeHtml(ruta.nombre) + '</span></td>' +
                '<td><strong>' + UI.escapeHtml(ruta.turista.nombres + ' ' + ruta.turista.apellidos) + '</strong><br>' +
                    '<span class="text-small muted">' + UI.escapeHtml(ruta.turista.correo) + '</span></td>' +
                '<td>' + UI.escapeHtml(ruta.servicio.tipo_servicio) + '</td>' +
                '<td>' + ruta.total_atractivos + '</td>' +
                '<td>' + UI.formatMinutes(ruta.total_minutos) + '</td>' +
                '<td>' + UI.escapeHtml(UI.formatDateOnly(ruta.fecha_visita)) + '</td>' +
                '<td><div class="table-actions">' +
                    '<a class="btn btn-small btn-ghost" href="gerencias.html">Gerencias</a>' +
                    '<button type="button" class="btn btn-small btn-danger" data-delete-route="' + ruta.id_ruta + '">Eliminar</button>' +
                '</div></td>' +
            '</tr>';
        }).join('');

        var isEmpty = filtered.length === 0;
        if (empty) {
            empty.hidden = !isEmpty;
            empty.textContent = rows.length === 0
                ? 'No hay rutas registradas todavía.'
                : 'No hay coincidencias con la búsqueda.';
        }
    }

    function deleteRoute(id) {
        if (!window.confirm('¿Eliminar la ruta #' + id + ' y todo su detalle de atractivos? Esta acción no se puede deshacer.')) {
            return;
        }

        API.del('rutas.php', id).then(function (response) {
            UI.toast(response.mensaje || 'Ruta eliminada.', 'success');
            reloadAll();
        }).catch(function (error) {
            UI.toast(error.message, 'error');
        });
    }
})();
