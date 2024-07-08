<?php
declare(strict_types = 1);

namespace Alahaka\Driver;

use Alahaka\Exception\NetworkException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class StreamDriver extends AbstractDriver {
  public function sendRequest(RequestInterface $request): ResponseInterface {
    $streamOpts = [
      'http' => [
        'method' => $request->getMethod(),
        'header' => $this->formatHeaders($request->getHeaders()),
        'user_agent' => $this->getUserAgent(),
        'ignore_errors' => true
      ],
      'socket' => [
        'tcp_nodelay' => true
      ]
    ];

    $streamOpts['http'] += $this->options;

    $body = $request->getBody();
    if ($body->isReadable() === true) {
      if ($body->isSeekable()) {
        $body->rewind();
      }

      $streamOpts['http']['content'] = $body->getContents();
    }

    $context = stream_context_create($streamOpts);
    $stream = @fopen((string)$request->getUri(), 'r', false, $context);
    if ($stream === false) {
      $lastError = error_get_last();
      throw new NetworkException(
        $lastError['message'] ?? '',
        $lastError['type'] ?? 0,
        $request
      );
    }

    $metaData = stream_get_meta_data($stream);

    /** @var \Psr\Http\Message\StreamInterface */
    $bodyStream = $this->streamFactory->createStreamFromResource(fopen('php://temp', 'w+b'));
    $bodyStream->write(stream_get_contents($stream));
    $bodyStream->rewind();

    fclose($stream);

    $responseData = $this->parseResponseHeader($metaData['wrapper_data']);

    /** @var Psr\Http\Message\ResponseInterface */
    $response = $this->responseFactory->createResponse($responseData['statusCode']);
    $response = $response
      ->withProtocolVersion($responseData['protocolVersion'])
      ->withBody($bodyStream);

    foreach ($responseData['headers'] as $name => $value) {
      $response = $response->withHeader($name, $value);
    }

    return $response;
  }
}
