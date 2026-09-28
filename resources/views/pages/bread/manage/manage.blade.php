<div>
    <x-tardis::page-header
        title="BREAD Management"
        description="Manage your Browse, Read, Edit, Add, Delete definitions"
    >
        <x-slot:action>
            <a href="{{ route('tardis.bread.create') }}" class="btn btn-primary gap-2">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                New BREAD
            </a>
        </x-slot:action>
    </x-tardis::page-header>

    @if (session('message'))
        <div class="alert alert-success mb-4">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error mb-4">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if ($this->hasLegacyDefinitions)
        <div class="alert alert-warning mb-6">
            <x-tardis::icon name="information-circle" class="w-5 h-5 shrink-0" />
            <div class="flex-1">
                <h3 class="font-semibold">Legacy config definitions detected</h3>
                <p class="text-sm text-base-content/70">
                    You still have BREAD definitions in <code class="badge badge-ghost badge-sm">config/bread</code>.
                    Run <code class="badge badge-ghost badge-sm">php artisan tardis:bread:migrate</code> to move
                    them into JSON storage.
                </p>
            </div>
        </div>
    @endif

    @if ($this->breads->isEmpty())
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body text-center py-12">
                <x-tardis::icon name="table-cells" class="w-16 h-16 mx-auto text-base-content/30" />
                <h3 class="text-lg font-semibold mt-4">No BREAD definitions found</h3>
                <p class="text-base-content/60 mt-2">
                    Create a BREAD definition to get started
                </p>
            </div>
        </div>
    @else
        <div class="card bg-base-100 border border-base-300">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Source</th>
                            <th class="text-right" scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->breads as $slug => $bread)
                            <tr>
                                <td class="font-semibold">{{ $bread->name ?? $slug }}</td>
                                <td><code class="badge badge-ghost badge-sm">{{ $slug }}</code></td>
                                <td><span class="badge badge-info badge-sm">json</span></td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <div class="dropdown dropdown-end">
                                            <div tabindex="0" role="button" class="btn btn-ghost btn-sm gap-1" aria-label="Backups for {{ $slug }}">
                                                <x-tardis::icon name="clock" class="w-4 h-4" />
                                                Backups
                                            </div>
                                            <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-10 w-72 p-2 shadow border border-base-300">
                                                @forelse ($this->backups($slug) as $backup)
                                                    <li>
                                                        <button
                                                            wire:click="rollback('{{ $slug }}', '{{ $backup['name'] }}')"
                                                            wire:confirm="Restore this backup? The current definition will be snapshotted first."
                                                            class="justify-between font-mono text-xs"
                                                        >
                                                            <span class="truncate">{{ $backup['date'] }}</span>
                                                            <span class="badge badge-warning badge-sm shrink-0">Restore</span>
                                                        </button>
                                                    </li>
                                                @empty
                                                    <li>
                                                        <span class="px-2 py-1 text-sm text-base-content/60">
                                                            No backups yet
                                                        </span>
                                                    </li>
                                                @endforelse
                                            </ul>
                                        </div>
                                        <a href="{{ route('tardis.bread.edit', ['slug' => $slug]) }}" class="btn btn-ghost btn-sm">
                                            Edit
                                        </a>
                                        <a href="{{ route('tardis.bread.index', ['slug' => $slug]) }}" class="btn btn-ghost btn-sm">
                                            Browse
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>