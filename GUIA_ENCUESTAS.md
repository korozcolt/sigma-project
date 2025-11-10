# 📋 Guía de Configuración de Encuestas

## 🎯 Tipos de Preguntas y su Configuración

### 1. Sí/No (YES_NO)
**¿Cuándo usarlo?** Preguntas con respuesta binaria.

**Configuración:**
- ✅ No requiere configuración adicional
- Las opciones "Sí" y "No" están predefinidas

**Ejemplo:**
```
Pregunta: "¿Está satisfecho con el servicio?"
Respuestas posibles: Sí / No
```

---

### 2. Escala Numérica (SCALE)
**¿Cuándo usarlo?** Para calificaciones, ratings, niveles de satisfacción.

**Configuración:**
- Aparece campo "Configuración de Escala" con formato clave-valor
- Define:
  - `min_value`: Valor mínimo (ej: 1)
  - `max_value`: Valor máximo (ej: 10)

**Ejemplo:**
```
Pregunta: "¿Cómo califica nuestro servicio?"
Configuración: min_value = 1, max_value = 5
Respuestas posibles: 1, 2, 3, 4, 5
```

**Casos de uso:**
- Escala 1-5: Satisfacción básica
- Escala 1-10: NPS (Net Promoter Score)
- Escala 0-100: Porcentaje de aprobación

---

### 3. Selección Múltiple (MULTIPLE_CHOICE)
**¿Cuándo usarlo?** Cuando el usuario puede elegir **varias opciones**.

**Configuración:**
- Aparece un "Repeater" (lista de opciones)
- Muestra 3 campos por defecto
- Botón "Agregar Opción" para más
- Mínimo 2 opciones requeridas

**Ejemplo:**
```
Pregunta: "¿Qué servicios le interesan? (puede seleccionar varios)"
Opciones:
  ☑️ Salud
  ☑️ Educación
  ☑️ Seguridad
  ☑️ Vivienda
  ☑️ Empleo

Usuario puede seleccionar: Salud + Educación + Vivienda
```

---

### 4. Selección Única (SINGLE_CHOICE)
**¿Cuándo usarlo?** Cuando el usuario puede elegir **solo una opción**.

**Configuración:**
- Usa el mismo "Repeater" que selección múltiple
- Mínimo 2 opciones requeridas

**Ejemplo:**
```
Pregunta: "¿Cuál es su nivel educativo más alto?"
Opciones:
  ⚪ Sin estudios
  ⚪ Primaria
  ⚪ Secundaria
  ⚪ Técnico/Tecnólogo
  ⚪ Universidad
  ⚪ Posgrado

Usuario puede seleccionar: Solo Universidad
```

---

### 5. Texto Libre (TEXT)
**¿Cuándo usarlo?** Para respuestas abiertas, comentarios, sugerencias.

**Configuración:**
- Campo "Longitud Máxima"
- Por defecto: 500 caracteres
- Rango: 1 a 5,000 caracteres

**Ejemplo:**
```
Pregunta: "¿Qué sugerencias tiene para mejorar nuestro servicio?"
Longitud máxima: 1000 caracteres
Respuesta: [Campo de texto libre]
```

---

## 🔧 Flujo de Creación de Preguntas

### Paso 1: Acceder al formulario
1. Ir a `/admin/surveys`
2. Editar una encuesta existente
3. En la pestaña "Preguntas", hacer clic en "Crear"

### Paso 2: Completar campos básicos
- **Texto de la Pregunta**: La pregunta que verá el usuario
- **Tipo de Pregunta**: Seleccionar el tipo (los campos de configuración aparecerán dinámicamente)
- **Orden**: Posición de la pregunta en la encuesta
- **Pregunta Requerida**: ¿Es obligatorio responderla?

### Paso 3: Configurar según el tipo
**El formulario es REACTIVO**: Al cambiar el "Tipo de Pregunta", automáticamente aparecen los campos correspondientes:

#### Si seleccionas SCALE:
```
✅ Aparece: "Configuración de Escala"
   - Agregar pares clave-valor:
   - min_value: 1
   - max_value: 10
```

#### Si seleccionas MULTIPLE_CHOICE o SINGLE_CHOICE:
```
✅ Aparece: "Opciones de Respuesta" (Repeater)
   - Opción 1: [campo de texto]
   - Opción 2: [campo de texto]
   - Opción 3: [campo de texto]
   - [Botón: Agregar Opción]
```

#### Si seleccionas TEXT:
```
✅ Aparece: "Longitud Máxima"
   - Campo numérico (1 - 5000)
```

#### Si seleccionas YES_NO:
```
✅ No aparece configuración adicional
   (Las opciones Sí/No están predefinidas)
```

### Paso 4: Texto de ayuda (Opcional)
- Campo "Texto de Ayuda" disponible para TODOS los tipos
- Aparece debajo de la pregunta en la UI de respuesta
- Útil para aclaraciones o instrucciones

---

## 💡 Ejemplos Completos de Encuestas

### Ejemplo 1: Encuesta de Satisfacción del Votante
```
Encuesta: "Satisfacción con el Proceso de Registro"

Pregunta 1 (YES_NO):
  "¿El proceso de registro fue claro?"
  Configuración: Ninguna
  Requerida: Sí

Pregunta 2 (SCALE):
  "¿Qué tan satisfecho está con la atención recibida?"
  Configuración: min_value=1, max_value=5
  Texto de ayuda: "1 = Muy insatisfecho, 5 = Muy satisfecho"
  Requerida: Sí

Pregunta 3 (MULTIPLE_CHOICE):
  "¿Qué aspectos le gustaron? (puede seleccionar varios)"
  Opciones:
    - Rapidez del proceso
    - Amabilidad del personal
    - Claridad de la información
    - Ubicación del punto de registro
  Requerida: No

Pregunta 4 (TEXT):
  "Sugerencias para mejorar"
  Longitud máxima: 500
  Requerida: No
```

