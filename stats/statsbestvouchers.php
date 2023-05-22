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

class StatsBestVouchers extends StatsModule
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
     * @var array[]
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

    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRID;

        $this->default_sort_column = 'ca';
        $this->default_sort_direction = 'DESC';
        $this->empty_message = $this->l('Empty recordset returned.');
        $this->paging_message = sprintf($this->l('Displaying %1$s of %2$s'), '{0} - {1}', '{2}');

        $this->columns = [
            [
                'id' => 'code',
                'header' => $this->l('Code'),
                'dataIndex' => 'code',
                'align' => 'left',
            ],
            [
                'id' => 'name',
                'header' => $this->l('Name'),
                'dataIndex' => 'name',
                'align' => 'left',
            ],
            [
                'id' => 'ca',
                'header' => $this->l('Sales'),
                'dataIndex' => 'ca',
                'align' => 'right',
            ],
            [
                'id' => 'total',
                'header' => $this->l('Total used'),
                'dataIndex' => 'total',
                'align' => 'center',
            ],
        ];

        $this->displayName = $this->l('Best vouchers');
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $engine_params = [
            'id' => 'id_product',
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
			' . $this->engine($engine_params) . '
			<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
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
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $this->query = 'SELECT cr.code, ocr.name, COUNT(ocr.id_cart_rule) AS total, ROUND(SUM(o.total_paid_real) / o.conversion_rate,2) AS ca
				FROM ' . _DB_PREFIX_ . 'order_cart_rule ocr
				LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON o.id_order = ocr.id_order
				LEFT JOIN ' . _DB_PREFIX_ . 'cart_rule cr ON cr.id_cart_rule = ocr.id_cart_rule
				WHERE o.valid = 1
					' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
					AND o.invoice_date BETWEEN ' . $this->getDate() . '
				GROUP BY ocr.id_cart_rule';

        if (Validate::IsName($this->_sort)) {
            $this->query .= ' ORDER BY `' . bqSQL($this->_sort) . '`';
            if (isset($this->_direction) && (strtoupper($this->_direction) == 'ASC' || strtoupper($this->_direction) == 'DESC')) {
                $this->query .= ' ' . pSQL($this->_direction);
            }
        }

        if (Validate::IsUnsignedInt($this->_limit)) {
            $this->query .= ' LIMIT ' . (int)$this->_start . ', ' . (int)$this->_limit;
        }

        $conn = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $values = $conn->executeS($this->query);
        foreach ($values as &$value) {
            $value['ca'] = Tools::displayPrice($value['ca'], $currency);
        }

        $this->_values = $values;

        if (Validate::IsUnsignedInt($this->_limit)) {
            $totalQuery = (new DbQuery())
                ->select('COUNT(DISTINCT ocr.id_cart_rule)')
                ->from('order_cart_rule', 'ocr')
                ->leftJoin('orders', 'o', '(o.id_order = ocr.id_order)')
                ->leftJoin('cart_rule', 'cr', '(cr.id_cart_rule = ocr.id_cart_rule)')
                ->where('o.valid = 1 ' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o'))
                ->where('o.invoice_date BETWEEN ' . $this->getDate());
            $this->_totalCount = (int)$conn->getValue($totalQuery);
        } else {
            $this->_totalCount = count($values);
        }
    }
}
