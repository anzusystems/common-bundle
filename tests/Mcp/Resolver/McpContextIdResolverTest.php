<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpContextIdResolver;
use PHPUnit\Framework\TestCase;

final class McpContextIdResolverTest extends TestCase
{
    private const string CONTEXT_ID = '0197c6a6-56f4-7b74-a53c-71b5ad9b3821';

    private McpContextIdResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new McpContextIdResolver();
    }

    public function testResolveNormalizesUuid(): void
    {
        self::assertSame(self::CONTEXT_ID, $this->resolver->resolve(strtoupper(self::CONTEXT_ID)));
        self::assertSame(self::CONTEXT_ID, $this->resolver->resolve(' ' . self::CONTEXT_ID . ' '));
    }

    public function testResolveInvalidThrows(): void
    {
        $this->expectException(McpToolInputException::class);

        $this->resolver->resolve('not-a-uuid');
    }

    public function testResolveOptionalReturnsNullOnEmptyInput(): void
    {
        self::assertNull($this->resolver->resolveOptional(null));
        self::assertNull($this->resolver->resolveOptional(''));
        self::assertNull($this->resolver->resolveOptional('   '));
    }

    public function testResolveOptionalResolvesValue(): void
    {
        self::assertSame(self::CONTEXT_ID, $this->resolver->resolveOptional(self::CONTEXT_ID));
    }
}
