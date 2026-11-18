<div>
    <x-tardis::page-header :title="__('tardis::site_menus.title')" :description="__('tardis::site_menus.description')" />

    @include('tardis::partials.menu-tabs', ['active' => 'site'])

    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        <x-tardis::card :title="__('tardis::site_menus.menus')">
            <ul class="menu w-full p-0">
                @forelse ($this->names() as $name)
                    <li wire:key="site-menu-{{ $name }}">
                        <button type="button" wire:click="select(@js($name))" @class(['menu-active' => $name === $menu]) @if ($name === $menu) aria-current="true" @endif>
                            <code>{{ $name }}</code>
                        </button>
                    </li>
                @empty
                    <li class="text-sm text-base-content/60 px-2 py-1">{{ __('tardis::site_menus.no_menus') }}</li>
                @endforelse
            </ul>

            <form wire:submit="createMenu" class="mt-4 flex gap-2">
                <input type="text" wire:model="newMenu" placeholder="main" class="input input-sm w-full" aria-label="{{ __('tardis::site_menus.new_menu') }}" />
                <button type="submit" class="btn btn-sm btn-primary">{{ __('tardis::site_menus.create') }}</button>
            </form>
            @error('newMenu') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
        </x-tardis::card>

        <div class="space-y-6">
            @if ($menu === '')
                <x-tardis::card>
                    <p class="text-base-content/60">{{ __('tardis::site_menus.start') }}</p>
                </x-tardis::card>
            @else
                <x-tardis::card :title="__('tardis::site_menus.items_of', ['name' => $menu])">
                    <x-slot name="actions">
                        <button type="button" wire:click="deleteMenu" wire:confirm="{{ __('tardis::site_menus.confirm_delete') }}" class="btn btn-ghost btn-sm text-error">{{ __('tardis::site_menus.delete_menu') }}</button>
                    </x-slot>

                    <p class="text-sm text-base-content/60 mb-4">{!! __('tardis::site_menus.usage', ['code' => '<code>&lt;x-tardis::site-menu name="'.e($menu).'" /&gt;</code>']) !!}</p>

                    @error('items') <p class="text-error text-sm mb-2">{{ $message }}</p> @enderror

                    @php($rows = $this->rows())
                    @if ($rows === [])
                        <p class="text-base-content/60">{{ __('tardis::site_menus.empty') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('tardis::site_menus.item_title') }}</th>
                                        <th scope="col">{{ __('tardis::site_menus.item_url') }}</th>
                                        <th scope="col">{{ __('tardis::site_menus.new_tab') }}</th>
                                        <th scope="col" class="text-right">{{ __('tardis::menu_builder.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr wire:key="site-item-{{ $row['id'] }}">
                                            <td>
                                                <div class="flex items-center" style="padding-inline-start: {{ ($row['depth'] - 1) * 1.5 }}rem">
                                                    @if ($row['depth'] > 1)<span class="text-base-content/30 me-1" aria-hidden="true">└</span>@endif
                                                    <input type="text" value="{{ $row['title'] }}" wire:change="updateItem(@js($row['id']), 'title', $event.target.value)" class="input input-sm w-full max-w-xs" aria-label="{{ __('tardis::site_menus.item_title') }}" />
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" value="{{ $row['url'] }}" wire:change="updateItem(@js($row['id']), 'url', $event.target.value)" class="input input-sm w-full max-w-xs" aria-label="{{ __('tardis::site_menus.item_url') }}" />
                                            </td>
                                            <td>
                                                <input type="checkbox" @checked($row['new_tab']) wire:change="updateItem(@js($row['id']), 'new_tab', $event.target.checked)" class="checkbox checkbox-sm" aria-label="{{ __('tardis::site_menus.new_tab') }}" />
                                            </td>
                                            <td class="text-right">
                                                <div class="flex justify-end gap-1">
                                                    <button type="button" wire:click="moveItem(@js($row['id']), -1)" @disabled($row['first']) class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::menu_builder.move_up') }}">▲</button>
                                                    <button type="button" wire:click="moveItem(@js($row['id']), 1)" @disabled($row['last']) class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::menu_builder.move_down') }}">▼</button>
                                                    <button type="button" wire:click="outdentItem(@js($row['id']))" @disabled($row['depth'] === 1) class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::site_menus.outdent') }}">◀</button>
                                                    <button type="button" wire:click="indentItem(@js($row['id']))" @disabled($row['first'] || $row['depth'] >= \Tardis\Menu\SiteMenuStore::MAX_DEPTH) class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::site_menus.indent') }}">▶</button>
                                                    <button type="button" wire:click="removeItem(@js($row['id']))" wire:confirm="{{ __('tardis::site_menus.confirm_remove') }}" class="btn btn-ghost btn-xs text-error">{{ __('tardis::menu_builder.remove') }}</button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-tardis::card>

                <x-tardis::card :title="__('tardis::site_menus.add_item')">
                    <form wire:submit="addItem" class="grid gap-3 md:grid-cols-2">
                        <label class="flex flex-col gap-1">
                            <span class="text-sm font-medium">{{ __('tardis::site_menus.item_title') }}</span>
                            <input type="text" wire:model="itemTitle" class="input input-sm w-full" />
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-sm font-medium">{{ __('tardis::site_menus.item_url') }}</span>
                            <input type="text" wire:model="itemUrl" placeholder="/blog" class="input input-sm w-full" />
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-sm font-medium">{{ __('tardis::site_menus.parent') }}</span>
                            <select wire:model="itemParent" class="select select-sm w-full">
                                <option value="">{{ __('tardis::site_menus.top_level') }}</option>
                                @foreach ($rows as $row)
                                    @if ($row['depth'] < \Tardis\Menu\SiteMenuStore::MAX_DEPTH)
                                        <option value="{{ $row['id'] }}">{{ str_repeat('— ', $row['depth'] - 1) }}{{ $row['title'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>
                        <label class="flex items-center gap-2 self-end pb-1">
                            <input type="checkbox" wire:model="itemNewTab" class="checkbox checkbox-sm" />
                            <span class="text-sm">{{ __('tardis::site_menus.new_tab') }}</span>
                        </label>
                        <div class="md:col-span-2 flex items-center gap-3">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('tardis::site_menus.add_item') }}</button>
                            @error('itemUrl') <span class="text-error text-sm">{{ $message }}</span> @enderror
                        </div>
                    </form>
                </x-tardis::card>
            @endif
        </div>
    </div>
</div>
