<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Tests;

use Keboola\GoodData\Client;

class ProjectTest extends \PHPUnit_Framework_TestCase
{

    public function testCreateGetAndDeleteProject()
    {
        $client = new Client();
        $client->login(GOODDATA_USERNAME, GOODDATA_PASSWORD);

        $projectName = uniqid();

        $projectId = $client->createProject($projectName, GOODDATA_AUTH_TOKEN);
        $this->assertNotEmpty($projectId);

        $result = $client->getProject($projectId);
        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('meta', $result['project']);
        $this->assertArrayHasKey('title', $result['project']['meta']);
        $this->assertEquals($projectName, $result['project']['meta']['title']);

        $this->assertEmpty($client->deleteProject($projectId));
    }
}