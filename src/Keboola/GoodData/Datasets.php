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
            if ((string)$e['title'] === (string)$value) {
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

    private function createEtlTask($pid, $dirName)
    {
        $uri = "/gdc/md/$pid/etl/pull2";
        $result = $this->client->post($uri, ['pullIntegration' => $dirName]);

        if (!isset($result['pull2Task']['links']['poll'])) {
            throw Exception::unexpectedResponseError('ETL task failed', 'POST', $uri, $result);
        }

        return $result['pull2Task']['links']['poll'];
    }

    private function pollEtlTask($uri)
    {
        $try = 1;
        do {
            sleep(10 * $try);
            $taskResponse = $this->client->get($uri);

            if (!isset($taskResponse['wTaskStatus']['status'])) {
                throw Exception::unexpectedResponseError(
                    'ETL task could not be checked',
                    'GET',
                    $uri,
                    $taskResponse
                );
            }

            $try++;
        } while ($taskResponse['wTaskStatus']['status'] == 'RUNNING');

        if ($taskResponse['wTaskStatus']['status'] == 'ERROR') {
            $errors = [];
            if (isset($taskResponse['messages'])) {
                foreach ($taskResponse['messages'] as $m) {
                    if (isset($m['error'])) {
                        $errors[] = Exception::parseMessage($m['error']);
                    }
                }
            }
            if (isset($taskResponse['wTaskStatus']['messages'])) {
                foreach ($taskResponse['wTaskStatus']['messages'] as $m) {
                    if (isset($m['error'])) {
                        $errors[] = Exception::parseMessage($m['error']);
                    }
                }
            }
            throw new Exception($errors);
        }
        return isset($taskResponse['messages']) ? $taskResponse['messages'] : [];
    }

    public function loadData($pid, $dirName)
    {
        $pollLink = $this->createEtlTask($pid, $dirName);

        try {
            return $this->pollEtlTask($pollLink);
        } catch (Exception $e) {
            if ($e->getCode() == 404 || strpos($e->getMessage(), 'not found') !== false) {
                // If ETL task was started just before maintenance, then ETL could expire sooner then the maintenance
                // ends. So we create the task once more.
                $pollLink = $this->createEtlTask($pid, $dirName);
                return $this->pollEtlTask($pollLink);
            }
            throw $e;
        }
    }

    public static function getDataLoadManifest(
        $identifier,
        array $columns,
        $incrementalLoad = false
    ) {
        $manifest = [
            'dataSetSLIManifest' => [
                'file' => "$identifier.csv",
                'dataSet' => $identifier,
                'parts' => []
            ]
        ];
        foreach ($columns as $columnName => $column) {
            if (!isset($column['type'])) {
                continue;
            }
            switch ($column['type']) {
                case 'CONNECTION_POINT':
                case 'ATTRIBUTE':
                    if (!isset($column['identifierLabel'])) {
                        throw Exception::configurationError(
                            "Configuration of column $columnName is missing 'identifierLabel'"
                        );
                    }
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            $column['identifierLabel']
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL',
                        'referenceKey' => 1
                    ];
                    break;
                case 'FACT':
                    if (!isset($column['identifier'])) {
                        throw Exception::configurationError(
                            "Configuration of column $columnName is missing 'identifier'"
                        );
                    }
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            $column['identifier']
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL'
                    ];
                    break;
                case 'LABEL':
                case 'HYPERLINK':
                    if (!isset($column['identifier'])) {
                        throw Exception::configurationError(
                            "Configuration of column $columnName is missing 'identifier'"
                        );
                    }
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            $column['identifier']
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL'
                    ];
                    break;
                case 'REFERENCE':
                    if (!isset($column['schemaReferenceConnectionLabel'])) {
                        throw Exception::configurationError(
                            "Configuration of column $columnName is missing 'schemaReferenceConnectionLabel'"
                        );
                    }
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            $column['schemaReferenceConnectionLabel']
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL',
                        'referenceKey' => 1
                    ];
                    break;
                case 'DATE':
                    if (!isset($column['identifier'])) {
                        throw Exception::configurationError(
                            "Configuration of column $columnName is missing 'identifier'"
                        );
                    }
                    $manifest['dataSetSLIManifest']['parts'][] = [
                        'columnName' => $columnName,
                        'populates' => [
                            (isset($column['template']) && $column['template'] == 'custom')
                                ? "{$column['identifier']}.date.day.us.mm_dd_yyyy"
                                : "{$column['identifier']}.date.mmddyyyy"
                        ],
                        'constraints' => [
                            'date' => (string)$column['format']
                        ],
                        'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL',
                        'referenceKey' => 1
                    ];
                    if (!empty($column['includeTime'])) {
                        if (!isset($column['identifierTimeFact'])) {
                            throw Exception::configurationError(
                                "Configuration of column $columnName is missing 'identifierTimeFact'"
                            );
                        }
                        $manifest['dataSetSLIManifest']['parts'][] = [
                            'columnName' => $columnName . '_tm',
                            'populates' => [
                                $column['identifierTimeFact']
                            ],
                            'mode' => $incrementalLoad ? 'INCREMENTAL' : 'FULL'
                        ];
                        $manifest['dataSetSLIManifest']['parts'][] = [
                            'columnName' => $columnName . '_id',
                            'populates' => [
                                "label.time.second.of.day.".Identifiers::getIdentifier($column['dateDimension'])
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
