<?php
declare(strict_types = 1);

namespace Alahaka\Driver;

use Alahaka\Exception\NetworkException;
use Alahaka\Exception\RequestException;
use CurlHandle;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class CurlDriver extends AbstractDriver {
  protected function getUserAgent(): string {
    $curl = curl_version();

    return sprintf(
      'alahaka/%d (curl/%s; PHP/%s; %s)',
      '0.0.1a',
      $curl['version'],
      PHP_VERSION,
      PHP_OS_FAMILY
    );
  }

  public function sendRequest(RequestInterface $request): ResponseInterface {
    $curlOpts = [
      CURLOPT_AUTOREFERER => true,
      CURLOPT_CERTINFO => true,
      CURLOPT_CONNECTTIMEOUT => 30, //$connectTimeout,
      CURLOPT_COOKIEFILE => '',
      CURLOPT_COOKIELIST => 'RELOAD',
      CURLOPT_COOKIESESSION => true,
      CURLOPT_DNS_SHUFFLE_ADDRESSES => true,
      CURLOPT_DNS_USE_GLOBAL_CACHE => false,
      CURLOPT_ENCODING => '',
      CURLOPT_FAILONERROR => false,
      CURLOPT_FILETIME => true,
      CURLOPT_FOLLOWLOCATION => $this->options['follow_location'] === 1,
      CURLOPT_FORBID_REUSE => true,
      CURLOPT_FRESH_CONNECT => true,
      CURLOPT_HEADER => false,
      CURLOPT_HTTP_CONTENT_DECODING => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TCP_FASTOPEN => true,
      CURLOPT_TCP_NODELAY => true,
      CURLOPT_TIMEOUT => (int)$this->options['read_timeout'],
      CURLOPT_USERAGENT => $this->getUserAgent()
    ];

    $curlOpts[CURLOPT_NOBODY] = strtoupper($request->getMethod()) === 'HEAD';
    $curlOpts[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
    $curlOpts[CURLOPT_HTTPHEADER] = $this->formatHeaders($request->getHeaders());

    $body = $request->getBody();
    if ($body->isReadable() === true) {
      if ($body->isSeekable()) {
        $body->rewind();
      }

      $curlOpts[CURLOPT_POSTFIELDS] = $body->getContents();
    }

    $responseHeaders = [];
    $responseProperties = [];
    $curlOpts[CURLOPT_HEADERFUNCTION] = static function (
      CurlHandle $hCurl,
      string $data
    ) use (&$responseProperties, &$responseHeaders): int {
      $split = strpos($data, ':');
      if ($split === false) {
        if (preg_match('/^HTTP\/(?<version>0\.9|1\.0|1\.1|2|3) (?<code>[0-9]{3})/', $data, $matches) === 1) {
          $responseProperties['protocolVersion'] = $matches['version'];
          $responseProperties['statusCode'] = (int)$matches['code'];
        }

        return strlen($data);
      }

      $name = strtolower(trim(substr($data, 0, $split)));
      $value = trim(substr($data, $split + 1));
      if (array_key_exists($name, $responseHeaders) === false) {
        $responseHeaders[$name] = [];
      }

      $responseHeaders[$name][] = $value;

      return strlen($data);
    };

    /** @var \Psr\Http\Message\StreamInterface */
    $bodyStream = $this->streamFactory->createStreamFromResource(fopen('php://temp', 'w+b'));
    $curlOpts[CURLOPT_WRITEFUNCTION] = static function (CurlHandle $hCurl, string $data) use ($bodyStream): int {
      return $bodyStream->write($data);
    };

    $hCurl = curl_init((string)$request->getUri());
    if (curl_setopt_array($hCurl, $curlOpts) === false) {
      throw new RuntimeException();
    }

    curl_exec($hCurl);
    $curlErrNo = curl_errno($hCurl);
    if ($curlErrNo > 0) {
      $curlError = curl_error($hCurl);
      curl_close($hCurl);

      switch ($curlErrNo) {
        case CURLE_URL_MALFORMAT:
        // case CURLE_RANGE_ERROR:
        case CURLE_BAD_DOWNLOAD_RESUME:
        case CURLE_FILE_COULDNT_READ_FILE:
        case CURLE_TOO_MANY_REDIRECTS:
          throw new RequestException(
            $curlError,
            $curlErrNo,
            $request
          );

        case CURLE_COULDNT_RESOLVE_PROXY:
        case CURLE_COULDNT_RESOLVE_HOST:
        case CURLE_COULDNT_CONNECT:
        case CURLE_HTTP2:
        case CURLE_PARTIAL_FILE:
        case CURLE_WRITE_ERROR:
        case CURLE_UPLOAD_FAILED:
        case CURLE_READ_ERROR:
        case CURLE_OPERATION_TIMEDOUT:
        case CURLE_INTERFACE_FAILED:
        case CURLE_SEND_ERROR:
        case CURLE_RECV_ERROR:
        case CURLE_HTTP3:
        case CURLE_QUIC_CONNECT_ERROR:
        case CURLE_PROXY:
          throw new NetworkException(
            $curlError,
            $curlErrNo,
            $request
          );

        // case CURLE_UNSUPPORTED_PROTOCOL:
        // case CURLE_FAILED_INIT:
        // case CURLE_NOT_BUILT_IN:
        // case CURLE_WEIRD_SERVER_REPLY:
        // case CURLE_OUT_OF_MEMORY:
        // case CURLE_HTTP_POST_ERROR:
        // case CURLE_GOT_NOTHING:
        // case CURLE_SSL_ENGINE_NOTFOUND:
        // case CURLE_SSL_ENGINE_SETFAILED:
        // case CURLE_SSL_CERTPROBLEM:
        // case CURLE_SSL_CIPHER:
        // case CURLE_PEER_FAILED_VERIFICATION:
        // case CURLE_BAD_CONTENT_ENCODING:
        // case CURLE_FILESIZE_EXCEEDED:
        // case CURLE_SEND_FAIL_REWIND:
        default:
          throw new RuntimeException($curlError, $curlErrNo);
      }
    }

    curl_close($hCurl);

    $bodyStream->rewind();

    /** @var Psr\Http\Message\ResponseInterface */
    $response = $this->responseFactory->createResponse($responseProperties['statusCode']);
    $response = $response
      ->withProtocolVersion($responseProperties['protocolVersion'])
      ->withBody($bodyStream);

    foreach ($responseHeaders as $name => $value) {
      $response = $response->withHeader($name, $value);
    }

    return $response;
  }
}
