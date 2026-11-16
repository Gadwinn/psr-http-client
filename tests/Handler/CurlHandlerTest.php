<?php

declare(strict_types=1);

namespace G\Http\Client\Tests\Handler;

use CurlHandle;
use G\Http\Client\Handler\CurlHandler;
use G\Http\Client\Tests\Fixtures\Request;
use G\Http\Client\Tests\Fixtures\Response;
use G\Http\Client\Tests\Fixtures\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Unit tests of the response parsing and of the cURL options, without any network access.
 */
final class CurlHandlerTest extends TestCase
{
    /**
     * Handler exposing its protected parts.
     */
    private function handler(array $curlOptions = []): CurlHandler
    {
        return new class ($curlOptions) extends CurlHandler {
            /**
             * @param string[] $lines
             */
            public function parse(array $lines): ResponseInterface
            {
                $this->emptyResponse = $this->response = new Response();
                $this->lastHeaderName = '';
                $ch = curl_init();
                assert($ch instanceof CurlHandle);

                foreach ($lines as $line) {
                    $this->curlHeaderFunction($ch, $line . "\r\n");
                }

                return $this->response;
            }

            /**
             * @return array<int, mixed>
             */
            public function options(RequestInterface $request): array
            {
                return $this->getCurlOptions($request);
            }
        };
    }

    public function testStatusLineAndHeadersAreParsed(): void
    {
        $response = $this->handler()->parse([
            'HTTP/1.1 404 Not Found',
            'Content-Type: text/html; charset=utf-8',
            'X-Time: 12:30:00',
            '',
        ]);

        $this->assertSame('1.1', $response->getProtocolVersion());
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $response->getReasonPhrase());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('12:30:00', $response->getHeaderLine('X-Time'));
    }

    public function testStatusLineWithoutReasonPhrase(): void
    {
        $response = $this->handler()->parse(['HTTP/2 200', '']);

        $this->assertSame('2', $response->getProtocolVersion());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testInterimResponsesAreDropped(): void
    {
        // PSR-18: the client must reassemble a multi-step 1xx response itself.
        $response = $this->handler()->parse([
            'HTTP/1.1 100 Continue',
            'X-Interim: yes',
            '',
            'HTTP/1.1 200 OK',
            'X-Final: yes',
            '',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('X-Interim'));
        $this->assertSame('yes', $response->getHeaderLine('X-Final'));
    }

    public function testRepeatedAndFoldedHeaders(): void
    {
        $response = $this->handler()->parse([
            'HTTP/1.1 200 OK',
            'Set-Cookie: a=1',
            'Set-Cookie: b=2',
            'X-Folded: first',
            ' second',
            '',
        ]);

        $this->assertSame(['a=1', 'b=2'], $response->getHeader('Set-Cookie'));
        $this->assertSame(['first', 'second'], $response->getHeader('X-Folded'));
    }

    public function testRequiredOptionsCannotBeOverridden(): void
    {
        $request = new Request('GET', new Uri('http://example.test/path?q=1'));
        $options = $this->handler([
            CURLOPT_URL => 'http://other.test/',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
        ])->options($request);

        $this->assertSame('http://example.test/path?q=1', $options[CURLOPT_URL]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame(5, $options[CURLOPT_TIMEOUT]);
        $this->assertSame(2, $options[CURLOPT_CONNECTTIMEOUT]);
        $this->assertSame(['Host: example.test'], $options[CURLOPT_HTTPHEADER]);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
    }

    public function testDefaultOptions(): void
    {
        $options = $this->handler()->options(new Request('GET', new Uri('http://example.test/')));

        $this->assertSame(60, $options[CURLOPT_CONNECTTIMEOUT]);
        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
    }

    public function testNonOriginFormTargetFallsBackToTheUri(): void
    {
        $request = (new Request('OPTIONS', new Uri('http://example.test/path')))->withRequestTarget('*');

        $this->assertSame('http://example.test/path', $this->handler()->options($request)[CURLOPT_URL]);
    }

    public function testProtocolVersions(): void
    {
        $request = new Request('GET', new Uri('http://example.test/'));
        $handler = $this->handler();

        $this->assertSame(
            CURL_HTTP_VERSION_1_0,
            $handler->options($request->withProtocolVersion('1.0'))[CURLOPT_HTTP_VERSION]
        );
        $this->assertSame(
            CURL_HTTP_VERSION_2_0,
            $handler->options($request->withProtocolVersion('2'))[CURLOPT_HTTP_VERSION]
        );
        $this->assertSame(
            CURL_HTTP_VERSION_2_0,
            $handler->options($request->withProtocolVersion('2.0'))[CURLOPT_HTTP_VERSION]
        );
        $this->assertSame(
            CURL_HTTP_VERSION_NONE,
            $handler->options($request->withProtocolVersion('9.9'))[CURLOPT_HTTP_VERSION]
        );
    }
}
