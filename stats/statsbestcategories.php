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


class StatsBestCategories extends StatsModule
{
    const PARAM_ONLY_LEAF_CATEGORIES = 'only_leaf_categories';
    const PARAM_ATTRIBUTION_MODEL = 'attribution_model';

    const ATTRIBUTION_MODEL_DEFAULT_CATEGORY = 'default_category';
    const ATTRIBUTION_MODEL_ASSOCIATED_CATEGORIES = 'associated_categories';

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

        $this->default_sort_column = 'totalPriceSold';
        $this->default_sort_direction = 'DESC';
        $this->empty_message = $this->l('Empty recordset returned');
        $this->paging_message = sprintf($this->l('Displaying %1$s of %2$s'), '{0} - {1}', '{2}');
        $this->displayName = $this->l('Best categories');
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $onlyLeafCategories = $this->showLeafOnlyCategories();
        $attributionModel = $this->getAttributionModel();

        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        if ($method === 'POST') {
            $url = Context::getContext()->link->getAdminLink('AdminStats', true, [
                'module' => 'statsbestcategories',
                static::PARAM_ONLY_LEAF_CATEGORIES => $onlyLeafCategories,
                static::PARAM_ATTRIBUTION_MODEL => $attributionModel,
            ]);
            Tools::redirectAdmin($url);
        }

        $columns = [
            [
                'id' => 'name',
                'header' => $this->l('Name'),
                'dataIndex' => 'name',
                'align' => 'left',
            ],
            [
                'id' => 'totalQuantitySold',
                'header' => $this->l('Total Quantity Sold'),
                'dataIndex' => 'totalQuantitySold',
                'align' => 'center',
            ],
            [
                'id' => 'totalPriceSold',
                'header' => $this->l('Total Price'),
                'dataIndex' => 'totalPriceSold',
                'align' => 'right',
            ],
            [
                'id' => 'totalWholeSalePriceSold',
                'header' => $this->l('Total Margin'),
                'dataIndex' => 'totalWholeSalePriceSold',
                'align' => 'center',
            ],
        ];

        if ($this->utils->trackingPageViews()) {
            $columns[] = [
                'id' => 'totalPageViewed',
                'header' => $this->l('Total Viewed'),
                'dataIndex' => 'totalPageViewed',
                'align' => 'center',
            ];
        }

        $engine_params = [
            'id' => 'id_category',
            'title' => $this->displayName,
            'columns' => $columns,
            'defaultSortColumn' => $this->default_sort_column,
            'defaultSortDirection' => $this->default_sort_direction,
            'emptyMessage' => $this->empty_message,
            'pagingMessage' => $this->paging_message,
            'customParams' => [
                static::PARAM_ONLY_LEAF_CATEGORIES => $onlyLeafCategories,
                static::PARAM_ATTRIBUTION_MODEL => $attributionModel,
            ],
        ];

        if (Tools::getValue('export')) {
            $this->csvExport($engine_params);
        }

