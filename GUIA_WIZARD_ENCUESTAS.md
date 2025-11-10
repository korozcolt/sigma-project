# 🧙‍♂️ Guía del Wizard de Preguntas - Paso a Paso

## 📋 Nuevo Flujo de Creación de Preguntas

El formulario de creación de preguntas ahora usa un **Wizard (asistente paso a paso)** que guía al usuario a través de 4 pasos claros y ordenados.

---

## 🎯 Los 4 Pasos del Wizard

### Paso 1: 📝 Información Básica
**Icono**: ✏️ Lápiz
**Descripción**: "Defina la pregunta y sus propiedades"

**Campos**:
- **Texto de la Pregunta** (Requerido)
  - Área de texto de 3 líneas
  - Placeholder: "Ejemplo: ¿Está satisfecho con nuestro servicio?"
  - Ayuda: "Escriba la pregunta exactamente como la verá el usuario"

- **Orden** (Requerido)
  - Número que indica la posición
  - Se auto-completa con el siguiente número disponible
  - Ayuda: "Posición de esta pregunta en la encuesta"

- **Pregunta Requerida** (Toggle)
  - Por defecto: NO
  - Ayuda: "¿El usuario debe responder obligatoriamente?"

**Botón**: [Siguiente →]

---

### Paso 2: 🎯 Tipo de Pregunta
**Icono**: 📋 Lista
**Descripción**: "Seleccione el formato de respuesta"

**Campo**:
- **¿Cómo desea que el usuario responda?** (Requerido)

**Opciones**:
```
✓ Sí / No
  → Respuesta binaria simple

📊 Escala Numérica
  → Calificación (ej: 1-5, 1-10)

⚪ Selección Única
  → Elegir solo una opción

☑️  Selección Múltiple
  → Elegir varias opciones

📝 Texto Libre
  → Respuesta abierta
```

**Botones**: [← Anterior] [Siguiente →]

---

### Paso 3: ⚙️ Configuración
**Icono**: ⚙️ Engranaje
**Descripción**: "Configure las opciones específicas"

El contenido de este paso **cambia dinámicamente** según lo que seleccionaste en el Paso 2:

#### Si seleccionaste "Sí / No":
```
┌──────────────────────────────────────────┐
│ Configuración                            │
├──────────────────────────────────────────┤
│ Las opciones "Sí" y "No" están          │
│ predefinidas. No necesita configuración  │
│ adicional.                               │
└──────────────────────────────────────────┘
```
**Acción**: Solo dar clic en [Siguiente →]

#### Si seleccionaste "Escala Numérica":
```
┌──────────────────────────────────────────┐
│ Valor Mínimo *     Valor Máximo *       │
│ [____1____]        [____5____]           │
│                                          │
│ Número más bajo    Número más alto      │
│ de la escala       de la escala          │
└──────────────────────────────────────────┘
```
**Ejemplo**: Para una escala del 1 al 10, pon `1` y `10`

#### Si seleccionaste "Selección Única" o "Selección Múltiple":
```
┌──────────────────────────────────────────┐
│ Opciones de Respuesta *                  │
├──────────────────────────────────────────┤
│ Opción 1: [Candidato A_______________]   │
│ Opción 2: [Candidato B_______________]   │
│ Opción 3: [Candidato C_______________]   │
│                                          │
│ [+ Agregar Opción]                       │
│                                          │
│ Agregue todas las opciones que el       │
│ usuario podrá seleccionar. Mínimo 2.    │
└──────────────────────────────────────────┘
```
**Características**:
- Mínimo 2 opciones (obligatorio)
- Puedes agregar más con [+ Agregar Opción]
- Puedes reordenar arrastrando
- Puedes eliminar opciones

#### Si seleccionaste "Texto Libre":
```
┌──────────────────────────────────────────┐
│ Longitud Máxima (caracteres) *          │
│ [____500____]                            │
│                                          │
│ Cantidad máxima de caracteres           │
│ permitidos en la respuesta               │
└──────────────────────────────────────────┘
```
**Rango**: De 1 a 5,000 caracteres

**Botones**: [← Anterior] [Siguiente →]

---

### Paso 4: ✅ Finalizar
**Icono**: ✓ Check
**Descripción**: "Texto de ayuda opcional"

**Campo**:
- **Texto de Ayuda** (Opcional)
  - Área de texto de 3 líneas
  - Placeholder: "Ejemplo: Seleccione la opción que mejor describa su experiencia..."
  - Ayuda: "Este texto aparecerá debajo de la pregunta para guiar al usuario"

**Botones**: [← Anterior] [Guardar cambios]

---

## 🎬 Ejemplo Completo: Crear Pregunta de Intención de Voto

### Escenario
Quieres crear la pregunta: "¿Por cuál candidato votaría en las próximas elecciones?"

