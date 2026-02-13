<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\AnzuTap;

use AnzuSystems\CommonBundle\Tests\AnzuWebTestCase;

final class AnzuTapTransformerTest extends AnzuWebTestCase
{
    private TestAnzuTapEditor $editor;

    protected function setUp(): void
    {
        $this->editor = static::getContainer()->get(TestAnzuTapEditor::class);
    }

    /**
     * @dataProvider transformerDataProvider
     */
    public function testTransformer(string $html, array $anzuTap): void
    {
        $body = $this->editor->transform($html);

        $this->assertEqualsCanonicalizing($anzuTap, $body->getAnzuTapBody()->toArray());
    }

    public function transformerDataProvider(): array
    {
        return [
            [
                'html' => '<p><url href="#1">Anchor link</url><anchor name="1"></anchor></p>',
                'anzuTap' => ['type' => 'doc', 'content' => [
                    [
                        'type' => 'paragraph',
                        'attrs' => [
                            'anchor' => 'pp-1',
                        ],
                        'content' => [
                            ['type' => 'text', 'marks' => [
                                ['type' => 'link', 'attrs' => [
                                    'variant' => 'anchor',
                                    'href' => 'pp-1',
                                    'nofollow' => false,
                                    'external' => false,
                                ]],
                            ],
                                'text' => 'Anchor link',
                            ],
                        ],
                    ],
                ]],
            ],
            [
                'html' => 'Simple Text <i><i><b>double italic</b></i><list></list>',
                'anzuTap' => ['type' => 'doc', 'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'Simple Text '],
                            ['type' => 'text', 'marks' => [['type' => 'italic'], ['type' => 'bold']], 'text' => 'double italic'],
                        ],
                    ],
                ]],
            ],
            [
                'html' => '<list><li>li1</li><li>li2</li><li></li></list>',
                'anzuTap' => ['type' => 'doc', 'content' => [
                    [
                        'type' => 'bulletList',
                        'content' => [
                            ['type' => 'listItem', 'content' => [
                                ['type' => 'paragraph', 'content' => [
                                    ['type' => 'text', 'text' => 'li1'],
                                ]],
                            ]],
                            ['type' => 'listItem', 'content' => [
                                ['type' => 'paragraph', 'content' => [
                                    ['type' => 'text', 'text' => 'li2'],
                                ]],
                            ]],
                        ],
                    ],
                ]],
            ],
            [
                'html' => '<p><i>Text before <email href="mailto:jolo@sme.sk">jolo@sme.sk</email>.</i></p>',
                'anzuTap' => ['type' => 'doc', 'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'marks' => [['type' => 'italic']], 'text' => 'Text before '],
                            ['type' => 'text', 'marks' => [
                                ['type' => 'italic'],
                                ['type' => 'link', 'attrs' => [
                                    'href' => 'jolo@sme.sk',
                                    'variant' => 'email',
                                ]],
                            ],
                                'text' => 'jolo@sme.sk',
                            ],
                            ['type' => 'text', 'marks' => [['type' => 'italic']], 'text' => '.'],
                        ],
                    ],
                ]],
            ],
        ];
    }
}
