<x-layouts::app :title="__('Permissions')">
    <div class="flex flex-col gap-y-2 sm:gap-y-3">
        <div class="flex min-w-0 flex-col gap-1">
            <flux:heading level="1" size="xl" class="font-display text-3xl! font-semibold! tracking-tight"
                data-test="permission-heading">
                {{ __('Permissions') }}
            </flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400" data-test="permission-description">
                {{ __('What each role can be allowed to do. Permissions are given to accounts through their roles.') }}
            </flux:text>
        </div>

        @forelse ($permissionsByModule as $module => $permissions)
            <section data-test="permission-module-{{ $module }}" aria-labelledby="permission-module-{{ $module }}-heading"
                class="flex flex-col gap-2">
                <flux:heading level="2" size="lg" id="permission-module-{{ $module }}-heading">
                    {{ \Basics13\Models\Permission::moduleLabel($module) }}
                </flux:heading>

                <div class="resource-list-table overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-card dark:border-zinc-800 dark:bg-zinc-900">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Permission') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Slug') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Granted by') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($permissions as $permission)
                            <flux:table.row :key="$permission->id" data-test="permission-row-{{ $permission->slug }}">
                                <flux:table.cell class="font-medium">{{ $permission->label() }}</flux:table.cell>
                                <flux:table.cell>
                                    <code class="text-sm text-zinc-600 dark:text-zinc-300">{{ $permission->slug }}</code>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($permission->roles->isEmpty())
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No role') }}</span>
                                    @else
                                        <span class="flex flex-wrap gap-1">
                                            @foreach ($permission->roles as $role)
                                                <flux:badge size="sm">{{ $role->name }}</flux:badge>
                                            @endforeach
                                        </span>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                </div>
            </section>
        @empty
            <flux:text data-test="permission-empty">{{ __('No permissions yet.') }}</flux:text>
        @endforelse
    </div>
</x-layouts::app>
