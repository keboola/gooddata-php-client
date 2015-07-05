<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Subscriber\Retry\RetrySubscriber;

class Client
{
    const BASE_URL = 'https://secure.gooddata.com';

    /** @var \GuzzleHttp\Client  */
    protected $client;

    protected $username;
    protected $password;
    protected $authSst;
    protected $authTt;

    public function __construct($url = null)
    {
        $this->client = new \GuzzleHttp\Client([
            'base_url' => $url ?: self::BASE_URL,
            'defaults' => [
                'timeout' => 600,
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json; charset=utf-8'
                ]
            ]
        ]);
        $retry = new RetrySubscriber([
            'filter' => RetrySubscriber::createChainFilter([
                RetrySubscriber::createConnectFilter(),
                RetrySubscriber::createStatusFilter([500, 502, 504, 509]),
                RetrySubscriber::createCurlFilter()
            ])
        ]);
        $this->client->getEmitter()->attach($retry);
    }

    public static function getUserIdFromUri($uri)
    {
        return substr($uri, strrpos($uri, '/')+1);
    }

    public function getUser($uid)
    {
        return $this->request('GET', "/gdc/account/profile/{$uid}");
    }

    public function getCurrentUser()
    {
        return $this->getUser('current');
    }

    public function getCurrentUserId()
    {
        $result = $this->getCurrentUser();
        return self::getUserIdFromUri($result['accountSetting']['links']['self']);
    }

    public function login($username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        if (!$this->username || !$this->password) {
            throw new Exception('GoodData API login failed, missing username or password');
        }

        try {
            $response = $this->rawRequest('POST', '/gdc/account/login', [
                'postUserLogin' => [
                    'login' => $username,
                    'password' => $password,
                    'remember' => 0
                ]
            ]);
        } catch (Exception $e) {
            throw new Exception('GoodData API Login failed', $e);
        }

        if ($response) {
            $this->authSst = self::findCookie($response->getHeaderAsArray('Set-Cookie'), 'GDCAuthSST');
        }
        if (!$this->authSst) {
            throw new Exception('GoodData API login failed');
        }

        $this->refreshToken();
    }

    public function ping()
    {
        $errorCount = 0;
        $request = $this->client->createRequest('GET', '/gdc/ping');
        do {
            try {
                $response = $this->client->send($request);
                return $response->getStatusCode() != 503;
            } catch (ServerException $e) {
                return false;
            } catch (TransferException $e) {
                $errorCount++;
                sleep(rand(1, 5));
            }
        } while ($errorCount <= 5);
        return false;
    }

    private function request($method, $uri, $params = [], $refreshToken = true)
    {
        if (!$this->authTt && $refreshToken) {
            $this->refreshToken();
        }

        $result = $this->rawRequest($method, $uri, $params);
        return $result->json();
    }

    private function rawRequest($method, $uri, array $params = [])
    {
        $params = count($params) ? json_encode($params) : null;
        $lastException = null;
        do {
            $request = $this->client->createRequest($method, $uri, [
                'body' => $params,
                'cookies' => [
                    'GDCAuthSST' => $this->authSst,
                    'GDCAuthTT' => $this->authTt
                ]
            ]);
            $isMaintenance = false;

            try {
                return $this->client->send($request);

            } catch (RequestException $e) {
                $lastException = $e;
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
                echo $e->getRequest().PHP_EOL.PHP_EOL;
                echo $e->getResponse().PHP_EOL.PHP_EOL;
                
            }

            if ($isMaintenance) {
                sleep(rand(60, 600));
            }

        } while ($isMaintenance);

        $statusCode = $lastException ? $lastException->getResponse()->getStatusCode() : null;
        $error = $lastException ? $lastException->getResponse()->getBody() : 'API Error';
        throw new Exception($error, $statusCode, $lastException);
    }

    public function refreshToken()
    {
        try {
            $response = $this->rawRequest('GET', '/gdc/account/token', []);
        } catch (Exception $e) {
            throw new Exception('Refresh token failed', $e);
        }

        if ($response) {
            $this->authTt = self::findCookie($response->getHeaderAsArray('Set-Cookie'), 'GDCAuthTT');
        }
        if (!$this->authTt) {
            throw new Exception('Refresh token failed');
        }
    }

    /**
     * Utility function: Retrieves specified cookie from supplied response headers
     * NB: Very basic parsing - ignores path, domain, expiry
     *
     * @param array $cookies
     * @param $name
     * @return string or null if specified cookie not found
     * @author Jakub Nesetril
     */
    protected static function findCookie(array $cookies, $name)
    {
        $cookie = array_filter($cookies, function ($cookie) use ($name) {
            return strpos($cookie, $name) === 0;
        });
        $cookie = reset($cookie);
        if (empty($cookie)) {
            return false;
        }

        $cookie = explode('; ', $cookie);
        $cookie = reset($cookie);
        return substr($cookie, strpos($cookie, '=') + 1);
    }
}
