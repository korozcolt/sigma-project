{{--
    Registraduria browser modal.
    Always rendered by Placeholder (Livewire controls sessionId + isOpen).
    On init: appends itself to <body> to escape Filament's CSS transform stacking context.
    Note: use x-on: instead of @ for Alpine events (avoids Blade directive conflicts).
--}}
<div
    x-data="{
        sessionId: @js($sessionId),
        isOpen: @js($isOpen),
        status: 'pending',
        viewport: { width: 1280, height: 800 },
        screenshotSrc: '',
        statusInterval: null,
        screenshotInterval: null,

        init() {
            if ($el.parentElement !== document.body) {
                document.body.appendChild($el);
            }
            // Control display manually — x-show only sets display:none/''
            // but we need display:flex, so we manage it via $watch
            this._show = () => {
                $el.style.display = (this.isOpen && this.sessionId) ? 'flex' : 'none';
            };
            this.$watch('isOpen', () => this._show());
            this._show();
            if (this.isOpen && this.sessionId) {
                this.start();
            }
        },

        destroy() { this.stop(); },

        start() {
            this.screenshotSrc = '/registraduria/screenshot/' + this.sessionId + '?t=' + Date.now();
            fetch('/registraduria/viewport/' + this.sessionId)
                .then(r => r.json())
                .then(d => { if (d && d.width) this.viewport = d; })
                .catch(() => {});

            this.screenshotInterval = setInterval(() => {
                if (!this.isOpen) return;
                this.screenshotSrc = '/registraduria/screenshot/' + this.sessionId + '?t=' + Date.now();
            }, 400);

            this.statusInterval = setInterval(() => {
                if (!this.isOpen) { this.stop(); return; }
                fetch('/registraduria/result/' + this.sessionId)
                    .then(r => r.json())
                    .then(d => {
                        this.status = d.status ?? 'error';
                        if (d.status === 'done' || d.status === 'error') {
                            this.stop();
                            setTimeout(() => $wire.handleRegistraduriaResult(d), 1200);
                        }
                    })
                    .catch(() => {});
            }, 2000);
        },

        stop() {
            if (this.screenshotInterval) { clearInterval(this.screenshotInterval); this.screenshotInterval = null; }
            if (this.statusInterval) { clearInterval(this.statusInterval); this.statusInterval = null; }
        },

        forwardClick(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const x = Math.round((event.clientX - rect.left) * (this.viewport.width / rect.width));
            const y = Math.round((event.clientY - rect.top) * (this.viewport.height / rect.height));
            fetch('/registraduria/click/' + this.sessionId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ x, y }),
            }).catch(() => {});
        },

        statusLabel() {
            return { pending: 'Cargando...', waiting_captcha: 'Resuelve el CAPTCHA', done: 'Listo', error: 'Error' }[this.status] ?? this.status;
        },

        statusClass() {
            return { pending: 'bg-gray-100 text-gray-600', waiting_captcha: 'bg-amber-100 text-amber-700', done: 'bg-green-100 text-green-700', error: 'bg-red-100 text-red-700' }[this.status] ?? 'bg-gray-100 text-gray-600';
        }
    }"
    x-on:keydown.escape.window="$wire.closeRegistraduriaBrowser()"
    style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; background:rgba(0,0,0,0.6); flex-direction:column; align-items:center; justify-content:flex-start; padding-top:2.5rem;"
>
    <div class="bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden mx-4"
         style="width:min(1000px,92vw); max-height:88vh;">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50 rounded-t-2xl shrink-0">
            <div class="flex items-center gap-3">
                <span class="font-semibold text-gray-800 text-sm">Registraduría — Puesto de votación</span>
                <span class="text-xs font-medium px-2.5 py-0.5 rounded-full" :class="statusClass()" x-text="statusLabel()"></span>
            </div>
            <button type="button" x-on:click="$wire.closeRegistraduriaBrowser()" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Instruction --}}
        <div class="bg-blue-50 border-b border-blue-100 px-5 py-2 shrink-0">
            <p class="text-xs text-blue-700">Haz clic sobre la imagen para interactuar. El formulario se llenará automáticamente.</p>
        </div>

        {{-- Screenshot --}}
        <div class="flex-1 overflow-hidden bg-gray-100 p-2">
            <template x-if="status !== 'error'">
                <img
                    :src="screenshotSrc"
                    alt="Registraduría"
                    class="w-full rounded-lg border border-gray-200 cursor-pointer select-none object-contain object-top"
                    style="max-height: calc(88vh - 110px);"
                    x-on:click="forwardClick($event)"
                    draggable="false"
                />
            </template>
            <template x-if="status === 'error'">
                <div class="flex flex-col items-center justify-center h-48 gap-3 text-red-600">
                    <p class="text-sm font-medium">Error al consultar la Registraduría</p>
                    <button type="button" x-on:click="$wire.closeRegistraduriaBrowser()" class="text-xs underline text-red-500">Cerrar</button>
                </div>
            </template>
        </div>
    </div>
</div>
