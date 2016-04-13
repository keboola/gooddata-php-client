<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Client;
use Keboola\GoodData\Exception;
use Keboola\GoodData\TimeDimension;
use Keboola\GoodData\WebDav;
use Keboola\Temp\Temp;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Helper
{
    /** @var  LoggerInterface */
    private static $logger;
    /** @var  Client */
    private static $client;

    private static $lastUser;

    /** @var  Temp */
    private static $temp;

    public static function getTemp()
    {
        if (!self::$temp) {
            self::$temp = new Temp();
            self::$temp->initRunFolder();
        }
        return self::$temp;
    }

    public static function getTempFolder($dirName = null)
    {
        if (!$dirName) {
            $dirName = uniqid();
        }
        $temp = self::getTemp();
        $dir = $temp->getTmpFolder().'/'.$dirName;
        if (!file_exists($dir)) {
            mkdir($dir);
        }
        return $dir;
    }

    public static function getLogger()
    {
        if (!self::$logger) {
            self::$logger = new Logger(KBGDC_APP_NAME);
            if (getenv('KBGDC_PAPERTRAIL_PORT')) {
                $handler = new SyslogUdpHandler("logs.papertrailapp.com", getenv('KBGDC_PAPERTRAIL_PORT'));
            } else {
                $handler = new SyslogHandler(KBGDC_APP_NAME);
            }
            $handler->setFormatter(new JsonFormatter());
            self::$logger->pushHandler($handler);
        }
        return self::$logger;
    }

    public static function getClient()
    {
        if (!self::$client) {
            self::$client = new Client();
            self::$client->setLogger(self::getLogger());
            self::$client->setLogData([
                'app' => KBGDC_APP_NAME
            ]);
        }
        self::$client->login(KBGDC_USERNAME, KBGDC_PASSWORD);
        return self::$client;
    }

    /**
     * Find some project not older than day (older projects will be deleted by cleanup script)
     * If there is none, create new project
     */
    public static function getSomeProject()
    {
        $result = self::getClient()->get('/gdc/account/profile/current');
        $uri = $result['accountSetting']['links']['self'];
        $uid = substr($uri, strrpos($uri, '/')+1);
        $result = self::getClient()->get("/gdc/account/profile/$uid/projects");
        foreach ($result['projects'] as $project) {
            if ($project['project']['content']['state'] == 'ENABLED'
                && strpos($project['project']['meta']['title'], KBGDC_PROJECTS_PREFIX) === 0
                && time() - strtotime($project['project']['meta']['created']) < 82800) {
                $projectUri = $project['project']['links']['self'];
                return substr($projectUri, strrpos($projectUri, '/') + 1);
            }
        }

        return self::createProject();
    }

    public static function getDataSetsList($pid)
    {
        $result = [];
        $call = self::getClient()->get("/gdc/md/$pid/data/sets");
        foreach ($call['dataSetsInfo']['sets'] as $r) {
            $result[$r['meta']['identifier']] = [
                'id' => $r['meta']['identifier'],
                'title' => $r['meta']['title']
            ];
        }
        return $result;
    }

    public static function cleanUpProject($pid)
    {
        do {
            $error = false;
            $datasets = self::getClient()->get("/gdc/md/$pid/data/sets");
            foreach ($datasets['dataSetsInfo']['sets'] as $dataset) {
                try {
                    self::getClient()->getDatasets()->executeMaql(
                        $pid,
                        'DROP ALL IN {' . $dataset['meta']['identifier'] . '} CASCADE'
                    );
                } catch (Exception $e) {
                    $error = true;
                }
            }
        } while ($error);

        $folders = self::getClient()->get("/gdc/md/$pid/query/folders");
        foreach ($folders['query']['entries'] as $folder) {
            try {
                self::getClient()->getDatasets()->executeMaql(
                    $pid,
                    'DROP {'.$folder['identifier'].'};'
                );
            } catch (Exception $e) {
            }
        }
        $dimensions = self::getClient()->get("/gdc/md/$pid/query/dimensions");
        foreach ($dimensions['query']['entries'] as $folder) {
            try {
                self::getClient()->getDatasets()->executeMaql($pid, 'DROP {'.$folder['identifier'].'};');
            } catch (Exception $e) {
            }
        }
    }

    public static function createProject()
    {
        return self::getClient()->getProjects()
            ->createProject(KBGDC_PROJECTS_PREFIX . uniqid(), KBGDC_AUTH_TOKEN, null, true);
    }

    public static function createUser($email = null, $pass = null)
    {
        if (!$email) {
            $email = uniqid().'@'.KBGDC_USERS_DOMAIN;
        }
        if (!$pass) {
            $pass = md5($email);
        }
        $uid = self::getClient()->getUsers()->createUser($email, $pass, KBGDC_DOMAIN, [
            'firstName' => uniqid(),
            'lastName' => uniqid(),
            'ssoProvider' => KBGDC_SSO_PROVIDER
        ]);

        self::$lastUser = [
            'email' => strtolower($email),
            'password' => $pass,
            'uid' => $uid
        ];

        return self::$lastUser['uid'];
    }

    public static function getSomeUser()
    {
        if (!self::$lastUser) {
            self::createUser();
        }
        return self::$lastUser;
    }
    
    public static function getFilters($pid)
    {
        $result = self::getClient()->get("/gdc/md/$pid/query/userfilters");
        return $result['query']['entries'];
    }

    public static function createReport($pid, $title = 'Test Report')
    {
        $attribute1 = self::getClient()->getDatasets()->getUriForIdentifier($pid, "label.categories.id");
        $attribute2 = self::getClient()->getDatasets()->getUriForIdentifier($pid, "label.categories.name");

        $definition = '
{
   "reportDefinition" : {
      "content" : {
         "grid" : {
            "sort" : {
               "columns" : [],
               "rows" : []
            },
            "columnWidths" : [],
            "columns" : [],
            "metrics" : [],
            "rows" : [
               {
                  "attribute" : {
                     "alias" : "",
                     "totals" : [],
                     "uri" : "'.$attribute1.'"
                  }
               },
               {
                  "attribute" : {
                     "alias" : "",
                     "totals" : [],
                     "uri" : "'.$attribute2.'"
                  }
               }
            ]
         },
         "format" : "grid",
         "filters" : []
      },
      "meta" : {
         "tags" : "",
         "deprecated" : "0",
         "summary" : "",
         "title" : "Test Report Definition",
         "category" : "reportDefinition"
      }
   }
}';
        $result = self::getClient()->post("/gdc/md/$pid/obj", $definition);
        $result = self::getClient()->post("/gdc/md/$pid/obj", [
            "report" => [
                "content" => [
                    "domains" => [],
                    "definitions" => [
                        $result['uri']
                    ]
                ],
                "meta" => [
                    "tags" => "",
                    "deprecated" => "0",
                    "summary" => "",
                    "title" => $title
                ]
            ]
        ]);
        return $result['uri'];
    }
    
    public static function loadData($pid)
    {
        $dirName = uniqid();
        $webDav = new WebDav(KBGDC_USERNAME, KBGDC_PASSWORD);
        $webDav->createFolder($dirName);
        $webDav->upload(__DIR__.'/data/categories.csv', $dirName);
        $webDav->upload(__DIR__.'/data/products.csv', $dirName);
        $webDav->upload(__DIR__.'/data/upload_info.json', $dirName);
        self::getClient()->getDatasets()->loadData($pid, $dirName);
    }

    public static function initProjectModel($pid)
    {
        $client = self::getClient();
        self::cleanUpProject($pid);

        $client->getDateDimensions()->create($pid, 'Date 1');

        $td = new TimeDimension($client);
        $td->create($pid, 'Date 1');

        $model = json_decode(file_get_contents(__DIR__.'/data/model.json'), true);
        $client->getProjectModel()->updateProject($pid, $model);
    }
}
