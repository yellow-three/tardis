/**
 * Tardis core script.
 *
 * Loaded as a plain script before Livewire starts Alpine, so everything here
 * registers through whenAlpine(): immediately if Alpine is already running, on
 * `alpine:init` otherwise. The server hands it the page's data in
 * window.__TARDIS__ (see components/theme-boot.blade.php).
 *
 * The public surface for plugins is window.Tardis; the stores behind it
 * (`theme`, `toasts`) are internal and may change between major versions.
 */
(function () {
    'use strict';

    var boot = window.__TARDIS__ || {};
    var listeners = {};

    function whenAlpine(callback) {
        if (window.Alpine) {
            callback(window.Alpine);

            return;
        }

        document.addEventListener('alpine:init', function () {
            callback(window.Alpine);
        });
    }

    function emit(name, detail) {
        (listeners[name] || []).slice().forEach(function (listener) {
            try {
                listener(detail);
            } catch (error) {
                console.error('[tardis] listener for "' + name + '" failed', error);
            }
        });
    }

    // ---------------------------------------------------------------- theme --

    function themeStore() {
        var data = boot.theme || {};
        var timer = null;

        return {
            mode: data.mode || 'dark',
            light: data.light || 'tardis-light',
            dark: data.dark || 'tardis-dark',
            themes: data.themes || [],

            get availableThemes() {
                return this.themes;
            },

            get lightThemes() {
                return this.themes.filter(function (t) { return t.scheme === 'light'; });
            },

            get darkThemes() {
                return this.themes.filter(function (t) { return t.scheme === 'dark'; });
            },

            get applied() {
                if (this.mode === 'system') {
                    return window.matchMedia('(prefers-color-scheme: dark)').matches ? this.dark : this.light;
                }

                return this.mode === 'dark' ? this.dark : this.light;
            },

            init: function () {
                var store = this;

                this.apply();

                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                    if (store.mode === 'system') {
                        store.apply();
                    }
                });
            },

            apply: function () {
                document.documentElement.setAttribute('data-theme', this.applied);
                emit('theme-changed', { mode: this.mode, applied: this.applied });
            },

            setMode: function (mode) {
                this.mode = mode;
                this.changed({ mode: mode });
            },

            setLight: function (name) {
                this.light = name;
                this.changed({ light: name });
            },

            setDark: function (name) {
                this.dark = name;
                this.changed({ dark: name });
            },

            toggle: function () {
                this.setMode(this.applied === this.dark || this.mode === 'dark' ? 'light' : 'dark');
            },

            // light -> dark -> system -> light
            cycle: function () {
                var order = ['light', 'dark', 'system'];

                this.setMode(order[(order.indexOf(this.mode) + 1) % order.length]);
            },

            // Apply at once, then tell the server (debounced). A failed save is
            // not worth interrupting the user for: the choice still holds for this page.
            changed: function (patch) {
                var store = this;

                this.apply();

                if (!data.persist || !data.saveUrl) {
                    return;
                }

                store.pending = Object.assign(store.pending || {}, patch);
                clearTimeout(timer);

                timer = setTimeout(function () {
                    var body = store.pending;
                    store.pending = null;

                    fetch(data.saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': data.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(body),
                    }).catch(function () {});
                }, 250);
            },
        };
    }

    // ---------------------------------------------------------------- toasts --

    function toastsStore() {
        var nextId = 1;

        return {
            items: [],

            show: function (message, options) {
                options = options || {};

                var toast = {
                    id: nextId++,
                    message: String(message),
                    type: options.type || 'info',
                    timeout: options.timeout === undefined ? 5000 : options.timeout,
                };

                this.items.push(toast);

                if (toast.timeout > 0) {
                    var store = this;
                    setTimeout(function () { store.dismiss(toast.id); }, toast.timeout);
                }

                return toast.id;
            },

            dismiss: function (id) {
                this.items = this.items.filter(function (toast) { return toast.id !== id; });
            },
        };
    }


    // ---------------------------------------------------------------- builder layout helpers --

    function builderLayout() {
        return {
            dragging: null,
            dragOver: null,

            startDrag(field, index) {
                this.dragging = { field: field, index: index };
            },

            endDrag() {
                this.dragging = null;
                this.dragOver = null;
            },

            dragEnter(index) {
                this.dragOver = index;
            },

            drop(targetIndex, items, updateCallback) {
                if (this.dragging === null) {
                    return;
                }
                const from = this.dragging.index;
                const to = targetIndex;
                if (from === to) {
                    this.endDrag();
                    return;
                }
                const arr = items.slice();
                const [moved] = arr.splice(from, 1);
                arr.splice(to, 0, moved);
                updateCallback(arr);
                this.endDrag();
            },

            setColSpan(field, span, updateCallback) {
                const s = Math.max(1, Math.min(6, parseInt(span) || 1));
                updateCallback(field, s);
            },

            incSpan(field, current, updateCallback) {
                this.setColSpan(field, (current || 1) + 1, updateCallback);
            },

            decSpan(field, current, updateCallback) {
                this.setColSpan(field, (current || 1) - 1, updateCallback);
            },
        };
    }


    whenAlpine(function (Alpine) {
        Alpine.store('theme', themeStore());
        Alpine.store('toasts', toastsStore());
        Alpine.data('builderLayout', builderLayout);
    });

    // Livewire components can raise a toast with $this->dispatch('tardis-toast', message: '...', type: 'success').
    window.addEventListener('tardis-toast', function (event) {
        var detail = event.detail || {};

        window.Tardis.toast(detail.message || '', detail);
    });

    document.addEventListener('livewire:navigated', function () {
        emit('navigate', { url: window.location.href });
    });

    // ----------------------------------------------------------- public API --

    window.Tardis = {
        version: boot.version || '1',

        /** Register an Alpine component: Tardis.component('chart', () => ({ ... })) */
        component: function (name, definition) {
            whenAlpine(function (Alpine) {
                Alpine.data(name, definition);
            });
        },

        /** Subscribe to 'ready', 'navigate' or 'theme-changed'. */
        on: function (name, listener) {
            (listeners[name] = listeners[name] || []).push(listener);

            if (name === 'ready' && document.readyState !== 'loading') {
                listener({});
            }
        },

        toast: function (message, options) {
            if (window.Alpine && window.Alpine.store('toasts')) {
                return window.Alpine.store('toasts').show(message, options);
            }

            console.info('[tardis] toast:', message);
        },

        /** The theme currently applied to the page. */
        get theme() {
            return document.documentElement.getAttribute('data-theme');
        },

        csrf: function () {
            return ((boot.theme || {}).csrf) || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        emit('ready', {});
    });
})();
