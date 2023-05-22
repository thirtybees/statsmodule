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

class StatsOrigin extends StatsModule
{
    /**
     * @var string
     */
    protected $_html;

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRAPH;

        $this->displayName = $this->l('Visitors origin');
    }

    /**
     * @param string $dateBetween
     *
     * @return int[]
     *
     * @throws PrestaShopException
     */
    private function getOrigins($dateBetween)
    {
        $directLink = $this->l('Direct link');
        $sql = 'SELECT http_referer
				FROM ' . _DB_PREFIX_ . 'connections
				WHERE 1
					' . Shop::addSqlRestriction() . '
					AND date_add BETWEEN ' . $dateBetween;
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->query($sql);
        $websites = [$directLink => 0];
        while ($row = Db::getInstance(_PS_USE_SQL_SLAVE_)->nextRow($result)) {
            if (empty($row['http_referer'])) {
                ++$websites[$directLink];
            }
            else {
                $website = preg_replace('/^www./', '', parse_url($row['http_referer'], PHP_URL_HOST));
                if (!isset($websites[$website])) {
                    $websites[$website] = 1;
                }
                else {
                    ++$websites[$website];
                }
            }
        }
        arsort($websites);
        return $websites;
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $websites = $this->getOrigins(ModuleGraph::getDateBetween());
        if (Tools::getValue('export')) {
            if (Tools::getValue('exportType') == 'top') {
                $this->csvExport(['type' => 'pie']);
            }
        }
        $this->_html = '<div class="panel-heading">' . $this->l('Origin') . '</div>';
        if (count($websites)) {
            $this->_html .= '
			<div class="alert alert-info">
				' . $this->l('In the tab, we break down the 10 most popular referral websites that bring customers to your online store.') . '
			</div>
			<h4>' . $this->l('Guide') . '</h4>
			<div class="alert alert-warning">
				<h4>' . $this->l('What is a referral website?') . '</h4>
				<p>
					' . $this->l('The referrer is the URL of the previous webpage from which a link was followed by the visitor.') . '<br />
					' . $this->l('A referrer also enables you to know which keywords visitors use in search engines when browsing for your online store.') . '<br /><br />
					' . $this->l('A referrer can be:') . '
				</p>
				<ul>
					<li>' . $this->l('Someone who posts a link to your shop.') . '</li>
					<li>' . $this->l('A partner who has agreed to a link exchange in order to attract new customers.') . '</li>
				</ul>
			</div>
			<div class="row row-margin-bottom">
				<div class="col-lg-12">
					<div class="col-lg-8">
						' . $this->engine($this->type, ['type' => 'pie']) . '
					</div>
					<div class="col-lg-4">
						<a href="' . Tools::safeOutput($_SERVER['REQUEST_URI'] . '&export=1&exportType=top') . '" class="btn btn-default">
							<i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '
						</a>
					</div>
				</div>
			</div>
			<table class="table">
				<thead>
					<tr>
						<th><span class="title_box active">' . $this->l('Origin') . '</span></th>
						<th><span class="title_box active">' . $this->l('Total') . '</span></th>
					</tr>
				</thead>
				<tbody>';
            foreach ($websites as $website => $total) {
                $this->_html .= '
					<tr>
						<td>' . (!strstr($website, ' ') ? '<a href="' . Tools::getProtocol() . $website . '">' : '') . $website . (!strstr($website, ' ') ? '</a>' : '') . '</td><td>' . $total . '</td>
					</tr>';
            }
            $this->_html .= '
				</tbody>
			</table>';
        } else {
            $this->_html .= '<p>' . $this->l('Direct links only') . '</p>';
        }
        return $this->_html;
    }

    /**
     * @param int $layers
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function getData($layers)
    {
        $this->_titles['main'] = $this->l('Top ten referral websites');
        $websites = $this->getOrigins($this->getDate());
        $total = 0;
        $total2 = 0;
        $i = 0;
        foreach ($websites as $website => $totalRow) {
            if (!$totalRow) {
                continue;
            }
            $total += $totalRow;
            if ($i++ < 9) {
                $this->_legend[] = $website;
                $this->_values[] = $totalRow;
                $total2 += $totalRow;
            }
        }
        if ($total != $total2) {
            $this->_legend[] = $this->l('Others');
            $this->_values[] = $total - $total2;
        }
    }
}


