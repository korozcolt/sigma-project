# 📚 Índice de Documentación SIGMA

Bienvenido al centro de documentación del proyecto SIGMA. Aquí encontrarás toda la información necesaria para trabajar en el proyecto.

---

## 🗂️ Estructura de Documentación

### 📖 Documentos en Raíz del Proyecto

| Documento | Descripción | Para quién |
|-----------|-------------|------------|
| **[README.md](../README.md)** | Información general del proyecto, instalación y stack | Todos |
| **[SIGMA.md](../SIGMA.md)** | Especificación completa del dominio electoral | Product/Dev |
| **[PLAN_DESARROLLO.md](../PLAN_DESARROLLO.md)** | Plan maestro con todas las tareas y especificaciones técnicas | Developers |
| **[PROGRESO.md](../PROGRESO.md)** | Tracking diario del avance del proyecto | Todos |
| **[CLAUDE.md](../CLAUDE.md)** | Guidelines de Laravel, Filament, y mejores prácticas | Developers |

### 📁 Documentos en `/docs`

| Documento | Descripción | Para quién |
|-----------|-------------|------------|
| **[GUIA_USO_PLAN.md](./GUIA_USO_PLAN.md)** | Cómo usar efectivamente los documentos de planificación | Developers |
| **[DECISIONES.md](./DECISIONES.md)** | Registro de decisiones técnicas (ADR) | Tech Leads/Devs |
| **[CHEATSHEET.md](./CHEATSHEET.md)** | Referencia rápida de comandos y patrones | Developers |

---

## 🎯 Guías por Rol

### 👨‍💼 Nuevo en el Proyecto

1. Lee **[README.md](../README.md)** para visión general
2. Lee **[SIGMA.md](../SIGMA.md)** para entender el dominio
3. Consulta **[PROGRESO.md](../PROGRESO.md)** para estado actual

### 👨‍💻 Desarrollador Nuevo

1. Lee **[README.md](../README.md)** - Instalación y setup
2. Lee **[SIGMA.md](../SIGMA.md)** - Reglas de negocio
3. Lee **[CLAUDE.md](../CLAUDE.md)** - Convenciones del proyecto
4. Lee **[GUIA_USO_PLAN.md](./GUIA_USO_PLAN.md)** - Workflow de desarrollo
5. Consulta **[PLAN_DESARROLLO.md](../PLAN_DESARROLLO.md)** para tareas
6. Usa **[CHEATSHEET.md](./CHEATSHEET.md)** como referencia diaria

### 🏗️ Tech Lead / Arquitecto

1. Lee **[SIGMA.md](../SIGMA.md)** - Dominio del negocio
2. Lee **[PLAN_DESARROLLO.md](../PLAN_DESARROLLO.md)** - Arquitectura completa
3. Lee **[DECISIONES.md](./DECISIONES.md)** - Decisiones técnicas
4. Consulta **[PROGRESO.md](../PROGRESO.md)** - Estado del proyecto

### 📊 Project Manager

1. Lee **[README.md](../README.md)** - Visión general
2. Consulta **[PROGRESO.md](../PROGRESO.md)** - Tracking diario
3. Revisa **[PLAN_DESARROLLO.md](../PLAN_DESARROLLO.md)** - Scope completo

---

## 📖 Guías por Tarea

### Quiero Entender el Sistema

**Leer en orden:**
1. [README.md](../README.md) - ¿Qué es SIGMA?
2. [SIGMA.md](../SIGMA.md) - ¿Cómo funciona?
3. [PLAN_DESARROLLO.md](../PLAN_DESARROLLO.md) - ¿Cómo está construido?

### Quiero Comenzar a Desarrollar

**Leer en orden:**
1. [README.md](../README.md) - Instalación
2. [CLAUDE.md](../CLAUDE.md) - Convenciones
3. [GUIA_USO_PLAN.md](./GUIA_USO_PLAN.md) - Workflow
4. [CHEATSHEET.md](./CHEATSHEET.md) - Comandos útiles
5. [PLAN_DESARROLLO.md](../PLAN_DESARROLLO.md) - Qué construir

### Quiero Saber Qué Hacer Hoy

**Consultar:**
1. [PROGRESO.md](../PROGRESO.md) - Sección "Esta Semana" y "Próximos Pasos"
2. [PLAN_DESARROLLO.md](../PLAN_DESARROLLO.md) - Detalle de la tarea actual

### Quiero Entender una Decisión Técnica

**Consultar:**
1. [DECISIONES.md](./DECISIONES.md) - Buscar ADR específico

### Necesito Referencia Rápida

**Consultar:**
1. [CHEATSHEET.md](./CHEATSHEET.md) - Comandos, patterns, snippets

---

## 🔍 Búsqueda Rápida

### Conceptos de Negocio

- **Campañas** → [SIGMA.md](../SIGMA.md) - Sección "Conceptos Fundamentales"
- **Roles de Usuario** → [SIGMA.md](../SIGMA.md) - Sección "Usuarios y Roles"
- **Flujo Electoral** → [SIGMA.md](../SIGMA.md) - Sección "Etapas y Estados"
- **Territorio** → [SIGMA.md](../SIGMA.md) - Sección "Territorio"

### Tareas de Desarrollo

