<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GoodData\Model;

class Label extends Column
{
    /**
     * @var bool
     */
    protected $isLink;

    /**
     * @return boolean
     */
    public function isIsLink()
    {
        return $this->isLink;
    }

    /**
     * @param boolean $isLink
     */
    public function setIsLink(bool $isLink)
    {
        $this->isLink = $isLink;
    }
}
