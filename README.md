# PSR-18 HTTP Client

A lightweight [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client built on ext-curl. The PSR-17 factories are injected, so it works with any PSR-7 implementation.

## Tech Stack

- **PHP** 8.3+ with **ext-curl**
- **PSR-18** (`psr/http-client: ^1.0`) — `ClientInterface`
- **PSR-7 / PSR-17** (`psr/http-message`, `psr/http-factory`) — interfaces only; bring your own implementation (e.g. `gadwinn/psr-http-message`)

## Installation

```bash
composer require gadwinn/psr-http-client
```

## Usage

### Instantiation

```php
use G\Http\Client\Client;
use G\Http\Message\{RequestFactory, ResponseFactory, StreamFactory}; // or any PSR-17 factories

$client = new Client(
    responseFactory: new ResponseFactory(),
    requestFactory: new RequestFactory(),
    streamFactory: new StreamFactory(),
);
```

The constructor signature:

```php
public function __construct(
    ResponseFactoryInterface $responseFactory,
    RequestFactoryInterface $requestFactory,
    StreamFactoryInterface $streamFactory,
    HandlerInterface $handler = new CurlHandler()
)
```

### Sending a PSR-7 request

```php
$request = (new RequestFactory())
    ->createRequest('GET', 'https://api.example.com/users/42')
    ->withHeader('Accept', 'application/json');

$response = $client->sendRequest($request);

$response->getStatusCode();        // 200
(string) $response->getBody();     // '{"id":42,...}'
```

### Shortcuts

`request()` builds the request for you; `get()`, `post()`, `put()`, `patch()`, `delete()`, `head()`, `options()`, `connect()` and `trace()` call it with their method.

```php
$response = $client->get('https://api.example.com/users');

$response = $client->post('https://api.example.com/users', [
    'headers' => ['Content-Type' => 'application/json'],
    'body'    => json_encode(['name' => 'Alice']),
]);
```

| Option | Type | Effect |
|---|---|---|
| `headers` | `array<string, string\|string[]>` | Request headers |
| `body` | `string\|StreamInterface` | Request body |
| `protocolVersion` | `string` | `1.0`, `1.1`, `2` or `3` |
| `requestTarget` | `string` | Overrides the path and query of the URI |

### Errors

As required by PSR-18, an HTTP error status is **not** an exception: a `404` or a `500` is returned as a normal response. Exceptions are only thrown when no response could be obtained:

| Exception | Implements | When |
|---|---|---|
| `G\Http\Client\Exceptions\RequestException` | `RequestExceptionInterface` | The request is invalid (malformed URL, scheme other than http/https) |
| `G\Http\Client\Exceptions\NetworkException` | `NetworkExceptionInterface` | Network failure: DNS, connection, TLS, timeout… |
| `G\Http\Client\Exceptions\ClientException` | `ClientExceptionInterface` | Parent of both; also thrown when the response cannot be parsed |

Every exception gives the request back with `getRequest()`; the code and message are those of cURL.

```php
use Psr\Http\Client\{ClientExceptionInterface, NetworkExceptionInterface};

try {
    $response = $client->get('https://api.example.com/users');
} catch (NetworkExceptionInterface $e) {
    // retry later
} catch (ClientExceptionInterface $e) {
    // invalid request or unreadable response
}
```

### Behaviour

- **Redirects are never followed**: a `3xx` response is returned as is, with its `Location` header.
- Interim `1xx` responses are dropped; only the final response is returned.
- Repeated response headers (`Set-Cookie`…) are all kept.
- When the request has a body and no `Content-Type`, cURL adds `Content-Type: application/x-www-form-urlencoded`.
- The cURL handle is kept between requests, so connections are reused.

### cURL options

Pass extra cURL options to the handler: timeouts, proxy, CA bundle, user agent…

```php
use G\Http\Client\Handlers\CurlHandler;

$handler = new CurlHandler([
    CURLOPT_CONNECTTIMEOUT => 5,   // default: 60
    CURLOPT_TIMEOUT        => 30,  // default: none
    CURLOPT_PROXY          => 'http://proxy.local:3128',
    CURLOPT_USERAGENT      => 'my-app/1.0',
]);

$client = new Client(new ResponseFactory(), new RequestFactory(), new StreamFactory(), $handler);
```

The options the handler needs to build the response cannot be overridden: URL, method, headers, body, HTTP version, header/body callbacks and `CURLOPT_FOLLOWLOCATION`.

### Custom handler

Implement `G\Http\Client\Handlers\HandlerInterface` to replace the transport (another extension, a fake for your tests…). The handler receives the request and an empty response with a writable body, and returns the filled response.

```php
use G\Http\Client\Handlers\HandlerInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

final class FakeHandler implements HandlerInterface
{
    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write('{"ok":true}');

        return $response->withHeader('Content-Type', 'application/json');
    }
}

$client = new Client(new ResponseFactory(), new RequestFactory(), new StreamFactory(), new FakeHandler());
```

## Tests

The functional tests start a local PHP built-in server (`tests/Fixtures/server.php`); no Internet access is needed.

```bash
composer test-all
```
