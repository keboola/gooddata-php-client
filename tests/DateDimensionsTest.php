<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Identifiers;
use Keboola\GoodData\TimeDimension;

class DateDimensionTest extends AbstractClientTest
{
    public function testCreateDateDimensionDateOnly()
    {
        $pid = Helper::getSomeProject();
        Helper::cleanUpProject($pid);

        $name = 'd' . uniqid();
        $this->client->createDateDimension(['pid' => $pid, 'name' => $name]);

        $this->assertTrue(in_array(Identifiers::getDateDimensionId($name), $this->getDataSets($pid)));
    }

    public function testCreateDateDimensionTemplateDateOnly()
    {
        $pid = Helper::getSomeProject();
        Helper::cleanUpProject($pid);

        $name = 'd' . uniqid();
        $this->client->createDateDimension(['pid' => $pid, 'name' => $name, 'template' => 'keboola']);

        $this->assertTrue(in_array(Identifiers::getDateDimensionId($name, 'keboola'), $this->getDataSets($pid)));
    }

    public function testCreateDateDimensionTemplateTime()
    {
        $pid = Helper::getSomeProject();
        Helper::cleanUpProject($pid);

        $name = 'd' . uniqid();
        $this->client->createDateDimension(['pid' => $pid, 'name' => $name, 'template' => 'keboola', 'includeTime' => true]);

        $this->assertTrue(in_array(TimeDimension::getTimeDimensionIdentifier($name), $this->getDataSets($pid)));
    }

    protected function getDataSets($pid)
    {
        $call = $this->client->get("/gdc/md/$pid/data/sets");
        $existingDataSets = [];
        foreach ($call['dataSetsInfo']['sets'] as $r) {
            $existingDataSets[] = $r['meta']['identifier'];
        }
        return $existingDataSets;
    }
}
