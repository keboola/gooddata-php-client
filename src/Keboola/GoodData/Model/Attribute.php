<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GoodData\Model;

class Attribute extends Column
{
    protected static $sortOrderDirections = ['ASC', 'DESC'];

    /**
     * @var array
     */
    protected $labels = [];
    /**
     * @var string
     */
    protected $defaultLabel;
    /**
     * @var string
     */
    protected $sortOrderLabel;
    /**
     * @var string
     */
    protected $sortOrderDirection;

    /**
     * @return array
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * @param Label $label
     */
    public function addLabel(Label $label)
    {
        $this->labels[$label->getIdentifier()] = $label;
    }

    /**
     * @return string
     */
    public function getDefaultLabel()
    {
        return $this->defaultLabel;
    }

    /**
     * @param string $defaultLabel
     * @throws Exception
     */
    public function setDefaultLabel($defaultLabel)
    {
        if (!in_array($defaultLabel, array_keys($this->labels))) {
            throw new Exception("Identifier '{$defaultLabel}' not found in labels and cannot be set as default label");
        }
        $this->defaultLabel = $defaultLabel;
    }

    /**
     * @return string
     */
    public function getSortOrderLabel()
    {
        return $this->sortOrderLabel;
    }

    /**
     * @param string $sortOrderLabel
     * @throws Exception
     */
    public function setSortOrderLabel($sortOrderLabel)
    {
        if (!in_array($sortOrderLabel, array_keys($this->labels))) {
            throw new Exception("Identifier '{$sortOrderLabel}' not found in labels and cannot be set as sort order label");
        }
        $this->sortOrderLabel = $sortOrderLabel;
    }

    /**
     * @return string
     */
    public function getSortOrderDirection()
    {
        return $this->sortOrderDirection;
    }

    /**
     * @param string $sortOrderDirection
     * @throws Exception
     */
    public function setSortOrderDirection($sortOrderDirection)
    {
        if (!in_array($sortOrderDirection, self::$sortOrderDirections)) {
            throw new Exception("Value '{$sortOrderDirection}' is not allowed for attribute sort order direction");
        }
        $this->sortOrderDirection = $sortOrderDirection;
    }
}