### Ejemplo 2: Encuesta de Intención de Voto
```
Encuesta: "Intención de Voto - Elecciones 2025"

Pregunta 1 (YES_NO):
  "¿Tiene intención de votar en las próximas elecciones?"
  Requerida: Sí

Pregunta 2 (SINGLE_CHOICE):
  "¿Por cuál candidato votaría?"
  Opciones:
    - Candidato A
    - Candidato B
    - Candidato C
    - Voto en blanco
    - No sabe / No responde
  Requerida: Sí

Pregunta 3 (MULTIPLE_CHOICE):
  "¿Qué temas son más importantes para usted?"
  Opciones:
    - Seguridad
    - Empleo
    - Salud
    - Educación
    - Corrupción
  Requerida: Sí

Pregunta 4 (SCALE):
  "¿Qué tan probable es que vote? (0 = nada probable, 10 = muy probable)"
  Configuración: min_value=0, max_value=10
  Requerida: Sí
```

---

## 🎨 Interfaz de Usuario

### Vista del Administrador (Creando preguntas)
```
┌─────────────────────────────────────────────────────────┐
│ Crear Pregunta                                          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Texto de la Pregunta *                                  │
│ ┌─────────────────────────────────────────────────────┐│
│ │ ¿Qué servicios le interesan?                        ││
│ └─────────────────────────────────────────────────────┘│
│                                                         │
│ Tipo de Pregunta *          Orden *                     │
│ ┌─────────────────────────┐ ┌────────┐                 │
│ │ Selección Múltiple ▼    │ │   1    │                 │
│ └─────────────────────────┘ └────────┘                 │
│                                                         │
│ ☑️ Pregunta Requerida                                   │
│                                                         │
│ ─────────────────────────────────────────────────────  │
│                                                         │
│ Opciones de Respuesta *                                 │
│ Agregue las opciones que el usuario podrá seleccionar  │
│                                                         │
│ Opción 1                                                │
│ ┌─────────────────────────────────────────────────────┐│
│ │ Salud                                               ││
│ └─────────────────────────────────────────────────────┘│
│                                                         │
│ Opción 2                                                │
│ ┌─────────────────────────────────────────────────────┐│
│ │ Educación                                           ││
│ └─────────────────────────────────────────────────────┘│
│                                                         │
│ Opción 3                                                │
│ ┌─────────────────────────────────────────────────────┐│
│ │ Seguridad                                           ││
│ └─────────────────────────────────────────────────────┘│
│                                                         │
│ [+ Agregar Opción]                                      │
│                                                         │
│ Texto de Ayuda (opcional)                               │
│ ┌─────────────────────────────────────────────────────┐│
│ │ Puede seleccionar varias opciones                   ││
│ └─────────────────────────────────────────────────────┘│
│                                                         │
│             [Cancelar]  [Crear Pregunta]                │
└─────────────────────────────────────────────────────────┘
```

---

## ⚠️ Validaciones y Restricciones

### Validaciones del Sistema
- ✅ Preguntas YES_NO: No permiten configuración adicional
- ✅ Preguntas SCALE: min_value debe ser menor que max_value
- ✅ Preguntas MULTIPLE_CHOICE/SINGLE_CHOICE: Mínimo 2 opciones
- ✅ Preguntas TEXT: Longitud máxima entre 1 y 5,000 caracteres
- ✅ Todas las preguntas: El campo "order" es único por encuesta

### Mejores Prácticas
1. **Orden lógico**: Organiza las preguntas de lo general a lo específico
2. **Preguntas requeridas**: Usa con moderación para no frustrar al usuario
3. **Texto de ayuda**: Úsalo para aclarar preguntas ambiguas
4. **Opciones de respuesta**: Máximo 7-10 opciones para no abrumar
5. **Escalas**: Usa 1-5 para encuestas rápidas, 1-10 para más precisión

---

## 🔍 Datos Almacenados en la Base de Datos

### Estructura de `configuration` (JSON)
```json
// Pregunta YES_NO
{
  "help_text": "Responda honestamente"
}

// Pregunta SCALE
{
  "scale": {
    "min_value": "1",
    "max_value": "10"
  },
  "help_text": "1 = Muy malo, 10 = Excelente"
}

// Pregunta MULTIPLE_CHOICE
{
  "options": [
    {"option": "Salud"},
    {"option": "Educación"},
    {"option": "Seguridad"},
    {"option": "Vivienda"}
  ],
  "help_text": "Puede seleccionar varias"
}

// Pregunta TEXT
{
  "max_length": 1000,
  "help_text": "Sea específico en su respuesta"
}
```

---

## 🚀 Acceso Rápido

- **Gestión de Encuestas**: https://sigma-project.test/admin/surveys
- **Crear Nueva Encuesta**: https://sigma-project.test/admin/surveys/create
- **Lista de Encuestas**: https://sigma-project.test/admin/surveys

---

**Fecha de creación**: 2025-11-09
**Sistema**: Sigma Project - Gestión de Campañas Políticas
