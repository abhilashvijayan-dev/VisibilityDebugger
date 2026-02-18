<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Test\Unit\Model\Service;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Api\VisibilityCheckInterface;
use Company\VisibilityDebugger\Model\Data\VisibilityIssue;
use Company\VisibilityDebugger\Model\Service\VisibilityDiagnosticsService;
use PHPUnit\Framework\TestCase;

class VisibilityDiagnosticsServiceTest extends TestCase
{
    public function testRunReturnsIssuesSortedBySeverityFailWarnPass(): void
    {
        $failIssue = $this->createIssue('a', 'A', VisibilityIssueInterface::STATUS_FAIL);
        $warnIssue = $this->createIssue('b', 'B', VisibilityIssueInterface::STATUS_WARN);
        $passIssue = $this->createIssue('c', 'C', VisibilityIssueInterface::STATUS_PASS);

        $check1 = $this->createMock(VisibilityCheckInterface::class);
        $check1->method('check')->willReturn([$passIssue]);
        $check2 = $this->createMock(VisibilityCheckInterface::class);
        $check2->method('check')->willReturn([$failIssue]);
        $check3 = $this->createMock(VisibilityCheckInterface::class);
        $check3->method('check')->willReturn([$warnIssue]);

        $service = new VisibilityDiagnosticsService([$check1, $check2, $check3]);
        $issues = $service->run(42, 1);

        $this->assertCount(3, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_FAIL, $issues[0]->getStatus());
        $this->assertSame(VisibilityIssueInterface::STATUS_WARN, $issues[1]->getStatus());
        $this->assertSame(VisibilityIssueInterface::STATUS_PASS, $issues[2]->getStatus());
    }

    public function testRunAggregatesAllCheckResults(): void
    {
        $issue1 = $this->createIssue('x', 'X', VisibilityIssueInterface::STATUS_PASS);
        $issue2 = $this->createIssue('y', 'Y', VisibilityIssueInterface::STATUS_PASS);
        $check1 = $this->createMock(VisibilityCheckInterface::class);
        $check1->method('check')->willReturn([$issue1]);
        $check2 = $this->createMock(VisibilityCheckInterface::class);
        $check2->method('check')->willReturn([$issue2]);

        $service = new VisibilityDiagnosticsService([$check1, $check2]);
        $issues = $service->run(42, 1);

        $this->assertCount(2, $issues);
        $codes = array_map(static fn($i) => $i->getCode(), $issues);
        $this->assertContains('x', $codes);
        $this->assertContains('y', $codes);
    }

    public function testRunAddsGenericIssueWhenCheckThrows(): void
    {
        $check = $this->createMock(VisibilityCheckInterface::class);
        $check->method('check')->willThrowException(new \RuntimeException('Internal error'));

        $service = new VisibilityDiagnosticsService([$check]);
        $issues = $service->run(42, 1);

        $this->assertCount(1, $issues);
        $this->assertSame('check_error', $issues[0]->getCode());
        $this->assertSame(VisibilityIssueInterface::STATUS_WARN, $issues[0]->getStatus());
    }

    public function testRunFiltersNonCheckInterfaces(): void
    {
        $issue = $this->createIssue('a', 'A', VisibilityIssueInterface::STATUS_PASS);
        $check = $this->createMock(VisibilityCheckInterface::class);
        $check->method('check')->willReturn([$issue]);

        $service = new VisibilityDiagnosticsService([$check, new \stdClass()]);
        $issues = $service->run(42, 1);

        $this->assertCount(1, $issues);
    }

    private function createIssue(string $code, string $title, string $status): VisibilityIssueInterface
    {
        $issue = new VisibilityIssue();
        $issue->setCode($code)->setTitle($title)->setStatus($status)->setMessage('msg');
        return $issue;
    }
}
