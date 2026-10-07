<?php

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Models\Media;
use Tardis\Support\ModelResolver;

new #[Title('tardis::media.edit_media')] #[Layout('tardis::layouts.admin')] class extends Component
{
    #[Locked]
    public int $media_id;

    public string $name = '';

    public string $original_name = '';

    public ?string $alt_text = null;

    public ?string $caption = null;

    public ?string $description = null;

    public ?Media $media = null;

    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_RENAME);
    }

    public function mount(int $id): void
    {
        $this->media_id = $id;
        $this->media = ModelResolver::media()::findOrFail($id);
        $this->name = $this->media->name;
        $this->original_name = $this->media->original_name;
        $this->alt_text = $this->media->alt_text;
        $this->caption = $this->media->caption;
        $this->description = $this->media->description;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'original_name' => 'required|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $this->media->update($validated);
        session()->flash('success', __('tardis::media.updated_successfully'));
    }

    public function delete(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_DELETE);

        // The row stores the full storage path (basePath already prefixed), so
        // the file goes directly — MediaManager::deleteFile() would prefix it
        // a second time and miss.
        if ($this->media->path !== null) {
            Storage::disk($this->media->disk)->delete($this->media->path);
        }

        $this->media->delete();
        session()->flash('success', __('tardis::media.deleted_successfully'));
        $this->redirectRoute('tardis.media');
    }
};
