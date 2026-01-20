<?php

namespace Tests\E2E\ChromeDevTools;

use Tests\TestCase;

/**
 * Base class for Chrome DevTools MCP E2E Tests
 */
abstract class ChromeDevToolsTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure we have a clean Chrome DevTools session
        $this->cleanupChromeSession();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupChromeSession();
    }

    /**
     * Clean up Chrome DevTools session
     */
    protected function cleanupChromeSession(): void
    {
        // Close any existing pages except the first one
        $pages = $this->getChromePages();
        if (count($pages) > 1) {
            for ($i = count($pages) - 1; $i > 0; $i--) {
                $this->closeChromePage($pages[$i]['id']);
            }
        }
    }

    /**
     * Get list of Chrome DevTools pages
     */
    protected function getChromePages(): array
    {
        // This will be implemented by the Chrome DevTools MCP service
        return [];
    }

    /**
     * Close a specific Chrome DevTools page
     */
    protected function closeChromePage(int $pageId): void
    {
        // This will be implemented by the Chrome DevTools MCP service
    }

    /**
     * Navigate to a URL and take snapshot
     */
    protected function navigateAndSnapshot(string $url): array
    {
        // This will be implemented by the Chrome DevTools MCP service
        return [];
    }

    /**
     * Fill form and submit
     */
    protected function fillAndSubmitForm(array $fields, string $submitButtonSelector): void
    {
        // This will be implemented by the Chrome DevTools MCP service
    }

    /**
     * Assert text is visible on page
     */
    protected function assertSeeText(string $text): void
    {
        // This will be implemented by the Chrome DevTools MCP service
    }

    /**
     * Assert element is visible
     */
    protected function assertSeeElement(string $selector): void
    {
        // This will be implemented by the Chrome DevTools MCP service
    }

    /**
     * Click an element
     */
    protected function clickElement(string $selector): void
    {
        // This will be implemented by the Chrome DevTools MCP service
    }

    /**
     * Type text in an input field
     */
    protected function typeInField(string $selector, string $value): void
    {
        // This will be implemented by the Chrome DevTools MCP service
    }

    /**
     * Wait for element to appear
     */
    protected function waitForElement(string $selector, int $timeout = 10000): void
    {
        // This will be implemented by the Chrome DevTools MCP service
    }

    /**
     * Upload file to input field
     */
    protected function uploadFile(string $selector, string $filePath): void
    {
        // This will be implemented by the Chrome DevTools MCP service
    }
}