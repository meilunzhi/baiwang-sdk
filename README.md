# Baiwang PHP SDK

PHP 7.4 compatible SDK for Baiwang OpenAPI invoice services. The PHP implementation is exposed as a Composer package from `php/src`; the existing TypeScript source is retained only as a protocol reference.

## Requirements

- PHP 7.4
- `ext-curl`
- `ext-json`

## Installation

Install from a published package:

```bash
composer require Melon/baiwang-sdk
```

For this repository, generate the local autoloader first:

```bash
composer dump-autoload
```

## Basic usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Melon\Baiwang\BaiwangClient;

$baiwang = new BaiwangClient(array(
    'username' => 'xxx',
    'password' => 'xxx',
    'appKey' => 'xxx',
    'appSecret' => 'xxx',
    'secret' => 'xxx',
    'salesAccount' => 'xxx',
    'salesPassword' => 'xxx',
    'outputTaxNumber' => '913500000000000000',
    'invoicingPoint' => 'dzpzd008',
    'env' => 'test', // test/sandbox or prod
    'autoSign' => true,
    'timeout' => 10000,
    'maxRetries' => 1,
));

$token = $baiwang->authenticate();

$invoice = $baiwang->invoicing(array(
    'taxNo' => '913500000000000000',
    'invoiceTerminalCode' => 'dzpzd008',
    'formatGenerate' => true,
    'data' => array(
        'buyerName' => 'Example Company',
        'buyerTaxNo' => '91500000747150890S',
        'invoiceType' => '0',
        'invoiceTypeCode' => '026',
        'invoiceTotalPrice' => 100.00,
        'invoiceTotalTax' => 13.00,
        'invoiceTotalPriceTax' => 113.00,
        'invoiceDetailsList' => array(
            array(
                'goodsName' => 'Service fee',
                'goodsPrice' => 100.00,
                'goodsQuantity' => 1,
                'goodsTaxRate' => 0.13,
                'goodsTotalPrice' => 100.00,
                'goodsTotalTax' => 13.00,
            ),
        ),
    ),
));
```

The equivalent factory entry point is also available:

```php
$baiwang = Melon\Baiwang\createInvoice($config);
```

## API methods


| PHP method                  | Baiwang method                     |
| --------------------------- | ---------------------------------- |
| `getToken()`                | `baiwang.oauth.token`              |
| `refreshToken()`            | `baiwang.oauth.token`              |
| `authenticate()`            | Reads cache or obtains a token     |
| `invoicing($params)`        | `baiwang.output.invoice.issue`     |
| `queryInvoice($params)`     | `baiwang.output.invoice.query`     |
| `queryLayout($params)`      | `baiwang.output.format.query`      |
| `queryCloudHeadUp($params)` | `baiwang.bizinfo.companySearch`    |
| `preInvoice($params)`       | `baiwang.output.preinvoice.issue`  |
| `voidInvoiced($params)`     | `baiwang.output.invoice.cancel`    |
| `issueRedLetter($params)`   | `baiwang.output.redinvoice.issued` |
| `request($method, $params)` | Any supported OpenAPI method       |

All business methods take an associative PHP array. Use `taxNo` or `taxId` to populate `orgId`; a configured `orgId` takes precedence. Successful API envelopes return their `data` value directly. API errors throw `Melon\Baiwang\Exception\ApiException`, whose `getResponse()` method returns the unmodified API response.

## Cache adapter

Pass an implementation of `Melon\Baiwang\Contracts\CacheInterface` as the constructor's third parameter or in `cacheProvider`. `authenticate()` stores `access_token` and `refresh_token` at `baiwang_access_token_{appKey}`, using the API's `expires_in` as the TTL.

```php
use Melon\Baiwang\Contracts\CacheInterface;

final class AppCache implements CacheInterface
{
    public function get(string $key)
    {
        return null; // return the stored token array when available
    }

    public function set(string $key, $value, int $ttl): void
    {
        // Store $value for $ttl seconds.
    }
}
```

## HTTP adapter and testing

The default transport is cURL. To supply a framework HTTP client or mock requests, implement `Melon\Baiwang\Contracts\HttpClientInterface` and return an `HttpResponse` from `post()`.

```bash
composer test
composer lint
```
