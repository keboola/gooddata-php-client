<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2013-03-19
 *
 */

namespace Keboola\GoodData;

use Symfony\Component\Process\Process;

class WebDav
{
    const URL = 'https://secure-di.gooddata.com/uploads';

    protected $username;
    protected $password;
    protected $url;

    public function __construct($username, $password, $url = '')
    {
        $this->username = $username;
        $this->password = $password;
        $this->url = $url ?: self::URL;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function createFolder($folder)
    {
        $this->request($folder, 'MKCOL');
    }

    public function upload($file, $davFolder)
    {
        if (!file_exists($file)) {
            throw new Exception("File '$file' to be uploaded to WebDav does not exist.");
        }
        $fileInfo = pathinfo($file);

        $fileUri = "$davFolder/{$fileInfo['basename']}";
        try {
            $this->request(
                $fileUri,
                'PUT',
                '-T - --header ' . escapeshellarg('Content-encoding: gzip'),
                'cat ' . escapeshellarg($file) . ' | gzip -c | '
            );
        } catch (Exception $e) {
            throw new Exception("Error when uploading file to WebDav url '$fileUri'. " . $e->getMessage(), 400, $e);
        }
    }

    public function get($file)
    {
        try {
            return $this->request($file, 'GET');
        } catch (Exception $e) {
            throw new Exception("Error when getting file '$file' from WebDav'. " . $e->getMessage(), 400, $e);
        }
    }

    public function fileExists($file)
    {
        try {
            $this->request($file, 'PROPFIND');
            return true;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404 Not Found') !== false
                || strpos($e->getMessage(), 'curl: (22)') !== false) {
                return false;
            } else {
                throw $e;
            }
        }
    }

    public function listFiles($folderName, $extensions = [])
    {
        $result = $this->request(
            $folderName,
            'PROPFIND',
            ' --data ' . escapeshellarg(
                '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname /></d:prop></d:propfind>'
            )
            . ' -L -H ' . escapeshellarg('Content-Type: application/xml') . ' -H ' . escapeshellarg('Depth: 1')
        );

        libxml_use_internal_errors(true);
        /** @var \SimpleXMLElement $responseXML */
        $responseXML = simplexml_load_string($result, null, LIBXML_NOBLANKS | LIBXML_NOCDATA);
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
            $i = 0;
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

    protected function request($uri, $method = null, $arguments = null, $prepend = null, $append = null)
    {
        $url = $this->url . '/' . $uri;
        if ($method) {
            $arguments .= ' -X ' . escapeshellarg($method);
        }
        $command = $prepend . sprintf(
            'curl -s -S -f --retry 15 --user %s:%s %s %s',
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            $arguments,
            escapeshellarg($url)
        ) . $append;

        $error = null;
        $output = null;
        for ($i = 0; $i < 5; $i++) {
            $process = new Process($command);
            $process->setTimeout(5 * 60 * 60);
            $process->run();
            $output = $process->getOutput();
            $error = $process->getErrorOutput();

            if (!$process->isSuccessful() || $error) {
                $retry = false;
                if (substr($error, 0, 7) == 'curl: (' && $process->getExitCode() != 22) {
                    $retry = true;
                }
                if (!$retry) {
                    break;
                }
            } else {
                return $output;
            }

            sleep($i * 60);
        }

        throw new Exception($error? $error : $output);
    }
}
