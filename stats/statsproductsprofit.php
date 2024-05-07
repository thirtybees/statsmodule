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

use Thirtybees\StatsModule\Utils;

if (!defined('_TB_VERSION_')) {
    exit;
}

class StatsProductsProfit extends StatsModule
{
    /**
     * @var int
     */
    private $categoryId;

    /**
     * @var int
     */
    private $orderId;

    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRID;
        $this->displayName = $this->l('Products profit');
    }

    /**
     * @param string $option
     * @param int $layers
     *
     * @return void
     */
    public function setOption($option, $layers = 1)
    {
        $ids = array_map('intval', explode('-', $option));
        if ($ids && count($ids) === 2) {
            $this->categoryId = $ids[0];
            $this->orderId = $ids[1];
        }
    }


    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $this->categoryId = (int)Tools::getValue('id_category');
        $this->orderId = (int)Tools::getValue('id_order');
        $engineParams = [
            'id' => 'id_product',
            'title' => $this->displayName,
            'columns' => [
                [
                    'id' => 'id_product',
                    'header' => $this->l('Product ID'),
                    'dataIndex' => 'id_product',
                    'align' => 'center',
                ],
                [
                    'id' => 'reference',
                    'header' => $this->l('Product reference'),
                    'dataIndex' => 'reference',
                    'align' => 'center',
                ],
                [
                    'id' => 'name',
                    'header' => $this->l('Product name'),
                    'dataIndex' => 'name',
                    'align' => 'center',
                ],
                [
                    'id' => 'orders',
                    'header' => $this->l('Orders'),
                    'dataIndex' => 'orders',
                    'align' => 'center',
                ],
                [
                    'id' => 'quantity',
                    'header' => $this->l('Quantity'),
                    'dataIndex' => 'quantity',
                    'align' => 'center',
                ],
                [
                    'id' => 'total_income',
                    'header' => $this->l('Sales'),
                    'dataIndex' => 'total_income',
                    'align' => 'center',
                ],
                [
                    'id' => 'total_costs',
                    'header' => $this->l('Costs'),
                    'dataIndex' => 'total_costs',
                    'align' => 'center',
                ],
                [
                    'id' => 'profit',
                    'header' => $this->l('Profit'),
                    'dataIndex' => 'profit',
                    'align' => 'center',
                ],
            ],
            'defaultSortColumn' => 'id_product',
            'defaultSortDirection' => 'ASC',
            'emptyMessage' => $this->l('An empty record-set was returned.'),
            'pagingMessage' => sprintf($this->l('Displaying %1$s of %2$s'), '{0} - {1}', '{2}'),
            'option' => $this->categoryId . '-' . $this->orderId,
        ];

        if (Tools::getValue('export')) {
            $this->csvExport($engineParams);
        }

        $info = '';
        if ($this->orderId) {
            $info = '<p class="alert alert-warning">' . sprintf($this->l('Displaying products from order ID %s'), $this->orderId) . '</p>';
        }
        return (
            '<div class="panel-heading">' . $this->displayName . '</div>'.$info.'
			<form action="#" method="post" id="categoriesForm" class="form-horizontal">
				<div class="row row-margin-bottom">
					<label class="control-label col-lg-3">' . $this->l('Choose a category') . '</label>
					<div class="col-lg-9">
						<select name="id_category" onchange="$(\'#categoriesForm\').submit();">
							'.$this->utils->getCategoryOptions($this->categoryId).'
						</select>
					</div>
				</div>
			</form>'
            . $this->engine($engineParams) . '
            <a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
                <i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
            </a>'
        );
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
        $idLang = (int)Context::getContext()->language->id;
        $conn = Db::getInstance(_PS_USE_SQL_SLAVE_);

        $baseQuery = (new DbQuery())
            ->from('orders', 'o')
            ->innerJoin('order_detail', 'od', '(od.id_order = o.id_order)')
            ->innerJoin('product', 'p', '(p.id_product = od.product_id)')
            ->leftJoin('product_lang', 'pl', '(pl.`id_product` = p.`id_product` AND pl.`id_lang` = '. $idLang . Shop::addSqlRestrictionOnLang('pl') .')')
            ->where('o.valid = 1')
            ->where('o.invoice_date BETWEEN ' . $this->getDate())
            ->addCurrentShopRestriction('o');

        if ($this->orderId) {
            $baseQuery->where('o.id_order = ' . (int)$this->orderId);
        }

        if ($this->categoryId) {
            $baseQuery->where('p.id_category_default = ' . (int)$this->categoryId);
        }

        $query = (clone $baseQuery)
            ->select('p.id_product')
            ->select('p.reference')
            ->select('pl.name')
            ->select('COUNT(DISTINCT o.id_order) AS orders')
            ->select('SUM(od.product_quantity) AS quantity')
            ->select('ROUND(SUM(od.`original_wholesale_price` / o.conversion_rate * od.`product_quantity`), 2) AS total_costs')
            ->select('ROUND(SUM(od.total_price_tax_excl / o.conversion_rate), 2) AS total_income')
            ->orderBy('p.id_product')
            ->groupBy('p.id_product');

        if (Validate::IsName($this->_sort)) {
            $dir = isset($this->_direction) && Validate::isSortDirection($this->_direction) ? $this->_direction : 'ASC';
            $query->orderBy($this->_sort . ' ' . $dir);
        }

        if (Validate::IsUnsignedInt($this->_limit)) {
            $query->limit((int)$this->_limit, (int)$this->_start);
        }

        $values = $conn->executeS($query);
        if (! is_array($values)) {
            $values = [];
        }
        foreach ($values as &$value) {
            $costs = round((float)$value['total_costs'], 2);
            $income = round((float)$value['total_income'], 2);
            $profit = round($income - $costs, 2);
            $value['total_costs'] = Tools::displayPrice($costs, $currency);
            $value['total_income'] = Tools::displayPrice($income, $currency);
            $value['profit'] = Tools::displayPrice($profit, $currency);
            $value['name'] = Tools::safeOutput($value['name']);
            $value['reference'] = Tools::safeOutput($value['reference']);
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
            $totalQuery = (clone $baseQuery)->select('COUNT(DISTINCT p.id_product)');
            $this->_totalCount = (int)$conn->getValue($totalQuery);
        } else {
            $this->_totalCount = count($values);
        }

    }
}
