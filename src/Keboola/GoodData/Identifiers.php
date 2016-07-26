<?php
/**
 * Created by PhpStorm.
 * User: JakubM
 * Date: 30.01.14
 * Time: 11:03
 */

namespace Keboola\GoodData;

class Identifiers
{
    public static function getIdentifier($name)
    {
        $string = Utility::unaccent($name);
        $string = preg_replace('/[^\w\d_]/', '', $string);
        $string = preg_replace('/^[\d_]*/', '', $string);
        return strtolower($string);
    }

    public static function getDatasetId($name)
    {
        return 'dataset.' . Identifiers::getIdentifier($name);
    }

    public static function getImplicitConnectionPointId($tableName)
    {
        return sprintf('attr.%s.factsof', self::getIdentifier($tableName));
    }

    public static function getAttributeId($tableName, $attrName)
    {
        return sprintf('attr.%s.%s', Identifiers::getIdentifier($tableName), Identifiers::getIdentifier($attrName));
    }

    public static function getFactId($tableName, $attrName)
    {
        return sprintf('fact.%s.%s', self::getIdentifier($tableName), self::getIdentifier($attrName));
    }

    public static function getLabelId($tableName, $attrName)
    {
        return sprintf('label.%s.%s', self::getIdentifier($tableName), self::getIdentifier($attrName));
    }

    public static function getRefLabelId($tableName, $refName, $attrName)
    {
        return sprintf(
            'label.%s.%s.%s',
            self::getIdentifier($tableName),
            self::getIdentifier($refName),
            self::getIdentifier($attrName)
        );
    }
    
    public static function getDateDimensionGrainId($name, $template = null)
    {
        $template = strtolower($template);
        return self::getIdentifier($name) . (($template && $template != 'gooddata') ? ".$template" : "");
    }

    public static function getDateDimensionId($name, $template = null)
    {
        $template = strtolower($template);
        return self::getIdentifier($name)
            . (($template && $template != 'gooddata') ? '.' . $template : null) . '.dataset.dt';
    }
}
