# Integración API Hablame (SMS)

**Versión API**: 5.0
**Proveedor**: Hablame Colombia
**Documentación oficial**: https://docs.hablame.co/reference/

---

## 1. Resumen

La API REST de Hablame permite el envío de SMS masivos y transaccionales para:
- Envío automático de mensajes de cumpleaños
- Recordatorios electorales y programados
- Notificaciones de validación y confirmación
- Comunicaciones segmentadas por campaña

---

## 2. Autenticación

### Métodos soportados

1. **Header HTTP** (recomendado):
   ```
   X-Hablame-Key: tu_clave_api
   ```

2. **Query String**:
   ```
   ?X-Hablame-Key=tu_clave_api
   ```

3. **Body JSON**:
   ```json
   {
     "X-Hablame-Key": "tu_clave_api"
   }
   ```

### Variables de entorno requeridas

```env
# .env
HABLAME_API_KEY=                    # Clave API desde portal Hablame
HABLAME_API_URL=https://www.hablame.co/api
HABLAME_FROM_NAME=SIGMA            # Nombre del remitente (requiere aprobación)
HABLAME_ENABLED=true               # Flag para habilitar/deshabilitar en desarrollo
```

### Seguridad

- ✅ Nunca exponer la clave en repositorios públicos
- ✅ Almacenar en variables de entorno
- ✅ Usar HTTPS para todas las solicitudes
- ✅ Rotar clave si hay sospecha de compromiso
- ❌ No incluir la clave en logs o respuestas

---

## 3. Rate Limits

| Endpoint | Límite |
|----------|--------|
| `/utilities/v5/auth` | 20 req/min |
| `/v5/account/info` | 60 req/min |
| `/sms/v5/send` | Por verificar con proveedor |

**Estrategia**: Implementar retry con exponential backoff y circuit breaker.

---

## 4. Endpoints principales

### 4.1 Envío de SMS

```
POST https://www.hablame.co/api/sms/v5/send
```

**Request Body** (formato actualizado v5):
```json
{
  "messages": [
    {
      "to": "3001234567",
      "text": "¡Feliz cumpleaños! Desde SIGMA te deseamos lo mejor."
    },
    {
      "to": "3009876543",
      "text": "¡Feliz cumpleaños! Desde SIGMA te deseamos lo mejor."
    }
  ]
}
```

**Nota importante**: Los números de teléfono deben ser de 10 dígitos sin el prefijo +57 o 57.

**Response (200 OK)**:
```json
{
  "payLoad": {
    "accountId": 10010002,
    "billingAccount": 99910010002,
    "campaignId": null,
    "campaignName": null,
    "certificate": true,
    "deliveryReceiptUrl": "https://www.hablame.co",
    "flash": false,
    "from": "9409110000",
    "ip": "200.189.27.71",
    "messages": [
      {
        "areaId": 0,
        "areaName": "",
        "costCenter": 123,
        "countryId": null,
        "encoding": "gsm",
        "id": "fb640ade-cc11-48d1-a45f-39578c509373",
        "partsQty": 1,
        "price": 0,
        "reference01": null,
        "reference02": null,
        "reference03": null,
        "statusId": 102,
        "text": "Hola SMS de prueba Hablame",
        "textLength": 26,
        "to": "3001234567"
      }
    ],
    "priority": true,
    "sendDate": "2025-06-20 08:59:00",
    "shortenUrls": false,
    "smsQty": 1
  },
  "responseTime": 2.29,
  "statusCode": 200,
  "statusMessage": "OK",
  "timeStamp": "2025-06-20T08:59:32-05:00"
}
```

**Campos importantes**:
- `messages[].id`: UUID único del mensaje para seguimiento
- `messages[].statusId`: Estado del mensaje (ver tabla abajo)
- `messages[].price`: Costo individual del mensaje
- `smsQty`: Cantidad total de SMS enviados
- `accountId`: ID de la cuenta Hablame
- `sendDate`: Fecha programada de envío

**Status IDs (messages[].statusId)**:
| statusId | Descripción |
|----------|-------------|
| 101 | Mensaje en cola |
| 102 | Mensaje enviado exitosamente |
| 103 | Mensaje fallido |
| 104 | Número inválido |
| 105 | Sin saldo |
| 106 | Mensaje programado/en cola (exitoso) |

### 4.2 Información de cuenta

```
GET https://www.hablame.co/api/v5/account/info
```

**Response (200 OK)**:
```json
{
  "statusCode": 200,
  "statusMessage": "OK",
  "timestamp": "2024-11-03T14:30:00Z",
  "responseTime": "45",
  "payLoad": {
    "account_id": "xyz789",
    "status": "active",
    "billing_type": "prepaid",
    "balance": 123.45,
    "created_at": "2022-01-15T10:00:00Z"
  }
}
```

