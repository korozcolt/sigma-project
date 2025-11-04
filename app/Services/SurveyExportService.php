<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SurveyExportService
{
    /**
     * Export survey responses to CSV.
     */
    public function exportToCSV(Survey $survey): string
    {
        $fileName = 'survey-'.$survey->id.'-'.Str::slug($survey->title).'-'.now()->format('Y-m-d-His').'.csv';
        $filePath = 'exports/'.$fileName;

        // Ensure directory exists
        Storage::makeDirectory('exports');

        // Open file handle
        $handle = fopen(Storage::path($filePath), 'w');

        // Write BOM for UTF-8
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        // Get all questions for headers
        $questions = $survey->questions()->orderBy('order')->get();

        // Build headers
        $headers = [
            'ID Respuesta',
            'ID Votante',
            'Nombre Votante',
            'Documento',
            'Respondido Por',
            'Fecha Respuesta',
        ];

        foreach ($questions as $question) {
            $headers[] = "P{$question->order}: {$question->question_text}";
        }

        fputcsv($handle, $headers);

        // Get all unique voters who responded
        $voterIds = SurveyResponse::where('survey_id', $survey->id)
            ->distinct()
            ->pluck('voter_id')
            ->filter();

        foreach ($voterIds as $voterId) {
            $voter = \App\Models\Voter::find($voterId);

            // Get all responses for this voter (with user relationship)
            $responses = SurveyResponse::with('answerer')
                ->where('survey_id', $survey->id)
                ->where('voter_id', $voterId)
                ->get()
                ->keyBy('survey_question_id');

            $firstResponse = $responses->first();

            $row = [
                $firstResponse?->id ?? '',
                $voterId,
                $voter ? $voter->full_name : 'N/A',
                $voter ? $voter->document_number : 'N/A',
                $firstResponse && $firstResponse->answerer
                    ? $firstResponse->answerer->name
                    : 'N/A',
                $firstResponse?->responded_at?->format('Y-m-d H:i:s') ?? '',
            ];

            // Add responses for each question
            foreach ($questions as $question) {
                $response = $responses->get($question->id);
                $row[] = $response ? $this->formatResponseValue($response->response_value) : '';
            }

            fputcsv($handle, $row);
        }

        fclose($handle);

        return $filePath;
    }

    /**
     * Export survey summary with metrics to CSV.
     */
    public function exportSummaryToCSV(Survey $survey): string
    {
        $fileName = 'survey-summary-'.$survey->id.'-'.Str::slug($survey->title).'-'.now()->format('Y-m-d-His').'.csv';
        $filePath = 'exports/'.$fileName;

        Storage::makeDirectory('exports');

        $handle = fopen(Storage::path($filePath), 'w');

        // Write BOM for UTF-8
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        // Survey information
        fputcsv($handle, ['RESUMEN DE ENCUESTA']);
        fputcsv($handle, ['Título', $survey->title]);
        fputcsv($handle, ['Descripción', $survey->description ?? '']);
        fputcsv($handle, ['Fecha de exportación', now()->format('Y-m-d H:i:s')]);
        fputcsv($handle, []);

        // Overall metrics
        $overallMetrics = $survey->metrics()->where('metric_type', 'overall')->first();

        if ($overallMetrics) {
            fputcsv($handle, ['MÉTRICAS GENERALES']);
            fputcsv($handle, ['Total de respuestas', $overallMetrics->total_responses]);
            fputcsv($handle, ['Tasa de respuesta', $overallMetrics->response_rate.'%']);
            fputcsv($handle, []);
        }

        // Question by question breakdown
        fputcsv($handle, ['DESGLOSE POR PREGUNTA']);
        fputcsv($handle, []);

        foreach ($survey->questions()->orderBy('order')->get() as $question) {
            fputcsv($handle, ['Pregunta', $question->question_text]);
            fputcsv($handle, ['Tipo', $question->question_type->getLabel()]);
            fputcsv($handle, ['Obligatoria', $question->is_required ? 'Sí' : 'No']);

            $metrics = $survey->metrics()
                ->where('survey_question_id', $question->id)
                ->first();

            if ($metrics) {
                fputcsv($handle, ['Total de respuestas', $metrics->total_responses]);

                if ($metrics->average_value) {
                    fputcsv($handle, ['Promedio', $metrics->average_value]);
                }

                if ($metrics->distribution) {
                    fputcsv($handle, ['Distribución']);
                    fputcsv($handle, ['Opción', 'Cantidad', 'Porcentaje']);

                    foreach ($metrics->distribution as $option => $stats) {
                        fputcsv($handle, [
                            $option,
                            $stats['count'] ?? 0,
                            ($stats['percentage'] ?? 0).'%',
                        ]);
                    }
                }
            }

            fputcsv($handle, []);
        }

        fclose($handle);

        return $filePath;
    }

    /**
     * Format response value for CSV export.
     */
    protected function formatResponseValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        // Check if it's JSON
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return implode(', ', $decoded);
        }

        return (string) $value;
    }

    /**
     * Clean up old export files (older than 24 hours).
     */
    public function cleanupOldExports(): int
    {
        $files = Storage::files('exports');
        $deleted = 0;

        foreach ($files as $file) {
            if (Storage::lastModified($file) < now()->subDay()->timestamp) {
                Storage::delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get download URL for an export file.
     */
    public function getDownloadUrl(string $filePath): string
    {
        return Storage::url($filePath);
    }
}
