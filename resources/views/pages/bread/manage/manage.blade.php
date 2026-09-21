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

    @if ($this->breads->isEmpty())
        <div class="card bg-base-100 shadow">
            <div class="card-body text-center py-12">
                <x-tardis::icon name="table-cells" class="w-16 h-16 mx-auto opacity-30" />
                <h3 class="text-lg font-semibold mt-4">No BREAD definitions found</h3>
                <p class="text-base-content/60 mt-2">
                    Create a BREAD definition to get started
                </p>
            </div>
        </div>
    @else
        <div class="card bg-base-100 shadow">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Source</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->breads as $slug => $bread)
                            <tr>
                                <td class="font-semibold">{{ $bread->name ?? $slug }}</td>
                                <td><code class="badge badge-ghost badge-sm">{{ $slug }}</code></td>
                                <td><span class="badge badge-info badge-sm">config</span></td>
                                <td class="text-right">
                                    <a href="{{ route('tardis.bread.index', ['slug' => $slug]) }}" class="btn btn-ghost btn-sm">
                                        Browse
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
