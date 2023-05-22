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

class StatsBestManufacturers extends StatsModule
{
    /**
     * @var null
     */
    protected $html = null;
    /**
     * @var null
     */
    protected $query = null;
    /**
     * @var array[]|null
     */
    protected $columns = null;
    /**
     * @var string|null
     */
    protected $default_sort_column = null;
    /**
     * @var string|null
     */
    protected $default_sort_direction = null;
    /**
     * @var string|null
     */
    protected $empty_message = null;
    /**
     * @var string|null
     */
    protected $paging_message = null;

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRID;
        $this->default_sort_column = 'sales';
        $this->default_sort_direction = 'DESC';
        $this->empty_message = $this->l('Empty record set returned');
        $this->paging_message = sprintf($this->l('Displaying %1$s of %2$s'), '{0} - {1}', '{2}');

        $this->columns = [
            [
                'id' => 'name',
                'header' => $this->l('Name'),
                'dataIndex' => 'name',
                'align' => 'center',
            ],
            [
                'id' => 'quantity',
                'header' => $this->l('Quantity sold'),
                'dataIndex' => 'quantity',
                'align' => 'center',
            ],
            [
                'id' => 'sales',
                'header' => $this->l('Total paid'),
                'dataIndex' => 'sales',
                'align' => 'center',
            ],
        ];

        $this->displayName = $this->l('Best manufacturers');
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $engine_params = [
            'id' => 'id_category',
            'title' => $this->displayName,
            'columns' => $this->columns,
            'defaultSortColumn' => $this->default_sort_column,
            'defaultSortDirection' => $this->default_sort_direction,
            'emptyMessage' => $this->empty_message,
            'pagingMessage' => $this->paging_message,
        ];

        if (Tools::getValue('export') == 1) {
            $this->csvExport($engine_params);
        }
        $this->html = '
			<div class="panel-heading">
				' . $this->displayName . '
			</div>
			' . $this->engine($this->type, $engine_params) . '
			<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
				<i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
			</a>';
        return $this->html;
    }

    /**
     * @return int Get total of distinct manufacturers
     * @throws PrestaShopException
     */
    public function getTotalCount()
    {
        $sql = 'SELECT COUNT(DISTINCT(m.id_manufacturer))
				FROM ' . _DB_PREFIX_ . 'order_detail od
				LEFT JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = od.product_id
				LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON o.id_order = od.id_order
				LEFT JOIN ' . _DB_PREFIX_ . 'manufacturer m ON m.id_manufacturer = p.id_manufacturer
				WHERE o.invoice_date BETWEEN ' . $this->getDate() . '
					' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
					AND o.valid = 1
					AND m.id_manufacturer IS NOT NULL';
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    public function getData($layers = null)
    {
        $this->_totalCount = $this->getTotalCount();

        $this->query = 'SELECT m.name, SUM(od.product_quantity) AS quantity, ROUND(SUM(od.product_quantity * od.product_price) / c.conversion_rate, 2) AS sales
				FROM ' . _DB_PREFIX_ . 'order_detail od
				LEFT JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = od.product_id
				LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON o.id_order = od.id_order
				LEFT JOIN ' . _DB_PREFIX_ . 'currency c ON c.id_currency = o.id_currency
				LEFT JOIN ' . _DB_PREFIX_ . 'manufacturer m ON m.id_manufacturer = p.id_manufacturer
				WHERE o.invoice_date BETWEEN ' . $this->getDate() . '
					' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
					AND o.valid = 1
					AND m.id_manufacturer IS NOT NULL
				GROUP BY p.id_manufacturer';
        if (Validate::IsName($this->_sort)) {
            $this->query .= ' ORDER BY `' . $this->_sort . '`';
            if (isset($this->_direction) && Validate::isSortDirection($this->_direction)) {
                $this->query .= ' ' . $this->_direction;
            }
        }

        if (($this->_start === 0 || Validate::IsUnsignedInt($this->_start)) && Validate::IsUnsignedInt($this->_limit)) {
            $this->query .= ' LIMIT ' . $this->_start . ', ' . ($this->_limit);
        }
        $this->_values = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query);
    }
}
