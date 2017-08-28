<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

class DateDimensions
{
    /** @var  Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public static function getTemplateUrn($template = null)
    {
        $template = $template? strtoupper($template) : 'GOODDATA';
        return "URN:{$template}:DATE";
    }

    public static function getDefaultIdentifier($name)
    {
        $identifier = Identifiers::getIdentifier($name);
        if (!$identifier) {
            throw new Exception("Identifier derived from dimension name '$name' is not valid. "
                . "Choose other name or custom identifier.");
        }
        return $identifier;
    }

    public static function getDefaultIdentifierForReference($dimension, $template = null)
    {
        $result = Identifiers::getIdentifier($dimension);
        if (!empty($template)) {
            $templateId = strtolower($template);
            if ($templateId != 'gooddata') {
                $result .= ".$templateId";
            }
        }
        return $result;
    }

    public function exists($pid, $name, $template = null)
    {
        $call = $this->client->get("/gdc/md/$pid/data/sets");
        $existingDataSets = [];
        foreach ($call['dataSetsInfo']['sets'] as $r) {
            $existingDataSets[] = $r['meta']['identifier'];
        }
        return in_array(Identifiers::getDateDimensionId($name, $template), $existingDataSets);
    }

    public function executeCreateMaql($pid, $name, $identifier, $template = null)
    {
        $this->client->getDatasets()->executeMaql($pid, sprintf(
            'INCLUDE TEMPLATE "%s" MODIFY (IDENTIFIER "%s", TITLE "%s");',
            self::getTemplateUrn($template),
            $identifier,
            $name
        ));
    }

    public function create($pid, $name, $identifier = null, $template = null)
    {
        if (!$identifier) {
            $identifier = $this->getDefaultIdentifier($name);
        }
        if (!$this->exists($pid, $name, $template)) {
            $this->executeCreateMaql($pid, $name, $identifier, $template);
        }
    }
}
