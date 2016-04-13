<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

class Datasets
{
    /** @var  Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getUriForIdentifier($pid, $identifier)
    {
        $result = $this->client->post("/gdc/md/$pid/identifiers", ["identifierToUri" => [$identifier]]);
        if (!count($result['identifiers'])) {
            throw new Exception("Object '$identifier' not found in project '$pid'");
        }
        return $result['identifiers'][0]['uri'];
    }

    public function getAttributeValueUri($pid, $attribute, $value)
    {
        $attr = $this->client->get($this->getUriForIdentifier($pid, $attribute));
        $result = $this->client->get($attr['attribute']['content']['displayForms'][0]['links']['elements']);
            //. '?filter='.urlencode($value));

        if (!count($result['attributeElements']['elements'])) {
            throw new Exception("Value '$value' of attribute '$attribute' not found in project '$pid'");
        }
        foreach ($result['attributeElements']['elements'] as $e) {
            if ($e['title'] == $value) {
                return $e['uri'];
            }
        }

        throw new Exception("Value '$value' of attribute '$attribute' not found in project '$pid'");
    }

    public function executeMaql($pid, $maql)
    {
        $uri = "/gdc/md/$pid/ldm/manage2";
        $result = $this->client->post($uri, [
            'manage' => [
                'maql' => $maql
            ]
        ]);

        $result = array_column($result['entries'], 'link', 'category');
        if (!isset($result['tasks-status'])) {
            throw Exception::unexpectedResponseError('Missing poll link in maql execute', 'POST', $uri, $result);
        }
        $this->client->pollMaqlTask($result['tasks-status']);
    }

    public function optimizeSliHash($pid)
    {
        $uri = "/gdc/md/$pid/etl/mode";
        $result = $this->client->post($uri, [
            'etlMode' => [
                'mode' => 'SLI',
                'lookup' => 'recreate'
            ]
        ]);
        if (empty($result['uri'])) {
            throw Exception::unexpectedResponseError('Missing poll link in optimize sli hash', 'POST', $uri, $result);
        }
        $this->client->pollMaqlTask($result['uri']);
    }

    public function synchronize($pid, $datasetId, $preserveData = true)
    {
        $maql = "SYNCHRONIZE {{$datasetId}}";
        if ($preserveData) {
            $maql .= ' PRESERVE DATA';
        }
        $maql .= ';';
        $this->executeMaql($pid, $maql);
    }

    public function loadData($pid, $dirName)
    {
        $uri = "/gdc/md/$pid/etl/pull";
        $result = $this->client->post($uri, ['pullIntegration' => $dirName]);

        if (isset($result['pullTask']['uri'])) {
            $try = 1;
            do {
                sleep(10 * $try);
                $taskResponse = $this->client->get($result['pullTask']['uri']);

                if (!isset($taskResponse['taskStatus'])) {
                    throw Exception::unexpectedResponseError(
                        'ETL task could not be checked',
                        'GET',
                        $result['pullTask']['uri'],
                        $taskResponse
                    );
                }

                $try++;
            } while (in_array($taskResponse['taskStatus'], ['PREPARED', 'RUNNING']));

            // Gather data about upload
            $uploadInfo = [];
            $taskId = substr($result['pullTask']['uri'], strrpos($result['pullTask']['uri'], '/')+1);
            if (strpos($taskId, ':') !== false) {
                $taskIds = explode(':', $taskId);
                array_shift($taskIds);
                array_shift($taskIds);
                foreach ($taskIds as $taskId) {
                    $uploadInfo[] = $this->client->get("/gdc/md/$pid/data/upload/$taskId");
                }
            } else {
                $uploadInfo[] = $this->client->get("/gdc/md/$pid/data/upload/$taskId");
            }

            if ($taskResponse['taskStatus'] == 'ERROR' || $taskResponse['taskStatus'] == 'WARNING') {
                // Find upload error message
                foreach ($uploadInfo as $upload) {
                    if (isset($upload['dataUpload']['status']) && $upload['dataUpload']['status'] == 'ERROR') {
                        $dataset = $this->client->get($upload['dataUpload']['etlInterface']);
                        throw Exception::error(
                            $result['pullTask']['uri'],
                            "Data load of dataset {$dataset['dataSet']['meta']['title']} failed. "
                                . (isset($upload['dataUpload']['msg'])? $upload['dataUpload']['msg'] : '')
                                . json_encode($uploadInfo),
                            400
                        );
                    }
                }
            }

            return $uploadInfo;
        } else {
            throw Exception::unexpectedResponseError('ETL task failed', 'POST', $uri, $result);
        }
    }

    public static function getDataLoadManifest($definition, $incrementalLoad = false, $useDateFacts = false)
    {
        $manifest = [
            'dataSetSLIManifest' => [
                'file' => strtolower($definition['tableId']) . '.csv',
                'dataSet' => !empty($definition['identifier']) ? $definition['identifier']
                    : Identifiers::getDatasetId($definition['tableId']),
                'parts' => []
            ]
        ];
        foreach ($definition['columns'] as $columnName => $column) {
            if (!isset($column['type'])) {
                continue;
            }
            switch ($column['type']) {
                case 'CONNECTION_POINT':
                case 'ATTRIBUTE':
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            !empty($column['identifierLabel']) ? $column['identifierLabel']
                                : Identifiers::getLabelId($definition['tableId'], $columnName)
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL',
                        'referenceKey' => 1
                    ];
                    break;
                case 'FACT':
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            !empty($column['identifier']) ? $column['identifier']
                                : Identifiers::getFactId($definition['tableId'], $columnName)
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL'
                    ];
                    break;
                case 'LABEL':
                case 'HYPERLINK':
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            !empty($column['identifier']) ? $column['identifier']
                                : Identifiers::getRefLabelId($definition['tableId'], $column['reference'], $columnName)
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL'
                    ];
                    break;
                case 'REFERENCE':
                    $identifier = !empty($column['schemaReferenceConnectionLabel'])
                        ? $column['schemaReferenceConnectionLabel'] : (!empty($column['identifier'])
                            ? $column['identifier'] : sprintf(
                                'label.%s.%s',
                                Identifiers::getIdentifier($column['schemaReference']),
                                Identifiers::getIdentifier($column['reference'])
                            ));
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            $identifier
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL',
                        'referenceKey' => 1
                    ];
                    break;
                case 'DATE':
                    $dimensionName = Identifiers::getIdentifier($column['dateDimension']);
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            sprintf('%s.date.mmddyyyy', !empty($column['identifier'])
                                ? $column['identifier']
                                : ($dimensionName
                                    . (!empty($column['template'] && strtolower($column['template']) != 'gooddata')
                                        ? '.' . strtolower($column['template']) : null)))
                        ],
                        'constraints' => [
                            'date' => (string)$column['format']
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL',
                        'referenceKey' => 1
                    ];
                    if ($useDateFacts) {
                        $manifest['dataSetSLIManifest']['parts'][] = [
                            'columnName' => $columnName . '_dt',
                            'populates' => [
                                !empty($column['identifierDateFact']) ? $column['identifierDateFact']
                                    : Identifiers::getDateFactId($definition['tableId'], $columnName)
                            ],
                            'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL'
                        ];
                    }
                    if (!empty($column['includeTime'])) {
                        $manifest['dataSetSLIManifest']['parts'][] = [
                            'columnName' => $columnName . '_tm',
                            'populates' => [
                                !empty($column['identifierTimeFact']) ? $column['identifierTimeFact']
                                    : TimeDimension::getTimeFactIdentifier($definition['tableId'], $columnName)
                            ],
                            'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL'
                        ];
                        $manifest['dataSetSLIManifest']['parts'][] = [
                            'columnName' => $columnName . '_id',
                            'populates' => [
                                sprintf('label.time.second.of.day.%s', $dimensionName)
                            ],
                            'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL',
                            'referenceKey' => 1
                        ];
                    }
                    break;
                case 'IGNORE':
                    break;
            }
        }

        return $manifest;
    }
}
