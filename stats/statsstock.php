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

class StatsStock extends StatsModule
{
    const PARAM_CATEGORY = 'id_category';
    const PARAM_ENABLE_STATUS = 'enable_status';

    const STATUS_ALL = 'all';
    const STATUS_ENABLED = 'enabled';
    const STATUS_DISABLED = 'disabled';

    /**
     * @var string
     */
    protected $html = '';

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_CUSTOM;

        $this->displayName = $this->l('Available quantities');
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $categoryId = $this->getCategoryId();
        $enableStatus = $this->getEnableStatus();

        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        if ($method === 'POST') {
            $params = [ 'module' => 'statsstock' ];
            if ($categoryId) {
                $params[static::PARAM_CATEGORY] = $categoryId;
            }
            if ($enableStatus !== static::STATUS_ALL) {
                $params[static::PARAM_ENABLE_STATUS] = $enableStatus;
            }
            $url = Context::getContext()->link->getAdminLink('AdminStats', true, $params);
            Tools::redirectAdmin($url);
        }

        $ru = AdminController::$currentIndex . '&module=statsstock&token=' . Tools::getValue('token');
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));

        $filters = [];
        if ($categoryId) {
            $filters[] = 'p.id_product IN (SELECT cp.id_product FROM ' . _DB_PREFIX_ . 'category_product cp WHERE cp.id_category = ' . $categoryId . ')';
        }
        if ($enableStatus === static::STATUS_ENABLED) {
            $filters[] = 'product_shop.active = 1';
        }
        if ($enableStatus === static::STATUS_DISABLED) {
            $filters[] = 'product_shop.active = 0';
        }

        $sql = 'SELECT p.id_product, p.reference, pl.name,
				IFNULL((
					SELECT AVG(product_attribute_shop.wholesale_price)
					FROM ' . _DB_PREFIX_ . 'product_attribute pa
					' . Shop::addSqlAssociation('product_attribute', 'pa') . '
					WHERE p.id_product = pa.id_product
					AND product_attribute_shop.wholesale_price != 0
				), product_shop.wholesale_price) as wholesale_price,
				IFNULL(stock.quantity, 0) as quantity
				FROM ' . _DB_PREFIX_ . 'product p
				' . Shop::addSqlAssociation('product', 'p') . '
				INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl
					ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->context->language->id . Shop::addSqlRestrictionOnLang('pl') . ')
				' . Product::sqlStock('p', 0);
        if ($filters) {
            $sql .= ' WHERE ' . implode(' AND ', $filters);
        }

        $products = Db::getInstance()->executeS($sql);

        foreach ($products as $key => $p) {
            $products[$key]['stockvalue'] = $p['wholesale_price'] * $p['quantity'];
        }

        $this->html .= '
		<script type="text/javascript">$(\'#calendar\').slideToggle();</script>

		<div class="panel-heading">'
            . $this->l('Evaluation of available quantities for sale') .
            '</div>
		<form action="' . Tools::safeOutput($ru) . '" method="post" class="form-horizontal">
			<div class="row row-margin-bottom">
				<label class="control-label col-lg-3">' . $this->l('Category') . '</label>
				<div class="col-lg-6">
					<select name="'.static::PARAM_CATEGORY.'" onchange="this.form.submit();">
						' . $this->utils->getCategoryOptions($categoryId). '
					</select>
				</div>
			</div>
			<div class="row">
				<label class="control-label col-lg-3">' . $this->l('Enabled status') . '</label>
				<div class="col-lg-6">
					<select name="'.static::PARAM_ENABLE_STATUS.'" onchange="this.form.submit();">
					    <option value="'.static::STATUS_ALL.'" '.($enableStatus === static::STATUS_ALL ? ' selected="selected"' : '') .'>'.$this->l('All').'</option>
					    <option value="'.static::STATUS_ENABLED.'" '.($enableStatus === static::STATUS_ENABLED ? ' selected="selected"' : '') .'>'.$this->l('Active products').'</option>
					    <option value="'.static::STATUS_DISABLED.'" '.($enableStatus === static::STATUS_DISABLED ? ' selected="selected"' : '') .'>'.$this->l('Disabled products').'</option>
					</select>
				</div>
            </div>
		</form>';

        if (!count($products)) {
            $this->html .= '<p>' . $this->l('No product matches criteria.') . '</p>';
        } else {
            $rollup = ['quantity' => 0, 'wholesale_price' => 0, 'stockvalue' => 0];
            $this->html .= '
			<table class="table">
				<thead>
					<tr>
						<th><span class="title_box active">' . $this->l('ID') . '</span></th>
						<th><span class="title_box active">' . $this->l('Ref.') . '</span></th>
						<th><span class="title_box active">' . $this->l('Item') . '</span></th>
						<th><span class="title_box active">' . $this->l('Available quantity for sale') . '</span></th>
						<th><span class="title_box active">' . $this->l('Price*') . '</span></th>
						<th><span class="title_box active">' . $this->l('Value') . '</span></th>
					</tr>
				</thead>
				<tbody>';
            foreach ($products as $product) {
                $rollup['quantity'] += $product['quantity'];
                $rollup['wholesale_price'] += $product['wholesale_price'];
                $rollup['stockvalue'] += $product['stockvalue'];
                $this->html .= '<tr>
						<td>' . $product['id_product'] . '</td>
						<td>' . $product['reference'] . '</td>
						<td>' . $product['name'] . '</td>
						<td>' . $product['quantity'] . '</td>
						<td>' . Tools::displayPrice($product['wholesale_price'], $currency) . '</td>
						<td>' . Tools::displayPrice($product['stockvalue'], $currency) . '</td>
					</tr>';
            }
            $this->html .= '
				</tbody>
				<tfoot>
					<tr>
						<th colspan="3"></th>
						<th><span class="title_box active">' . $this->l('Total quantities') . '</span></th>
						<th><span class="title_box active">' . $this->l('Average price') . '</span></th>
						<th><span class="title_box active">' . $this->l('Total value') . '</span></th>
					</tr>
					<tr>
						<td colspan="3"></td>
						<td>' . $rollup['quantity'] . '</td>
						<td>' . Tools::displayPrice($rollup['wholesale_price'] / count($products), $currency) . '</td>
						<td>' . Tools::displayPrice($rollup['stockvalue'], $currency) . '</td>
					</tr>
				</tfoot>
			</table>
			<i class="icon-asterisk"></i> ' . $this->l('This section corresponds to the default wholesale price according to the default supplier for the product. An average price is used when the product has attributes.');
        }
        return $this->html;
    }

    /**
     * @return int
     */
    private function getCategoryId()
    {
        return (int)Tools::getValue(static::PARAM_CATEGORY, 0);
    }

    /**
     * @return string
     */
    private function getEnableStatus()
    {
        $value = strtolower(Tools::getValue(static::PARAM_ENABLE_STATUS, 'all'));
        if (in_array($value, [static::STATUS_ALL, static::STATUS_ENABLED, static::STATUS_DISABLED])) {
            return $value;
        }
        return static::STATUS_ALL;
    }
}