- **Qué falta hacer** → [PROGRESO.md](../PROGRESO.md)
- **Cómo hacer una tarea** → [PLAN_DESARROLLO.md](../PLAN_DESARROLLO.md)
- **Convenciones de código** → [CLAUDE.md](../CLAUDE.md)
- **Workflow de desarrollo** → [GUIA_USO_PLAN.md](./GUIA_USO_PLAN.md)

### Comandos y Código

- **Artisan commands** → [CHEATSHEET.md](./CHEATSHEET.md) - Sección "Crear Componentes"
- **Testing patterns** → [CHEATSHEET.md](./CHEATSHEET.md) - Sección "Testing"
- **Filament patterns** → [CHEATSHEET.md](./CHEATSHEET.md) - Sección "Filament"
- **Git workflow** → [CHEATSHEET.md](./CHEATSHEET.md) - Sección "Git"

### Decisiones Técnicas

- **Stack tecnológico** → [DECISIONES.md](./DECISIONES.md) - ADR-001
- **Sistema de roles** → [DECISIONES.md](./DECISIONES.md) - ADR-002
- **Multi-tenancy** → [DECISIONES.md](./DECISIONES.md) - PD-004
- **Mensajería** → [DECISIONES.md](./DECISIONES.md) - PD-002

---

## 📅 Uso Diario Recomendado

### Inicio del Día

```bash
# 1. Ver estado y qué hacer
cat PROGRESO.md | head -30

# 2. Ver detalles de tarea
# Abrir PLAN_DESARROLLO.md en la fase actual
```

### Durante Desarrollo

```bash
# Consultar convenciones
# Abrir CLAUDE.md

# Comandos rápidos
# Abrir CHEATSHEET.md
```

### Fin del Día

```bash
# Actualizar progreso
# Editar PROGRESO.md con avances del día
```

---

## ✅ Checklist de Lectura Inicial

Para nuevos desarrolladores, marcar cuando completes:

- [ ] He leído README.md completo
- [ ] Entiendo el dominio (SIGMA.md)
- [ ] Conozco las convenciones (CLAUDE.md)
- [ ] Entiendo el workflow (GUIA_USO_PLAN.md)
- [ ] Tengo el CHEATSHEET.md a mano
- [ ] He instalado el proyecto localmente
- [ ] He ejecutado los tests existentes
- [ ] Sé dónde consultar el progreso (PROGRESO.md)
- [ ] Sé dónde ver tareas (PLAN_DESARROLLO.md)

---

## 🔗 Enlaces Externos Útiles

### Framework y Librerías

- [Laravel 12 Docs](https://laravel.com/docs/12.x)
- [Filament 4 Docs](https://filamentphp.com/docs/4.x)
- [Livewire 3 Docs](https://livewire.laravel.com/docs)
- [Volt Docs](https://livewire.laravel.com/docs/volt)
- [Pest Docs](https://pestphp.com/docs)
- [Tailwind CSS 4 Docs](https://tailwindcss.com/docs)
- [Flux UI Docs](https://fluxui.dev/docs)

### Packages

- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- [Laravel Fortify](https://laravel.com/docs/12.x/fortify)

### Herramientas

- [Laravel Herd](https://herd.laravel.com)
- [Laravel Pint](https://laravel.com/docs/12.x/pint)

---

## 📝 Mantenimiento de Documentación

### Cuándo Actualizar

| Documento | Cuándo Actualizar |
|-----------|-------------------|
| README.md | Cambios mayores en stack o arquitectura |
| SIGMA.md | Cambios en reglas de negocio |
| PLAN_DESARROLLO.md | Agregar/modificar tareas o fases |
| PROGRESO.md | Diariamente al completar tareas |
| CLAUDE.md | Nuevas convenciones del proyecto |
| DECISIONES.md | Cada decisión técnica importante |
| CHEATSHEET.md | Nuevos comandos o patterns útiles |

### Responsabilidad

- **Developers:** PROGRESO.md (diario)
- **Tech Lead:** DECISIONES.md, PLAN_DESARROLLO.md
- **Product:** SIGMA.md (si cambian reglas)
- **Todos:** README.md, CHEATSHEET.md

---

## 💡 Tips

- 📌 **Bookmarks:** Guarda este índice en favoritos
- 🔍 **Search:** Usa Cmd/Ctrl+F para buscar en documentos
- 📱 **Mobile:** Todos los .md se leen bien en GitHub mobile
- 🔄 **Sync:** Pull antes de leer documentos para ver última versión
- 📝 **Notes:** Agrega tus propias notas en archivos personales

---

## 🆘 Necesito Ayuda

### No encuentro información sobre...

1. Busca en este índice por tema
2. Usa Cmd+F en el documento relevante
3. Consulta CHEATSHEET.md
4. Pregunta al equipo

### No sé cómo hacer...

1. Consulta CHEATSHEET.md primero
2. Revisa CLAUDE.md para convenciones
3. Lee documentación oficial del package
4. Pregunta al equipo

### No sé por qué se decidió...

1. Consulta DECISIONES.md
2. Busca el ADR relevante
3. Pregunta al Tech Lead

---

**Última Actualización:** 2025-11-02

**¡Feliz desarrollo! 🚀**
