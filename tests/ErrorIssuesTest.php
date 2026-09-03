<?php

declare(strict_types=1);

namespace Facturino\Tests;

use Facturino\Exception\ApiException;
use Facturino\Exception\ConflictException;
use Facturino\Exception\InvalidRequestException;
use PHPUnit\Framework\TestCase;

/**
 * `error.issues` — the detailed reasons behind one refusal.
 *
 * The refusal keeps ONE main code, which is what a caller branches on. When it
 * rests on several reasons, or on a reason more precise than that code, each
 * one is published as data rather than joined into a sentence.
 */
final class ErrorIssuesTest extends TestCase
{
    /** @return list<array{code: string, param: ?string, message: string}> */
    private function sampleIssues(): array
    {
        return [
            [
                'code' => 'invalid_postal_code',
                'param' => 'customer.address.postalCode',
                'message' => 'Buyer territory could not be resolved.',
            ],
        ];
    }

    public function testGetIssuesReturnsTheDetailedReasons(): void
    {
        $exception = new InvalidRequestException(
            'Buyer territory could not be resolved.',
            422,
            'validation_error',
            'validation_error',
            'customerId',
            null,
            'req_abc',
            null,
            $this->sampleIssues(),
        );

        // The main code is unchanged: it stays the value to branch on.
        self::assertSame('validation_error', $exception->getErrorCode());
        self::assertSame('customerId', $exception->getParam());
        // The detail says WHICH field to fix.
        self::assertSame($this->sampleIssues(), $exception->getIssues());
    }

    public function testGetIssuesIsAnEmptyArrayWhenTheRefusalHasNoDetail(): void
    {
        // Reading it never needs a null check.
        $exception = new InvalidRequestException('No such invoice', 404, 'not_found_error', 'not_found');
        self::assertSame([], $exception->getIssues());
    }

    public function testEveryApiExceptionExposesGetIssues(): void
    {
        self::assertSame([], (new ApiException('boom', 500, 'req_x'))->getIssues());
        self::assertSame(
            $this->sampleIssues(),
            (new ConflictException('conflict', 409, 'conflict_error', 'conflict', null, null, 'req_x', null, $this->sampleIssues()))
                ->getIssues(),
        );
    }
}
