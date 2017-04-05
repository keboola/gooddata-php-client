<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

class ProjectModel
{
    /** @var  Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function view($pid)
    {
        $uri = "/gdc/projects/$pid/model/view?includeDeprecated=true&includeGrain=true";
        $result = $this->client->get($uri);

        if (isset($result['asyncTask']['link']['poll'])) {
            $try = 1;
            do {
                sleep(10 * $try);
                $taskResponse = $this->client->get($result['asyncTask']['link']['poll']);

                if (!isset($taskResponse['asyncTask']['link']['poll'])) {
                    if (isset($taskResponse['projectModelView']['model'])) {
                        return $taskResponse['projectModelView']['model'];
                    } else {
                        throw Exception::unexpectedResponseError(
                            'Polling of Model view could not be finished',
                            'GET',
                            $result['asyncTask']['link']['poll'],
                            $taskResponse
                        );
                    }
                }

                $try++;
            } while (true);
        } else {
            throw Exception::unexpectedResponseError('Model view failed', 'GET', $uri, $result);
        }

        return false;
    }

    public function diff($pid, $model)
    {
        $uri = "/gdc/projects/$pid/model/diff?includeDeprecated=true&includeGrain=true";
        $result = $this->client->post($uri, ['diffRequest' => ['targetModel' => $model]]);

        if (isset($result['asyncTask']['link']['poll'])) {
            $try = 1;
            do {
                sleep(10 * $try);
                $taskResponse = $this->client->get($result['asyncTask']['link']['poll']);

                if (!isset($taskResponse['asyncTask']['link']['poll'])) {
                    if (isset($taskResponse['projectModelDiff']['updateScripts'])) {
                        $lessDestructive = [];
                        $moreDestructive = [];
                        // Preserve data if possible
                        foreach ($taskResponse['projectModelDiff']['updateScripts'] as $updateScript) {
                            if ($updateScript['updateScript']['preserveData']
                                && !$updateScript['updateScript']['cascadeDrops']) {
                                $lessDestructive = $updateScript['updateScript']['maqlDdlChunks'];
                            }
                            if (!count($lessDestructive) && !$updateScript['updateScript']['preserveData']
                                && !$updateScript['updateScript']['cascadeDrops']) {
                                $lessDestructive = $updateScript['updateScript']['maqlDdlChunks'];
                            }
                            if (!$updateScript['updateScript']['preserveData']
                                && $updateScript['updateScript']['cascadeDrops']) {
                                $moreDestructive = $updateScript['updateScript']['maqlDdlChunks'];
                            }
                            if (!count($moreDestructive) && $updateScript['updateScript']['preserveData']
                                && $updateScript['updateScript']['cascadeDrops']) {
                                $moreDestructive = $updateScript['updateScript']['maqlDdlChunks'];
                            }
                        }

                        $description = [];
                        foreach ($taskResponse['projectModelDiff']['updateOperations'] as $o) {
                            $description[] = vsprintf(
                                $o['updateOperation']['description'],
                                $o['updateOperation']['parameters']
                            );
                        }

                        if (!count($lessDestructive) && count($moreDestructive)) {
                            $lessDestructive = $moreDestructive;
                            $moreDestructive = [];
                        }

                        return [
                            'moreDestructiveMaql' => $moreDestructive,
                            'lessDestructiveMaql' => $lessDestructive,
                            'description' => $description
                        ];
                    } else {
                        throw Exception::unexpectedResponseError(
                            'Polling of Model diff could not be finished',
                            'GET',
                            $result['asyncTask']['link']['poll'],
                            $taskResponse
                        );
                    }
                }

                $try++;
            } while (true);
        } else {
            throw Exception::unexpectedResponseError(
                'Polling of Model diff could not be started',
                'POST',
                $uri,
                $result
            );
        }

        return false;
    }

    public function normalizeModel($model)
    {
        foreach ($model['projectModel']['datasets'] as &$d) {
            if (isset($d['dataset']['anchor']['attribute']['labels'])) {
                foreach ($d['dataset']['anchor']['attribute']['labels'] as &$l) {
                    if (isset($l['label']['dataType']) && $l['label']['dataType'] == 'DECIMAL(16,2)') {
                        $l['label']['dataType'] = 'DECIMAL(15,2)';
                    }
                }
            }
            if (isset($d['dataset']['attributes'])) {
                foreach ($d['dataset']['attributes'] as &$a) {
                    if (isset($a['attribute']['labels'])) {
                        foreach ($a['attribute']['labels'] as &$l) {
                            if (isset($l['label']['dataType']) && $l['label']['dataType'] == 'DECIMAL(16,2)') {
                                $l['label']['dataType'] = 'DECIMAL(15,2)';
                            }
                        }
                    }
                }
            }
            if (isset($d['dataset']['facts'])) {
                foreach ($d['dataset']['facts'] as &$f) {
                    if (isset($f['fact']['dataType']) && $f['fact']['dataType'] == 'DECIMAL(16,2)') {
                        $f['fact']['dataType'] = 'DECIMAL(15,2)';
                    }
                }
            }
        }
        return $model;
    }

    public function update($pid, $model, $dryRun = false)
    {
        $model = $this->normalizeModel($model);

        $update = $this->diff($pid, $model);
        if ($dryRun) {
            return $update;
        } else {
            if (count($update['lessDestructiveMaql'])) {
                foreach ($update['lessDestructiveMaql'] as $i => $m) {
                    try {
                        $this->client->getDatasets()->executeMaql($pid, $m);
                    } catch (Exception $e) {
                        if (!empty($update['moreDestructiveMaql'][$i])) {
                            $m = $update['moreDestructiveMaql'][$i];
                            $this->client->getDatasets()->executeMaql($pid, $m);
                        } else {
                            throw $e;
                        }
                    }

                    return [
                        'description' => $update['description'],
                        'maql' => $m
                    ];
                }
            }
            return false;
        }
    }

    public function updateDataSet($pid, $datasetModel, $dryRun = false)
    {
        $projectModel = $this->view($pid);

        if (!isset($projectModel['projectModel']['datasets'])) {
            $projectModel['projectModel']['datasets'] = [];
        }
        $dataSetFound = false;
        foreach ($projectModel['projectModel']['datasets'] as &$dataSet) {
            if ($dataSet['dataset']['identifier'] == $datasetModel['identifier']) {
                $dataSetFound = true;
                $dataSet['dataset'] = $datasetModel;
                break;
            }
        }

        if (!$dataSetFound) {
            $projectModel['projectModel']['datasets'][] = ['dataset' => $datasetModel];
        }

        return $this->update($pid, $projectModel, $dryRun);
    }


    public function dropDataSet($pid, $dataSetName)
    {
        $dataSetId = Identifiers::getIdentifier($dataSetName);
        $model = $this->view($pid);
        if (isset($model['projectModel']['datasets'])) {
            foreach ($model['projectModel']['datasets'] as $i => $dataSet) {
                if ($dataSet['dataset']['title'] == $dataSetName) {
                    unset($model['projectModel']['datasets'][$i]);
                    break;
                }
            }
            $model['projectModel']['datasets'] = array_values($model['projectModel']['datasets']);
        }

        $update = $this->diff($pid, $model);

        if (count($update['moreDestructiveMaql'])) {
            foreach ($update['moreDestructiveMaql'] as $m) {
                $this->client->getDatasets()->executeMaql($pid, $m);
            }
            $this->client->getDatasets()->executeMaql($pid, sprintf('DROP IF EXISTS {dim.%s};', $dataSetId));
            $this->client->getDatasets()->executeMaql($pid, sprintf('DROP IF EXISTS {ffld.%s};', $dataSetId));

            return $update['description'];
        }
        return false;
    }

    public function updateProject($pid, $model, $dryRun = false)
    {
        // Get current GD model
        $gdModel = $this->view($pid);
        if (!isset($gdModel['projectModel']['datasets'])) {
            $gdModel['projectModel']['datasets'] = [];
        }

        // Get writer model
        $writerDatasets = [];
        foreach ($model['projectModel']['datasets'] as $d) {
            $writerDatasets[$d['dataset']['identifier']] = $d;
        }

        // Replace existing datasets
        foreach ($gdModel['projectModel']['datasets'] as &$d) {
            if (in_array($d['dataset']['identifier'], array_keys($writerDatasets))) {
                $d['dataset'] = $writerDatasets[$d['dataset']['identifier']]['dataset'];
                unset($writerDatasets[$d['dataset']['identifier']]);
            }
        }
        unset($d);

        // Add new datasets
        foreach ($writerDatasets as $d) {
            $gdModel['projectModel']['datasets'][] = $d;
        }

        // Update
        return $this->update($pid, $gdModel, $dryRun);
    }
}
