<?php
declare(strict_types = 1);

namespace Alahaka\Exception;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

class RequestException extends RuntimeException implements RequestExceptionInterface {
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
