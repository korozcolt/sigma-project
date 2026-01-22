<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ auth()->user()->hasRole('admin_campaign') ? route('campaign-admin.dashboard') : (auth()->user()->hasRole('coordinator') ? route('coordinator.dashboard') : route('dashboard')) }}" class="me-5 flex items-center gap-3" wire:navigate>
                @php
                    $campaign = auth()->user()->campaigns->first();
                @endphp

                @if($campaign && $campaign->hasLogo())
                    <img src="{{ $campaign->public_logo_url }}" alt="Logo" class="size-8 rounded-md object-cover">
                    <div class="flex flex-col">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">
                            {{ $campaign->name }}
                        </span>
                        @if(auth()->user()->hasRole('admin_campaign'))
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">Administrador de Campaña</span>
                        @elseif(auth()->user()->hasRole('coordinator'))
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">Coordinador</span>
                        @endif
                    </div>
                @else
                    <x-app-logo-icon class="h-8 w-auto" />
                @endif
            </a>

            <flux:navlist variant="outline">
                @if(auth()->user()->hasRole('admin_campaign'))
                    <flux:navlist.group :heading="__('Campaign Admin')" class="grid">
                        <flux:navlist.item icon="chart-bar" :href="route('campaign-admin.dashboard')" :current="request()->routeIs('campaign-admin.*')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    </flux:navlist.group>
                @elseif(auth()->user()->hasRole('coordinator'))
                    <flux:navlist.group :heading="__('Coordinación')" class="grid">
                        <flux:navlist.item icon="home" :href="route('coordinator.dashboard')" :current="request()->routeIs('coordinator.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                        <flux:navlist.item icon="users" :href="route('coordinator.leaders')" :current="request()->routeIs('coordinator.leaders*')" wire:navigate>{{ __('Líderes') }}</flux:navlist.item>
                        <flux:navlist.item icon="bolt" href="/coordinator/dia-d" :current="request()->is('coordinator/dia-d')" wire:navigate>{{ __('Día D') }}</flux:navlist.item>
                    </flux:navlist.group>
                @else
                    <flux:navlist.group :heading="__('Platform')" class="grid">
                        <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endif
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                    data-test="sidebar-menu-button"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
