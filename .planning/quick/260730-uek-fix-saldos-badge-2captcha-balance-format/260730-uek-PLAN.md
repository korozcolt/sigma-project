---
phase: quick-260730-uek
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/filament/components/saldos-badge.blade.php
autonomous: true
requirements: [UEK-01]
must_haves:
  truths:
    - "El balance principal 'Saldo 2captcha' se muestra con 2 decimales y sufijo ' USD' (ej. '29.18 USD'), sin signo '$'"
    - "Cada fila del 'Costo promedio 2captcha (últimos 7 días)' con status Computed muestra el número con 5 decimales y sufijo ' USD' (ej. '0.00299 USD'), sin signo '$'"
    - "El trigger 'Saldos' en la topbar tiene un margen izquierdo (ms-2) que lo separa del botón 'Cambiar' adyacente"
    - "La línea 'Saldo Hablame', el bloque @php, SaldoColorResolver y los props existentes del dropdown quedan intactos"
  artifacts:
    - path: "resources/views/filament/components/saldos-badge.blade.php"
      provides: "Badge de saldos con etiquetado USD explícito y separación en topbar"
      contains: "number_format($captchaBalance, 2)"
  key_links:
    - from: "resources/views/filament/components/saldos-badge.blade.php"
      to: "línea de balance principal 2captcha"
      via: "number_format con 2 decimales + ' USD'"
      pattern: "number_format\\(\\$captchaBalance, 2\\).*USD"
    - from: "resources/views/filament/components/saldos-badge.blade.php"
      to: "trigger del dropdown en topbar"
      via: "class=\"ms-2\" en la etiqueta <x-filament::dropdown id=\"saldos-badge\">"
      pattern: "id=\"saldos-badge\".*ms-2"
---

<objective>
Corregir el badge de saldos con dos arreglos pequeños en el mismo archivo Blade:

1. Eliminar la ambigüedad de moneda en las líneas de 2captcha: el signo `$` se interpreta como pesos colombianos (COP) en Colombia, pero estos valores son en dólares (USD).
2. Añadir separación entre el trigger "Saldos" y el botón "Cambiar" adyacente en la topbar, que hoy se renderizan pegados.

Purpose: El usuario reportó (desde un screenshot real) que `$29.18291` puede confundirse con pesos, cuando es USD ("colocar USD y dejalo a 2 decimales"). Además, `campaign-context-switcher` y `saldos-badge` se registran consecutivos en el mismo hook `PanelsRenderHook::TOPBAR_END` (AdminPanelProvider.php:90-91) y Filament los emite sin contenedor flex/gap, dejándolos pegados sin espacio.
Output: Badge con etiquetado de moneda explícito (consistente con la línea "Saldo Hablame": número + espacio + código, sin `$`) y con margen izquierdo que lo separa del elemento anterior en la topbar.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/filament/components/saldos-badge.blade.php

<interfaces>
<!-- Estilo de referencia ya presente en el archivo (línea Hablame, línea 38): -->
<!-- number followed by space and currency code, no `$` sign -->

Línea Hablame (NO tocar, es el patrón a imitar):
```blade
{{ number_format($hablameBalance, 0, ',', '.') }} COP
```

Línea balance 2captcha actual (línea 49) — a cambiar:
```blade
${{ number_format($captchaBalance, 5) }}
```

Línea costo promedio por fila actual (línea 63) — a cambiar solo el etiquetado, NO la precisión:
```blade
${{ number_format($dia->averageUsd, 5) }}
```

Etiqueta de apertura del dropdown (línea 25) — añadir class="ms-2":
```blade
<x-filament::dropdown id="saldos-badge" width="xs" placement="bottom-end" teleport="true">
```
</interfaces>

Convención de espaciado: el codebase usa propiedades lógicas de Tailwind (`ms-*`, no `ml-*`) — confirmado en `resources/views/components/layouts/app/header.blade.php` (`ms-2`, `ms-1`). Usar `ms-2`.

Contexto topbar: en `app/Providers/Filament/AdminPanelProvider.php:90-91`, `campaign-context-switcher` y `saldos-badge` se registran consecutivos en `PanelsRenderHook::TOPBAR_END`. Filament emite ambos sin wrapper flex/gap (`topbar.blade.php:254`), de ahí el pegado. NO tocar `campaign-context-switcher.blade.php` ni `AdminPanelProvider.php` — el arreglo va del lado de saldos-badge por ser el segundo en orden de render. Filament fusiona atributos no reconocidos vía `$attributes`, así que `class="ms-2"` en la etiqueta del dropdown aplica al div raíz.

