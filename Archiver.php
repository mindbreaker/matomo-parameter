<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\UrlParameter;

use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Db;

class Archiver extends \Piwik\Plugin\Archiver
{
    const RECORD_NAME_PARAMETERS = 'UrlParameter_parameters';
    const MAX_ROWS = 500;

    public function aggregateDayReport()
    {
        $logAggregator = $this->getLogAggregator();

        $select = 'log_action.name as url, count(*) as nb_hits';

        $from = array(
            'log_link_visit_action',
            array(
                'table'  => 'log_action',
                'joinOn' => 'log_link_visit_action.idaction_url = log_action.idaction',
            ),
        );

        $where = $logAggregator->getWhereStatement('log_link_visit_action', 'server_time');
        $where .= ' AND log_link_visit_action.idaction_url IS NOT NULL';

        $groupBy = 'log_action.idaction';
        $orderBy = 'nb_hits DESC';

        $query = $logAggregator->generateQuery($select, $from, $where, $groupBy, $orderBy);
        $resultSet = $logAggregator->getDb()->query($query['sql'], $query['bind']);

        $parameterData = [];
        while ($row = $resultSet->fetch()) {
            $url = $row['url'];
            $hits = (int) $row['nb_hits'];

            $pos = strpos($url, '?');
            if ($pos === false) {
                continue;
            }

            $queryString = substr($url, $pos + 1);

            $hashPos = strpos($queryString, '#');
            if ($hashPos !== false) {
                $queryString = substr($queryString, 0, $hashPos);
            }

            if ($queryString === '' || $queryString === false) {
                continue;
            }

            parse_str($queryString, $queryParams);

            foreach ($queryParams as $paramName => $paramValue) {
                if (is_array($paramValue)) {
                    $flat = [];
                    array_walk_recursive($paramValue, function ($v) use (&$flat) {
                        $flat[] = $v;
                    });
                    $paramValue = implode(', ', $flat);
                }

                $label = (string) $paramName . '=' . (string) $paramValue;

                if (!isset($parameterData[$label])) {
                    $parameterData[$label] = 0;
                }
                $parameterData[$label] += $hits;
            }
        }

        $table = new DataTable();
        foreach ($parameterData as $label => $hits) {
            $table->addRow(new Row([
                Row::COLUMNS => [
                    'label' => $label,
                    'nb_hits' => $hits,
                ],
            ]));
        }

        $this->getProcessor()->insertBlobRecord(
            self::RECORD_NAME_PARAMETERS,
            $table->getSerialized(self::MAX_ROWS)
        );
    }

    public function aggregateMultipleReports()
    {
        $this->getProcessor()->aggregateDataTableRecords(
            [self::RECORD_NAME_PARAMETERS],
            self::MAX_ROWS
        );
    }
}
