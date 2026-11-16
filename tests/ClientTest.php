<?php

declare(strict_types=1);

namespace G\Http\Client\Tests;

use G\Http\Client\Client;
use G\Http\Client\Handler\CurlHandler;
use G\Http\Client\Handler\HandlerInterface;
use G\Http\Client\Tests\Fixtures\Factory;
use G\Http\Client\Tests\Fixtures\Stream;
use G\Http\Client\Tests\Fixtures\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Functional tests run against a local PHP built-in server (see Fixtures/server.php).
 */
final class ClientTest extends TestCase
{
    private const HOST = '127.0.0.1';

    /** @var resource|null */
    private static $server = null;
    private static string $baseUrl = '';

    private Factory $factory;
    private Client $client;

    public static function setUpBeforeClass(): void
    {
        $port = self::freePort();
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        $server = proc_open(
            [PHP_BINARY, '-S', self::HOST . ':' . $port, __DIR__ . '/Fixtures/server.php'],
            [['pipe', 'r'], ['file', $null, 'w'], ['file', $null, 'w']],
            $pipes
        );
        if ($server === false) {
            throw new RuntimeException('Unable to start the test server.');
        }

        self::$server = $server;
        self::$baseUrl = 'http://' . self::HOST . ':' . $port;

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen(self::HOST, $port);
            if ($connection !== false) {
                fclose($connection);
                return;
            }
            usleep(50_000);
        }

