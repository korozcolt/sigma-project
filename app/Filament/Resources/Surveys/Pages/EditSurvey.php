<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Filament\Resources\Surveys\SurveyResource;
use App\Filament\Widgets\SurveyResultsWidget;
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
        return $this->record->questions
            ->sortBy('order')
            ->map(fn (SurveyQuestion $question): WidgetConfiguration => new WidgetConfiguration(
                SurveyResultsWidget::class,
                ['questionId' => $question->id],
            ))
            ->values()
            ->all();
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
