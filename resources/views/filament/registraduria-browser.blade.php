{{--
    Registraduria browser modal — screenshot proxy with smart reCAPTCHA click.
    Operator sees live screenshots of the real Registraduria page.
    Clicking on the reCAPTCHA area triggers frame_locator click inside the iframe.
    Note: use x-on: instead of @ (Blade conflict avoidance).
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

        statusLabel() {
            return {
                pending:         'Iniciando...',
                waiting_captcha: 'Haz clic en el reCAPTCHA y luego en Consultar',
                done:            '✅ Datos obtenidos',
                error:           '❌ Error',
            }[this.status] ?? this.status;
        },

        init() {
            if ($el.parentElement !== document.body) document.body.appendChild($el);
            this._updateDisplay();
            this.$watch('isOpen', () => this._updateDisplay());
            if (this.isOpen && this.sessionId) this.start();
        },

        destroy() { this.stop(); },

        _updateDisplay() {
            $el.style.display = (this.isOpen && this.sessionId) ? 'flex' : 'none';
        },

        start() {
            this.screenshotSrc = '/registraduria/screenshot/' + this.sessionId + '?t=' + Date.now();

            fetch('/registraduria/viewport/' + this.sessionId)
                .then(r => r.json())
                .then(d => { if (d && d.width) this.viewport = d; })
                .catch(() => {});

            // Screenshot every 1.5s (lightweight — JPEG q50)
            this.screenshotInterval = setInterval(() => {
                if (!this.isOpen) return;
                this.screenshotSrc = '/registraduria/screenshot/' + this.sessionId + '?t=' + Date.now();
            }, 1500);

            // Status poll every 2s
            this.statusInterval = setInterval(() => {
                if (!this.isOpen) { this.stop(); return; }
                fetch('/registraduria/result/' + this.sessionId)
                    .then(r => r.json())
                    .then(d => {
                        this.status = d.status ?? 'error';
                        if (d.status === 'done' && d.data) {
                            this.stop();
                            setTimeout(() => $wire.handleRegistraduriaResult(d), 1000);
                        }
                        if (d.status === 'error') this.stop();
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
            const scaleX = this.viewport.width / rect.width;
            const clickX = event.clientX - rect.left;
            const clickY = event.clientY - rect.top + (event.currentTarget.parentElement?.scrollTop ?? 0);
            const x = Math.round(clickX * scaleX);
            const y = Math.round(clickY * scaleX);
            fetch('/registraduria/click/' + this.sessionId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ x, y }),
            }).catch(() => {});
        }
    }"
    x-on:keydown.escape.window="$wire.closeRegistraduriaBrowser()"
    style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999;
           background:rgba(0,0,0,0.6); flex-direction:column; align-items:center; justify-content:flex-start; padding-top:2rem;"
>
    <div style="background:#fff; border-radius:1rem; box-shadow:0 25px 60px rgba(0,0,0,0.35);
                width:min(1000px,92vw); max-height:90vh; display:flex; flex-direction:column; overflow:hidden;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between;
                    padding:.75rem 1.25rem; border-bottom:1px solid #e5e7eb; background:#f9fafb; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:.75rem;">
                <span style="font-weight:700; font-size:.9rem; color:#111827;">Registraduría — Puesto de votación</span>
                <span style="font-size:.8rem; font-weight:600; padding:.2rem .6rem; border-radius:9999px;
                              background:#fef3c7; color:#92400e;" x-text="statusLabel()"></span>
            </div>
            <button type="button" x-on:click="$wire.closeRegistraduriaBrowser()"
                    style="color:#9ca3af; background:none; border:none; cursor:pointer; font-size:1.25rem; line-height:1;">✕</button>
        </div>

        {{-- Instruction --}}
        <div style="background:#eff6ff; border-bottom:1px solid #bfdbfe; padding:.5rem 1.25rem; flex-shrink:0;">
            <p style="font-size:.8rem; color:#1d4ed8; margin:0;">
                <strong>1.</strong> Haz clic en el checkbox <em>"No soy un robot"</em> de la imagen &nbsp;
                <strong>2.</strong> Haz clic en el botón <em>"Consultar"</em> de la imagen
            </p>
        </div>

        {{-- Screenshot area —scrollable --}}
        <div style="flex:1; overflow-y:auto; background:#f3f4f6; padding:.5rem;">
            <template x-if="status !== 'error'">
                <img
                    :src="screenshotSrc"
                    alt="Registraduría"
                    style="width:100%; border-radius:.5rem; border:1px solid #e5e7eb; cursor:pointer; user-select:none; display:block;"
                    x-on:click="forwardClick($event)"
                    draggable="false"
                />
            </template>
            <template x-if="status === 'error'">
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:12rem; gap:.75rem; color:#dc2626;">
                    <span style="font-size:2rem;">❌</span>
                    <p style="font-size:.875rem; font-weight:600; margin:0;">Error al consultar Registraduría</p>
                    <button type="button" x-on:click="$wire.closeRegistraduriaBrowser()"
                            style="font-size:.8rem; text-decoration:underline; background:none; border:none; cursor:pointer; color:#dc2626;">Cerrar</button>
                </div>
            </template>
            <template x-if="status === 'done'">
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:12rem; gap:.75rem; color:#16a34a;">
                    <span style="font-size:3rem;">✅</span>
                    <p style="font-size:.95rem; font-weight:700; margin:0;">Datos obtenidos correctamente</p>
                    <p style="font-size:.8rem; color:#6b7280; margin:0;">El formulario se llenará automáticamente</p>
                </div>
            </template>
        </div>
    </div>
</div>
