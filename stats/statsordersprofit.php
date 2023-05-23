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

class StatsOrdersProfit extends StatsModule
{
    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRID;
        $this->displayName = $this->l('Orders Profit');
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
            'columns' => [
                [
                    'id' => 'number',
                    'header' => $this->l('Order ID'),
                    'dataIndex' => 'number',
                    'align' => 'center',
                ],
                [
                    'id' => 'invoice_number',
                    'header' => $this->l('Invoice Number'),
                    'dataIndex' => 'invoice_number',
                    'align' => 'center',
                ],
                [
                    'id' => 'invoice_date',
                    'header' => $this->l('Invoice Date'),
                    'dataIndex' => 'invoice_date',
                    'align' => 'center',
                ],
                [
                    'id' => 'paid',
                    'header' => $this->l('Paid'),
                    'dataIndex' => 'paid',
                    'align' => 'center',
                ],
                [
                    'id' => 'total',
                    'header' => $this->l('Total'),
                    'dataIndex' => 'total',
                    'align' => 'center',
                ],
                [
                    'id' => 'shipping',
                    'header' => $this->l('Shipping'),
                    'dataIndex' => 'shipping',
                    'align' => 'center',
                ],
                [
                    'id' => 'TaxTotal',
                    'header' => $this->l('Tax'),
                    'dataIndex' => 'TaxTotal',
                    'align' => 'center',
                ],
                [
                    'id' => 'cost',
                    'header' => $this->l('Cost'),
                    'dataIndex' => 'cost',
                    'align' => 'center',
                ],
                [
                    'id' => 'totalDiscount',
                    'header' => $this->l('Discount'),
                    'dataIndex' => 'totalDiscount',
                    'align' => 'center',
                ],
                [
                    'id' => 'profit',
                    'header' => $this->l('Profit'),
                    'dataIndex' => 'profit',
                    'align' => 'center',
                ],
            ],
            'defaultSortColumn' => 'date_add',
            'defaultSortDirection' => 'ASC',
            'emptyMessage' => $this->l('An empty record-set was returned.'),
            'pagingMessage' => sprintf($this->l('Displaying %1$s of %2$s'), '{0} - {1}', '{2}'),
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
        $export = !!Tools::getValue('export');
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $date_between = $this->getDate();

        $query = 'SELECT o.id_order as number, o.invoice_number as invoice_number, DATE_FORMAT(o.invoice_date, \'%Y-%m-%d\') as invoice_date, ROUND( o.total_paid / o.conversion_rate , 2 ) as paid,  ROUND((o.total_paid / o.conversion_rate + o.total_discounts_tax_incl / o.conversion_rate), 2 ) as total, ROUND((o.total_paid / o.conversion_rate - total_paid_tax_excl / o.conversion_rate) , 2 ) as TaxTotal, ROUND( o.total_shipping_tax_excl / o.conversion_rate , 2 ) AS shipping, ROUND(SUM(o.total_discounts_tax_incl / o.conversion_rate),2) as totalDiscount, ((
			SELECT ROUND(SUM(od.original_wholesale_price / o.conversion_rate * od.product_quantity), 2)
                        FROM ' . _DB_PREFIX_ . 'order_detail od
			LEFT JOIN ' . _DB_PREFIX_ . 'product p ON od.product_id = p.id_product
			LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON pa.id_product_attribute = od.product_attribute_id
			WHERE od.id_order = o.`id_order`
			)) AS cost,
			((
			SELECT ROUND(o.`total_paid` / o.conversion_rate - SUM(od.original_wholesale_price / o.conversion_rate * od.product_quantity) , 2)
			FROM ' . _DB_PREFIX_ . 'order_detail od
			LEFT JOIN ' . _DB_PREFIX_ . 'product p ON od.product_id = p.id_product
			LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON pa.id_product_attribute = od.product_attribute_id
			WHERE od.id_order = o.`id_order`
			) -
			(ROUND( o.total_paid_tax_incl / o.conversion_rate - o.total_paid_tax_excl / o.conversion_rate, 2 )) -
			ROUND( o.total_shipping_tax_excl / o.conversion_rate , 2 )
			) AS profit
			FROM `' . _DB_PREFIX_ . 'orders` o
			WHERE o.valid = 1
			AND o.invoice_date BETWEEN ' . $date_between . '
			GROUP BY o.`id_order`';

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
            $value['paid'] = Tools::displayPrice($value['paid'], $currency);
            $value['total'] = Tools::displayPrice($value['total'], $currency);
            $value['shipping'] = Tools::displayPrice($value['shipping'], $currency);
            $value['TaxTotal'] = Tools::displayPrice($value['TaxTotal'], $currency);
            $value['cost'] = Tools::displayPrice($value['cost'], $currency);
            $value['totalDiscount'] = Tools::displayPrice($value['totalDiscount'], $currency);
            $value['profit'] = Tools::displayPrice($value['profit'], $currency);
            if (! $export) {
                $drilldown = Context::getContext()->link->getAdminLink('AdminStats', true, [
                    'module' => 'statsproductsprofit',
                    'id_order' => (int)$value['number']
                ]);
                $value['profit'] = '<a href="'.Tools::safeOutput($drilldown).'">'.$value['profit'].'</a>';
            }
        }
        unset($value);

        $this->_values = $values;

        if (Validate::IsUnsignedInt($this->_limit)) {
            $totalQuery = (new DbQuery())
                ->select('COUNT(1)')
                ->from('orders',  'o')
                ->where('o.valid = 1')
                ->where('o.invoice_date BETWEEN ' . $date_between);
            $this->_totalCount = (int)$conn->getValue($totalQuery);
        } else {
            $this->_totalCount = count($values);
        }
    }
}
