<?php

namespace Mita\UranusHttpServer\Testing;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Mita\UranusHttpServer\Server;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
use Mita\UranusHttpServer\Contracts\AuthenticatableInterface;
use Mockery;
use SlimSession\Helper;

abstract class BaseTestCase extends TestCase
{
    use DatabaseAuthenticationTrait;

    /**
     * @var Server|null
     */
    protected static $server = null;

    /**
     * @var Container|null
     */
    protected static $container = null;

    protected array $defaultHeaders = [];
    protected array $testConfig = [];

    protected static array $sharedData = [];

    protected function setSharedData(string $key, $value): void
    {
        static::$sharedData[$key] = $value;
    }

    protected function getSharedData(string $key)
    {
        return static::$sharedData[$key] ?? null;
    }
    
    abstract protected function getServer(): Server;
    
    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$server) {
            self::$server = $this->getServer();
            self::$server->boot();
            self::$container = self::$server->getContainer();
        }

        $this->setUpDatabase();
    }

    /**
     * @throws \Throwable
     */
    protected function runTest()
    {
        try {
            return parent::runTest();
        } catch (\Throwable $e) {
            // Chạy tearDown trước khi ném ngoại lệ
            $this->tearDown();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function withToken(string $token): self
    {
        $this->defaultHeaders['Authorization'] = 'Bearer ' . $token;
        return $this;
    }

    protected function makeRequest(string $method, string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        $request = new ServerRequest($method, $uri);
        $headers = array_merge($this->defaultHeaders, $headers);

        if (!empty($data)) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request = $request->withBody(Utils::streamFor(json_encode($data)));
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return self::$container->get(\Slim\App::class)->handle($request);
    }

    protected function get(string $uri, array $headers = []): ResponseInterface 
    {
        return $this->makeRequest('GET', $uri, [], $headers);
    }

    protected function post(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->makeRequest('POST', $uri, $data, $headers);
    }

    protected function put(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->makeRequest('PUT', $uri, $data, $headers);
    }

    protected function delete(string $uri, array $headers = []): ResponseInterface
    {
        return $this->makeRequest('DELETE', $uri, [], $headers);
    }

    protected function assertJsonResponse(ResponseInterface $response, array $expected): void
    {
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $actual = json_decode((string)$response->getBody(), true);
        $this->assertEquals($expected, $actual);
    }

    protected function assertResponseStatus(ResponseInterface $response, int $expectedStatus): void
    {
        $this->assertEquals($expectedStatus, $response->getStatusCode());
    }

    protected function dumpResponse(ResponseInterface $response)
    {
        $body = (string) $response->getBody();
        try {
            $json = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                print_r(json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL);
            } else {
                print_r($body . PHP_EOL);
            }
        } catch (\Exception $e) {
            print_r($body . PHP_EOL);
        }
    }

    protected function getResponseData(ResponseInterface $response): array
    {
        try {
            return json_decode((string)$response->getBody(), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }
}