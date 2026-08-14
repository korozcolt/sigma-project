<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown by PollingPlaceResolver::startLiveLookup() when a genuine live/2captcha
 * attempt is already claimed for the same cédula (RegistraduriaLiveSession).
 * Deliberately a plain \RuntimeException subclass so every existing generic
 * `catch (\Exception $e)` call site (HasRegistraduriaPolling) keeps working
 * unchanged and still surfaces $e->getMessage() to the operator; callers that want
 * a distinct, friendlier notification can catch this class specifically.
 * See .planning/debug/resolved/2captcha-duplicate-spend.md.
 */
class RegistraduriaLookupInProgressException extends \RuntimeException {}
