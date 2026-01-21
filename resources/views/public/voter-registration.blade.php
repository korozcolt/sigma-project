<x-layouts.auth :title="'Registro de votantes'">
    <div class="min-h-screen bg-gray-50 px-4 py-12 dark:bg-gray-900 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-xl flex-col gap-6">
            <div class="text-center">
                <flux:heading>Registro de votantes</flux:heading>
                <flux:text class="mt-2">
                    Completa tus datos. El registro quedará asociado al equipo indicado a continuación.
                </flux:text>
            </div>

            @if (session('success'))
                <flux:callout type="success">
                    {{ session('success') }}
                </flux:callout>
            @endif

            @if (session('error'))
                <flux:callout type="danger">
                    {{ session('error') }}
                </flux:callout>
            @endif

            @if ($errors->any())
                <flux:callout type="danger">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </flux:callout>
            @endif

            <flux:card>
                <div class="space-y-2">
                    <flux:heading size="sm">Información del enlace</flux:heading>

                    <flux:text class="text-sm text-gray-600 dark:text-gray-300">
                        <span class="font-medium">Campaña:</span> {{ $invitation->campaign?->name ?? '—' }}
                    </flux:text>

                    @if ($invitation->coordinator)
                        <flux:text class="text-sm text-gray-600 dark:text-gray-300">
                            <span class="font-medium">Coordinador:</span> {{ $invitation->coordinator->name }}
                        </flux:text>
                    @endif

                    @if ($invitation->leader)
                        <flux:text class="text-sm text-gray-600 dark:text-gray-300">
                            <span class="font-medium">Líder:</span> {{ $invitation->leader->name }}
                        </flux:text>
                    @endif

                    @if ($invitation->municipality)
                        <flux:text class="text-sm text-gray-600 dark:text-gray-300">
                            <span class="font-medium">Municipio:</span> {{ $invitation->municipality->name }}
                        </flux:text>
                    @endif
                </div>

                <flux:separator class="my-6" />

                <form method="POST" action="{{ route('public.voters.register.submit', $token) }}" class="space-y-5">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label for="document_number">Número de documento *</flux:label>
                            <flux:input
                                id="document_number"
                                name="document_number"
                                type="text"
                                inputmode="numeric"
                                maxlength="10"
                                value="{{ old('document_number') }}"
                                required
                                autocomplete="off"
                            />
                        </flux:field>

                        <flux:field>
                            <flux:label for="phone">Teléfono principal *</flux:label>
                            <flux:input
                                id="phone"
                                name="phone"
                                type="tel"
                                inputmode="tel"
                                maxlength="10"
                                value="{{ old('phone') }}"
                                required
                                autocomplete="tel"
                            />
                        </flux:field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label for="first_name">Nombres *</flux:label>
                            <flux:input
                                id="first_name"
                                name="first_name"
                                type="text"
                                value="{{ old('first_name') }}"
                                required
                                autocomplete="given-name"
                            />
                        </flux:field>

                        <flux:field>
                            <flux:label for="last_name">Apellidos *</flux:label>
                            <flux:input
                                id="last_name"
                                name="last_name"
                                type="text"
                                value="{{ old('last_name') }}"
                                required
                                autocomplete="family-name"
                            />
                        </flux:field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label for="secondary_phone">Teléfono secundario</flux:label>
                            <flux:input
                                id="secondary_phone"
                                name="secondary_phone"
                                type="tel"
                                inputmode="tel"
                                maxlength="10"
                                value="{{ old('secondary_phone') }}"
                            />
                        </flux:field>

                        <flux:field>
                            <flux:label for="email">Correo electrónico</flux:label>
                            <flux:input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                autocomplete="email"
                            />
                        </flux:field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label for="birth_date">Fecha de nacimiento</flux:label>
                            <flux:input
                                id="birth_date"
                                name="birth_date"
                                type="date"
                                value="{{ old('birth_date') }}"
                                max="{{ now()->subYears(18)->format('Y-m-d') }}"
                            />
                        </flux:field>

                        <flux:field>
                            <flux:label for="municipality_id">Municipio *</flux:label>
                            @if ($invitation->municipality_id)
                                <flux:input
                                    value="{{ $invitation->municipality?->name ?? '—' }}"
                                    disabled
                                    readonly
                                />
                                <input type="hidden" name="municipality_id" value="{{ $invitation->municipality_id }}">
                            @else
                                <flux:select id="municipality_id" name="municipality_id" required>
                                    <option value="" selected disabled>Selecciona un municipio</option>
                                    @foreach ($municipalities as $municipality)
                                        <option value="{{ $municipality->id }}" @selected(old('municipality_id') == $municipality->id)>
                                            {{ $municipality->name }}
                                        </option>
                                    @endforeach
                                </flux:select>
                            @endif
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label for="address">Dirección</flux:label>
                        <flux:textarea
                            id="address"
                            name="address"
                            rows="3"
                            placeholder="Escribe tu dirección"
                        >{{ old('address') }}</flux:textarea>
                    </flux:field>

                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" class="flex-1">
                            Enviar registro
                        </flux:button>

                        <flux:button type="button" variant="ghost" class="flex-1" onclick="window.location='{{ route('home') }}'">
                            Volver
                        </flux:button>
                    </div>

                    <flux:text class="text-center text-sm text-gray-500">
                        Tus datos se usarán únicamente para fines electorales de la campaña.
                    </flux:text>
                </form>
            </flux:card>
        </div>
    </div>
</x-layouts.auth>
