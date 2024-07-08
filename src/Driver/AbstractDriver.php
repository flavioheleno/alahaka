<?php
declare(strict_types = 1);

namespace Alahaka\Driver;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

abstract class AbstractDriver implements DriverInterface {
  protected ResponseFactoryInterface $responseFactory;
  protected StreamFactoryInterface $streamFactory;
  /**
   * @var array<string, mixed>
   */
  protected array $options = [
    'proxy' => null,
    'follow_location' => 1,
    'max_redirects' => 20,
    'read_timeout' => 10
  ];

  protected function getUserAgent(): string {
    return sprintf(
      'alahaka/%d (PHP/%s; %s)',
      '0.0.1a',
      PHP_VERSION,
      PHP_OS_FAMILY
    );
  }

  /**
   * @param array<string, list<string>> $headers
   *
   * @return list<string>
   */
  protected function formatHeaders(array $headers): array {
    $formattedHeaders = [];
    foreach ($headers as $name => $values) {
      $formattedHeaders[] = sprintf(
        '%s: %s',
        $name,
        implode(', ', $values)
      );
    }

    return $formattedHeaders;
  }

  /**
   * @param array<string, list<string>> $header
   *
   * @return array{
   *   protocol_version: string,
   *   status_code: int,
   *   headers: list<string>
   * }
   */
  protected function parseResponseHeader(array $header): array {
    $result = [
      'protocolVersion' => '',
      'statusCode' => 0,
      'headers' => []
    ];
    foreach ($header as $line) {
      $split = strpos($line, ':');
      if ($split === false) {
        if (preg_match('/^HTTP\/(?<version>0\.9|1\.0|1\.1|2|3) (?<code>[0-9]{3})/', $line, $matches) === 1) {
          $result['protocolVersion'] = $matches['version'];
          $result['statusCode'] = (int)$matches['code'];
        }

        continue;
      }

      $name = strtolower(trim(substr($line, 0, $split)));
      $value = trim(substr($line, $split + 1));
      if (array_key_exists($name, $result['headers']) === false) {
        $result['headers'][$name] = [];
      }

      $result['headers'][$name][] = $value;
    }

    return $result;
  }

  public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory) {
    $this->responseFactory = $responseFactory;
    $this->streamFactory = $streamFactory;
  }

  public function getOption(string $name): mixed {
    if (array_key_exists($name, $this->options) === false) {
      throw new InvalidArgumentException("Invalid option \"{$name}\"");
    }

    return $this->options[$name];
  }

  public function setOption(string $name, mixed $value): void {
    if (array_key_exists($name, $this->options) === false) {
      throw new InvalidArgumentException("Invalid option \"{$name}\"");
    }

    $this->options[$name] = $value;
  }

  /**
   * @param array<string, mixed> $options
   */
  public function setOptions(array $options): void {
    foreach ($options as $name => $value) {
      $this->setOption($name, $value);
    }
  }
}
