<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Datasets;
use Keboola\GoodData\Exception;
use Keboola\GoodData\WebDav;

class DatasetsTest extends AbstractClientTest
{
    public function testDatasetsGetUriForIdentifier()
    {
        $pid = Helper::getSomeProject();
        $dataset = 'dataset.d'.uniqid();
        $this->client->getDatasets()->executeMaql($pid, "CREATE DATASET {{$dataset}} VISUAL (TITLE \"Test $dataset\");");

        $model = new Datasets($this->client);
        $uri = $model->getUriForIdentifier($pid, $dataset);
        $this->assertNotEmpty($uri);
        $result = $this->client->get($uri);
        $this->assertArrayHasKey('dataSet', $result);
        $this->assertArrayHasKey('meta', $result['dataSet']);
        $this->assertArrayHasKey('identifier', $result['dataSet']['meta']);
        $this->assertEquals($dataset, $result['dataSet']['meta']['identifier']);

        try {
            $model->getUriForIdentifier($pid, 'dataset.d'.uniqid());
            $this->fail();
        } catch (Exception $e) {
        }
    }

    public function testDatasetsGetAttributeValueUri()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        Helper::loadData($pid);

        $attrIdentifier = "attr.categories.id";
        $model = new Datasets($this->client);
        $uri = $model->getAttributeValueUri($pid, $attrIdentifier, 'c1');

        $attr = $this->client->get($this->client->getDatasets()->getUriForIdentifier($pid, $attrIdentifier));
        $result = $this->client->get($attr['attribute']['content']['displayForms'][0]['links']['elements']
            . '?filter='.urlencode('c1'));

        $elements = array_column($result['attributeElements']['elements'], 'uri', 'title');
        $this->assertArrayHasKey('c1', $elements);
        $this->assertEquals($uri, $elements['c1']);

        try {
            $model->getAttributeValueUri($pid, $attrIdentifier, 'xcsdfdsf');
            $this->fail();
        } catch (Exception $e) {
        }

