<div class="card bg-base-100 shadow">
    <div class="card-body">
        <div class="flex items-center gap-4">
            <div class="p-3 rounded-box bg-primary/10 text-primary">
                <x-heroicon-o-photo class="w-8 h-8" />
            </div>
            <div>
                <p class="text-3xl font-bold">{{ $count }}</p>
                <p class="text-sm opacity-60">Media Files</p>
                @if($count > 0)
                    <p class="text-xs opacity-40">{{ $totalSize }} total</p>
                @endif
            </div>
        </div>
    </div>
</div>
