<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Client
{
    /**
     * Number of retries for one API call
     */
    const RETRIES_COUNT = 5;
    /**
     * Back off time before retrying API call
     */
    const BACKOFF_INTERVAL = 1;
    /**
     * Back off time before polling of async tasks
     */
    const WAIT_INTERVAL = 10;

    const API_URL = 'https://secure.gooddata.com';

    const DEFAULT_CLIENT_SETTINGS = [
        'timeout' => 600,
        'headers' => [
            'accept' => 'application/json',
            'content-type' => 'application/json; charset=utf-8'
        ]
    ];

    /** @var  \GuzzleHttp\Client */
    protected $guzzle;
    protected $apiUrl;
    /** @var  LoggerInterface */
    protected $logger;
    /** @var null MessageFormatter */
    protected $loggerFormatter;
    /** @var  array */
    protected $logData = [];

    protected $username;
    protected $password;

    /** @var  Datasets */
    protected $datasets;
    /** @var  DateDimensions */
    protected $dateDimensions;
    /** @var  Filters */
    protected $filters;
    /** @var  ProjectModel */
    protected $projectModel;
    /** @var  Projects */
    protected $projects;
    /** @var  Reports */
    protected $reports;
    /** @var  Users */
    protected $users;

    public function __construct($url = null, $logger = null, $loggerFormatter = null)
    {
        $this->apiUrl = $url ?: self::API_URL;
        if ($logger) {
            $this->logger = $logger;
        }
        $this->loggerFormatter = $loggerFormatter ?: new MessageFormatter("{hostname} {req_header_User-Agent} - [{ts}] "
            . "\"{method} {resource} {protocol}/{version}\" {code} {res_header_Content-Length}");
        $this->initClient();
    }

    protected function initClient()
    {
        $handlerStack = HandlerStack::create();

        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                return $response && $response->getStatusCode() == 503;
            },
            function ($retries) {
                return rand(60, 600) * 1000;
            }
        ));
        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                if ($retries >= self::RETRIES_COUNT) {
                    return false;
                } elseif ($response && $response->getStatusCode() > 499) {
                    return true;
                } elseif ($error) {
                    return true;
                } else {
                    return false;
                }
            },
            function ($retries) {
                return (int) pow(2, $retries - 1) * 1000;
            }
        ));

        $handlerStack->push(Middleware::cookies());
        if ($this->logger) {
            $handlerStack->push(Middleware::log($this->logger, $this->loggerFormatter));
        }
        $this->guzzle = new \GuzzleHttp\Client([
            'base_uri' => $this->apiUrl,
            'handler' => $handlerStack,
            'cookies' => true
        ]);
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setLogData($data)
    {
        $this->logData = $data;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setApiUrl($url)
    {
        $this->apiUrl = $url;
        $this->initClient();
    }


    public function getProjects()
    {
        if (!$this->projects) {
            $this->projects = new Projects($this);
        }
        return $this->projects;
    }

    public function getProjectModel()
    {
        if (!$this->projectModel) {
            $this->projectModel = new ProjectModel($this);
        }
        return $this->projectModel;
    }

    public function getUsers()
    {
        if (!$this->users) {
            $this->users = new Users($this);
        }
        return $this->users;
    }

    public function getDatasets()
    {
        if (!$this->datasets) {
            $this->datasets = new Datasets($this);
        }
        return $this->datasets;
    }

    public function getFilters()
    {
        if (!$this->filters) {
            $this->filters = new Filters($this);
        }
        return $this->filters;
    }

    public function getReports()
    {
        if (!$this->reports) {
            $this->reports = new Reports($this);
        }
        return $this->reports;
    }

    public function getDateDimensions()
    {
        if (!$this->dateDimensions) {
            $this->dateDimensions = new DateDimensions($this);
        }
        return $this->dateDimensions;
    }



    public function get($uri, $params = [])
    {
        return $this->jsonRequest('GET', $uri, $params);
    }

    public function post($uri, $params = [])
    {
        return $this->jsonRequest('POST', $uri, $params);
    }

    public function put($uri, $params = [])
    {
        return $this->jsonRequest('PUT', $uri, $params);
    }

    public function delete($uri, $params = [])
    {
        return $this->jsonRequest('DELETE', $uri, $params);
    }

    public function jsonRequest($method, $uri, $params = [])
    {
        $body = $this->request($method, $uri, $params)->getBody();
        return json_decode($body, true);
    }

    public function pollTask($uri)
    {
        $repeat = true;
        $i = 0;
        do {
            sleep(self::WAIT_INTERVAL * ($i + 1));

            $result = $this->get($uri);
            if (isset($result['taskState']['status'])) {
                if (in_array($result['taskState']['status'], ['OK', 'ERROR', 'WARNING'])) {
                    $repeat = false;
                }
            } else {
                throw Exception::unexpectedResponseError('Task polling failed', 'GET', $uri, $result);
            }

            $i++;
        } while ($repeat);

        if ($result['taskState']['status'] != 'OK') {
            throw Exception::error($uri, $result);
        }
    }

    public function pollMaqlTask($uri)
    {
        $try = 1;
        do {
            sleep(10 * $try);
            $result = $this->get($uri);

            if (!isset($result['wTaskStatus']['status'])) {
                throw Exception::unexpectedResponseError('Task polling failed', 'GET', $uri, $result);
            }

            $try++;
        } while (in_array($result['wTaskStatus']['status'], ['PREPARED', 'RUNNING']));

        if ($result['wTaskStatus']['status'] == 'ERROR') {
            throw Exception::error($uri, $result);
        }
    }

    public function ping()
    {
        $curlErrorCount = 0;
        do {
            try {
                $response = $this->guzzle->request('GET', '/gdc/ping', self::DEFAULT_CLIENT_SETTINGS);
                return $response->getStatusCode() != 503;
            } catch (ServerException $e) {
                return false;
            } catch (ConnectException $e) {
                $curlErrorCount++;
                sleep(rand(1, 5));
            }
        } while ($curlErrorCount <= 5);
        return false;
    }

    public function getUserUploadUrl()
    {
        $data = $this->get('/gdc');
        foreach ($data['about']['links'] as $r) {
            if ($r['category'] == 'uploads') {
                return $r['link'];
            }
        }
        return false;
    }

    /**
     * @return ResponseInterface
     */
    public function request($method, $uri, $params = [], $refreshToken = true, $headers = null, $filename = null)
    {
        if ($refreshToken) {
            $this->refreshToken();
        }

        $startTime = time();

        $options = self::DEFAULT_CLIENT_SETTINGS;

        if ($params) {
            if ($method == 'GET' || $method == 'DELETE') {
                $options['query'] = $params;
            } else {
                $options['json'] = $params;
            }
        }

        if ($filename) {
            $options['sink'] = $filename;
        }

        if ($headers) {
            $options['headers'] = array_replace($options['headers'], $headers);
        }

        try {
            $response = $this->guzzle->request($method, $uri, $options);

            $this->log($uri, $method, $params, $response, time() - $startTime, $headers, $filename);
            return $response;
        } catch (RequestException $e) {
            $this->log($uri, $method, $params, $e->getResponse(), time() - $startTime, $headers, $filename);

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseJson = json_decode($response->getBody(), true);
                switch ($e->getResponse()->getStatusCode()) {
                    case 401:
                        if ($uri == '/gdc/account/login') {
                            throw Exception::error($uri, $responseJson, 401, $e);
                        } else {
                            $this->login($this->username, $this->password);
                        }
                        break;
                    default:
                        throw Exception::error($uri, $responseJson, $response->getStatusCode(), $e);
                }
            }
        } catch (\Exception $e) {
            $this->log($uri, $method, $params, null, time() - $startTime, $headers, $filename);
            throw $e;
        }
    }

    public function getToFile($uri, $filename, $retries = 20)
    {
        $this->refreshToken();
        $startTime = time();

        $options = self::DEFAULT_CLIENT_SETTINGS;
        $options['timeout'] = 0;
        $options['sink'] = $filename;
        $options['headers'] = array_replace($options['headers'], [
            'accept' => 'text/csv',
            'accept-charset' => 'utf-8'
        ]);

        try {
            $response = $this->guzzle->get($uri, $options);

            $this->log($uri, 'GET', ['filename' => $filename], $response, time() - $startTime);

            if ($response->getStatusCode() == 200) {
                return $filename;
            } elseif ($response->getStatusCode() == 202) {
                if ($retries <= 0) {
                    throw new Exception("Downloading of report timed out");
                }
                sleep(self::BACKOFF_INTERVAL * (21 - $retries));
                return $this->getToFile($uri, $filename, $retries-1);
            }
        } catch (RequestException $e) {
            $this->log($uri, 'GET', [], $e->getResponse(), time() - $startTime, $options['headers'], $filename);

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseJson = json_decode($response->getBody(), true);
                switch ($e->getResponse()->getStatusCode()) {
                    case 401:
                        if ($uri == '/gdc/account/login') {
                            throw Exception::error($uri, $responseJson, 401, $e);
                        } else {
                            $this->login($this->username, $this->password);
                        }
                        break;
                    default:
                        throw Exception::error($uri, $responseJson, $response->getStatusCode(), $e);
                }
            }
        } catch (\Exception $e) {
            $this->log($uri, 'GET', [], null, time() - $startTime, $options['headers'], $filename);
            throw $e;
        }
    }

    public function login($username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        try {
            $this->request('POST', '/gdc/account/login', [
                'postUserLogin' => [
                    'login' => $this->username,
                    'password' => $this->password,
                    'remember' => 0
                ]
            ], false);
        } catch (Exception $e) {
            throw Exception::loginError($e);
        }

        $this->refreshToken();
    }

    public function refreshToken()
    {
        try {
            $this->request('GET', '/gdc/account/token', [], false);
        } catch (Exception $e) {
            throw Exception::loginError($e);
        }
    }

    protected function log(
        $uri,
        $method,
        $params,
        Response $response = null,
        $duration = 0,
        $headers = null,
        $filename = null
    ) {
        if (!$this->logger) {
            return;
        }

        foreach ($params as $k => &$v) {
            if ($k == 'password') {
                $v = '***';
            }
        }

        $data = [
            'request' => [
                'params' => $params,
                'response' => $response ? [
                    'status' => $response->getStatusCode(),
                    'body' => (string)$response->getBody()
                ] : null
            ],
            'duration' => $duration,
            'pid' => getmypid()
        ];
        if ($headers) {
            $data['request']['headers'] = $headers;
        }
        if ($filename) {
            $data['request']['filename'] = $filename;
        }

        $this->logger->debug($method . ' ' . $uri, array_merge($this->logData, $data));
    }
}
