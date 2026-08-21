# Phase 22: Table-Stakes New Visualizations - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-21
**Phase:** 22-table-stakes-new-visualizations
**Areas discussed:** Donut de estados (VIZ-01), Barras apiladas por coordinador (VIZ-02), Funnels: llamadas + mensajes (VIZ-03, VIZ-04), Gauge + histograma de encuestas SCALE (VIZ-05)

---

## Donut de estados (VIZ-01)

| Option | Description | Selected |
|--------|-------------|----------|
| 12 completos | Todos los VoterStatus como segmento propio, sin agrupar | ✓ |
| Agrupar en 'Otros' | Estados por debajo de un umbral se colapsan | |
| Tooltip + leyenda completa, visual agrupado | Arco agrupado, tooltip con desglose exacto | |

**User's choice:** 12 completos
**Notes:** Prioriza números operativos exactos sobre legibilidad visual, consistente con la restricción del proyecto de que datos inexactos son inaceptables.

| Option | Description | Selected |
|--------|-------------|----------|
| Solo hover | Consistente con Fase 21, sin drill-through en ningún chart migrado | ✓ |
| Click-through a tabla filtrada | Navega a VoterResource filtrado por status | |

**User's choice:** Solo hover
**Notes:** Mantiene Phase 22 enfocada en visualización, sin agregar alcance de interacción nuevo.

---

## Barras apiladas por coordinador (VIZ-02)

| Option | Description | Selected |
|--------|-------------|----------|
| Verificado | VERIFIED_CENSUS + VERIFIED_REGISTRADURIA + VERIFIED_CALL + CONFIRMED | ✓ |
| Verificado + Day D | Lo anterior + VOTED + DID_NOT_VOTE | |
| Solo Confirmado+ | Solo CONFIRMED + VOTED + DID_NOT_VOTE | |

**User's choice:** Verificado
**Notes:** "Validado" significa que pasó alguna verificación formal, sin importar si llegó a Día D.

| Option | Description | Selected |
|--------|-------------|----------|
| 'Registrado' = resto | Categoría residual, las 3 barras suman el 100% exacto | ✓ |
| Solo 3 buckets explícitos, resto se omite | Estados no mapeados no aparecen en ninguna barra | |

**User's choice:** 'Registrado' = resto
**Notes:** Ningún voter se pierde del conteo visual.

| Option | Description | Selected |
|--------|-------------|----------|
| Todos los coordinadores de la campaña | Sin límite | ✓ |
| Top-N por volumen | Los N coordinadores con más apoyos, orden descendente | |

**User's choice:** Todos los coordinadores de la campaña
**Notes:** Usuario rechazó explícitamente la recomendación de top-N/legibilidad a favor de completitud. Legibilidad a alto volumen queda como detalle de implementación (scroll/responsive), no como corte de datos.

---

## Funnels: llamadas + mensajes (VIZ-03, VIZ-04)

| Option | Description | Selected |
|--------|-------------|----------|
| Persistencia | Cada etapa = votantes que llegaron a ese intento; 'Contactado' = éxito en cualquier intento | ✓ |
| Tasa de éxito por intento | % de llamadas de ese intento con contacto exitoso, no necesariamente decreciente | |

**User's choice:** Persistencia
**Notes:** Produce un funnel genuinamente monotónico, compatible con el componente Funnel nativo de Recharts.

| Option | Description | Selected |
|--------|-------------|----------|
| Todos los MessageBatch históricos | Agregado de campaña completa, todo el tiempo, sin filtro | ✓ |
| Filtrable por batch/rango de fechas | Selector de batch específico o rango de fechas | |

**User's choice:** Todos los MessageBatch históricos
**Notes:** Dato hoy 100% invisible — cualquier vista agregada es mejora; filtrado queda como mejora futura si se necesita operacionalmente.

---

## Gauge + histograma de encuestas SCALE (VIZ-05)

| Option | Description | Selected |
|--------|-------------|----------|
| Por encuesta/pregunta específica | Vive junto a esa encuesta, usa SurveyMetrics directamente, cero normalización | ✓ |
| Promedio global normalizado | Un solo gauge normalizando todas las preguntas SCALE de todas las encuestas | |

**User's choice:** Por encuesta/pregunta específica
**Notes:** Evita el problema de normalización entre rangos SCALE distintos (1-5 vs 1-10 configurable por pregunta).

| Option | Description | Selected |
|--------|-------------|----------|
| Página de detalle de la encuesta (ViewSurvey/EditSurvey) | Junto a SurveyResultsWidget, mismo patrón de footer widgets de Fase 21 | ✓ |
| Dashboard admin, encuesta más reciente/activa | Gauge+histograma visible en el dashboard general | |

**User's choice:** Página de detalle de la encuesta (ViewSurvey/EditSurvey)
**Notes:** Consistente con dónde ya vive SurveyResultsWidget.

---

## Claude's Discretion

- Ubicación exacta de los widgets donut (VIZ-01) y barras apiladas (VIZ-02) — sigue el precedente de widgets similares en el Admin dashboard.
- Ubicación de los 2 funnels — se adjuntan a su Resource natural (VerificationCallResource, MessageBatchResource).
- Implementación de los nuevos ChartRouter kinds: stacked-bar, funnel, gauge, histogram (ninguno existe hoy).
- Uso del componente Funnel nativo de Recharts vs. el truco de barra horizontal de MonoCharts.
- Estructura exacta de las queries de agregación nuevas (donut group-by-status, pivot de 3 buckets por coordinador, conteos de funnels).
- Comportamiento de estado vacío/error — sigue el estándar ya establecido en Fase 20 D-03, sin nueva decisión necesaria.
- Page-scoped vs panel-global para los nuevos widgets, con el recordatorio de registrar en `PAGE_SCOPED_WIDGETS` si aplica.

## Deferred Ideas

- Click-to-drill-through para el donut u otro chart nuevo de Fase 22 — declinado explícitamente para esta fase.
- Filtrado por batch/rango de fechas para el funnel de mensajería — declinado explícitamente para esta fase.
- Promedio global normalizado entre encuestas SCALE — declinado explícitamente para esta fase.
- Top-N para las barras apiladas por coordinador — declinado explícitamente para esta fase.