---

## 5. Códigos de respuesta HTTP

### Éxito (2XX)
- `200 OK`: Solicitud procesada correctamente
- `201 Created`: Recurso creado exitosamente
- `202 Accepted`: Solicitud aceptada para procesamiento asíncrono
- `204 No Content`: Solicitud exitosa sin contenido

### Errores (4XX/5XX)
```json
{
  "statusCode": 401,
  "statusMessage": "Unauthorized - Invalid API Key",
  "timestamp": "2024-03-10T12:00:00Z",
  "responseTime": "150"
}
```

Códigos comunes:
- `400 Bad Request`: Parámetros inválidos
- `401 Unauthorized`: API Key inválida o faltante
- `403 Forbidden`: Sin permisos para el recurso
- `429 Too Many Requests`: Rate limit excedido
- `500 Internal Server Error`: Error del servidor

---

## 6. Arquitectura de integración en SIGMA

### 6.1 Estructura de archivos

```
app/
├── Services/
│   └── Messaging/
│       ├── HablameSmsService.php           # Implementación Hablame
│       ├── Contracts/
│       │   └── SmsProviderInterface.php    # Interface para múltiples proveedores
│       └── Enums/
│           └── MessageStatus.php           # Estados de mensajes
├── Jobs/
│   ├── SendBirthdayMessages.php            # Job programado cumpleaños
│   ├── SendBulkSms.php                     # Job envío masivo
│   └── CheckHablameBalance.php             # Job monitoreo saldo
├── Models/
│   ├── Message.php                         # Registro de mensajes enviados
│   ├── MessageTemplate.php                 # Plantillas reutilizables
│   └── MessageBatch.php                    # Batches de envíos masivos
└── Exceptions/
    └── SmsException.php                    # Excepciones específicas SMS

database/migrations/
├── create_messages_table.php
├── create_message_templates_table.php
└── create_message_batches_table.php

tests/Feature/
├── HablameSmsServiceTest.php
├── SendBirthdayMessagesTest.php
└── MessageTemplateTest.php

config/
└── hablame.php                             # Configuración del servicio
```

### 6.2 Modelos de datos

#### Tabla: `messages`
```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
    $table->foreignId('voter_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('message_batch_id')->nullable()->constrained()->nullOnDelete();
    $table->string('provider')->default('hablame'); // hablame, whatsapp, etc.
    $table->string('to'); // Número destino
    $table->string('from')->nullable(); // Remitente
    $table->text('message'); // Contenido del mensaje
    $table->string('status'); // pending, sent, failed, delivered
    $table->string('batch_id')->nullable(); // ID del proveedor
    $table->decimal('cost', 8, 4)->nullable();
    $table->json('response')->nullable(); // Respuesta completa del proveedor
    $table->text('error_message')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamps();

    $table->index(['campaign_id', 'status']);
    $table->index('batch_id');
    $table->index('sent_at');
});
```