### Paso a Paso

#### **Paso 1: Información Básica**
```
Texto de la Pregunta:
┌──────────────────────────────────────────┐
│ ¿Por cuál candidato votaría en las      │
│ próximas elecciones?                     │
└──────────────────────────────────────────┘

Orden: [1]                    ☑️ Pregunta Requerida

[Siguiente →]
```

#### **Paso 2: Tipo de Pregunta**
```
¿Cómo desea que el usuario responda?
┌──────────────────────────────────────────┐
│ ⚪ Selección Única                       │
│   → Elegir solo una opción               │
└──────────────────────────────────────────┘

[← Anterior]  [Siguiente →]
```

#### **Paso 3: Configuración**
```
Opciones de Respuesta *
┌──────────────────────────────────────────┐
│ Opción 1: [Candidato A_______________]   │
│ Opción 2: [Candidato B_______________]   │
│ Opción 3: [Candidato C_______________]   │
│ Opción 4: [Voto en blanco____________]   │
│ Opción 5: [No sabe / No responde_____]   │
│                                          │
│ [+ Agregar Opción]                       │
└──────────────────────────────────────────┘

[← Anterior]  [Siguiente →]
```

#### **Paso 4: Finalizar**
```
Texto de Ayuda (Opcional)
┌──────────────────────────────────────────┐
│ Seleccione el candidato de su           │
│ preferencia para las elecciones          │
│ municipales de 2025                      │
└──────────────────────────────────────────┘

[← Anterior]  [Guardar cambios]
```

✅ **Resultado**: Pregunta creada exitosamente!

---

## 🎨 Ventajas del Wizard

### ✓ Mejor Experiencia de Usuario
- Proceso guiado y claro
- No hay confusión sobre qué llenar
- Validación paso a paso
- Menos errores

### ✓ Mobile Friendly
- Cada paso ocupa toda la pantalla
- No hay problemas de responsive
- Scroll mínimo en cada paso
- Botones grandes y accesibles

### ✓ Flexibilidad
- Puedes saltar pasos (skippable)
- Puedes volver atrás y modificar
- El progreso se guarda en la URL
- Si refrescas la página, vuelves al mismo paso

### ✓ Visual Claro
- Iconos representativos en cada paso
- Descripciones claras
- Barra de progreso visible
- Indicador de paso actual

---

## ⌨️ Atajos de Teclado

- **Tab**: Navegar entre campos
- **Enter**: Siguiente paso (si todos los campos están llenos)
- **Esc**: Cerrar el wizard

---

## 📱 Cómo Acceder

1. Ve a `/admin/surveys`
2. Haz clic en una encuesta para editarla
3. En la pestaña "Preguntas", haz clic en **"Nueva Pregunta"**
4. ¡El wizard aparece!

---

## 🔄 Editar vs Crear

- **Crear**: El wizard muestra 4 pasos completos
- **Editar**: El wizard muestra los mismos 4 pasos con los valores actuales pre-llenados

---

## 💡 Tips y Mejores Prácticas

### 1. Orden de las Preguntas
- Empieza con preguntas generales
- Termina con preguntas específicas
- Agrupa preguntas relacionadas

### 2. Texto de las Preguntas
- Sé claro y conciso
- Evita ambigüedades
- Usa lenguaje simple

### 3. Opciones de Respuesta
- Máximo 7-10 opciones
- Ordénalas lógicamente (alfabético, más común primero, etc.)
- Incluye "Otro" o "No sabe/No responde" cuando sea relevante

### 4. Texto de Ayuda
- Úsalo solo cuando sea necesario
- Debe ser breve (1-2 líneas)
- Aclara dudas comunes

### 5. Preguntas Requeridas
- No abuses de las preguntas obligatorias
- Solo marca como requeridas las verdaderamente importantes
- Considera el abandono de encuesta

---

## 🐛 Solución de Problemas

### "No puedo avanzar al siguiente paso"
- Verifica que todos los campos requeridos (*) estén llenos
- Los campos con asterisco rojo necesitan ser completados

### "Mis opciones no se guardan"
- Asegúrate de agregar al menos 2 opciones
- Cada opción debe tener texto (no puede estar vacía)

### "El wizard desapareció"
- Probablemente diste clic fuera del modal
- Haz clic en "Nueva Pregunta" de nuevo
- El progreso NO se pierde

---

## 📊 Resumen Visual

```
Flujo Completo:
================

[1. Información Básica]
         ↓
[2. Tipo de Pregunta]
         ↓
[3. Configuración] ← Contenido dinámico según tipo
         ↓
[4. Finalizar]
         ↓
    ✅ GUARDADO
```

---

**Última actualización**: 2025-11-09
**Sistema**: Sigma Project - Gestión de Campañas Políticas
