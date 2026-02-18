<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Api\VisibilityCheckInterface;
use Company\VisibilityDebugger\Model\Data\VisibilityIssue;

/**
 * Base for visibility checks: provides createIssue() for consistent issue DTOs.
 */
abstract class AbstractCheck implements VisibilityCheckInterface
{
    /**
     * Build a single visibility issue for check results.
     *
     * @param string $code Check identifier (e.g. product_status)
     * @param string $title Display title for the check
     * @param string $status One of VisibilityIssueInterface::STATUS_*
     * @param string $message Result message
     * @param string|null $suggestion Optional actionable suggestion
     * @param string|null $docsUrl Optional documentation URL
     */
    protected function createIssue(
        string $code,
        string $title,
        string $status,
        string $message,
        ?string $suggestion = null,
        ?string $docsUrl = null
    ): VisibilityIssueInterface {
        $issue = new VisibilityIssue();
        $issue->setCode($code)
            ->setTitle($title)
            ->setStatus($status)
            ->setMessage($message)
            ->setSuggestion($suggestion)
            ->setDocsUrl($docsUrl);
        return $issue;
    }
}
