# Comando de Prueba SMS - Hablame

## Descripción

El comando `test:hablame-sms` permite enviar mensajes SMS de prueba y verificar la integración con la API de Hablame.

## Uso Básico

```bash
php artisan test:hablame-sms {teléfono}
```

### Ejemplo simple

```bash
php artisan test:hablame-sms 3001234567
```

## Opciones disponibles

### 1. Mensaje personalizado

```bash
php artisan test:hablame-sms 3001234567 --message="Hola desde SIGMA"
```

### 2. Verificar información de cuenta

Muestra balance, estado de la cuenta, tipo de facturación, etc.

```bash
php artisan test:hablame-sms 3001234567 --check-account
```

### 3. Validar API Key

Verifica que la API Key configurada sea válida.

```bash
php artisan test:hablame-sms 3001234567 --validate-key
```

### 4. Combinación de opciones

```bash
php artisan test:hablame-sms 3001234567 \
  --check-account \
  --validate-key \
  --message="Mensaje de prueba completo"
```

## Formatos de número aceptados

El comando acepta números en diferentes formatos:

- `3001234567` (10 dígitos)
- `573001234567` (con código país sin +)
- `+573001234567` (formato internacional completo)

El sistema automáticamente formatea el número al estándar internacional (+57...).

## Flujo del comando

1. **Validación de configuración**
   - Verifica que `HABLAME_API_KEY` esté configurada
   - Muestra el estado de la configuración

2. **Validación de API Key** (si se usa `--validate-key`)
   - Consulta la API para verificar que la key sea válida
   - Muestra resultado de validación

3. **Información de cuenta** (si se usa `--check-account`)
   - Obtiene y muestra:
     - Account ID
     - Estado de la cuenta
     - Balance disponible
     - Tipo de facturación

4. **Preparación del mensaje**
   - Busca o crea una campaña activa
   - Busca o crea un votante con el teléfono indicado
   - Muestra el contenido del mensaje a enviar

5. **Confirmación**
   - Solicita confirmación antes de enviar (se puede saltar con `--no-interaction`)
   - Indica si está en modo sandbox o producción

6. **Envío**
   - Envía el SMS a través de HablameSmsService
   - Muestra resultado detallado:
     - Batch ID
     - Mensajes enviados/fallidos
     - Costo
     - Código y mensaje de estado
     - Tiempo de respuesta

7. **Registro**
   - Guarda el mensaje en la base de datos
   - Marca como enviado o fallido según resultado

## Salida del comando

### Éxito

```
🚀 Prueba de Integración Hablame SMS API v5

✅ API Key configurada

📱 Preparando envío de SMS a: +573001234567
✅ Campaña creada: Campaña de Prueba SMS
✅ Votante creado: Usuario Prueba

📝 Contenido del mensaje:
🧪 Mensaje de prueba desde SIGMA.

Esto es una prueba de integración con Hablame SMS API v5.

Fecha: 03/11/2025 19:30:45
¡La integración funciona correctamente! ✅

SIGMA - Sistema de Gestión Electoral

¿Deseas enviar este SMS? (yes/no) [yes]:
> yes

⚠️  Modo SANDBOX activado - No se consumirá saldo real
📤 Enviando SMS...

✅ ¡SMS enviado exitosamente!

+------------------+-------------------------------------------------+
| Campo            | Valor                                           |
+------------------+-------------------------------------------------+
| Batch ID         | sandbox_673b4f5e8a2c1                          |
| Mensajes enviados| 1                                              |
| Mensajes fallidos| 0                                              |
| Costo            | $0.034                                         |
| Código estado    | 201                                            |
| Mensaje estado   | Message sent successfully (Sandbox Mode)       |
| Tiempo respuesta | 50ms                                           |
+------------------+-------------------------------------------------+

💾 Mensaje guardado en la base de datos con ID: 42
```

### Error de configuración

```
🚀 Prueba de Integración Hablame SMS API v5

❌ HABLAME_API_KEY no está configurada en .env
💡 Agrega HABLAME_API_KEY=tu_api_key en el archivo .env
```

### Error de API

```
✅ API Key configurada

🔑 Validando API Key...
❌ API Key inválida
```

## Modo Sandbox vs Producción

### Modo Sandbox (recomendado para pruebas)

```env
HABLAME_SANDBOX_MODE=true
```

- ✅ No consume saldo real
- ✅ Simula respuestas exitosas
- ✅ Útil para desarrollo y testing
- ⚠️ No envía SMS reales

### Modo Producción

```env
HABLAME_SANDBOX_MODE=false
```

- ✅ Envía SMS reales
- ⚠️ Consume saldo de la cuenta
- ⚠️ Requiere API Key válida y activa

## Casos de uso

### 1. Primera vez - Verificar todo

```bash
php artisan test:hablame-sms 3001234567 \
  --validate-key \
  --check-account
```

### 2. Prueba rápida diaria

```bash
php artisan test:hablame-sms 3001234567
```

### 3. Envío sin confirmación (scripts automatizados)

```bash
php artisan test:hablame-sms 3001234567 \
  --no-interaction
```

### 4. Mensaje personalizado para cliente

```bash
php artisan test:hablame-sms 3001234567 \
  --message="Estimado cliente, su servicio está activo."
```

## Troubleshooting

### Error: "API Key no configurada"

**Solución**: Agregar en `.env`:

```env
HABLAME_API_KEY=tu_clave_aqui
```

### Error: "API Key inválida"

**Causas posibles**:
- API Key incorrecta o expirada
- Cuenta suspendida
- Problemas de conectividad

**Solución**: Verificar en el portal de Hablame y regenerar si es necesario.

### Error: "Balance insuficiente"

**Solución**: Recargar saldo en la cuenta de Hablame.

### No se recibe el SMS

**Verificar**:
1. Número de teléfono correcto y en formato válido
2. Modo sandbox desactivado (`HABLAME_SANDBOX_MODE=false`)
3. Balance suficiente en cuenta
4. Logs en `storage/logs/laravel.log`

## Datos creados

El comando crea automáticamente:

- **Campaign**: Si no existe ninguna campaña
  - Nombre: "Campaña de Prueba SMS"
  - Candidato: "Sistema SIGMA"
  - Estado: ACTIVE

- **Voter**: Si no existe un votante con ese teléfono
  - Teléfono: El proporcionado
  - Nombre: "Usuario Prueba"
  - Documento: Aleatorio (9999999XX)

- **Message**: Siempre se crea
  - Tipo: custom
  - Canal: sms
  - Estado: pending → sent/failed
  - Incluye batch_id si es exitoso

## Logs

Todos los envíos quedan registrados en:

- **Base de datos**: Tabla `messages`
- **Laravel logs**: `storage/logs/laravel.log`

## Seguridad

- ✅ Requiere confirmación antes de enviar (excepto con `--no-interaction`)
- ✅ Valida formato de número antes de enviar
- ✅ Registra todos los intentos en logs
- ✅ No expone API Key en salida del comando

## Integración con CI/CD

Para pruebas automatizadas en pipelines:

```bash
# Modo sandbox + no interactivo
HABLAME_SANDBOX_MODE=true php artisan test:hablame-sms 3001234567 --no-interaction
```

---

**Última actualización**: 3 de noviembre de 2025
**Comando**: `test:hablame-sms`
**Ubicación**: `app/Console/Commands/TestHablameSms.php`
