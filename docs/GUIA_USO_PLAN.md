# 📖 Guía de Uso del Plan de Desarrollo

## 🎯 Propósito

Este documento explica cómo usar efectivamente el plan de desarrollo y los documentos de tracking de SIGMA.

---

## 📚 Documentos del Plan

### 1. `PLAN_DESARROLLO.md` (Plan Maestro)

**Ubicación:** Raíz del proyecto

**Propósito:** Documento completo y detallado con todas las tareas, archivos a crear, y especificaciones técnicas.

**Cuándo usar:**
- Al inicio de cada fase para entender qué se debe hacer
- Para ver especificaciones técnicas detalladas
- Como referencia de arquitectura
- Para planificar sprints

**No usar para:**
- Tracking diario (muy extenso)
- Comunicar progreso rápido

---

### 2. `PROGRESO.md` (Tracking Diario)

**Ubicación:** Raíz del proyecto

**Propósito:** Vista rápida del estado actual, progreso semanal y próximos pasos.

**Cuándo usar:**
- Al inicio de cada día de desarrollo
- Para actualizar progreso después de completar tareas
- Para comunicar estado a stakeholders
- Para planning de sprint

**Actualizar:**
- ✅ Cada vez que completes un módulo
- ✅ Al final de cada día
- ✅ Al inicio de cada semana (sección "Esta Semana")

---

### 3. `SIGMA.md` (Documentación de Negocio)

**Ubicación:** Raíz del proyecto

**Propósito:** Especificación del dominio electoral y reglas de negocio.

**Cuándo consultar:**
- Cuando tengas dudas sobre reglas de negocio
- Para entender el flujo electoral
- Para validar que la implementación cumple requisitos

---

### 4. `CLAUDE.md` (Guías de Desarrollo)

**Ubicación:** Raíz del proyecto

**Propósito:** Guidelines de Laravel, Filament, Livewire, y mejores prácticas del proyecto.

**Cuándo usar:**
- Antes de escribir código nuevo
- Para verificar convenciones del proyecto
- Para recordar sintaxis de Pest, Volt, etc.

---

## 🔄 Flujo de Trabajo Recomendado

### Al Iniciar el Día

```bash
# 1. Revisar progreso
cat PROGRESO.md

# 2. Ver qué sigue
# Leer sección "Próximos 3 Pasos"

# 3. Consultar detalles en plan maestro
# Abrir PLAN_DESARROLLO.md en la fase actual
```

### Al Trabajar en una Tarea

```bash
# 1. Leer especificación completa en PLAN_DESARROLLO.md
# Ejemplo: FASE 1.1 - Modelo de Departamento

# 2. Verificar guidelines en CLAUDE.md
# ¿Cómo crear modelos?
# ¿Cómo usar Filament?

# 3. Implementar código

# 4. Escribir tests

# 5. Ejecutar tests
php artisan test --filter=DepartmentTest

# 6. Formatear código
vendor/bin/pint --dirty
```

### Al Completar una Tarea

```bash
# 1. Marcar en PLAN_DESARROLLO.md
- [x] Crear modelo Department

# 2. Actualizar PROGRESO.md
- Incrementar progreso de fase
- Actualizar estadísticas
- Agregar nota de desarrollo

# 3. Commit
git add .
git commit -m "feat: add Department model with Filament resource"
```

---

## ✅ Checklist por Módulo

Para cada módulo que implementes, asegúrate de:

- [ ] **Leer especificación** completa en PLAN_DESARROLLO.md
- [ ] **Crear rama** de feature
- [ ] **Implementar** código según especificación
- [ ] **Escribir tests** (mínimo happy path)
- [ ] **Ejecutar tests** y verificar que pasen
- [ ] **Formatear** código con Pint
- [ ] **Marcar tarea** como completa en PLAN_DESARROLLO.md
- [ ] **Actualizar PROGRESO.md** con nuevo porcentaje
- [ ] **Commit** con mensaje semántico
- [ ] **Push** de rama
- [ ] **Merge** cuando sea apropiado

---

## 📊 Cómo Actualizar Progreso

### En PLAN_DESARROLLO.md

Buscar la tarea y cambiar:

```markdown
- [ ] Crear modelo Department
```

A:

```markdown
- [x] Crear modelo Department
```

### En PROGRESO.md

#### 1. Actualizar tabla de visión general:

```markdown
| 1 | Estructura Territorial | 🚧 En Progreso | 33% | 🔥 Alta |
```

#### 2. Actualizar sección de fase:

```markdown
### Módulos
- [x] 1.1 Departamento - 5/5 tareas ✅
- [ ] 1.2 Municipio - 0/5 tareas
- [ ] 1.3 Barrio - 0/4 tareas

**Progreso:** 1/3 módulos (33%)
```

#### 3. Actualizar estadísticas:

```markdown
| Modelos | 20+ | 2 | 18+ |  # Incrementó de 1 a 2
```

