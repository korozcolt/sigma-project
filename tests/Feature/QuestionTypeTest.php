<?php

declare(strict_types=1);

use App\Enums\QuestionType;

it('enum has all 5 question types', function () {
    $cases = QuestionType::cases();

    expect($cases)->toHaveCount(5);
    expect($cases)->toContain(QuestionType::YES_NO);
    expect($cases)->toContain(QuestionType::SCALE);
    expect($cases)->toContain(QuestionType::TEXT);
    expect($cases)->toContain(QuestionType::MULTIPLE_CHOICE);
    expect($cases)->toContain(QuestionType::SINGLE_CHOICE);
});

it('enum has correct labels in Spanish', function () {
    expect(QuestionType::YES_NO->getLabel())->toBe('Sí/No');
    expect(QuestionType::SCALE->getLabel())->toBe('Escala Numérica');
    expect(QuestionType::TEXT->getLabel())->toBe('Texto Libre');
    expect(QuestionType::MULTIPLE_CHOICE->getLabel())->toBe('Opción Múltiple');
    expect(QuestionType::SINGLE_CHOICE->getLabel())->toBe('Selección Única');
});

it('enum has correct colors', function () {
    expect(QuestionType::YES_NO->getColor())->toBe('success');
    expect(QuestionType::SCALE->getColor())->toBe('info');
    expect(QuestionType::TEXT->getColor())->toBe('gray');
    expect(QuestionType::MULTIPLE_CHOICE->getColor())->toBe('warning');
    expect(QuestionType::SINGLE_CHOICE->getColor())->toBe('primary');
});

it('enum has correct icons', function () {
    expect(QuestionType::YES_NO->getIcon())->toBe('heroicon-m-check-circle');
    expect(QuestionType::SCALE->getIcon())->toBe('heroicon-m-chart-bar');
    expect(QuestionType::TEXT->getIcon())->toBe('heroicon-m-document-text');
    expect(QuestionType::MULTIPLE_CHOICE->getIcon())->toBe('heroicon-m-queue-list');
    expect(QuestionType::SINGLE_CHOICE->getIcon())->toBe('heroicon-m-circle-stack');
});

it('enum has descriptions for each type', function () {
    expect(QuestionType::YES_NO->getDescription())->toContain('Sí o No');
    expect(QuestionType::SCALE->getDescription())->toContain('escala numérica');
    expect(QuestionType::TEXT->getDescription())->toContain('texto libre');
    expect(QuestionType::MULTIPLE_CHOICE->getDescription())->toContain('múltiples opciones');
    expect(QuestionType::SINGLE_CHOICE->getDescription())->toContain('única opción');
});
