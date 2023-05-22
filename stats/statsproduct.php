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

class StatsProduct extends StatsModule
{
    protected $html = '';
    protected $query = '';
    protected $option = 0;
    protected $id_product = 0;
    protected $packTracking = false;

    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRAPH;

        $this->displayName = Translate::getModuleTranslation('statsmodule', 'Product details', 'statsmodule');
        $this->description = Translate::getModuleTranslation('statsmodule', 'Adds detailed statistics for each product to the Stats dashboard.', 'statsmodule');

        $this->packTracking = class_exists('OrderDetailPack');
    }

    /**
     * Returns total sales of product as individual item -- separate line in order
     *
     * @param int $productId
     *
     * @return int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getTotalBought($productId)
    {
        $dateBetween = ModuleGraph::getDateBetween();
        $productId = (int)$productId;
        $sql = (new DbQuery())
            ->select('SUM(od.product_quantity) AS total')
            ->from('order_detail', 'od')
            ->innerJoin('orders', 'o', 'o.id_order = od.id_order')
            ->where("od.product_id = $productId")
            ->where('o.valid = 1 ' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o'))
            ->where("o.date_add BETWEEN $dateBetween");
        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Returns total sales of product sold as part of a pack
     *
     * @param int $productId
     *
     * @return int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getTotalBoughtAsPartOfPack($productId)
    {
        if ($this->packTracking) {
            $dateBetween = ModuleGraph::getDateBetween();
            $productId = (int)$productId;
            $sql = (new DbQuery())
                ->select('SUM(od.product_quantity * p.quantity) AS total')
                ->from('order_detail', 'od')
                ->innerJoin('order_detail_pack', 'p', 'p.id_order_detail = od.id_order_detail')
                ->innerJoin('orders', 'o', 'o.id_order = od.id_order')
                ->where("p.id_product = $productId")
                ->where('o.valid = 1 ' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o'))
                ->where("o.date_add BETWEEN $dateBetween");
            return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        }
        return 0;
    }

    /**
     * @param $id_product
     *
     * @return float
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getTotalSales($id_product)
    {
        $date_between = ModuleGraph::getDateBetween();
        $sql = 'SELECT SUM(od.`total_price_tax_excl`) AS total
				FROM `' . _DB_PREFIX_ . 'order_detail` od
				LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = od.`id_order`
				WHERE od.`product_id` = ' . (int)$id_product . '
					' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
					AND o.valid = 1
					AND o.`date_add` BETWEEN ' . $date_between;

        return (float)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * @param $id_product
     *
     * @return int|mixed
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getTotalViewed($id_product)
    {
        $date_between = ModuleGraph::getDateBetween();
        $sql = 'SELECT SUM(pv.`counter`) AS total
				FROM `' . _DB_PREFIX_ . 'page_viewed` pv
				LEFT JOIN `' . _DB_PREFIX_ . 'date_range` dr ON pv.`id_date_range` = dr.`id_date_range`
				LEFT JOIN `' . _DB_PREFIX_ . 'page` p ON pv.`id_page` = p.`id_page`
				LEFT JOIN `' . _DB_PREFIX_ . 'page_type` pt ON pt.`id_page_type` = p.`id_page_type`
				WHERE pt.`name` = \'product\'
					' . Shop::addSqlRestriction(false, 'pv') . '
					AND p.`id_object` = ' . (int)$id_product . '
					AND dr.`time_start` BETWEEN ' . $date_between . '
					AND dr.`time_end` BETWEEN ' . $date_between;
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        return isset($result['total']) ? $result['total'] : 0;
    }

    /**
     * @param $id_lang
     *
     * @return array|bool|PDOStatement|null
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getProducts($id_lang)
    {
        $sql = 'SELECT p.`id_product`, p.reference, pl.`name`, IFNULL(stock.quantity, 0) as quantity
				FROM `' . _DB_PREFIX_ . 'product` p
				' . Product::sqlStock('p', 0) . '
				LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON p.`id_product` = pl.`id_product`' . Shop::addSqlRestrictionOnLang('pl') . '
				' . Shop::addSqlAssociation('product', 'p') . '
				' . (Tools::getValue('id_category') ? 'LEFT JOIN `' . _DB_PREFIX_ . 'category_product` cp ON p.`id_product` = cp.`id_product`' : '') . '
				WHERE pl.`id_lang` = ' . (int)$id_lang . '
					' . (Tools::getValue('id_category') ? 'AND cp.id_category = ' . (int)Tools::getValue('id_category') : '') . '
				ORDER BY pl.`name`';

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * @param int $productId
     *
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getSales($productId)
    {
        $productId = (int)$productId;
        $sql = (new DbQuery())
            ->select('o.date_add')
            ->select('o.id_order')
            ->select('od.product_id AS id_product')
            ->select('o.id_customer')
            ->select('od.product_quantity')
            ->select('(od.product_price * od.product_quantity) as total')
            ->select('od.product_name')
            ->from('orders', 'o')
            ->innerJoin('order_detail', 'od', 'o.id_order = od.id_order')
            ->where('o.date_add BETWEEN ' . $this->getDate())
            ->where('o.valid ' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o'));

        if ($this->packTracking) {
            $sql->leftJoin('order_detail_pack', 'p', "(p.id_order_detail = od.id_order_detail AND p.id_product = $productId)");
            $sql->select('IFNULL(p.quantity, 0) AS pack_quantity');
            $sql->where("(od.product_id = $productId OR p.id_product = $productId)");
        } else {
            $sql->select('0 AS pack_quantity');
            $sql->where('od.product_id = ' . $productId);
        }
        $conn = Db::getInstance(_PS_USE_SQL_SLAVE_);
        return $conn->executeS($sql);
    }

    /**
     * @param $id_product
     * @param $id_lang
     *
     * @return array|bool|PDOStatement|null
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getCrossSales($id_product, $id_lang)
    {
        $sql = 'SELECT pl.name as pname, pl.id_product, SUM(od.product_quantity) as pqty, AVG(od.product_price) as pprice
				FROM `' . _DB_PREFIX_ . 'orders` o
				LEFT JOIN `' . _DB_PREFIX_ . 'order_detail` od ON o.id_order = od.id_order
				LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (pl.id_product = od.product_id AND pl.id_lang = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('pl') . ')
				WHERE o.id_customer IN (
						SELECT o.id_customer
						FROM `' . _DB_PREFIX_ . 'orders` o
						LEFT JOIN `' . _DB_PREFIX_ . 'order_detail` od ON o.id_order = od.id_order
						WHERE o.date_add BETWEEN ' . $this->getDate() . '
						AND o.valid = 1
						AND od.product_id = ' . (int)$id_product . '
					)
					' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
					AND o.date_add BETWEEN ' . $this->getDate() . '
					AND o.valid = 1
					AND od.product_id != ' . (int)$id_product . '
				GROUP BY od.product_id
				ORDER BY pqty DESC';

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Hook
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $link = $this->context->link;
        $id_category = (int)Tools::getValue('id_category');
        $currency = Context::getContext()->currency;

        if (Tools::getValue('export'))
            if (!Tools::getValue('exportType'))
                $this->csvExport(array(
                    'layers' => 2,
                    'type' => 'line',
                    'option' => '42'
                ));

        $this->html = '
			<div class="panel-heading">
				' . $this->displayName . '
			</div>
			<h4>' . Translate::getModuleTranslation('statsmodule', 'Guide', 'statsmodule') . '</h4>
			<div class="alert alert-warning">
				<h4>' . Translate::getModuleTranslation('statsmodule', 'Number of purchases compared to number of views', 'statsmodule') . '</h4>
					' . Translate::getModuleTranslation('statsmodule', 'After choosing a category and selecting a product, informational graphs will appear.', 'statsmodule') . '
					<ul>
						<li class="bullet">' . Translate::getModuleTranslation('statsmodule', 'If you notice that a product is often purchased but viewed infrequently, you should display it more prominently in your Front Office.', 'statsmodule') . '</li>
						<li class="bullet">' . Translate::getModuleTranslation('statsmodule', 'On the other hand, if a product has many views but is not often purchased, we advise you to check or modify this product\'s information, description and photography again, see if you can find something better.', 'statsmodule') . '
						</li>
					</ul>
			</div>';
        if ($id_product = (int)Tools::getValue('id_product')) {
            if (Tools::getValue('export')) {
                if (Tools::getValue('exportType') == 1)
                    $this->csvExport(array(
                        'layers' => 2,
                        'type' => 'line',
                        'option' => '1-' . $id_product
                    ));
                elseif (Tools::getValue('exportType') == 2)
                    $this->csvExport(array(
                        'type' => 'pie',
                        'option' => '3-' . $id_product
                    ));
            }
            $product = new Product($id_product, false, $this->context->language->id);
            $totalBought = $this->getTotalBought($product->id);
            $isPartOfPack = $this->packTracking && Pack::isPacked($product->id);
            $totalBoughtPack = $isPartOfPack ? $this->getTotalBoughtAsPartOfPack($product->id) : 0;
            $total_sales = $this->getTotalSales($product->id);
            $total_viewed = $this->getTotalViewed($product->id);
            $hasSales = $totalBought > 0 || $totalBoughtPack > 0;
            $this->html .= '<h4>' . $product->name . ' - ' . Translate::getModuleTranslation('statsmodule', 'Details', 'statsmodule') . '</h4>
			<div class="row row-margin-bottom">
				<div class="col-lg-12">
					<div class="col-lg-8">
						' . $this->engine($this->type, array(
                    'layers' => 2,
                    'type' => 'line',
                    'option' => '1-' . $id_product
                )) . '
					</div>
					<div class="col-lg-4">
						<ul class="list-unstyled">
							<li>' . Translate::getModuleTranslation('statsmodule', 'Total bought', 'statsmodule') . ' ' . $totalBought . '</li>
							' . ($isPartOfPack ? ('<li>' . Translate::getModuleTranslation('statsmodule', 'Total bought in pack', 'statsmodule') . ' ' . $totalBoughtPack . '</li>') : '') . '
							<li>' . Translate::getModuleTranslation('statsmodule', 'Sales (tax excluded)', 'statsmodule') . ' ' . Tools::displayprice($total_sales, $currency) . '</li>
							<li>' . Translate::getModuleTranslation('statsmodule', 'Total viewed', 'statsmodule') . ' ' . $total_viewed . '</li>
							<li>' . Translate::getModuleTranslation('statsmodule', 'Conversion rate', 'statsmodule') . ' ' . number_format($total_viewed ? $totalBought / $total_viewed : 0, 2) . '</li>
						</ul>
						<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '&export=1&exportType=1">
							<i class="icon-cloud-upload"></i> ' . Translate::getModuleTranslation('statsmodule', 'CSV Export', 'statsmodule') . '
						</a>
					</div>
				</div>
			</div>';
            if ($hasSales) {
                $hasAttribute = $product->hasAttributes();
                if ($hasAttribute) {
                    $this->html .= '
                        <h3 class="space">' . Translate::getModuleTranslation('statsmodule', 'Attribute sales distribution', 'statsmodule') . '</h3>
                        <center>' . $this->engine($this->type, array('type' => 'pie', 'option' => '3-' . $id_product)) . '</center><br />
                        <a href="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '&export=1&exportType=2"><img src="../img/admin/asterisk.gif" alt=""/>' . Translate::getModuleTranslation('statsmodule', 'CSV Export', 'statsmodule') . '</a>';
                }

                $sales = $this->getSales($id_product);
                $this->html .= '
				<h4>' . Translate::getModuleTranslation('statsmodule', 'Sales', 'statsmodule') . '</h4>
				<div style="overflow-y:scroll;height:' . min(400, (count($sales) + 1) * 32) . 'px">
					<table class="table">
						<thead>
							<tr>
								<th>
									<span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Date', 'statsmodule') . '</span>
								</th>
								<th>
									<span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Order', 'statsmodule') . '</span>
								</th>
								<th>
									<span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Customer', 'statsmodule') . '</span>
								</th>
								<th>
								    <span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Product name', 'statsmodule') . '</span>
								</th>
								<th>
									<span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Quantity', 'statsmodule') . '</span>
								</th>
								' . ($isPartOfPack ? '<th><span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Items in pack', 'statsmodule') . '</span></th>' : '') . '
								' . ($isPartOfPack ? '<th><span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Items total', 'statsmodule') . '</span></th>' : '') . '
								<th>
									<span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Price', 'statsmodule') . '</span>
								</th>
							</tr>
						</thead>
						<tbody>';
                $token_order = Tools::getAdminToken('AdminOrders' . (int)Tab::getIdFromClassName('AdminOrders') . (int)$this->context->employee->id);
                $token_customer = Tools::getAdminToken('AdminCustomers' . (int)Tab::getIdFromClassName('AdminCustomers') . (int)$this->context->employee->id);
                $productUrl = $link->getAdminLink('AdminProducts', true);
                foreach ($sales as $sale) {
                    $this->html .= '
						<tr>
							<td>' . Tools::displayDate($sale['date_add'], null, false) . '</td>
							<td class="text-center"><a href="?tab=AdminOrders&id_order=' . $sale['id_order'] . '&vieworder&token=' . $token_order . '">' . (int)$sale['id_order'] . '</a></td>
							<td class="text-center"><a href="?tab=AdminCustomers&id_customer=' . $sale['id_customer'] . '&viewcustomer&token=' . $token_customer . '">' . (int)$sale['id_customer'] . '</a></td>
							<td><a href="' . $productUrl . '&updateproduct&id_product=' . $sale['id_product'] . '">' . $sale['product_name'] . '</a></td>
							<td>' . (int)$sale['product_quantity'] . '</td>
							' . ($isPartOfPack ? '<td>' . ($sale['pack_quantity'] ? $sale['pack_quantity'] : '--') . '</td>' : '') . '
							' . ($isPartOfPack ? '<td>' . ($sale['product_quantity'] * ($sale['pack_quantity'] ? $sale['pack_quantity'] : 1)) . '</td>' : '') . '
							<td>' . Tools::displayprice($sale['total'], $currency) . '</td>
						</tr>';
                }
                $this->html .= '
						</tbody>
					</table>
				</div>';

                $cross_selling = $this->getCrossSales($id_product, $this->context->language->id);
                if (count($cross_selling)) {
                    $this->html .= '
					<h4>' . Translate::getModuleTranslation('statsmodule', 'Cross selling', 'statsmodule') . '</h4>
					<div style="overflow-y:scroll;height:200px">
						<table class="table">
							<thead>
								<tr>
									<th>
										<span class="title_box active">' . Translate::getModuleTranslation('statsmodule', 'Product name', 'statsmodule') . '</span>
									</th>
									<th>
										<span class="title_box active">' . Translate::getModuleTranslation('statsmodule', 'Quantity sold', 'statsmodule') . '</span>
									</th>
									<th>
										<span class="title_box active">' . Translate::getModuleTranslation('statsmodule', 'Average price', 'statsmodule') . '</span>
									</th>
								</tr>
							</thead>
						<tbody>';
                    $token_products = Tools::getAdminToken('AdminProducts' . (int)Tab::getIdFromClassName('AdminProducts') . (int)$this->context->employee->id);
                    foreach ($cross_selling as $selling)
                        $this->html .= '
							<tr>
								<td><a href="?tab=AdminProducts&id_product=' . (int)$selling['id_product'] . '&addproduct&token=' . $token_products . '">' . $selling['pname'] . '</a></td>
								<td class="text-center">' . (int)$selling['pqty'] . '</td>
								<td class="text-right">' . Tools::displayprice($selling['pprice'], $currency) . '</td>
							</tr>';
                    $this->html .= '
							</tbody>
						</table>
					</div>';
                }
            }
        } else {
            $categories = Category::getCategories((int)$this->context->language->id, true, false);
            $this->html .= '
			<form action="#" method="post" id="categoriesForm" class="form-horizontal">
				<div class="row row-margin-bottom">
					<label class="control-label col-lg-3">
						<span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="' . Translate::getModuleTranslation('statsmodule', 'Click on a product to access its statistics!', 'statsmodule') . '">
							' . Translate::getModuleTranslation('statsmodule', 'Choose a category', 'statsmodule') . '
						</span>
					</label>
					<div class="col-lg-3">
						<select name="id_category" onchange="$(\'#categoriesForm\').submit();">
							<option value="0">' . Translate::getModuleTranslation('statsmodule', 'All', 'statsmodule') . '</option>';
            foreach ($categories as $category)
                $this->html .= '<option value="' . $category['id_category'] . '"' . ($id_category == $category['id_category'] ? ' selected="selected"' : '') . '>' . $category['name'] . '</option>';
            $this->html .= '
						</select>
					</div>
				</div>
			</form>
			<h4>' . Translate::getModuleTranslation('statsmodule', 'Products available', 'statsmodule') . '</h4>
			<table class="table" style="border: 0; cellspacing: 0;">
				<thead>
					<tr>
						<th>
							<span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Reference', 'statsmodule') . '</span>
						</th>
						<th>
							<span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Name', 'statsmodule') . '</span>
						</th>
						<th>
							<span class="title_box  active">' . Translate::getModuleTranslation('statsmodule', 'Available quantity for sale', 'statsmodule') . '</span>
						</th>
					</tr>
				</thead>
				<tbody>';

            foreach ($this->getProducts($this->context->language->id) as $product)
                $this->html .= '
				<tr>
					<td>' . $product['reference'] . '</td>
					<td>
						<a href="' . Tools::safeOutput(AdminController::$currentIndex . '&token=' . Tools::getValue('token') . '&module=statsproduct&id_product=' . $product['id_product']) . '">' . $product['name'] . '</a>
					</td>
					<td>' . $product['quantity'] . '</td>
				</tr>';

            $this->html .= '
				</tbody>
			</table>
			<a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
				<i class="icon-cloud-upload"></i> ' . Translate::getModuleTranslation('statsmodule', 'CSV Export', 'statsmodule') . '
			</a>';
        }

        return $this->html;
    }

    /**
     * @param $option
     * @param $layers
     *
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function setOption($option, $layers = 1)
    {
        $options = explode('-', $option);
        if (count($options) === 2)
            list($this->option, $this->id_product) = $options;
        else
            $this->option = $option;
        $date_between = $this->getDate();
        switch ($this->option) {
            case 1:
                $this->_titles['main'][0] = Translate::getModuleTranslation('statsmodule', 'Popularity', 'statsmodule');
                $this->_titles['main'][1] = Translate::getModuleTranslation('statsmodule', 'Sales', 'statsmodule');
                $this->_titles['main'][2] = Translate::getModuleTranslation('statsmodule', 'Visits (x100)', 'statsmodule');
                $this->query = [];
                $this->query[0] = 'SELECT o.`date_add`, SUM(od.`product_quantity`) AS total
						FROM `' . _DB_PREFIX_ . 'order_detail` od
						LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = od.`id_order`
						WHERE od.`product_id` = ' . (int)$this->id_product . '
							' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
							AND o.valid = 1
							AND o.`date_add` BETWEEN ' . $date_between . '
						GROUP BY o.`date_add`';

                $this->query[1] = 'SELECT dr.`time_start` AS date_add, (SUM(pv.`counter`) / 100) AS total
						FROM `' . _DB_PREFIX_ . 'page_viewed` pv
						LEFT JOIN `' . _DB_PREFIX_ . 'date_range` dr ON pv.`id_date_range` = dr.`id_date_range`
						LEFT JOIN `' . _DB_PREFIX_ . 'page` p ON pv.`id_page` = p.`id_page`
						LEFT JOIN `' . _DB_PREFIX_ . 'page_type` pt ON pt.`id_page_type` = p.`id_page_type`
						WHERE pt.`name` = \'product\'
							' . Shop::addSqlRestriction(false, 'pv') . '
							AND p.`id_object` = ' . (int)$this->id_product . '
							AND dr.`time_start` BETWEEN ' . $date_between . '
							AND dr.`time_end` BETWEEN ' . $date_between . '
						GROUP BY dr.`time_start`';
                break;

            case 3:
                $this->query = 'SELECT product_attribute_id, SUM(od.`product_quantity`) AS total
						FROM `' . _DB_PREFIX_ . 'orders` o
						LEFT JOIN `' . _DB_PREFIX_ . 'order_detail` od ON o.`id_order` = od.`id_order`
						WHERE od.`product_id` = ' . (int)$this->id_product . '
							' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
							AND o.valid = 1
							AND o.`date_add` BETWEEN ' . $date_between . '
						GROUP BY od.`product_attribute_id`';
                $this->_titles['main'] = Translate::getModuleTranslation('statsmodule', 'Attributes', 'statsmodule');
                break;

            case 42:
                $this->_titles['main'][1] = Translate::getModuleTranslation('statsmodule', 'Reference', 'statsmodule');
                $this->_titles['main'][2] = Translate::getModuleTranslation('statsmodule', 'Name', 'statsmodule');
                $this->_titles['main'][3] = Translate::getModuleTranslation('statsmodule', 'Stock', 'statsmodule');
                break;
        }
    }

    /**
     * @param $layers
     *
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getData($layers)
    {
        if ($this->option == 42) {
            $products = $this->getProducts($this->context->language->id);
            foreach ($products as $product) {
                $this->_values[0][] = $product['reference'];
                $this->_values[1][] = $product['name'];
                $this->_values[2][] = $product['quantity'];
                $this->_legend[] = $product['id_product'];
            }
        } else if ($this->option != 3)
            $this->setDateGraph($layers, true);
        else {
            $product = new Product($this->id_product, false, (int)$this->getLang());

            $comb_array = array();
            $assoc_names = array();
            $combinations = $product->getAttributeCombinations((int)$this->getLang());
            foreach ($combinations as $combination)
                $comb_array[$combination['id_product_attribute']][] = array(
                    'group' => $combination['group_name'],
                    'attr' => $combination['attribute_name']
                );
            foreach ($comb_array as $id_product_attribute => $product_attribute) {
                $list = '';
                foreach ($product_attribute as $attribute)
                    $list .= trim($attribute['group']) . ' - ' . trim($attribute['attr']) . ', ';
                $list = rtrim($list, ', ');
                $assoc_names[$id_product_attribute] = $list;
            }

            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query);
            foreach ($result as $row) {
                $this->_values[] = $row['total'];
                $this->_legend[] = @$assoc_names[$row['product_attribute_id']];
            }
        }
    }

    /**
     * @param $layers
     *
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function setAllTimeValues($layers)
    {
        for ($i = 0; $i < $layers; $i++) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query[$i]);
            foreach ($result as $row)
                $this->_values[$i][(int)substr($row['date_add'], 0, 4)] += $row['total'];
        }
    }

    /**
     * @param $layers
     *
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function setYearValues($layers)
    {
        for ($i = 0; $i < $layers; $i++) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query[$i]);
            foreach ($result as $row)
                $this->_values[$i][(int)substr($row['date_add'], 5, 2)] += $row['total'];
        }
    }

    /**
     * @param $layers
     *
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function setMonthValues($layers)
    {
        for ($i = 0; $i < $layers; $i++) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query[$i]);
            foreach ($result as $row)
                $this->_values[$i][(int)substr($row['date_add'], 8, 2)] += $row['total'];
        }
    }

    /**
     * @param $layers
     *
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function setDayValues($layers)
    {
        for ($i = 0; $i < $layers; $i++) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query[$i]);
            foreach ($result as $row)
                $this->_values[$i][(int)substr($row['date_add'], 11, 2)] += $row['total'];
        }
    }
}
