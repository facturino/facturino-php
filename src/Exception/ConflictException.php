<?php

declare(strict_types=1);

namespace Facturino\Exception;

/**
 * Thrown on `409 Conflict`.
 *
 * The two cases the API answers with it:
 *   - an `Idempotency-Key` reused with a DIFFERENT request body — the key
 *     identifies one request, and reusing it for another is a misuse the caller
 *     must see rather than a silent second execution;
 *   - a request whose resource is being written concurrently.
 *
 * A conflict is never retried: retrying the same call changes nothing. It
 * extends {@see InvalidRequestException}, so existing `catch` blocks on that
 * type keep working.
 */
class ConflictException extends InvalidRequestException
{
}
