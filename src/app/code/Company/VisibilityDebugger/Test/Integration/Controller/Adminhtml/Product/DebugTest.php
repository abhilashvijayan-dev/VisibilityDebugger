<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Test\Integration\Controller\Adminhtml\Product;

use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Integration test skeleton for Debug controller JSON response.
 *
 * @magentoAppArea adminhtml
 */
class DebugTest extends AbstractBackendController
{
    /**
     * @inheritDoc
     */
    protected $resource = 'Company_VisibilityDebugger::debug';

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/company_visibilitydebugger/product/debug';

    /**
     * @inheritDoc
     */
    protected $httpMethod = 'GET';

    /**
     * Verify controller returns JSON with success and issues when valid product and store.
     */
    public function testExecuteReturnsJsonWithSuccessAndIssues(): void
    {
        $this->getRequest()->setParams(['product_id' => '1', 'store_id' => '1']);
        $this->dispatch($this->uri . '?product_id=1&store_id=1');
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());
        $body = $response->getBody();
        $this->assertNotEmpty($body);
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('issues', $data);
        $this->assertIsArray($data['issues']);
        if ($data['success']) {
            $this->assertArrayHasKey('product_id', $data);
            $this->assertArrayHasKey('store_id', $data);
            foreach ($data['issues'] as $issue) {
                $this->assertArrayHasKey('code', $issue);
                $this->assertArrayHasKey('title', $issue);
                $this->assertArrayHasKey('status', $issue);
                $this->assertArrayHasKey('message', $issue);
                $this->assertArrayHasKey('suggestion', $issue);
            }
        } else {
            $this->assertArrayHasKey('error', $data);
        }
    }

    /**
     * Verify controller returns JSON with success false when product_id invalid.
     */
    public function testExecuteReturnsErrorWhenProductIdInvalid(): void
    {
        $this->getRequest()->setParams(['product_id' => '999999', 'store_id' => '1']);
        $this->dispatch($this->uri . '?product_id=999999&store_id=1');
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode());
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * ACL: has access when resource allowed.
     */
    public function testAclHasAccess(): void
    {
        $this->getRequest()->setParams(['product_id' => '1', 'store_id' => '1']);
        parent::testAclHasAccess();
    }

    /**
     * ACL: denies access when resource denied.
     */
    public function testAclNoAccess(): void
    {
        parent::testAclNoAccess();
    }
}
