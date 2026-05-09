{{--
    Registraduria modal — 2captcha edition.
    No screenshot proxy. Automated flow: page load → 2captcha → inject token → result.
    Shows a progress spinner with status updates. Auto-closes on done/error.
    Note: use x-on: instead of @ to avoid Blade directive conflicts.
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
                pending:          'Iniciando...',
                loading:          'Cargando página de Registraduría...',
                solving_captcha:  'Resolviendo CAPTCHA automáticamente...',
                waiting_result:   'Obteniendo resultado...',
                done:             '✅ Datos obtenidos',
                error:            '❌ Error',
            }[this.status] ?? this.status;
        },

        statusColor() {
            return {
                pending:         '#6b7280',
                loading:         '#2563eb',
                solving_captcha: '#d97706',
                waiting_result:  '#2563eb',
                done:            '#16a34a',
                error:           '#dc2626',
            }[this.status] ?? '#6b7280';
        },

        isSpinning() {
            return !['done', 'error'].includes(this.status);
        },

        init() {
            if ($el.parentElement !== document.body) {
                document.body.appendChild($el);
            }
            this._updateDisplay();
            this.$watch('isOpen', () => this._updateDisplay());
            if (this.isOpen && this.sessionId) this.startPoll();
        },

        destroy() { this.stopPoll(); },

        _updateDisplay() {
            $el.style.display = (this.isOpen && this.sessionId) ? 'flex' : 'none';
        },

        startPoll() {
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
                            setTimeout(() => $wire.handleRegistraduriaResult(d), 800);
                        }
                    })
                    .catch(() => {});
            }, 2000);
        },

        stopPoll() {
            if (this.statusInterval) { clearInterval(this.statusInterval); this.statusInterval = null; }
        }
    }"
    x-on:keydown.escape.window="$wire.closeRegistraduriaBrowser()"
    style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999;
           background:rgba(0,0,0,0.55); align-items:center; justify-content:center;"
>
    <div style="background:#fff; border-radius:1rem; box-shadow:0 25px 60px rgba(0,0,0,0.3);
                width:min(480px,90vw); padding:2rem; display:flex; flex-direction:column; align-items:center; gap:1.5rem;">

        {{-- Icon / Spinner --}}
        <div style="position:relative; width:64px; height:64px;">
            {{-- Spinner ring --}}
            <svg x-show="isSpinning()" style="position:absolute;inset:0;animation:spin 1s linear infinite;" viewBox="0 0 64 64" fill="none">
                <circle cx="32" cy="32" r="28" stroke="#e5e7eb" stroke-width="6"/>
                <path d="M32 4a28 28 0 0 1 28 28" stroke="#2563eb" stroke-width="6" stroke-linecap="round"/>
            </svg>
            {{-- Done check --}}
            <svg x-show="status === 'done'" viewBox="0 0 64 64" fill="none" style="position:absolute;inset:0;">
                <circle cx="32" cy="32" r="30" fill="#dcfce7" stroke="#16a34a" stroke-width="3"/>
                <path d="M20 33l9 9 15-16" stroke="#16a34a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{-- Error X --}}
            <svg x-show="status === 'error'" viewBox="0 0 64 64" fill="none" style="position:absolute;inset:0;">
                <circle cx="32" cy="32" r="30" fill="#fee2e2" stroke="#dc2626" stroke-width="3"/>
                <path d="M22 22l20 20M42 22l-20 20" stroke="#dc2626" stroke-width="4" stroke-linecap="round"/>
            </svg>
        </div>

        {{-- Title --}}
        <div style="text-align:center;">
            <p style="font-weight:700; font-size:1.1rem; color:#111827; margin:0 0 .5rem;">
                Registraduría — Puesto de votación
            </p>
            <p x-text="statusLabel()" :style="'color:' + statusColor()" style="font-size:.95rem; font-weight:500; margin:0;"></p>
        </div>

        {{-- Progress steps --}}
        <div style="width:100%; display:flex; flex-direction:column; gap:.5rem;">
            @foreach([
                ['loading',         '1', 'Cargando página'],
                ['solving_captcha', '2', 'Resolviendo CAPTCHA (2captcha)'],
                ['waiting_result',  '3', 'Obteniendo resultado'],
            ] as [$step, $num, $label])
            <div style="display:flex; align-items:center; gap:.75rem; padding:.5rem .75rem; border-radius:.5rem;"
                 :style="{
                     background: ['{{ $step }}', 'done'].some(s => status === s || (status === 'done' && true))
                         ? (status === '{{ $step }}' ? '#eff6ff' : (['solving_captcha','waiting_result','done'].includes(status) && '{{ $step }}' === 'loading' ? '#f0fdf4' : (status === 'done' || (status === 'waiting_result' && '{{ $step }}' !== 'waiting_result') ? '#f0fdf4' : '#f9fafb')))
                         : '#f9fafb'
                 }">
                <div style="width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;"
                     :style="{
                         background: status === '{{ $step }}' ? '#2563eb' : (['done', 'waiting_result', 'solving_captcha'].includes(status) && {{ $loop->index }} < ['loading','solving_captcha','waiting_result','done'].indexOf(status) ? '#16a34a' : '#e5e7eb'),
                         color: (status === '{{ $step }}' || (['done', 'waiting_result', 'solving_captcha'].includes(status) && {{ $loop->index }} < ['loading','solving_captcha','waiting_result','done'].indexOf(status))) ? '#fff' : '#9ca3af'
                     }">
                    {{ $num }}
                </div>
                <span style="font-size:.875rem; color:#374151;">{{ $label }}</span>
            </div>
            @endforeach
        </div>

        {{-- Error message --}}
        <div x-show="status === 'error' && error" style="width:100%;background:#fee2e2;border-radius:.5rem;padding:.75rem;font-size:.8rem;color:#991b1b;" x-text="error"></div>

        {{-- Close button --}}
        <button
            type="button"
            x-on:click="$wire.closeRegistraduriaBrowser()"
            style="font-size:.875rem;color:#6b7280;text-decoration:underline;background:none;border:none;cursor:pointer;padding:0;"
        >Cancelar</button>
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
