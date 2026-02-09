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

class Archiver extends \Piwik\Plugin\Archiver
{
    const RECORD_NAME_PARAMETERS = 'UrlParameter_parameters';

    public function aggregateDayReport()
    {
        $resultSet = $this->getLogAggregator()->queryActionsByDimension(
            ['log_action.name'],
            '',
            ['count(*) as nb_hits'],
            false,
            null,
            'idaction_url'
        );

        $parameterData = [];
        while ($row = $resultSet->fetch()) {
            $url = $row['name'];
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
            $table->getSerialized()
        );
    }

    public function aggregateMultipleReports()
    {
        $this->getProcessor()->aggregateDataTableRecords(
            [self::RECORD_NAME_PARAMETERS]
        );
    }
}