Nota sobre el test: `tests/Feature/Filament/SaldosBadgeTest.php` solo verifica la presencia del componente (`assertSee('saldos-badge')` / `assertDontSee`) según rol. NO tiene aserciones sobre el texto/formato del balance renderizado. No requiere cambios.
</context>

<tasks>

<task type="auto">
  <name>Task 1: Etiquetar como USD las líneas de 2captcha y separar el trigger en la topbar</name>
  <files>resources/views/filament/components/saldos-badge.blade.php</files>
  <action>
    Tres ediciones pequeñas en el mismo archivo `resources/views/filament/components/saldos-badge.blade.php`. NO tocar la línea Hablame (38), el bloque `@php` (1-23), ni `SaldoColorResolver`.

    1. Separación en topbar (línea 25): añadir `class="ms-2"` a la etiqueta de apertura del dropdown, conservando los props existentes.
       De:
       ```blade
       <x-filament::dropdown id="saldos-badge" width="xs" placement="bottom-end" teleport="true">
       ```
       A:
       ```blade
       <x-filament::dropdown id="saldos-badge" class="ms-2" width="xs" placement="bottom-end" teleport="true">
       ```
       (Usar `ms-2`, no `ml-2` — el codebase usa propiedades lógicas.)

    2. Balance principal "Saldo 2captcha" (línea 49): 2 decimales y sufijo USD, sin `$`.
       De:
       ```blade
       ${{ number_format($captchaBalance, 5) }}
       ```
       A:
       ```blade
       {{ number_format($captchaBalance, 2) }} USD
       ```
       Resultado esperado: `29.18 USD`.

    3. Costo promedio por fila (línea 63, dentro del `@foreach`, rama `DailyCaptchaCostStatus::Computed`): MANTENER 5 decimales (costos sub-centavo ~$0.001–$0.003; a 2 decimales mostraría `0.00`). Solo cambiar el etiquetado de moneda.
       De:
       ```blade
       ${{ number_format($dia->averageUsd, 5) }}
       ```
       A:
       ```blade
       {{ number_format($dia->averageUsd, 5) }} USD
       ```
       Resultado esperado: `0.00299 USD`.

    NO cambiar las ramas `N/D`, `Recarga detectada`, ni `—`.
  </action>
  <verify>
    <automated>grep -n 'id="saldos-badge" class="ms-2"' "resources/views/filament/components/saldos-badge.blade.php" && grep -n "number_format(\$captchaBalance, 2) }} USD" "resources/views/filament/components/saldos-badge.blade.php" && grep -n "number_format(\$dia->averageUsd, 5) }} USD" "resources/views/filament/components/saldos-badge.blade.php" && ! grep -n '${{ number_format' "resources/views/filament/components/saldos-badge.blade.php" && php artisan test --filter=SaldosBadge</automated>
  </verify>
  <done>
    - La etiqueta del dropdown incluye `class="ms-2"` conservando `id`, `width`, `placement`, `teleport`.
    - La línea de balance principal usa `number_format($captchaBalance, 2)` seguido de ` USD`, sin `$`.
    - La línea de costo promedio conserva `number_format($dia->averageUsd, 5)` y añade ` USD`, sin `$`.
    - No queda ningún patrón `${{ number_format` en el archivo.
    - La línea Hablame y el resto del archivo quedan sin cambios.
    - Los tests de SaldosBadge siguen pasando.
  </done>
</task>

</tasks>

<verification>
- Inspección visual del Blade: solo cambian las líneas 25, 49 y 63. En 49 y 63 el `$` inicial desaparece y aparece ` USD` como sufijo; en 25 se añade `class="ms-2"` sin perder props.
- La precisión del costo promedio se mantiene en 5 decimales.
- Verificación en navegador (topbar del panel /admin como super admin): el trigger "Saldos" ya no queda pegado al botón "Cambiar"; el dropdown muestra `29.18 USD` y filas `0.00299 USD`.
- `php artisan test --filter=SaldosBadge` pasa.
- `vendor/bin/pint --dirty` sin cambios pendientes.
</verification>

<success_criteria>
- "Saldo 2captcha" se lee como `29.18 USD` (2 decimales, sufijo USD, sin `$`).
- Cada fila de costo promedio (status Computed) se lee como `0.00299 USD` (5 decimales, sufijo USD, sin `$`).
- El trigger "Saldos" tiene separación visible respecto al botón "Cambiar" en la topbar.
- Ningún otro elemento del archivo, ni otros archivos, fueron modificados.
</success_criteria>

<output>
After completion, create `.planning/quick/260730-uek-fix-saldos-badge-2captcha-balance-format/260730-uek-SUMMARY.md`
</output>
