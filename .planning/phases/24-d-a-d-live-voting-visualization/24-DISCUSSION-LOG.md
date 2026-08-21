# Phase 24: Día D Live Voting Visualization - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-21
**Phase:** 24-d-a-d-live-voting-visualization
**Areas discussed:** Estrategia de cacheo/pre-agregación, Intervalo de polling del widget, Ubicación de la gráfica, Alcance de la serie temporal

---

## Estrategia de cacheo/pre-agregación

| Option | Description | Selected |
|--------|-------------|----------|
| Cache::remember con TTL corto | Recalcula on-demand solo cuando expira; simple, sin scheduler nuevo, usa el cache_driver 'database' ya configurado | ✓ |
| Job programado (scheduler) cada N segundos | Recalcula y guarda el agregado periódicamente sin importar si alguien mira; nunca hay cache miss costoso en vivo, pero añade infraestructura nueva | |
| Invalidación por evento al crear VoteRecord | Cache::forget() vía Model Observer en cada voto marcado; más fresco pero acopla el hot-path de marcar-voto a la invalidación de un widget de solo-lectura | |

**User's choice:** Cache::remember con TTL corto (recomendado)
**Notes:** Ningún widget existente usa Cache:: hoy — este es el primer caso en el codebase.

### Follow-up: TTL exacto

| Option | Description | Selected |
|--------|-------------|----------|
| 30 segundos | Se siente "en vivo" pero absorbe ráfagas de polling concurrente | ✓ |
| 60 segundos | Menos carga aún, imperceptible dada la granularidad horaria de la gráfica | |
| 10 segundos (igual a DiaDStatsOverview) | Coherencia visual con el resto del panel, pero reduce menos la carga de query | |

**User's choice:** 30 segundos (recomendado)

---

## Intervalo de polling del widget

| Option | Description | Selected |
|--------|-------------|----------|
| 30s, igual al TTL del cache | Cada poll coincide con una posible actualización real del cache — sin polls desperdiciados | ✓ |
| 10s, igual al resto del panel Día D | Mismo ritmo visual que DiaDStatsOverview, pero 3x más peticiones sin beneficio real | |
| 60s, más conservador | Reduce aún más el tráfico de polling en alta concurrencia, a costa de inmediatez | |

**User's choice:** 30s, igual al TTL del cache (recomendado)
**Notes:** Diverge deliberadamente del 10s de DiaDStatsOverview, que sigue sin cache y queda fuera de esta fase.

---

## Ubicación de la gráfica

| Option | Description | Selected |
|--------|-------------|----------|
| Página dedicada DiaD | Junto a DiaDStatsOverview y DiaDTerritorialProgressTable — donde los admins ya miran durante la jornada | ✓ |
| Admin dashboard general | Junto a las demás gráficas nuevas de Fases 22/23 en el dashboard principal | |
| Ambos | Más visibilidad pero duplica el punto de renderizado a mantener | |

**User's choice:** Página dedicada DiaD (recomendado)

---

## Alcance de la serie temporal

| Option | Description | Selected |
|--------|-------------|----------|
| Una sola línea acumulada, campaign-wide | Coincide exactamente con el roadmap (DAYD-05), evita duplicar DiaDTerritorialProgressTable | ✓ |
| Desglose por territorio/coordinador dentro de la misma gráfica | Capacidad nueva no pedida por el roadmap | |

**User's choice:** Confirmar: una sola línea acumulada, campaign-wide (recomendado)

---

## Claude's Discretion

- Exact cache key naming/format (must scope by campaign_id + active election_event_id)
- Exact chart kind/component reuse (LineChart.jsx expected to fit, confirm during research)
- Empty/pre-Día-D state copy
- Exact aggregation query shape (flat continuation vs gaps for zero-vote hours)

## Deferred Ideas

- Per-territory/per-coordinador breakdown within the live line chart — declined in favor of the roadmap's simple single-line scope; `DiaDTerritorialProgressTable` already serves this need.