#### Tabla: `message_templates`
```php
Schema::create('message_templates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('name'); // "Felicitación cumpleaños", "Recordatorio votación"
    $table->string('category'); // birthday, reminder, validation, general
    $table->text('content'); // Plantilla con placeholders: "Hola {nombre}"
    $table->json('variables')->nullable(); // ['nombre', 'fecha', 'lugar']
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### Tabla: `message_batches`
```php
Schema::create('message_batches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->string('name'); // "Cumpleaños Noviembre 2024"
    $table->string('provider')->default('hablame');
    $table->string('status'); // pending, processing, completed, failed
    $table->integer('total_recipients')->default(0);
    $table->integer('sent_count')->default(0);
    $table->integer('failed_count')->default(0);
    $table->decimal('total_cost', 10, 2)->nullable();
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

### 6.3 Service Interface

```php
// app/Services/Messaging/Contracts/SmsProviderInterface.php
interface SmsProviderInterface
{
    public function send(string $to, string $message, ?string $from = null): array;
    public function sendBulk(array $recipients, string $message, ?string $from = null): array;
    public function getBalance(): float;
    public function getAccountInfo(): array;
    public function validateCredentials(): bool;
}
```

### 6.4 Enum de estados

```php
// app/Services/Messaging/Enums/MessageStatus.php
enum MessageStatus: string
{
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
}
```

---

## 7. Flujo de integración

### 7.1 Configuración inicial

1. Añadir variables de entorno en `.env`
2. Crear archivo de configuración `config/hablame.php`:
```php
return [
    'api_key' => env('HABLAME_API_KEY'),
    'api_url' => env('HABLAME_API_URL', 'https://www.hablame.co/api'),
    'from_name' => env('HABLAME_FROM_NAME', 'SIGMA'),
    'enabled' => env('HABLAME_ENABLED', true),
    'timeout' => env('HABLAME_TIMEOUT', 30),
    'retry_times' => env('HABLAME_RETRY_TIMES', 3),
    'retry_sleep' => env('HABLAME_RETRY_SLEEP', 1000), // ms
];
```

### 7.2 Implementación del servicio

```php
// app/Services/Messaging/HablameSmsService.php
class HablameSmsService implements SmsProviderInterface
{
    public function __construct(
        private readonly HttpClient $client,
        private readonly string $apiKey,
        private readonly string $apiUrl,
        private readonly string $fromName
    ) {}

    public function send(string $to, string $message, ?string $from = null): array
    {
        $response = $this->client->post("{$this->apiUrl}/sms/v5/send", [
            'headers' => [
                'X-Hablame-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'messages' => [
                    [
                        'to' => $to, // 10 dígitos sin +57
                        'text' => $message,
                    ],
                ],
            ],
        ]);

        return $response->json();
    }

    // ... otros métodos
}
```

### 7.3 Job de envío masivo

```php
// app/Jobs/SendBulkSms.php
class SendBulkSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public MessageBatch $batch,
        public array $recipients,
        public string $message
    ) {}

    public function handle(HablameSmsService $sms): void
    {
        $this->batch->update(['status' => 'processing', 'started_at' => now()]);

        foreach (array_chunk($this->recipients, 100) as $chunk) {
            $response = $sms->sendBulk($chunk, $this->message);

            // Registrar cada mensaje individual
            foreach ($chunk as $recipient) {
                Message::create([
                    'campaign_id' => $this->batch->campaign_id,
                    'message_batch_id' => $this->batch->id,
                    'to' => $recipient,
                    'message' => $this->message,
                    'status' => MessageStatus::SENT,
                    'batch_id' => $response['payLoad']['batch_id'],
                    'cost' => $response['payLoad']['cost'] / count($chunk),
                    'sent_at' => now(),
                ]);
            }
        }

        $this->batch->update(['status' => 'completed', 'completed_at' => now()]);
    }
}
```

### 7.4 Scheduler para cumpleaños

```php
// routes/console.php o app/Console/Kernel.php (Laravel 12: bootstrap/app.php)
Schedule::job(new SendBirthdayMessages)->dailyAt('09:00');
```

---

## 8. Testing

### 8.1 Tests unitarios

```php
// tests/Feature/HablameSmsServiceTest.php
it('can send SMS successfully', function () {
    Http::fake([
        'hablame.co/*' => Http::response([
            'statusCode' => 201,
            'payLoad' => [
                'batch_id' => 'test123',
                'sent' => 1,
                'failed' => 0,
                'cost' => 0.034,
            ],
        ], 201),
    ]);

    $service = app(HablameSmsService::class);
    $response = $service->send('+573001234567', 'Test message');

    expect($response['statusCode'])->toBe(201);
    expect($response['payLoad']['sent'])->toBe(1);
});
```

### 8.2 Tests de integración

```php
it('creates message record when sending SMS', function () {
    Http::fake();

    $voter = Voter::factory()->create();

    $service = app(HablameSmsService::class);
    $service->send($voter->phone, 'Test message');

    expect(Message::count())->toBe(1);
    expect(Message::first()->to)->toBe($voter->phone);
});
```

---

## 9. Monitoreo y auditoría

### 9.1 Métricas a trackear

- Total de mensajes enviados por campaña
- Tasa de éxito/fallo
- Costo total por campaña/batch
- Balance de cuenta Hablame
- Tiempo de respuesta promedio
- Rate limit hits

### 9.2 Dashboard queries

```php
// Mensajes enviados hoy
Message::whereDate('sent_at', today())->count();

// Tasa de éxito
$sent = Message::where('status', 'sent')->count();
$failed = Message::where('status', 'failed')->count();
$successRate = $sent / ($sent + $failed) * 100;

// Costo total por campaña
Message::where('campaign_id', $campaignId)->sum('cost');

// Top 5 campañas con más mensajes
Campaign::withCount('messages')
    ->orderByDesc('messages_count')
    ->take(5)
    ->get();
```

### 9.3 Alertas recomendadas

- Balance de Hablame < 20% del presupuesto mensual
- Tasa de fallas > 5%
- Rate limit alcanzado
- Batch sin completar después de 2 horas

---

## 10. Seguridad y cumplimiento

### 10.1 GDPR / Protección de datos

- ✅ Almacenar solo números necesarios para auditoría
- ✅ Implementar soft deletes en mensajes
- ✅ Permitir opt-out de comunicaciones
- ✅ Encriptar números sensibles en logs
- ✅ Auditar acceso a datos de mensajería

### 10.2 Opt-out

```php
// Tabla: message_opt_outs
Schema::create('message_opt_outs', function (Blueprint $table) {
    $table->id();
    $table->string('phone')->unique();
    $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('reason')->nullable();
    $table->timestamp('opted_out_at');
    $table->timestamps();
});
```

### 10.3 Horarios permitidos

- Lunes a Viernes: 8:00 AM - 8:00 PM
- Sábados: 9:00 AM - 6:00 PM
- Domingos/Festivos: No enviar (excepto emergencias aprobadas)

```php
// app/Services/Messaging/MessageScheduler.php
public function isAllowedTime(): bool
{
    $now = now();
    $hour = $now->hour;

    if ($now->isSunday() || $now->isHoliday()) {
        return false;
    }

    if ($now->isSaturday()) {
        return $hour >= 9 && $hour < 18;
    }

    return $hour >= 8 && $hour < 20;
}
```

---

## 11. Costos estimados

| Tipo | Costo aprox. (COP) |
|------|-------------------|
| SMS Nacional Colombia | $35 - $50 |
| SMS Internacional | $150 - $300 |
| Remitente personalizado | Tarifa única de aprobación |

**Recomendación**: Implementar límite de presupuesto por campaña y alertas.

---

## 12. Testing y validación

### 12.1 Tests automatizados

```bash
# Tests de integración Hablame
php artisan test --filter=HablameSms

# Tests de mensajería completa
php artisan test --filter=MessageTest
php artisan test --filter=MessageTemplateTest
php artisan test --filter=SendBirthdayMessagesTest
```

### 12.2 Comando de prueba interactivo

SIGMA incluye un comando Artisan para probar la integración en tiempo real:

```bash
# Envío básico
php artisan test:hablame-sms 3001234567

# Con mensaje personalizado
php artisan test:hablame-sms 3001234567 --message="Hola, prueba desde SIGMA"

# Verificar información de cuenta
php artisan test:hablame-sms 3001234567 --check-account

# Validar API key
php artisan test:hablame-sms 3001234567 --validate-key

# Todas las opciones juntas
php artisan test:hablame-sms 3001234567 --check-account --validate-key
```

**Características del comando de prueba:**
- ✅ Valida configuración de API Key
- 📊 Muestra información de cuenta (balance, estado)
- 📱 Envía SMS real o sandbox según configuración
- 💾 Registra el mensaje en la base de datos
- 📝 Muestra batch_id, costo y estado de envío
- 🎯 Crea campaña y votante de prueba si no existen
- ⚠️ Solicita confirmación antes de enviar

### 12.3 Modo sandbox

Para pruebas sin consumir saldo real:

```env
HABLAME_SANDBOX_MODE=true
```

Esto simula respuestas exitosas sin llamar la API real. Ideal para:
- Tests automatizados
- Desarrollo local
- Integración continua (CI/CD)

---

## 13. Checklist de implementación

- [x] Configurar variables de entorno
- [x] Actualizar `config/services.php` con configuración Hablame
- [x] Implementar `HablameSmsService`
- [x] Crear modelos: Message, MessageTemplate, MessageBatch
- [x] Crear migraciones y factories
- [x] Implementar Jobs: SendMessage
- [x] Implementar Commands: SendBirthdayMessages, TestHablameSms
- [x] Crear tests unitarios y de integración (8 tests, 27 assertions)
- [x] Configurar colas con database driver
- [x] Implementar sistema de rate limiting
- [x] Crear documentación completa
- [x] Modo sandbox para testing
- [ ] Implementar sistema de opt-out
- [ ] Crear dashboard de monitoreo en Filament
- [ ] Configurar alertas de balance/fallos
- [ ] Validar en staging con números de prueba reales
- [ ] Solicitar aprobación de remitente "SIGMA" a Hablame
- [ ] Deploy a producción con feature flag

---

## 14. Referencias

---

## 13. Próximos pasos (WhatsApp)

Pendiente recibir documentación de integración de WhatsApp para:
- Mensajería con plantillas aprobadas
- Envío de multimedia (imágenes, PDFs)
- Webhooks para estados de entrega
- Chatbot para respuestas automáticas

---

## 14. Referencias

- [Hablame - Introducción](https://docs.hablame.co/reference/introducción)
- [Hablame - Autenticación](https://docs.hablame.co/reference/autenticacion)
- [Hablame - Envío SMS](https://docs.hablame.co/reference/envio-sms-post)
- [Hablame - Información de cuenta](https://docs.hablame.co/reference/informacion-general)
- [Hablame - Códigos de respuesta](https://docs.hablame.co/reference/solicitud-exitosa)

---

**Última actualización**: 2024-11-03
**Versión documento**: 1.0
**Autor**: Equipo SIGMA
