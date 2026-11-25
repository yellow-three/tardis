/**
 * Tardis core script.
 *
 * Loaded as a plain script before Livewire starts Alpine, so everything here
 * registers through whenAlpine(): immediately if Alpine is already running, on
 * `alpine:init` otherwise. The server hands it the page's data in
 * the #tardis-boot JSON block (see components/theme-boot.blade.php).
 *
 * The public surface for plugins is window.Tardis; the stores behind it
 * (`theme`, `toasts`) are internal and may change between major versions.
 */
(function () {
    'use strict';

    var boot = readBoot();
    // Kept for plugins written against 0.1, which read the global directly.
    window.__TARDIS__ = boot;
    var listeners = {};

    // The server writes the page data as a JSON block (components/theme-boot),
    // which needs no CSP nonce; a page from an older layout may still set the
    // global itself.
    function readBoot() {
        var element = document.getElementById('tardis-boot');

        if (element) {
            try {
                return JSON.parse(element.textContent) || {};
            } catch (error) {
                console.error('[tardis] the page data block is not valid JSON', error);
            }
        }

        return window.__TARDIS__ || {};
    }

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


    // ------------------------------------------------------ command palette --

    // Ctrl+K / ⌘K opens it anywhere in the panel; the entries come from the
    // JSON block the server rendered (components/command-palette).
    function commandPalette() {
        return {
            open: false,
            query: '',
            active: 0,
            entries: [],

            init() {
                var block = this.$el.querySelector('[data-palette-entries]');

                try {
                    this.entries = block ? JSON.parse(block.textContent) || [] : [];
                } catch (error) {
                    this.entries = [];
                }

                this.$watch('query', () => { this.active = 0; });
            },

            get results() {
                var terms = this.query.toLowerCase().split(/\s+/).filter(Boolean);

                if (terms.length === 0) {
                    return this.entries.slice(0, 8);
                }

                return this.entries.filter(function (entry) {
                    var text = (entry.title + ' ' + entry.group + ' ' + entry.keywords).toLowerCase();

                    return terms.every(function (term) { return text.indexOf(term) !== -1; });
                }).slice(0, 20);
            },

            shortcut(event) {
                if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    this.open ? this.close() : this.show();
                }
            },

            show() {
                this.open = true;
                this.query = '';
                this.active = 0;
                this.$nextTick(() => this.$refs.input.focus());
            },

            close() {
                this.open = false;
            },

            navigate(event) {
                var last = this.results.length - (this.query.trim() === '' ? 1 : 0);

                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.close();
                } else if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    this.active = Math.min(this.active + 1, last);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    this.active = Math.max(this.active - 1, 0);
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    this.active < this.results.length ? this.go(this.results[this.active]) : this.search();
                }
            },

            searchUrl() {
                return this.$root.dataset.searchUrl + '?query=' + encodeURIComponent(this.query.trim());
            },

            go(entry) {
                this.close();
                window.Livewire && window.Livewire.navigate ? window.Livewire.navigate(entry.url) : (window.location.href = entry.url);
            },

            search() {
                if (this.query.trim() !== '') {
                    this.go({ url: this.searchUrl() });
                }
            },
        };
    }

    whenAlpine(function (Alpine) {
        Alpine.data('commandPalette', commandPalette);
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
        version: boot.version || '0',

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

        /** Open the command palette, as Ctrl+K does. */
        palette: function () {
            window.dispatchEvent(new CustomEvent('tardis-palette'));
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
