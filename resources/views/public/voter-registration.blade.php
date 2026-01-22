<x-layouts.public :title="'Registro de votantes'">
    <div class="relative overflow-hidden bg-gray-50 dark:bg-gray-950">
        <div class="absolute inset-0 bg-gradient-to-b from-gray-100 to-transparent dark:from-white/5"></div>

        <div class="relative mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            @php
                $campaign = $invitation->campaign;
                $campaignLogoUrl = $campaign?->logo_path
                    ? route('public.campaign-logo', ['filename' => basename($campaign->logo_path)])
                    : null;
            @endphp

            @if ($campaignLogoUrl)
                <div class="mb-8">
                    <img
                        src="{{ $campaignLogoUrl }}"
                        alt="{{ $campaign?->name ? 'Imagen de '.$campaign->name : 'Imagen de campaña' }}"
                        class="h-24 w-full object-cover sm:h-32 lg:h-40"
                        loading="eager"
                    />
                </div>
            @else
                <div class="mb-8 flex items-center justify-center">
                    <img
                        src="{{ asset('images/logo-sigma_small.webp') }}"
                        alt="{{ config('app.name') }}"
                        class="h-12 w-auto"
                        loading="eager"
                    />
                </div>
            @endif

            <div class="flex flex-col items-center gap-3 text-center">
                <div class="max-w-2xl">
                    <h1 class="text-balance text-2xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                        Registro de votantes
                    </h1>
                    <p class="mt-2 text-pretty text-sm text-gray-600 dark:text-gray-300 sm:text-base">
                        Completa el formulario. El votante quedará asociado al líder indicado por este enlace.
                    </p>
                </div>
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

            <div class="mt-8 grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-zinc-900 dark:ring-white/10">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Información del enlace</h2>

                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">Candidato</dt>
                                <dd class="text-right font-medium text-gray-900 dark:text-white">{{ $campaign?->candidate_name ?? '—' }}</dd>
                            </div>

                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">Campaña</dt>
                                <dd class="text-right font-medium text-gray-900 dark:text-white">{{ $campaign?->name ?? '—' }}</dd>
                            </div>

                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">Coordinador</dt>
                                <dd class="text-right font-medium text-gray-900 dark:text-white">{{ $invitation->coordinator?->name ?? '—' }}</dd>
                            </div>

                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">Líder</dt>
                                <dd class="text-right font-medium text-gray-900 dark:text-white">{{ $invitation->leader?->name ?? '—' }}</dd>
                            </div>

                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">Municipio</dt>
                                <dd class="text-right font-medium text-gray-900 dark:text-white">{{ $invitation->municipality?->name ?? '—' }}</dd>
                            </div>
                        </dl>

                        <p class="mt-6 text-xs text-gray-500 dark:text-gray-400">
                            Si necesitas registrar más de un votante, puedes volver a abrir este enlace y completar el formulario nuevamente.
                        </p>
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-zinc-900 dark:ring-white/10 sm:p-8">
                        <form method="POST" action="{{ route('public.voters.register.submit', $token) }}" class="space-y-6">
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
                                        <flux:select id="department_id" name="department_id">
                                            <option value="" selected>Selecciona un departamento</option>
                                            @foreach ($departments as $department)
                                                <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>
                                                    {{ $department->name }}
                                                </option>
                                            @endforeach
                                        </flux:select>

                                        <flux:select id="municipality_id" name="municipality_id" required>
                                            <option value="" selected disabled>Selecciona un municipio</option>
                                            @foreach ($municipalities as $municipality)
                                                <option value="{{ $municipality->id }}" data-department-id="{{ $municipality->department_id }}" @selected(old('municipality_id') == $municipality->id)>
                                                    {{ $municipality->name }}
                                                </option>
                                            @endforeach
                                        </flux:select>
                                    @endif
                                </flux:field>
                            </div>

	                            <div class="grid gap-4 sm:grid-cols-2">
	                                <flux:field>
	                                    <flux:label for="polling_place_id">Puesto de votación</flux:label>
	                                    @if (! $invitation->municipality_id)
	                                        <flux:select id="polling_place_id" name="polling_place_id" disabled>
	                                            <option value="" selected>Selecciona un puesto (opcional)</option>
	                                        </flux:select>
	                                    @else
	                                        <flux:select id="polling_place_id" name="polling_place_id">
	                                            <option value="" selected>Selecciona un puesto (opcional)</option>
	                                            @foreach (\App\Models\PollingPlace::query()->where('municipality_id', $invitation->municipality_id)->orderBy('name')->get(['id', 'name', 'max_tables']) as $pollingPlace)
	                                                <option
	                                                    value="{{ $pollingPlace->id }}"
	                                                    data-max-tables="{{ $pollingPlace->max_tables }}"
	                                                    @selected(old('polling_place_id') == $pollingPlace->id)
	                                                >
	                                                    {{ $pollingPlace->name }}
	                                                </option>
	                                            @endforeach
	                                        </flux:select>
	                                    @endif
	                                    <p id="polling_place_help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
	                                        @if ($invitation->municipality_id)
	                                            Selecciona el puesto de votación del votante (opcional).
	                                        @else
                                            Selecciona un municipio para ver los puestos disponibles.
                                        @endif
                                    </p>
                                </flux:field>

	                                <flux:field>
	                                    <flux:label for="polling_table_number">Número de mesa</flux:label>
	                                    @if (! old('polling_place_id') && ! $invitation->municipality_id)
	                                        <flux:input
	                                            id="polling_table_number"
	                                            name="polling_table_number"
	                                            type="number"
	                                            inputmode="numeric"
	                                            min="1"
	                                            value="{{ old('polling_table_number') }}"
	                                            disabled
	                                        />
	                                    @else
	                                        <flux:input
	                                            id="polling_table_number"
	                                            name="polling_table_number"
	                                            type="number"
	                                            inputmode="numeric"
	                                            min="1"
	                                            value="{{ old('polling_table_number') }}"
	                                        />
	                                    @endif
	                                    <p id="polling_table_help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
	                                        Debe ser menor o igual al máximo de mesas del puesto seleccionado.
	                                    </p>
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

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <flux:button type="submit" variant="primary" class="sm:flex-1">
                                    Enviar registro
                                </flux:button>

                                <flux:button type="button" variant="ghost" class="sm:flex-1" onclick="window.location='{{ route('home') }}'">
                                    Volver
                                </flux:button>
                            </div>

                            <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                                Tus datos se usarán únicamente para fines electorales de la campaña.
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (! $invitation->municipality_id)
        <script>
            (function () {
                const departmentSelect = document.getElementById('department_id');
                const municipalitySelect = document.getElementById('municipality_id');
                const pollingPlaceSelect = document.getElementById('polling_place_id');
                const pollingPlaceHelp = document.getElementById('polling_place_help');
                const pollingTableInput = document.getElementById('polling_table_number');

                if (!municipalitySelect || !pollingPlaceSelect || !pollingTableInput) return;

                const allMunicipalityOptions = Array.from(municipalitySelect.querySelectorAll('option[data-department-id]'));
                const municipalityPlaceholder = municipalitySelect.querySelector('option[value=\"\"]');

                function filterMunicipalities() {
                    const departmentId = departmentSelect?.value;
                    municipalitySelect.innerHTML = '';
                    if (municipalityPlaceholder) {
                        municipalitySelect.appendChild(municipalityPlaceholder);
                    }

                    for (const option of allMunicipalityOptions) {
                        if (!departmentId || option.dataset.departmentId === departmentId) {
                            municipalitySelect.appendChild(option);
                        }
                    }

                    municipalitySelect.disabled = !departmentId;
                    municipalitySelect.value = '';
                }

                function setLoading(isLoading) {
                    pollingPlaceSelect.disabled = isLoading || !municipalitySelect.value;
                    pollingTableInput.disabled = isLoading || !pollingPlaceSelect.value;
                    pollingPlaceHelp.textContent = isLoading
                        ? 'Cargando puestos de votación…'
                        : (municipalitySelect.value ? 'Selecciona un puesto (opcional).' : 'Selecciona un municipio para ver los puestos disponibles.');
                }

                async function loadPollingPlaces() {
                    const municipalityId = municipalitySelect.value;
                    pollingPlaceSelect.innerHTML = '<option value="" selected>Selecciona un puesto (opcional)</option>';
                    pollingTableInput.value = '';
                    pollingTableInput.removeAttribute('max');

                    if (!municipalityId) {
                        setLoading(false);
                        return;
                    }

                    setLoading(true);

                    try {
                        const response = await fetch(`{{ route('public.polling-places.options') }}?municipality_id=${encodeURIComponent(municipalityId)}`, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) throw new Error('Request failed');

                        const places = await response.json();

                        for (const place of places) {
                            const option = document.createElement('option');
                            option.value = place.id;
                            option.textContent = place.name;
                            option.dataset.maxTables = place.max_tables;
                            pollingPlaceSelect.appendChild(option);
                        }
                    } catch (e) {
                        pollingPlaceHelp.textContent = 'No fue posible cargar los puestos. Intenta nuevamente.';
                    } finally {
                        setLoading(false);
                    }
                }

                function syncTableMax() {
                    const selected = pollingPlaceSelect.options[pollingPlaceSelect.selectedIndex];
                    const maxTables = selected?.dataset?.maxTables;
                    pollingTableInput.disabled = !pollingPlaceSelect.value;

                    if (maxTables) {
                        pollingTableInput.max = maxTables;
                    } else {
                        pollingTableInput.removeAttribute('max');
                    }
                }

                departmentSelect?.addEventListener('change', function () {
                    filterMunicipalities();
                    loadPollingPlaces().then(syncTableMax);
                });
                municipalitySelect.addEventListener('change', loadPollingPlaces);
                pollingPlaceSelect.addEventListener('change', syncTableMax);

                filterMunicipalities();
                loadPollingPlaces().then(syncTableMax);
            })();
        </script>
    @endif
</x-layouts.public>
