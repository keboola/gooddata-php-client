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

    public function exists($pid, $name, $identifier = null, $template = null)
    {
        if (!$identifier) {
            $identifier = Identifiers::getIdentifier($name);
            if (!$identifier) {
                throw new Exception("Identifier derived from dimension name '$name' is not valid. "
                    . "Choose other name or custom identifier.");
            }
        }

        $call = $this->client->get("/gdc/md/$pid/data/sets");
        $existingDataSets = [];
        foreach ($call['dataSetsInfo']['sets'] as $r) {
            $existingDataSets[] = $r['meta']['identifier'];
        }
        return in_array(Identifiers::getDateDimensionId($name, $template), $existingDataSets);
    }

    public function create($pid, $name, $identifier = null, $template = null)
    {
        if (!$this->exists($pid, $name, $identifier, $template)) {
            $this->client->getDatasets()->executeMaql($pid, sprintf(
                'INCLUDE TEMPLATE "%s" MODIFY (IDENTIFIER "%s", TITLE "%s");',
                self::getTemplateUrn($template),
                $identifier,
                $name
            ));
        }
    }
}
