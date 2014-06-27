<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\CoreConsole\Commands;

use Piwik\Plugin\Report;
use Piwik\Translate;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TODO we should automatically create a method in the API (works currently only if there is no API.php yet)
 * TODO automatically create or modify translation file for instance for report name
 */
class GenerateReport extends GeneratePluginBase
{
    protected function configure()
    {
        $this->setName('generate:report')
            ->setDescription('Adds a new report to an existing plugin')
            ->addOption('pluginname', null, InputOption::VALUE_REQUIRED, 'The name of an existing plugin which does not have a menu defined yet')
            ->addOption('reportname', null, InputOption::VALUE_REQUIRED, 'The name of the report you want to create')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'The name of the category the report belongs to')
            ->addOption('dimension', null, InputOption::VALUE_OPTIONAL, 'The name of the dimension in case your report has a dimension')
            ->addOption('documentation', null, InputOption::VALUE_REQUIRED, 'A documentation that explains what your report is about');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pluginName    = $this->getPluginName($input, $output);
        $reportName    = $this->getReportName($input, $output);
        $category      = $this->getCategory($input, $output, $pluginName);
        $documentation = $this->getDocumentation($input, $output);
        list($dimension, $dimensionClass) = $this->getDimension($input, $output);

        $order   = $this->getOrder($category);
        $apiName = $this->getApiName($reportName);

        $exampleFolder  = PIWIK_INCLUDE_PATH . '/plugins/ExamplePlugin';
        $replace        = array('ExamplePlugin'     => $pluginName,
                                'GetExampleReport'  => ucfirst($apiName),
                                'getExampleReport'  => lcfirst($apiName),
                                'getApiReport'      => lcfirst($apiName),
                                'ExampleCategory'   => $category,
                                'ExampleReportName' => $reportName,
                                'ExampleReportDocumentation' => $documentation,
                                '999'               => $order,
                                'new ExitPageUrl()' => $dimension,
                                'use Piwik\Plugins\Actions\Columns\ExitPageUrl;' => $dimensionClass
        );
        $whitelistFiles = array('/Reports', '/Reports/Base.php', '/Reports/GetExampleReport.php', '/API.php');

        $this->copyTemplateToPlugin($exampleFolder, $pluginName, $replace, $whitelistFiles);

        $this->writeSuccessMessage($output, array(
            sprintf('Reports/%s.php for %s generated.', ucfirst($apiName), $pluginName),
            'You should now implement the method called "' . lcfirst($apiName) . '" in API.php',
           // 'Read more about this here: link to developer guide',
            'Enjoy!'
        ));
    }

    private function getOrder($category)
    {
        $order = 1;

        foreach (Report::getAllReports() as $report) {
            if ($report->getCategory() === $category) {
                if ($report->getOrder() > $order) {
                    $order = $report->getOrder() + 1;
                }
            }
        }

        return $order;
    }

    private function getApiName($reportName)
    {
        $reportName = trim($reportName);
        $reportName = str_replace(' ', '', $reportName);
        $reportName = preg_replace("/[^A-Za-z0-9]/", '', $reportName);

        $apiName = 'get' . ucfirst($reportName);

        return $apiName;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     * @throws \RunTimeException
     */
    protected function getReportName(InputInterface $input, OutputInterface $output)
    {
        $validate = function ($reportName) {
            if (empty($reportName)) {
                throw new \InvalidArgumentException('Please enter the name of your report');
            }

            if (preg_match("/[^A-Za-z0-9 ]/", $reportName)) {
                throw new \InvalidArgumentException('Only alpha numerical characters and whitespaces are allowed');
            }

            return $reportName;
        };

        $reportName = $input->getOption('reportname');

        if (empty($reportName)) {
            $dialog = $this->getHelperSet()->get('dialog');
            $reportName = $dialog->askAndValidate($output, 'Enter the name of your report, for instance "Browser Families": ', $validate);
        } else {
            $validate($reportName);
        }

        $reportName = ucfirst($reportName);

        return $reportName;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     * @throws \RunTimeException
     */
    protected function getDocumentation(InputInterface $input, OutputInterface $output)
    {
        $validate = function ($documentation) {
            if (empty($documentation)) {
                return '';
            }

            return $documentation;
        };

        $documentation = $input->getOption('documentation');

        if (empty($documentation)) {
            $dialog = $this->getHelperSet()->get('dialog');
            $documentation = $dialog->askAndValidate($output, 'Enter a documentation that describes the data of your report (you can leave it empty and define it later): ', $validate);
        } else {
            $validate($documentation);
        }

        $documentation = ucfirst($documentation);

        return $documentation;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $pluginName
     * @return array
     * @throws \RunTimeException
     */
    protected function getCategory(InputInterface $input, OutputInterface $output, $pluginName)
    {
        $path = $this->getPluginPath($pluginName) . '/Reports/Base.php';
        if (file_exists($path)) {
            // category is already defined in base.php
            return '';
        }

        $validate = function ($category) {
            if (empty($category)) {
                throw new \InvalidArgumentException('Please enter the name of the category your report belongs to');
            }

            return $category;
        };

        $category = $input->getOption('category');

        $categories = array();
        foreach (Report::getAllReports() as $report) {
            if ($report->getCategory()) {
                $categories[] = $report->getCategory();
            }
        }
        $categories = array_values(array_unique($categories));

        if (empty($category)) {
            $dialog = $this->getHelperSet()->get('dialog');
            $category = $dialog->askAndValidate($output, 'Enter the report category, for instance "Visitor" (you can reuse any existing category or define a new one): ', $validate, false, null, $categories);
        } else {
            $validate($category);
        }

        $translationKey = Translate::findTranslationKeyForTranslation($category);
        if (!empty($translationKey)) {
            return $translationKey;
        }

        $category = ucfirst($category);

        return $category;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     * @throws \RunTimeException
     */
    protected function getDimension(InputInterface $input, OutputInterface $output)
    {
        $dimensions = array();
        $dimensionNames = array();

        foreach (Report::getAllReports() as $report) {
            $dimension = $report->getDimension();
            if (is_object($dimension)) {
                $name = $dimension->getName();
                if (!empty($name)) {
                    $dimensions[$name] = get_class($dimension);
                    $dimensionNames[] = $name;
                }
            }
        }

        $dimensionNames = array_values(array_unique($dimensionNames));

        $validate = function ($dimension) use ($dimensions) {
            if (empty($dimension)) {
                return '';
            }

            if (!empty($dimension) && !array_key_exists($dimension, $dimensions)) {
                throw new \InvalidArgumentException('Leave dimension either empty or use an existing one. You can also create a new dimension by calling .console generate:dimension before generating this report.');
            }

            return $dimension;
        };

        $actualDimension = $input->getOption('dimension');

        if (null === $actualDimension) {
            $dialog = $this->getHelperSet()->get('dialog');
            $actualDimension = $dialog->askAndValidate($output, 'Enter the report dimension, for instance "Browser" (you can leave it either empty or use an existing one): ', $validate, false, null, $dimensionNames);
        } else {
            $validate($actualDimension);
        }

        if (empty($actualDimension)) {
            return array('null', '');
        }

        $className = $dimensions[$actualDimension];
        $parts = explode('\\', $className);
        $name = end($parts);

        return array('new ' . $name . '()', 'use ' . $className . ';');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     * @throws \RunTimeException
     */
    protected function getPluginName(InputInterface $input, OutputInterface $output)
    {
        $pluginNames = $this->getPluginNames();
        $invalidName = 'You have to enter a name of an existing plugin.';

        return $this->askPluginNameAndValidate($input, $output, $pluginNames, $invalidName);
    }

}
