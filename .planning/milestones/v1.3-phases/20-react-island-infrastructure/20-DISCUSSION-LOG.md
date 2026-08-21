# Phase 20: React Island Infrastructure - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-20
**Phase:** 20-react-island-infrastructure
**Areas discussed:** Estética del PoC, Tema oscuro/claro, Estado de fallo, Checkpoint humano

---

## Estética del PoC

| Option | Description | Selected |
|--------|-------------|----------|
| Mínimo ahora | El PoC es una caja simple que solo prueba que el ciclo poll→dispatch→render→unmount funciona. Separa el riesgo de plumbing del riesgo visual. | ✓ |
| Con estética MonoCharts desde ya | El PoC ya usa la card anidada/radius/header-footer de MonoCharts. | |
| Vos decidís | Dejar la decisión al criterio técnico de Claude. | |

**User's choice:** Mínimo ahora (Recomendado)
**Notes:** Confirma la lógica de fases de la investigación — separar riesgo de plumbing del riesgo visual.

---

## Tema oscuro/claro

| Option | Description | Selected |
|--------|-------------|----------|
| Claro, estilo Filament actual | Los charts usan la variante light de MonoCharts, consistentes con el panel tal como existe hoy. | |
| Oscuro fijo, monocromo MonoCharts | Los charts imponen su propio fondo oscuro independiente del panel. | |
| Ambos, con toggle futuro | Construir los charts para soportar ambas variantes desde el inicio, aunque hoy el panel no tenga toggle. | ✓ |

**User's choice:** Ambos, con toggle futuro
**Notes:** Verificado en código que ningún `*PanelProvider.php` llama `darkMode()` — panel es light-only hoy. Decisión: construir ambas variantes del shell de card, default fijo a "light" hasta que el panel tenga toggle real.

---

## Estado de fallo

| Option | Description | Selected |
|--------|-------------|----------|
| Mensaje de error explícito | Estado de error visible en vez de blanco o datos viejos. | ✓ |
| Skeleton/loading indefinido | Esqueleto de carga que nunca resuelve. | |
| Vos decidís | Dejar la decisión de UX de fallo al criterio técnico de Claude. | |

**User's choice:** Mensaje de error explícito (Recomendado)
**Notes:** Alineado con la constraint del proyecto: datos operativos inexactos/engañosos son inaceptables.

---

## Checkpoint humano

| Option | Description | Selected |
|--------|-------------|----------|
| Sí, checkpoint humano | Ver el PoC corriendo en navegador real antes de cerrar la fase, además del test automático. | ✓ |
| No, el test Pest Browser alcanza | Confiar solo en el test automatizado (INFRA-04). | |

**User's choice:** Sí, checkpoint humano (Recomendado)
**Notes:** Consistente con la preferencia ya conocida del usuario de verificar cambios de UI en navegador real antes de darlos por buenos.

---

## Claude's Discretion

- Naming exacto de directorios/archivos bajo `resources/js/charts/` y del componente Alpine bridge.
- Panel que aloja el widget PoC descartable (Admin es la elección natural).
- Mecánica exacta del render hook `Vite::withEntryPoints()` por PanelProvider, siempre que quede verificado en los 5 paneles (INFRA-03).

## Deferred Ideas

- Estrategia de migración de los 3 sparklines embebidos (Stat::chart() vs ChartWidget dedicado) — pertenece a la discusión de Fase 21.
- Composición visual completa de MonoCharts (card anidada, opacidad por serie, animación escalonada, tooltip custom) — se aplica a partir de Fase 21, no en el PoC de Fase 20.
- Dark mode real a nivel de panel Filament (no solo los charts) — fuera de alcance de esta milestone.
