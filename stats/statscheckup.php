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

/**
 * Class StatsCheckUp
 *
 * @since 1.0.0
 */
class StatsCheckUp extends StatsModule
{
    const ORDER_PRODUCT_ID = 0;
    const ORDER_SALES = 1;
    const ORDER_NAME = 2;

    /**
     * @var string
     */
    protected $html = '';

    /**
     * StatsCheckUp constructor.
     *
     * @throws PrestaShopException
     * @since 1.0.0
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_CUSTOM;

        $this->displayName = $this->l('Catalog evaluation');
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     * @since 1.0.0
     */
    public function hookAdminStatsModules()
    {
        if (Tools::isSubmit('submitCheckup')) {
            $confs = [
                'CHECKUP_DESCRIPTIONS_LT',
                'CHECKUP_DESCRIPTIONS_GT',
                'CHECKUP_IMAGES_LT',
                'CHECKUP_IMAGES_GT',
                'CHECKUP_SALES_LT',
                'CHECKUP_SALES_GT',
                'CHECKUP_STOCK_LT',
                'CHECKUP_STOCK_GT',
            ];
            foreach ($confs as $confname) {
                Configuration::updateValue($confname, (int)Tools::getValue($confname));
            }
            echo '<div class="conf confirm"> ' . $this->l('Configuration updated') . '</div>';
        }

        $orderOption = (int)Tools::getValue('submitCheckupOrder');
        $orderBy = $this->getOrderBy($orderOption);

        $conn = Db::readOnly();
        $employee = Context::getContext()->employee;
        $prop30 = ((strtotime($employee->stats_date_to . ' 23:59:59') - strtotime($employee->stats_date_from . ' 00:00:00')) / 60 / 60 / 24) / 30;

        // Get languages
        $sql = (new DbQuery())
            ->select('lang_shop.id_shop')
            ->select('lang_shop.id_lang')
            ->select('lang.iso_code')
            ->select('shop.name')
            ->from('lang', 'lang')
            ->innerJoin('lang_shop', 'lang_shop', 'lang_shop.id_lang = lang.id_lang')
            ->innerJoin('shop', 'shop', 'lang_shop.id_shop = shop.id_shop')
            ->addCurrentShopRestriction('lang_shop')
            ->orderBy('lang_shop.id_shop, lang.iso_code');
        $languages = [];
        foreach ($conn->getArray($sql) as $row) {
            $key = $row['id_shop'] . '_' . $row['id_lang'];
            $languages[$key] = [
                'iso_code' => $row['iso_code'],
                'shopId' => (int)$row['id_shop'],
                'shopName' => $row['name'],
            ];
        }

        $arrayColors = [
            0 => '<img src="../modules/statsmodule/views/img/red.png" title="' . Tools::safeOutput($this->l('Bad')) . '" />',
            1 => '<img src="../modules/statsmodule/views/img/orange.png" title="' . Tools::safeOutput($this->l('Average')) . '" />',
            2 => '<img src="../modules/statsmodule/views/img/green.png" title="' . Tools::safeOutput($this->l('Good')) . '" />',
        ];
        $divisor = 4;
        $totals = ['products' => 0, 'active' => 0, 'images' => 0, 'sales' => 0, 'stock' => 0];
        foreach ($languages as $key => $_) {
            $divisor++;
            $totals['description_' . $key] = 0;
        }


        // Get products stats
        $sql = 'SELECT p.id_product, product_shop.active, pl.name, (
					SELECT COUNT(*)
					FROM ' . _DB_PREFIX_ . 'image i
					' . Shop::addSqlAssociation('image', 'i') . '
					WHERE i.id_product = p.id_product
				) as nbImages, (
					SELECT SUM(od.product_quantity)
					FROM ' . _DB_PREFIX_ . 'orders o
					LEFT JOIN ' . _DB_PREFIX_ . 'order_detail od ON o.id_order = od.id_order
					WHERE od.product_id = p.id_product
						AND o.invoice_date BETWEEN ' . ModuleGraph::getDateBetween() . '
						' . Shop::addSqlRestriction(false, 'o') . '
				) as nbSales,
				IFNULL(stock.quantity, 0) as stock
				FROM ' . _DB_PREFIX_ . 'product p
				' . Shop::addSqlAssociation('product', 'p') . '
				' . Product::sqlStock('p', 0) . '
				LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
					ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->context->language->id . Shop::addSqlRestrictionOnLang('pl') . ')
				ORDER BY ' . $orderBy;
        $result = $conn->getArray($sql);

