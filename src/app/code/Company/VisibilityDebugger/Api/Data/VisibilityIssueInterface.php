<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Api\Data;

/**
 * Single visibility check result: pass, fail, or warn.
 *
 * @api
 */
interface VisibilityIssueInterface
{
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';
    public const STATUS_WARN = 'warn';

    /**
     * Unique code for this check (e.g. product_status, visibility_attribute).
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Human-readable title of the check.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Status: pass, fail, or warn.
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Result message.
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Actionable suggestion (null if none).
     *
     * @return string|null
     */
    public function getSuggestion(): ?string;

    /**
     * Optional docs URL.
     *
     * @return string|null
     */
    public function getDocsUrl(): ?string;

    /**
     * @param string $code
     * @return $this
     */
    public function setCode(string $code): self;

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self;

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): self;

    /**
     * @param string|null $suggestion
     * @return $this
     */
    public function setSuggestion(?string $suggestion): self;

    /**
     * @param string|null $docsUrl
     * @return $this
     */
    public function setDocsUrl(?string $docsUrl): self;
}
