<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Exception;
use Keboola\GoodData\Projects;

class ProjectsTest extends AbstractClientTest
{
    public function testProjectsGetPidFromUri()
    {
        $pid = uniqid();
        $this->assertEquals($pid, Projects::getPidFromUri("/gdc/projects/$pid"));
    }

    public function testProjectsCreateProject()
    {
        $projects = new Projects($this->client);

        $title = KBGDC_PROJECTS_PREFIX . uniqid();
        $description = uniqid();
        $pid = $projects->createProject($title, KBGDC_AUTH_TOKEN, $description, true);

        $result = $this->client->get("/gdc/projects/$pid");
        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('content', $result['project']);
        $this->assertArrayHasKey('state', $result['project']['content']);
        $this->assertEquals('ENABLED', $result['project']['content']['state']);
        $this->assertArrayHasKey('authorizationToken', $result['project']['content']);
        $this->assertEquals(KBGDC_AUTH_TOKEN, $result['project']['content']['authorizationToken']);
        $this->assertArrayHasKey('meta', $result['project']);
        $this->assertArrayHasKey('title', $result['project']['meta']);
        $this->assertEquals($title, $result['project']['meta']['title']);
        $this->assertArrayHasKey('summary', $result['project']['meta']);
        $this->assertEquals($description, $result['project']['meta']['summary']);

        $this->client->delete("/gdc/projects/$pid");
    }

    public function testProjectsDeleteProject()
    {
        $pid = Helper::createProject();

        $result = $this->client->get("/gdc/projects/$pid");
        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('content', $result['project']);
        $this->assertArrayHasKey('state', $result['project']['content']);
        $this->assertEquals('ENABLED', $result['project']['content']['state']);

        $projects = new Projects($this->client);
        $projects->deleteProject($pid);

        $result = $this->client->get("/gdc/projects/$pid");
        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('content', $result['project']);
        $this->assertArrayHasKey('state', $result['project']['content']);
        $this->assertEquals('DELETED', $result['project']['content']['state']);
    }

    public function testProjectsGetProject()
    {
        $pid = Helper::getSomeProject();

        $projects = new Projects($this->client);
        $result = $projects->getProject($pid);
        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('content', $result['project']);
        $this->assertArrayHasKey('state', $result['project']['content']);
        $this->assertEquals('ENABLED', $result['project']['content']['state']);
    }

    public function testProjectsGetUsersForProjectYield()
    {
        $projects = new Projects($this->client);

        $pid = Helper::getSomeProject();
        $usersCount = 0;
        foreach ($this->client->getProjects()->getUsersForProjectYield($pid, 1) as $usersBatch) {
            $usersCount += count($usersBatch);
        }

        $uid1 = Helper::createUser();
        Helper::getClient()->getProjects()->addUser($pid, $uid1);
        $count = 0;
        foreach ($projects->getUsersForProjectYield($pid, 1) as $usersBatch) {
            $count += count($usersBatch);
        }
        $this->assertEquals($usersCount + 1, $count);

        $uid2 = Helper::createUser();
        Helper::getClient()->getProjects()->addUser($pid, $uid2);
        $count = 0;
        foreach ($projects->getUsersForProjectYield($pid, 1) as $usersBatch) {
            $count += count($usersBatch);
        }
        $this->assertEquals($usersCount + 2, $count);

        $count = 0;
        foreach ($projects->getUsersForProjectYield($pid, 2) as $usersBatch) {
            $count += count($usersBatch);
        }
        $this->assertEquals($usersCount + 2, $count);

        $count = 0;
        foreach ($projects->getUsersForProjectYield($pid, 3) as $usersBatch) {
            $count += count($usersBatch);
        }
        $this->assertEquals($usersCount + 2, $count);

        $count = 0;
        foreach ($projects->getUsersForProjectYield($pid) as $usersBatch) {
            $count += count($usersBatch);
        }
        $this->assertEquals($usersCount + 2, $count);
    }

