<?php
/**
 * Copyright (C) 2017-2024 thirty bees
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
 * @copyright 2017-2024 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark of PrestaShop SA.
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

class StatsRegistrations extends StatsModule
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

        $this->displayName = $this->l('Customer accounts');
    }

    /**
     * @return int Get total of registration in date range
     * @throws PrestaShopException
     */
    public function getTotalRegistrations()
    {
        $sql = 'SELECT COUNT(`id_customer`) as total
				FROM `' . _DB_PREFIX_ . 'customer`
				WHERE `date_add` BETWEEN ' . ModuleGraph::getDateBetween() . '
				' . Shop::addSqlRestriction(false);
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        return isset($result['total']) ? $result['total'] : 0;
    }

    /**
     * @return int Get total of blocked visitors during registration process
     * @throws PrestaShopException
     */
    public function getBlockedVisitors()
    {
        $sql = 'SELECT COUNT(DISTINCT c.`id_guest`) as blocked
				FROM `' . _DB_PREFIX_ . 'page_type` pt
				LEFT JOIN `' . _DB_PREFIX_ . 'page` p ON p.id_page_type = pt.id_page_type
				LEFT JOIN `' . _DB_PREFIX_ . 'connections_page` cp ON p.id_page = cp.id_page
				LEFT JOIN `' . _DB_PREFIX_ . 'connections` c ON c.id_connections = cp.id_connections
				LEFT JOIN `' . _DB_PREFIX_ . 'guest` g ON c.id_guest = g.id_guest
				WHERE pt.name = "authentication"
					' . Shop::addSqlRestriction(false, 'c') . '
					AND (g.id_customer IS NULL OR g.id_customer = 0)
					AND c.`date_add` BETWEEN ' . ModuleGraph::getDateBetween();
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        return $result['blocked'];
    }

    /**
     * @return int
     * @throws PrestaShopException
     */
    public function getFirstBuyers()
    {
        $sql = 'SELECT COUNT(DISTINCT o.`id_customer`) as buyers
				FROM `' . _DB_PREFIX_ . 'orders` o
				LEFT JOIN `' . _DB_PREFIX_ . 'guest` g ON o.id_customer = g.id_customer
				LEFT JOIN `' . _DB_PREFIX_ . 'connections` c ON c.id_guest = g.id_guest
				WHERE o.`date_add` BETWEEN ' . ModuleGraph::getDateBetween() . '
					' . Shop::addSqlRestriction(false, 'o') . '
					AND o.valid = 1
					AND ABS(TIMEDIFF(o.date_add, c.date_add)+0) < 120000';
        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $total_registrations = $this->getTotalRegistrations();
        $total_blocked = $this->getBlockedVisitors();
        $total_buyers = $this->getFirstBuyers();
        if (Tools::getValue('export')) {
            $this->csvExport([
                'layers' => 0,
                'type' => 'line'
            ]);
        }
        $this->html = '
		<div class="panel-heading">
			' . $this->displayName . '
		</div>
		<div class="alert alert-info">
			<ul>
				<li>
					' . $this->l('Number of visitors who stopped at the registering step:') . ' <span class="totalStats">' . (int)$total_blocked . ($total_registrations ? ' (' . number_format(100 * $total_blocked / ($total_registrations + $total_blocked), 2) . '%)' : '') . '</span></li>
					<li>' . $this->l('Number of visitors who placed an order directly after registration:') . ' <span class="totalStats">' . (int)$total_buyers . ($total_registrations ? ' (' . number_format(100 * $total_buyers / ($total_registrations), 2) . '%)' : '') . '</span></li>
				<li>
					' . $this->l('Total customer accounts:') . ' <span class="totalStats">' . $total_registrations . '</span></li>
			</ul>
		</div>
		<h4>' . $this->l('Guide') . '</h4>
		<div class="alert alert-warning">
			<h4>' . $this->l('Number of customer accounts created') . '</h4>
			<p>' . $this->l('The total number of accounts created is not in itself important information. However, it is beneficial to analyze the number created over time. This will indicate whether or not things are on the right track. You feel me?') . '</p>
		</div>
		<h4>' . $this->l('How to act on the registrations\' evolution?') . '</h4>
		<div class="alert alert-warning">
			' . $this->l('If you let your shop run without changing anything, the number of customer registrations should stay stable or show a slight decline.') . '
			' . $this->l('A significant increase or decrease in customer registration shows that there has probably been a change to your shop. With that in mind, we suggest that you identify the cause, correct the issue and get back in the business of making money!') . '<br />
			' . $this->l('Here is a summary of what may affect the creation of customer accounts:') . '
			<ul>
				<li>' . $this->l('An advertising campaign can attract an increased number of visitors to your online store. This will likely be followed by an increase in customer accounts and profit margins, which will depend on customer "quality." Well-targeted advertising is typically more effective than large-scale advertising... and it\'s cheaper too!') . '</li>
				<li>' . $this->l('Specials, sales, promotions and/or contests typically demand a shoppers\' attentions. Offering such things will not only keep your business lively, it will also increase traffic, build customer loyalty and genuinely change your current e-commerce philosophy.') . '</li>
				<li>' . $this->l('Design and user-friendliness are more important than ever in the world of online sales. An ill-chosen or hard-to-follow graphical theme can keep shoppers at bay. This means that you should aspire to find the right balance between beauty and functionality for your online store.') . '</li>
			</ul>
		</div>

		<div class="row row-margin-bottom">
			<div class="col-lg-12">
				<div class="col-lg-8">
					' . $this->engine(['type' => 'line']) . '
				</div>
				<div class="col-lg-4">
					<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
						<i class="icon-cloud-upload"></i>' . $this->l('CSV Export') . '
					</a>
				</div>
			</div>
		</div>';

        return $this->html;
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function getData($layers)
    {
        $this->query = '
			SELECT `date_add`
			FROM `' . _DB_PREFIX_ . 'customer`
			WHERE 1
				' . Shop::addSqlRestriction(Shop::SHARE_CUSTOMER) . '
				AND `date_add` BETWEEN';
        $this->_titles['main'] = $this->l('Number of customer accounts created');
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
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . $this->getDate());
        foreach ($result as $row) {
            $this->_values[(int)substr($row['date_add'], 0, 4)]++;
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
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . $this->getDate());
        foreach ($result as $row) {
            $mounth = (int)substr($row['date_add'], 5, 2);
            if (!isset($this->_values[$mounth])) {
                $this->_values[$mounth] = 0;
            }
            $this->_values[$mounth]++;
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
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . $this->getDate());
        foreach ($result as $row) {
            $this->_values[(int)substr($row['date_add'], 8, 2)]++;
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
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . $this->getDate());
        foreach ($result as $row) {
            $this->_values[(int)substr($row['date_add'], 11, 2)]++;
        }
    }
}
