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
use Piwik\Tracker\Action;

class Archiver extends \Piwik\Plugin\Archiver
{
    const RECORD_NAME_PARAMETERS = 'UrlParameter_parameters';

    public function aggregateDayReport()
    {
        $params = $this->getLogAggregator()->getParams();
        $startDate = $params->getDateStart()->getDatetime();
        $endDate = $params->getDateEnd()->addDay(1)->getDatetime();
        $idSite = $params->getSite()->getId();

        $sql = "SELECT
                    log_action.name as url,
                    COUNT(*) as nb_hits
                FROM " . Common::prefixTable('log_link_visit_action') . " log_link_visit_action
                JOIN " . Common::prefixTable('log_action') . " log_action
                    ON log_link_visit_action.idaction_url = log_action.idaction
                WHERE log_link_visit_action.server_time >= ?
                    AND log_link_visit_action.server_time < ?
                    AND log_link_visit_action.idsite = ?
                    AND log_action.type = " . Action::TYPE_PAGE_URL . "
                GROUP BY log_action.idaction";

        $rows = Db::fetchAll($sql, [$startDate, $endDate, $idSite]);

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
     * @return array Associative array: paramName => ['nb_hits' => int, 'values' => [value => hits]]
     */
    private function extractParameters(array $rows): array
    {
        $parameterData = [];

        foreach ($rows as $row) {
            $url = $row['url'];
            $hits = (int) $row['nb_hits'];

            $queryString = parse_url($url, PHP_URL_QUERY);
            if (empty($queryString)) {
                continue;
            }

            parse_str($queryString, $queryParams);

            foreach ($queryParams as $paramName => $paramValue) {
                $paramName = (string) $paramName;

                if (is_array($paramValue)) {
                    $paramValue = implode(', ', array_map('strval', $this->flattenArray($paramValue)));
                }
                $paramValue = (string) $paramValue;

                if (!isset($parameterData[$paramName])) {
                    $parameterData[$paramName] = [
                        'nb_hits' => 0,
                        'values' => [],
                    ];
                }

                $parameterData[$paramName]['nb_hits'] += $hits;

                if (!isset($parameterData[$paramName]['values'][$paramValue])) {
                    $parameterData[$paramName]['values'][$paramValue] = 0;
                }
                $parameterData[$paramName]['values'][$paramValue] += $hits;
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
     * Build a DataTable with subtables from the extracted parameter data.
     *
     * @param array $parameterData
     * @return DataTable
     */
    private function buildDataTable(array $parameterData): DataTable
    {
        $table = new DataTable();

        foreach ($parameterData as $paramName => $data) {
            $subtable = new DataTable();

            foreach ($data['values'] as $value => $valueHits) {
                $subtable->addRow(new Row([
                    Row::COLUMNS => [
                        'label' => (string) $value,
                        'nb_hits' => $valueHits,
                    ],
                ]));
            }

            $table->addRow(new Row([
                Row::COLUMNS => [
                    'label' => $paramName,
                    'nb_hits' => $data['nb_hits'],
                ],
                Row::DATATABLE_ASSOCIATED => $subtable,
            ]));
        }

        return $table;
    }
}
