<?php

namespace App\Filament\Resources\Surveys\RelationManagers;

use App\Enums\QuestionType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Preguntas';

    protected static ?string $recordTitleAttribute = 'question_text';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Wizard\Step::make('Información Básica')
                        ->description('Defina la pregunta y sus propiedades')
                        ->icon('heroicon-m-pencil-square')
                        ->schema([
                            Textarea::make('question_text')
                                ->label('Texto de la Pregunta')
                                ->required()
                                ->rows(3)
                                ->placeholder('Ejemplo: ¿Está satisfecho con nuestro servicio?')
                                ->helperText('Escriba la pregunta exactamente como la verá el usuario')
                                ->columnSpanFull(),

                            Grid::make(2)
                                ->schema([
                                    TextInput::make('order')
                                        ->label('Orden')
                                        ->numeric()
                                        ->default(fn () => $this->getOwnerRecord()->questions()->max('order') + 1)
                                        ->required()
                                        ->minValue(1)
                                        ->helperText('Posición de esta pregunta en la encuesta'),

                                    Toggle::make('is_required')
                                        ->label('Pregunta Requerida')
                                        ->default(false)
                                        ->helperText('¿El usuario debe responder obligatoriamente?'),
                                ]),
                        ]),

                    Wizard\Step::make('Tipo de Pregunta')
                        ->description('Seleccione el formato de respuesta')
                        ->icon('heroicon-m-queue-list')
                        ->schema([
                            Select::make('question_type')
                                ->label('¿Cómo desea que el usuario responda?')
                                ->options([
                                    QuestionType::YES_NO->value => '✓ Sí / No - Respuesta binaria simple',
                                    QuestionType::SCALE->value => '📊 Escala Numérica - Calificación (ej: 1-5, 1-10)',
                                    QuestionType::SINGLE_CHOICE->value => '⚪ Selección Única - Elegir solo una opción',
                                    QuestionType::MULTIPLE_CHOICE->value => '☑️  Selección Múltiple - Elegir varias opciones',
                                    QuestionType::TEXT->value => '📝 Texto Libre - Respuesta abierta',
                                ])
                                ->required()
                                ->helperText('Esta decisión determinará cómo se presenta la pregunta al usuario'),
                        ]),

                    Wizard\Step::make('Configuración')
                        ->description('Configure las opciones específicas')
                        ->icon('heroicon-m-cog-6-tooth')
                        ->schema([
                            // Configuración para Escala
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('configuration.scale.min_value')
                                        ->label('Valor Mínimo')
                                        ->numeric()
                                        ->default('1')
                                        ->required()
                                        ->helperText('Número más bajo de la escala'),

                                    TextInput::make('configuration.scale.max_value')
                                        ->label('Valor Máximo')
                                        ->numeric()
                                        ->default('5')
                                        ->required()
                                        ->helperText('Número más alto de la escala'),
                                ])
                                ->visible(fn (Get $get) => $get('question_type') === QuestionType::SCALE->value)
                                ->columnSpanFull(),

                            // Configuración para Opciones Múltiples/Únicas
                            Repeater::make('configuration.options')
                                ->label('Opciones de Respuesta')
                                ->simple(
                                    TextInput::make('option')
                                        ->label('Opción')
                                        ->required()
                                        ->placeholder('Escriba una opción')
                                )
                                ->visible(fn (Get $get) => in_array($get('question_type'), [
                                    QuestionType::MULTIPLE_CHOICE->value,
                                    QuestionType::SINGLE_CHOICE->value,
                                ]))
                                ->minItems(2)
                                ->defaultItems(3)
                                ->addActionLabel('+ Agregar Opción')
                                ->reorderable()
                                ->helperText('Agregue todas las opciones que el usuario podrá seleccionar. Mínimo 2 opciones.')
                                ->columnSpanFull(),

                            // Configuración para Texto
                            TextInput::make('configuration.max_length')
                                ->label('Longitud Máxima (caracteres)')
                                ->numeric()
                                ->default(500)
                                ->minValue(1)
                                ->maxValue(5000)
                                ->visible(fn (Get $get) => $get('question_type') === QuestionType::TEXT->value)
                                ->helperText('Cantidad máxima de caracteres permitidos en la respuesta')
                                ->columnSpanFull(),

                            // Información para Sí/No
                            Placeholder::make('yes_no_info')
                                ->label('Configuración')
                                ->content('Las opciones "Sí" y "No" están predefinidas. No necesita configuración adicional.')
                                ->visible(fn (Get $get) => $get('question_type') === QuestionType::YES_NO->value)
                                ->columnSpanFull(),
                        ]),

                    Wizard\Step::make('Finalizar')
                        ->description('Texto de ayuda opcional')
                        ->icon('heroicon-m-check-circle')
                        ->schema([
                            Textarea::make('configuration.help_text')
                                ->label('Texto de Ayuda (Opcional)')
                                ->rows(3)
                                ->placeholder('Ejemplo: Seleccione la opción que mejor describa su experiencia...')
                                ->helperText('Este texto aparecerá debajo de la pregunta para guiar al usuario')
                                ->columnSpanFull(),
                        ]),
                ])
                    ->columnSpanFull()
                    ->skippable()
                    ->persistStepInQueryString(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                TextColumn::make('order')
                    ->label('#')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('question_text')
                    ->label('Pregunta')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('question_type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_required')
                    ->label('Requerida')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('responses_count')
                    ->label('Respuestas')
                    ->counts('responses')
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva Pregunta'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar'),
                DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ])
            ->defaultSort('order')
            ->reorderable('order');
    }
}
