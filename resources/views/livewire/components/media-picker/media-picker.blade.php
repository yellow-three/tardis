<div>
    @if ($open)
        <dialog class="modal modal-open">
            <div class="modal-box max-w-3xl p-0">
                {{-- Header --}}
                <div class="flex items-center justify-between px-5 py-4 border-b border-base-200">
                    <h3 class="font-bold text-lg">{{ __('tardis::media.media_library') }}</h3>
                    <button type="button" wire:click="close" class="btn btn-ghost btn-sm btn-square" title="{{ __('tardis::media.close') }}">
                        <x-tardis::icon name="x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="px-5 py-4 space-y-4">
                    @if ($showFolders)
                        {{-- Breadcrumbs --}}
                        <div class="text-sm breadcrumbs px-1 text-base-content/70">
                            <ul>
                                @foreach ($this->getBreadcrumbs() as $crumb)
                                    <li>
                                        @if ($loop->last)
                                            <span>{{ $crumb['label'] }}</span>
                                        @else
                                            <a wire:click="navigateTo('{{ $crumb['path'] }}')" class="cursor-pointer hover:text-primary">{{ $crumb['label'] }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @if ($currentPath !== '')
                            <button type="button" wire:click="goToParent" class="btn btn-ghost btn-sm gap-2">
                                <x-tardis::icon name="folder" class="w-4 h-4" />
                                ..
                            </button>
                        @endif
                    @endif

                    @if ($error !== null)
                        <div class="alert alert-error">
                            <span>{{ $error }}</span>
                        </div>
                    @endif

                    @if (empty($files))
                        {{-- Empty State --}}
                        <div class="text-center py-12">
                            <x-tardis::icon name="photo" class="w-16 h-16 mx-auto text-base-content/20" />
                            <p class="text-base-content/60 mt-3">{{ __('tardis::media.no_files_found') }}</p>
                        </div>
                    @else
                        {{-- File Grid --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 max-h-96 overflow-y-auto">
                            @foreach ($files as $file)
                                @if ($file['type'] === 'directory')
                                    <button
                                        type="button"
                                        wire:click="navigateTo('{{ $file['relative_path'] }}')"
                                        class="aspect-square rounded-xl overflow-hidden border-2 border-transparent hover:border-base-300 bg-base-200 transition-all">
                                        <span class="w-full h-full flex flex-col items-center justify-center gap-2">
                                            <x-tardis::icon name="folder" class="w-12 h-12 text-primary/60" />
                                            <span class="text-xs font-medium px-2 text-center w-full truncate">{{ $file['name'] }}</span>
                                        </span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="toggleSelect('{{ $file['path'] }}')"
                                        class="group relative aspect-square rounded-xl overflow-hidden border-2 transition-all {{ in_array($file['path'], $selected, true) ? 'border-primary ring-2 ring-primary/30' : 'border-transparent hover:border-base-300' }} bg-base-100">
                                        @if (str_starts_with($file['type'], 'image/'))
                                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="object-cover w-full h-full" loading="lazy" />
                                        @else
                                            <span class="w-full h-full flex flex-col items-center justify-center gap-2 bg-base-200">
                                                <x-tardis::icon name="document-text" class="w-10 h-10 text-base-content/30" />
                                                <span class="text-xs px-2 text-center w-full truncate">{{ $file['name'] }}</span>
                                            </span>
                                        @endif
                                        @if (in_array($file['path'], $selected, true))
                                            <span class="absolute top-2 right-2 bg-primary text-primary-content rounded-full p-1">
                                                <x-tardis::icon name="check-circle" class="w-3 h-3" />
                                            </span>
                                        @endif
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between px-5 py-4 border-t border-base-200">
                    <span class="text-sm text-base-content/60">{{ __('tardis::media.files_selected', ['count' => count($selected)]) }}</span>
                    <div class="flex gap-2">
                        <button type="button" wire:click="close" class="btn btn-ghost">{{ __('tardis::media.cancel') }}</button>
                        <button type="button" wire:click="confirm" class="btn btn-primary" @disabled($selected === [])>
                            {{ __('tardis::media.select') }}
                        </button>
                    </div>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="close">{{ __('tardis::media.close') }}</button>
            </form>
        </dialog>
    @endif
</div>
