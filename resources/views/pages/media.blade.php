<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Tardis\Models\Media;

new #[Title('Media')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use WithFileUploads;

    public $upload = null;

    public string $collection = 'default';

    public string $altText = '';

    public string $viewMode = 'grid';

    public ?int $deleteId = null;

    protected function rules(): array
    {
        return [
            'upload' => 'required|file|max:'.config('tardis-media.max_file_size', 10240),
            'collection' => 'required|string|max:255',
            'altText' => 'nullable|string|max:500',
        ];
    }

    #[Computed]
    public function mediaItems()
    {
        return Media::latest()->paginate(24);
    }

    #[Computed]
    public function collections()
    {
        return Media::distinct()->pluck('collection')->filter();
    }

    public function upload(): void
    {
        $this->validate();

        Media::upload($this->upload, $this->collection, $this->altText ?: null);

        $this->upload = null;
        $this->altText = '';
        session()->flash('message', 'File uploaded successfully');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function delete(): void
    {
        $media = Media::findOrFail($this->deleteId);
        $media->delete();
        $this->deleteId = null;
        session()->flash('message', 'File deleted successfully');
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
    }
}; ?>

<div class="max-w-6xl mx-auto">
    @if (session('message'))
        <div class="alert alert-success mb-4 shadow-lg">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <h2 class="card-title text-2xl mb-2">Media</h2>
            <p class="text-sm opacity-70 mb-4">Upload and manage media files</p>

            <form wire:submit="upload" class="flex flex-col gap-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">File</span>
                    </label>
                    <input
                        type="file"
                        wire:model="upload"
                        class="file-input file-input-bordered file-input-primary w-full"
                    />
                    @error('upload')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                    <div wire:loading wire:target="upload" class="text-sm opacity-70 mt-1">Uploading...</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Collection</span>
                        </label>
                        <select wire:model="collection" class="select select-bordered w-full">
                            <option value="default">default</option>
                            @foreach ($this->collections as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Alt Text</span>
                        </label>
                        <input
                            type="text"
                            wire:model="altText"
                            placeholder="Description for accessibility"
                            class="input input-bordered w-full"
                        />
                    </div>
                </div>

                <button type="submit" class="btn btn-primary self-start" wire:loading.attr="disabled">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                    </svg>
                    Upload
                </button>
            </form>
        </div>
    </div>

    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">All Media</h3>
        <div class="flex gap-2">
            <button wire:click="$set('viewMode', 'grid')" class="btn btn-ghost btn-sm {{ $viewMode === 'grid' ? 'btn-active' : '' }}">
                Grid
            </button>
            <button wire:click="$set('viewMode', 'list')" class="btn btn-ghost btn-sm {{ $viewMode === 'list' ? 'btn-active' : '' }}">
                List
            </button>
        </div>
    </div>

    @if ($viewMode === 'grid')
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @forelse ($this->mediaItems as $media)
                <div class="card bg-base-100 shadow">
                    <figure class="px-2 pt-2">
                        @if ($media->isImage())
                            <img src="{{ $media->url }}" alt="{{ $media->alt_text ?? $media->original_name }}" class="rounded-xl h-32 w-full object-cover" />
                        @else
                            <div class="h-32 w-full rounded-xl bg-base-200 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                        @endif
                    </figure>
                    <div class="card-body p-3">
                        <p class="text-xs truncate font-medium" title="{{ $media->original_name }}">{{ $media->original_name }}</p>
                        <p class="text-xs opacity-60">{{ $media->formatted_size }}</p>
                        <button wire:click="confirmDelete({{ $media->id }})" class="btn btn-ghost btn-xs text-error mt-1">Delete</button>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center opacity-70 py-12">
                    <p>No media uploaded yet</p>
                </div>
            @endforelse
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Name</th>
                        <th>Collection</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th class="w-20">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->mediaItems as $media)
                        <tr>
                            <td>
                                @if ($media->isImage())
                                    <img src="{{ $media->url }}" alt="" class="w-10 h-10 rounded object-cover" />
                                @else
                                    <div class="w-10 h-10 rounded bg-base-200 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif
                            </td>
                            <td class="font-medium text-sm truncate max-w-xs">{{ $media->original_name }}</td>
                            <td><span class="badge badge-ghost badge-sm">{{ $media->collection }}</span></td>
                            <td class="text-sm opacity-70">{{ $media->formatted_size }}</td>
                            <td class="text-sm opacity-70">{{ $media->created_at->diffForHumans() }}</td>
                            <td>
                                <button wire:click="confirmDelete({{ $media->id }})" class="btn btn-ghost btn-xs text-error">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center opacity-70 py-8">No media uploaded yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-4">
        {{ $this->mediaItems->links() }}
    </div>

    @if ($deleteId)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Delete Media</h3>
                <p class="py-4">Are you sure you want to delete this file? This action cannot be undone.</p>
                <div class="modal-action">
                    <button wire:click="cancelDelete" class="btn btn-ghost">Cancel</button>
                    <button wire:click="delete" class="btn btn-error">Delete</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelDelete">close</button>
            </form>
        </dialog>
    @endif
</div>
