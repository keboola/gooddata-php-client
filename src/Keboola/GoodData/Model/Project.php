<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GoodData\Model;

class Project
{
    /**
     * @var array
     */
    protected $dataSets = [];
    /**
     * @var array
     */
    protected $dateDimensions = [];

    /**
     * @return array
     */
    public function getDataSets()
    {
        return $this->dataSets;
    }

    /**
     * @param DataSet $dataSet
     */
    public function addDataSet(DataSet $dataSet)
    {
        $this->dataSets[$dataSet->getIdentifier()] = $dataSet;
    }

    /**
     * @return array
     */
    public function getDateDimensions()
    {
        return $this->dateDimensions;
    }

    /**
     * @param DateDimension $dateDimension
     */
    public function addDateDimension(DateDimension $dateDimension)
    {
        $this->dateDimensions[$dateDimension->getName()] = $dateDimension;
    }
}
