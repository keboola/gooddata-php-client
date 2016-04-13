<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Datasets;

class ModelTest extends AbstractClientTest
{
    public function testModelGetUriForIdentifier()
    {
        $pid = Helper::getSomeProject();
        $dataset = 'dataset.d'.uniqid();
        $this->client->getDatasets()->executeMaql($pid, "CREATE DATASET {{$dataset}} VISUAL (TITLE \"Test $dataset\");");

        $model = new Datasets($this->client);
        $uri = $model->getUriForIdentifier($pid, $dataset);
        $this->assertNotEmpty($uri);
        $result = $this->client->get($uri);
        $this->assertArrayHasKey('dataSet', $result);
        $this->assertArrayHasKey('meta', $result['dataSet']);
        $this->assertArrayHasKey('identifier', $result['dataSet']['meta']);
        $this->assertEquals($dataset, $result['dataSet']['meta']['identifier']);
    }

    public function testModelGetAttributeValueUri()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        Helper::loadData($pid);

        $attrIdentifier = "attr.categories.id";
        $model = new Datasets($this->client);
        $uri = $model->getAttributeValueUri($pid, $attrIdentifier, 'c1');

        $attr = $this->client->get($this->client->getDatasets()->getUriForIdentifier($pid, $attrIdentifier));
        $result = $this->client->get($attr['attribute']['content']['displayForms'][0]['links']['elements']
            . '?filter='.urlencode('c1'));

        $elements = array_column($result['attributeElements']['elements'], 'uri', 'title');
        $this->assertArrayHasKey('c1', $elements);
        $this->assertEquals($uri, $elements['c1']);
    }

    public function testModelExecuteMaql()
    {
        $pid = Helper::getSomeProject();
        $dataset = 'dataset.d'.uniqid();

        $model = new Datasets($this->client);
        $model->executeMaql($pid, "CREATE DATASET {{$dataset}} VISUAL (TITLE \"Test $dataset\");");

        $found = false;
        $result = $this->client->get("/gdc/md/$pid/data/sets");
        foreach ($result['dataSetsInfo']['sets'] as $d) {
            if ($d['meta']['identifier'] == $dataset) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function testModelOptimizeSliHash()
    {
        $pid = Helper::getSomeProject();

        $model = new Datasets($this->client);
        $model->optimizeSliHash($pid);
        $this->assertTrue(true);
    }

    public function testModelSynchronize()
    {
        $pid = Helper::getSomeProject();
        $dataset = 'dataset.d'.uniqid();
        $this->client->getDatasets()->executeMaql($pid, "CREATE DATASET {{$dataset}} VISUAL (TITLE \"Test $dataset\");");

        $model = new Datasets($this->client);
        $model->synchronize($pid, $dataset);
        $this->assertTrue(true);
    }
}
