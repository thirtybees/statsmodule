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

class StatsShopAssociation extends StatsModule
{
    /**
     * @var string
     */
    protected $html = '';

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_CUSTOM;

        $this->displayName = $this->l('Product shop association');
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $shops = $this->getShops();
        $products = $this->getProducts();
        $productShopData = $this->getProductShopData($shops);

        $this->generateTable($shops, $products, $productShopData);

        return $this->html;
    }

    /**
     * Get all shops based on current context
     *
     * @return array
     */
    private function getShops()
    {
        $contextShop = Shop::getContextShopID();
        $query = 'SELECT id_shop, name FROM ' . _DB_PREFIX_ . 'shop WHERE active = 1';

        if ($contextShop) {
            $query .= ' AND id_shop = ' . (int)$contextShop;
        }

        $query .= ' ORDER BY id_shop ASC';
        return Db::getInstance()->executeS($query);
    }

    /**
     * Get all products with their "Enabled/Disabled" status
     *
     * @return array
     */
    private function getProducts()
    {
        return Db::getInstance()->executeS('
            SELECT p.id_product, pl.name, p.active
            FROM ' . _DB_PREFIX_ . 'product p
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
              ON p.id_product = pl.id_product
              AND pl.id_lang = ' . (int)$this->context->language->id . '
            GROUP BY p.id_product
            ORDER BY p.id_product ASC
        ');
    }

    /**
     * Preload product-shop associations
     *
     * @param array $shops
     * @return array
     */
    private function getProductShopData($shops)
    {
        $shopIds = array_column($shops, 'id_shop');
        $data = Db::getInstance()->executeS('
            SELECT ps.id_product, ps.id_shop
            FROM ' . _DB_PREFIX_ . 'product_shop ps
            WHERE ps.id_shop IN (' . implode(',', array_map('intval', $shopIds)) . ')
        ');

        $productShopData = [];
        foreach ($data as $row) {
            $productShopData[$row['id_product']][$row['id_shop']] = true;
        }

        return $productShopData;
    }

    /**
     * Generate HTML table
     *
     * @param array $shops
     * @param array $products
     * @param array $productShopData
     */
    private function generateTable($shops, $products, $productShopData)
	{
		$statusImages = [
			0 => '<img src="../modules/statsmodule/views/img/red.png" title="' . Tools::safeOutput($this->l('Not associated')) . '" />',
			1 => '<img src="../modules/statsmodule/views/img/green.png" title="' . Tools::safeOutput($this->l('Associated')) . '" />',
		];

		$enabledDisabledImages = [
			0 => '<img src="../modules/statsmodule/views/img/red.png" title="' . Tools::safeOutput($this->l('Disabled')) . '" />',
			1 => '<img src="../modules/statsmodule/views/img/green.png" title="' . Tools::safeOutput($this->l('Enabled')) . '" />',
		];

		$link = Context::getContext()->link;

		$this->html .= '<div class="panel-heading">' . $this->l('Product Shop Association') . '</div>';
		
		$this->html .= '<style>
			.separator-left-right {
				border-right: 1px solid #EAEDEF;
				border-left: 1px solid #EAEDEF;
				text-align: center;
			}
			th {
				font-weight: bold !important;
			}
			.center {
				text-align: center;
			}
		</style>';

		$this->html .= '<div style="overflow-x:auto;"><table class="table">
			<thead>
				<tr>
					<th>' . $this->l('Product ID') . '</th>
					<th>' . $this->l('Product Name') . '</th>
					<th class="separator-left-right center">' . $this->l('Enabled/Disabled') . '</th>';

		foreach ($shops as $shop) {
			$this->html .= '<th class="center">' . Tools::safeOutput($shop['name']) . '</th>';
		}

		$this->html .= '</tr>
			</thead>
			<tbody>';

		foreach ($products as $product) {
			$productId = (int)$product['id_product'];
			$isGloballyEnabled = (int)$product['active'];

			$productEditUrl = $link->getAdminLink('AdminProducts', true, [
				'id_product' => $productId,
				'updateproduct' => 1,
			]);

			$this->html .= '<tr>
				<td>' . $productId . '</td>
				<td><a href="' . Tools::safeOutput($productEditUrl) . '" target="_blank" title="' . $this->l('Opens in new tab') . '">' . Tools::safeOutput($product['name']) . '</a></td>
				<td class="separator-left-right center">' . $enabledDisabledImages[$isGloballyEnabled] . '</td>';

			foreach ($shops as $shop) {
				$isAssociated = isset($productShopData[$productId][$shop['id_shop']]) && $productShopData[$productId][$shop['id_shop']];

				$this->html .= '<td class="center">' . $statusImages[(int)$isAssociated] . '</td>';
			}

			$this->html .= '</tr>';
		}

		$this->html .= '</tbody></table></div>';
	}
}
