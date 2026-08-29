<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Model\McpPageWindow;

final readonly class McpPageWindowResolver
{
    public const int PAGE_MAX = 200;

    private const string WARNING_CLAMPED = 'The requested page %d and limit %d were reduced to page %d and limit %d '
        . '(the page is capped at %d and the limit at %d). The response describes the reduced page, not the one asked '
        . 'for, and at most %d rows are reachable through pagination — narrow the filters instead of paging further.';
    private const string WARNING_RAISED_TO_MINIMUM = 'The requested page %d and limit %d are below the minimum and '
        . 'were raised to page %d and limit %d. The response describes that first page, not the one asked for.';

    public function resolve(int $page, int $limit, int $limitMax): McpPageWindow
    {
        $resolvedLimitMax = max($limitMax, McpPageWindow::LIMIT_MIN);

        return new McpPageWindow(
            page: min(max($page, McpPageWindow::PAGE_MIN), self::PAGE_MAX),
            limit: min(max($limit, McpPageWindow::LIMIT_MIN), $resolvedLimitMax),
            requestedPage: $page,
            requestedLimit: $limit,
            pageMax: self::PAGE_MAX,
            limitMax: $resolvedLimitMax,
        );
    }

    public function getClampWarning(McpPageWindow $window): string
    {
        if ($window->isRaisedToMinimum()) {
            return sprintf(
                self::WARNING_RAISED_TO_MINIMUM,
                $window->requestedPage,
                $window->requestedLimit,
                $window->page,
                $window->limit,
            );
        }

        return sprintf(
            self::WARNING_CLAMPED,
            $window->requestedPage,
            $window->requestedLimit,
            $window->page,
            $window->limit,
            $window->pageMax,
            $window->limitMax,
            $window->getReachableRows(),
        );
    }
}
