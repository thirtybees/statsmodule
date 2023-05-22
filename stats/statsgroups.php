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

class StatsGroups extends StatsModule
{
    /**
     * @var string
     */
    protected $html = '';

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_CUSTOM;

        $this->displayName = $this->l('Stats by Groups');
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        if (!isset($this->context->cookie->stats_granularity)) {
            $this->context->cookie->stats_granularity = 10;
        }
        if (Tools::isSubmit('submitIdZone')) {
            $this->context->cookie->stats_id_zone = (int)Tools::getValue('stats_id_zone');
        }
        if (Tools::isSubmit('submitGranularity')) {
            $this->context->cookie->stats_granularity = Tools::getValue('stats_granularity');
        }

        $currency = $this->context->currency;
        $employee = $this->context->employee;

        $this->html .= '<div>
            <div class="panel-heading"><i class="icon-dashboard"></i> ' . $this->displayName . '</div>
            <div class="alert alert-info">' . $this->l('The listed amounts do not include tax.') . '</div>';

        $resultSql = 'SELECT COUNT(*) as countOrders,
            SUM((SELECT SUM(od.product_quantity) FROM ' . _DB_PREFIX_ . 'order_detail od WHERE o.id_order = od.id_order)) as countProducts,
            SUM(o.total_paid_tax_excl / o.conversion_rate) as totalSales
            FROM ' . _DB_PREFIX_ . 'orders o
            WHERE o.valid = 1
            AND o.invoice_date BETWEEN ' . ModuleGraph::getDateBetween() . '
            ' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o');

        if ($newResult = Db::getInstance()->getRow($resultSql)) {
            $this->html .= '<div>' . $this->l('Placed orders') . ': ' . $newResult['countOrders'] . ' | ' . $this->l('Bought items') . ': ' . $newResult['countProducts'] . ' | ' . $this->l('Revenue') . ': ' . Tools::displayPrice($newResult['totalSales'], $currency) . '</div><br /><br />';

            $this->html .= '<table class="table">
                <thead>
                  <tr>
                    <th><span class="title_box active">' . $this->l('ID') . '</span></th>
                    <th><span class="title_box active">' . $this->l('Group') . '</span></th>
                    <th class="text-right"><span class="title_box active">' . $this->l('Revenue') . '</span></th>
                    <th class="text-right"><span class="title_box active">' . $this->l('Average cart value') . '</span></th>
                    <th class="text-center"><span class="title_box active">' . $this->l('Placed orders') . '</span></th>
                    <th class="text-center"><span class="title_box active">' . $this->l('Members per group') . '</span></th>
                  </tr>
                </thead>
                <tbody>';

            $groupSql = 'SELECT * FROM `' . _DB_PREFIX_ . 'group_lang` WHERE `id_lang`=' . (int)$this->context->language->id . ' GROUP BY `id_group` ORDER BY `id_group`';
            if ($results = Db::getInstance()->ExecuteS($groupSql)) {
                foreach ($results as $grow) {
                    $this->html .= '<tr>';
                    $this->html .= '<td>' . $grow['id_group'] . '</td>';
                    $this->html .= '<td>' . $grow['name'] . '</td>';

                    $cagroupSql = 'SELECT SUM(o.total_paid_tax_excl / o.conversion_rate) as totalCA,
                        COUNT(o.id_order) as nbrCommandes
                        FROM ' . _DB_PREFIX_ . 'orders o
                        LEFT JOIN ' . _DB_PREFIX_ . 'customer c ON c.id_customer=o.id_customer
                        WHERE c.id_default_group=' . $grow['id_group'] . '
                        AND o.valid = 1
                        AND o.invoice_date BETWEEN ' . ModuleGraph::getDateBetween() . '
                        ' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o');
                    if ($cagroup = Db::getInstance()->getrow($cagroupSql)) {
                        if ((int)$cagroup['nbrCommandes']) {
                            $this->html .= '<td class="text-right">' . Tools::displayPrice($cagroup['totalCA'], $currency) . '</td>';
                            $this->html .= '<td class="text-right">' . Tools::displayPrice(($cagroup['totalCA'] / $cagroup['nbrCommandes']), $currency) . '</td>';
                            $this->html .= '<td class="text-center">' . $cagroup['nbrCommandes'] . '</td>';
                        } else {
                            $this->html .= '<td></td>';
                            $this->html .= '<td></td>';
                            $this->html .= '<td></td>';
                        }
                    } else {
                        $this->html .= '<td></td>';
                        $this->html .= '<td></td>';
                        $this->html .= '<td></td>';
                    }

                    $membersSql = 'SELECT COUNT(*) as nombread
                        FROM ' . _DB_PREFIX_ . 'customer WHERE id_default_group=' . $grow['id_group'] . '
                        AND date_add <= "' . $employee->stats_date_to . ' 23:59:59"';

                    if ($members = Db::getInstance()->getrow($membersSql)) {
                        $this->html .= '<td class="text-center">' . $members['nombread'] . '</td>';
                    }
                    $this->html .= '</tr>';
                }
            }
            $this->html .= '</table>';
        }

        return $this->html;
    }
}
