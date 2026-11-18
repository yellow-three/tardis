<div class="tardis-container px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-base-content">
            {{ __('tardis::media.edit_media') }}
        </h1>
        <a href="{{ route('tardis.media') }}" class="btn btn-ghost btn-sm">
            {{ __('tardis::common.back') }}
        </a>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">{{ __('tardis::media.name') }}</span></label>
                        <input type="text" wire:model="name" class="input input-bordered" />
                        @error('name') <p class="text-sm text-error mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">{{ __('tardis::media.original_name') }}</span></label>
                        <input type="text" wire:model="original_name" class="input input-bordered" />
                        @error('original_name') <p class="text-sm text-error mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">{{ __('tardis::media.alt_text') }}</span></label>
                        <input type="text" wire:model="alt_text" class="input input-bordered" />
                        @error('alt_text') <p class="text-sm text-error mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">{{ __('tardis::media.caption') }}</span></label>
                        <input type="text" wire:model="caption" class="input input-bordered" />
                        @error('caption') <p class="text-sm text-error mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">{{ __('tardis::media.description') }}</span></label>
                        <textarea wire:model="description" class="textarea textarea-bordered" rows="4"></textarea>
                        @error('description') <p class="text-sm text-error mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    @if($media->isImage())
                        <div class="flex justify-center">
                            <img src="{{ $media->url }}" alt="{{ $media->alt_text }}" class="max-h-64 rounded border border-base-300" />
                        </div>
                    @endif

                    <div class="text-sm text-base-content/70 space-y-1">
                        <div>{{ __('tardis::media.mime_type') }}: {{ $media->mime_type }}</div>
                        <div>{{ __('tardis::media.size') }}: {{ $media->formatted_size }}</div>
                        <div>{{ __('tardis::media.path') }}: {{ $media->path }}</div>
                        <div>{{ __('tardis::media.disk') }}: {{ $media->disk }}</div>
                    </div>

                    <div class="card-actions">
                        <button type="submit" class="btn btn-primary btn-block">{{ __('tardis::common.save') }}</button>
                        <button type="button" wire:click="delete" wire:confirm="{{ __('tardis::media.delete_confirm') }}" class="btn btn-error btn-block">{{ __('tardis::media.delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
