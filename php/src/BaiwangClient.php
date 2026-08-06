<?php

namespace Melon\Baiwang;

use InvalidArgumentException;
use Melon\Baiwang\Contracts\CacheInterface;
use Melon\Baiwang\Contracts\HttpClientInterface;
use Melon\Baiwang\Exception\ApiException;
use Melon\Baiwang\Exception\HttpException;
use Melon\Baiwang\Http\CurlHttpClient;

final class BaiwangClient
{
    public const PRODUCTION_ENDPOINT = 'https://openapi.baiwang.com/router/rest';
    public const SANDBOX_ENDPOINT = 'https://sandbox-openapi.baiwang.com/router/rest';

    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var CacheInterface|null */
    private $cache;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, ?HttpClientInterface $httpClient = null, ?CacheInterface $cache = null)
    {
        foreach (array('username', 'password', 'appKey', 'appSecret', 'secret') as $requiredKey) {
            if (!isset($config[$requiredKey]) || $config[$requiredKey] === '') {
                throw new InvalidArgumentException(sprintf('Baiwang config "%s" is required.', $requiredKey));
            }
        }

        $env = isset($config['env']) && $config['env'] === 'prod' ? 'prod' : 'sandbox';
        $timeout = isset($config['timeout']) ? (int) $config['timeout'] : 10000;
        if ($timeout < 1) {
            throw new InvalidArgumentException('Baiwang config "timeout" must be greater than zero.');
        }

        $this->config = $config;
        $this->config['env'] = $env;
        $this->config['endpoint'] = isset($config['endpoint']) && $config['endpoint'] !== ''
            ? $config['endpoint']
            : ($env === 'prod' ? self::PRODUCTION_ENDPOINT : self::SANDBOX_ENDPOINT);
        $this->config['timeout'] = $timeout;
        $this->config['autoSign'] = !empty($config['autoSign']);
        $this->config['maxRetries'] = isset($config['maxRetries']) ? max(0, (int) $config['maxRetries']) : 0;

        $configuredHttpClient = isset($config['httpClient']) ? $config['httpClient'] : null;
        if ($httpClient === null && $configuredHttpClient instanceof HttpClientInterface) {
            $httpClient = $configuredHttpClient;
        }
        $this->httpClient = $httpClient ?: new CurlHttpClient();

        $configuredCache = isset($config['cacheProvider']) ? $config['cacheProvider'] : (isset($config['cache']) ? $config['cache'] : null);
        if ($cache === null && $configuredCache instanceof CacheInterface) {
            $cache = $configuredCache;
        }
        $this->cache = $cache;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function setAccessToken(string $accessToken, ?string $refreshToken = null): void
    {
        $this->config['access_token'] = $accessToken;
        if ($refreshToken !== null) {
            $this->config['_refreshTokenCache'] = $refreshToken;
        }
    }

    /**
     * Send any Baiwang API method.
     *
     * @param string $method
     * @param array<string, mixed> $params
     * @return mixed
     */
    public function execute(string $method, array $params = array())
    {
        $rawResponse = $this->send($method, $params);

        if ($method !== Method::AUTH && !empty($this->config['autoSign']) && $this->isTokenExpiredResponse($rawResponse)) {
            $this->renewAccessToken();
            $rawResponse = $this->send($method, $params);
        }

        return $this->transformResponse($rawResponse);
    }

    /**
     * Alias for execute(), useful when the required API method is not yet wrapped by this SDK.
     *
     * @param string $method
     * @param array<string, mixed> $params
     * @return mixed
     */
    public function request(string $method, array $params = array())
    {
        return $this->execute($method, $params);
    }

    /**
     * @param array<string, mixed>|null $params
     * @return mixed
     */
    public function getToken(?array $params = null)
    {
        if ($params === null) {
            $params = array(
                'username' => $this->config['username'],
                'client_secret' => $this->config['appSecret'],
                'password' => $this->config['password'],
            );
        }

        $token = $this->execute(Method::AUTH, $params);
        if (is_array($token)) {
            $this->rememberToken($token);
        }
        return $token;
    }

    /**
     * @param array<string, mixed>|null $params
     * @return mixed
     */
    public function refreshToken(?array $params = null)
    {
        if ($params === null) {
            if (empty($this->config['_refreshTokenCache'])) {
                throw new InvalidArgumentException('No refresh token is available. Call authenticate() or setAccessToken() first.');
            }
            $params = array(
                'client_secret' => $this->config['appSecret'],
                'refresh_token' => $this->config['_refreshTokenCache'],
            );
        }

        $token = $this->execute(Method::AUTH, $params);
        if (is_array($token)) {
            $this->rememberToken($token);
        }
        return $token;
    }

    /**
     * Load a cached token or obtain and cache a new one.
     *
     * @return array<string, mixed>
     */
    public function authenticate(): array
    {
        if ($this->cache !== null) {
            $cached = $this->cache->get($this->tokenCacheKey());
            if (is_array($cached) && !empty($cached['access_token'])) {
                $this->rememberToken($cached, false);
                return $cached;
            }
        }

        $token = $this->getToken();
        if (!is_array($token) || empty($token['access_token'])) {
            throw new ApiException('Baiwang token response does not contain access_token.', is_array($token) ? $token : array());
        }
        return $token;
    }

    /** @param array<string, mixed> $params @return mixed */
    public function invoicing(array $params)
    {
        return $this->execute(Method::INVOICING, $params);
    }

    /** @param array<string, mixed> $params @return mixed */
    public function queryInvoice(array $params)
    {
        return $this->execute(Method::INVOICE_QUERY, $params);
    }

    /** @param array<string, mixed> $params @return mixed */
    public function queryLayout(array $params)
    {
        return $this->execute(Method::LAYOUT_QUERY, $params);
    }

    /** @param array<string, mixed> $params @return mixed */
    public function queryCloudHeadUp(array $params)
    {
        return $this->execute(Method::CLOUD_HEAD_UP, $params);
    }

    /** @param array<string, mixed> $params @return mixed */
    public function preInvoice(array $params)
    {
        return $this->execute(Method::PRE_INVOICE, $params);
    }

    /** @param array<string, mixed> $params @return mixed */
    public function voidInvoiced(array $params)
    {
        return $this->execute(Method::VOID_INVOICED, $params);
    }

    /** @param array<string, mixed> $params @return mixed */
    public function issueRedLetter(array $params)
    {
        return $this->execute(Method::RED_LETTER_ISSUANCE, $params);
    }

    /**
     * @param string $method
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function send(string $method, array $params): array
    {
        $businessContent = array_key_exists('data', $params) ? $params['data'] : null;
        $otherParams = $params;
        unset($otherParams['data'], $otherParams['taxNo'], $otherParams['taxId']);

        $request = array_merge(array(
            'method' => $method,
            'token' => isset($this->config['access_token']) ? $this->config['access_token'] : '',
            'format' => 'json',
            'type' => 'sync',
            'timestamp' => (string) floor(microtime(true) * 1000),
            'appKey' => $this->config['appKey'],
            'version' => '6.0',
        ), $otherParams);

        $request['orgId'] = $this->organizationId($params);
        $request['sign'] = Signer::sign($request, $this->config['appSecret'], $businessContent);
        if ($businessContent !== null) {
            $request['data'] = $businessContent;
        }

        $headers = array('Content-Type' => 'application/json');
        if (!empty($this->config['access_token'])) {
            $headers['Authorization'] = 'Bearer ' . $this->config['access_token'];
        }

        $attempt = 0;
        do {
            try {
                $response = $this->httpClient->post(
                    $this->config['endpoint'],
                    Signer::jsonEncode($request),
                    $headers,
                    $this->config['timeout']
                );
                $statusCode = $response->getStatusCode();
                if ($statusCode >= 500) {
                    throw new HttpException('Baiwang server returned HTTP ' . $statusCode . '.', $statusCode, $response->getBody());
                }

                $decoded = json_decode($response->getBody(), true);
                if (!is_array($decoded)) {
                    throw new HttpException('Baiwang returned an invalid JSON response.', $statusCode, $response->getBody());
                }
                return $decoded;
            } catch (HttpException $exception) {
                if ($attempt >= $this->config['maxRetries']) {
                    throw $exception;
                }
                $attempt++;
            }
        } while (true);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function organizationId(array $params)
    {
        if (!empty($this->config['orgId'])) {
            return $this->config['orgId'];
        }
        if (!empty($params['taxNo'])) {
            return $params['taxNo'];
        }
        return isset($params['taxId']) ? $params['taxId'] : null;
    }

    /**
     * @param array<string, mixed> $response
     * @return mixed
     */
    private function transformResponse(array $response)
    {
        if (isset($response['code']) && (string) $response['code'] !== '0000' && (string) $response['code'] !== '0') {
            $message = isset($response['msg']) ? (string) $response['msg'] : 'Baiwang API error';
            throw new ApiException($message, $response);
        }
        if (isset($response['success']) && $response['success'] === false) {
            $error = isset($response['errorResponse']) && is_array($response['errorResponse']) ? $response['errorResponse'] : $response;
            $message = isset($error['msg']) ? (string) $error['msg'] : 'Baiwang API error';
            throw new ApiException($message, $response);
        }
        if (array_key_exists('data', $response) && $response['data'] !== null) {
            return $response['data'];
        }
        if (isset($response['success']) && $response['success'] === true && array_key_exists('response', $response)) {
            return $response['response'];
        }
        return $response;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function isTokenExpiredResponse(array $response): bool
    {
        $code = isset($response['code']) ? (string) $response['code'] : '';
        if ($code === '100001' || $code === '100002' || $code === '1006') {
            return true;
        }
        if (isset($response['errorResponse']) && is_array($response['errorResponse'])) {
            $errorCode = isset($response['errorResponse']['code']) ? (string) $response['errorResponse']['code'] : '';
            return $errorCode === '100001' || $errorCode === '100002' || $errorCode === '1006';
        }
        return false;
    }

    private function renewAccessToken(): void
    {
        if (!empty($this->config['_refreshTokenCache'])) {
            try {
                $this->refreshToken();
                return;
            } catch (ApiException $exception) {
                // A rejected refresh token falls back to a new authentication request.
            }
        }
        $this->getToken();
    }

    /**
     * @param array<string, mixed> $token
     */
    private function rememberToken(array $token, bool $writeCache = true): void
    {
        if (empty($token['access_token'])) {
            return;
        }
        $this->setAccessToken(
            (string) $token['access_token'],
            isset($token['refresh_token']) ? (string) $token['refresh_token'] : null
        );

        if ($writeCache && $this->cache !== null) {
            $expiresIn = isset($token['expires_in']) ? (int) $token['expires_in'] : 7200;
            $this->cache->set($this->tokenCacheKey(), $token, max(1, $expiresIn));
        }
    }

    private function tokenCacheKey(): string
    {
        return 'baiwang_access_token_' . $this->config['appKey'];
    }
}
