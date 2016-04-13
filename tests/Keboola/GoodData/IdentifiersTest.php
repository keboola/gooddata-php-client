<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Identifiers;

class IdentifiersTest extends \PHPUnit_Framework_TestCase
{
    public function testIdentifiersGetIdentifier()
    {
        $this->assertEquals('zcrd', Identifiers::getIdentifier('ž č ř Ď'));
    }

    public function testIdentifiersGetDatasetId()
    {
        $this->assertEquals('dataset.zcrd', Identifiers::getDatasetId('ž č ř Ď'));
    }

    public function testIdentifiersGetImplicitConnectionPointId()
    {
        $this->assertEquals('attr.zcrd.factsof', Identifiers::getImplicitConnectionPointId('ž č ř Ď'));
    }

    public function testIdentifiersGetAttributeId()
    {
        $this->assertEquals('attr.zcrd.tn', Identifiers::getAttributeId('ž č ř Ď', 'ŤŇ'));
    }

    public function testIdentifiersGetFactId()
    {
        $this->assertEquals('fact.zcrd.tn', Identifiers::getFactId('ž č ř Ď', 'ŤŇ'));
    }

    public function testIdentifiersGetLabelId()
    {
        $this->assertEquals('label.zcrd.tn', Identifiers::getLabelId('ž č ř Ď', 'ŤŇ'));
    }

    public function testIdentifiersGetRefLabelId()
    {
        $this->assertEquals('label.zcrd.tn.uu', Identifiers::getRefLabelId('ž č ř Ď', 'ŤŇ', 'ú Ů'));
    }

    public function testIdentifiersGetDateDimensionGrainId()
    {
        $this->assertEquals('zcrd.template', Identifiers::getDateDimensionGrainId('ž č ř Ď', 'template'));
    }

    public function testIdentifiersGetDateDimensionId()
    {
        $this->assertEquals('zcrd.template.dataset.dt', Identifiers::getDateDimensionId('ž č ř Ď', 'template'));
    }

    public function testIdentifiersGetDateFactId()
    {
        $this->assertEquals('dt.zcrd.uu', Identifiers::getDateFactId('ž č ř Ď', 'ú Ů'));
    }
}
