<?php

namespace Omnidocs\Jsdoc\Tests;

use Omnidocs\Jsdoc\JsdocSourceFactory;
use Omnidocs\SourceInterface;
use Omnidocs\Testing\SourceContractTests;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The conformance suite on the tool's whole output, as the harness writes it (docker compose run --rm jsdoc,
 * then docker compose run --rm omnidocs real): OMNIDOCS_JSDOC_REAL names it; skipped without it.
 */
#[Group('real')]
final class RealOutputTest extends TestCase
{
    use SourceContractTests;

    protected function setUp(): void
    {
        $real = getenv('OMNIDOCS_JSDOC_REAL');
        if (false === $real || !file_exists($real)) {
            self::markTestSkipped('OMNIDOCS_JSDOC_REAL names no output of the tool.');
        }
    }

    protected function source(): SourceInterface
    {
        return (new JsdocSourceFactory())->create(['file' => (string) getenv('OMNIDOCS_JSDOC_REAL'), 'root' => '/tmp/src']);
    }

    protected function documentsSymbols(): bool
    {
        return true;
    }
}
