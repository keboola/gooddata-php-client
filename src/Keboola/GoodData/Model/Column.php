<?php
/**
 * @package gooddata-php-client
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GoodData\Model;

abstract class Column
{
    protected static $dataTypes = ['BIGINT', 'INT', 'DATE', 'DECIMAL', 'VARCHAR'];

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
    protected $dataType;
    /**
     * @var string
     */
    protected $folder;

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
    public function getDataType()
    {
        return $this->dataType;
    }

    /**
     * @param string $dataType
     * @param null $size
     * @param null $scale
     * @throws Exception
     */
    public function setDataType($dataType, $size = null, $scale = null)
    {
        if (!in_array($dataType, self::$dataTypes)) {
            throw new Exception("Value '{$dataType}' not allowed as data type");
        }

        if ($dataType == 'DECIMAL') {
            if (!$size) {
                $dataType .= "(8,2)";
            } else {
                if (!is_int($size)) {
                    throw new Exception("Size '{$size}' of DECIMAL must integer");
                }
                if (!is_int($scale) || $scale < 1 || $scale > 6) {
                    throw new Exception("Scale '{$scale}' of DECIMAL must be number between 1 and 6");
                }
                $dataType .= "({$size},{$scale})";
            }
        } elseif ($dataType == 'VARCHAR') {
            if (!$size) {
                $dataType .= "(128)";
            } else {
                if (!is_int($size) || $size < 1 || $size > 255) {
                    throw new Exception("Size '{$size}' of INT must be number between 1 and 255");
                }
                $dataType .= "({$size})";
            }
        }

        $this->dataType = $dataType;
    }

    /**
     * @return string
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param string $folder
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
    }
}
