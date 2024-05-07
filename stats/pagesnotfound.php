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

class PagesNotFound extends StatsModule
{

    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_CUSTOM;

        $this->displayName = $this->l('Pages not found');
    }

    /**
     * @return array
     * @throws PrestaShopException
     */
    private function getPages()
    {
        $sql = 'SELECT http_referer, request_uri, COUNT(*) AS nb
				FROM `' . _DB_PREFIX_ . 'pagenotfound`
				WHERE date_add BETWEEN ' . ModuleGraph::getDateBetween()
            . Shop::addSqlRestriction() .
            'GROUP BY http_referer, request_uri';
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $pages = [];
        foreach ($result as $row) {
            $row['http_referer'] = parse_url($row['http_referer'], PHP_URL_HOST) . parse_url($row['http_referer'], PHP_URL_PATH);
            if (empty($row['http_referer'])) {
                $row['http_referer'] = '--';
            }
            if (!isset($pages[$row['request_uri']])) {
                $pages[$row['request_uri']] = ['nb' => 0];
            }
            $pages[$row['request_uri']][$row['http_referer']] = $row['nb'];
            $pages[$row['request_uri']]['nb'] += $row['nb'];
        }
        uasort($pages, function ($a, $b) {
            if ($a['nb'] == $b['nb']) {
                return 0;
            }
            return ($a['nb'] > $b['nb']) ? -1 : 1;
        });

        return $pages;
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        $html = '';

        if (Tools::isSubmit('submitTruncatePNF')) {
            Db::getInstance()->execute('TRUNCATE `' . _DB_PREFIX_ . 'pagenotfound`');
            $html .= '<div class="alert alert-warning"> ' . $this->l('The "pages not found" cache has been emptied.') . '</div>';
        } else {
            if (Tools::isSubmit('submitDeletePNF')) {
                Db::getInstance()->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . 'pagenotfound`
				WHERE date_add BETWEEN ' . ModuleGraph::getDateBetween()
                );
                $html .= '<div class="alert alert-warning"> ' . $this->l('The "pages not found" cache has been deleted.') . '</div>';
            }
        }

        $html .= '
			<div class="panel-heading">
				' . $this->displayName . '
			</div>
			<h4>' . $this->l('Guide') . '</h4>
			<div class="alert alert-warning">
				<h4>' . $this->l('404 errors') . '</h4>
				<p>'
            . $this->l('A 404 error is an HTTP error code which means that the file requested by the user cannot be found. In your case it means that one of your visitors entered a wrong URL in the address bar, or that you or another website has a dead link. When possible, the referrer is shown so you can find the page/site which contains the dead link. If not, it generally means that it is a direct access, so someone may have bookmarked a link which doesn\'t exist anymore.') . '
				</p>
				<p>&nbsp;</p>
				<h4>' . $this->l('How to catch these errors?') . '</h4>
				<p>'
            . sprintf($this->l('If your webhost supports .htaccess files, you can create one in the root directory of PrestaShop and insert the following line inside: "%s".'), 'ErrorDocument 404 ' . __PS_BASE_URI__ . '404.php') . '<br />' .
            sprintf($this->l('A user requesting a page which doesn\'t exist will be redirected to the following page: %s. This module logs access to this page.'), __PS_BASE_URI__ . '404.php') . '
				</p>
			</div>';
        if (!file_exists($this->_normalizeDirectory(_PS_ROOT_DIR_) . '.htaccess')) {
            $html .= '<div class="alert alert-warning">' . $this->l('You must use a .htaccess file to redirect 404 errors to the "404.php" page.') . '</div>';
        }

        $pages = $this->getPages();
        if (count($pages)) {
            $baseUrl = Configuration::get('PS_SSL_ENABLED')
                ? Tools::getProtocol(true) . Context::getContext()->shop->domain_ssl
                : Tools::getProtocol(false) . Context::getContext()->shop->domain;
            $html .= '
			<table class="table">
				<thead>
					<tr>
						<th><span class="title_box active">' . $this->l('Page') . '</span></th>
						<th><span class="title_box active">' . $this->l('Referrer') . '</span></th>
						<th><span class="title_box active">' . $this->l('Counter') . '</span></th>
					</tr>
				</thead>
				<tbody>';
            foreach ($pages as $ru => $hrs) {
                foreach ($hrs as $hr => $counter) {
                    if ($hr != 'nb') {
                        $html .= '
						<tr>
							<td><a href="' . $baseUrl . Tools::safeOutput($ru) . '" target="_blank">' . Tools::safeOutput(Tools::truncate(urldecode($ru), 30)) . '</a></td>
							<td><a href="' . Tools::getProtocol(true) . Tools::safeOutput($hr) . '" target="_blank" noreferrer>' .Tools::safeOutput(Tools::truncate(urldecode($hr), 40)) . '</a></td>
							<td>' . $counter . '</td>
						</tr>';
                    }
                }
            }
            $html .= '
				</tbody>
			</table>';
        } else {
            $html .= '<div class="alert alert-warning"> ' . $this->l('No "page not found" issue registered for now.') . '</div>';
        }

        if (count($pages)) {
            $html .= '
				<h4>' . $this->l('Empty database') . '</h4>
				<form action="' . Tools::htmlEntitiesUtf8($_SERVER['REQUEST_URI']) . '" method="post">
					<button type="submit" class="btn btn-default" name="submitDeletePNF">
						<i class="icon-remove"></i> ' . $this->l('Empty ALL "pages not found" notices for this period') . '
					</button>
					<button type="submit" class="btn btn-default" name="submitTruncatePNF">
						<i class="icon-remove"></i> ' . $this->l('Empty ALL "pages not found" notices') . '
					</button>
				</form>';
        }

        return $html;
    }

    /**
     * @param string $directory
     *
     * @return string
     */
    private function _normalizeDirectory($directory)
    {
        $last = $directory[strlen($directory) - 1];

        if (in_array($last, ['/', '\\'])) {
            $directory[strlen($directory) - 1] = DIRECTORY_SEPARATOR;
            return $directory;
        }

        $directory .= DIRECTORY_SEPARATOR;
        return $directory;
    }
}