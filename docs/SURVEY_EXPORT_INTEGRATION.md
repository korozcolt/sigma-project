# Integración de Exportación de Encuestas

## Servicio Implementado

El servicio `SurveyExportService` proporciona dos tipos de exportación:

### 1. Exportación de Respuestas Individuales

```php
$service = app(SurveyExportService::class);
$filePath = $service->exportToCSV($survey);
```

Genera un CSV con:
- ID Respuesta
- ID Votante
- Nombre Votante
- Documento
- Respondido Por
- Fecha Respuesta
- Una columna por cada pregunta con las respuestas

### 2. Exportación de Resumen con Métricas

```php
$service = app(SurveyExportService::class);
$filePath = $service->exportSummaryToCSV($survey);
```

Genera un CSV con:
- Información general de la encuesta
- Métricas generales (tasa de respuesta, total de respuestas)
- Desglose pregunta por pregunta con distribuciones

## Integración en Filament Resource

Cuando se cree el `SurveyResource`, agregar estas actions:

### En ListSurveys (página de listado)

```php
use App\Services\SurveyExportService;
use Filament\Tables\Actions\Action;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // ... columnas
        ])
        ->actions([
            Action::make('exportResponses')
                ->label('Exportar Respuestas')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (Survey $record) {
                    $service = app(SurveyExportService::class);
                    $filePath = $service->exportToCSV($record);

                    return response()->download(
                        Storage::path($filePath),
                        basename($filePath)
                    )->deleteFileAfterSend();
                })
                ->requiresConfirmation()
                ->modalHeading('Exportar Respuestas')
                ->modalDescription('Se generará un archivo CSV con todas las respuestas de esta encuesta.')
                ->modalSubmitActionLabel('Exportar'),

            Action::make('exportSummary')
                ->label('Exportar Resumen')
                ->icon('heroicon-o-document-chart-bar')
                ->color('success')
                ->action(function (Survey $record) {
                    $service = app(SurveyExportService::class);
                    $filePath = $service->exportSummaryToCSV($record);

                    return response()->download(
                        Storage::path($filePath),
                        basename($filePath)
                    )->deleteFileAfterSend();
                })
                ->requiresConfirmation()
                ->modalHeading('Exportar Resumen')
                ->modalDescription('Se generará un archivo CSV con las métricas y distribuciones de esta encuesta.')
                ->modalSubmitActionLabel('Exportar'),
        ]);
}
```

### En ViewSurvey o EditSurvey (página individual)

```php
use Filament\Actions\Action;

protected function getHeaderActions(): array
{
    return [
        Action::make('exportResponses')
            ->label('Exportar Respuestas')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function () {
                $service = app(SurveyExportService::class);
                $filePath = $service->exportToCSV($this->record);

                return response()->download(
                    Storage::path($filePath),
                    basename($filePath)
                )->deleteFileAfterSend();
            }),

        Action::make('exportSummary')
            ->label('Exportar Resumen')
            ->icon('heroicon-o-document-chart-bar')
            ->color('success')
            ->action(function () {
                $service = app(SurveyExportService::class);
                $filePath = $service->exportSummaryToCSV($this->record);

                return response()->download(
                    Storage::path($filePath),
                    basename($filePath)
                )->deleteFileAfterSend();
            }),
    ];
}
```

## Limpieza Automática de Archivos

Configurar en el scheduler (routes/console.php o bootstrap/app.php):

```php
use App\Services\SurveyExportService;

Schedule::call(function () {
    app(SurveyExportService::class)->cleanupOldExports();
})->daily();
```

## Storage Configuration

Asegurarse de que el disco 'local' esté configurado en `config/filesystems.php`:

```php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
        'throw' => false,
    ],
],
```

## Permisos

Los archivos de exportación se guardan en `storage/app/exports/` y son eliminados automáticamente después de 24 horas.

Si se necesita acceso público temporal:
1. Usar el disco 'public' en lugar de 'local'
2. Generar URLs firmadas temporales

```php
use Illuminate\Support\Facades\URL;

$url = URL::temporarySignedRoute(
    'survey.download',
    now()->addMinutes(30),
    ['survey' => $survey->id, 'file' => basename($filePath)]
);
```

## Testing

```php
use App\Services\SurveyExportService;

it('exports survey responses to CSV', function () {
    $survey = Survey::factory()
        ->has(SurveyQuestion::factory()->count(3))
        ->create();

    $voter = Voter::factory()->create();

    // Create some responses
    foreach ($survey->questions as $question) {
        SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'survey_question_id' => $question->id,
            'voter_id' => $voter->id,
        ]);
    }

    $service = new SurveyExportService;
    $filePath = $service->exportToCSV($survey);

    expect(Storage::exists($filePath))->toBeTrue();
    expect(file_get_contents(Storage::path($filePath)))->toContain($voter->full_name);

    Storage::delete($filePath);
});
```
