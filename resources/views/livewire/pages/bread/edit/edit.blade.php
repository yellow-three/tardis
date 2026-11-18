<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ __('tardis::bread.edit_record', ['name' => $bread['name'] ?? ucfirst($slug)]) }}</h1>
            <p class="text-base-content/60 mt-1">{{ __('tardis::bread.update_the_selected_record') }}</p>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-error mb-4">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error mb-4">
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form wire:submit="save" class="card bg-base-100 border border-base-300">
        <div class="card-body space-y-5">
            @foreach ($this->formfields as $field)
                <x-tardis::form-field :field="$field" :active-locale="$activeLocale" :context="['relationOptions' => $this->relationResults[$field->name] ?? []]" />
            @endforeach

            <div class="card-actions justify-end">
                <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug) }}" class="btn btn-ghost">{{ __('tardis::bread.cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('tardis::bread.update') }}</button>
            </div>
        </div>
    </form>
</div>