        if (!$result) {
            return $this->l('No product was found.');
        }

        $arrayConf = [
            'DESCRIPTIONS' => ['name' => $this->l('Descriptions'), 'text' => $this->l('chars (without HTML)')],
            'IMAGES' => ['name' => $this->l('Images'), 'text' => $this->l('images')],
            'SALES' => ['name' => $this->l('Sales'), 'text' => $this->l('orders / month')],
            'STOCK' => ['name' => $this->l('Available quantity for sale'), 'text' => $this->l('items')],
        ];

        $this->html = '
		<div class="panel-heading">'
            . $this->displayName . '
		</div>
		<form action="' . Tools::safeOutput(AdminController::$currentIndex . '&token=' . Tools::getValue('token') . '&module=statscheckup') . '" method="post" class="checkup form-horizontal">
			<table class="table checkup">
				<thead>
					<tr>
						<th></th>
						<th><span class="title_box active">' . $arrayColors[0] . ' ' . Tools::safeOutput($this->l('Not enough')) . '</span></th>
						<th><span class="title_box active">' . $arrayColors[2] . ' ' . Tools::safeOutput($this->l('Alright')) . '</span></th>
					</tr>
				</thead>';
        foreach ($arrayConf as $conf => $translations) {
            $this->html .= '
				<tbody>
					<tr>
						<td>
							<label class="control-label col-lg-12">' . Tools::safeOutput($translations['name']) . '</label>
						</td>
						<td>
							<div class="row">
								<div class="col-lg-11 input-group">
									<span class="input-group-addon">' . Tools::safeOutput($this->l('Less than')) . '</span>
									<input type="text" name="CHECKUP_' . $conf . '_LT" value="' . Tools::safeOutput(Tools::getValue('CHECKUP_' . $conf . '_LT', Configuration::get('CHECKUP_' . $conf . '_LT'))) . '" />
									<span class="input-group-addon">' . Tools::safeOutput($translations['text']) . '</span>
								 </div>
							 </div>
						</td>
						<td>
							<div class="row">
								<div class="col-lg-12 input-group">
									<span class="input-group-addon">' . Tools::safeOutput($this->l('Greater than')) . '</span>
									<input type="text" name="CHECKUP_' . $conf . '_GT" value="' . Tools::safeOutput(Tools::getValue('CHECKUP_' . $conf . '_GT', Configuration::get('CHECKUP_' . $conf . '_GT'))) . '" />
									<span class="input-group-addon">' . Tools::safeOutput($translations['text']) . '</span>
								 </div>
							 </div>
						</td>
					</tr>
				</tbody>';
        }
        $this->html .= '</table>
			<button type="submit" name="submitCheckup" class="btn btn-default pull-right">
				<i class="icon-save"></i> ' . Tools::safeOutput($this->l('Save')) . '
			</button>
		</form>
		<form action="' . Tools::safeOutput(AdminController::$currentIndex . '&token=' . Tools::getValue('token') . '&module=statscheckup') . '" method="post" class="form-horizontal alert">
			<div class="row">
				<div class="col-lg-12">
					<label class="control-label pull-left">' . Tools::safeOutput($this->l('Order by')) . '</label>
					<div class="col-lg-3">
						<select name="submitCheckupOrder" onchange="this.form.submit();">
							<option value="'.static::ORDER_PRODUCT_ID.'" '. ($orderOption === static::ORDER_PRODUCT_ID ? 'selected="selected"' : '') . '>' . Tools::safeOutput($this->l('ID')) . '</option>
							<option value="'.static::ORDER_NAME.'" ' . ($orderOption === static::ORDER_NAME ? 'selected="selected"' : '') . '>' . Tools::safeOutput($this->l('Name')) . '</option>
							<option value="'.static::ORDER_SALES.'" ' . ($orderOption === static::ORDER_SALES ? 'selected="selected"' : '') . '>' . Tools::safeOutput($this->l('Sales')) . '</option>
						</select>
					</div>
				</div>
			</div>
		</form>
		<div style="overflow-x:auto">
		<table class="table checkup2">
			<thead>
				<tr>
					<th><span class="title_box active">' . Tools::safeOutput($this->l('ID')) . '</span></th>
					<th><span class="title_box active">' . Tools::safeOutput($this->l('Item')) . '</span></th>
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Active')) . '</span></th>';
        foreach ($languages as $language) {
            $this->html .= '<th><span class="title_box active" title="'.Tools::safeOutput($language['shopName']).'">' . $this->getDescColumnTitle($language) . '</span></th>';
        }
        $this->html .= '
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Images')) . '</span></th>
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Sales')) . '</span></th>
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Available quantity for sale')) . '</span></th>
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Global')) . '</span></th>
				</tr>
			</thead>
			<tbody>';
        foreach ($result as $row) {
            $productId = (int)$row['id_product'];
            $totals['products']++;
            $scores = [
                'active' => ($row['active'] ? 2 : 0),
                'images' => ($row['nbImages'] < Configuration::get('CHECKUP_IMAGES_LT') ? 0 : ($row['nbImages'] > Configuration::get('CHECKUP_IMAGES_GT') ? 2 : 1)),
                'sales' => (($row['nbSales'] * $prop30 < Configuration::get('CHECKUP_SALES_LT')) ? 0 : (($row['nbSales'] * $prop30 > Configuration::get('CHECKUP_SALES_GT')) ? 2 : 1)),
                'stock' => (($row['stock'] < Configuration::get('CHECKUP_STOCK_LT')) ? 0 : (($row['stock'] > Configuration::get('CHECKUP_STOCK_GT')) ? 2 : 1)),
            ];
            $totals['active'] += (int)$scores['active'];
            $totals['images'] += (int)$scores['images'];
            $totals['sales'] += (int)$scores['sales'];
            $totals['stock'] += (int)$scores['stock'];

            $descriptionSql = (new DbQuery())
                ->select('pl.id_shop')
                ->select('pl.id_lang')
                ->select('pl.description')
                ->from('product_lang', 'pl')
                ->innerJoin('shop', 's', 'pl.id_shop = s.id_shop')
                ->innerJoin('lang', 'l', 'pl.id_lang = l.id_lang')
                ->where('pl.id_product = ' . $productId)
                ->addCurrentShopRestriction('pl');
            foreach ($conn->getArray($descriptionSql) as $descriptionRow) {
                $shopId = (int)$descriptionRow['id_shop'];
                $langId = (int)$descriptionRow['id_lang'];
                $description = (string)$descriptionRow['description'];

                $descriptionKey = 'description_' . $shopId . '_' . $langId;
                $descLengthKey = 'desclength_' . $shopId . '_' . $langId;
                $descLength = mb_strlen(strip_tags($description));
                $row[$descLengthKey] = $descLength;

                if ($descLength < (int)Configuration::get('CHECKUP_DESCRIPTIONS_LT')) {
                    $scores[$descriptionKey] = 0;
                } elseif ($descLength > (int)Configuration::get('CHECKUP_DESCRIPTIONS_GT')) {
                    $scores[$descriptionKey] = 2;
                } else {
                    $scores[$descriptionKey] = 1;
                }
                if (isset($totals[$descriptionKey])) {
                    $totals[$descriptionKey] += $scores[$descriptionKey];
                } else {
                    $totals[$descriptionKey] = $scores[$descriptionKey];
                }
            }
            $scores['average'] = array_sum($scores) / $divisor;
            $scores['average'] = ($scores['average'] < 1 ? 0 : ($scores['average'] > 1.5 ? 2 : 1));

            $this->html .= '
				<tr>
					<td>' . $productId . '</td>
					<td><a href="' . Tools::safeOutput($this->getProductEditUrl($productId)) . '">' . mb_substr($row['name'], 0, 42) . '</a></td>
					<td class="center">' . $arrayColors[$scores['active']] . '</td>';
            foreach ($languages as $key => $language) {
                if (isset($row['desclength_' . $key])) {
                    $cellContent = (int)$row['desclength_' . $key] . ' ' . $arrayColors[$scores['description_' . $key]];
                } else {
                    $cellContent = '0 ' . $arrayColors[0];
                }
                $productUrl = $this->getProductEditUrl($productId, $language['shopId']);
                $this->html .= '<td class="center"><a href="' . Tools::safeOutput($productUrl) . '">' . $cellContent . '</td>';
            }
            $this->html .= '
					<td class="center">' . (int)$row['nbImages'] . ' ' . $arrayColors[$scores['images']] . '</td>
					<td class="center">' . (int)$row['nbSales'] . ' ' . $arrayColors[$scores['sales']] . '</td>
					<td class="center">' . (int)$row['stock'] . ' ' . $arrayColors[$scores['stock']] . '</td>
					<td class="center">' . $arrayColors[$scores['average']] . '</td>
				</tr>';
        }

