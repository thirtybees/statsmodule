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

class StatsSearch extends StatsModule
{
    /**
     * @var string
     */
    protected $html = '';
    /**
     * @var string
     */
    protected $query = '';
    /**
     * @var string
     */
    protected $query_group_by = '';

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRAPH;

        $this->query = 'SELECT `keywords`, COUNT(TRIM(`keywords`)) as occurences, MAX(results) as total
				FROM `' . _DB_PREFIX_ . 'statssearch`
				WHERE 1
					' . Shop::addSqlRestriction() . '
					AND `date_add` BETWEEN ';

        $this->query_group_by = 'GROUP BY `keywords`
				HAVING occurences >= 1
				ORDER BY occurences DESC';

        $this->displayName = $this->l('Shop search');
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        if (Tools::getValue('export')) {
            $this->csvExport(['type' => 'pie']);
        }

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . ModuleGraph::getDateBetween() . $this->query_group_by);
        $this->html = '
		<div class="panel-heading">
			' . $this->displayName . '
		</div>';
        $table = '
		<table class="table">
			<thead>
				<tr>
					<th><span class="title_box active">' . $this->l('Keywords') . '</span></th>
					<th><span class="title_box active">' . $this->l('Occurrences') . '</span></th>
					<th><span class="title_box active">' . $this->l('Results') . '</span></th>
				</tr>
			</thead>
			<tbody>';

        foreach ($result as $row) {
            if (mb_strlen($row['keywords']) >= Configuration::get('PS_SEARCH_MINWORDLEN')) {
                $table .= '<tr>
					<td>' . $row['keywords'] . '</td>
					<td>' . $row['occurences'] . '</td>
					<td>' . $row['total'] . '</td>
				</tr>';
            }
        }
        $table .= '
			</tbody>
		</table>';

        if (count($result)) {
            $this->html .= '<div>' . $this->engine($this->type, ['type' => 'pie']) . '</div>
							<a class="btn btn-default" href="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '&export=1">
								<i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
							</a>' . $table;
        }
        else {
            $this->html .= '<p>' . $this->l('Cannot find any keywords that have been searched for more than once.') . '</p>';
        }

        return $this->html;
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function getData($layers)
    {
        $this->_titles['main'] = $this->l('Top 10 keywords');
        $total_result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . $this->getDate() . $this->query_group_by);
        $total = 0;
        $total2 = 0;
        foreach ($total_result as $total_row) {
            $total += $total_row['occurences'];
        }
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . $this->getDate() . $this->query_group_by . ' LIMIT 9');
        foreach ($result as $row) {
            if (!$row['occurences']) {
                continue;
            }
            $this->_legend[] = $row['keywords'];
            $this->_values[] = $row['occurences'];
            $total2 += $row['occurences'];
        }
        if ($total > $total2) {
            $this->_legend[] = $this->l('Others');
            $this->_values[] = $total - $total2;
        }
    }
}
