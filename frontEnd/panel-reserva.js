(function () {
    'use strict';

    var UI = window.CaneteUI;
    var API = window.CaneteAPI;

    var state = {
        distritos: [],
        categorias: [],
        atractivos: [],
        servicios: [],
        selected: [],
        filtros: { distrito: '', categoria: '', q: '' },
        busy: false
    };

    var els = {};
    var namePattern = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ' .-]+$/;
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        cacheElements();
        if (!els.form) {
            return;
        }

        bindEvents();
        setDateBounds();
        loadCatalogos();
    }

    function cacheElements() {
        els.form = document.getElementById('tourist-form');
        els.pageAlert = document.getElementById('page-alert');
        els.bookingLayout = document.getElementById('booking-layout');
        els.nombres = document.getElementById('nombres');
        els.apellidos = document.getElementById('apellidos');
        els.correo = document.getElementById('correo');
        els.servicio = document.getElementById('servicio');
        els.servicioDetalle = document.getElementById('servicio-detalle');
        els.nombreRuta = document.getElementById('nombre-ruta');
        els.fechaVisita = document.getElementById('fecha-visita');
        els.filtroDistrito = document.getElementById('filtro-distrito');
        els.filtroCategoria = document.getElementById('filtro-categoria');
        els.filtroQ = document.getElementById('filtro-q');
        els.atractivosLoading = document.getElementById('atractivos-loading');
        els.atractivosList = document.getElementById('atractivos-list');
        els.atractivosEmpty = document.getElementById('atractivos-empty');
        els.atractivosCount = document.getElementById('atractivos-count');
        els.selectedEmpty = document.getElementById('selected-empty');
        els.selectedList = document.getElementById('selected-list');
        els.selectionHint = document.getElementById('selection-hint');
        els.selectionTotal = document.getElementById('selection-total');
        els.sumTourist = document.getElementById('summary-tourist');
        els.sumAtractivos = document.getElementById('sum-atractivos');
        els.sumDistritos = document.getElementById('sum-distritos');
        els.sumCategorias = document.getElementById('sum-categorias');
        els.sumTiempo = document.getElementById('sum-tiempo');
        els.sumServicio = document.getElementById('sum-servicio');
        els.sumNombre = document.getElementById('sum-nombre');
        els.sumFecha = document.getElementById('sum-fecha');
        els.sumMini = document.getElementById('summary-mini');
        els.sumHoras = document.getElementById('sum-horas');
        els.summaryHint = document.getElementById('summary-hint');
        els.btnSubmit = document.getElementById('btn-submit');
        els.successPanel = document.getElementById('success-panel');
        els.successMessage = document.getElementById('success-message');
        els.successSummary = document.getElementById('success-summary');
        els.successLink = document.getElementById('success-link');
        els.successAgain = document.getElementById('success-again');
    }

    function bindEvents() {
        els.filtroDistrito.addEventListener('change', function () {
            state.filtros.distrito = els.filtroDistrito.value;
            renderAtractivos();
        });

        els.filtroCategoria.addEventListener('change', function () {
            state.filtros.categoria = els.filtroCategoria.value;
            renderAtractivos();
        });

        els.filtroQ.addEventListener('input', UI.debounce(function () {
            state.filtros.q = els.filtroQ.value;
            renderAtractivos();
        }, 180));

        els.servicio.addEventListener('change', function () {
            setFieldError('servicio', '');
            renderServiceDetail();
            updateSummary();
        });

        [els.nombres, els.apellidos, els.correo, els.nombreRuta, els.fechaVisita].forEach(function (input) {
            input.addEventListener('input', function () {
                if (input.id) {
                    setFieldError(input.id, '');
                }
                updateSummary();
            });
        });

        els.atractivosList.addEventListener('click', function (event) {
            var button = event.target.closest('[data-add]');

            if (button) {
                addAtractivo(Number(button.getAttribute('data-add')));
            }
        });

        els.selectedList.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-remove]');
            var move = event.target.closest('[data-move]');

            if (remove) {
                removeAtractivo(Number(remove.getAttribute('data-remove')));
                return;
            }

            if (move) {
                moverAtractivo(Number(move.getAttribute('data-id')), move.getAttribute('data-move'));
            }
        });

        els.selectedList.addEventListener('input', function (event) {
            var input = event.target.closest('[data-time]');

            if (!input) {
                return;
            }

            var item = findSelected(Number(input.getAttribute('data-time')));

            if (!item) {
                return;
            }

            var value = parseInt(input.value, 10);
            item.tiempo_estadia_min = isNaN(value) ? 0 : value;
            input.removeAttribute('aria-invalid');
            updateSummary();
        });

        els.selectedList.addEventListener('change', function (event) {
            var input = event.target.closest('[data-time]');

            if (!input) {
                return;
            }

            var value = parseInt(input.value, 10);

            if (isNaN(value) || value < 15) {
                value = 15;
            }

            if (value > 1440) {
                value = 1440;
            }

            input.value = String(value);

            var item = findSelected(Number(input.getAttribute('data-time')));

            if (item) {
                item.tiempo_estadia_min = value;
            }

            updateSummary();
        });

        els.btnSubmit.addEventListener('click', submit);
        els.form.addEventListener('submit', function (event) {
            event.preventDefault();
            submit();
        });

        els.successAgain.addEventListener('click', resetForNewRoute);
    }

    function loadCatalogos() {
        Promise.all([
            API.get('distritos.php'),
            API.get('categorias.php'),
            API.get('atractivos.php'),
            API.get('servicios.php')
        ]).then(function (results) {
            state.distritos = results[0].distritos;
            state.categorias = results[1].categorias;
            state.atractivos = results[2].atractivos;
            state.servicios = results[3].servicios;

            populateDistritos();
            populateCategorias();
            populateServicios();
            applyQueryPreset();

            els.atractivosLoading.hidden = true;
            renderAtractivos();
            renderSelected();
            renderServiceDetail();
            updateSummary();
        }).catch(function (error) {
            els.atractivosLoading.textContent = 'No se pudieron cargar los atractivos.';
            UI.setAlert(
                els.pageAlert,
                'No se pudo conectar con el API de Cañete 360. Verifica que el backend PHP y MySQL estén activos. Detalle: ' + error.message,
                'error'
            );
            els.servicio.innerHTML = '<option value="">Servicio no disponible</option>';
        });
    }

    function populateDistritos() {
        state.distritos.forEach(function (distrito) {
            var option = document.createElement('option');
            option.value = String(distrito.id_distrito);
            option.textContent = distrito.nombre;
            els.filtroDistrito.appendChild(option);
        });
    }

    function populateCategorias() {
        state.categorias.forEach(function (categoria) {
            var option = document.createElement('option');
            option.value = String(categoria.id_categoria);
            option.textContent = categoria.nombre;
            els.filtroCategoria.appendChild(option);
        });
    }

    function populateServicios() {
        els.servicio.innerHTML = '<option value="">Selecciona una gerencia</option>';

        state.servicios.forEach(function (servicio) {
            var option = document.createElement('option');
            option.value = String(servicio.id_servicio);
            option.textContent = servicio.tipo_servicio + (servicio.distrito ? ' · ' + servicio.distrito : '');
            els.servicio.appendChild(option);
        });
    }

    function applyQueryPreset() {
        var params = new URLSearchParams(window.location.search);
        var distrito = params.get('distrito');

        if (!distrito) {
            return;
        }

        var target = UI.normalize(distrito);
        var match = state.distritos.find(function (item) {
            return UI.normalize(item.nombre) === target;
        });

        if (match) {
            els.filtroDistrito.value = String(match.id_distrito);
            state.filtros.distrito = String(match.id_distrito);
        }
    }

    function filteredAtractivos() {
        var q = UI.normalize(state.filtros.q);

        return state.atractivos.filter(function (atractivo) {
            if (state.filtros.distrito && String(atractivo.id_distrito) !== state.filtros.distrito) {
                return false;
            }

            if (state.filtros.categoria && String(atractivo.id_categoria) !== state.filtros.categoria) {
                return false;
            }

            if (q) {
                var text = UI.normalize([
                    atractivo.nombre,
                    atractivo.descripcion,
                    atractivo.direccion,
                    atractivo.distrito,
                    atractivo.categoria
                ].join(' '));

                if (text.indexOf(q) === -1) {
                    return false;
                }
            }

            return true;
        });
    }

    function renderAtractivos() {
        if (!els.atractivosList) {
            return;
        }

        var lista = filteredAtractivos();
        var limite = state.selected.length >= 5;

        els.atractivosEmpty.hidden = lista.length > 0;
        els.atractivosList.hidden = lista.length === 0;

        els.atractivosList.innerHTML = lista.map(function (atractivo) {
            var selected = Boolean(findSelected(atractivo.id_atractivo));
            var disabled = selected || limite;
            var label = selected ? 'Añadido' : limite ? 'Máximo 5' : 'Añadir';

            return '<article class="atractivo-card' + (selected ? ' selected' : '') + '">' +
                '<div class="chip-row">' +
                    '<span class="badge ' + UI.badgeClass(atractivo.categoria) + '">' + UI.escapeHtml(atractivo.categoria) + '</span>' +
                    '<span class="badge badge-neutral">' + UI.escapeHtml(atractivo.distrito) + '</span>' +
                '</div>' +
                '<h4>' + UI.escapeHtml(atractivo.nombre) + '</h4>' +
                '<p>' + UI.escapeHtml(atractivo.descripcion || 'Sin descripción registrada.') + '</p>' +
                '<span class="meta">' + UI.escapeHtml(atractivo.direccion || 'Dirección no registrada') + '</span>' +
                '<div class="actions">' +
                    '<button type="button" class="btn btn-small ' + (selected ? 'btn-ghost' : 'btn-primary') + '" data-add="' + atractivo.id_atractivo + '"' + (disabled ? ' disabled' : '') + '>' + label + '</button>' +
                '</div>' +
            '</article>';
        }).join('');
    }

    function renderSelected() {
        var count = state.selected.length;

        els.selectedEmpty.hidden = count > 0;
        els.selectedList.hidden = count === 0;

        els.selectedList.innerHTML = state.selected.map(function (item, index) {
            var atractivo = item.atractivo;
            var upDisabled = index === 0 ? ' disabled' : '';
            var downDisabled = index === count - 1 ? ' disabled' : '';

            return '<div class="route-item" data-id="' + atractivo.id_atractivo + '">' +
                '<span class="route-index" aria-hidden="true">' + (index + 1) + '</span>' +
                '<div class="route-info">' +
                    '<h4>' + UI.escapeHtml(atractivo.nombre) + '</h4>' +
                    '<div class="meta">' +
                        '<span class="badge ' + UI.badgeClass(atractivo.categoria) + '">' + UI.escapeHtml(atractivo.categoria) + '</span>' +
                        '<span>' + UI.escapeHtml(atractivo.distrito) + '</span>' +
                    '</div>' +
                '</div>' +
                '<label class="time-box">Min ' +
                    '<input type="number" min="15" max="1440" step="15" value="' + item.tiempo_estadia_min + '" data-time="' + atractivo.id_atractivo + '" aria-label="Minutos en ' + UI.escapeHtml(atractivo.nombre) + '">' +
                '</label>' +
                '<div class="route-actions">' +
                    '<button type="button" class="btn btn-icon btn-ghost" data-move="up" data-id="' + atractivo.id_atractivo + '" title="Subir"' + upDisabled + '>&uarr;</button>' +
                    '<button type="button" class="btn btn-icon btn-ghost" data-move="down" data-id="' + atractivo.id_atractivo + '" title="Bajar"' + downDisabled + '>&darr;</button>' +
                    '<button type="button" class="btn btn-icon btn-danger" data-remove="' + atractivo.id_atractivo + '" title="Quitar">&times;</button>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function findSelected(id) {
        return state.selected.find(function (item) {
            return Number(item.atractivo.id_atractivo) === Number(id);
        });
    }

    function addAtractivo(id) {
        if (findSelected(id)) {
            UI.toast('Ese atractivo ya está en tu ruta.', 'error');
            return;
        }

        if (state.selected.length >= 5) {
            UI.toast('Solo puedes agregar hasta 5 atractivos.', 'error');
            return;
        }

        var atractivo = state.atractivos.find(function (item) {
            return Number(item.id_atractivo) === Number(id);
        });

        if (!atractivo) {
            return;
        }

        state.selected.push({
            atractivo: atractivo,
            tiempo_estadia_min: 60
        });

        UI.setAlert(els.pageAlert, '');
        renderSelected();
        renderAtractivos();
        updateSummary();
    }

    function removeAtractivo(id) {
        state.selected = state.selected.filter(function (item) {
            return Number(item.atractivo.id_atractivo) !== Number(id);
        });

        renderSelected();
        renderAtractivos();
        updateSummary();
    }

    function moverAtractivo(id, direccion) {
        var index = state.selected.findIndex(function (item) {
            return Number(item.atractivo.id_atractivo) === Number(id);
        });

        if (index === -1) {
            return;
        }

        var target = direccion === 'up' ? index - 1 : index + 1;

        if (target < 0 || target >= state.selected.length) {
            return;
        }

        var temp = state.selected[index];
        state.selected[index] = state.selected[target];
        state.selected[target] = temp;

        renderSelected();
        updateSummary();
    }

    function renderServiceDetail() {
        var id = els.servicio.value;
        var servicio = state.servicios.find(function (item) {
            return String(item.id_servicio) === String(id);
        });

        if (!servicio) {
            els.servicioDetalle.hidden = true;
            els.servicioDetalle.innerHTML = '';
            return;
        }

        els.servicioDetalle.hidden = false;
        els.servicioDetalle.innerHTML =
            '<strong>' + UI.escapeHtml(servicio.tipo_servicio) + '</strong><br>' +
            'Teléfono: ' + UI.escapeHtml(servicio.telefono || 'No registrado') + ' · ' +
            'Distrito: ' + UI.escapeHtml(servicio.distrito || 'No registrado') + '<br>' +
            'Dirección: ' + UI.escapeHtml(servicio.direccion || 'No registrada');
    }

    function updateSummary() {
        var count = state.selected.length;
        var totalMinutos = state.selected.reduce(function (total, item) {
            return total + (Number(item.tiempo_estadia_min) || 0);
        }, 0);
        var distritos = {};
        var categorias = {};

        state.selected.forEach(function (item) {
            distritos[item.atractivo.distrito] = true;
            categorias[item.atractivo.categoria] = true;
        });

        var servicio = state.servicios.find(function (item) {
            return String(item.id_servicio) === String(els.servicio.value);
        });
        var nombres = els.nombres.value.trim();
        var apellidos = els.apellidos.value.trim();
        var nombreRuta = els.nombreRuta.value.trim();
        var fechaVisita = els.fechaVisita.value;

        els.atractivosCount.textContent = count + ' de 3 a 5';
        els.atractivosCount.className = 'badge ' + (count >= 3 && count <= 5 ? 'badge-aventura' : 'badge-neutral');

        els.sumAtractivos.textContent = String(count);
        els.sumDistritos.textContent = String(Object.keys(distritos).length);
        els.sumCategorias.textContent = String(Object.keys(categorias).length);
        els.sumTiempo.textContent = UI.formatMinutes(totalMinutos);
        els.sumServicio.textContent = servicio ? servicio.tipo_servicio : 'Sin elegir';
        els.sumNombre.textContent = nombreRuta || 'Sin definir';
        els.sumFecha.textContent = fechaVisita ? formatFecha(fechaVisita) : 'Sin elegir';
        els.sumHoras.textContent = (Math.round((totalMinutos / 60) * 10) / 10) + ' h';

        els.sumTourist.textContent = nombres || apellidos
            ? (nombres + ' ' + apellidos).trim()
            : 'Completa tus datos para comenzar.';

        els.sumMini.innerHTML = state.selected.map(function (item) {
            return '<li>' + UI.escapeHtml(item.atractivo.nombre) + ' · ' + UI.formatMinutes(item.tiempo_estadia_min) + '</li>';
        }).join('');

        els.selectionTotal.textContent = UI.formatMinutes(totalMinutos);

        if (count < 3) {
            els.selectionHint.textContent = 'Añade ' + (3 - count) + ' atractivo' + (3 - count === 1 ? '' : 's') + ' más para continuar.';
            els.selectionHint.className = 'warn';
        } else if (count > 5) {
            els.selectionHint.textContent = 'Quita ' + (count - 5) + ' atractivo' + (count - 5 === 1 ? '' : 's') + ' para continuar.';
            els.selectionHint.className = 'warn';
        } else {
            els.selectionHint.textContent = 'Ruta válida: ' + count + ' atractivos. Ajusta el orden si lo deseas.';
            els.selectionHint.className = 'ok';
        }

        var ready = isFormReady();

        els.btnSubmit.disabled = state.busy || !ready;
        els.summaryHint.textContent = ready
            ? 'Todo listo. Revisa el resumen y envía tu ruta.'
            : 'Completa tus datos, elige el servicio, ponle nombre y fecha, y selecciona entre 3 y 5 atractivos.';
    }

    function isName(value) {
        value = String(value || '').trim();
        return value.length >= 2 && namePattern.test(value);
    }

    function isTimeValid(value) {
        return Number.isInteger(value) && value >= 15 && value <= 1440;
    }

    function isDateValid(value) {
        var text = String(value || '');

        if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) {
            return false;
        }

        var parts = text.split('-').map(Number);
        var date = new Date(parts[0], parts[1] - 1, parts[2]);

        return date.getFullYear() === parts[0] &&
            date.getMonth() === parts[1] - 1 &&
            date.getDate() === parts[2];
    }

    function formatFecha(value) {
        if (!isDateValid(value)) {
            return value || 'Sin elegir';
        }

        var parts = value.split('-').map(Number);
        var date = new Date(parts[0], parts[1] - 1, parts[2]);

        return date.toLocaleDateString('es-PE', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function setDateBounds() {
        var today = new Date();
        var month = String(today.getMonth() + 1).padStart(2, '0');
        var day = String(today.getDate()).padStart(2, '0');

        els.fechaVisita.min = today.getFullYear() + '-' + month + '-' + day;
    }

    function isFormReady() {
        return isName(els.nombres.value) &&
            isName(els.apellidos.value) &&
            emailPattern.test(String(els.correo.value || '').trim()) &&
            els.servicio.value !== '' &&
            els.nombreRuta.value.trim().length >= 3 &&
            isDateValid(els.fechaVisita.value) &&
            state.selected.length >= 3 &&
            state.selected.length <= 5 &&
            state.selected.every(function (item) {
                return isTimeValid(item.tiempo_estadia_min);
            });
    }

    function setFieldError(name, message) {
        var error = document.querySelector('[data-error="' + name + '"]');
        var input = document.getElementById(name);

        if (error) {
            error.textContent = message || '';
        }

        if (input) {
            if (message) {
                input.setAttribute('aria-invalid', 'true');
            } else {
                input.removeAttribute('aria-invalid');
            }
        }
    }

    function clearErrors() {
        ['nombres', 'apellidos', 'correo', 'servicio', 'nombre-ruta', 'fecha-visita'].forEach(function (name) {
            setFieldError(name, '');
        });

        els.selectedList.querySelectorAll('[data-time]').forEach(function (input) {
            input.removeAttribute('aria-invalid');
        });
    }

    function validate() {
        clearErrors();

        var valid = true;
        var firstInvalid = null;

        function mark(name, input, message) {
            setFieldError(name, message);
            valid = false;

            if (!firstInvalid && input) {
                firstInvalid = input;
            }
        }

        if (!isName(els.nombres.value)) {
            mark('nombres', els.nombres, 'Ingresa tus nombres completos (solo letras).');
        }

        if (!isName(els.apellidos.value)) {
            mark('apellidos', els.apellidos, 'Ingresa tus apellidos completos (solo letras).');
        }

        if (!emailPattern.test(String(els.correo.value || '').trim())) {
            mark('correo', els.correo, 'Escribe un correo válido, por ejemplo nombre@correo.com.');
        }

        if (!els.servicio.value) {
            mark('servicio', els.servicio, 'Selecciona la gerencia que te acompañará.');
        }

        if (els.nombreRuta.value.trim().length < 3) {
            mark('nombre-ruta', els.nombreRuta, 'Ponle un nombre a la ruta (mínimo 3 caracteres).');
        }

        if (!isDateValid(els.fechaVisita.value)) {
            mark('fecha-visita', els.fechaVisita, 'Selecciona la fecha de la ruta.');
        }

        if (state.selected.length < 3 || state.selected.length > 5) {
            valid = false;
            els.selectionHint.className = 'warn';
        }

        var badTime = null;

        state.selected.forEach(function (item) {
            if (!isTimeValid(item.tiempo_estadia_min) && !badTime) {
                badTime = item;
            }
        });

        if (badTime) {
            valid = false;
            var input = els.selectedList.querySelector('[data-time="' + badTime.atractivo.id_atractivo + '"]');

            if (input) {
                input.setAttribute('aria-invalid', 'true');
            }
        }

        if (!valid) {
            UI.toast(
                badTime
                    ? 'Revisa los tiempos de visita: deben estar entre 15 y 1440 minutos.'
                    : 'Revisa los campos marcados y asegúrate de elegir entre 3 y 5 atractivos.',
                'error'
            );

            if (firstInvalid) {
                firstInvalid.focus();
            }

            return false;
        }

        return true;
    }

    function submit() {
        if (state.busy) {
            return;
        }

        UI.setAlert(els.pageAlert, '');

        if (!validate()) {
            return;
        }

        state.busy = true;
        els.btnSubmit.disabled = true;
        els.btnSubmit.textContent = 'Registrando ruta...';

        var payload = {
            nombres: els.nombres.value.trim(),
            apellidos: els.apellidos.value.trim(),
            correo: els.correo.value.trim(),
            id_servicio: Number(els.servicio.value),
            nombre_ruta: els.nombreRuta.value.trim(),
            fecha_visita: els.fechaVisita.value,
            atractivos: state.selected.map(function (item, index) {
                return {
                    id_atractivo: item.atractivo.id_atractivo,
                    orden_visita: index + 1,
                    tiempo_estadia_min: item.tiempo_estadia_min
                };
            })
        };

        API.post('rutas.php', payload).then(function (response) {
            state.busy = false;
            els.btnSubmit.textContent = 'Enviar mi ruta';
            showSuccess(response.ruta);
        }).catch(function (error) {
            state.busy = false;
            els.btnSubmit.textContent = 'Enviar mi ruta';
            updateSummary();

            var mensaje = error.status === 422
                ? error.message
                : 'No se pudo registrar la ruta. ' + error.message;

            UI.setAlert(els.pageAlert, mensaje, 'error');
            UI.toast(mensaje, 'error');
            els.pageAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    function showSuccess(ruta) {
        els.bookingLayout.hidden = true;
        els.successPanel.hidden = false;

        els.successMessage.textContent =
            'Gracias, ' + ruta.turista.nombres + ' ' + ruta.turista.apellidos +
            '. Tu ruta #' + ruta.id_ruta + ' quedó registrada con la gerencia ' +
            ruta.servicio.tipo_servicio + ' para el ' + formatFecha(ruta.fecha_visita) + '.';

        els.successSummary.innerHTML = [
            summaryRow('Ruta', '#' + ruta.id_ruta + ' · ' + ruta.nombre),
            summaryRow('Fecha de visita', formatFecha(ruta.fecha_visita)),
            summaryRow('Atractivos', ruta.total_atractivos + ' paradas'),
            summaryRow('Duración', UI.formatMinutes(ruta.total_minutos)),
            summaryRow('Servicio', ruta.servicio.tipo_servicio + ' · ' + (ruta.servicio.telefono || 'sin teléfono')),
            summaryRow('Correo', ruta.turista.correo)
        ].join('');

        els.successLink.href = 'gerencias.html';
        els.successPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function summaryRow(label, value) {
        return '<div class="summary-row"><span>' + UI.escapeHtml(label) + '</span><strong>' +
            UI.escapeHtml(value) + '</strong></div>';
    }

    function resetForNewRoute() {
        els.successPanel.hidden = true;
        els.bookingLayout.hidden = false;
        els.form.reset();
        els.servicio.value = '';
        els.nombreRuta.value = '';
        els.fechaVisita.value = '';
        state.selected = [];
        state.busy = false;
        els.btnSubmit.textContent = 'Enviar mi ruta';
        clearErrors();
        renderSelected();
        renderAtractivos();
        renderServiceDetail();
        updateSummary();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        els.nombres.focus();
    }
})();
