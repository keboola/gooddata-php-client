<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData\Test;

use Keboola\GoodData\Utility;

class UtilityTest extends \PHPUnit\Framework\TestCase
{
    public function testUtilitySeemsUtf8()
    {
        $this->assertTrue(Utility::seemsUtf8('PŘÍLIŠ žluťoučký Kůň'));
    }

    public function testUtilityUnaccent()
    {
        $this->assertEquals('PRILIS zlutoucky Kun', Utility::unaccent('PŘÍLIŠ žluťoučký Kůň'));
    }
}
