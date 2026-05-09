{{--
    Registraduria headless browser modal.
    Variables:
        $sessionId  string  — Python service session ID
    Alpine.js handles:
        - Screenshot polling (img src updated every 400ms)
        - Status polling (fetch /registraduria/result every 2s)
        - Click forwarding (scaled coordinates POSTed to /registraduria/click)
        - Viewport fetch on init (for coordinate scaling)
    Note: Use x-on: instead of @ shorthand to avoid Blade directive conflicts.
--}}
<div
    x-data="{
        sessionId: @js($sessionId),
        status: 'pending',
        error: null,
        viewport: { width: 1280, height: 800 },
        screenshotSrc: '',
        statusInterval: null,
        screenshotInterval: null,

        init() {
            this.screenshotSrc = '/registraduria/screenshot/' + this.sessionId + '?t=' + Date.now();

            fetch('/registraduria/viewport/' + this.sessionId)
                .then(r => r.json())
                .then(data => { if (data && data.width) this.viewport = data; })
                .catch(() => {});

            this.screenshotInterval = setInterval(() => {
                this.screenshotSrc = '/registraduria/screenshot/' + this.sessionId + '?t=' + Date.now();
            }, 400);

            this.statusInterval = setInterval(() => {
                fetch('/registraduria/result/' + this.sessionId)
                    .then(r => r.json())
                    .then(data => {
                        this.status = data.status ?? 'error';

                        if (data.status === 'done' || data.status === 'error') {
                            clearInterval(this.statusInterval);
                            clearInterval(this.screenshotInterval);
                            this.statusInterval = null;
                            this.screenshotInterval = null;

                            setTimeout(() => {
                                $wire.handleRegistraduriaResult(data);
                            }, 1500);
                        }
                    })
                    .catch(() => { this.error = 'Error de comunicación con el servicio.'; });
            }, 2000);
        },

        destroy() {
            if (this.statusInterval) clearInterval(this.statusInterval);
            if (this.screenshotInterval) clearInterval(this.screenshotInterval);
        },

        forwardClick(event) {
            const img = event.target;
            const rect = img.getBoundingClientRect();
            const scaleX = this.viewport.width / rect.width;
            const scaleY = this.viewport.height / rect.height;
            const x = Math.round((event.clientX - rect.left) * scaleX);
            const y = Math.round((event.clientY - rect.top) * scaleY);

            fetch('/registraduria/click/' + this.sessionId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ x, y })
            }).catch(() => {});
        },

        statusLabel() {
            const labels = {
                pending: 'Cargando...',
                waiting_captcha: 'Resuelve el CAPTCHA',
                done: 'Completado',
                error: 'Error',
            };
            return labels[this.status] ?? this.status;
        },

        statusColor() {
            const colors = {
                pending: 'bg-gray-100 text-gray-700',
                waiting_captcha: 'bg-yellow-100 text-yellow-800',
                done: 'bg-green-100 text-green-800',
                error: 'bg-red-100 text-red-800',
            };
            return colors[this.status] ?? 'bg-gray-100 text-gray-700';
        }
    }"
    class="fixed inset-0 z-50 flex items-start justify-center bg-black/60 pt-8"
    x-on:keydown.escape.window="$wire.closeRegistraduriaBrowser()"
>
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4 flex flex-col overflow-hidden"
         style="max-height: 90vh;">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 shrink-0">
            <div class="flex items-center gap-3">
                <span class="font-semibold text-gray-800 text-sm">Registraduría — Consulta de puesto de votación</span>
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="statusColor()"
                    x-text="statusLabel()"
                ></span>
            </div>
            <button
                type="button"
                class="text-gray-400 hover:text-gray-600 transition-colors"
                x-on:click="$wire.closeRegistraduriaBrowser()"
                title="Cerrar"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Instruction bar --}}
        <div class="bg-blue-50 border-b border-blue-100 px-5 py-2 shrink-0">
            <p class="text-xs text-blue-700">
                Haz clic directamente sobre la imagen para interactuar con la página.
                El formulario se llenará automáticamente al obtener el resultado.
            </p>
        </div>

        {{-- Screenshot area --}}
        <div class="flex-1 overflow-auto bg-gray-100 p-3">
            <template x-if="status !== 'error'">
                <img
                    :src="screenshotSrc"
                    alt="Registraduría"
                    class="w-full rounded cursor-pointer select-none object-contain object-top"
                    style="max-height: calc(85vh - 130px);"
                    x-on:click="forwardClick($event)"
                />
            </template>
            <template x-if="status === 'error'">
                <div class="flex flex-col items-center justify-center h-48 gap-2 text-red-600">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    <p class="text-sm font-medium" x-text="error ?? 'Ocurrió un error al consultar la Registraduría.'"></p>
                    <button
                        type="button"
                        class="text-xs underline text-red-500 hover:text-red-700"
                        x-on:click="$wire.closeRegistraduriaBrowser()"
                    >Cerrar</button>
                </div>
            </template>
        </div>
    </div>
</div>
