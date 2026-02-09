# 📋 Plan de Pruebas de Regresión - SIGMA (Protocolo Vivo)

Este documento es el **checklist oficial de regresión**. Se ejecuta:
- Después de cada feature relevante.
- Antes de cada release.

**Regla de proyecto (Stop-the-line):** si un flujo core está roto, no se construye encima.

---

## ✅ Checklist Global (multi-campaña)

- [ ] Contexto de campaña activo (selector para `super_admin`).
- [ ] Usuario no-super solo ve su campaña.
- [ ] Scopes globales aplican en listados y relaciones.
- [ ] Creación/edición fija `campaign_id` desde contexto.
- [ ] Gates bloquean acceso cruzado por campaña.

---

## ✅ Checklist por Módulo

### 1) Campañas
- [ ] Crear campaña con estado y configuración válida.
- [ ] `super_admin` puede alternar contexto sin pérdida de acceso.
- [ ] No hay auto-pausa de otras campañas.

### 2) Usuarios / Roles
- [ ] Líder/Coordinador solo ve miembros de su campaña.
- [ ] Asignación a campañas se restringe al contexto actual.

### 3) Votantes
- [ ] CRUD completo bajo contexto.
- [ ] Import/export respeta campaña activa.
- [ ] Estados y validaciones conservan campaña.

### 4) Encuestas
- [ ] Crear encuesta en campaña activa.
- [ ] Respuestas y métricas no mezclan campañas.

### 5) Call Center
- [ ] Cargar 5 en la campaña activa.
- [ ] Asignaciones no cruzan campañas.

### 6) Mensajería / SMS
- [ ] Plantillas y envíos quedan en campaña activa.
- [ ] Driver configurable (null/log/real) no rompe tests.

### 7) Día D / Eventos
- [ ] Eventos activos por campaña.
- [ ] VoteRecord y ValidationHistory en campaña correcta.

---

## 🧪 E2E (Chrome DevTools)

- [ ] Día D (votar / no votar / evidencia obligatoria).
- [ ] Roles y accesos (5 roles).
- [ ] Call Center (Cargar 5).
- [ ] Mensajería SMS.
- [ ] Cierre de evento electoral.

**Nota:** Si E2E es simulado, debe decirlo explícitamente y no prometer MCP real.

---

## 🧪 Visual E2E (Navegador real)

**Objetivo:** Ejecutar pruebas visuales reales desde navegador para TODOS los roles y flujos críticos.

### Roles cubiertos
- `super_admin`
- `admin_campaign`
- `coordinator`
- `leader`
- `reviewer`

### Flujos cubiertos (mínimo)
- Login / Dashboard
- Campañas
- Usuarios
- Votantes
- Encuestas
- Mensajes / Plantillas / Envíos
- Call Center / Llamadas de verificación
- Gestión de Eventos / Día D
- Invitaciones
- Configuración territorial (Deptos / Municipios / Barrios)

### Comando principal
```bash
php artisan db:seed --class=VisualE2ESeeder
npx playwright test -c tests/Visual/visual.config.js --update-snapshots
```

### Evidencia
- Baselines: `tests/Visual/__screenshots__`
- Reporte: `output/playwright-report/index.html`
- Artefactos: `output/playwright/`

---

## 📌 Reglas de ejecución

- Registrar resultados en `PROGRESO.md`.
- Si falla un test crítico, se corrige y se agrega prueba.
- Cada fix debe reflejarse en `CHANGELOG.md`.
