<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Exception;
use Keboola\GoodData\Filters;
use Keboola\GoodData\Identifiers;

class FiltersTest extends AbstractClientTest
{
    public function testFiltersCreate()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        Helper::loadData($pid);

        $filter = uniqid();

        $attrIdentifier = Identifiers::getAttributeId("categories", "id");
        $attrUri = $this->client->getDatasets()->getUriForIdentifier($pid, $attrIdentifier);
        $overAttrUri = $this->client->getDatasets()->getUriForIdentifier(
            $pid,
            Identifiers::getAttributeId("products", "id")
        );
        $toAttrUri = $this->client->getDatasets()->getUriForIdentifier($pid, "attr.prodakts.name");
        $attrValueUri = $this->client->getDatasets()->getAttributeValueUri($pid, $attrIdentifier, 'c1');

        $filters = new Filters($this->client);
        $uri = $filters->create($pid, $filter, $attrUri, '=', $attrValueUri, $overAttrUri, $toAttrUri);

        $result = $this->client->get($uri);
        $this->assertArrayHasKey('userFilter', $result);
        $this->assertArrayHasKey('meta', $result['userFilter']);
        $this->assertArrayHasKey('title', $result['userFilter']['meta']);
        $this->assertEquals($filter, $result['userFilter']['meta']['title']);
        $this->assertEquals(
            "([$attrUri] = [$attrValueUri]) OVER [$overAttrUri] TO [$toAttrUri]",
            $result['userFilter']['content']['expression']
        );
    }

    public function testFiltersDelete()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        Helper::loadData($pid);

        $filter = uniqid();

        $attrIdentifier = Identifiers::getAttributeId("categories", "id");
        $attrUri = $this->client->getDatasets()->getUriForIdentifier($pid, $attrIdentifier);
        $attrValueUri = $this->client->getDatasets()->getAttributeValueUri($pid, $attrIdentifier, 'c1');
        
        $uri = $this->client->getFilters()->create($pid, $filter, $attrUri, '=', $attrValueUri);
        $this->assertNotEmpty($this->client->get($uri));

        $filters = new Filters($this->client);
        $filters->delete($uri);

        try {
            $this->client->get($uri);
            $this->fail();
        } catch (Exception $e) {
        }
    }

    public function testFiltersAssignToUser()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        Helper::loadData($pid);

        $user = Helper::getSomeUser();

        $filter1 = uniqid();
        $filter2 = uniqid();

        $attrIdentifier = Identifiers::getAttributeId("categories", "id");
        $attrUri = $this->client->getDatasets()->getUriForIdentifier($pid, $attrIdentifier);
        $attrValueUri = $this->client->getDatasets()->getAttributeValueUri($pid, $attrIdentifier, 'c1');

        $uri1 = $this->client->getFilters()->create($pid, $filter1, $attrUri, '=', $attrValueUri);
        $uri2 = $this->client->getFilters()->create($pid, $filter2, $attrUri, '=', $attrValueUri);
        $userUri = "/gdc/account/profile/{$user['uid']}";

        $filters = new Filters($this->client);
        $result = $this->client->get("/gdc/md/$pid/userfilters");
        $result = array_column($result['userFilters']['items'], 'userFilters', 'user');
        $this->assertArrayNotHasKey($userUri, $result);

        $filters->assignToUser($pid, $user['uid'], [$uri1]);
        $result = $this->client->get("/gdc/md/$pid/userfilters");
        $result = array_column($result['userFilters']['items'], 'userFilters', 'user');
        $this->assertArrayHasKey($userUri, $result);
        $this->assertEquals([$uri1], $result[$userUri], '', 0, 10, true);

        $filters->assignToUser($pid, $user['uid'], [$uri2]);
        $result = $this->client->get("/gdc/md/$pid/userfilters");
        $result = array_column($result['userFilters']['items'], 'userFilters', 'user');
        $this->assertArrayHasKey($userUri, $result);
        $this->assertEquals([$uri2], $result[$userUri], '', 0, 10, true);

        $filters->assignToUser($pid, $user['uid'], [$uri1, $uri2]);
        $result = $this->client->get("/gdc/md/$pid/userfilters");
        $result = array_column($result['userFilters']['items'], 'userFilters', 'user');
        $this->assertArrayHasKey($userUri, $result);
        $this->assertEquals([$uri1, $uri2], $result[$userUri], '', 0, 10, true);

        $filters->assignToUser($pid, $user['uid'], []);
        $result = $this->client->get("/gdc/md/$pid/userfilters");
        $result = array_column($result['userFilters']['items'], 'userFilters', 'user');
        $this->assertArrayNotHasKey($userUri, $result);
    }

    public function testFiltersGetForUser()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        Helper::loadData($pid);

        $user = Helper::getSomeUser();

        $filter1 = uniqid();
        $filter2 = uniqid();

        $attrIdentifier = Identifiers::getAttributeId("categories", "id");
        $attrUri = $this->client->getDatasets()->getUriForIdentifier($pid, $attrIdentifier);
        $attrValueUri = $this->client->getDatasets()->getAttributeValueUri($pid, $attrIdentifier, 'c1');

        $uri1 = $this->client->getFilters()->create($pid, $filter1, $attrUri, '=', $attrValueUri);
        $uri2 = $this->client->getFilters()->create($pid, $filter2, $attrUri, '=', $attrValueUri);
        $this->client->getFilters()->assignToUser($pid, $user['uid'], [$uri1, $uri2]);

        $filters = new Filters($this->client);
        $this->assertEquals([$uri1, $uri2], $filters->getForUser($pid, $user['uid']), '', 0, 10, true);
    }
}
