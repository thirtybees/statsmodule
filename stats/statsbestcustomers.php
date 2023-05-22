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

class StatsBestCustomers extends StatsModule
{
    /**
     * @var string
     */
    protected $html;

    /**
     * @var string
     */
    protected $query;

    /**
     * @var array
     */
    protected $columns;
    /**
     * @var string
     */
    protected $default_sort_column;
    /**
     * @var string
     */
    protected $default_sort_direction;
    /**
     * @var string
     */
    protected $empty_message;
    /**
     * @var string
     */
    protected $paging_message;

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRID;

        $this->default_sort_column = 'totalMoneySpent';
        $this->default_sort_direction = 'DESC';
        $this->empty_message = Translate::getModuleTranslation('statsmodule', 'Empty recordset returned', 'statsmodule');
        $this->paging_message = sprintf(Translate::getModuleTranslation('statsmodule', 'Displaying %1$s of %2$s', 'statsmodule'), '{0} - {1}', '{2}');

        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));

        $this->columns = [
            [
                'id' => 'lastname',
                'header' => Translate::getModuleTranslation('statsmodule', 'Last Name', 'statsmodule'),
                'dataIndex' => 'lastname',
                'align' => 'center',
            ],
            [
                'id' => 'firstname',
                'header' => Translate::getModuleTranslation('statsmodule', 'First Name', 'statsmodule'),
                'dataIndex' => 'firstname',
                'align' => 'center',
            ],
            [
                'id' => 'email',
                'header' => Translate::getModuleTranslation('statsmodule', 'Email', 'statsmodule'),
                'dataIndex' => 'email',
                'align' => 'center',
            ],
            [
                'id' => 'totalVisits',
                'header' => Translate::getModuleTranslation('statsmodule', 'Visits', 'statsmodule'),
                'dataIndex' => 'totalVisits',
                'align' => 'center',
            ],
            [
                'id' => 'totalValidOrders',
                'header' => Translate::getModuleTranslation('statsmodule', 'Valid orders', 'statsmodule'),
                'dataIndex' => 'totalValidOrders',
                'align' => 'center',
            ],
            [
                'id' => 'totalMoneySpent',
                'header' => Translate::getModuleTranslation('statsmodule', 'Money spent', 'statsmodule') . ' (' . Tools::safeOutput($currency->iso_code) . ')',
                'dataIndex' => 'totalMoneySpent',
                'align' => 'center',
            ],
        ];

        $this->displayName = Translate::getModuleTranslation('statsmodule', 'Best customers', 'statsmodule');
        $this->description = Translate::getModuleTranslation('statsmodule', 'Adds a list of the best customers to the Stats dashboard.', 'statsmodule');
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $engine_params = [
            'id' => 'id_customer',
            'title' => $this->displayName,
            'columns' => $this->columns,
            'defaultSortColumn' => $this->default_sort_column,
            'defaultSortDirection' => $this->default_sort_direction,
            'emptyMessage' => $this->empty_message,
            'pagingMessage' => $this->paging_message,
        ];

        if (Tools::getValue('export')) {
            $this->csvExport($engine_params);
        }

        $this->html = '
		<div class="panel-heading">
			' . $this->displayName . '
		</div>
		<h4>' . Translate::getModuleTranslation('statsmodule', 'Guide', 'statsmodule') . '</h4>
			<div class="alert alert-warning">
				<h4>' . Translate::getModuleTranslation('statsmodule', 'Develop clients\' loyalty', 'statsmodule') . '</h4>
				<div>
					' . Translate::getModuleTranslation('statsmodule', 'Keeping a client can be more profitable than gaining a new one. That is one of the many reasons it is necessary to cultivate customer loyalty.', 'statsmodule') . ' <br />
					' . Translate::getModuleTranslation('statsmodule', 'Word of mouth is also a means for getting new, satisfied clients. A dissatisfied customer can hurt your e-reputation and obstruct future sales goals.', 'statsmodule') . '<br />
					' . Translate::getModuleTranslation('statsmodule', 'In order to achieve this goal, you can organize:', 'statsmodule') . '
					<ul>
						<li>' . Translate::getModuleTranslation('statsmodule', 'Punctual operations: commercial rewards (personalized special offers, product or service offered), non commercial rewards (priority handling of an order or a product), pecuniary rewards (bonds, discount coupons, payback).', 'statsmodule') . '</li>
						<li>' . Translate::getModuleTranslation('statsmodule', 'Sustainable operations: loyalty points or cards, which not only justify communication between merchant and client, but also offer advantages to clients (private offers, discounts).', 'statsmodule') . '</li>
					</ul>
					' . Translate::getModuleTranslation('statsmodule', 'These operations encourage clients to buy products and visit your online store more regularly.', 'statsmodule') . '
				</div>
			</div>
		' . $this->engine($this->type, $engine_params) . '
		<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=') . '1">
			<i class="icon-cloud-upload"></i> ' . Translate::getModuleTranslation('statsmodule', 'CSV Export', 'statsmodule') . '
		</a>';

        return $this->html;
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    public function getData($layers = null)
    {
        $this->query = '
		SELECT SQL_CALC_FOUND_ROWS c.`id_customer`, c.`lastname`, c.`firstname`, c.`email`,
			COUNT(co.`id_connections`) AS totalVisits,
			IFNULL((
				SELECT ROUND(SUM(IFNULL(op.`amount`, 0) / cu.conversion_rate), 2)
				FROM `' . _DB_PREFIX_ . 'orders` o
				LEFT JOIN `' . _DB_PREFIX_ . 'order_payment` op ON o.reference = op.order_reference
				LEFT JOIN `' . _DB_PREFIX_ . 'currency` cu ON o.id_currency = cu.id_currency
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN ' . $this->getDate() . '
				AND o.valid
			), 0) AS totalMoneySpent,
			IFNULL((
				SELECT COUNT(*)
				FROM `' . _DB_PREFIX_ . 'orders` o
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN ' . $this->getDate() . '
				AND o.valid
			), 0) AS totalValidOrders
		FROM `' . _DB_PREFIX_ . 'customer` c
		LEFT JOIN `' . _DB_PREFIX_ . 'guest` g ON c.`id_customer` = g.`id_customer`
		LEFT JOIN `' . _DB_PREFIX_ . 'connections` co ON g.`id_guest` = co.`id_guest`
		WHERE co.date_add BETWEEN ' . $this->getDate()
            . Shop::addSqlRestriction(Shop::SHARE_CUSTOMER, 'c') .
            'GROUP BY c.`id_customer`, c.`lastname`, c.`firstname`, c.`email`';

        if (Validate::IsName($this->_sort)) {
            $this->query .= ' ORDER BY `' . bqSQL($this->_sort) . '`';
            if (isset($this->_direction) && Validate::isSortDirection($this->_direction)) {
                $this->query .= ' ' . $this->_direction;
            }
        }

        if (($this->_start === 0 || Validate::IsUnsignedInt($this->_start)) && Validate::IsUnsignedInt($this->_limit)) {
            $this->query .= ' LIMIT ' . (int)$this->_start . ', ' . (int)$this->_limit;
        }

        $this->_values = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query);
        $this->_totalCount = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT FOUND_ROWS()');
    }
}
