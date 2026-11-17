<?php

declare(strict_types=1);

namespace G\Http\Client\Handler;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use G\Http\Client\Exception\{ClientException, NetworkException, RequestException};
use CurlHandle;
use InvalidArgumentException;

/**
 * Sends requests with ext-curl. The cURL handle is kept between requests so
 * that connections are reused.
 */
class CurlHandler implements HandlerInterface
{
    protected const DEFAULT_CONNECT_TIMEOUT = 60;
    protected const STATUS_LINE_PARTS = 3;
    protected const HEADER_PARTS = 2;
    protected const METHOD_HEAD = 'HEAD';
    protected const METHODS_WITH_BODY = ['POST', 'PUT', 'PATCH'];

    /** cURL errors caused by the request itself rather than by the network. */
    protected const REQUEST_ERRORS = [CURLE_URL_MALFORMAT, CURLE_UNSUPPORTED_PROTOCOL];

    protected ?CurlHandle $ch = null;
    protected ResponseInterface $emptyResponse;
    protected ResponseInterface $response;
    protected string $lastHeaderName = '';

    /**
     * @param array<int,mixed> $curlOptions Extra cURL options (timeouts, proxy, CA bundle...). The options the
     *                                      handler needs to build the response cannot be overridden.
     */
    public function __construct(protected array $curlOptions = [])
    {
 //
    }

    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->emptyResponse = $response;
        $this->response = $response;
        $this->lastHeaderName = '';

        if ($this->ch !== null) {
            curl_reset($this->ch);
        } else {
            $this->ch = curl_init() ?: null;
        }

        if ($this->ch === null) {
            throw new NetworkException($request, 'Unable to initialise cURL.');
        }

        curl_setopt_array($this->ch, $this->getCurlOptions($request));

        try {
            curl_exec($this->ch);
        } catch (InvalidArgumentException $exception) {
            throw new ClientException($request, 'Invalid HTTP response: ' . $exception->getMessage(), 0, $exception);
        }

        $errno = curl_errno($this->ch);
        if ($errno !== CURLE_OK) {
            $error = curl_error($this->ch);

            throw in_array($errno, self::REQUEST_ERRORS, true)
                ? new RequestException($request, $error, $errno)
                : new NetworkException($request, $error, $errno);
        }

        if ($this->response->getBody()->isSeekable()) {
            $this->response->getBody()->rewind();
        }

        return $this->response;
    }

    /* --- */

    /**
     * @param RequestInterface $request
     *
     * @return array<int,mixed> The cURL options
     */
    protected function getCurlOptions(RequestInterface $request): array
    {
        $method = strtoupper($request->getMethod());

        $options = [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_URL => $this->getRequestUrl($request),
            CURLOPT_HTTP_VERSION => $this->getHttpVersion($request),
            CURLOPT_HTTPHEADER => $this->getHeaders($request),
            CURLOPT_HEADER => false,
            CURLOPT_HEADERFUNCTION => [$this, 'curlHeaderFunction'],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => [$this, 'curlWriteFunction'],
            // Redirects are returned to the caller, never followed.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY => $method === self::METHOD_HEAD,
        ];

        $body = (string) $request->getBody();
        if ($body !== '' || in_array($method, self::METHODS_WITH_BODY, true)) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        return $options + $this->curlOptions + [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => self::DEFAULT_CONNECT_TIMEOUT,
        ];
    }

    protected function getHttpVersion(RequestInterface $request): int
    {
        return match ($request->getProtocolVersion()) {
            '1.0' => CURL_HTTP_VERSION_1_0,
            '1.1' => CURL_HTTP_VERSION_1_1,
            '2', '2.0' => CURL_HTTP_VERSION_2_0,
            '3', '3.0' => defined('CURL_HTTP_VERSION_3') ? CURL_HTTP_VERSION_3 : CURL_HTTP_VERSION_NONE,
            default => CURL_HTTP_VERSION_NONE,
        };
    }

    protected function getRequestUrl(RequestInterface $request): string
    {
        $uri = $request->getUri();
        $target = $request->getRequestTarget();

        // Only an origin-form target ("/path?query") can replace the path of the URI.
        if (!str_starts_with($target, '/')) {
            return (string) $uri;
        }

        $url = '';
        if ($uri->getScheme() !== '') {
            $url .= $uri->getScheme() . ':';
        }
        if ($uri->getAuthority() !== '') {
            $url .= '//' . $uri->getAuthority();
        }

        return $url . $target;
    }

    /**
     * CURLOPT_HEADERFUNCTION
     *
     * @param CurlHandle $ch
     * @param string $data
     *
     * @return int
     * @throws InvalidArgumentException
     */
    protected function curlHeaderFunction(CurlHandle $ch, string $data): int
    {
        $header = trim($data);
        if ($header === '') {
            return strlen($data);
        }

        if (stripos($header, 'HTTP/') === 0) {
            // A new status line starts a new response: the headers of the
            // previous ones (1xx interim responses) are dropped (PSR-18).
            $parts = explode(' ', $header, self::STATUS_LINE_PARTS);
            $this->lastHeaderName = '';
            $this->response = $this->emptyResponse
                ->withProtocolVersion(explode('/', $parts[0], self::HEADER_PARTS)[1])
                ->withStatus((int) ($parts[1] ?? 0), $parts[2] ?? '');
            return strlen($data);
        }

        $parts = explode(':', $header, self::HEADER_PARTS);
        if (isset($parts[1])) {
            $this->lastHeaderName = trim($parts[0]);
            $this->response = $this->response->withAddedHeader($this->lastHeaderName, trim($parts[1]));
        } elseif ($this->lastHeaderName !== '') {
            $this->response = $this->response->withAddedHeader($this->lastHeaderName, trim($parts[0]));
        }

        return strlen($data);
    }

    /**
     * CURLOPT_WRITEFUNCTION
     *
     * @param CurlHandle $ch
     * @param string $data
     *
     * @return int
     */
    protected function curlWriteFunction(CurlHandle $ch, string $data): int
    {
        $this->response->getBody()->write($data);
        return strlen($data);
    }

    /**
     * @param RequestInterface $request
     *
     * @return string[]
     */
    protected function getHeaders(RequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                // "Name;" is the cURL syntax to send a header without value.
                $headers[] = $name . ($value !== '' ? ': ' . $value : ';');
            }
        }

        return $headers;
    }
}
