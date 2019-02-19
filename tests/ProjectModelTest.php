<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

class ProjectModelTest extends AbstractClientTest
{
    public function testProjectModelUpdateDataset()
    {
        $pid = Helper::getSomeProject();
        Helper::initProjectModel($pid);
        
        $result = $this->client->getProjectModel()->view($pid);
        $this->assertArrayHasKey('projectModel', $result);
        $this->assertArrayHasKey('datasets', $result['projectModel']);
        $categoriesFound = false;
        foreach ($result['projectModel']['datasets'] as $dataSet) {
            $this->assertArrayHasKey('dataset', $dataSet);
            $this->assertArrayHasKey('title', $dataSet['dataset']);
            if ($dataSet['dataset']['title'] == 'Categories') {
                $categoriesFound = true;
                $this->assertArrayHasKey('attributes', $dataSet['dataset']);
            }
        }
        $this->assertTrue($categoriesFound);


        // Update dataset
        $this->client->getProjectModel()->updateDataSet($pid, [
            "identifier" => "dataset.categories",
            "title" => "Categories",
            "anchor" => [
                "attribute" => [
                    "identifier" => "attr.categories.name",
                    "title" => "Name",
                    "defaultLabel" => "label.categories.name",
                    "labels" => [
                        [
                            "label" => [
                                "identifier" => "label.categories.name",
                                "title" => "Name",
                                "type" => "GDC.text"
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $result = $this->client->getProjectModel()->view($pid);
        $this->assertArrayHasKey('projectModel', $result);
        $this->assertArrayHasKey('datasets', $result['projectModel']);
        $categoriesFound = false;
        $dataSetsCount = count($result['projectModel']['datasets']);
        foreach ($result['projectModel']['datasets'] as $dataSet) {
            $this->assertArrayHasKey('dataset', $dataSet);
            $this->assertArrayHasKey('title', $dataSet['dataset']);
            if ($dataSet['dataset']['title'] == 'Categories') {
                $categoriesFound = true;
                $this->assertArrayNotHasKey('attributes', $dataSet['dataset']);
            }
        }
        $this->assertTrue($categoriesFound);


        // Add new dataset
        $this->client->getProjectModel()->updateDataSet($pid, [
            "identifier" => "dataset.test",
            "title" => "Test",
            "anchor" => [
                "attribute" => [
                    "identifier" => "attr.test.name",
                    "title" => "Name",
                    "defaultLabel" => "label.test.name",
                    "labels" => [
                        [
                            "label" => [
                                "identifier" => "label.test.name",
                                "title" => "Name",
                                "type" => "GDC.text"
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $result = $this->client->getProjectModel()->view($pid);
        $this->assertArrayHasKey('projectModel', $result);
        $this->assertArrayHasKey('datasets', $result['projectModel']);
        $dataSetFound = false;
        foreach ($result['projectModel']['datasets'] as $dataSet) {
            $this->assertArrayHasKey('dataset', $dataSet);
            $this->assertArrayHasKey('title', $dataSet['dataset']);
            if ($dataSet['dataset']['title'] == 'Test') {
                $dataSetFound= true;
            }
        }
        $this->assertTrue($dataSetFound);
        $this->assertEquals($dataSetsCount+1, count($result['projectModel']['datasets']));


        // Drop dataset
        $this->client->getProjectModel()->dropDataSet($pid, 'Products');

        $result = $this->client->getProjectModel()->view($pid);
        $this->assertArrayHasKey('projectModel', $result);
        $this->assertArrayHasKey('datasets', $result['projectModel']);
        foreach ($result['projectModel']['datasets'] as $dataSet) {
            if ($dataSet['dataset']['title'] == 'Products') {
                $this->fail();
            }
        }
        $this->assertTrue(true);
        $this->assertEquals($dataSetsCount, count($result['projectModel']['datasets']));
    }
}
