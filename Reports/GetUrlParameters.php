<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\UrlParameter\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetUrlParameters extends Report
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('UrlParameter_UrlParameters');
        $this->documentation = Piwik::translate('UrlParameter_ReportDocumentation');
        $this->metrics = ['nb_hits'];
        $this->order = 40;
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'UrlParameter_UrlParameters';
        $this->actionToLoadSubTables = 'getUrlParameters';
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = true;
        $view->config->show_exclude_low_population = true;
        $view->config->addTranslation('label', Piwik::translate('UrlParameter_ParameterName'));
        $view->config->addTranslation('nb_hits', Piwik::translate('General_ColumnPageviews'));
        $view->requestConfig->filter_sort_column = 'nb_hits';
        $view->requestConfig->filter_sort_order = 'desc';
    }
}
