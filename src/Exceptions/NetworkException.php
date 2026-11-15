<?php

declare(strict_types=1);

namespace G\Http\Client\Exceptions;

use Psr\Http\Client\NetworkExceptionInterface;

class NetworkException extends ClientException implements NetworkExceptionInterface
{
}
