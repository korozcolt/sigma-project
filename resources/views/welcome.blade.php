<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Sistema de Gestión de Campañas Políticas</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white dark:bg-zinc-950 antialiased">
    <!-- Navigation -->
    <nav class="fixed top-0 z-50 w-full border-b border-zinc-200/50 bg-white/80 backdrop-blur-xl dark:border-zinc-800/50 dark:bg-zinc-950/80">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center">
                    <img src="{{ asset('images/logo-sigma_small.webp') }}" alt="{{ config('app.name') }}" class="h-10 w-auto" />
                </div>
                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-900 shadow-sm transition-all hover:border-zinc-300 hover:shadow dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:hover:border-zinc-600">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-[#e74f32] to-[#0172b9] px-4 py-2 text-sm font-medium text-white shadow-lg shadow-[#e74f32]/30 transition-all hover:shadow-xl hover:shadow-[#0172b9]/40">
                                Iniciar Sesión
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                </svg>
                            </a>
                        @endauth
                    </div>
                @endif
            </div>
        </div>
    </nav>

    <!-- Hero Section with Grid Background -->
    <section class="relative overflow-hidden pt-16">
        <!-- Animated Grid Background -->
        <div class="absolute inset-0 -z-10">
            <div class="absolute inset-0 bg-gradient-to-b from-blue-50/50 via-white to-white dark:from-blue-950/20 dark:via-zinc-950 dark:to-zinc-950"></div>
            <svg class="absolute inset-0 h-full w-full stroke-zinc-200/50 dark:stroke-zinc-800/50" aria-hidden="true">
                <defs>
                    <pattern id="grid-pattern" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid-pattern)"/>
            </svg>
            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-white dark:to-zinc-950"></div>
        </div>

        <!-- Glow Effects -->
        <div class="absolute left-1/2 top-0 -z-10 -translate-x-1/2">
            <div class="h-[600px] w-[600px] rounded-full bg-gradient-to-r from-[#e74f32] to-[#ff6b4a] opacity-20 blur-3xl dark:opacity-20"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-32">
            <div class="relative">
                <!-- Decorative Corner Element -->
                <div class="absolute -left-4 -top-4 size-24 rounded-full bg-gradient-to-br from-blue-500/20 to-purple-500/20 blur-2xl"></div>

                <div class="relative text-center">
                    <!-- Logo -->
                    <div class="mb-8 flex justify-center">
                        <img src="{{ asset('images/logo-sigma_small.webp') }}" alt="{{ config('app.name') }}" class="h-20 w-auto sm:h-24" />
                    </div>

                    <!-- Badge -->
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#e74f32]/30 bg-[#e74f32]/10 px-4 py-1.5 text-sm font-medium text-[#e74f32] dark:border-[#e74f32]/50 dark:bg-[#e74f32]/20 dark:text-[#e74f32]">
                        <span class="relative flex size-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#e74f32] opacity-75"></span>
                            <span class="relative inline-flex size-2 rounded-full bg-[#e74f32]"></span>
                        </span>
                        Sistema de Gestión Electoral
                    </div>

                    <h1 class="mx-auto max-w-4xl text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-6xl lg:text-7xl">
                        Gestiona tu
                        <span class="relative inline-block">
                            <span class="relative bg-gradient-to-r from-[#e74f32] via-[#ff6b4a] to-[#e74f32] bg-clip-text text-transparent dark:from-[#ff6b4a] dark:via-[#e74f32] dark:to-[#ff6b4a]">
                                Campaña Política
                            </span>
                            <svg class="absolute -bottom-2 left-0 w-full" height="8" viewBox="0 0 200 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 5.5C40 2.5 80 2 120 3.5C160 5 180 6.5 199 5.5" stroke="url(#gradient-line)" stroke-width="3" stroke-linecap="round"/>
                                <defs>
                                    <linearGradient id="gradient-line" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" style="stop-color:#e74f32"/>
                                        <stop offset="50%" style="stop-color:#ff6b4a"/>
                                        <stop offset="100%" style="stop-color:#e74f32"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                        </span>
                        <br/>
                        <span class="text-zinc-900 dark:text-white">de Forma Inteligente</span>
                    </h1>

                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-zinc-600 dark:text-zinc-400 sm:text-xl">
                        Plataforma integral para la gestión de campañas políticas. Organiza tu equipo, registra votantes y toma decisiones basadas en datos en tiempo real.
                    </p>

                    <div class="mt-10 flex items-center justify-center gap-4">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#0172b9] to-[#e74f32] px-8 py-4 text-base font-semibold text-white shadow-xl shadow-[#0172b9]/30 transition-all hover:scale-105 hover:shadow-2xl hover:shadow-[#e74f32]/40">
                                Ir al Dashboard
                                <svg class="size-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#0172b9] to-[#e74f32] px-8 py-4 text-base font-semibold text-white shadow-xl shadow-[#0172b9]/30 transition-all hover:scale-105 hover:shadow-2xl hover:shadow-[#e74f32]/40">
                                Iniciar Sesión
                                <svg class="size-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                </svg>
                            </a>
                        @endauth
                    </div>

                    <!-- Trust Indicators -->
                    <div class="mt-12 flex flex-wrap items-center justify-center gap-8 text-sm text-zinc-600 dark:text-zinc-400">
                        <div class="flex items-center gap-2">
                            <svg class="size-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <span class="font-medium">Seguro y Confiable</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="size-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <span class="font-medium">Actualizaciones en Tiempo Real</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="size-5 text-purple-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <span class="font-medium">Soporte Dedicado</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section with Bordered Grid -->
    <section class="relative py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-4 py-1.5 text-sm font-medium text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Características Principales
                </div>
                <h2 class="mt-6 text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-5xl">
                    Todo lo que necesitas para
                    <span class="block text-blue-600">tu campaña electoral</span>
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
                    Herramientas profesionales diseñadas para maximizar el rendimiento de tu campaña política
                </p>
            </div>

            <!-- Features Grid with Borders -->
            <div class="relative mt-16">
                <!-- Decorative Border Container -->
                <div class="absolute inset-0 rounded-2xl border border-zinc-200 dark:border-zinc-800"></div>
                <div class="absolute -left-2 -top-2 size-4 rounded-full border-4 border-blue-500 bg-white dark:bg-zinc-950"></div>
                <div class="absolute -right-2 -top-2 size-4 rounded-full border-4 border-purple-500 bg-white dark:bg-zinc-950"></div>
                <div class="absolute -bottom-2 -left-2 size-4 rounded-full border-4 border-green-500 bg-white dark:bg-zinc-950"></div>
                <div class="absolute -bottom-2 -right-2 size-4 rounded-full border-4 border-yellow-500 bg-white dark:bg-zinc-950"></div>

                <div class="relative grid grid-cols-1 gap-px overflow-hidden rounded-2xl bg-zinc-200 dark:bg-zinc-800 lg:grid-cols-3">
                    <!-- Feature 1 -->
                    <div class="group relative bg-white p-8 transition-all hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                        <div class="relative">
                            <div class="inline-flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-[#0172b9] to-[#e74f32] shadow-lg shadow-[#0172b9]/30">
                                <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">
                                Gestión de Equipo
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                Organiza coordinadores, líderes y voluntarios con roles y permisos específicos para cada nivel.
                            </p>
                        </div>
                        <div class="absolute bottom-0 left-0 h-1 w-0 bg-gradient-to-r from-[#0172b9] to-[#e74f32] transition-all group-hover:w-full"></div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="group relative bg-white p-8 transition-all hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                        <div class="relative">
                            <div class="inline-flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-[#e74f32] to-[#0172b9] shadow-lg shadow-[#e74f32]/30">
                                <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">
                                Registro de Votantes
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                Sistema completo de registro y seguimiento de votantes con métricas en tiempo real y análisis predictivo.
                            </p>
                        </div>
                        <div class="absolute bottom-0 left-0 h-1 w-0 bg-gradient-to-r from-[#e74f32] to-[#0172b9] transition-all group-hover:w-full"></div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="group relative bg-white p-8 transition-all hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                        <div class="relative">
                            <div class="inline-flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-[#0172b9] to-[#e74f32] shadow-lg shadow-[#0172b9]/30">
                                <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">
                                Dashboard Estadístico
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                Visualiza el progreso con dashboards interactivos, reportes detallados y gráficos en tiempo real.
                            </p>
                        </div>
                        <div class="absolute bottom-0 left-0 h-1 w-0 bg-gradient-to-r from-[#0172b9] to-[#e74f32] transition-all group-hover:w-full"></div>
                    </div>

                    <!-- Feature 4 -->
                    <div class="group relative bg-white p-8 transition-all hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                        <div class="relative">
                            <div class="inline-flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-yellow-500 to-yellow-600 shadow-lg shadow-yellow-500/30">
                                <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">
                                Call Center
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                Sistema integrado de llamadas para confirmación con seguimiento de intentos y recordatorios automáticos.
                            </p>
                        </div>
                        <div class="absolute bottom-0 left-0 h-1 w-0 bg-gradient-to-r from-yellow-500 to-yellow-600 transition-all group-hover:w-full"></div>
                    </div>

                    <!-- Feature 5 -->
                    <div class="group relative bg-white p-8 transition-all hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                        <div class="relative">
                            <div class="inline-flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-red-600 shadow-lg shadow-red-500/30">
                                <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">
                                Encuestas Personalizadas
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                Crea y aplica encuestas personalizadas para ajustar tu estrategia basándote en feedback real.
                            </p>
                        </div>
                        <div class="absolute bottom-0 left-0 h-1 w-0 bg-gradient-to-r from-red-500 to-red-600 transition-all group-hover:w-full"></div>
                    </div>

                    <!-- Feature 6 -->
                    <div class="group relative bg-white p-8 transition-all hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                        <div class="relative">
                            <div class="inline-flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 shadow-lg shadow-indigo-500/30">
                                <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">
                                Organización Territorial
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                Estructura por departamentos, municipios y barrios para cobertura completa del territorio.
                            </p>
                        </div>
                        <div class="absolute bottom-0 left-0 h-1 w-0 bg-gradient-to-r from-indigo-500 to-indigo-600 transition-all group-hover:w-full"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="relative overflow-hidden bg-gradient-to-b from-white to-zinc-50 py-20 dark:from-zinc-950 dark:to-zinc-900 sm:py-28">
        <div class="absolute inset-0 -z-10">
            <svg class="absolute inset-0 h-full w-full stroke-zinc-200/30 dark:stroke-zinc-800/30" aria-hidden="true">
                <defs>
                    <pattern id="stats-pattern" width="80" height="80" patternUnits="userSpaceOnUse">
                        <circle cx="40" cy="40" r="1" fill="currentColor"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#stats-pattern)"/>
            </svg>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-4 py-1.5 text-sm font-medium text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        ¿Por qué elegirnos?
                    </div>
                    <h2 class="mt-6 text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-5xl">
                        La mejor plataforma
                        <span class="block text-blue-600">para tu campaña</span>
                    </h2>
                    <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                        Diseñada específicamente para las necesidades de campañas políticas modernas en América Latina.
                    </p>

                    <dl class="mt-10 space-y-6">
                        <div class="relative flex gap-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 shadow-lg shadow-blue-500/30">
                                <svg class="size-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <dt class="text-base font-semibold text-zinc-900 dark:text-white">
                                    Interfaz Intuitiva
                                </dt>
                                <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    Diseñada para todos los niveles de experiencia técnica
                                </dd>
                            </div>
                        </div>

                        <div class="relative flex gap-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-green-600 shadow-lg shadow-green-500/30">
                                <svg class="size-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div>
                                <dt class="text-base font-semibold text-zinc-900 dark:text-white">
                                    Actualizaciones en Tiempo Real
                                </dt>
                                <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    Datos sincronizados al instante en todos los dispositivos
                                </dd>
                            </div>
                        </div>

                        <div class="relative flex gap-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 shadow-lg shadow-purple-500/30">
                                <svg class="size-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <div>
                                <dt class="text-base font-semibold text-zinc-900 dark:text-white">
                                    Máxima Seguridad
                                </dt>
                                <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    Protección de datos con los más altos estándares de seguridad
                                </dd>
                            </div>
                        </div>

                        <div class="relative flex gap-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-yellow-500 to-yellow-600 shadow-lg shadow-yellow-500/30">
                                <svg class="size-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                                </svg>
                            </div>
                            <div>
                                <dt class="text-base font-semibold text-zinc-900 dark:text-white">
                                    Altamente Escalable
                                </dt>
                                <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    Desde campañas locales hasta nacionales sin límites
                                </dd>
                            </div>
                        </div>
                    </dl>
                </div>

                <!-- Dashboard Preview -->
                <div class="flex items-center justify-center lg:justify-end">
                    <div class="relative">
                        <div class="absolute -inset-6 rounded-3xl bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 opacity-20 blur-3xl"></div>
                        <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-800">
                                <div class="flex items-center gap-2">
                                    <div class="size-3 rounded-full bg-red-500"></div>
                                    <div class="size-3 rounded-full bg-yellow-500"></div>
                                    <div class="size-3 rounded-full bg-green-500"></div>
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="space-y-4">
                                    <div class="rounded-xl border border-zinc-200 bg-gradient-to-br from-blue-50 to-blue-100 p-6 dark:border-zinc-800 dark:from-blue-950/50 dark:to-blue-900/30">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Total Votantes</p>
                                                <p class="mt-2 text-3xl font-bold text-blue-600">15,342</p>
                                            </div>
                                            <div class="rounded-xl bg-blue-600 p-3 shadow-lg shadow-blue-500/30">
                                                <svg class="size-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="mt-4 flex items-center gap-2 text-sm">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                <svg class="size-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M12 7a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 11-2 0V9.414l-3.293 3.293a1 1 0 01-1.414 0L8 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0L12 10.586 14.586 8H13a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                                +12.5%
                                            </span>
                                            <span class="text-xs text-zinc-600 dark:text-zinc-400">vs mes anterior</span>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-gradient-to-br from-green-50 to-green-100 p-6 dark:border-zinc-800 dark:from-green-950/50 dark:to-green-900/30">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Confirmados</p>
                                                <p class="mt-2 text-3xl font-bold text-green-600">12,891</p>
                                            </div>
                                            <div class="rounded-xl bg-green-600 p-3 shadow-lg shadow-green-500/30">
                                                <svg class="size-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-green-200 dark:bg-green-900/30">
                                            <div class="h-full w-[84%] rounded-full bg-green-600"></div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-gradient-to-br from-purple-50 to-purple-100 p-6 dark:border-zinc-800 dark:from-purple-950/50 dark:to-purple-900/30">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Tasa de Conversión</p>
                                                <p class="mt-2 text-3xl font-bold text-purple-600">84.0%</p>
                                            </div>
                                            <div class="rounded-xl bg-purple-600 p-3 shadow-lg shadow-purple-500/30">
                                                <svg class="size-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="relative overflow-hidden py-20 sm:py-28">
        <div class="absolute inset-0 -z-10">
            <div class="absolute inset-0 bg-gradient-to-br from-[#0172b9] via-[#0172b9] to-[#e74f32]"></div>
            <svg class="absolute inset-0 h-full w-full opacity-10" aria-hidden="true">
                <defs>
                    <pattern id="cta-pattern" width="60" height="60" patternUnits="userSpaceOnUse">
                        <path d="M0 60L60 0M-15 15L15 -15M45 75L75 45" stroke="white" stroke-width="2"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#cta-pattern)"/>
            </svg>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm font-medium text-white backdrop-blur-sm">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-white"></span>
                    </span>
                    Comienza Hoy
                </div>

                <h2 class="mx-auto mt-6 max-w-3xl text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    Lleva tu campaña al
                    <span class="block">siguiente nivel</span>
                </h2>

                <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-blue-100">
                    Únete a las campañas políticas que confían en {{ config('app.name') }} para alcanzar sus objetivos electorales y maximizar su impacto.
                </p>

                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="group inline-flex items-center gap-2 rounded-xl bg-white px-8 py-4 text-base font-semibold text-[#e74f32] shadow-xl transition-all hover:scale-105 hover:shadow-2xl hover:text-[#0172b9]">
                            Ir al Dashboard
                            <svg class="size-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="group inline-flex items-center gap-2 rounded-xl bg-white px-8 py-4 text-base font-semibold text-[#e74f32] shadow-xl transition-all hover:scale-105 hover:shadow-2xl hover:text-[#0172b9]">
                            Iniciar Sesión
                            <svg class="size-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                        </a>
                    @endauth
                </div>

                <div class="mt-12 flex flex-wrap items-center justify-center gap-8 text-sm text-white/80">
                    <div class="flex items-center gap-2">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span>Plataforma Segura</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span>Datos en Tiempo Real</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span>Soporte Dedicado</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-zinc-200 bg-zinc-50 py-12 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                <div class="flex items-center">
                    <img src="{{ asset('images/logo-sigma_small.webp') }}" alt="{{ config('app.name') }}" class="h-10 w-auto" />
                </div>

                <div class="flex flex-col items-center gap-4 sm:flex-row sm:gap-8">
                    <div class="flex items-center gap-6">
                        <a href="#" class="text-sm text-zinc-600 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                            Términos
                        </a>
                        <a href="#" class="text-sm text-zinc-600 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                            Privacidad
                        </a>
                        <a href="#" class="text-sm text-zinc-600 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                            Contacto
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 border-t border-zinc-200 pt-8 dark:border-zinc-800">
                <p class="text-center text-xs text-zinc-600 dark:text-zinc-400">
                    © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
