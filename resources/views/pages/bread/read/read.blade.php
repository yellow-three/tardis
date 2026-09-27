<div>
    <x-tardis::page-header
        :title="$bread['name'] ?? ucfirst($slug)"
        description="Record details"
    >
        <x-slot:action>
            <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug) }}" class="btn btn-ghost">Back to list</a>
        </x-slot:action>
    </x-tardis::page-header>

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            @foreach ($this->fields as $field)
                @php($name = $field['name'] ?? '')
                @php($label = $field['label'] ?? ucfirst((string) $name))

                <div class="border-b border-base-300 pb-3">
                    <div class="text-xs uppercase tracking-wide text-base-content/50">{{ $label }}</div>
                    <div class="mt-1 text-base font-medium">
                        @if (! empty($field['translatable']))
                            {{ \Tardis\Classes\Translation::value(data_get($record, $name), $field['locales'] ?? null) }}
                        @else
                            {{ data_get($record, $name, '-') }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
