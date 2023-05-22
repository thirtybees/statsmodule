<?php
/**
 * Copyright (C) 2017-2023 thirty bees
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
 * @copyright 2017-2023 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark of PrestaShop SA.
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

class StatsVisits extends StatsModule
{
    /**
     * @var string
     */
    protected $html = '';
    /**
     * @var string
     */
    protected $query = '';

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRAPH;

        $this->displayName = $this->l('Visits and Visitors');
    }

    /**
     * @return int
     * @throws PrestaShopException
     */
    public function getTotalVisits()
    {
        $sql = 'SELECT COUNT(c.`id_connections`)
				FROM `' . _DB_PREFIX_ . 'connections` c
				WHERE c.`date_add` BETWEEN ' . ModuleGraph::getDateBetween() . '
					' . Shop::addSqlRestriction(false, 'c');

        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * @return int
     * @throws PrestaShopException
     */
    public function getTotalGuests()
    {
        $sql = 'SELECT COUNT(DISTINCT c.`id_guest`)
				FROM `' . _DB_PREFIX_ . 'connections` c
				WHERE c.`date_add` BETWEEN ' . ModuleGraph::getDateBetween() . '
					' . Shop::addSqlRestriction(false, 'c');

        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $graph_params = [
            'layers' => 2,
            'type' => 'line',
            'option' => 3,
        ];

        $total_visits = $this->getTotalVisits();
        $total_guests = $this->getTotalGuests();
        if (Tools::getValue('export')) {
            $this->csvExport([
                'layers' => 2,
                'type' => 'line',
                'option' => 3
            ]);
        }
        $this->html = '
		<div class="panel-heading">
			' . $this->displayName . '
		</div>
		<h4>' . $this->l('Guide') . '</h4>
			<div class="alert alert-warning">
				<h4>' . $this->l('Determine the interest of a visit') . '</h4>
				<p>
					' . $this->l('The visitors\' evolution graph strongly resembles the visits\' graph, but provides additional information:') . '<br />
				</p>
				<ul>
					<li>' . $this->l('If this is the case, congratulations, your website is well planned and pleasing. Glad to see that you\'ve been paying attention.') . '</li>
					<li>' . $this->l('Otherwise, the conclusion is not so simple. The problem can be aesthetic or ergonomic. It is also possible that many visitors have mistakenly visited your URL without possessing a particular interest in your shop. This strange and ever-confusing phenomenon is most likely cause by search engines. If this is the case, you should consider revising your SEO structure.') . '</li>
				</ul>
				<p>
					' . $this->l('This information is mostly qualitative. It is up to you to determine the interest of a disjointed visit.') . '
				</p>
			</div>
			<div class="alert alert-info">
				' . $this->l('A visit corresponds to an internet user coming to your shop, and until the end of their session, only one visit is counted.') . '
				' . $this->l('A visitor is an unknown person who has not registered or logged into your store. A visitor can also be considered a person who has visited your shop multiple times.') . '
			</div>
			<div class="row row-margin-bottom">
				<div class="col-lg-12">
					<div class="col-lg-8">
						' . ($total_visits ? $this->engine($graph_params) . '
					</div>
					<div class="col-lg-4">
						<ul class="list-unstyled">
							<li>' . $this->l('Total visits:') . ' <span class="totalStats">' . $total_visits . '</span></li>
							<li>' . $this->l('Total visitors:') . ' <span class="totalStats">' . $total_guests . '</span></li>
						</ul>
						<hr/>
						<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
							<i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
						</a> ' : '') . '
					</div>
				</div>
			</div>';

        return $this->html;
    }

    /**
     * @param int $option
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    public function setOption($option, $layers = 1)
    {
        if ($option == 3) {
            $this->_titles['main'][0] = $this->l('Number of visits and unique visitors');
            $this->_titles['main'][1] = $this->l('Visits');
            $this->_titles['main'][2] = $this->l('Visitors');
            $this->query = [];
            $this->query[0] = 'SELECT date_add, COUNT(`date_add`) as total
					FROM `' . _DB_PREFIX_ . 'connections`
					WHERE 1
						' . Shop::addSqlRestriction() . '
						AND `date_add` BETWEEN ';
            $this->query[1] = 'SELECT date_add, COUNT(DISTINCT `id_guest`) as total
					FROM `' . _DB_PREFIX_ . 'connections`
					WHERE 1
						' . Shop::addSqlRestriction() . '
						AND `date_add` BETWEEN ';
        }
    }

    /**
     * @param int $layers
     *
     * @return void
     */
    protected function getData($layers)
    {
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
        for ($i = 0; $i < $layers; $i++) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query[$i] . $this->getDate() . ' GROUP BY LEFT(date_add, 4)');
            foreach ($result as $row) {
                $this->_values[$i][(int)substr($row['date_add'], 0, 4)] = (int)$row['total'];
            }
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
        for ($i = 0; $i < $layers; $i++) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query[$i] . $this->getDate() . ' GROUP BY LEFT(date_add, 7)');
            foreach ($result as $row) {
                $this->_values[$i][(int)substr($row['date_add'], 5, 2)] = (int)$row['total'];
            }
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
        for ($i = 0; $i < $layers; $i++) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query[$i] . $this->getDate() . ' GROUP BY LEFT(date_add, 10)');
            foreach ($result as $row) {
                $this->_values[$i][(int)substr($row['date_add'], 8, 2)] = (int)$row['total'];
            }
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
        for ($i = 0; $i < $layers; $i++) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query[$i] . $this->getDate() . ' GROUP BY LEFT(date_add, 13)');
            foreach ($result as $row) {
                $this->_values[$i][(int)substr($row['date_add'], 11, 2)] = (int)$row['total'];
            }
        }
    }
}
