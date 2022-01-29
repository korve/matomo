<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Goals\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\Actions\Columns\PageUrl;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\Goals\Visualizations\GoalsPages;

class GetPagesUrl extends BasePages
{

    protected function init()
    {
        parent::init();
        $this->name = Piwik::translate('General_Pages');
        $this->documentation = Piwik::translate('Goals_PagesReportDocumentation');
        $this->dimension = new PageUrl();
        $this->order = 1;
    }

    public function configureView(ViewDataTable $view)
    {

        $view->config->show_exclude_low_population = false;

        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->disable_subtable_when_show_goals = true;
        }

        $view->requestConfig->filter_sort_column = 'nb_hits';
        $view->requestConfig->filter_sort_order  = 'asc';
        $view->requestConfig->filter_limit       = 25;

        $view->config->addTranslations(array('label' => $this->dimension->getName(),
                                             'nb_hits' => Piwik::translate('General_ColumnUniquePageviews')));
    }

    public function getDefaultTypeViewDataTable()
    {
        return GoalsPages::ID;
    }

}