#### 4. Agregar nota de desarrollo:

```markdown
### 2025-11-03
- ✅ Módulo Department completado
- 🚧 Iniciado módulo Municipality
```

---

## 🎯 Prioridades

### Orden de Implementación

Seguir estrictamente el orden de fases:

```
FASE 0 → FASE 1 → FASE 2 → FASE 3 → FASE 4 → FASE 5 → FASE 6 → FASE 7
```

**¿Por qué?**
- Cada fase depende de la anterior
- Evita re-trabajo
- Estructura lógica

### Dentro de Cada Fase

1. **Primero:** Modelos y migraciones
2. **Segundo:** Seeders y factories
3. **Tercero:** Resources de Filament
4. **Cuarto:** Tests
5. **Quinto:** Volt components (si aplica)

---

## 🧪 Testing

### Regla de Oro

**No marcar una tarea como completa si los tests no pasan.**

### Comando Rápido

```bash
# Test específico
php artisan test --filter=NombreTest

# Tests de una fase
php artisan test tests/Feature/Phase1/

# Todos los tests
php artisan test
```

### Antes de Merge

```bash
# Ejecutar suite completa
php artisan test

# Verificar cobertura
php artisan test --coverage

# Formatear
vendor/bin/pint
```

---

## 📝 Mensajes de Commit

### Formato Semántico

```bash
tipo(scope): descripción

# Ejemplos:
feat(voters): add Voter model with census validation
fix(campaign): correct date validation in Campaign model
test(department): add CRUD tests for Department
docs(readme): update installation instructions
refactor(territory): simplify Municipality relationships
```

### Tipos:
- `feat`: Nueva funcionalidad
- `fix`: Corrección de bug
- `test`: Agregar o mejorar tests
- `docs`: Documentación
- `refactor`: Refactorización sin cambio funcional
- `style`: Formato (pint)
- `chore`: Tareas de mantenimiento

---

## 🚨 Señales de Alerta

### ❌ No avanzar si:

- Tests no pasan
- Pint reporta errores
- Código tiene TODOs sin resolver
- No hay tests para código nuevo
- Saltaste una fase anterior

### ✅ Ok para avanzar si:

- Todos los tests pasan
- Código está formateado
- Tarea marcada en ambos documentos
- Commit realizado

---

## 📞 Preguntas Frecuentes

### ¿Puedo saltar fases?

**No.** Cada fase depende de la anterior. Saltar fases generará problemas.

### ¿Puedo trabajar en paralelo en múltiples fases?

**No recomendado.** Mejor terminar una fase completamente antes de continuar.

### ¿Qué hago si encuentro algo no planeado?

1. Agregarlo al PLAN_DESARROLLO.md en la fase apropiada
2. Actualizar PROGRESO.md
3. Documentar decisión en `docs/DECISIONES.md`

### ¿Cada cuánto actualizo PROGRESO.md?

**Mínimo una vez al día**, idealmente cada vez que completes un módulo.

### ¿Debo seguir PLAN_DESARROLLO.md al pie de la letra?

El plan es una guía, pero puedes:
- Ajustar nombres de archivos si hay mejor convención
- Agregar campos a modelos si son necesarios
- Mejorar especificaciones

**Importante:** Documenta cambios significativos.

---

## 🎨 Tips para Eficiencia

### 1. Usa Snippets

Crea snippets para tareas repetitivas:

```php
// Snippet para modelo base
php artisan make:model {Name} -mfsr
```

### 2. Trabaja por Bloques

Implementa todo un módulo antes de pasar al siguiente:
- Modelo
- Migración
- Factory
- Seeder
- Resource
- Tests

### 3. Revisa Examples

Antes de implementar, revisa código existente similar:
- User model para ver estructura
- Tests existentes para ver patterns
- Volt components para ver sintaxis

### 4. Documenta Decisiones

Si tomas una decisión importante (ej: usar tabla separada para Leaders vs rol), documentala en `docs/DECISIONES.md`.

---

## 📅 Planificación Semanal

### Lunes
- Revisar PROGRESO.md
- Planificar objetivos de semana
- Actualizar sección "Esta Semana"

### Diario
- Marcar progreso en checkboxes
- Actualizar porcentajes
- Agregar notas

### Viernes
- Review de semana
- Actualizar estadísticas
- Planificar próxima semana

---

## 🎯 Objetivo Final

Al terminar todas las fases:

```markdown
**Progreso Total:** 100% (28/28 módulos) ✅

✅ FASE 0: Configuración Base
✅ FASE 1: Estructura Territorial
✅ FASE 2: Sistema Multi-Campaña
✅ FASE 3: Gestión de Usuarios
✅ FASE 4: Módulo de Votantes
✅ FASE 5: Validación y Censo
✅ FASE 6: Módulos Estratégicos
✅ FASE 7: Reportes y Analítica
```

---

**¡Feliz desarrollo! 🚀**
