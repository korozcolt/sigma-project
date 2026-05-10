{{--
    Registraduria modal — fully automated with 2captcha + session cookies.
    No user interaction needed. Shows progress spinner.
    Note: use x-on: instead of @ (Blade directive conflict).
--}}
<div
    x-data="{
        sessionId: @js($sessionId),
        isOpen: @js($isOpen),
        status: 'pending',
        error: null,
        statusInterval: null,

        statusLabel() {
            return {
                pending:         'Iniciando...',
                loading:         'Cargando página de Registraduría...',
                solving_captcha: 'Resolviendo CAPTCHA (2captcha)...',
                waiting_result:  'Obteniendo resultado...',
                done:            '✅ Datos obtenidos',
                error:           '❌ Error',
            }[this.status] ?? this.status;
        },

        isSpinning() {
            return !['done', 'error'].includes(this.status);
        },

        init() {
            // Capture Livewire component ID BEFORE moving element out of component tree
            this._componentId = $wire.__instance?.id ?? null;
            if ($el.parentElement !== document.body) document.body.appendChild($el);
            this._show();
            this.$watch('isOpen', () => this._show());
            if (this.isOpen && this.sessionId) this.poll();
        },

        _livewire() {
            // After appendChild, $wire is unavailable — use Livewire.find() instead
            return this._componentId ? window.Livewire.find(this._componentId) : null;
        },

        destroy() { this.stopPoll(); },

        _show() {
            $el.style.display = (this.isOpen && this.sessionId) ? 'flex' : 'none';
        },

        poll() {
            this.stopPoll();
            this.statusInterval = setInterval(() => {
                fetch('/registraduria/result/' + this.sessionId)
                    .then(r => r.json())
                    .then(d => {
                        this.status = d.status ?? 'error';
                        if (d.status === 'error') {
                            this.error = d.error ?? 'Error desconocido';
                            this.stopPoll();
                        }
                        if (d.status === 'done' && d.data) {
                            this.stopPoll();
                            // Pass d.data (not d) — method expects polling_place fields directly
                            const lw = this._livewire();
                            setTimeout(() => lw && lw.handleRegistraduriaResult(d.data), 600);
                        }
                    })
                    .catch(() => {});
            }, 2000);
        },

        stopPoll() {
            if (this.statusInterval) { clearInterval(this.statusInterval); this.statusInterval = null; }
        }
    }"
    x-on:keydown.escape.window="this._livewire() && this._livewire().closeRegistraduriaBrowser()"
    style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999;
           background:rgba(0,0,0,0.55); align-items:center; justify-content:center;"
>
    <div style="background:#fff; border-radius:1.25rem; box-shadow:0 25px 60px rgba(0,0,0,0.3);
                width:min(420px,88vw); padding:2.5rem 2rem; display:flex; flex-direction:column;
                align-items:center; gap:1.5rem; text-align:center;">

        {{-- Spinner / Icon --}}
        <div style="position:relative; width:64px; height:64px;">
            <svg x-show="isSpinning()"
                 style="position:absolute;inset:0;animation:reg-spin 1s linear infinite;"
                 viewBox="0 0 64 64" fill="none">
                <circle cx="32" cy="32" r="28" stroke="#e5e7eb" stroke-width="6"/>
                <path d="M32 4a28 28 0 0 1 28 28" stroke="#2563eb" stroke-width="6" stroke-linecap="round"/>
            </svg>
            <svg x-show="status === 'done'" viewBox="0 0 64 64" fill="none" style="position:absolute;inset:0;">
                <circle cx="32" cy="32" r="30" fill="#dcfce7" stroke="#16a34a" stroke-width="3"/>
                <path d="M20 33l9 9 15-16" stroke="#16a34a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg x-show="status === 'error'" viewBox="0 0 64 64" fill="none" style="position:absolute;inset:0;">
                <circle cx="32" cy="32" r="30" fill="#fee2e2" stroke="#dc2626" stroke-width="3"/>
                <path d="M22 22l20 20M42 22l-20 20" stroke="#dc2626" stroke-width="4" stroke-linecap="round"/>
            </svg>
        </div>

        {{-- Status text --}}
        <div>
            <p style="font-weight:700; font-size:1.1rem; color:#111827; margin:0 0 .4rem;">
                Registraduría — Puesto de votación
            </p>
            <p x-text="statusLabel()"
               :style="{ color: status === 'done' ? '#16a34a' : status === 'error' ? '#dc2626' : '#2563eb' }"
               style="font-size:.95rem; font-weight:600; margin:0;"></p>
        </div>

        {{-- Steps --}}
        <div style="width:100%; display:flex; flex-direction:column; gap:.4rem;">
            @foreach([
                ['loading',         '1', 'Cargando Registraduría'],
                ['solving_captcha', '2', 'Resolviendo CAPTCHA automáticamente'],
                ['waiting_result',  '3', 'Obteniendo datos del puesto'],
            ] as [$step, $num, $label])
            <div style="display:flex; align-items:center; gap:.6rem; padding:.4rem .6rem; border-radius:.4rem;"
                 :style="{ background: status === '{{ $step }}' ? '#eff6ff' : (
                     ['done','waiting_result','solving_captcha'].includes(status) && {{ $loop->index }} < ['loading','solving_captcha','waiting_result','done'].indexOf(status)
                     ? '#f0fdf4' : '#f9fafb'
                 )}">
                <div style="width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;"
                     :style="{ background: status === '{{ $step }}' ? '#2563eb' : '#e5e7eb',
                               color: status === '{{ $step }}' ? '#fff' : '#9ca3af' }">
                    {{ $num }}
                </div>
                <span style="font-size:.8rem; color:#374151;">{{ $label }}</span>
            </div>
            @endforeach
        </div>

        {{-- Error --}}
        <div x-show="status === 'error' && error"
             style="width:100%;background:#fee2e2;border-radius:.5rem;padding:.6rem;font-size:.75rem;color:#991b1b;"
             x-text="error"></div>

        <button type="button" x-on:click="this._livewire() && this._livewire().closeRegistraduriaBrowser()"
                style="font-size:.8rem;color:#9ca3af;text-decoration:underline;background:none;border:none;cursor:pointer;">
            Cancelar
        </button>
    </div>
</div>

<style>
@keyframes reg-spin { to { transform: rotate(360deg); } }
</style>
