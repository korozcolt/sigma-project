<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <!-- Mobile Navigation Header -->
        <flux:header class="sticky top-0 z-50 border-b border-zinc-200 bg-white/95 backdrop-blur-sm supports-[backdrop-filter]:bg-white/80 dark:border-zinc-700 dark:bg-zinc-900/95 dark:supports-[backdrop-filter]:bg-zinc-900/80">
            <div class="flex items-center gap-3">
                @php
                    $campaign = auth()->user()->campaigns->first();
                @endphp

                <a href="{{ route('leader.dashboard') }}" wire:navigate>
                    @if($campaign && $campaign->hasLogo())
                        <img src="{{ $campaign->public_logo_url }}" alt="Logo" class="h-8 w-auto rounded-md object-cover">
                    @else
                        <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
                        </div>
                    @endif
                </a>
                <div class="flex flex-col">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ $campaign?->name ?? config('app.name') }}
                    </span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">Líder</span>
                </div>
            </div>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                    size="sm"
                />

                <flux:menu class="w-64">
                    <div class="p-2 text-sm">
                        <div class="flex items-center gap-2 px-1 py-1.5">
                            <span class="relative flex h-10 w-10 shrink-0 overflow-hidden rounded-lg">
                                <span class="flex h-full w-full items-center justify-center rounded-lg bg-zinc-200 text-black dark:bg-zinc-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold text-zinc-900 dark:text-white">{{ auth()->user()->name }}</span>
                                <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Configuración') }}</flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Cerrar Sesión') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <!-- Main Content -->
        <main class="mx-auto max-w-2xl px-4 py-6">
            {{ $slot }}
        </main>

        <!-- Bottom Navigation -->
        <nav class="fixed bottom-0 left-0 right-0 z-40 border-t border-zinc-200 bg-white/95 backdrop-blur-sm supports-[backdrop-filter]:bg-white/80 dark:border-zinc-700 dark:bg-zinc-900/95 dark:supports-[backdrop-filter]:bg-zinc-900/80">
            <div class="grid grid-cols-4 gap-1 px-2 py-2">
                <a
                    href="{{ route('leader.dashboard') }}"
                    wire:navigate
                    @class([
                        'flex flex-col items-center gap-1 rounded-lg px-3 py-2 text-xs font-medium transition-colors',
                        'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' => request()->routeIs('leader.dashboard'),
                        'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => !request()->routeIs('leader.dashboard'),
                    ])
                >
                    <flux:icon.home variant="outline" class="h-5 w-5" />
                    <span>Inicio</span>
                </a>

                <a
                    href="{{ route('leader.register-voter') }}"
                    wire:navigate
                    @class([
                        'flex flex-col items-center gap-1 rounded-lg px-3 py-2 text-xs font-medium transition-colors',
                        'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' => request()->routeIs('leader.register-voter'),
                        'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => !request()->routeIs('leader.register-voter'),
                    ])
                >
                    <flux:icon.user-plus variant="outline" class="h-5 w-5" />
                    <span>Registrar</span>
                </a>

                <a
                    href="{{ route('leader.my-voters') }}"
                    wire:navigate
                    @class([
                        'flex flex-col items-center gap-1 rounded-lg px-3 py-2 text-xs font-medium transition-colors',
                        'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' => request()->routeIs('leader.my-voters'),
                        'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => !request()->routeIs('leader.my-voters'),
                    ])
                >
                    <flux:icon.users variant="outline" class="h-5 w-5" />
                    <span>Mis Votantes</span>
                </a>

                <a
                    href="/leader/dia-d"
                    wire:navigate
                    @class([
                        'flex flex-col items-center gap-1 rounded-lg px-3 py-2 text-xs font-medium transition-colors',
                        'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' => request()->is('leader/dia-d'),
                        'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => !request()->is('leader/dia-d'),
                    ])
                >
                    <flux:icon.bolt variant="outline" class="h-5 w-5" />
                    <span>Día D</span>
                </a>
            </div>
        </nav>

        <!-- Bottom padding to avoid content hiding behind nav -->
        <div class="h-20"></div>

        @fluxScripts
    </body>
</html>
