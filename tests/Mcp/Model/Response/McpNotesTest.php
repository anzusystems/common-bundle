<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Model\Response\McpNotes;
use PHPUnit\Framework\TestCase;

final class McpNotesTest extends TestCase
{
    public function testKeepsOnlyTheTextsWhoseConditionHolds(): void
    {
        $notes = new McpNotes()
            ->addIf(true, 'kept')
            ->addIf(false, 'dropped');

        self::assertSame(['kept'], $notes->texts);
    }
}
