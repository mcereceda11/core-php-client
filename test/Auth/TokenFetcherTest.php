<?php

namespace Autodesk\Core;

use Autodesk\Core\Auth\TokenFetcher;
use Autodesk\Core\Exception\LogicException;
use Autodesk\Core\Exception\RuntimeException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

class TokenFetcherTest extends TestCase
{
    /**
     * @var Configuration|PHPUnit_Framework_MockObject_MockObject
     */
    private $configuration;

    /**
     * @var GuzzleClient|PHPUnit_Framework_MockObject_MockObject
     */
    private $httpClient;

    /**
     * @var TokenFetcher
     */
    private $tokenFetcher;

    public function setUp()
    {
        $this->configuration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()
            ->setMethods(['getHost'])
            ->getMock();

        $this->httpClient = $this->getMockBuilder(GuzzleClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['post'])
            ->getMock();

        $this->tokenFetcher = new TokenFetcher($this->configuration, $this->httpClient);
    }

    public function test_exception_is_thrown_when_no_scopes_defined()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot fetch token when no scopes where defined');

        $this->tokenFetcher->fetch('url', 'grantType', [], []);
    }

    public function test_call_to_http_client()
    {
        $path = 'somepage.php';
        $domain = 'www.test.com/';
        $url = "{$domain}/{$path}";

        $grantType = 'grantType';
        $scopes = ['scopeOne'];
        $additionalParams = [
            'additionalParameter' => 'additionalValue',
        ];

        $options = [
            'headers'     => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'client_id'           => $this->configuration->getClientId(),
                'client_secret'       => $this->configuration->getClientSecret(),
                'grant_type'          => $grantType,
                'scope'               => implode(' ', $scopes),
                'additionalParameter' => 'additionalValue',
            ],
        ];

        $response = ['X-Foo' => 'Bar'];
        $responseObject = new Response(200, [], json_encode($response));

        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with($url, $options)
            ->willReturn($responseObject);

        $this->configuration
            ->expects($this->once())
            ->method('getHost')
            ->willReturn($domain);

        $result = $this->tokenFetcher->fetch($path, $grantType, $scopes, $additionalParams);
        $this->assertEquals($response, $result);
    }

    public function test_general_exception_handling_from_guzzle_client()
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturnCallback(function () {
                throw new \Exception('Some exception because guzzle failed');
            });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch token');

        $this->tokenFetcher->fetch('somepage.php', 'grantType', ['a'], []);
    }

    public function test_error_response_handling()
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturnCallback(function () {
                $clientException = new ClientException(
                    'Some error with response code of 400',
                    new Request('A', 'B'),
                    new Response(400, [], '{"developerMessage": "Error in the content of the request"}')
                );

                throw $clientException;
            });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error in the content of the request');

        $this->tokenFetcher->fetch('somepage.php', 'grantType', ['a'], []);
    }
}