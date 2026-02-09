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
     * Returns a flat DataTable of all URL query parameter=value combinations
     * with the number of pageviews they appeared in.
     *
     * @param int          $idSite
     * @param string       $period
     * @param string       $date
     * @param string|false $segment
     * @return \Piwik\DataTable
     */
    public function getUrlParameters($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        return Archive::createDataTableFromArchive(
            Archiver::RECORD_NAME_PARAMETERS,
            $idSite,
            $period,
            $date,
            $segment
        );
    }
}
