<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Enums\QuestionType;
use App\Filament\Resources\Surveys\SurveyResource;
use App\Filament\Widgets\SurveyResultsWidget;
use App\Filament\Widgets\SurveyScaleGaugeChart;
use App\Filament\Widgets\SurveyScaleHistogramChart;
use App\Models\SurveyQuestion;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Widgets\WidgetConfiguration;

class EditSurvey extends EditRecord
{
    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        $questions = $this->record->questions->sortBy('order')->values();

        $resultWidgets = $questions->map(fn (SurveyQuestion $question): WidgetConfiguration => new WidgetConfiguration(
            SurveyResultsWidget::class,
            ['questionId' => $question->id],
        ));

        // D-10/D-11: one gauge + one histogram per SCALE question, alongside its
        // existing SurveyResultsWidget instance (not a replacement for it).
        $scaleWidgets = $questions
            ->filter(fn (SurveyQuestion $question): bool => $question->question_type === QuestionType::SCALE)
            ->flatMap(fn (SurveyQuestion $question): array => [
                new WidgetConfiguration(SurveyScaleGaugeChart::class, ['questionId' => $question->id]),
                new WidgetConfiguration(SurveyScaleHistogramChart::class, ['questionId' => $question->id]),
            ]);

        return $resultWidgets->merge($scaleWidgets)
            ->values()
            ->all();
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
