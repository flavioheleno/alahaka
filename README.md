# alahaka

A minimal [PSR-18](https://www.php-fig.org/psr/psr-18/) compliant HTTP Client

## Installation

To use **alahaka**, install it using composer:

```shell
composer require flavioheleno/alahaka
```

You also need to install a [PSR-17](https://www.php-fig.org/psr/psr-17/) compliant factory to create
[PSR-7](https://www.php-fig.org/psr/psr-7/) compliant requests and responses.

You can pick one from [this list](https://packagist.org/providers/psr/http-factory-implementation).

Example:

```shell
composer require nyholm/psr7
```

## License

This library is licensed under the [MIT License](LICENSE).
