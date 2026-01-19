## Reglas de negocio (SIGMA)

Este documento describe las reglas de negocio vigentes y los cambios recientes para facilitar trazabilidad y pruebas de regresión.

### 1) Campaña (operación por instancia)

- La operación esperada es **1 campaña por instancia**.
- En el sistema se mantiene el modelo `Campaign` por compatibilidad, pero se restringe el uso:
  - **Solo puede existir una campaña activa** al mismo tiempo. Si una campaña se guarda como `active`, el sistema pausa automáticamente cualquier otra campaña activa.
  - En Filament, la creación de campañas queda permitida **solo si no existe ninguna campaña** (se busca evitar múltiples campañas por instancia). El borrado de campañas está deshabilitado.
- El `campaign_id` se mantiene en tablas y pantallas por economía de cambios y compatibilidad.

**Implicaciones para pruebas:**
- Casos de regresión deben asumir 0 o 1 campaña activa.
- Si una prueba intenta crear 2 campañas activas, la segunda debe causar que la primera pase a `paused`.

### 2) Unicidad global del documento del votante

- `voters.document_number` es **único global** (en toda la base de datos).
- El formulario de Votantes en Filament valida unicidad global y muestra el mensaje correspondiente.

**Implicaciones para datos/migración:**
- Si existen votantes con el mismo `document_number` en distintas campañas, la migración fallará. Se debe depurar/mergear antes de aplicar en producción.

**Implicaciones para pruebas:**
- Probar que no se puede crear el mismo documento en campañas distintas.
- Probar que el mensaje de validación se emite en Filament en el campo `document_number`.

### 3) Call center: cola por revisor con “Cargar 5”

- El flujo del revisor se opera desde el panel Admin.
- La cola ya no es una “lista global”; es una **cola asignada por revisor**:
  - Acción `Cargar 5` asigna votantes elegibles al revisor hasta completar una cola de 5 (o menos si no hay disponibilidad).
  - La asignación “bloquea” el votante para otros revisores mientras la asignación esté en `pending` o `in_progress`.
  - Las asignaciones **no expiran** automáticamente: se mantienen hasta que se completen.
- Los votantes elegibles para asignación siguen el criterio de call center:
  - Sin llamadas exitosas previas (`answered`/`confirmed`), o con intentos fallidos reintento (por ejemplo `no_answer`/`busy`/`callback_requested` con `attempt_number < 3`).
  - Con teléfono disponible.
- Al registrar una llamada desde la cola:
  - Se crea `verification_calls` vinculado a la asignación.
  - Si se selecciona una encuesta y el contacto fue exitoso, el sistema redirige a aplicar la encuesta con `call_id` para guardar histórico por llamada.

**Implicaciones para pruebas:**
- Probar que `Cargar 5` no sobre-asigna si ya hay `pending/in_progress`.
- Probar que 2 revisores no reciben el mismo votante (bloqueo por asignación).
- Probar que registrar llamada desde la cola cambia el estado de la asignación (y crea `verification_calls`).

### 4) Encuestas: histórico por llamada

- Las respuestas de encuesta se guardan asociadas a una llamada (`verification_call_id`) para mantener histórico por intento.
- La unicidad/actualización se hace por **(llamada + pregunta)** (no por votante), evitando sobreescritura entre intentos.

**Implicaciones para pruebas:**
- Probar que una misma pregunta puede tener múltiples respuestas históricas si hay múltiples llamadas (distinto `verification_call_id`).
- Probar que dentro de la misma llamada, re-guardar una respuesta de la misma pregunta actualiza (no duplica).

### 5) Día D: evidencia obligatoria para marcar “VOTÓ”

- Para marcar `VOTÓ` es obligatorio:
  - **Foto** (subida desde cámara o galería)
  - **GPS** (captura automática desde el navegador; se requiere que el usuario otorgue permisos)
- La evidencia se almacena en `storage` usando el disco `public` (recomendado tener `php artisan storage:link` en despliegue).
- Para `NO VOTÓ` no se exige evidencia.

**Implicaciones para pruebas:**
- Probar que `markVoted` falla si no hay `photo`/coordenadas.
- Probar que se crea `vote_records` con `photo_path`, `latitude`, `longitude` cuando se marca `VOTÓ`.

### 6) Cierre de evento electoral (Día D)

- Al cierre (desactivar un evento electoral), se ejecuta un proceso que marca como `did_not_vote` a los votantes de la campaña del evento que:
  - estén en estados previos relevantes (`verified_call` o `confirmed`), y
  - **no tengan** `vote_records` en ese evento.
- Se genera `validation_histories` con `validation_type = election` para auditoría del cierre.

**Implicaciones para pruebas:**
- Probar que al desactivar el evento se actualiza el estado de los votantes sin registro.
- Probar que se crea el historial de validación.

### 7) Auditoría (prioridad)

Para regresión, las acciones mínimas a auditar/verificar son:
- Creación/edición/borrado de votantes y cambios de estado.
- Llamadas y resultados (y su vínculo con asignaciones).
- Respuestas de encuestas ligadas a llamadas.
- Envío de mensajes (si aplica el canal).

### 8) Pruebas de regresión (mapa rápido)

Archivos de pruebas que validan estas reglas (orientativo):

- Campaña única activa: `tests/Feature/CampaignTest.php`
- Unicidad global de documento del votante:
  - `tests/Feature/VoterTest.php`
  - `tests/Feature/Filament/VoterResourceTest.php`
- Día D evidencia + flujo:
  - `tests/Feature/Livewire/DiaDComponentTest.php`
  - `tests/Browser/DiaDVotingFlowTest.php`
- Call center (acceso/página/widgets): `tests/Feature/Filament/CallCenterPageTest.php`

**Nota sobre Browser tests**
- Los browser tests usan `pestphp/pest-plugin-browser` y ejecutan un servidor de Playwright para controlar un navegador Chromium.
- A nivel práctico, esto funciona mediante automatización del navegador (Chromium) y puede involucrar Chrome DevTools Protocol (CDP) bajo el capó, pero **la dependencia del proyecto es Playwright**, no Selenium/Laravel Dusk.
- Requiere poder bindear puertos locales. En CI o entornos restringidos, se debe permitir binding local o separar esos tests.

### 9) (Opcional) Pruebas E2E via Chrome DevTools MCP (CDP)

Si se quiere estandarizar pruebas de navegador usando el servidor MCP de Chrome DevTools (CDP):

- Repositorio de referencia: `https://github.com/ChromeDevTools/chrome-devtools-mcp`
- Esto **no reemplaza automáticamente** los browser tests actuales; requiere:
  - instalar/configurar el servidor MCP en tu entorno, y
  - añadirlo a `.mcp.json` para que Codex lo pueda usar.

Ejemplo de entrada (ajustar comando/ruta según tu instalación):

```json
{
  "mcpServers": {
    "chrome-devtools": {
      "command": "node",
      "args": ["path/to/chrome-devtools-mcp/dist/index.js"]
    }
  }
}
```

**Limitación importante (entorno Codex/CI):**
- Para ejecutar este tipo de pruebas desde un agente, el entorno debe permitir:
  - lanzar un navegador/Chromium,
  - bindear puertos locales,
  - y tener instalado el servidor MCP.
