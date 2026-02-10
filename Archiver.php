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
use Piwik\Log\LoggerInterface;

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

        // paramName => ['nb_hits' => int, 'values' => [value => hits]]
        $parameterData = [];

        try {
            $rows = Db::fetchAll($query['sql'], $query['bind']);

            foreach ($rows as $row) {
                $url = $row['url'];
                if (empty($url)) {
                    continue;
                }
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
                    $paramName = (string) $paramName;
                    $paramValue = (string) $paramValue;

                    if (!isset($parameterData[$paramName])) {
                        $parameterData[$paramName] = [
                            'nb_hits' => 0,
                            'values'  => [],
                        ];
                    }
                    $parameterData[$paramName]['nb_hits'] += $hits;

                    if (!isset($parameterData[$paramName]['values'][$paramValue])) {
                        $parameterData[$paramName]['values'][$paramValue] = 0;
                    }
                    $parameterData[$paramName]['values'][$paramValue] += $hits;
                }
            }
        } catch (\Exception $e) {
            $logger = \Piwik\Container\StaticContainer::get(LoggerInterface::class);
            $logger->error('UrlParameter Archiver error: {exception}', [
                'exception' => $e,
            ]);
        }

        $table = new DataTable();
        foreach ($parameterData as $paramName => $data) {
            $subtable = new DataTable();
            foreach ($data['values'] as $value => $valueHits) {
                $subtable->addRow(new Row([
                    Row::COLUMNS => [
                        'label'   => (string) $value,
                        'nb_hits' => $valueHits,
                    ],
                ]));
            }

            $table->addRow(new Row([
                Row::COLUMNS => [
                    'label'   => $paramName,
                    'nb_hits' => $data['nb_hits'],
                ],
                Row::DATATABLE_ASSOCIATED => $subtable,
            ]));
        }

        $serialized = $table->getSerialized(self::MAX_ROWS, self::MAX_ROWS);
        $this->getProcessor()->insertBlobRecord(self::RECORD_NAME_PARAMETERS, $serialized);
    }

    public function aggregateMultipleReports()
    {
        $this->getProcessor()->aggregateDataTableRecords(
            [self::RECORD_NAME_PARAMETERS],
            self::MAX_ROWS,
            self::MAX_ROWS
        );
    }
}