    public function testProjectsClone()
    {
        // Get and clean first project
        $pid1 = Helper::getSomeProject();
        Helper::cleanUpProject($pid1);
        $result = $this->client->get("/gdc/md/$pid1/data/sets");
        $this->assertArrayHasKey('dataSetsInfo', $result);
        $this->assertArrayHasKey('sets', $result['dataSetsInfo']);
        $this->assertCount(0, $result['dataSetsInfo']['sets']);

        // Build model in first project
        Helper::initProjectModel($pid1);
        $result = $this->client->get("/gdc/md/$pid1/data/sets");
        $this->assertArrayHasKey('dataSetsInfo', $result);
        $this->assertArrayHasKey('sets', $result['dataSetsInfo']);
        $this->assertCount(4, $result['dataSetsInfo']['sets']);

        // Create second project
        $pid2 = Helper::createProject();
        $result = $this->client->get("/gdc/md/$pid2/data/sets");
        $this->assertArrayHasKey('dataSetsInfo', $result);
        $this->assertArrayHasKey('sets', $result['dataSetsInfo']);
        $this->assertCount(0, $result['dataSetsInfo']['sets']);

        // Execute cloning
        $projects = new Projects($this->client);
        $projects->cloneProject($pid1, $pid2);

        $result = $this->client->get("/gdc/md/$pid2/data/sets");
        $this->assertArrayHasKey('dataSetsInfo', $result);
        $this->assertArrayHasKey('sets', $result['dataSetsInfo']);
        $this->assertCount(4, $result['dataSetsInfo']['sets']);
    }

    public function testProjectsValidate()
    {
        $pid = Helper::getSomeProject();

        $projects = new Projects($this->client);
        $result = $projects->validate($pid);
        $this->assertArrayHasKey('error_found', $result);
        $this->assertArrayHasKey('fatal_error_found', $result);
        $this->assertArrayHasKey('results', $result);
    }

    public function testProjectsIsAccessible()
    {
        $pid = Helper::getSomeProject();
        $user = Helper::getSomeUser();

        $client = clone $this->client;
        $client->login($user['email'], $user['password']);
        $projects = new Projects($client);

        Helper::getClient()->getProjects()->addUser($pid, $user['uid']);
        $this->assertTrue($projects->isAccessible($pid));
        Helper::getClient()->getProjects()->disableUser($user['uid'], $pid);
        $this->assertFalse($projects->isAccessible($pid));
    }

    public function testProjectsIsAccessibleByUser()
    {
        $uid = Helper::createUser();
        $pid = Helper::getSomeProject();
        $projects = new Projects($this->client);

        $this->assertFalse($projects->isAccessibleByUser($pid, $uid));
        Helper::getClient()->getProjects()->addUser($pid, $uid);
        $this->assertTrue($projects->isAccessibleByUser($pid, $uid));
        $this->assertFalse($projects->isAccessibleByUser($pid, uniqid()));
    }

    public function testProjectsLeave()
    {
        $email = uniqid().'@'.KBGDC_USERS_DOMAIN;
        $pass = uniqid();
        $uid = Helper::createUser($email, $pass);
        $pid = Helper::getSomeProject();
        Helper::getClient()->getProjects()->addUser($pid, $uid);

        $projects = new Projects($this->client);
        $this->client->login($email, $pass);
        $this->client->get("/gdc/projects/$pid/users");
        $projects->leaveProject($pid, $uid);
        try {
            $this->client->get("/gdc/projects/$pid/users");
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testProjectsInvite()
    {
        $pid = Helper::getSomeProject();
        $email = uniqid().'@'.KBGDC_USERS_DOMAIN;
        $projects = new Projects($this->client);
        $projects->inviteUser($pid, $email);

        $invitationFound = false;
        $result = $this->client->get("/gdc/projects/$pid/invitations");
        foreach ($result['invitations'] as $r) {
            if ($email == $r['invitation']['content']['email']) {
                $invitationFound = true;
            }
        }
        $this->assertTrue($invitationFound);
    }
    
    public function testProjectsCancelInvitation()
    {
        $pid = Helper::getSomeProject();
        $email = uniqid().'@'.KBGDC_USERS_DOMAIN;

        $result = $this->client->get("/gdc/projects/$pid/roles");
        $this->client->post("/gdc/projects/$pid/invitations", [
            'invitations' => [
                [
                    'invitation' => [
                        'content' => [
                            'email' => $email,
                            'role' => $result['projectRoles']['roles'][0]
                        ]
                    ]
                ]
            ]
        ]);

        $projects = new Projects($this->client);
        $projects->cancelInvitation($pid, $email);

        $invitationFound = false;
        $result = $this->client->get("/gdc/projects/$pid/invitations");
        foreach ($result['invitations'] as $r) {
            if ($email == $r['invitation']['content']['email']) {
                $invitationFound = true;
                $this->assertEquals('CANCELED', $r['invitation']['content']['status']);
            }
        }
        $this->assertTrue($invitationFound);
    }
}
