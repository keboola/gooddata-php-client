<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GoodData\Model;

class DataSet
{
    /**
     * @var string
     */
    protected $identifier;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var string
     */
    protected $connectionPoint;
    /**
     * @var array
     */
    protected $facts = [];
    /**
     * @var array
     */
    protected $attributes = [];
    /**
     * @var array
     */
    protected $references = [];

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getConnectionPoint()
    {
        return $this->connectionPoint;
    }

    /**
     * @return array
     */
    public function getFacts()
    {
        return $this->facts;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return array
     */
    public function getReferences()
    {
        return $this->references;
    }

    /**
     * @param Attribute $attribute
     */
    public function addAttribute(Attribute $attribute)
    {
        $this->attributes[$attribute->getIdentifier()] = $attribute;
    }

    /**
     * @param Fact $fact
     */
    public function addFact(Fact $fact)
    {
        $this->facts[$fact->getIdentifier()] = $fact;
    }

    /**
     * @param $connectionPoint
     * @throws Exception
     */
    public function setConnectionPoint($connectionPoint)
    {
        if (!in_array($connectionPoint, array_keys($this->attributes))) {
            throw new Exception("Identifier '{$connectionPoint}' not found in attributes and cannot be set as connection point'");
        }
        $this->connectionPoint = $connectionPoint;
    }

    public function addReference($reference)
    {
        if (!in_array($reference, $this->references)) {
            $this->references[] = $reference;
        }
    }
}
