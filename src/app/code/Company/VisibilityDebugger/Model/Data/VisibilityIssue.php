<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Model\Data;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Magento\Framework\DataObject;

/**
 * DTO for a single visibility check result.
 */
class VisibilityIssue extends DataObject implements VisibilityIssueInterface
{
    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return (string) $this->getData('code');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        return (string) $this->getData('title');
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        return (string) $this->getData('message');
    }

    /**
     * @inheritdoc
     */
    public function getSuggestion(): ?string
    {
        $v = $this->getData('suggestion');
        return $v === null || $v === '' ? null : (string) $v;
    }

    /**
     * @inheritdoc
     */
    public function getDocsUrl(): ?string
    {
        $v = $this->getData('docs_url');
        return $v === null || $v === '' ? null : (string) $v;
    }

    /**
     * @inheritdoc
     */
    public function setCode(string $code): self
    {
        $this->setData('code', $code);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setTitle(string $title): self
    {
        $this->setData('title', $title);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setStatus(string $status): self
    {
        $this->setData('status', $status);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setMessage(string $message): self
    {
        $this->setData('message', $message);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setSuggestion(?string $suggestion): self
    {
        $this->setData('suggestion', $suggestion);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setDocsUrl(?string $docsUrl): self
    {
        $this->setData('docs_url', $docsUrl);
        return $this;
    }
}
