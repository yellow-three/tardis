{{-- Renders the toast store (resources/js/app.js). Raise one with Tardis.toast(...) or $this->dispatch('tardis-toast', ...). --}}
<div class="toast toast-end toast-top z-50" aria-live="polite" x-data>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="alert shadow-lg" :class="{ 'alert-success': toast.type === 'success', 'alert-error': toast.type === 'error', 'alert-warning': toast.type === 'warning', 'alert-info': toast.type === 'info' }" role="status">
            <span x-text="toast.message"></span>
            <button type="button" class="btn btn-ghost btn-xs" x-on:click="$store.toasts.dismiss(toast.id)" aria-label="{{ __('tardis::appearance.close') }}">✕</button>
        </div>
    </template>
</div>
