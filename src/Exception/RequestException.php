<?php

declare(strict_types=1);

namespace G\Http\Client\Exception;

use Psr\Http\Client\RequestExceptionInterface;

class RequestException extends ClientException implements RequestExceptionInterface
{
}
