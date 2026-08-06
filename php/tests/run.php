<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Melon\Baiwang\BaiwangClient;
use Melon\Baiwang\Contracts\CacheInterface;
use Melon\Baiwang\Contracts\HttpClientInterface;
use Melon\Baiwang\Exception\ApiException;
use Melon\Baiwang\HttpResponse;
use Melon\Baiwang\Method;
use Melon\Baiwang\Signer;

final class FakeHttpClient implements HttpClientInterface
{
    /** @var array<int, HttpResponse> */
    public $responses = array();

    /** @var array<int, array<string, mixed>> */
    public $requests = array();

    public function post(string $url, string $body, array $headers, int $timeout): HttpResponse
    {
        $this->requests[] = array(
            'url' => $url,
            'body' => json_decode($body, true),
            'headers' => $headers,
            'timeout' => $timeout,
        );
        if (count($this->responses) === 0) {
            throw new RuntimeException('No fake HTTP response was configured.');
        }
        return array_shift($this->responses);
    }
}

final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public $values = array();

    /** @var array<string, int> */
    public $ttls = array();

    public function get(string $key)
    {
        return isset($this->values[$key]) ? $this->values[$key] : null;
    }

    public function set(string $key, $value, int $ttl): void
    {
        $this->values[$key] = $value;
        $this->ttls[$key] = $ttl;
    }
}

/** @param mixed $actual @param mixed $expected */
function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

function assertTrueValue(bool $actual, string $message): void
{
    assertSameValue(true, $actual, $message);
}

/** @return array<string, mixed> */
function testConfig(): array
{
    return array(
        'username' => 'demo-user',
        'password' => 'demo-password',
        'appKey' => 'demo-app-key',
        'appSecret' => 'demo-app-secret',
        'secret' => 'demo-signing-secret',
        'outputTaxNumber' => '913500000000000000',
        'env' => 'test',
        'timeout' => 3456,
    );
}

$tests = array();

$tests['invoicing signs the exact request and unwraps data'] = function (): void {
    $http = new FakeHttpClient();
    $http->responses[] = new HttpResponse(200, '{"code":"0000","data":{"invoiceNo":"12345678"}}');
    $client = new BaiwangClient(testConfig(), $http);
    $result = $client->invoicing(array(
        'taxNo' => '913500000000000000',
        'invoiceTerminalCode' => 'dzpzd008',
        'data' => array('buyerName' => 'Test Company', 'invoiceTotalPrice' => 100),
    ));

    assertSameValue('12345678', $result['invoiceNo'], 'The response data should be unwrapped.');
    $request = $http->requests[0];
    assertSameValue(BaiwangClient::SANDBOX_ENDPOINT, $request['url'], 'test environment should use sandbox endpoint.');
    assertSameValue(3456, $request['timeout'], 'The configured timeout should be sent to HTTP client.');
    assertSameValue(Method::INVOICING, $request['body']['method'], 'The API method should be included.');
    assertSameValue('913500000000000000', $request['body']['orgId'], 'taxNo should map to orgId.');
    assertTrueValue(!isset($request['headers']['Authorization']), 'No Authorization header should be sent without a token.');

    $unsigned = $request['body'];
    $data = $unsigned['data'];
    unset($unsigned['data'], $unsigned['sign']);
    assertSameValue(Signer::sign($unsigned, 'demo-app-secret', $data), $request['body']['sign'], 'The request signature must cover sorted parameters and data.');
};

$tests['getToken updates config and writes cache'] = function (): void {
    $http = new FakeHttpClient();
    $cache = new ArrayCache();
    $http->responses[] = new HttpResponse(200, '{"code":"0000","data":{"access_token":"token-1","refresh_token":"refresh-1","expires_in":120}}');
    $client = new BaiwangClient(testConfig(), $http, $cache);
    $token = $client->getToken();

    assertSameValue('token-1', $token['access_token'], 'Token response should be returned.');
    assertSameValue('token-1', $client->getConfig()['access_token'], 'Access token should update client configuration.');
    assertSameValue('refresh-1', $client->getConfig()['_refreshTokenCache'], 'Refresh token should update client configuration.');
    assertSameValue(120, $cache->ttls['baiwang_access_token_demo-app-key'], 'API expiry should be used as cache TTL.');
    assertSameValue('demo-password', $http->requests[0]['body']['password'], 'Default token request should retain the original SDK password payload.');
};

$tests['authenticate uses a valid cached token without HTTP'] = function (): void {
    $http = new FakeHttpClient();
    $cache = new ArrayCache();
    $cache->values['baiwang_access_token_demo-app-key'] = array('access_token' => 'cached-token', 'refresh_token' => 'cached-refresh');
    $client = new BaiwangClient(testConfig(), $http, $cache);
    $token = $client->authenticate();

    assertSameValue('cached-token', $token['access_token'], 'Cached token should be returned.');
    assertSameValue(0, count($http->requests), 'Cached authentication must not make an HTTP request.');
    assertSameValue('cached-token', $client->getConfig()['access_token'], 'Cached token should update client configuration.');
};

$tests['autoSign refreshes then retries an expired request'] = function (): void {
    $http = new FakeHttpClient();
    $config = testConfig();
    $config['autoSign'] = true;
    $http->responses[] = new HttpResponse(200, '{"success":false,"errorResponse":{"code":100001,"msg":"expired"}}');
    $http->responses[] = new HttpResponse(200, '{"code":"0000","data":{"access_token":"renewed","refresh_token":"renewed-refresh"}}');
    $http->responses[] = new HttpResponse(200, '{"code":"0000","data":{"total":1}}');
    $client = new BaiwangClient($config, $http);
    $result = $client->queryInvoice(array('taxNo' => '913500000000000000', 'data' => array('invoiceNo' => '12345678')));

    assertSameValue(1, $result['total'], 'The retried request should return its response.');
    assertSameValue(3, count($http->requests), 'Expired request, token request and retry should be sent.');
    assertSameValue(Method::AUTH, $http->requests[1]['body']['method'], 'A token request should follow the expired request.');
    assertSameValue('renewed', $http->requests[2]['body']['token'], 'The retried request should use the renewed token.');
};

$tests['API errors expose the complete response'] = function (): void {
    $http = new FakeHttpClient();
    $http->responses[] = new HttpResponse(200, '{"code":"1001","msg":"Invalid parameters"}');
    $client = new BaiwangClient(testConfig(), $http);
    try {
        $client->voidInvoiced(array('taxNo' => '913500000000000000', 'data' => array()));
    } catch (ApiException $exception) {
        assertSameValue('1001', $exception->getResponse()['code'], 'The API exception should retain the API response.');
        return;
    }
    throw new RuntimeException('An API error must throw ApiException.');
};

$failures = array();
foreach ($tests as $name => $test) {
    try {
        $test();
        fwrite(STDOUT, "[PASS] " . $name . PHP_EOL);
    } catch (Throwable $exception) {
        $failures[] = $name . ': ' . $exception->getMessage();
        fwrite(STDERR, "[FAIL] " . $name . ': ' . $exception->getMessage() . PHP_EOL);
    }
}

if (count($failures) > 0) {
    exit(1);
}
