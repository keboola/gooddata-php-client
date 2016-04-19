<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Identifiers;
use Keboola\GoodData\TimeDimension;

class TimeDimensionTest extends AbstractClientTest
{
    public function testTimeDimensionGetTimeDimensionIdentifier()
    {
        $dim = 'd'.uniqid();
        $this->assertEquals("dataset.time.$dim", TimeDimension::getTimeDimensionIdentifier($dim));
    }

    public function testTimeDimensionGetTimeFactIdentifier()
    {
        $table = 't'.uniqid();
        $attr = 'a'.uniqid();
        $this->assertEquals("tm.dt.$table.$attr", TimeDimension::getTimeFactIdentifier($table, $attr));
    }

    public function testTimeDimensionGetCreateMaql()
    {
        $id = 't'.uniqid();
        $name = 'a'.uniqid();
        $result = TimeDimension::getCreateMaql($id, $name);
        $this->assertStringStartsWith("CREATE DATASET {dataset.time.$id} VISUAL(TITLE \"Time ($name)\"", $result);
    }

    public function testTimeDimensionGetLDM()
    {
        $id = 't'.uniqid();
        $name = 'n'.uniqid();
        $result = TimeDimension::getLDM($id, $name);
        $this->assertArrayHasKey('identifier', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('anchor', $result);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertCount(5, $result['attributes']);
    }

    public function testTimeDimensionGeDataLoadManifest()
    {
        $id = 'd'.uniqid();
        $result = TimeDimension::getDataLoadManifest($id);
        $result = json_decode($result, true);
        $this->assertArrayHasKey('dataSetSLIManifest', $result);
        $this->assertArrayHasKey('parts', $result['dataSetSLIManifest']);
        $this->assertCount(9, $result['dataSetSLIManifest']['parts']);
        $this->assertArrayHasKey('file', $result['dataSetSLIManifest']);
        $this->assertArrayHasKey('dataSet', $result['dataSetSLIManifest']);
    }

    public function testTimeDimensionLoadData()
    {
        $pid = Helper::getSomeProject();
        Helper::cleanUpProject($pid);
        $name = 't'.uniqid();

        $dir = sys_get_temp_dir().'/'.uniqid();
        mkdir($dir);

        $timeDimension = new TimeDimension($this->client);
        $timeDimension->create($pid, $name);
        $timeDimension->loadData($pid, $name, $dir);

        $result = $this->client->get("/gdc/md/$pid/data/sets");
        $this->assertArrayHasKey('dataSetsInfo', $result);
        $this->assertArrayHasKey('sets', $result['dataSetsInfo']);
        $this->assertCount(1, $result['dataSetsInfo']['sets']);
        $this->assertArrayHasKey('meta', $result['dataSetsInfo']['sets'][0]);
        $this->assertArrayHasKey('identifier', $result['dataSetsInfo']['sets'][0]['meta']);
        $this->assertEquals("dataset.time.$name", $result['dataSetsInfo']['sets'][0]['meta']['identifier']);
        $this->assertArrayHasKey('lastUpload', $result['dataSetsInfo']['sets'][0]);
        $this->assertArrayHasKey('dataUploadShort', $result['dataSetsInfo']['sets'][0]['lastUpload']);
        $this->assertArrayHasKey('status', $result['dataSetsInfo']['sets'][0]['lastUpload']['dataUploadShort']);
        $this->assertEquals("OK", $result['dataSetsInfo']['sets'][0]['lastUpload']['dataUploadShort']['status']);
    }
}
