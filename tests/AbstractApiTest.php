<?php

namespace App\Tests;

abstract class AbstractApiTest extends BaseAppTest
{
    protected function checkNotFound($url, $method = 'GET')
    {
        $client = self::getClient();
        $client->request($method, $url);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertNotEmpty($response['errorMsg']);
    }

    protected function checkSendInModuleError(array $response)
    {
        $this->assertFalse($response['success']);
        $this->assertEquals('Updating settings failed in RetailCRM', $response['errorMsg']);
    }
}
