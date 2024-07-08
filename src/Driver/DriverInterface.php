<?php
declare(strict_types = 1);

namespace Alahaka\Driver;

use Psr\Http\Client\ClientInterface;

interface DriverInterface extends ClientInterface {
  public function getOption(string $name): mixed;
  public function setOption(string $name, mixed $value): void;
  /**
   * @param array<string, mixed> $options
   */
  public function setOptions(array $options): void;
}
