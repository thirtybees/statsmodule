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

use Thirtybees\StatsModule\ProductSalesView;

if (!defined('_TB_VERSION_')) {
    exit;
}

class StatsBestProducts extends StatsModule
{
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

    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRID;

        $this->default_sort_column = 'totalPriceSold';
        $this->default_sort_direction = 'DESC';
        $this->empty_message = $this->l('An empty record-set was returned.');
        $this->paging_message = sprintf($this->l('Displaying %1$s of %2$s'), '{0} - {1}', '{2}');

        $this->columns = [
            [
                'id' => 'reference',
                'header' => $this->l('Reference'),
                'dataIndex' => 'reference',
                'align' => 'left',
            ],
            [
                'id' => 'name',
                'header' => $this->l('Name'),
                'dataIndex' => 'name',
                'align' => 'left',
            ],
            [
                'id' => 'totalOrders',
                'header' => $this->l('Orders'),
                'dataIndex' => 'totalOrders',
                'align' => 'center',
            ],
            [
                'id' => 'totalQuantityProduct',
                'header' => $this->l('Qty (individual)'),
                'dataIndex' => 'totalQuantityProduct',
                'align' => 'center',
            ],
            [
                'id' => 'totalQuantityPack',
                'header' => $this->l('Qty (pack)'),
                'dataIndex' => 'totalQuantityPack',
                'align' => 'center',
            ],
            [
                'id' => 'totalQuantitySold',
                'header' => $this->l('Qty'),
                'dataIndex' => 'totalQuantitySold',
                'align' => 'center',
            ],
            [
                'id' => 'avgPriceSold',
                'header' => $this->l('Avg price'),
                'dataIndex' => 'avgPriceSold',
                'align' => 'right',
            ],
            [
                'id' => 'totalPriceSold',
                'header' => $this->l('Sales'),
                'dataIndex' => 'totalPriceSold',
                'align' => 'right',
            ],
            [
                'id' => 'averageQuantitySold',
                'header' => $this->l('Quantity/day'),
                'dataIndex' => 'averageQuantitySold',
                'align' => 'center',
            ],
            [
                'id' => 'totalPageViewed',
                'header' => $this->l('Page views'),
                'dataIndex' => 'totalPageViewed',
                'align' => 'center',
            ],
            [
                'id' => 'quantity',
                'header' => $this->l('Available quantity'),
                'dataIndex' => 'quantity',
                'align' => 'center',
            ],
            [
                'id' => 'active',
                'header' => $this->l('Active'),
                'dataIndex' => 'active',
                'align' => 'center',
            ],
        ];

        $this->columns = array_filter($this->columns, function($column) {
            $id = $column['id'];
            // Do not display totalPageViewed if we don't collect this information
            if ($id === 'totalPageViewed' && !$this->utils->trackingPageViews()) {
                return false;
            }

            // Hide packs related quantities if we don't have any packs
            if (in_array($id, ['totalQuantityPack', 'totalQuantityProduct']) && !Pack::isFeatureActive()) {
                return false;
            }

            // show the rest
            return true;
        });

        $this->displayName = $this->l('Best-selling products');
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


        return '<div class="panel-heading">' . $this->displayName . '</div>
		' . $this->engine($engine_params) . '
		<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
			<i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
		</a>';
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
        $date_between = $this->getDate();
        $array_date_between = explode(' AND ', $date_between);
        $export = !!Tools::getValue('export');

        $sales = new ProductSalesView($date_between);

        $query = 'SELECT p.reference, p.id_product, pl.name,
				ROUND(AVG(sales.price), 2) as avgPriceSold,
				IFNULL(stock.quantity, 0) as quantity,
				COUNT(DISTINCT sales.id_order) AS totalOrders,
				IFNULL(SUM(sales.quantity), 0) AS totalQuantitySold,
				IFNULL(SUM(CASE WHEN sales.pack_item THEN 0 ELSE sales.quantity END), 0) AS totalQuantityProduct,
				IFNULL(SUM(CASE WHEN sales.pack_item THEN sales.quantity ELSE 0 END), 0) AS totalQuantityPack,
				ROUND(IFNULL(IFNULL(SUM(sales.quantity), 0) / (1 + LEAST(TO_DAYS(' . $array_date_between[1] . '), TO_DAYS(NOW())) - GREATEST(TO_DAYS(' . $array_date_between[0] . '), TO_DAYS(product_shop.date_add))), 0), 2) as averageQuantitySold,
				ROUND(IFNULL(SUM(sales.price * sales.quantity), 0), 2) AS totalPriceSold,
			    '.$this->getPageViewedSubselect($date_between).' AS totalPageViewed,
				product_shop.active
				FROM ' . _DB_PREFIX_ . 'product p
				' . Shop::addSqlAssociation('product', 'p') . '
				LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->getLang() . ' ' . Shop::addSqlRestrictionOnLang('pl') . ')
				INNER JOIN '.$sales->getAsTable().' sales ON (sales.id_product = p.id_product)
				' . Product::sqlStock('p', 0) . '
				GROUP BY sales.id_product';

        if (Validate::IsName($this->_sort)) {
            $query .= ' ORDER BY `' . bqSQL($this->_sort) . '`';
            if (isset($this->_direction) && Validate::isSortDirection($this->_direction)) {
                $query .= ' ' . $this->_direction;
            }
        }

        if (Validate::IsUnsignedInt($this->_limit)) {
            $query .= ' LIMIT ' . (int)$this->_start . ', ' . (int)$this->_limit;
        }

        $conn = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $values = $conn->executeS($query);
        foreach ($values as &$value) {
            $value['avgPriceSold'] = Tools::displayPrice($value['avgPriceSold'], $currency);
            $value['totalPriceSold'] = Tools::displayPrice($value['totalPriceSold'], $currency);

            if (! $export) {
                $drilldown = Context::getContext()->link->getAdminLink('AdminStats', true, [
                    'module' => 'statsproduct',
                    'id_product' => (int)$value['id_product']
                ]);
                $value['name'] = '<a href="'.Tools::safeOutput($drilldown).'">'.$value['name'].'</a>';
            }
        }
        unset($value);

        $this->_values = $values;

        if (Validate::IsUnsignedInt($this->_limit)) {
            $totalQuery = (new DbQuery())
                ->select('COUNT(DISTINCT sales.id_product)')
                ->from('orders', 'o')
                ->join('INNER JOIN ' . $sales->getAsTable() . ' AS sales ON (sales.id_order = o.id_order)');
            $this->_totalCount = (int)$conn->getValue($totalQuery);
        } else {
            $this->_totalCount = count($values);
        }
    }

    /**
     * @param string $dateBetween
     *
     * @return string
     * @throws PrestaShopException
     */
    protected function getPageViewedSubselect($dateBetween)
    {
        if ($this->utils->trackingPageViews()) {
            $sql = (new DbQuery())
                ->select('IFNULL(SUM(pv.`counter`), 0)')
                ->from('page', 'pa')
                ->innerJoin('page_viewed', 'pv', 'pa.`id_page` = pv.`id_page`')
                ->innerJoin('date_range', 'dr', 'pv.`id_date_range` = dr.`id_date_range`')
                ->where('pa.id_object = p.id_product')
                ->where('pa.id_page_type = '. (int)Page::getPageTypeByName('product'))
                ->where('dr.`time_start` BETWEEN ' . $dateBetween)
                ->where('dr.`time_end` BETWEEN ' . $dateBetween);
            return "($sql)";
        }
        return '0';
    }
}
