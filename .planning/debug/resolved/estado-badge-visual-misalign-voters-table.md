---
status: superseded
trigger: "estado-badge-visual-misalign-voters-table"
created: 2026-08-05T00:00:00Z
updated: 2026-08-05T23:30:00Z
---

## SUPERSEDED

This session investigated the wrong framing. The original trigger ("badges apilados
visualmente entre las columnas Estado y Fuente del Puesto de Votacion") was a
misinterpretation of what the product owner actually spotted in the screenshot: not a
CSS/rendering bug, but a **DATA/LOGIC bug** — a voter row showing a "Pendiente de
Revision" status badge next to an "En Vivo" polling-place-source badge, which is
contradictory per business rules (a voter already confirmed live shouldn't still be
sitting in unreviewed/pending state). The visual investigation below correctly ruled out
every CSS/layout/animation hypothesis (that part of the analysis was sound) but never
considered that the two badges' VALUES themselves could be legitimately desynced.

The real root cause was confirmed by separate investigation and is fully documented,
fixed, and resolved in:

**`.planning/debug/resolved/status-polling-place-source-desync.md`**

Do not re-investigate this as a rendering/CSS bug. See that file for the confirmed root
cause, fix, and verification.

## Current Focus
<!-- OVERWRITE on each update - reflects NOW -->

hypothesis: SUPERSEDED — see status-polling-place-source-desync.md for the confirmed root cause (a data/logic desync between Voter.status and Voter.polling_place_source, not a CSS/rendering bug).
test: N/A
expecting: N/A
next_action: N/A — session closed, superseded.

## Investigation Environment Note

No fue posible usar la base de datos real `sigma_betha_backup` (MySQL) para la verificacion visual: el servidor MySQL local no esta corriendo en esta maquina (no hay proceso mysqld, ni Docker, ni servicio Herd Pro activo) y no se encontro binario/CLI para levantarlo. Se replico el bug con una base de datos sqlite temporal sembrada con datos sinteticos (VisualE2ESeeder + 8 voters adicionales con distintos VoterStatus/PollingPlaceSource) sirviendo la app real (Laravel + Filament v4 + Vite build real) via `php -S` con un router script corregido (el router de `php artisan serve` no reenvia las variables de entorno DB_* al proceso hijo, y `vendor/.../resources/server.php` resuelve rutas relativas a `vendor/`, no a la raiz del proyecto — se escribio un router.php ad-hoc con la ruta absoluta correcta). Verificado con Playwright real (Chromium) contra el build de Vite de produccion (`public/build`), no un mock.

## Symptoms
<!-- Written during gathering, then IMMUTABLE -->

expected: La columna "Estado" debe mostrar un unico badge de estado por fila (ej. "Pendiente de Revision"), y la columna separada "Fuente del Puesto de Votacion" debe mostrar su propio badge (ej. "En Vivo") alineado bajo su propio header, sin mezclarse visualmente con la columna Estado.
actual: En la captura de pantalla del usuario, para varias filas de votantes distintos, se ven 1-2 badges apilados verticalmente en lo que aparenta ser la misma zona/columna, justo entre "Telefono" y "Municipio". El usuario marco esto con un recuadro rojo como "bug de produccion".
errors: Ninguno reportado por el usuario (no hay stacktrace ni mensaje de error visible).
reproduction: Ir al listado principal de Apoyos/Voters en el panel Filament (app/Filament/Resources/Voters) y observar la tabla — ver archivo app/Filament/Resources/Voters/Tables/VotersTable.php.
started: Reciente — el usuario dice que empezo a notarse hace poco, antes se veia bien (no siempre fue asi).

## Eliminated
<!-- APPEND only - prevents re-investigating -->

- hypothesis: Columna Estado usa notacion de relacion hasMany o getStateUsing() devolviendo array/collection (causaria apilado real de <li> de Filament).
  evidence: VotersTable.php:86-102 usa TextColumn::make('status') simple sobre cast enum VoterStatus, sin notacion de relacion ni getStateUsing() con array. Confirmado por subagente de solo lectura previo.
  timestamp: pre-investigation (prior_investigation)

- hypothesis: Duplicacion real de filas en la tabla voters causando el patron visual.
  evidence: Screenshot muestra nombres de votantes distintos por fila, no la misma fila repetida.
  timestamp: pre-investigation (prior_investigation)

- hypothesis: Bug de layout responsive (Filament colapsa columnas Estado + Fuente del Puesto de Votacion en una sola celda "card" por falta de espacio horizontal en viewports angostos).
  evidence: Probado en navegador real (Playwright + Chromium, build de Vite de produccion) en 1440px, 1280px, 1024px, 900px y 768px de ancho. En todos los casos las columnas Estado y Fuente se renderizan correctamente alineadas bajo sus propios headers, lado a lado. Por debajo del ancho natural de la tabla, el comportamiento es scroll horizontal (overflow-x), NO colapso tipo "card" ni apilado vertical de badges dentro de una celda.
  timestamp: 2026-08-05 (browser verification)

- hypothesis: Cambio reciente de columnas/orden en VotersTable.php (ej. el commit que oculta Campana por default, o el que agrego polling_place_source) introdujo directamente el bug.
  evidence: git log -p sobre VotersTable.php para esos commits (47e42e3, c827d49) muestra cambios acotados y correctos (toggleable(isToggledHiddenByDefault) y una columna badge nueva independiente) sin logica de agrupacion/stack. Renderizado en navegador con el codigo actual (HEAD) es correcto en todos los anchos probados.
  timestamp: 2026-08-05 (code review + browser verification)

- hypothesis: La animacion de entrada CSS de las filas (.fi-ta-row { animation: sigma-slide-in-up ... translateY(18px) } definida en resources/css/filament/theme.css, parte del "Premium Motion Design System") causa superposicion visual momentanea entre filas si se toma una captura durante la transicion.
  evidence: Verificado con page.getAnimations() en Playwright: animation-duration real es 0.2s (200ms), y el desplazamiento (translateY(18px)) es pequeno comparado con la altura de fila (~50-60px); a los pocos cientos de ms todas las animaciones ya estan en estado "finished" con transform identidad y opacity 1. No se pudo producir un frame intermedio con badges de filas distintas superpuestos, ni siquiera forzando delays cortos en las capturas. Esta animacion es real pero insuficiente para explicar el patron reportado (2 badges de estado/fuente aparentemente fusionados en una sola celda para MULTIPLES filas distintas de forma consistente, no momentanea).
  timestamp: 2026-08-05 (browser verification)

- hypothesis: El widget RevalidationProgressWidget (wire:poll cada 5s) en la pagina de Apoyos re-renderiza la tabla completa periodicamente, re-disparando la animacion de entrada de filas repetidamente y produciendo el efecto de apilado.
  evidence: El propio codigo fuente documenta explicitamente (comentario en RevalidationProgressWidget.php) "wire:poll refreshes only this widget, never the Apoyos table itself" — es un componente Livewire independiente (Filament Widget), su poll no dispara re-render del componente de tabla hermano. No se encontro wire:poll en la tabla de Voters ni en ListVoters.php.
  timestamp: 2026-08-05 (code review)

## Evidence
<!-- APPEND only - facts discovered -->

- timestamp: 2026-08-05
  checked: app/Filament/Resources/Voters/Tables/VotersTable.php (lectura completa, HEAD actual)
  found: 'status' (Estado) y 'polling_place_source' (Fuente del Puesto de Votacion) son dos TextColumn independientes, ambos con ->badge() sobre un enum de valor unico (VoterStatus, PollingPlaceSource via casts() en app/Models/Voter.php). No hay notacion de relacion hasMany/belongsToMany, no hay getStateUsing() con array, no hay Stack/Split layout agrupandolas. Ningun cambio de codigo explica un apilado real dentro de una celda.
  implication: El codigo actual no tiene un bug estructural que produzca "1 celda con 2 badges" — si el patron es real, es un problema de renderizado/layout (CSS/viewport) o de un entorno con assets desactualizados, no un bug logico en la definicion de columnas.

- timestamp: 2026-08-05
  checked: git log -p sobre VotersTable.php, resources/css/filament/theme.css, resources/css/app.css (historial completo)
  found: Los unicos commits recientes que tocan VotersTable.php son adiciones acotadas (nueva columna badge, toggleable default). No hay commits recientes sobre CSS de tablas Filament ni sobre theme.css/app.css (los cambios de CSS mas recientes son del inicio del historial del repo, no recientes). No existen vistas Blade publicadas/customizadas para tablas Filament (find en resources/views no encontro overrides).
  implication: Descarta un cambio de codigo/CSS reciente como disparador directo.

- timestamp: 2026-08-05
  checked: Entorno local — intento de levantar MySQL local (sigma_betha_backup) para reproducir con datos reales de produccion
  found: No hay proceso mysqld corriendo, ni Docker daemon activo, ni servicio Herd Pro disponible ("Herd Pro is required to use services."), ni binario mysql/mysqld localizable en el sistema. La memoria del proyecto sobre esta DB tiene 6 dias y esta desactualizada respecto al estado actual de la maquina.
  implication: No fue posible verificar contra los datos reales exactos que el usuario vio. Se uso una reproduccion sintetica equivalente (sqlite + seeder) como sustituto razonable para probar el RENDERIZADO (que es independiente del contenido de los datos), aclarando esta limitacion en el reporte final.

- timestamp: 2026-08-05
  checked: Verificacion visual en navegador real (Playwright + Chromium 1228, build de Vite de produccion en public/build) contra sqlite sembrado con 9 voters de nombres/estados/fuentes variados, en anchos de viewport 1440/1280/1024/900/768px
  found: En todos los anchos probados, "Estado" y "Fuente del Puesto de Votacion" se renderizan como columnas separadas, correctamente alineadas bajo sus propios headers. Nunca se observo apilado vertical de 2 badges dentro de una sola celda. Por debajo del ancho natural de la tabla el comportamiento es scroll horizontal normal.
  implication: El bug reportado NO se reproduce con el codigo actual + build de assets actual en un navegador real. Esto apunta a una causa externa al codigo fuente (cache de navegador del usuario con JS/CSS viejo, o build de produccion desactualizado respecto al commit actual desplegado) en vez de un bug de codigo presente en HEAD.

## Resolution
<!-- OVERWRITE as understanding evolves -->

root_cause: SUPERSEDED. This was the wrong framing of the bug — see status-polling-place-source-desync.md for the confirmed root cause (Voter.status and Voter.polling_place_source are two independent state machines updated by two uncoordinated hourly cron jobs, causing exactly the contradictory badge combination — "Pendiente de Revision" next to "En Vivo" — the product owner spotted). The CSS/rendering elimination work below remains valid (it correctly ruled out those hypotheses) but the investigation stopped short of considering the data itself was desynced.
fix: See status-polling-place-source-desync.md (resolved/fixed there).
verification: See status-polling-place-source-desync.md.
files_changed: []
