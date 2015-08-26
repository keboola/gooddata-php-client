<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Tests;

use Keboola\GoodData\Client;

class ProjectModelTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateProjectModel()
    {
        $client = new Client();
        $client->login(GOODDATA_USERNAME, GOODDATA_PASSWORD);

        $pid = $client->createProject(uniqid(), GOODDATA_AUTH_TOKEN, null, 'TESTING');

        $result = $client->getProjectModel($pid);
        $this->assertArrayHasKey('projectModel', $result);
        $this->assertEmpty($result['projectModel']);

        $model = json_decode(file_get_contents(__DIR__.'/../../data/model.json'));
        $client->updateModel($pid, $model);
        $result = $client->getProjectModel($pid);
        $this->assertArrayHasKey('projectModel', $result);
        $this->assertArrayHasKey('datasets', $result['projectModel']);
        $this->assertCount(4, $result['projectModel']['datasets']);

        $this->assertEmpty($client->deleteProject($pid));
    }
}