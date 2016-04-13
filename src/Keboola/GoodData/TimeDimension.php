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

    public function create($pid, $name, $identifier = null)
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

        if (!in_array(self::getTimeDimensionIdentifier($name), $existingDataSets)) {
            $this->gdClient->getDatasets()->executeMaql($pid, self::getCreateMaql($identifier, $name));
        }
    }

    public function loadData($pid, $name, $tmpDir)
    {
        // Upload to WebDav
        $webDav = new WebDav(
            $this->gdClient->getUsername(),
            $this->gdClient->getPassword(),
            $this->gdClient->getUserUploadUrl()
        );

        $dimensionName = Identifiers::getIdentifier($name);
        $tmpFolderNameDimension = "$pid-$dimensionName-".uniqid();

        $tmpFolderDimension = $tmpDir . '/' . Identifiers::getIdentifier($name);
        mkdir($tmpFolderDimension);
        $timeDimensionManifest = self::getDataLoadManifest($dimensionName);
        file_put_contents("$tmpFolderDimension/upload_info.json", $timeDimensionManifest);
        copy(__DIR__ . '/time-dimension.csv', "$tmpFolderDimension/$dimensionName.csv");
        $webDav->createFolder($tmpFolderNameDimension);
        $webDav->upload("$tmpFolderDimension/upload_info.json", $tmpFolderNameDimension);
        $webDav->upload("$tmpFolderDimension/$dimensionName.csv", $tmpFolderNameDimension);


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
        $maql  = 'CREATE DATASET {dataset.time.%ID%} VISUAL(TITLE "Time (%NAME%)");';
        $maql .= 'CREATE FOLDER {dim.time.%ID%} VISUAL(TITLE "Time dimension (%NAME%)") TYPE ATTRIBUTE;';
        $maql .= 'CREATE FOLDER {ffld.time.%ID%} VISUAL(TITLE "Time dimension (%NAME%)") TYPE FACT;';

        $maql .= 'CREATE ATTRIBUTE {attr.time.second.of.day.%ID%} VISUAL(TITLE "Time (%NAME%)",'
            . ' FOLDER {dim.time.%ID%}) AS KEYS {d_time_second_of_day_%ID%.id} FULLSET WITH LABELS'
            . ' {label.time.%ID%} VISUAL(TITLE "Time (hh:mm:ss)") AS {d_time_second_of_day_%ID%.nm},'
            . ' {label.time.twelve.%ID%} VISUAL(TITLE "Time (HH:mm:ss)") AS {d_time_second_of_day_%ID%.nm_12},'
            . ' {label.time.second.of.day.%ID%} VISUAL(TITLE "Second of Day") AS {d_time_second_of_day_%ID%.nm_sec};';
        $maql .= 'ALTER ATTRIBUTE {attr.time.second.of.day.%ID%} ORDER BY {label.time.%ID%} ASC;';
        $maql .= 'ALTER DATASET {dataset.time.%ID%} ADD {attr.time.second.of.day.%ID%};';

        $maql .= 'CREATE ATTRIBUTE {attr.time.second.%ID%} VISUAL(TITLE "Second (%NAME%)",'
            . ' FOLDER {dim.time.%ID%}) AS KEYS {d_time_second_%ID%.id} FULLSET,'
            . ' {d_time_second_of_day_%ID%.second_id} WITH LABELS'
            . ' {label.time.second.%ID%} VISUAL(TITLE "Second") AS {d_time_second_%ID%.nm};';
        $maql .= 'ALTER DATASET {dataset.time.%ID%} ADD {attr.time.second.%ID%};';

        $maql .= 'CREATE ATTRIBUTE {attr.time.minute.of.day.%ID%} VISUAL(TITLE "Minute of Day (%NAME%)",'
            . ' FOLDER {dim.time.%ID%}) AS KEYS {d_time_minute_of_day_%ID%.id} FULLSET,'
            . ' {d_time_second_of_day_%ID%.minute_id} WITH LABELS'
            . ' {label.time.minute.of.day.%ID%} VISUAL(TITLE "Minute of Day") AS {d_time_minute_of_day_%ID%.nm};';
        $maql .= 'ALTER DATASET {dataset.time.%ID%} ADD {attr.time.minute.of.day.%ID%};';

        $maql .= 'CREATE ATTRIBUTE {attr.time.minute.%ID%} VISUAL(TITLE "Minute (%NAME%)",'
            . ' FOLDER {dim.time.%ID%}) AS KEYS {d_time_minute_%ID%.id} FULLSET,'
            . ' {d_time_minute_of_day_%ID%.minute_id} WITH LABELS'
            . ' {label.time.minute.%ID%} VISUAL(TITLE "Minute") AS {d_time_minute_%ID%.nm};';
        $maql .= 'ALTER DATASET {dataset.time.%ID%} ADD {attr.time.minute.%ID%};';

        $maql .= 'CREATE ATTRIBUTE {attr.time.hour.of.day.%ID%} VISUAL(TITLE "Hour (%NAME%)",'
            . ' FOLDER {dim.time.%ID%}) AS KEYS {d_time_hour_of_day_%ID%.id} FULLSET,'
            . ' {d_time_minute_of_day_%ID%.hour_id} WITH LABELS'
            . ' {label.time.hour.of.day.%ID%} VISUAL(TITLE "Hour (0-23)") AS {d_time_hour_of_day_%ID%.nm},'
            . ' {label.time.hour.of.day.twelve.%ID%} VISUAL(TITLE "Hour (1-12)") AS {d_time_hour_of_day_%ID%.nm_12};';
        $maql .= 'ALTER ATTRIBUTE {attr.time.hour.of.day.%ID%} ORDER BY {label.time.hour.of.day.%ID%} ASC;';
        $maql .= 'ALTER DATASET {dataset.time.%ID%} ADD {attr.time.hour.of.day.%ID%};';

        $maql .= 'CREATE ATTRIBUTE {attr.time.ampm.%ID%} VISUAL(TITLE "AM/PM (%NAME%)", '
            . 'FOLDER {dim.time.%ID%}) AS KEYS {d_time_ampm_%ID%.id} FULLSET,'
            . ' {d_time_hour_of_day_%ID%.ampm_id} WITH LABELS'
            . ' {label.time.ampm.%ID%} VISUAL(TITLE "AM/PM") AS {d_time_ampm_%ID%.nm};';
        $maql .= 'ALTER DATASET {dataset.time.%ID%} ADD {attr.time.ampm.%ID%};';

        $maql .= 'SYNCHRONIZE {dataset.time.%ID%};';

        $maql = str_replace('%ID%', $identifier, $maql);
        $maql = str_replace('%NAME%', $name, $maql);

        return $maql;
    }

    public static function getLDM($identifier, $title)
    {
        return [
            'identifier' => "dataset.time.$identifier",
            'title' => "Time ($title)",
            'anchor' => [
                'attribute' => [
                    'identifier' => "attr.time.second.of.day.$identifier",
                    'title' => "Time ($title)",
                    'folder' => "Time Dimension ($title)",
                    'labels' => [
                        [
                            'label' => [
                                'identifier' => 'label.time.'.$identifier,
                                'title' => 'Time (hh:mm:ss)',
                                'type' => 'GDC.text',
                                'dataType' => 'VARCHAR(128)'
                            ]
                        ],
                        [
                            'label' => [
                                'identifier' => 'label.time.twelve.'.$identifier,
                                'title' => 'Time (HH:mm:ss)',
                                'type' => 'GDC.text',
                                'dataType' => 'VARCHAR(128)'
                            ]
                        ],
                        [
                            'label' => [
                                'identifier' => 'label.time.second.of.day.'.$identifier,
                                'title' => 'Second of Day',
                                'type' => 'GDC.text',
                                'dataType' => 'VARCHAR(128)'
                            ]
                        ],
                    ],
                    'defaultLabel' => 'label.time.'.$identifier,
                    'sortOrder' => [
                        'attributeSortOrder' => [
                            'label' => 'label.time.'.$identifier,
                            'direction' => 'ASC'
                        ]
                    ]
                ]
            ],
            'attributes' => [
                [
                    'attribute' => [
                        'identifier' => 'attr.time.second.'.$identifier,
                        'title' => 'Second ('.$title.')',
                        'folder' => 'Time dimension ('.$title.')',
                        'labels' => [
                            [
                                'label' => [
                                    'identifier' => 'label.time.second.'.$identifier,
                                    'title' => 'Second',
                                    'type' => 'GDC.text',
                                    'dataType' => 'VARCHAR(128)'
                                ]
                            ]
                        ],
                        'defaultLabel' => 'label.time.second.'.$identifier
                    ]
                ],
                [
                    'attribute' => [
                        'identifier' => 'attr.time.minute.of.day.'.$identifier,
                        'title' => 'Minute of Day ('.$title.')',
                        'folder' => 'Time dimension ('.$title.')',
                        'labels' => [
                            [
                                'label' => [
                                    'identifier' => 'label.time.minute.of.day.'.$identifier,
                                    'title' => 'Minute of Day',
                                    'type' => 'GDC.text',
                                    'dataType' => 'VARCHAR(128)'
                                ]
                            ]
                        ],
                        'defaultLabel' => 'label.time.minute.of.day.'.$identifier
                    ]
                ],
                [
                    'attribute' => [
                        'identifier' => 'attr.time.minute.'.$identifier,
                        'title' => 'Minute ('.$title.')',
                        'folder' => 'Time dimension ('.$title.')',
                        'labels' => [
                            [
                                'label' => [
                                    'identifier' => 'label.time.minute.'.$identifier,
                                    'title' => 'Minute',
                                    'type' => 'GDC.text',
                                    'dataType' => 'VARCHAR(128)'
                                ]
                            ]
                        ],
                        'defaultLabel' => 'label.time.minute.'.$identifier
                    ]
                ],
                [
                    'attribute' => [
                        'identifier' => 'attr.time.hour.of.day.'.$identifier,
                        'title' => 'Hour ('.$title.')',
                        'folder' => 'Time dimension ('.$title.')',
                        'labels' => [
                            [
                                'label' => [
                                    'identifier' => 'label.time.hour.of.day.'.$identifier,
                                    'title' => 'Hour (0-23)',
                                    'type' => 'GDC.text',
                                    'dataType' => 'VARCHAR(128)'
                                ]
                            ],
                            [
                                'label' => [
                                    'identifier' => 'label.time.hour.of.day.twelve.'.$identifier,
                                    'title' => 'Hour (1-12)',
                                    'type' => 'GDC.text',
                                    'dataType' => 'VARCHAR(128)'
                                ]
                            ]
                        ],
                        'defaultLabel' => 'label.time.hour.of.day.'.$identifier,
                        'sortOrder' => [
                            'attributeSortOrder' => [
                                'label' => 'label.time.hour.of.day.'.$identifier,
                                'direction' => 'ASC'
                            ]
                        ]
                    ]
                ],
                [
                    'attribute' => [
                        'identifier' => 'attr.time.ampm.'.$identifier,
                        'title' => 'AM/PM ('.$title.')',
                        'folder' => 'Time dimension ('.$title.')',
                        'labels' => [
                            [
                                'label' => [
                                    'identifier' => 'label.time.ampm.'.$identifier,
                                    'title' => 'AM/PM',
                                    'type' => 'GDC.text',
                                    'dataType' => 'VARCHAR(128)'
                                ]
                            ]
                        ],
                        'defaultLabel' => 'label.time.ampm.'.$identifier
                    ]
                ]
            ]
        ];
    }

    public static function getDataLoadManifest($dimensionId)
    {
        $manifest = '{
    "dataSetSLIManifest" : {
        "parts" : [
            {
                "columnName" : "second_of_day",
                "populates" : [
                    "label.time.second.of.day.%NAME%"
                ],
                "mode" : "FULL",
                "referenceKey" : 1
            },
            {
                "columnName" : "second",
                "populates" : [
                    "label.time.second.%NAME%"
                ],
                "mode" : "FULL",
                "referenceKey" : 1
            },
            {
                "columnName" : "minute_of_day",
                "populates" : [
                    "label.time.minute.of.day.%NAME%"
                ],
                "mode" : "FULL",
                "referenceKey" : 1
            },
            {
                "columnName" : "minute",
                "populates" : [
                    "label.time.minute.%NAME%"
                ],
                "mode" : "FULL",
                "referenceKey" : 1
            },
            {
                "columnName" : "hour",
                "populates" : [
                    "label.time.hour.of.day.%NAME%"
                ],
                "mode" : "FULL",
                "referenceKey" : 1
            },
            {
                "columnName" : "hour12",
                "populates" : [
                    "label.time.hour.of.day.twelve.%NAME%"
                ],
                "mode" : "FULL"
            },
            {
                "columnName" : "am_pm",
                "populates" : [
                    "label.time.ampm.%NAME%"
                ],
                "mode" : "FULL",
                "referenceKey" : 1
            },
            {
                "columnName" : "time",
                "populates" : [
                    "label.time.%NAME%"
                ],
                "mode" : "FULL"
            },
            {
                "columnName" : "time12",
                "populates" : [
                    "label.time.twelve.%NAME%"
                ],
                "mode" : "FULL"
            }
        ],
        "file" : "%NAME%.csv",
        "dataSet" : "dataset.time.%NAME%"
    }
}';
        return str_replace('%NAME%', $dimensionId, $manifest);
    }
}
