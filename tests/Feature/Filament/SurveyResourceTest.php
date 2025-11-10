<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Filament\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Resources\Surveys\Pages\EditSurvey;
use App\Filament\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Resources\Surveys\RelationManagers\QuestionsRelationManager;
use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    // Crear roles si no existen
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    // Usuario administrador para los tests
    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->admin);
});

// ============ Tests de Listado ============

test('can render surveys list page', function () {
    Livewire::test(ListSurveys::class)
        ->assertSuccessful();
});

test('can list surveys', function () {
    $surveys = Survey::factory()->count(3)->create();

    Livewire::test(ListSurveys::class)
        ->assertCanSeeTableRecords($surveys);
});

test('can search surveys by title', function () {
    $survey1 = Survey::factory()->create(['title' => 'Encuesta de Satisfacción']);
    $survey2 = Survey::factory()->create(['title' => 'Encuesta de Opinión']);

    Livewire::test(ListSurveys::class)
        ->searchTable('Satisfacción')
        ->assertCanSeeTableRecords([$survey1])
        ->assertCanNotSeeTableRecords([$survey2]);
});

test('can filter surveys by campaign', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    $survey1 = Survey::factory()->create(['campaign_id' => $campaign1->id]);
    $survey2 = Survey::factory()->create(['campaign_id' => $campaign2->id]);

    Livewire::test(ListSurveys::class)
        ->filterTable('campaign_id', $campaign1->id)
        ->assertCanSeeTableRecords([$survey1])
        ->assertCanNotSeeTableRecords([$survey2]);
});

test('can filter surveys by active status', function () {
    $surveyActive = Survey::factory()->create(['is_active' => true]);
    $surveyInactive = Survey::factory()->inactive()->create();

    Livewire::test(ListSurveys::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$surveyActive])
        ->assertCanNotSeeTableRecords([$surveyInactive]);
});

test('surveys table shows question count', function () {
    $survey = Survey::factory()->create();
    SurveyQuestion::factory()->count(5)->create(['survey_id' => $survey->id]);

    Livewire::test(ListSurveys::class)
        ->assertCanSeeTableRecords([$survey]);
});

// ============ Tests de Creación ============

test('can render create survey page', function () {
    Livewire::test(CreateSurvey::class)
        ->assertSuccessful();
});

test('can create survey with basic data', function () {
    $campaign = Campaign::factory()->create();

    $surveyData = [
        'campaign_id' => $campaign->id,
        'title' => 'Nueva Encuesta de Prueba',
        'description' => 'Descripción de la encuesta',
        'is_active' => true,
    ];

    Livewire::test(CreateSurvey::class)
        ->fillForm($surveyData)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('surveys', [
        'campaign_id' => $campaign->id,
        'title' => 'Nueva Encuesta de Prueba',
        'description' => 'Descripción de la encuesta',
        'is_active' => true,
    ]);
});

test('cannot create survey without required fields', function () {
    Livewire::test(CreateSurvey::class)
        ->fillForm([
            'campaign_id' => null,
            'title' => '',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'campaign_id' => 'required',
            'title' => 'required',
        ]);
});

test('can create survey with parent survey', function () {
    $campaign = Campaign::factory()->create();
    $parentSurvey = Survey::factory()->create(['campaign_id' => $campaign->id]);

    $surveyData = [
        'campaign_id' => $campaign->id,
        'title' => 'Nueva Versión de Encuesta',
        'parent_survey_id' => $parentSurvey->id,
        'is_active' => true,
    ];

    Livewire::test(CreateSurvey::class)
        ->fillForm($surveyData)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('surveys', [
        'title' => 'Nueva Versión de Encuesta',
        'parent_survey_id' => $parentSurvey->id,
    ]);
});

test('survey defaults to active', function () {
    $campaign = Campaign::factory()->create();

    $surveyData = [
        'campaign_id' => $campaign->id,
        'title' => 'Encuesta con Defaults',
    ];

    Livewire::test(CreateSurvey::class)
        ->fillForm($surveyData)
        ->call('create')
        ->assertHasNoFormErrors();

    $survey = Survey::where('title', 'Encuesta con Defaults')->first();
    expect($survey->is_active)->toBeTrue();
});

// ============ Tests de Edición ============

test('can render edit survey page', function () {
    $survey = Survey::factory()->create();

    Livewire::test(EditSurvey::class, ['record' => $survey->id])
        ->assertSuccessful();
});