        $this->html .= '</tbody>';

        $totals['active'] = $totals['active'] / $totals['products'];
        $totals['active'] = ($totals['active'] < 1 ? 0 : ($totals['active'] > 1.5 ? 2 : 1));
        $totals['images'] = $totals['images'] / $totals['products'];
        $totals['images'] = ($totals['images'] < 1 ? 0 : ($totals['images'] > 1.5 ? 2 : 1));
        $totals['sales'] = $totals['sales'] / $totals['products'];
        $totals['sales'] = ($totals['sales'] < 1 ? 0 : ($totals['sales'] > 1.5 ? 2 : 1));
        $totals['stock'] = $totals['stock'] / $totals['products'];
        $totals['stock'] = ($totals['stock'] < 1 ? 0 : ($totals['stock'] > 1.5 ? 2 : 1));
        foreach ($languages as $key => $language) {
            $totals['description_' . $key] = $totals['description_' . $key] / $totals['products'];
            $totals['description_' . $key] = ($totals['description_' . $key] < 1 ? 0 : ($totals['description_' . $key] > 1.5 ? 2 : 1));
        }
        $totals['average'] = array_sum($totals) / $divisor;
        $totals['average'] = ($totals['average'] < 1 ? 0 : ($totals['average'] > 1.5 ? 2 : 1));

