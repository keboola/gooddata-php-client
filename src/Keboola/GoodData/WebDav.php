<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2013-03-19
 *
 */

namespace Keboola\GoodData;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;

class WebDav
{
    const RETRIES_COUNT = 5;
    const MAINTENANCE_RETRIES_COUNT = 60;
    const URL = 'https://secure.gooddata.com/gdc/uploads/';

    protected $username;
    protected $password;
    protected $url;

    public function __construct($username, $password, $url = '')
    {
        $this->username = $username;
        $this->password = $password;

        if ($url && substr($url, -1) != '/') {
            $url .= '/';
        }
        $this->url = $url ?: self::URL;

        $handlerStack = HandlerStack::create();

        // Retry from maintenance, makes 5-10 hours of waiting
        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                return $retries < self::MAINTENANCE_RETRIES_COUNT
                    && $response && ($response->getStatusCode() == 503 || $response->getStatusCode() == 423);
            },
            function ($retries) {
                return rand(60, 600) * 1000;
            }
        ));

        // Retry for server errors
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
        /*if ($this->logger) {
            $handlerStack->push(Middleware::log($this->logger, $this->loggerFormatter));
        }*/
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $this->url,
            'handler' => $handlerStack,
            'auth' => [$username, $password]
        ]);
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function createFolder($folder)
    {
        $this->client->request('MKCOL', $folder);
    }

    public function upload($file, $davFolder)
    {
        if (!file_exists($file)) {
            throw new Exception("File '$file' to be uploaded to WebDav does not exist.");
        }
        $fileInfo = pathinfo($file);

        $fileUri = "$davFolder/{$fileInfo['basename']}";
        try {
            $this->client->put($fileUri, [
                'body' => \GuzzleHttp\Psr7\stream_for(fopen($file, 'r'))
            ]);
        } catch (Exception $e) {
            throw new Exception("Error uploading file to WebDav '$fileUri'. " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function uploadZip(array $files, $davFolder)
    {
        $escapedFiles = [];
        foreach ($files as $file) {
            if (!file_exists($file)) {
                throw new Exception("File '$file' to be uploaded to WebDav does not exist.");
            }
            $escapedFiles[] = escapeshellarg($file);
        }

        $zipFile = sys_get_temp_dir() . '/' . uniqid(null, true) . '.zip';

        $process = new Process(sprintf('zip -j %s %s', escapeshellarg($zipFile), implode(' ', $escapedFiles)));
        $process->setTimeout(null);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Exception('Zip compression failed:' . $process->getErrorOutput());
        }

        $this->client->put("$davFolder/upload.zip", [
            'body' => \GuzzleHttp\Psr7\stream_for(fopen($zipFile, 'r'))
        ]);
    }

    public function get($file, $destination = null)
    {
        try {
            $options = ['headers' => ['Accept-Encoding' => 'gzip']];
            if ($destination) {
                $options['sink'] = $destination;
            }
            $response = $this->client->request('GET', $file, $options);
            return $destination ? null : (string)$response->getBody();
        } catch (Exception $e) {
            throw new Exception("Error getting file '$file' from WebDav'. " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function fileExists($file)
    {
        try {
            $this->client->request('HEAD', $file);
            return true;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() == 404) {
                return false;
            }
            throw $e;
        }
    }

    public function listFiles($folderName, $extensions = [])
    {
        $result = $this->client->request('PROPFIND', "$folderName/", [
            'body' => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname /></d:prop></d:propfind>',
            'headers' => [
                'Content-Type' => 'application/xml',
                'Depth' => '1'
            ]
        ]);

        libxml_use_internal_errors(true);
        /** @var \SimpleXMLElement $responseXML */
        $responseXML = simplexml_load_string($result->getBody(), null, LIBXML_NOBLANKS | LIBXML_NOCDATA);
        if ($responseXML === false) {
            throw new Exception('WebDav returned bad result when asked for error logs.');
        }

        $responseXML->registerXPathNamespace('D', 'urn:DAV');
        $list = [];
        foreach ($responseXML->xpath('D:response') as $response) {
            $response->registerXPathNamespace('D', 'urn:DAV');
            $href = $response->xpath('D:href');
            $file = pathinfo((string)$href[0]);
            if ($folderName != $file['basename']
                && (!count($extensions) || (isset($file['extension']) && in_array($file['extension'], $extensions)))) {
                $list[] = $file['basename'];
            }
        }

        return $list;
    }

    public function saveLogs($folderName, $logFile)
    {
        $errors = [];

        $uploadFile = "$folderName/upload_status.json";
        if ($this->fileExists($uploadFile)) {
            $result = $this->get($uploadFile);
            if ($result) {
                $jsonResult = json_decode($result, true);
                if (isset($jsonResult['error']['component'])
                    && $jsonResult['error']['component'] != 'GDC::DB2::ETL'
                    && isset($jsonResult['error']['message'])) {
                    if (isset($jsonResult['error']['parameters'])) {
                        $jsonResult['error']['message'] = vsprintf(
                            $jsonResult['error']['message'],
                            $jsonResult['error']['parameters']
                        );
                    }
                    $errors['upload_status.json'] = $jsonResult['error']['message'];
                }
            }
        }

        foreach ($this->listFiles($folderName, ['log']) as $file) {
            $errors[$file] = $this->get("$folderName/$file");
        }

        if (count($errors)) {
            $result = [];
            foreach ($errors as $f => $e) {
                $json = json_decode($e, true);
                $result[$f] = $json ?: $e;
            }
            file_put_contents($logFile, json_encode($result, JSON_PRETTY_PRINT));

            return true;
        } else {
            return false;
        }
    }
}
