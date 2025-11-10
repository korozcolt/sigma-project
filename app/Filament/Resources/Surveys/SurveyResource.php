<?php

namespace App\Filament\Resources\Surveys;

use App\Filament\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Resources\Surveys\Pages\EditSurvey;
use App\Filament\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Resources\Surveys\Schemas\SurveyForm;
use App\Filament\Resources\Surveys\Tables\SurveysTable;
use App\Models\Survey;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Encuestas';

    protected static ?string $modelLabel = 'Encuesta';

    protected static ?string $pluralModelLabel = 'Encuestas';

    protected static string|UnitEnum|null $navigationGroup = 'Call Center';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return SurveyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SurveysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurveys::route('/'),
            'create' => CreateSurvey::route('/create'),
            'edit' => EditSurvey::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
