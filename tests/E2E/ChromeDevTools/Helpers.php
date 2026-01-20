<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

/**
 * Helper Functions for Chrome DevTools MCP Tests
 * 
 * These functions provide the interface between test files
 * and the ChromeDevToolsService for actual MCP calls.
 */

/**
 * Navigate to a URL using Chrome DevTools
 */
function navigateToUrl(string $url): array
{
    return \Tests\E2E\ChromeDevTools\ChromeDevToolsService::navigate($url);
}

/**
 * Assert text is visible on page
 */
function assertSeeTextInSnapshot(array $snapshot, string $text): void
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::assertSeeText($text);
}

/**
 * Click an element in snapshot
 */
function clickElementInSnapshot(array &$snapshot, string $selector): void
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::click($selector);
    
    // Update snapshot after action
    $snapshot = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
    $snapshot['elements'] = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::parseElements($snapshot);
}

/**
 * Type in field in snapshot
 */
function typeInFieldInSnapshot(array &$snapshot, string $selector, string $value): void
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::type($selector, $value);
    
    // Update snapshot after action
    $snapshot = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
    $snapshot['elements'] = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::parseElements($snapshot);
}

/**
 * Upload file in snapshot
 */
function uploadFileInSnapshot(array &$snapshot, string $selector, string $filePath): void
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::uploadFile($selector, $filePath);
    
    // Update snapshot after action
    $snapshot = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
    $snapshot['elements'] = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::parseElements($snapshot);
}

/**
 * Wait for text and return new snapshot
 */
function waitForTextAndSnapshot(string $text, int $timeout = 10000): array
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::waitForText($text, $timeout);
    return \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
}

/**
 * Wait for element and return new snapshot
 */
function waitForElementAndSnapshot(string $selector, int $timeout = 10000): array
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::waitForElement($selector, $timeout);
    return \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
}

/**
 * Fill user form with multiple fields
 */
function fillUserFormInSnapshot(array &$snapshot, array $data): void
{
    foreach ($data as $field => $value) {
        $selector = match($field) {
            'name' => 'input[name="name"]',
            'email' => 'input[name="email"]',
            'document_number' => 'input[name="document_number"]',
            'password' => 'input[name="password"]',
            'password_confirmation' => 'input[name="password_confirmation"]',
            'municipality_id' => 'select[name="municipality_id"]',
            'role' => 'select[name="roles[]"]',
            default => 'input[name="' . $field . '"]',
        };
        
        typeInFieldInSnapshot($snapshot, $selector, (string) $value);
    }
}