<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Reports;

class ReportsTest extends AbstractClientTest
{
    public function testReportsExecute()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);

        $uri = Helper::createReport($this->client, $pid);

        $reports = new Reports($this->client);
        $reports->execute($uri);
        $this->assertTrue(true);
    }

    public function testReportsExport()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        Helper::loadData($pid);

        $uri = Helper::createReport($this->client, $pid);
        $report = Helper::getClient()->get($uri);

        $reports = new Reports($this->client);
        $result = $reports->export($pid, $report['report']['content']['definitions'][0]);
        $result = $this->client->request('GET', $result);
        $csv = str_getcsv($result->getBody());
        $this->assertCount(2, $csv);
        $this->assertArrayHasKey('Id', $csv[0]);
        $this->assertArrayHasKey('Name', $csv[0]);
    }
}
