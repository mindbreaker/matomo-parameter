<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\UrlParameter;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Db;

class Archiver extends \Piwik\Plugin\Archiver
{
    const RECORD_NAME_PARAMETERS = 'UrlParameter_parameters';

    public function aggregateDayReport()
    {
        $from = Common::prefixTable('log_link_visit_action') . ' log_link_visit_action'
            . ' JOIN ' . Common::prefixTable('log_action') . ' log_action'
            . '   ON log_link_visit_action.idaction_url = log_action.idaction';

        $query = $this->getLogAggregator()->generateQuery(
            'log_action.name as url, count(*) as nb_hits',
            $from,
            '',
            'log_action.idaction',
            ''
        );

        $rows = Db::fetchAll($query['sql'], $query['bind']);

        $parameterData = $this->extractParameters($rows);
        $table = $this->buildDataTable($parameterData);

        $this->getProcessor()->insertBlobRecord(
            self::RECORD_NAME_PARAMETERS,
            $table->getSerialized()
        );
    }

    public function aggregateMultipleReports()
    {
        $this->getProcessor()->aggregateDataTableRecords(
            [self::RECORD_NAME_PARAMETERS]
        );
    }

    /**
     * Extract URL parameters from query results.
     *
     * @param array $rows
     * @return array Associative array: 'paramName=value' => nb_hits
     */
    private function extractParameters(array $rows): array
    {
        $parameterData = [];

        foreach ($rows as $row) {
            $url = $row['url'];
            $hits = (int) $row['nb_hits'];

            $pos = strpos($url, '?');
            if ($pos === false) {
                continue;
            }

            $queryString = substr($url, $pos + 1);

            // Remove fragment if present
            $hashPos = strpos($queryString, '#');
            if ($hashPos !== false) {
                $queryString = substr($queryString, 0, $hashPos);
            }

            if ($queryString === '' || $queryString === false) {
                continue;
            }

            parse_str($queryString, $queryParams);

            foreach ($queryParams as $paramName => $paramValue) {
                $paramName = (string) $paramName;

                if (is_array($paramValue)) {
                    $paramValue = implode(', ', array_map('strval', $this->flattenArray($paramValue)));
                }
                $paramValue = (string) $paramValue;

                $label = $paramName . '=' . $paramValue;

                if (!isset($parameterData[$label])) {
                    $parameterData[$label] = 0;
                }
                $parameterData[$label] += $hits;
            }
        }

        return $parameterData;
    }

    /**
     * Flatten a nested array into a simple list of values.
     *
     * @param array $array
     * @return array
     */
    private function flattenArray(array $array): array
    {
        $result = [];
        array_walk_recursive($array, function ($value) use (&$result) {
            $result[] = $value;
        });
        return $result;
    }

    /**
     * Build a flat DataTable from the extracted parameter data.
     *
     * @param array $parameterData 'paramName=value' => nb_hits
     * @return DataTable
     */
    private function buildDataTable(array $parameterData): DataTable
    {
        $table = new DataTable();

        foreach ($parameterData as $label => $hits) {
            $table->addRow(new Row([
                Row::COLUMNS => [
                    'label' => $label,
                    'nb_hits' => $hits,
                ],
            ]));
        }

        return $table;
    }
}
