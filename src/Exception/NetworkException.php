<?php

declare(strict_types=1);

namespace G\Http\Client\Exception;

use Psr\Http\Client\NetworkExceptionInterface;

class NetworkException extends ClientException implements NetworkExceptionInterface
{
}
