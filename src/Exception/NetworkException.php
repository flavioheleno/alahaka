<?php
declare(strict_types = 1);

namespace Alahaka\Exception;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

class NetworkException extends RuntimeException implements NetworkExceptionInterface {
  protected RequestInterface $request;

  public function __construct(
    string $message,
    int $code,
    RequestInterface $request,
    Throwable|null $previous = null
  ) {
    parent::__construct($message, $code, $previous);

    $this->request = $request;
  }

  public function getRequest(): RequestInterface {
    return $this->request;
  }
}
