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
    const WAIT_INTERVAL = 10;

    /** @var \GuzzleHttp\Client  */
    protected $client;

    protected $username;
    protected $password;
    protected $authSst;
    protected $authTt;

    /**
     * @param string $url Base url of GoodData API if not the default one
     */
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
                RetrySubscriber::createCurlFilter(),
                RetrySubscriber::createStatusFilter([500, 502, 504, 509]),
            ])
        ]);
        $this->client->getEmitter()->attach($retry);
    }



    /******************************************
     * @section Users
     *****************************************/

    /**
     * Gets user info from API
     * @param $uid
     * @return array
     */
    public function getUser($uid)
    {
        return $this->request('GET', "/gdc/account/profile/{$uid}");
    }

    /**
     * Gets info about currently logged-in user from API
     * @return array
     */
    public function getCurrentUser()
    {
        return $this->getUser('current');
    }

    /**
     * Gets user id of currently logged-in user
     * @return string
     */
    public function getCurrentUserId()
    {
        $result = $this->getCurrentUser();
        return self::getIdFromUri($result['accountSetting']['links']['self']);
    }

    /**
     * Creates user in domain
     * @param $domain
     * @param $email
     * @param $password
     * @param $firstName
     * @param $lastName
     * @param null $ssoProvider
     * @return string
     * @throws Exception
     */
    public function createUser($domain, $email, $password, $firstName, $lastName, $ssoProvider = null)
    {
        $uri = sprintf('/gdc/account/domains/%s/users', $domain);
        $params = [
            'accountSetting' => [
                'login' => strtolower($email),
                'email' => strtolower($email),
                'password' => $password,
                'verifyPassword' => $password,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'ssoProvider' => $ssoProvider
            ],
        ];

        try {
            $result = $this->request('POST', $uri, $params);
            if (!isset($result['uri'])) {
                throw new Exception("User '{$email}' cannot be created, result does not contain 'uri'", 0, null, $result);
            }
            return self::getIdFromUri($result['uri']);
        } catch (Exception $e) {
            $response = $e->getResponse();
            if (isset($response['error']['errorClass']) && strpos($response['error']['errorClass'], 'LoginNameAlreadyRegisteredException') !== false) {
                throw new Exception("User '{$email}' cannot be created, it already exists in another domain", $e->getCode(), $e, $response);
            } else {
                $error = isset($details['error']['message']) ? $details['error']['message'] : $e->getMessage();
                throw new Exception("User '{$email}' cannot be created: {$error}", $e->getCode(), $e, $response);
            }
        }
    }

    /**
     * Updates user info
     * @param $uid
     * @param $data
     * @return array
     */
    public function updateUser($uid, array $data)
    {
        $userData = $this->getUser($uid);
        unset($userData['accountSetting']['login']);
        unset($userData['accountSetting']['email']);
        $userData['accountSetting'] = array_merge($userData['accountSetting'], $data);
        return $this->request('PUT', '/gdc/account/profile/'.$uid, $userData);
    }

    /**
     * Retrieves user id from email
     * @param $email
     * @param $domain
     * @return bool|string
     */
    public function retrieveUserId($email, $domain)
    {
        $result = $this->request('GET', "/gdc/account/domains/{$domain}/users?login={$email}");
        if (!empty($result['accountSettings']['items'])
            && count($result['accountSettings']['items'])
            && !empty($result['accountSettings']['items'][0]['accountSetting']['links']['self'])
        ) {
            return self::getIdFromUri($result['accountSettings']['items'][0]['accountSetting']['links']['self']);
        }
        return false;
    }

    /**
     * Drops user
     * @param $uid
     * @return string
     */
    public function deleteUser($uid)
    {
        return $this->request('DELETE', '/gdc/account/profile/' . $uid);
    }



    /******************************************
     * @section Projects
     *****************************************/

    /**
     * Create project
     * @param $name
     * @param $authToken
     * @param null $description
     * @return string
     * @throws Exception
     */
    public function createProject($name, $authToken, $description = null)
    {
        $params = [
            'project' => [
                'content' => [
                    'guidedNavigation' => 1,
                    'driver' => 'Pg',
                    'authorizationToken' => $authToken
                ],
                'meta' => [
                    'title' => $name
                ]
            ]
        ];
        if ($description) {
            $params['project']['meta']['summary'] = $description;
        }
        $result = $this->request('POST', '/gdc/projects', $params);

        if (empty($result['uri'])) {
            throw new Exception("Create project call failed", 0, null, $result);
        }

        $projectUri = $result['uri'];

        // Wait until project is ready
        $repeat = true;
        $i = 1;
        do {
            sleep(self::WAIT_INTERVAL * ($i + 1));

            $result = $this->request('GET', $projectUri);
            if (isset($result['project']['content']['state']) && $result['project']['content']['state'] != 'DELETED') {
                if ($result['project']['content']['state'] == 'ENABLED') {
                    $repeat = false;
                }
            } else {
                throw new Exception("Get project uri {$projectUri} after create project call failed", 0, null, $result);
            }

            $i++;
        } while ($repeat);

        return self::getIdFromUri($projectUri);
    }

    /**
     * Get project info
     *
     * @param $pid
     * @throws Exception
     * @return array
     */
    public function getProject($pid)
    {
        return $this->request('GET', "/gdc/projects/{$pid}");
    }

    /**
     * Clones project from other project
     * @param $sourcePid
     * @param $targetPid
     * @param $includeData
     * @param $includeUsers
     * @throws Exception
     * @return bool
     */
    public function cloneProject($sourcePid, $targetPid, $includeData = false, $includeUsers = false)
    {
        $params = [
            'exportProject' => [
                'exportUsers' => $includeUsers,
                'exportData' => $includeData
            ]
        ];
        $result = $this->request('POST', "/gdc/md/{$sourcePid}/maintenance/export", $params);
        if (empty($result['exportArtifact']['token']) || empty($result['exportArtifact']['status']['uri'])) {
            throw new Exception('Clone export failed', 0, null, $result);
        }

        $this->pollTask($result['exportArtifact']['status']['uri']);

        $result = $this->request('POST', "/gdc/md/{$targetPid}/maintenance/import", [
            'importProject' => [
                'token' => $result['exportArtifact']['token']
            ]
        ]);
        if (empty($result['uri'])) {
            throw new Exception('Clone import failed', 0, null, $result);
        }

        $this->pollTask($result['uri']);
    }

    /**
     * Deletes project
     * @param $pid
     * @return array
     */
    public function deleteProject($pid)
    {
        return $this->request('DELETE', "/gdc/projects/{$pid}");
    }



    /******************************************
     * @section Common methods
     *****************************************/

    /**
     * Process user login
     * @param $username
     * @param $password
     * @throws Exception
     */
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
            $this->authSst = self::findCookie($response->getHeader('Set-Cookie', true), 'GDCAuthSST');
        }
        if (!$this->authSst) {
            throw new Exception('GoodData API login failed');
        }

        $this->refreshToken();
    }

    /**
     * Poll task uri and wait for its finish
     * @param $uri
     * @throws Exception
     */
    public function pollTask($uri)
    {
        $repeat = true;
        $i = 0;
        do {
            sleep(self::WAIT_INTERVAL * ($i + 1));

            $result = $this->request('GET', $uri);
            if (isset($result['taskState']['status'])) {
                if (in_array($result['taskState']['status'], ['OK', 'ERROR', 'WARNING'])) {
                    $repeat = false;
                }
            } else {
                throw new Exception('Bad response', 0, null, $result);
            }

            $i++;
        } while ($repeat);

        if ($result['taskState']['status'] != 'OK') {
            throw new Exception('Bad response', 0, null, $result);
        }
    }

    /**
     * Check if API is available and not under maintenance
     * @return bool
     */
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

    /**
     * Extracts user or project id from it's uri
     * @param $uri
     * @return string
     */
    public static function getIdFromUri($uri)
    {
        return substr($uri, strrpos($uri, '/')+1);
    }

    /**
     * @param $method
     * @param $uri
     * @param array $params
     * @param bool|true $refreshToken
     * @return array
     * @throws Exception
     */
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
                //@TODO handle exceptions
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
            $this->authTt = self::findCookie($response->getHeader('Set-Cookie', true), 'GDCAuthTT');
            return $this->authTt;
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
