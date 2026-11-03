<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\CodingStandard\Fixer;

use AnzuSystems\CommonBundle\CodingStandard\Fixer\PromotedPropertyAttributeSpacingFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class PromotedPropertyAttributeSpacingFixerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // php-cs-fixer ships scoped inside ECS, whose bootstrap autoloads only the Symplify and prefixed classes.
        require_once dirname(__DIR__, 3) . '/vendor/symplify/easy-coding-standard/vendor/autoload.php';
    }

    /**
     * @dataProvider fixProvider
     */
    public function testFix(string $input, string $expected): void
    {
        self::assertSame($expected, $this->fix($input));
        self::assertSame($expected, $this->fix($expected));
    }

    public function fixProvider(): iterable
    {
        $input = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    #[Serialize]
                    private(set) string $headline,
                    #[Serialize]
                    private(set) string $perex,
                ) {
                }
            }
            PHP;
        $expected = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    #[Serialize]
                    private(set) string $headline,

                    #[Serialize]
                    private(set) string $perex,
                ) {
                }
            }
            PHP;

        yield 'all parameters with attributes' => [$input, $expected];

        $input = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    private readonly Bar $bar,
                    string $plain,
                    #[Serialize(type: Baz::class, strategy: Serialize::KEYS_VALUES)]
                    public readonly array $baz = [1, 2],
                    protected Qux $qux = new Qux(1, 2)
                ) {
                }
            }
            PHP;
        $expected = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    private readonly Bar $bar,

                    string $plain,

                    #[Serialize(type: Baz::class, strategy: Serialize::KEYS_VALUES)]
                    public readonly array $baz = [1, 2],

                    protected Qux $qux = new Qux(1, 2)
                ) {
                }
            }
            PHP;

        yield 'mix of parameters with and without attributes' => [$input, $expected];

        $input = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    #[Serialize]
                    private(set) string $headline, // shown on the front page
                    /**
                     * @var list<int>
                     */
                    #[Serialize]
                    private(set) array $ids,
                    // kept for the old clients
                    readonly int $legacy,
                ) {
                }
            }
            PHP;
        $expected = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    #[Serialize]
                    private(set) string $headline, // shown on the front page

                    /**
                     * @var list<int>
                     */
                    #[Serialize]
                    private(set) array $ids,

                    // kept for the old clients
                    readonly int $legacy,
                ) {
                }
            }
            PHP;

        yield 'docblock and comment before a parameter' => [$input, $expected];

        $input = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(

                    #[Serialize]
                    private(set) string $headline,


                    #[Serialize]
                    private(set) string $perex,

                ) {
                }
            }
            PHP;
        $expected = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    #[Serialize]
                    private(set) string $headline,

                    #[Serialize]
                    private(set) string $perex,
                ) {
                }
            }
            PHP;

        yield 'existing blank lines become one' => [$input, $expected];

        $code = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    private readonly Bar $bar,
                    private readonly Baz $baz,
                ) {
                }
            }
            PHP;

        yield 'constructor without attributes' => [$code, $code];

        $code = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    #[Autowire(param: 'kernel.debug')]
                    bool $debug,
                    private readonly Baz $baz,
                ) {
                }
            }
            PHP;

        yield 'attributed parameter that is not promoted' => [$code, $code];

        $code = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(#[Serialize] private string $a, #[Serialize] private string $b)
                {
                }
            }
            PHP;

        yield 'single-line constructor' => [$code, $code];

        $code = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    private readonly Bar $bar,
                ) {
                }

                public function bar(
                    #[MapQueryString]
                    Query $query,
                    #[CurrentUser]
                    User $user,
                ): void {
                }
            }
            PHP;

        yield 'method other than the constructor' => [$code, $code];
    }

    private function fix(string $code): string
    {
        $tokens = Tokens::fromCode($code);
        $fixer = new PromotedPropertyAttributeSpacingFixer();
        $fixer->fix(new SplFileInfo(__FILE__), $tokens);

        return $tokens->generateCode();
    }
}
