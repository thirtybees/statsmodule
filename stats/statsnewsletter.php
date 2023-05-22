<?php
/**
 * Copyright (C) 2017-2019 thirty bees
 * Copyright (C) 2007-2016 PrestaShop SA
 *
 * thirty bees is an extension to the PrestaShop software by PrestaShop SA.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2019 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark of PrestaShop SA.
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

class StatsNewsletter extends StatsModule
{
    /**
     * @var string
     */
    protected $_html = '';
    /**
     * @var string
     */
    protected $_query = '';
    /**
     * @var string
     */
    protected $_query2 = '';
    /**
     * @var string
     */
    protected $_option = '';

    /**
     * @var string
     */
    protected $table_name;
    /**
     * @var string
     */
    protected $newsletter_module_name;
    /**
     * @var string
     */
    protected $newsletter_module_human_readable_name;

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRAPH;

        $this->table_name = _DB_PREFIX_ . 'newsletter';
        $this->newsletter_module_name = 'blocknewsletter';
        $this->newsletter_module_human_readable_name = 'Newsletter block';

        $this->displayName = Translate::getModuleTranslation('statsmodule', 'Newsletter', 'statsmodule');
        $this->description = Translate::getModuleTranslation('statsmodule', 'Adds a tab with a graph showing newsletter registrations to the Stats dashboard.', 'statsmodule');
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        if (Module::isInstalled($this->newsletter_module_name)) {
            $totals = $this->getTotals();
            if (Tools::getValue('export')) {
                {
                    $this->csvExport(['type' => 'line', 'layers' => 3]);
                }
            }
            $this->_html = '
			<div class="panel-heading">
				' . $this->displayName . '
			</div>
			<div class="row row-margin-bottom">
				<div class="col-lg-12">
					<div class="col-lg-8">
						' . $this->engine($this->type, ['type' => 'line', 'layers' => 3]) . '
					</div>
					<div class="col-lg-4">
						<ul class="list-unstyled">
							<li>' . Translate::getModuleTranslation('statsmodule', 'Customer registrations:', 'statsmodule') . ' ' . (int)$totals['customers'] . '</li>
							<li>' . Translate::getModuleTranslation('statsmodule', 'Visitor registrations: ', 'statsmodule') . ' ' . (int)$totals['visitors'] . '</li>
							<li>' . Translate::getModuleTranslation('statsmodule', 'Both:', 'statsmodule') . ' ' . (int)$totals['both'] . '</li>
						</ul>
						<hr/>
						<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
							<i class="icon-cloud-upload"></i> ' . Translate::getModuleTranslation('statsmodule', 'CSV Export', 'statsmodule') . '
						</a>
					</div>
				</div>
			</div>';
        } else {
            $this->_html = '<p>' . Translate::getModuleTranslation('statsmodule', 'The "' . $this->newsletter_module_human_readable_name . '" module must be installed.', 'statsmodule') . '</p>';
        }

        return $this->_html;
    }

    /**
     * @return array
     * @throws PrestaShopException
     */
    private function getTotals()
    {
        $sql = 'SELECT COUNT(*) AS customers
				FROM `' . _DB_PREFIX_ . 'customer`
				WHERE 1
					' . Shop::addSqlRestriction(Shop::SHARE_CUSTOMER) . '
					AND `newsletter_date_add` BETWEEN ' . ModuleGraph::getDateBetween();
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        $sql = 'SELECT COUNT(*) AS visitors
				FROM ' . $this->table_name . '
				WHERE 1
				   ' . Shop::addSqlRestriction() . '
					AND `newsletter_date_add` BETWEEN ' . ModuleGraph::getDateBetween();
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
        return [
            'customers' => $result1['customers'],
            'visitors' => $result2['visitors'],
            'both' => $result1['customers'] + $result2['visitors']
        ];
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function getData($layers)
    {
        $this->_titles['main'][0] = Translate::getModuleTranslation('statsmodule', 'Newsletter statistics', 'statsmodule');
        $this->_titles['main'][1] = Translate::getModuleTranslation('statsmodule', 'customers', 'statsmodule');
        $this->_titles['main'][2] = Translate::getModuleTranslation('statsmodule', 'Visitors', 'statsmodule');
        $this->_titles['main'][3] = Translate::getModuleTranslation('statsmodule', 'Both', 'statsmodule');

        $this->_query = 'SELECT newsletter_date_add
				FROM `' . _DB_PREFIX_ . 'customer`
				WHERE 1
					' . Shop::addSqlRestriction(Shop::SHARE_CUSTOMER) . '
					AND `newsletter_date_add` BETWEEN ';

        $this->_query2 = 'SELECT newsletter_date_add
				FROM ' . $this->table_name . '
				WHERE 1
					' . Shop::addSqlRestriction(Shop::SHARE_CUSTOMER) . '
					AND `newsletter_date_add` BETWEEN ';
        $this->setDateGraph($layers, true);
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function setAllTimeValues($layers)
    {
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query . $this->getDate());
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query2 . $this->getDate());
        foreach ($result1 as $row) {
            $this->_values[0][(int)substr($row['newsletter_date_add'], 0, 4)] += 1;
        }
        if ($result2) {
            foreach ($result2 as $row) {
                $this->_values[1][(int)substr($row['newsletter_date_add'], 0, 4)] += 1;
            }
        }
        foreach ($this->_values[2] as $key => $zerofill) {
            $this->_values[2][$key] = $this->_values[0][$key] + $this->_values[1][$key];
        }
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function setYearValues($layers)
    {
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query . $this->getDate());
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query2 . $this->getDate());
        foreach ($result1 as $row) {
            $this->_values[0][(int)substr($row['newsletter_date_add'], 5, 2)] += 1;
        }
        if ($result2) {
            foreach ($result2 as $row) {
                $this->_values[1][(int)substr($row['newsletter_date_add'], 5, 2)] += 1;
            }
        }
        foreach ($this->_values[2] as $key => $zerofill) {
            $this->_values[2][$key] = $this->_values[0][$key] + $this->_values[1][$key];
        }
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function setMonthValues($layers)
    {
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query . $this->getDate());
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query2 . $this->getDate());
        foreach ($result1 as $row) {
            $this->_values[0][(int)substr($row['newsletter_date_add'], 8, 2)] += 1;
        }
        if ($result2) {
            foreach ($result2 as $row) {
                $this->_values[1][(int)substr($row['newsletter_date_add'], 8, 2)] += 1;
            }
        }
        foreach ($this->_values[2] as $key => $zerofill) {
            $this->_values[2][$key] = $this->_values[0][$key] + $this->_values[1][$key];
        }
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function setDayValues($layers)
    {
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query . $this->getDate());
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query2 . $this->getDate());
        foreach ($result1 as $row) {
            $this->_values[0][(int)substr($row['newsletter_date_add'], 11, 2)] += 1;
        }
        if ($result2) {
            foreach ($result2 as $row) {
                $this->_values[1][(int)substr($row['newsletter_date_add'], 11, 2)] += 1;
            }
        }
        foreach ($this->_values[2] as $key => $zerofill) {
            $this->_values[2][$key] = $this->_values[0][$key] + $this->_values[1][$key];
        }
    }
}