        return '
			<div class="panel-heading">
				<i class="icon-sitemap"></i> ' . $this->displayName . '
			</div>
            <form action="' . Tools::safeOutput(AdminController::$currentIndex . '&token=' . Tools::getValue('token') . '&module=statsbestcategories') . '" method="post" class="form-horizontal alert">
                <div class="row">
                    <div class="col-lg-12">
                        <label class="control-label pull-left">' . Tools::safeOutput($this->l('Attribution model')) . '</label>
                        <div class="col-lg-3">
                            <select id="attributionModel" name="'.static::PARAM_ATTRIBUTION_MODEL.'" onchange="this.form.submit();">
                                <option value="'.static::ATTRIBUTION_MODEL_DEFAULT_CATEGORY.'" '. ($attributionModel === static::ATTRIBUTION_MODEL_DEFAULT_CATEGORY ? 'selected="selected"' : '') . '>' . Tools::safeOutput($this->l('Default category')) . '</option>
                                <option value="'.static::ATTRIBUTION_MODEL_ASSOCIATED_CATEGORIES.'" ' . ($attributionModel === static::ATTRIBUTION_MODEL_ASSOCIATED_CATEGORIES ? 'selected="selected"' : '') . '>' . Tools::safeOutput($this->l('Associated categories')) . '</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="checkbox">
                            <label for="'.static::PARAM_ONLY_LEAF_CATEGORIES.'">
                                <input type="checkbox" name="'.static::PARAM_ONLY_LEAF_CATEGORIES.'" value="1" ' . ($onlyLeafCategories == 1 ? 'checked="checked"' : '') . ' onchange="this.form.submit();">
                                ' . $this->l('Display final level categories only (that have no child categories)') . '
                            </label>
                        </div>
                    </div>
                </div>
            </form>
			' . $this->engine($engine_params) . '
            <div class="row form-horizontal">
                <div class="col-md-3">
                    <a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
                        <i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
                    </a>
                </div>
            </div>';
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function getData($layers = null)
    {
        $export = !!Tools::getValue('export');
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $date_between = $this->getDate();
        $id_lang = $this->getLang();

        // If a shop is selected, get all children categories for the shop
        $categories = [];
        if (Shop::getContext() != Shop::CONTEXT_ALL) {
            $sql = 'SELECT c.nleft, c.nright
					FROM ' . _DB_PREFIX_ . 'category c
					WHERE c.id_category IN (
						SELECT s.id_category
						FROM ' . _DB_PREFIX_ . 'shop s
						WHERE s.id_shop IN (' . implode(', ', Shop::getContextListShopID()) . ')
					)';
            if ($result = Db::getInstance()->executeS($sql)) {
                $ntree_restriction = [];
                foreach ($result as $row) {
                    $ntree_restriction[] = '(nleft >= ' . $row['nleft'] . ' AND nright <= ' . $row['nright'] . ')';
                }

                if ($ntree_restriction) {
                    $sql = 'SELECT id_category
							FROM ' . _DB_PREFIX_ . 'category
							WHERE ' . implode(' OR ', $ntree_restriction);
                    if ($result = Db::getInstance()->executeS($sql)) {
                        foreach ($result as $row) {
                            $categories[] = $row['id_category'];
                        }
                    }
                }
            }
        }

        $productJoinCond = '';
        if ($this->getAttributionModel() === static::ATTRIBUTION_MODEL_DEFAULT_CATEGORY) {
            $productJoinCond = 'AND t.id_category_default = capr.id_category';
        }

        // Get best categories
        $query = '
				SELECT ca.`id_category`,
				(
					SELECT GROUP_CONCAT(cl2.name ORDER BY c2.nleft SEPARATOR " > ")
					FROM '._DB_PREFIX_.'category c2
					INNER JOIN '._DB_PREFIX_.'category_lang cl2 ON (cl2.id_category = c2.id_category AND cl2.id_lang = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('cl2') .')
					WHERE c2.nleft <= ca.nleft
					AND c2.nright >= ca.nright
					AND c2.id_parent
				) AS name,
				IFNULL(SUM(t.`totalQuantitySold`), 0) AS totalQuantitySold,
				ROUND(IFNULL(SUM(t.`totalPriceSold`), 0), 2) AS totalPriceSold,
				ROUND(IFNULL(SUM(t.`totalWholeSalePriceSold`), 0), 2) AS totalWholeSalePriceSold,
			    '.$this->getPageViewedSubselect($date_between).' AS totalPageViewed,
				(
                    SELECT COUNT(id_category) FROM ' . _DB_PREFIX_ . 'category WHERE `id_parent` = ca.`id_category`
			    ) AS hasChildren
			FROM `' . _DB_PREFIX_ . 'category` ca
			LEFT JOIN `' . _DB_PREFIX_ . 'category_product` capr ON ca.`id_category` = capr.`id_category`
			LEFT JOIN (
				SELECT pr.`id_product`, pr.`id_category_default`, t.`totalQuantitySold`, t.`totalPriceSold`, t.`totalWholeSalePriceSold`
				FROM `' . _DB_PREFIX_ . 'product` pr
				LEFT JOIN (
					SELECT pr.`id_product`,
						IFNULL(SUM(cp.`product_quantity`), 0) AS totalQuantitySold,
						IFNULL(SUM(cp.`product_price` * cp.`product_quantity` / o.conversion_rate), 0) AS totalPriceSold,
						IFNULL(SUM(
							CASE
								WHEN cp.`original_wholesale_price` <> "0.000000"
								THEN cp.`original_wholesale_price` / o.conversion_rate * cp.`product_quantity`
								WHEN pa.`wholesale_price` <> "0.000000"
								THEN pa.`wholesale_price` * cp.`product_quantity`
								WHEN pr.`wholesale_price` <> "0.000000"
								THEN pr.`wholesale_price` * cp.`product_quantity`
							END
						), 0) AS totalWholeSalePriceSold
					FROM `' . _DB_PREFIX_ . 'product` pr
					INNER JOIN `' . _DB_PREFIX_ . 'order_detail` cp ON pr.`id_product` = cp.`product_id`
					INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = cp.`id_order`
					INNER JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product_attribute` = cp.`product_attribute_id`
					' . Shop::addSqlRestriction(false, 'o') . '
					WHERE o.valid = 1
					AND o.invoice_date BETWEEN ' . $date_between . '
					GROUP BY pr.`id_product`
				) t ON t.`id_product` = pr.`id_product`
			) t	ON (t.`id_product` = capr.`id_product` '.$productJoinCond.')
			' . (($categories) ? 'WHERE ca.id_category IN (' . implode(', ', $categories) . ')' : '') . '
			GROUP BY ca.`id_category`
			HAVING ca.`id_category` != 1';

        if ($this->showLeafOnlyCategories()) {
            $query .= ' AND hasChildren = 0';
        }

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
            $totalPriceSold = round((float)$value['totalPriceSold'], 2);
            $totalWholeSalePriceSold = round((float)$value['totalWholeSalePriceSold'], 2);

            $value['totalWholeSalePriceSold'] = Tools::displayPrice($totalPriceSold - $totalWholeSalePriceSold, $currency);
            $value['totalPriceSold'] = Tools::displayPrice($totalPriceSold, $currency);

            if (! $export) {
                $drilldown = Context::getContext()->link->getAdminLink('AdminStats', true, [
                    'module' => 'statsproductsprofit',
                    'id_category' => (int)$value['id_category']
                ]);
                $value['totalWholeSalePriceSold'] = '<a href="'.Tools::safeOutput($drilldown).'">'.$value['totalWholeSalePriceSold'].'</a>';
            }

        }

        $this->_values = $values;

        if (Validate::IsUnsignedInt($this->_limit)) {
            $totalQuery = (new DbQuery())
                ->select('COUNT(1)')
                ->from('category', 'ca');
            if ($categories) {
                $totalQuery->where('ca.id_category IN (' . implode(', ', $categories) . ')');
            }
            if ($this->showLeafOnlyCategories()) {
                $totalQuery->where('NOT EXISTS (SELECT NULL FROM ' . _DB_PREFIX_ . 'category WHERE id_parent = ca.id_category)');
            }
            $this->_totalCount = (int)$conn->getValue($totalQuery);
        } else {
            $this->_totalCount = count($values);

        }
    }

    /**
     * @return string
     */
    protected function getAttributionModel()
    {
        switch (Tools::getValue(static::PARAM_ATTRIBUTION_MODEL)) {
            case static::ATTRIBUTION_MODEL_ASSOCIATED_CATEGORIES:
                return static::ATTRIBUTION_MODEL_ASSOCIATED_CATEGORIES;
            default:
                return static::ATTRIBUTION_MODEL_DEFAULT_CATEGORY;
        }
    }

    /**
     * @return int
     */
    protected function showLeafOnlyCategories()
    {
        return (int)Tools::getValue(static::PARAM_ONLY_LEAF_CATEGORIES, 0);
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
                ->from('page', 'p')
                ->innerJoin('page_viewed', 'pv', 'p.`id_page` = pv.`id_page`')
                ->innerJoin('date_range', 'dr', 'pv.`id_date_range` = dr.`id_date_range`')
                ->innerJoin('product', 'pr', ' CAST(p.`id_object` AS UNSIGNED INTEGER) = pr.`id_product`')
                ->innerJoin('category_product', 'capr2', 'capr2.`id_product` = pr.`id_product`')
                ->where('capr2.id_category = capr.id_category')
                ->where('p.id_page_type = '. (int)Page::getPageTypeByName('product'))
                ->where('dr.`time_start` BETWEEN ' . $dateBetween)
                ->where('dr.`time_end` BETWEEN ' . $dateBetween);
            return "($sql)";
        }
        return '0';
    }
}