        try {
            $model->getAttributeValueUri($pid, 'dataset.d'.uniqid(), 'c1');
            $this->fail();
        } catch (Exception $e) {
        }
    }

    public function testDatasetsExecuteMaql()
    {
        $pid = Helper::getSomeProject();
        $dataset = 'dataset.d'.uniqid();

        $model = new Datasets($this->client);
        $model->executeMaql($pid, "CREATE DATASET {{$dataset}} VISUAL (TITLE \"Test $dataset\");");

        $found = false;
        $result = $this->client->get("/gdc/md/$pid/data/sets");
        foreach ($result['dataSetsInfo']['sets'] as $d) {
            if ($d['meta']['identifier'] == $dataset) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function testDatasetsOptimizeSliHash()
    {
        $pid = Helper::getSomeProject();

        $model = new Datasets($this->client);
        $model->optimizeSliHash($pid);
        $this->assertTrue(true);
    }

    public function testDatasetsSynchronize()
    {
        $pid = Helper::getSomeProject();
        $dataset = 'dataset.d'.uniqid();
        $this->client->getDatasets()->executeMaql($pid, "CREATE DATASET {{$dataset}} VISUAL (TITLE \"Test $dataset\");");

        $model = new Datasets($this->client);
        $model->synchronize($pid, $dataset);
        $this->assertTrue(true);
    }

    public function testDatasetsLoadData()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        Helper::loadData($pid);

        $timestamp = time();
        $dirName = uniqid();
        $webDav = new WebDav(KBGDC_USERNAME, KBGDC_PASSWORD);
        $webDav->createFolder($dirName);
        $webDav->upload(__DIR__ . '/data/categories.csv', $dirName);
        $webDav->upload(__DIR__ . '/data/products.csv', $dirName);
        $webDav->upload(__DIR__ . '/data/upload_info.json', $dirName);
        $this->client->getDatasets()->loadData($pid, $dirName);
        $result = $this->client->get("/gdc/md/$pid/data/sets");
        $categoriesFound = false;
        $productsFound = false;
        foreach ($result['dataSetsInfo']['sets'] as $d) {
            if ($d['meta']['title'] == 'Categories') {
                $categoriesFound = true;
                $this->assertGreaterThan($timestamp, strtotime($d['lastUpload']['dataUploadShort']['date']));
            }
            if ($d['meta']['title'] == 'Products') {
                $productsFound = true;
                $this->assertGreaterThan($timestamp, strtotime($d['lastUpload']['dataUploadShort']['date']));
            }
        }
        $this->assertTrue($categoriesFound);
        $this->assertTrue($productsFound);
    }

    public function testGetDataLoadManifest()
    {
        $configuration = [
            'ignore' => [
                'title' => 'ignore'
            ],
            'id' => [
                'identifier' => 'attr.products.id',
                'identifierLabel' => 'label.products.id',
                'title' => 'Id',
                'type' => 'CONNECTION_POINT'
            ],
            'name' => [
                'title' => 'Name',
                'identifier' => 'ident.name.x',
                'identifierLabel' => 'label.products.name',
                'type' => 'ATTRIBUTE'
            ],
            'category' => [
                'type' => 'REFERENCE',
                'schemaReferenceConnectionLabel' => 'label.categories.id',
                'reference' => 'id',
                'schemaReference' => 'dataset.categories'
            ],
            'ignore2' => [
                'type' => 'IGNORE',
            ],
            'date' => [
                'identifier' => 'date1.keboola',
                'identifierTimeFact' => 'fact.time.products.id',
                'type' => 'DATE',
                'format' => 'yyyy-MM-dd',
                'template' => 'keboola',
                'dateDimension' => 'date 1',
                'includeTime' => true
            ],
            'price' => [
                'identifier' => 'fact.products.price',
                'title' => 'Price',
                'type' => 'FACT'
            ],
            'url' => [
                'identifier' => 'label.products.url.id',
                'type' => 'HYPERLINK'
            ]
        ];

        $result = Datasets::getDataLoadManifest('dataset.products', $configuration);
        $this->assertArrayHasKey('dataSetSLIManifest', $result);
        $this->assertArrayHasKey('file', $result['dataSetSLIManifest']);
        $this->assertArrayHasKey('dataSet', $result['dataSetSLIManifest']);
        $this->assertEquals('dataset.products', $result['dataSetSLIManifest']['dataSet']);
        $this->assertArrayHasKey('parts', $result['dataSetSLIManifest']);
        $this->assertCount(8, $result['dataSetSLIManifest']['parts']);

        $this->assertArrayHasKey('columnName', $result['dataSetSLIManifest']['parts'][0]);
        $this->assertEquals('id', $result['dataSetSLIManifest']['parts'][0]['columnName']);
        $this->assertArrayHasKey('populates', $result['dataSetSLIManifest']['parts'][0]);
        $this->assertEquals('label.products.id', $result['dataSetSLIManifest']['parts'][0]['populates'][0]);
        $this->assertArrayHasKey('mode', $result['dataSetSLIManifest']['parts'][0]);
        $this->assertEquals('FULL', $result['dataSetSLIManifest']['parts'][0]['mode']);
        $this->assertArrayHasKey('referenceKey', $result['dataSetSLIManifest']['parts'][0]);
        $this->assertEquals(1, $result['dataSetSLIManifest']['parts'][0]['referenceKey']);

        $this->assertArrayHasKey('columnName', $result['dataSetSLIManifest']['parts'][1]);
        $this->assertEquals('name', $result['dataSetSLIManifest']['parts'][1]['columnName']);
        $this->assertArrayHasKey('populates', $result['dataSetSLIManifest']['parts'][1]);
        $this->assertEquals('label.products.name', $result['dataSetSLIManifest']['parts'][1]['populates'][0]);
        $this->assertArrayHasKey('mode', $result['dataSetSLIManifest']['parts'][1]);
        $this->assertEquals('FULL', $result['dataSetSLIManifest']['parts'][1]['mode']);
        $this->assertArrayHasKey('referenceKey', $result['dataSetSLIManifest']['parts'][1]);
        $this->assertEquals(1, $result['dataSetSLIManifest']['parts'][1]['referenceKey']);

        $this->assertArrayHasKey('columnName', $result['dataSetSLIManifest']['parts'][2]);
        $this->assertEquals('category', $result['dataSetSLIManifest']['parts'][2]['columnName']);
        $this->assertArrayHasKey('populates', $result['dataSetSLIManifest']['parts'][2]);
        $this->assertEquals('label.categories.id', $result['dataSetSLIManifest']['parts'][2]['populates'][0]);
        $this->assertArrayHasKey('mode', $result['dataSetSLIManifest']['parts'][2]);
        $this->assertEquals('FULL', $result['dataSetSLIManifest']['parts'][2]['mode']);
        $this->assertArrayHasKey('referenceKey', $result['dataSetSLIManifest']['parts'][2]);
        $this->assertEquals(1, $result['dataSetSLIManifest']['parts'][2]['referenceKey']);

        $this->assertArrayHasKey('columnName', $result['dataSetSLIManifest']['parts'][3]);
        $this->assertEquals('date', $result['dataSetSLIManifest']['parts'][3]['columnName']);
        $this->assertArrayHasKey('populates', $result['dataSetSLIManifest']['parts'][3]);
        $this->assertEquals('date1.keboola.date.mmddyyyy', $result['dataSetSLIManifest']['parts'][3]['populates'][0]);
        $this->assertArrayHasKey('constraints', $result['dataSetSLIManifest']['parts'][3]);
        $this->assertArrayHasKey('date', $result['dataSetSLIManifest']['parts'][3]['constraints']);
        $this->assertEquals('yyyy-MM-dd', $result['dataSetSLIManifest']['parts'][3]['constraints']['date']);
        $this->assertArrayHasKey('mode', $result['dataSetSLIManifest']['parts'][3]);
        $this->assertEquals('FULL', $result['dataSetSLIManifest']['parts'][3]['mode']);
        $this->assertArrayHasKey('referenceKey', $result['dataSetSLIManifest']['parts'][3]);
        $this->assertEquals(1, $result['dataSetSLIManifest']['parts'][3]['referenceKey']);

        $this->assertArrayHasKey('columnName', $result['dataSetSLIManifest']['parts'][4]);
        $this->assertEquals('date_tm', $result['dataSetSLIManifest']['parts'][4]['columnName']);
        $this->assertArrayHasKey('populates', $result['dataSetSLIManifest']['parts'][4]);
        $this->assertEquals('fact.time.products.id', $result['dataSetSLIManifest']['parts'][4]['populates'][0]);

        $this->assertArrayHasKey('columnName', $result['dataSetSLIManifest']['parts'][5]);
        $this->assertEquals('date_id', $result['dataSetSLIManifest']['parts'][5]['columnName']);
        $this->assertArrayHasKey('populates', $result['dataSetSLIManifest']['parts'][5]);
        $this->assertEquals('label.time.second.of.day.date1', $result['dataSetSLIManifest']['parts'][5]['populates'][0]);

        $this->assertArrayHasKey('columnName', $result['dataSetSLIManifest']['parts'][6]);
        $this->assertEquals('price', $result['dataSetSLIManifest']['parts'][6]['columnName']);
        $this->assertArrayHasKey('populates', $result['dataSetSLIManifest']['parts'][6]);
        $this->assertEquals('fact.products.price', $result['dataSetSLIManifest']['parts'][6]['populates'][0]);

        $this->assertArrayHasKey('columnName', $result['dataSetSLIManifest']['parts'][7]);
        $this->assertEquals('url', $result['dataSetSLIManifest']['parts'][7]['columnName']);
        $this->assertArrayHasKey('populates', $result['dataSetSLIManifest']['parts'][7]);
        $this->assertEquals('label.products.url.id', $result['dataSetSLIManifest']['parts'][7]['populates'][0]);


        // Fail on missing identifiers
        $configuration1 = $configuration;
        unset($configuration1['id']['identifierLabel']);
        try {
            Datasets::getDataLoadManifest('dataset.products', $configuration1);
            $this->fail();
        } catch (Exception $e) {
        }

        $configuration2 = $configuration;
        unset($configuration2['price']['identifier']);
        try {
            Datasets::getDataLoadManifest('dataset.products', $configuration2);
            $this->fail();
        } catch (Exception $e) {
        }

        $configuration3 = $configuration;
        unset($configuration3['category']['schemaReferenceConnectionLabel']);
        try {
            Datasets::getDataLoadManifest('dataset.products', $configuration3);
            $this->fail();
        } catch (Exception $e) {
        }

        $configuration4 = $configuration;
        unset($configuration4['date']['identifier']);
        try {
            Datasets::getDataLoadManifest('dataset.products', $configuration4);
            $this->fail();
        } catch (Exception $e) {
        }

        $configuration5 = $configuration;
        unset($configuration5['date']['identifierTimeFact']);
        try {
            Datasets::getDataLoadManifest('dataset.products', $configuration5);
            $this->fail();
        } catch (Exception $e) {
        }
    }
}
