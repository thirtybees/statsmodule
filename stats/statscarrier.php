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

class StatsCarrier extends StatsModule
{
    /**
     * @var string
     */
    protected $html = '';
    /**
     * @var string
     */
    protected $option = '';

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRAPH;
        $this->displayName = $this->l('Carrier distribution');
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $sql = 'SELECT COUNT(o.`id_order`) AS total
				FROM `' . _DB_PREFIX_ . 'orders` o
				WHERE o.`date_add` BETWEEN ' . ModuleGraph::getDateBetween() . '
					' . Shop::addSqlRestriction(false, 'o') . '
					' . ((int)Tools::getValue('id_order_state') ? 'AND (SELECT oh.id_order_state FROM `' . _DB_PREFIX_ . 'order_history` oh WHERE o.id_order = oh.id_order ORDER BY oh.date_add DESC, oh.id_order_history DESC LIMIT 1) = ' . (int)Tools::getValue('id_order_state') : '');
        $result = Db::readOnly()->getRow($sql);
        $states = OrderState::getOrderStates($this->context->language->id);

        if (Tools::getValue('export')) {
            $this->csvExport(['type' => 'pie', 'option' => Tools::getValue('id_order_state')]);
        }
        $this->html = '
			<div class="panel-heading">
				' . $this->displayName . '
			</div>
			<form action="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '" method="post" class="form-horizontal alert">
				<div class="row">
					<div class="col-lg-5 col-lg-offset-6">
						<select name="id_order_state">
							<option value="0"' . ((!Tools::getValue('id_order_state')) ? ' selected="selected"' : '') . '>' . $this->l('All') . '</option>';
        foreach ($states as $state) {
            $this->html .= '<option value="' . $state['id_order_state'] . '"' . (($state['id_order_state'] == Tools::getValue('id_order_state')) ? ' selected="selected"' : '') . '>' . $state['name'] . '</option>';
        }
        $this->html .= '</select>
					</div>
					<div class="col-lg-1">
						<input type="submit" name="submitState" value="' . $this->l('Filter') . '" class="btn btn-default pull-right" />
					</div>
				</div>
			</form>

			<div class="alert alert-info">
				' . $this->l('This graph represents the carrier distribution for your orders. You can also narrow the focus of the graph to display distribution for a particular order status.') . '
			</div>
			<div class="row row-margin-bottom">
				<div class="col-lg-12">
					<div class="col-lg-8">
						' . ($result['total'] ? $this->engine(['type' => 'pie', 'option' => Tools::getValue('id_order_state')]) . '
					</div>
					<div class="col-lg-4">
						<a href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1&exportType=language') . '" class="btn btn-default">
							<i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
						</a>' : $this->l('No valid orders have been received for this period.')) . '
					</div>
				</div>
			</div>';

        return $this->html;
    }

    /**
     * @param int $option
     * @param int $layers
     *
     * @return void
     */
    public function setOption($option, $layers = 1)
    {
        $this->option = (int)$option;
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function getData($layers)
    {
        $state_query = '';
        if ((int)$this->option) {
            $state_query = 'AND (
				SELECT oh.id_order_state FROM `' . _DB_PREFIX_ . 'order_history` oh
				WHERE o.id_order = oh.id_order
				ORDER BY oh.date_add DESC, oh.id_order_history DESC
				LIMIT 1) = ' . (int)$this->option;
        }
        $this->_titles['main'] = $this->l('Percentage of orders listed by carrier.');

        $sql = 'SELECT c.name, COUNT(DISTINCT o.`id_order`) AS total
				FROM `' . _DB_PREFIX_ . 'carrier` c
				LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_carrier = c.id_carrier
				WHERE o.`date_add` BETWEEN ' . ModuleGraph::getDateBetween() . '
					' . Shop::addSqlRestriction(false, 'o') . '
					' . $state_query . '
				GROUP BY c.`id_carrier`';
        $result = Db::readOnly()->getArray($sql);
        foreach ($result as $row) {
            $this->_values[] = $row['total'];
            $this->_legend[] = $row['name'];
        }
    }
}


