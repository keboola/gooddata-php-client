<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 * @copyright Keboola 2015
 */
namespace Keboola\GoodData;

class TimeDimension
{
    /** @var  Client */
    protected $gdClient;

    public function __construct(Client $gdClient)
    {
        $this->gdClient = $gdClient;
    }

    public static function getTimeDimensionIdentifier($name)
    {
        return 'dataset.time.' . Identifiers::getIdentifier($name);
    }

    public static function getTimeFactIdentifier($tableName, $attrName)
    {
        return sprintf('tm.dt.%s.%s', Identifiers::getIdentifier($tableName), Identifiers::getIdentifier($attrName));
    }

    public function exists($pid, $name, $identifier = null)
    {
        if (!$identifier) {
            $identifier = Identifiers::getIdentifier($name);
            if (!$identifier) {
                throw new Exception("Identifier derived from dimension name '$name' is not valid. "
                    . "Choose other name or custom identifier.");
            }
        }

        $call = $this->gdClient->get("/gdc/md/$pid/data/sets");
        $existingDataSets = [];
        foreach ($call['dataSetsInfo']['sets'] as $r) {
            $existingDataSets[] = $r['meta']['identifier'];
        }
        return in_array(self::getTimeDimensionIdentifier($name), $existingDataSets);
    }

    public function create($pid, $name, $identifier = null)
    {
        if (!$this->exists($pid, $name, $identifier)) {
            $this->gdClient->getDatasets()->executeMaql($pid, self::getCreateMaql($identifier, $name));
        }
    }

    public function loadData($pid, $name, $tmpDir)
    {
        // Upload to WebDav
        $webDavUri = $this->gdClient->getUserUploadUrl();
        $webDav = new WebDav(
            $this->gdClient->getUsername(),
            $this->gdClient->getPassword(),
            $webDavUri
        );

        $dimensionName = Identifiers::getIdentifier($name);
        $tmpFolderNameDimension = "$pid-$dimensionName-".uniqid();

        $tmpFolderDimension = $tmpDir . '/' . Identifiers::getIdentifier($name);
        mkdir($tmpFolderDimension);
        $timeDimensionManifest = self::getDataLoadManifest($dimensionName);
        file_put_contents("$tmpFolderDimension/upload_info.json", $timeDimensionManifest);
        copy(__DIR__ . '/time-dimension.csv', "$tmpFolderDimension/$dimensionName.csv");
        $webDav->createFolder($tmpFolderNameDimension);
        $webDav->uploadZip(
            ["$tmpFolderDimension/upload_info.json", "$tmpFolderDimension/$dimensionName.csv"],
            $tmpFolderNameDimension
        );


        // Run ETL task of time dimensions
        try {
            $this->gdClient->getDatasets()->loadData($pid, $tmpFolderNameDimension);
        } catch (Exception $e) {
            $debugFile = "$tmpFolderDimension/$pid-etl.log";
            $logSaved = $webDav->saveLogs($tmpFolderDimension, $debugFile);
            if ($logSaved) {
                $data = file_get_contents($debugFile);
                if ($data) {
                    $data = json_decode($data, true);
                    if ($data) {
                        $e = new Exception(json_decode(file_get_contents($debugFile), true), $e->getCode(), $e);
                    }
                }
            }
            throw $e;
        }
    }

    public static function getCreateMaql($identifier, $name)
    {
        $maql  = file_get_contents(__DIR__.'/time-dimension.maql');
        $maql = str_replace('%ID%', $identifier, $maql);
        $maql = str_replace('%NAME%', $name, $maql);

        return $maql;
    }

    public static function getLDM($identifier, $title)
    {
        $model = file_get_contents(__DIR__.'/time-dimension-ldm.json');
        $model = str_replace('%ID%', $identifier, $model);
        $model = str_replace('%TITLE%', $title, $model);
        return json_decode($model, true);
    }

    public static function getDataLoadManifest($dimensionId)
    {
        $manifest = file_get_contents(__DIR__.'/time-dimension-manifest.json');
        return str_replace('%NAME%', $dimensionId, $manifest);
    }
}
