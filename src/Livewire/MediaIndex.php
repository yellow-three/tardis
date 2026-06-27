<?php

namespace Tardis\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Tardis\Manager\MediaManager;
use Tardis\Models\Media;

class MediaIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $collection = '';

    public string $mimeType = '';

    public string $viewMode = 'grid';

    public array $selectedIds = [];

    public $upload = null;

    public string $uploadCollection = 'default';

    public string $altText = '';

    public ?int $deleteId = null;

    protected $queryString = ['search', 'collection', 'mimeType', 'viewMode'];

    public function render()
    {
        $manager = app(MediaManager::class);

        $query = $manager->search([
            'search' => $this->search,
            'collection' => $this->collection,
            'mime_type' => $this->mimeType,
        ]);

        return view('tardis::livewire.media-index', [
            'media' => $query->paginate(24),
            'collections' => $manager->getCollections(),
            'mimeTypes' => $manager->getMimeTypes(),
        ]);
    }

    public function updatedUpload(): void
    {
        $this->validate([
            'upload' => 'required|file|max:'.config('tardis-media.max_file_size', 10240),
        ]);

        app(MediaManager::class)->upload(
            $this->upload,
            $this->uploadCollection,
            $this->altText ?: null,
        );

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
        app(MediaManager::class)->delete($media);
        $this->deleteId = null;
        session()->flash('message', 'File deleted successfully');
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $count = app(MediaManager::class)->bulkDelete($this->selectedIds);
        $this->selectedIds = [];
        session()->flash('message', "{$count} file(s) deleted successfully");
    }

    public function downloadZip(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $path = app(MediaManager::class)->downloadZip($this->selectedIds);

        $this->selectedIds = [];

        $this->dispatch('download-zip', path: $path);
    }
}
