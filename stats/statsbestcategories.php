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
        $onlyChildren = (int)Tools::getValue('onlyChildren');

        $engine_params = [
            'id' => 'id_category',
            'title' => $this->displayName,
            'columns' => [
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
                [
                    'id' => 'totalPageViewed',
                    'header' => $this->l('Total Viewed'),
                    'dataIndex' => 'totalPageViewed',
                    'align' => 'center',
                ],
            ],
            'defaultSortColumn' => $this->default_sort_column,
            'defaultSortDirection' => $this->default_sort_direction,
            'emptyMessage' => $this->empty_message,
            'pagingMessage' => $this->paging_message,
            'customParams' => [
                'onlyChildren' => $onlyChildren,
            ],
        ];

        if (Tools::getValue('export')) {
            $this->csvExport($engine_params);
        }

        return '
			<div class="panel-heading">
				<i class="icon-sitemap"></i> ' . $this->displayName . '
			</div>
			' . $this->engine($engine_params) . '
            <div class="row form-horizontal">
                <div class="col-md-3">
                    <a class="btn btn-default export-csv" href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1') . '">
                        <i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
                    </a>
                </div>
                <div class="col-md-9">
                    <div class="checkbox">
                        <label for="onlyChildren">
                            <input type="checkbox" name="onlyChildren" id="onlyChildren" value="1" ' . ($onlyChildren == 1 ? 'checked="checked"' : '') . '>
                            ' . $this->l('Display final level categories only (that have no child categories)') . '
                        </label>
                    </div>

                </div>
            </div>
            <script type="text/javascript">
                $(function(){
                    $("#onlyChildren").change(function(){
                        $("#calendar_form").append($(this).clone().css("display", "none")).submit();
                    });
                });
            </script>';
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

        $onlyChildren = '';
        if ((int)Tools::getValue('onlyChildren') == 1) {
            $onlyChildren = 'NOT EXISTS (SELECT NULL FROM ' . _DB_PREFIX_ . 'category WHERE id_parent = ca.id_category)';
        }

        // Get best categories
        $query = '
				SELECT ca.`id_category`, CONCAT(parent.name, \' > \', calang.`name`) AS name,
				IFNULL(SUM(t.`totalQuantitySold`), 0) AS totalQuantitySold,
				ROUND(IFNULL(SUM(t.`totalPriceSold`), 0), 2) AS totalPriceSold,
				ROUND(IFNULL(SUM(t.`totalWholeSalePriceSold`), 0), 2) AS totalWholeSalePriceSold,
				(
					SELECT IFNULL(SUM(pv.`counter`), 0)
					FROM `' . _DB_PREFIX_ . 'page` p
					LEFT JOIN `' . _DB_PREFIX_ . 'page_viewed` pv ON p.`id_page` = pv.`id_page`
					LEFT JOIN `' . _DB_PREFIX_ . 'date_range` dr ON pv.`id_date_range` = dr.`id_date_range`
					LEFT JOIN `' . _DB_PREFIX_ . 'product` pr ON CAST(p.`id_object` AS UNSIGNED INTEGER) = pr.`id_product`
					LEFT JOIN `' . _DB_PREFIX_ . 'category_product` capr2 ON capr2.`id_product` = pr.`id_product`
					WHERE capr.`id_category` = capr2.`id_category`
					AND p.`id_page_type` = 1
					AND dr.`time_start` BETWEEN ' . $date_between . '
					AND dr.`time_end` BETWEEN ' . $date_between . '
				) AS totalPageViewed,
				(
                    SELECT COUNT(id_category) FROM ' . _DB_PREFIX_ . 'category WHERE `id_parent` = ca.`id_category`
			    ) AS hasChildren
			FROM `' . _DB_PREFIX_ . 'category` ca
			LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` calang ON (ca.`id_category` = calang.`id_category` AND calang.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('calang') . ')
			LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` parent ON (ca.`id_parent` = parent.`id_category` AND parent.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('parent') . ')
			LEFT JOIN `' . _DB_PREFIX_ . 'category_product` capr ON ca.`id_category` = capr.`id_category`
			LEFT JOIN (
				SELECT pr.`id_product`, t.`totalQuantitySold`, t.`totalPriceSold`, t.`totalWholeSalePriceSold`
				FROM `' . _DB_PREFIX_ . 'product` pr
				LEFT JOIN (
					SELECT pr.`id_product`, pa.`wholesale_price`,
						IFNULL(SUM(cp.`product_quantity`), 0) AS totalQuantitySold,
						IFNULL(SUM(cp.`product_price` * cp.`product_quantity`), 0) / o.conversion_rate AS totalPriceSold,
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
					LEFT OUTER JOIN `' . _DB_PREFIX_ . 'order_detail` cp ON pr.`id_product` = cp.`product_id`
					LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = cp.`id_order`
					LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product_attribute` = cp.`product_attribute_id`
					' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
					WHERE o.valid = 1
					AND o.invoice_date BETWEEN ' . $date_between . '
					GROUP BY pr.`id_product`
				) t ON t.`id_product` = pr.`id_product`
			) t	ON t.`id_product` = capr.`id_product`
			' . (($categories) ? 'WHERE ca.id_category IN (' . implode(', ', $categories) . ')' : '') . '
			' . ($onlyChildren ? 'AND '.$onlyChildren : '') . '
			GROUP BY ca.`id_category`
			HAVING ca.`id_category` != 1';

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
                $parts = explode('>', $value['name']);
                $value['name'] = '<i class="icon-folder-open"></i> ' . trim($parts[0]) . ' > ';
                if ((int)$value['hasChildren'] == 0) {
                    $value['name'] .= '&bull; ';
                } else {
                    $value['name'] .= '<i class="icon-folder-open"></i> ';
                }
                $value['name'] .= trim($parts[1]);

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
            if ($onlyChildren) {
                $totalQuery->where($onlyChildren);
            }
            $this->_totalCount = (int)$conn->getValue($totalQuery);
        } else {
            $this->_totalCount = count($values);

        }
    }
}
