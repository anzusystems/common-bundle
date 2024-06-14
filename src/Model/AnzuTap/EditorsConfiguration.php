<?php

namespace AnzuSystems\CommonBundle\Model\AnzuTap;

use AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark\LinkNodeTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark\MarkNodeTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\AnchorTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\BulletListTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\HeadingTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\HorizontalRuleTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\LineBreakTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\ListItemTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\OrderedListTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\ParagraphNodeTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\TableCellTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\TableRowTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\TableTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\TextNodeTransformer;

final class EditorsConfiguration
{
    public const array DEFAULT_ALLOWED_NODE_TRANSFORMERS = [
        TextNodeTransformer::class,
        TableTransformer::class,
        TableRowTransformer::class,
        ParagraphNodeTransformer::class,
        TableCellTransformer::class,
        OrderedListTransformer::class,
        BulletListTransformer::class,
        ListItemTransformer::class,
        LineBreakTransformer::class,
        // ImageTransformer::class,
        HorizontalRuleTransformer::class,
        HeadingTransformer::class,
        AnchorTransformer::class,
    ];

    public const array DEFAULT_ALLOWED_MARK_TRANSFORMERS = [
        LinkNodeTransformer::class,
        MarkNodeTransformer::class,
    ];

    public const array DEFAULT_SKIP_NODES = [
        'span',
        'style',
    ];
}