        throw new RuntimeException('The test server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    protected function setUp(): void
    {
        $this->factory = new Factory();
        $this->client = new Client($this->factory, $this->factory, $this->factory);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://' . self::HOST . ':0');
        if ($socket === false) {
            throw new RuntimeException('Unable to find a free port.');
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private function request(string $method, string $path): RequestInterface
    {
        return $this->factory->createRequest($method, self::$baseUrl . $path);
    }

    /**
     * @return array{method: string, target: string, protocol: string, headers: array<string, string>, body: string}
     */
    private function echoed(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    // -------------------------------------------------------------------------
    // sendRequest (PSR-18)
    // -------------------------------------------------------------------------

    public function testImplementsPsrClient(): void
    {
        $this->assertInstanceOf(ClientInterface::class, $this->client);
    }

    public function testSendRequest(): void
    {
        $response = $this->client->sendRequest($this->request('GET', '/echo?foo=bar'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getReasonPhrase());
        $this->assertSame('1.1', $response->getProtocolVersion());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('GET', $this->echoed($response)['method']);
        $this->assertSame('/echo?foo=bar', $this->echoed($response)['target']);
    }

    public function testResponseBodyIsRewound(): void
    {
        $response = $this->client->sendRequest($this->request('GET', '/echo'));

        $this->assertSame(0, $response->getBody()->tell());
        $this->assertStringStartsWith('{', $response->getBody()->getContents());
    }

    public function testSendRequestWithBody(): void
    {
        $request = $this->request('POST', '/echo')->withBody(new Stream('foo=bar'));

        $this->assertSame('foo=bar', $this->echoed($this->client->sendRequest($request))['body']);
    }

    public function testBodyOfUnknownSizeIsSent(): void
    {
        $request = $this->request('PUT', '/echo')->withBody(new Stream('payload', knownSize: false));

        $this->assertSame('payload', $this->echoed($this->client->sendRequest($request))['body']);
    }

    public function testPostWithoutBodySendsAnEmptyBody(): void
    {
        $echoed = $this->echoed($this->client->sendRequest($this->request('POST', '/echo')));

        $this->assertSame('', $echoed['body']);
        $this->assertSame('0', $echoed['headers']['content-length'] ?? null);
    }

    public function testSendRequestWithHeaders(): void
    {
        $request = $this->request('GET', '/echo')
            ->withHeader('X-Foo', 'Bar')
            ->withHeader('X-Zero', '0')
            ->withHeader('X-Empty', '');

        $headers = $this->echoed($this->client->sendRequest($request))['headers'];

        $this->assertSame('Bar', $headers['x-foo'] ?? null);
        $this->assertSame('0', $headers['x-zero'] ?? null);
        $this->assertSame('', $headers['x-empty'] ?? null);
    }

    public function testSendRequestWithProtocolVersion(): void
    {
        $response = $this->client->sendRequest($this->request('GET', '/echo')->withProtocolVersion('1.0'));

        $this->assertSame('HTTP/1.0', $this->echoed($response)['protocol']);
    }

    public function testSendRequestWithRequestTarget(): void
    {
        $request = $this->request('GET', '/wrong')->withRequestTarget('/right?foo=bar');

        $this->assertSame('/right?foo=bar', $this->echoed($this->client->sendRequest($request))['target']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function errorStatusProvider(): iterable
    {
        yield 'client error' => [404];
        yield 'server error' => [500];
    }

    #[DataProvider('errorStatusProvider')]
    public function testErrorStatusIsNotAnException(int $status): void
    {
        $response = $this->client->sendRequest($this->request('GET', '/status?code=' . $status));

        $this->assertSame($status, $response->getStatusCode());
    }

    public function testRepeatedResponseHeadersAreAllKept(): void
    {
        $response = $this->client->sendRequest($this->request('GET', '/cookies'));

        $this->assertSame(['a=1', 'b=2'], $response->getHeader('Set-Cookie'));
    }

    public function testRedirectsAreNotFollowed(): void
    {
        $client = new Client(
            $this->factory,
            $this->factory,
            $this->factory,
            new CurlHandler([CURLOPT_FOLLOWLOCATION => true])
        );

        $response = $client->sendRequest($this->request('GET', '/redirect'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/echo', $response->getHeaderLine('Location'));
    }

    public function testHeadHasNoBody(): void
    {
        $response = $this->client->sendRequest($this->request('HEAD', '/echo'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testResponsesDoNotLeakBetweenRequests(): void
    {
        $first = $this->client->sendRequest($this->request('GET', '/cookies'));
        $second = $this->client->sendRequest($this->request('GET', '/echo'));

        $this->assertTrue($first->hasHeader('Set-Cookie'));
        $this->assertFalse($second->hasHeader('Set-Cookie'));
        $this->assertSame(['1'], $second->getHeader('X-Request-Count'));
        $this->assertNotSame((string) $first->getBody(), (string) $second->getBody());
    }

    public function testCustomCurlOptions(): void
    {
        $client = new Client(
            $this->factory,
            $this->factory,
            $this->factory,
            new CurlHandler([CURLOPT_USERAGENT => 'gadwinn-test'])
        );

        $headers = $this->echoed($client->sendRequest($this->request('GET', '/echo')))['headers'];

        $this->assertSame('gadwinn-test', $headers['user-agent'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Exceptions (PSR-18)
    // -------------------------------------------------------------------------

    public function testNetworkFailureThrowsNetworkException(): void
    {
        $request = $this->factory->createRequest('GET', 'http://' . self::HOST . ':' . self::freePort() . '/');

        try {
            $this->client->sendRequest($request);
            $this->fail('A connection failure must throw.');
        } catch (NetworkExceptionInterface $exception) {
            $this->assertSame($request, $exception->getRequest());
            $this->assertNotSame('', $exception->getMessage());
        }
    }

    public function testInvalidRequestThrowsRequestException(): void
    {
        $request = $this->factory->createRequest('GET', 'ftp://' . self::HOST . '/file');

        try {
            $this->client->sendRequest($request);
            $this->fail('An unsupported scheme must throw.');
        } catch (RequestExceptionInterface $exception) {
            $this->assertSame($request, $exception->getRequest());
        }
    }

    // -------------------------------------------------------------------------
    // request() and its shortcuts
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function shortcutProvider(): iterable
    {
        foreach (['get', 'post', 'put', 'patch', 'delete', 'options'] as $method) {
            yield $method => [$method];
        }
    }

    #[DataProvider('shortcutProvider')]
    public function testShortcutSendsItsMethod(string $shortcut): void
    {
        $response = $this->client->$shortcut(self::$baseUrl . '/echo');

        $this->assertSame(strtoupper($shortcut), $this->echoed($response)['method']);
    }

    public function testHeadShortcut(): void
    {
        $this->assertSame(200, $this->client->head(self::$baseUrl . '/echo')->getStatusCode());
    }

    public function testRequestAcceptsAUriObject(): void
    {
        $response = $this->client->request('GET', new Uri(self::$baseUrl . '/echo?a=1'));

        $this->assertSame('/echo?a=1', $this->echoed($response)['target']);
    }

    public function testRequestWithOptions(): void
    {
        $response = $this->client->post(
            self::$baseUrl . '/wrong',
            [
                'protocolVersion' => '1.0',
                'headers' => ['X-Foo' => 'Bar', 'Content-Type' => 'text/plain'],
                'body' => 'foo=bar',
                'requestTarget' => '/echo?x=1',
            ]
        );

        $echoed = $this->echoed($response);

        $this->assertSame('POST', $echoed['method']);
        $this->assertSame('HTTP/1.0', $echoed['protocol']);
        $this->assertSame('/echo?x=1', $echoed['target']);
        $this->assertSame('Bar', $echoed['headers']['x-foo'] ?? null);
        $this->assertSame('text/plain', $echoed['headers']['content-type'] ?? null);
        $this->assertSame('foo=bar', $echoed['body']);
    }

    public function testRequestAcceptsAStreamBody(): void
    {
        $response = $this->client->put(self::$baseUrl . '/echo', ['body' => new Stream('from stream')]);

        $this->assertSame('from stream', $this->echoed($response)['body']);
    }

    // -------------------------------------------------------------------------
    // Custom handler
    // -------------------------------------------------------------------------

    public function testCustomHandlerReceivesAnEmptyResponse(): void
    {
        $handler = new class implements HandlerInterface {
            public ?RequestInterface $request = null;
            public ?ResponseInterface $response = null;

            public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
            {
                $this->request = $request;
                $this->response = $response;

                return $response->withStatus(204);
            }
        };

        $client = new Client($this->factory, $this->factory, $this->factory, $handler);
        $response = $client->get('http://example.test/path');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('GET', $handler->request?->getMethod());
        $this->assertSame(200, $handler->response?->getStatusCode());
        $this->assertSame('', (string) $handler->response?->getBody());
    }
}
