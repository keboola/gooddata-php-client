<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Datasets;
use Keboola\GoodData\WebDav;

class DatasetsTest extends AbstractClientTest
{
    public function testDatasetsLoadData()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        Helper::loadData($pid);

        $timestamp = time();
        $dirName = uniqid();
        $webDav = new WebDav(KBGDC_USERNAME, KBGDC_PASSWORD);
        $webDav->createFolder($dirName);
        $webDav->upload(__DIR__.'/../../data/categories.csv', $dirName);
        $webDav->upload(__DIR__.'/../../data/products.csv', $dirName);
        $webDav->upload(__DIR__.'/../../data/upload_info.json', $dirName);
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
        $result = Datasets::getDataLoadManifest('dataset.products', [
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
            'date' => [
                'identifier' => 'date1',
                'type' => 'DATE',
                'format' => 'yyyy-MM-dd',
                'template' => 'keboola',
                'dateDimension' => 'date 1'
            ]
        ]);
        $this->assertArrayHasKey('dataSetSLIManifest', $result);
        $this->assertArrayHasKey('file', $result['dataSetSLIManifest']);
        $this->assertArrayHasKey('dataSet', $result['dataSetSLIManifest']);
        $this->assertEquals('dataset.products', $result['dataSetSLIManifest']['dataSet']);
        $this->assertArrayHasKey('parts', $result['dataSetSLIManifest']);
        $this->assertCount(4, $result['dataSetSLIManifest']['parts']);

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
    }
}
