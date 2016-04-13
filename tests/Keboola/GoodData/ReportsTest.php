<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Reports;
use Keboola\GoodData\WebDav;

class ReportsTest extends AbstractClientTest
{
    public function testReportsExecute()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);

        $uri = Helper::createReport($pid);

        $reports = new Reports($this->client);
        $reports->execute($uri);
        $this->assertTrue(true);
    }

    public function testReportsExport()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);

        // Create few MB csv
        $csvFile = Helper::getTemp()->createTmpFile();
        $fp = fopen($csvFile, 'w');
        fwrite($fp, "id,name".PHP_EOL);
        for ($i=1; $i<200000; $i++) {
            fwrite($fp, "$i,".uniqid().PHP_EOL);
        }
        shell_exec("mv $csvFile ".Helper::getTemp()->getTmpFolder()."/categories.csv");
        fclose($fp);

        $dirName = uniqid();
        $webDav = new WebDav(KBGDC_USERNAME, KBGDC_PASSWORD);
        $webDav->createFolder($dirName);
        $webDav->upload(__DIR__.'/../../data/products.csv', $dirName);
        $webDav->upload(Helper::getTemp()->getTmpFolder()."/categories.csv", $dirName);
        $webDav->upload(__DIR__.'/../../data/upload_info.json', $dirName);
        Helper::getClient()->getDatasets()->loadData($pid, $dirName);

        $uri = Helper::createReport($pid);
        $report = Helper::getClient()->get($uri);

        $reports = new Reports($this->client);
        $resultUri = $reports->export($pid, $report['report']['content']['definitions'][0]);

        $csvFile = Helper::getTemp()->getTmpFolder() . '/' . uniqid() . '.csv';
        $this->client->getToFile($resultUri, $csvFile);

        $this->assertTrue(file_exists($csvFile));
        $this->assertGreaterThan(4800000, filesize($csvFile));
    }
}
