<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\McpToolDefinitionsFactory;
use LogicException;
use PHPUnit\Framework\TestCase;

final class McpToolDefinitionsTest extends TestCase
{
    private const string READ_TOOL = 'example_read';
    private const string WRITE_TOOL = 'example_write';
    private const string OTHER_WRITE_TOOL = 'example_other_write';
    private const string UNMAPPED_TOOL = 'example_unmapped';
    private const string PREVIEW_TOOL = 'example_preview';
    private const string TOOL_CATEGORY = 'examples';
    private const string PERMISSION = 'example_read';

    public function testToolNamesListEveryToolWithAPermissionWithoutThePreview(): void
    {
        self::assertSame([self::READ_TOOL, self::WRITE_TOOL, self::OTHER_WRITE_TOOL], $this->createDefinitions()->getToolNames());
    }

    public function testWriteToolNamesListOnlyToolsWithAPermissionThatAreNotReadOnly(): void
    {
        self::assertSame([self::WRITE_TOOL, self::OTHER_WRITE_TOOL], $this->createDefinitions()->getWriteToolNames());
    }

    public function testPreviewToolNameIsFoundByItsWriteTool(): void
    {
        $definitions = $this->createDefinitions();

        self::assertSame(self::PREVIEW_TOOL, $definitions->getPreviewToolName(self::WRITE_TOOL));
        self::assertNull($definitions->getPreviewToolName(self::OTHER_WRITE_TOOL));
        self::assertNull($definitions->getPreviewToolName(self::UNMAPPED_TOOL));
    }

    public function testToolCategoryIsReturnedAndAToolWithoutCategoryFails(): void
    {
        $definitions = $this->createDefinitions();

        self::assertSame(self::TOOL_CATEGORY, $definitions->getToolCategory(self::WRITE_TOOL));
        self::assertSame(self::TOOL_CATEGORY, $definitions->getToolCategory(self::PREVIEW_TOOL));

        $this->expectException(LogicException::class);

        $definitions->getToolCategory(self::UNMAPPED_TOOL);
    }

    private function createDefinitions(): McpToolDefinitions
    {
        return McpToolDefinitionsFactory::create(
            toolPermissions: [
                self::READ_TOOL => self::PERMISSION,
                self::WRITE_TOOL => self::PERMISSION,
                self::OTHER_WRITE_TOOL => self::PERMISSION,
            ],
            readOnlyToolNames: [self::READ_TOOL, self::PREVIEW_TOOL],
            toolCategories: [self::WRITE_TOOL => self::TOOL_CATEGORY, self::PREVIEW_TOOL => self::TOOL_CATEGORY],
            writeToolNamesByPreview: [self::PREVIEW_TOOL => self::WRITE_TOOL],
        );
    }
}
