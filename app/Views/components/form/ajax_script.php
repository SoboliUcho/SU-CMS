<script>
    (function() {
        var form = document.getElementById('{{form_id}}');
        if (!form) {
            return;
        }

        var submitOnChange = '{{submit_on_change}}' === '1';
        var debounceMs = parseInt('{{debounce_ms}}', 10);
        if (Number.isNaN(debounceMs) || debounceMs < 0) {
            debounceMs = 500;
        }

        var debounceTimer = null;
        var pendingController = null;
        var triggerField = form.querySelector('input[name="__submit_trigger"]');

        function setTrigger(trigger) {
            if (triggerField) {
                triggerField.value = trigger;
            }
        }

        function emit(name, detail) {
            form.dispatchEvent(new CustomEvent(name, { detail: detail }));
        }

        async function send(trigger) {
            setTrigger(trigger);

            var method = (form.getAttribute('method') || 'POST').toUpperCase();
            var action = form.getAttribute('action') || window.location.pathname;
            var formData = new FormData(form);

            if (pendingController) {
                pendingController.abort();
            }
            pendingController = new AbortController();

            var url = action;
            var options = {
                method: method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                signal: pendingController.signal
            };

            if (method === 'GET') {
                var query = new URLSearchParams(formData).toString();
                if (query) {
                    url += (url.indexOf('?') === -1 ? '?' : '&') + query;
                }
            } else {
                options.body = formData;
            }

            try {
                emit('cms:form:request', { trigger: trigger });
                var response = await fetch(url, options);
                var contentType = response.headers.get('Content-Type') || '';
                var payload = null;

                if (contentType.indexOf('application/json') !== -1) {
                    payload = await response.json();
                } else {
                    payload = { ok: response.ok, message: await response.text() };
                }

                if (response.status === 429) {
                    emit('cms:form:rate-limited', payload);
                    return;
                }

                if (!response.ok || payload.ok === false) {
                    emit('cms:form:error', payload);
                    return;
                }

                emit('cms:form:success', payload);
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                emit('cms:form:error', {
                    ok: false,
                    message: 'Request failed',
                    error: String(error)
                });
            }
        }

        form.addEventListener('submit', function(event) {
            event.preventDefault();
            send('submit');
        });

        if (submitOnChange) {
            var onChangeHandler = function(event) {
                var target = event.target;
                if (!target || !target.name) {
                    return;
                }

                var type = (target.type || '').toLowerCase();
                if (type === 'submit' || type === 'button' || type === 'reset') {
                    return;
                }

                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                debounceTimer = setTimeout(function() {
                    send('change');
                }, debounceMs);
            };

            form.addEventListener('input', onChangeHandler);
            form.addEventListener('change', onChangeHandler);
        }
    })();
</script>