<?php
declare(strict_types = 1);

namespace Alahaka;

use Alahaka\Driver\DriverInterface;
use Alahaka\Driver\CurlDriver;
use Alahaka\Driver\SocketDriver;
use Alahaka\Driver\StreamDriver;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class Client implements ClientInterface {
  protected DriverInterface $driver;

  public static function create(
    ResponseFactoryInterface $responseFactory,
    StreamFactoryInterface $streamFactory
  ): static {
    if (extension_loaded('curl') === true) {
      return new static(new CurlDriver($responseFactory, $streamFactory));
    }

    if (extension_loaded('sockets') === true) {
      return new static(new SocketDriver($responseFactory, $streamFactory));
    }

    return new static(new StreamDriver($responseFactory, $streamFactory));
  }

  public function __construct(DriverInterface $driver) {
    $this->driver = $driver;
  }

  public function sendRequest(RequestInterface $request): ResponseInterface {
    return $this->driver->sendRequest($request);
  }
}
