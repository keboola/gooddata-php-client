<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Tests;

use Keboola\GoodData\Client;

class DataSetTest extends \PHPUnit_Framework_TestCase
{

    public function testCloneProject()
    {
        $client = new Client();
        $client->login(GOODDATA_USERNAME, GOODDATA_PASSWORD);

        $projectName = uniqid();

        $sourceProjectId = $client->createProject($projectName, GOODDATA_AUTH_TOKEN);
        $this->assertNotEmpty($sourceProjectId);

        //@TODO create some datasets in source project

        $targetProjectId = $client->createProject($projectName, GOODDATA_AUTH_TOKEN);
        $this->assertNotEmpty($targetProjectId);

        $client->cloneProject($sourceProjectId, $targetProjectId);

        $this->assertEmpty($client->deleteProject($sourceProjectId));
        $this->assertEmpty($client->deleteProject($targetProjectId));
    }
}