test('can edit survey', function () {
    $survey = Survey::factory()->create([
        'title' => 'Título Original',
        'description' => 'Descripción Original',
    ]);

    Livewire::test(EditSurvey::class, ['record' => $survey->id])
        ->fillForm([
            'title' => 'Título Actualizado',
            'description' => 'Descripción Actualizada',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $survey->refresh();

    expect($survey->title)->toBe('Título Actualizado');
    expect($survey->description)->toBe('Descripción Actualizada');
});

test('can toggle survey active status', function () {
    $survey = Survey::factory()->create(['is_active' => true]);

    Livewire::test(EditSurvey::class, ['record' => $survey->id])
        ->fillForm([
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $survey->refresh();

    expect($survey->is_active)->toBeFalse();
});

test('version field is disabled on edit', function () {
    $survey = Survey::factory()->create(['version' => 1]);

    Livewire::test(EditSurvey::class, ['record' => $survey->id])
        ->assertFormFieldIsDisabled('version');
});

// ============ Tests de Eliminación ============

test('can delete survey from edit page', function () {
    $survey = Survey::factory()->create();

    Livewire::test(EditSurvey::class, ['record' => $survey->id])
        ->callAction('delete');

    $this->assertSoftDeleted('surveys', ['id' => $survey->id]);
});

test('can bulk delete surveys', function () {
    $surveys = Survey::factory()->count(3)->create();

    Livewire::test(ListSurveys::class)
        ->callTableBulkAction('delete', $surveys);

    foreach ($surveys as $survey) {
        $this->assertSoftDeleted('surveys', ['id' => $survey->id]);
    }
});

// ============ Tests de Questions RelationManager ============

test('can add yes/no question to survey', function () {
    $survey = Survey::factory()->create();

    $questionData = [
        'question_text' => '¿Está satisfecho con el servicio?',
        'question_type' => QuestionType::YES_NO->value,
        'is_required' => true,
        'order' => 1,
    ];

    Livewire::test(QuestionsRelationManager::class, [
        'ownerRecord' => $survey,
        'pageClass' => EditSurvey::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), $questionData)
        ->assertNotified();

    assertDatabaseHas(SurveyQuestion::class, [
        'survey_id' => $survey->id,
        'question_text' => '¿Está satisfecho con el servicio?',
        'question_type' => QuestionType::YES_NO->value,
    ]);
});

test('can add scale question', function () {
    $survey = Survey::factory()->create();

    $questionData = [
        'question_text' => '¿Cómo califica el servicio?',
        'question_type' => QuestionType::SCALE->value,
        'is_required' => true,
        'order' => 1,
    ];

    Livewire::test(QuestionsRelationManager::class, [
        'ownerRecord' => $survey,
        'pageClass' => EditSurvey::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), $questionData)
        ->assertNotified();

    assertDatabaseHas(SurveyQuestion::class, [
        'survey_id' => $survey->id,
        'question_text' => '¿Cómo califica el servicio?',
        'question_type' => QuestionType::SCALE->value,
        'is_required' => true,
    ]);
});

test('can add multiple choice question', function () {
    $survey = Survey::factory()->create();

    $questionData = [
        'question_text' => '¿Qué servicios le interesan?',
        'question_type' => QuestionType::MULTIPLE_CHOICE->value,
        'is_required' => true,
        'order' => 1,
    ];

    Livewire::test(QuestionsRelationManager::class, [
        'ownerRecord' => $survey,
        'pageClass' => EditSurvey::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), $questionData)
        ->assertNotified();

    assertDatabaseHas(SurveyQuestion::class, [
        'survey_id' => $survey->id,
        'question_text' => '¿Qué servicios le interesan?',
        'question_type' => QuestionType::MULTIPLE_CHOICE->value,
        'is_required' => true,
    ]);
});

test('can edit survey question', function () {
    $survey = Survey::factory()->create();
    $question = SurveyQuestion::factory()->create([
        'survey_id' => $survey->id,
        'question_text' => 'Texto Original',
    ]);

    Livewire::test(QuestionsRelationManager::class, [
        'ownerRecord' => $survey,
        'pageClass' => EditSurvey::class,
    ])
        ->callAction(TestAction::make(EditAction::class)->table($question), [
            'question_text' => 'Texto Actualizado',
        ])
        ->assertNotified();

    $question->refresh();
    expect($question->question_text)->toBe('Texto Actualizado');
});

test('can delete survey question', function () {
    $survey = Survey::factory()->create();
    $question = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    Livewire::test(QuestionsRelationManager::class, [
        'ownerRecord' => $survey,
        'pageClass' => EditSurvey::class,
    ])
        ->callAction(TestAction::make(DeleteAction::class)->table($question))
        ->assertNotified();

    $this->assertSoftDeleted('survey_questions', ['id' => $question->id]);
});

test('can reorder survey questions', function () {
    $survey = Survey::factory()->create();
    $question1 = SurveyQuestion::factory()->create(['survey_id' => $survey->id, 'order' => 1]);
    $question2 = SurveyQuestion::factory()->create(['survey_id' => $survey->id, 'order' => 2]);

    Livewire::test(EditSurvey::class, ['record' => $survey->id])
        ->assertSuccessful();

    // Verificar que las preguntas existen en el orden correcto
    $questions = $survey->questions()->orderBy('order')->get();
    expect($questions->first()->id)->toBe($question1->id);
    expect($questions->last()->id)->toBe($question2->id);
});
