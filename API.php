<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\UrlParameter;

use Piwik\Archive;
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    /**
     * Returns a DataTable of all URL query parameters with the number of
     * pageviews they appeared in.
     *
     * Each row has a subtable containing the individual parameter values
     * and their respective hit counts.
     *
     * @param int          $idSite
     * @param string       $period
     * @param string       $date
     * @param string|false $segment
     * @param bool         $expanded
     * @param int|false    $idSubtable
     * @param bool         $flat
     * @return \Piwik\DataTable
     */
    public function getUrlParameters($idSite, $period, $date, $segment = false, $expanded = false, $idSubtable = false, $flat = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        return Archive::createDataTableFromArchive(
            Archiver::RECORD_NAME_PARAMETERS,
            $idSite,
            $period,
            $date,
            $segment,
            $expanded,
            $flat,
            $idSubtable
        );
    }
}
