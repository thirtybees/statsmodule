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

class StatsLive extends StatsModule
{
    /**
     * @var string
     */
    protected $html = '';

    public function __construct()
    {
        parent::__construct();
        $this->type = static::TYPE_CUSTOM;

        $this->displayName = $this->l('Visitors online');
    }

    /**
     * Get the number of online customers
     *
     * @return array (array, int) array of online customers entries, number of online customers
     *
     * @throws PrestaShopException
     */
    private function getCustomersOnline()
    {
        $maintenanceIPs = $this->getMaintenanceIPs();

        if (Configuration::get('PS_STATSDATA_CUSTOMER_PAGESVIEWS')) {
            $sql = 'SELECT u.id_customer, u.firstname, u.lastname, pt.name AS page
					FROM `' . _DB_PREFIX_ . 'connections` c
					LEFT JOIN `' . _DB_PREFIX_ . 'connections_page` cp ON c.id_connections = cp.id_connections
					LEFT JOIN `' . _DB_PREFIX_ . 'page` p ON p.id_page = cp.id_page
					LEFT JOIN `' . _DB_PREFIX_ . 'page_type` pt ON p.id_page_type = pt.id_page_type
					INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON c.id_guest = g.id_guest
					INNER JOIN `' . _DB_PREFIX_ . 'customer` u ON u.id_customer = g.id_customer
					WHERE cp.`time_end` IS NULL
						' . Shop::addSqlRestriction(false, 'c') . '
						AND TIME_TO_SEC(TIMEDIFF(\'' . pSQL(date('Y-m-d H:i:00', time())) . '\', cp.`time_start`)) < 900
					' . ($maintenanceIPs ? 'AND c.ip_address NOT IN (' . $maintenanceIPs . ')' : '') . '
					GROUP BY u.id_customer
					ORDER BY u.firstname, u.lastname';
        } else {
            $sql = 'SELECT u.id_customer, u.firstname, u.lastname, "-" AS page
					FROM `' . _DB_PREFIX_ . 'connections` c
					INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON c.id_guest = g.id_guest
					INNER JOIN `' . _DB_PREFIX_ . 'customer` u ON u.id_customer = g.id_customer
					WHERE TIME_TO_SEC(TIMEDIFF(\'' . pSQL(date('Y-m-d H:i:00', time())) . '\', c.`date_add`)) < 900
						' . Shop::addSqlRestriction(false, 'c') . '
					' . ($maintenanceIPs ? 'AND c.ip_address NOT IN (' . $maintenanceIPs . ')' : '') . '
					GROUP BY u.id_customer
					ORDER BY u.firstname, u.lastname';
        }

        $conn = Db::readOnly();
        $results = $conn->getArray($sql);

        return [$results, $conn->numRows()];
    }

    /**
     * Get the number of online visitors
     *
     * @return array(array, int) array of online visitors entries, number of online visitors
     *
     * @throws PrestaShopException
     */
    private function getVisitorsOnline()
    {
        $maintenanceIPs = $this->getMaintenanceIPs();

        if (Configuration::get('PS_STATSDATA_CUSTOMER_PAGESVIEWS')) {
            $sql = 'SELECT c.id_guest, c.ip_address, c.date_add, c.http_referer, pt.name AS page
					FROM `' . _DB_PREFIX_ . 'connections` c
					LEFT JOIN `' . _DB_PREFIX_ . 'connections_page` cp ON c.id_connections = cp.id_connections
					LEFT JOIN `' . _DB_PREFIX_ . 'page` p ON p.id_page = cp.id_page
					LEFT JOIN `' . _DB_PREFIX_ . 'page_type` pt ON p.id_page_type = pt.id_page_type
					INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON c.id_guest = g.id_guest
					WHERE (g.id_customer IS NULL OR g.id_customer = 0)
						' . Shop::addSqlRestriction(false, 'c') . '
						AND cp.`time_end` IS NULL
					AND TIME_TO_SEC(TIMEDIFF(\'' . pSQL(date('Y-m-d H:i:00', time())) . '\', cp.`time_start`)) < 900
					' . ($maintenanceIPs ? 'AND c.ip_address NOT IN (' . $maintenanceIPs . ')' : '') . '
					GROUP BY c.id_connections
					ORDER BY c.date_add DESC';
        } else {
            $sql = 'SELECT c.id_guest, c.ip_address, c.date_add, c.http_referer, "-" AS page
					FROM `' . _DB_PREFIX_ . 'connections` c
					INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON c.id_guest = g.id_guest
					WHERE (g.id_customer IS NULL OR g.id_customer = 0)
						' . Shop::addSqlRestriction(false, 'c') . '
						AND TIME_TO_SEC(TIMEDIFF(\'' . pSQL(date('Y-m-d H:i:00', time())) . '\', c.`date_add`)) < 900
					' . ($maintenanceIPs ? 'AND c.ip_address NOT IN (' . $maintenanceIPs . ')' : '') . '
					ORDER BY c.date_add DESC';
        }

        $conn = Db::readOnly();
        $results = $conn->getArray($sql);

        return [$results, $conn->numRows()];
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        list($customers, $total_customers) = $this->getCustomersOnline();
        list($visitors, $total_visitors) = $this->getVisitorsOnline();
        $irow = 0;

        $this->html .= '<script type="text/javascript">
			$("#calendar").remove();
		</script>';
        if (!Configuration::get('PS_STATSDATA_CUSTOMER_PAGESVIEWS')) {
            $this->html .= '
				<div class="alert alert-info">' .
                $this->l('You must activate the "Save page views for each customer" option in the "Data mining for statistics" (StatsData) module in order to see the pages that your visitors are currently viewing.') . '
				</div>';
        }
        $this->html .= '
			<h4> ' . $this->l('Current online customers') . '</h4>';
        if ($total_customers) {
            $this->html .= $this->l('Total:') . ' ' . (int)$total_customers . '
			<table class="table">
				<thead>
					<tr>
						<th class="center"><span class="title_box active">' . $this->l('Customer ID') . '</span></th>
						<th class="center"><span class="title_box active">' . $this->l('Name') . '</span></th>
						<th class="center"><span class="title_box active">' . $this->l('Current page') . '</span></th>
						<th class="center"><span class="title_box active">' . $this->l('View customer profile') . '</span></th>
					</tr>
				</thead>
				<tbody>';
            foreach ($customers as $customer) {
                $this->html .= '
					<tr' . ($irow++ % 2 ? ' class="alt_row"' : '') . '>
						<td class="center">' . $customer['id_customer'] . '</td>
						<td class="center">' . $customer['firstname'] . ' ' . $customer['lastname'] . '</td>
						<td class="center">' . $customer['page'] . '</td>
						<td class="center">
							<a href="' . Tools::safeOutput('index.php?tab=AdminCustomers&id_customer=' . $customer['id_customer'] . '&viewcustomer&token=' . Tools::getAdminToken('AdminCustomers' . (int)Tab::getIdFromClassName('AdminCustomers') . (int)$this->context->employee->id)) . '"
								target="_blank">
								<i class="icon icon-eye"></i>
							</a>
						</td>
					</tr>';
            }
            $this->html .= '
				</tbody>
			</table>';
        } else {
            $this->html .= '<p class="alert alert-warning">' . $this->l('There are no active customers online right now.') . '</p>';
        }
        $this->html .= '
			<h4> ' . $this->l('Current online visitors') . '</h4>';
        if ($total_visitors) {
            $this->html .= $this->l('Total:') . ' ' . (int)$total_visitors . '
			<div>
				<table class="table">
					<thead>
						<tr>
							<th class="center"><span class="title_box active">' . $this->l('Guest ID') . '</span></th>
							<th class="center"><span class="title_box active">' . $this->l('IP') . '</span></th>
							<th class="center"><span class="title_box active">' . $this->l('Last activity') . '</span></th>
							<th class="center"><span class="title_box active">' . $this->l('Current page') . '</span></th>
							<th class="center"><span class="title_box active">' . $this->l('Referrer') . '</span></th>
						</tr>
					</thead>
					<tbody>';
            foreach ($visitors as $visitor) {
                $this->html .= '<tr' . ($irow++ % 2 ? ' class="alt_row"' : '') . '>
						<td class="center">' . $visitor['id_guest'] . '</td>
						<td class="center">' . long2ip($visitor['ip_address']) . '</td>
						<td class="center">' . substr($visitor['date_add'], 11) . '</td>
						<td class="center">' . ($visitor['page'] ?? $this->l('Undefined')) . '</td>
						<td class="center">' . (empty($visitor['http_referer']) ? $this->l('None') : parse_url($visitor['http_referer'], PHP_URL_HOST)) . '</td>
					</tr>';
            }
            $this->html .= '
					</tbody>
				</table>
			</div>';
        } else {
            $this->html .= '<p class="alert alert-warning">' . $this->l('There are no visitors online.') . '</p>';
        }
        $this->html .= '
			<h4>' . $this->l('Notice') . '</h4>
			<p class="alert alert-info">' . $this->l('Maintenance IPs are excluded from the online visitors.') . '</p>
			<a class="btn btn-default" href="' . Tools::safeOutput('index.php?controller=AdminMaintenance&token=' . Tools::getAdminTokenLite('AdminMaintenance')) . '">
				<i class="icon-share-alt"></i> ' . $this->l('Add or remove an IP address.') . '
			</a>
		';

        return $this->html;
    }

    /**
     * @return string|null
     * @throws PrestaShopException
     */
    protected function getMaintenanceIPs()
    {
        $ips = (string)Configuration::get('PS_MAINTENANCE_IP');
        if ($ips) {
            $addresses = array_filter(array_map('ip2long', array_map('trim', explode(',', $ips))));
            if ($addresses) {
                return implode(',', $addresses);
            }
        }
        return null;
    }
}