        $this->html .= '
			<tfoot>
				<tr>
					<th colspan="2"></th>
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Active')) . '</span></th>';
        foreach ($languages as $language) {
            $this->html .= '<th class="center"><span class="title_box active" title="'.Tools::safeOutput($language['shopName']).'">' . $this->getDescColumnTitle($language) . '</span></th>';
        }
        $this->html .= '
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Images')) . '</span></th>
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Sales')) . '</span></th>
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Available quantity for sale')) . '</span></th>
					<th class="center"><span class="title_box active">' . Tools::safeOutput($this->l('Global')) . '</span></th>
				</tr>
				<tr>
					<td colspan="2"></td>
					<td class="center">' . $arrayColors[$totals['active']] . '</td>';
        foreach ($languages as $key => $language) {
            $this->html .= '<td class="center">' . $arrayColors[$totals['description_' . $key]] . '</td>';
        }
        $this->html .= '
					<td class="center">' . $arrayColors[$totals['images']] . '</td>
					<td class="center">' . $arrayColors[$totals['sales']] . '</td>
					<td class="center">' . $arrayColors[$totals['stock']] . '</td>
					<td class="center">' . $arrayColors[$totals['average']] . '</td>
				</tr>
			</tfoot>
		</table></div>';

        return $this->html;
    }

    /**
     * @param array $language
     *
     * @return string
     * @throws PrestaShopException
     */
    protected function getDescColumnTitle($language)
    {
        $title = Tools::safeOutput($this->l('Desc.')) . ' (' . Tools::safeOutput(strtoupper($language['iso_code'])) . ')';
        if (Shop::isFeatureActive()) {
            $title = $language['shopName'] . '<br />' . $title;
        }
        return $title;
    }

    /**
     * @param int $productId
     * @param int $shopContext
     *
     * @return string
     * @throws PrestaShopException
     */
    protected function getProductEditUrl(int $productId, int $shopContext = 0)
    {
        $link = Context::getContext()->link;
        $params = [
            'id_product' => $productId,
            'updateproduct' => 1
        ];
        if ($shopContext && Shop::isFeatureActive()) {
            $params['setShopContext'] = 's-' . $shopContext;
        }
        return $link->getAdminLink('AdminProducts', true, $params);
    }

    /**
     * @return string
     */
    protected function getOrderBy($orderOption)
    {
        switch ($orderOption) {
            case static::ORDER_SALES:
                return 'nbSales DESC';
            case static::ORDER_NAME:
                return 'pl.name';
            case static::ORDER_PRODUCT_ID:
            default:
                return 'p.id_product';
        }
    }
}
