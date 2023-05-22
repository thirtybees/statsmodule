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

class SEKeywords extends StatsModule
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
    protected $query2 = '';

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_GRAPH;

        $this->query = 'SELECT `keyword`, COUNT(TRIM(`keyword`)) AS occurences
				FROM `' . _DB_PREFIX_ . 'sekeyword`
				WHERE ' . (Configuration::get('SEK_FILTER_KW') == '' ? '1' : '`keyword` REGEXP \'' . Configuration::get('SEK_FILTER_KW') . '\'')
            . Shop::addSqlRestriction() .
            ' AND `date_add` BETWEEN ';

        $this->query2 = 'GROUP BY TRIM(`keyword`)
				HAVING occurences > ' . (int)Configuration::get('SEK_MIN_OCCURENCES') . '
				ORDER BY occurences DESC';

        $this->displayName = $this->l('Search engine keywords');
    }

    /**
     * @param array $params
     *
     * @return void
     * @throws PrestaShopException
     */
    public function hookTop($params)
    {
        if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], Tools::getHttpHost(false, false) == 0)) {
            return;
        }

        if ($keywords = $this->getKeywords($_SERVER['HTTP_REFERER'])) {
            Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'sekeyword` (`keyword`, `date_add`, `id_shop`, `id_shop_group`)
										VALUES (\'' . pSQL(mb_strtolower(trim($keywords))) . '\', NOW(), ' . (int)$this->context->shop->id . ', ' . (int)$this->context->shop->id_shop_group . ')');
        }
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        if (Tools::isSubmit('submitSEK')) {
            Configuration::updateValue('SEK_FILTER_KW', trim(Tools::getValue('SEK_FILTER_KW')));
            Configuration::updateValue('SEK_MIN_OCCURENCES', (int)Tools::getValue('SEK_MIN_OCCURENCES'));
            Tools::redirectAdmin('index.php?tab=AdminStats&token=' . Tools::getValue('token') . '&module=');
        }

        if (Tools::getValue('export')) {
            $this->csvExport(['type' => 'pie']);
        }
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . ModuleGraph::getDateBetween() . $this->query2);
        $total = count($result);
        $this->html = '
		<div class="panel-heading">'
            . $this->displayName . '
		</div>
		<h4>' . $this->l('Guide') . '</h4>
		<div class="alert alert-warning">
			<h4>' . $this->l('Identify external search engine keywords') . '</h4>
			<p>'
            . $this->l('This is one of the most common ways of finding a website through a search engine.') . '&nbsp;' .
            $this->l('Identifying the most popular keywords entered by your new visitors allows you to see the products you should put in front if you want to achieve better visibility in search engines.') . '
			</p>
			<p>&nbsp;</p>
			<h4>' . $this->l('How does it work?') . '</h4>
			<p>'
            . $this->l('When a visitor comes to your website, the web server notes the URL of the site he/she comes from. This module then parses the URL, and if it finds a reference to a known search engine, it finds the keywords in it.') . '<br>' .
            $this->l('This module can recognize all the search engines listed in PrestaShop\'s Stats/Search Engine page -- and you can add more!') . '<br>' .
            $this->l('IMPORTANT NOTE: in September 2013, Google chose to encrypt its searches queries using SSL. This means all the referer-based tools in the World (including this one) cannot identify Google keywords anymore.') . '
			</p>
		</div>
		<p>' . ($total == 1 ? sprintf($this->l('%d keyword matches your query.'), $total) : sprintf($this->l('%d keywords match your query.'), $total)) . '</p>';

        $form = '
		<form action="' . Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']) . '" method="post" class="form-horizontal">
			<div class="row row-margin-bottom">
				<label class="control-label col-lg-3">' . $this->l('Filter by keyword') . '</label>
				<div class="col-lg-9">
					<input type="text" name="SEK_FILTER_KW" value="' . Tools::htmlentitiesUTF8(Configuration::get('SEK_FILTER_KW')) . '" />
				</div>
			</div>
			<div class="row row-margin-bottom">
				<label class="control-label col-lg-3">' . $this->l('And min occurrences') . '</label>
				<div class="col-lg-9">
					<input type="text" name="SEK_MIN_OCCURENCES" value="' . (int)Configuration::get('SEK_MIN_OCCURENCES') . '" />
				</div>
			</div>
			<div class="row row-margin-bottom">
				<div class="col-lg-9 col-lg-offset-3">
					<button type="submit" class="btn btn-default" name="submitSEK">
						<i class="icon-ok"></i> ' . $this->l('Apply') . '
					</button>
				</div>
			</div>
		</form>';

        if ($result && $total) {
            $table = '
			<table class="table">
				<thead>
					<tr>
						<th><span class="title_box active">' . $this->l('Keywords') . '</span></th>
						<th><span class="title_box active">' . $this->l('Occurrences') . '</span></th>
					</tr>
				</thead>
				<tbody>';
            foreach ($result as $row) {
                $keyword =& $row['keyword'];
                $occurences =& $row['occurences'];
                $table .= '<tr><td>' . $keyword . '</td><td>' . $occurences . '</td></tr>';
            }
            $table .= '</tbody></table>';
            $this->html .= '<div>' . $this->engine($this->type, ['type' => 'pie']) . '</div>
			<a class="btn btn-default" href="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '&export=1&exportType=language"><<i class="icon-cloud-upload"></i> ' . $this->l('CSV Export') . '</a>
			' . $form . '<br/>' . $table;
        } else {
            $this->html .= $form . '<p><strong>' . $this->l('No keywords') . '</strong></p>';
        }

        return $this->html;
    }

    /**
     * @param string $url
     *
     * @return false|string
     * @throws PrestaShopException
     */
    public function getKeywords($url)
    {
        if (!Validate::isAbsoluteUrl($url)) {
            return false;
        }

        $parsed_url = parse_url($url);
        if (!isset($parsed_url['query']) && isset($parsed_url['fragment'])) {
            $parsed_url['query'] = $parsed_url['fragment'];
        }

        if (!isset($parsed_url['query'])) {
            return false;
        }

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT `server`, `getvar` FROM `' . _DB_PREFIX_ . 'search_engine`');
        foreach ($result as $row) {
            $host =& $row['server'];
            $varname =& $row['getvar'];
            if (strstr($parsed_url['host'], $host)) {
                $k_array = [];
                preg_match('/[^a-zA-Z&]?' . $varname . '=.*\&' . '/U', $parsed_url['query'], $k_array);

                if (empty($k_array[0])) {
                    preg_match('/[^a-zA-Z&]?' . $varname . '=.*$' . '/', $parsed_url['query'], $k_array);
                }

                if (empty($k_array[0])) {
                    return false;
                }

                if ($k_array[0][0] == '&' && mb_strlen($k_array[0]) == 1) {
                    return false;
                }

                return urldecode(str_replace('+', ' ', ltrim(mb_substr(rtrim($k_array[0], '&'), mb_strlen($varname) + 1), '=')));
            }
        }

        return false;
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
        $total_result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . $this->getDate() . $this->query2);
        $total = 0;
        $total2 = 0;
        foreach ($total_result as $total_row) {
            $total += $total_row['occurences'];
        }
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query . $this->getDate() . $this->query2 . ' LIMIT 9');
        foreach ($result as $row) {
            $this->_legend[] = $row['keyword'];
            $this->_values[] = $row['occurences'];
            $total2 += $row['occurences'];
        }
        if ($total >= $total2) {
            $this->_legend[] = $this->l('Others');
            $this->_values[] = $total - $total2;
        }
    }
}
