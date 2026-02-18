<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Model\Service;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Api\VisibilityCheckInterface;
use Company\VisibilityDebugger\Model\Data\VisibilityIssue;

/**
 * Aggregates all visibility checks and returns issues sorted: fail, warn, pass.
 */
class VisibilityDiagnosticsService
{
    /**
     * @var VisibilityCheckInterface[]
     */
    private array $checks;

    /**
     * @param array $checks List of VisibilityCheckInterface (injected via di.xml)
     */
    public function __construct(array $checks = [])
    {
        $this->checks = array_filter($checks, static fn($c) => $c instanceof VisibilityCheckInterface);
    }

    /**
     * Run all checks for the given product and store; return issues sorted by severity.
     *
     * @param int $productId
     * @param int $storeId
     * @return VisibilityIssueInterface[]
     */
    public function run(int $productId, int $storeId): array
    {
        $all = [];
        foreach ($this->checks as $check) {
            try {
                $issues = $check->check($productId, $storeId);
                foreach ($issues as $issue) {
                    $all[] = $issue;
                }
            } catch (\Throwable $e) {
                // Do not log sensitive data; add a generic issue for this check failure
                $all[] = $this->createIssue(
                    'check_error',
                    'Check error',
                    VisibilityIssueInterface::STATUS_WARN,
                    (string) __('A visibility check could not be completed.'),
                    (string) __('Retry or check server logs (no sensitive data is logged).')
                );
            }
        }

        return $this->sortBySeverity($all);
    }

    /**
     * Sort: fail first, then warn, then pass.
     *
     * @param VisibilityIssueInterface[] $issues
     * @return VisibilityIssueInterface[]
     */
    private function sortBySeverity(array $issues): array
    {
        $order = [
            VisibilityIssueInterface::STATUS_FAIL => 0,
            VisibilityIssueInterface::STATUS_WARN  => 1,
            VisibilityIssueInterface::STATUS_PASS => 2,
        ];
        usort($issues, static function (VisibilityIssueInterface $a, VisibilityIssueInterface $b) use ($order) {
            return ($order[$a->getStatus()] ?? 2) <=> ($order[$b->getStatus()] ?? 2);
        });
        return $issues;
    }

    private function createIssue(
        string $code,
        string $title,
        string $status,
        string $message,
        ?string $suggestion = null,
        ?string $docsUrl = null
    ): VisibilityIssueInterface {
        $issue = new VisibilityIssue();
        return $issue->setCode($code)
            ->setTitle($title)
            ->setStatus($status)
            ->setMessage($message)
            ->setSuggestion($suggestion)
            ->setDocsUrl($docsUrl);
    }
}
