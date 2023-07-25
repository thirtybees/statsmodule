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
        $this->empty_message = $this->l('Empty recordset returned');
        $this->paging_message = sprintf($this->l('Displaying %1$s of %2$s'), '{0} - {1}', '{2}');

        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));

        $this->columns = [
            [
                'id' => 'lastname',
                'header' => $this->l('Last Name'),
                'dataIndex' => 'lastname',
                'align' => 'center',
            ],
            [
                'id' => 'firstname',
                'header' => $this->l('First Name'),
                'dataIndex' => 'firstname',
                'align' => 'center',
            ],
            [
                'id' => 'email',
                'header' => $this->l('Email'),
                'dataIndex' => 'email',
                'align' => 'center',
            ],
            [
                'id' => 'totalVisits',
                'header' => $this->l('Visits'),
                'dataIndex' => 'totalVisits',
                'align' => 'center',
            ],
            [
                'id' => 'totalValidOrders',
                'header' => $this->l('Valid orders'),
                'dataIndex' => 'totalValidOrders',
                'align' => 'center',
            ],
            [
                'id' => 'totalMoneySpent',
                'header' => $this->l('Money spent') . ' (' . Tools::safeOutput($currency->iso_code) . ')',
                'dataIndex' => 'totalMoneySpent',
                'align' => 'center',
            ],
        ];

        $this->displayName = $this->l('Best customers');
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
		<h4>' . $this->l('Guide') . '</h4>
			<div class="alert alert-warning">
				<h4>' . $this->l('Develop clients\' loyalty') . '</h4>
				<div>
					' . $this->l('Keeping a client can be more profitable than gaining a new one. That is one of the many reasons it is necessary to cultivate customer loyalty.') . ' <br />
					' . $this->l('Word of mouth is also a means for getting new, satisfied clients. A dissatisfied customer can hurt your e-reputation and obstruct future sales goals.') . '<br />
					' . $this->l('In order to achieve this goal, you can organize:') . '
					<ul>
						<li>' . $this->l('Punctual operations: commercial rewards (personalized special offers, product or service offered), non commercial rewards (priority handling of an order or a product), pecuniary rewards (bonds, discount coupons, payback).') . '</li>
						<li>' . $this->l('Sustainable operations: loyalty points or cards, which not only justify communication between merchant and client, but also offer advantages to clients (private offers, discounts).') . '</li>
					</ul>
					' . $this->l('These operations encourage clients to buy products and visit your online store more regularly.') . '
				</div>
			</div>
		' . $this->engine($engine_params) . '
		<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=') . '1">
			<i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
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
		SELECT c.`id_customer`, c.`lastname`, c.`firstname`, c.`email`,
			COUNT(co.`id_connections`) AS totalVisits,
			IFNULL((
				SELECT ROUND(SUM(IFNULL(op.`amount`, 0) / cu.conversion_rate), 2)
				FROM `' . _DB_PREFIX_ . 'orders` o
				LEFT JOIN `' . _DB_PREFIX_ . 'order_payment` op ON o.reference = op.order_reference
				LEFT JOIN `' . _DB_PREFIX_ . 'currency` cu ON o.id_currency = cu.id_currency
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN ' . $this->getDate() . '
				AND o.valid ' . Shop::addSqlRestriction(false, 'o') . '
			), 0) AS totalMoneySpent,
			IFNULL((
				SELECT COUNT(*)
				FROM `' . _DB_PREFIX_ . 'orders` o
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN ' . $this->getDate() . '
				AND o.valid ' . Shop::addSqlRestriction(false, 'o') . '
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

        if (Validate::IsUnsignedInt($this->_limit)) {
            $this->query .= ' LIMIT ' . (int)$this->_start . ', ' . (int)$this->_limit;
        }

        $conn = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $this->_values = $conn->executeS($this->query);

        if (Validate::IsUnsignedInt($this->_limit)) {
            $totalQuery = (new DbQuery())
                ->select("COUNT(DISTINCT c.id_customer)")
                ->from('customer', 'c')
                ->leftJoin('guest', 'g', 'c.`id_customer` = g.`id_customer`')
                ->leftJoin('connections', 'co', 'g.`id_guest` = co.`id_guest`')
                ->where('co.date_add BETWEEN ' . $this->getDate(). ' ' . Shop::addSqlRestriction(Shop::SHARE_CUSTOMER, 'c'));
            $this->_totalCount = (int)$conn->getValue($totalQuery);
        } else {
            $this->_totalCount = count($this->_values);
        }
    }
}
