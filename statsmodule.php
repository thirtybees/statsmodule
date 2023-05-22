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

class StatsModule extends ModuleStats
{
    const TYPE_GRID = 'Grid';
    const TYPE_GRAPH = 'Graph';
    const TYPE_CUSTOM = 'Custom';

    /**
     * @var string Grid|Graph|Custom
     */
    protected $type;

    /**
     * @var string[]
     */
    public $modules = [
        'pagesnotfound',
        'sekeywords',
        'statsbestcategories',
        'statsbestcustomers',
        'statsbestmanufacturers',
        'statsbestproducts',
        'statsbestsuppliers',
        'statsbestvouchers',
        'statscarrier',
        'statscatalog',
        'statscheckup',
        'statsequipment',
        'statsforecast',
        'statsgroups',
        'statslive',
        'statsnewsletter',
        'statsordersprofit',
        'statsorigin',
        'statspersonalinfos',
        'statsproduct',
        'statsregistrations',
        'statssales',
        'statssearch',
        'statsstock',
        'statsvisits',
    ];

    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'statsmodule';
        $this->tab = 'analytics_stats';
        $this->version = '2.2.1';
        $this->author = 'thirty bees';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Statistics Module');
        $this->description = $this->l('Adds several statistics to the shop.');
        $this->tb_versions_compliancy = '> 1.0.3';
        $this->tb_min_version = '1.0.4';
    }

    /**
     * Install this module
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $this->registerHook('search');
        $this->registerHook('top');
        $this->registerHook('AdminStatsModules');

        if (!defined('TB_INSTALLATION_IN_PROGRESS') || !TB_INSTALLATION_IN_PROGRESS) {
            $this->unregisterStatsModuleHooks();
        }


        // statscheckup
        $confs = [
            'CHECKUP_DESCRIPTIONS_LT' => 100,
            'CHECKUP_DESCRIPTIONS_GT' => 400,
            'CHECKUP_IMAGES_LT' => 1,
            'CHECKUP_IMAGES_GT' => 2,
            'CHECKUP_SALES_LT' => 1,
            'CHECKUP_SALES_GT' => 2,
            'CHECKUP_STOCK_LT' => 1,
            'CHECKUP_STOCK_GT' => 3,
        ];
        foreach ($confs as $confname => $confdefault) {
            if (!Configuration::get($confname)) {
                Configuration::updateValue($confname, (int)$confdefault);
            }
        }

        // Search Engine Keywords
        Configuration::updateValue('SEK_MIN_OCCURENCES', 1);
        Configuration::updateValue('SEK_FILTER_KW', '');

        Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sekeyword` (
			id_sekeyword INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
			id_shop INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
			id_shop_group INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
			keyword VARCHAR(256) NOT NULL,
			date_add DATETIME NOT NULL,
			PRIMARY KEY(id_sekeyword)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pagenotfound` (
			id_pagenotfound INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
			id_shop INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
			id_shop_group INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
			request_uri VARCHAR(256) NOT NULL,
			http_referer VARCHAR(256) NOT NULL,
			date_add DATETIME NOT NULL,
			PRIMARY KEY(id_pagenotfound),
			INDEX (`date_add`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
        );

        Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'statssearch` (
			id_statssearch INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
			id_shop INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
		  	id_shop_group INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
			keywords VARCHAR(255) NOT NULL,
			results INT(6) NOT NULL DEFAULT 0,
			date_add DATETIME NOT NULL,
			PRIMARY KEY(id_statssearch)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        return true;
    }

    /**
     * @return array|array[]
     */
    public function getStatsModulesList()
    {
        return array_map(function ($module) {
            return ['name' => $module];
        }, $this->modules);
    }

    /**
     * @param string $moduleName
     * @param bool $hook
     *
     * @return StatsModule|string
     * @throws PrestaShopException
     */
    public function executeStatsInstance($moduleName, $hook = false)
    {
        $module = $this->getSubmoduleInstance($moduleName);
        if ($hook) {
            return $module->hookAdminStatsModules();
        } else {
            return $module;
        }
    }

    /**
     * @param string $moduleName
     *
     * @return StatsModule
     */
    protected function getSubmoduleInstance($moduleName)
    {
        require_once(dirname(__FILE__) . '/stats/' . $moduleName . '.php');
        return new $moduleName();
    }

    /**
     * @param string $type
     * @param array $params
     *
     * @return mixed
     */
    protected function engine($type, $params)
    {
        return call_user_func_array([$this, 'engine' . $type], [$params]);
    }

    /**
     * @param int $layers
     *
     * @return void
     */
    protected function getData($layers)
    {
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    public function hookAdminStatsModules()
    {
        throw new PrestaShopException('Stat submodule must implement hookAdminStatsModules() method');
    }

    /**
     * @return void
     */
    public function render()
    {
        $this->_render->render();
    }

    /**
     * @param array $params
     *
     * @return void
     * @throws PrestaShopException
     */
    public function hookSearch($params)
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'statssearch` (`id_shop`, `id_shop_group`, `keywords`, `results`, `date_add`)
				VALUES (' . (int)$this->context->shop->id . ', ' . (int)$this->context->shop->id_shop_group . ', \'' . pSQL($params['expr']) . '\', ' . (int)$params['total'] . ', NOW())';
        Db::getInstance()->execute($sql);
    }

    /**
     * @param array $params Module params
     *
     * @return string
     * @throws PrestaShopException
     */
    public function hookTop($params)
    {
        return $this->propagateHook('top', $params);
    }

    /**
     * @param string $hookName
     * @param array $params
     *
     * @return string
     * @throws PrestaShopException
     */
    protected function propagateHook($hookName, $params)
    {
        $result = '';
        $hookId = Hook::getIdByName($hookName);
        $hookName = Hook::getNameById($hookId);
        $retroName = Hook::getRetroHookName($hookName);

        $methods = ['hook' . ucfirst($hookName)];
        if ($retroName) {
            $methods[] = 'hook' . ucfirst($retroName);
        }

        foreach ($this->modules as $moduleName) {
            if (include_once dirname(__FILE__) . '/stats/' . $moduleName . '.php') {
                try {
                    $refl = new ReflectionClass($moduleName);
                    foreach ($methods as $methodName) {
                        if ($refl->hasMethod($methodName)) {
                            $methodInfo = $refl->getMethod($methodName);
                            if ($methodInfo->class !== static::class) {
                                if (!isset($instance)) {
                                    $instance = $this->getSubmoduleInstance($moduleName);
                                }
                                $result .= $instance->{$methodName}($params);
                            }
                        }
                    }
                    unset($instance);
                } catch (ReflectionException $e) {
                    throw new PrestaShopException("Failed to propagate hook $hookName", 0, $e);
                }
            }
        }
        return $result;
    }

    /**
     * Unregister module from hook
     *
     * @return bool result
     *
     * @throws PrestaShopException
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function unregisterStatsModuleHooks()
    {
        // Get hook id if a name is given as argument
        $hookName = 'displayAdminStatsModules';
        $hookId = Hook::getIdByName($hookName);

        $result = true;
        foreach ($this->modules as $moduleName) {
            Hook::exec('actionModuleUnRegisterHookBefore', ['object' => $this, 'hook_name' => $hookName]);

            // Unregister module on hook by id
            $result = Db::getInstance()->delete(
                    'hook_module',
                    '`id_module` = ' . (int)Module::getModuleIdByName($moduleName) . ' AND `id_hook` = ' . (int)$hookId
                ) && $result;

            // Clean modules position
            $this->cleanPositions($hookId);

            Hook::exec('actionModuleUnRegisterHookAfter', ['object' => $this, 'hook_name' => $hookName]);
        }

        return $result;
    }

    /**
     * @param array $datas
     *
     * @return void
     * @throws PrestaShopException
     */
    protected function csvExport($datas)
    {
        switch ($this->type) {
            case static::TYPE_GRID:
                $this->csvExportGrid($datas);
                return;
            case static::TYPE_GRAPH:
                $this->csvExportGraph($datas);
                return;
            case static::TYPE_CUSTOM:
                throw new PrestaShopException("Custom types do not support csv export");
        }
        throw new PrestaShopException("Cant export: invalid type");
    }

    /**
     * No-op implementation
     *
     * AdminStatsTabController never calls this hook handler for this particular module because of specific exception.
     * However, the hook handler must exists
     */
    public function hookDisplayAdminStatsModules()
    {
    }
}

/**
 * Submodule translations
 * This has to be present to support submodule translation
 *
 * $this->l('%d keyword matches your query.')
 * $this->l('%d keywords match your query.')
 * $this->l('(1 purchase / %d visits)')
 * $this->l('-- No filter --')
 * $this->l('0-18')
 * $this->l('18-24')
 * $this->l('25-34')
 * $this->l('35-49')
 * $this->l('404 errors')
 * $this->l('50-59')
 * $this->l('60+')
 * $this->l('A 404 error is an HTTP error code which means that the file requested by the user cannot be found. In your case it means that one of your visitors entered a wrong URL in the address bar, or that you or another website has a dead link. When possible, the referrer is shown so you can find the page/site which contains the dead link. If not, it generally means that it is a direct access, so someone may have bookmarked a link which doesn\'t exist anymore.')
 * $this->l('A partner who has agreed to a link exchange in order to attract new customers.')
 * $this->l('A referrer also enables you to know which keywords visitors use in search engines when browsing for your online store.')
 * $this->l('A referrer can be:')
 * $this->l('A significant increase or decrease in customer registration shows that there has probably been a change to your shop. With that in mind, we suggest that you identify the cause, correct the issue and get back in the business of making money!')
 * $this->l('A simple statistical calculation lets you know the monetary value of your visitors:')
 * $this->l('A user requesting a page which doesn\'t exist will be redirected to the following page: %s. This module logs access to this page.')
 * $this->l('A visit corresponds to an internet user coming to your shop, and until the end of their session, only one visit is counted.')
 * $this->l('A visitor is an unknown person who has not registered or logged into your store. A visitor can also be considered a person who has visited your shop multiple times.')
 * $this->l('About order statuses')
 * $this->l('Accounts')
 * $this->l('Active')
 * $this->l('Add or remove an IP address.')
 * $this->l('After choosing a category and selecting a product, informational graphs will appear.')
 * $this->l('Age range')
 * $this->l('Age ranges allow you to better understand target demographics.')
 * $this->l('All countries')
 * $this->l('All')
 * $this->l('Alright')
 * $this->l('An advertising campaign can attract an increased number of visitors to your online store. This will likely be followed by an increase in customer accounts and profit margins, which will depend on customer "quality." Well-targeted advertising is typically more effective than large-scale advertising... and it\'s cheaper too!')
 * $this->l('An empty record-set was returned.')
 * $this->l('And min occurrences')
 * $this->l('Apply')
 * $this->l('Attribute distribution')
 * $this->l('Attribute sales distribution')
 * $this->l('Attribute')
 * $this->l('Attributes')
 * $this->l('Available quantities')
 * $this->l('Available quantity for sale')
 * $this->l('Average cart value')
 * $this->l('Average number of images:')
 * $this->l('Average number of page visits:')
 * $this->l('Average number of purchases:')
 * $this->l('Average price (base price):')
 * $this->l('Average price')
 * $this->l('Average')
 * $this->l('Bad')
 * $this->l('Best categories')
 * $this->l('Best customers')
 * $this->l('Best manufacturers')
 * $this->l('Best suppliers')
 * $this->l('Best vouchers')
 * $this->l('Best-selling products')
 * $this->l('Both')
 * $this->l('Both:')
 * $this->l('Bought items')
 * $this->l('Browsers and operating systems')
 * $this->l('CSV Export')
 * $this->l('Cannot find any keywords that have been searched for more than once.')
 * $this->l('Carrier distribution')
 * $this->l('Carts')
 * $this->l('Catalog evaluation')
 * $this->l('Catalog statistics')
 * $this->l('Category distribution')
 * $this->l('Category')
 * $this->l('Choose a category')
 * $this->l('Click on a product to access its statistics!')
 * $this->l('Code')
 * $this->l('Configuration updated')
 * $this->l('Contacting a group of clients by email or newsletter.')
 * $this->l('Conversion rate')
 * $this->l('Conversion rate*:')
 * $this->l('Conversion')
 * $this->l('Cost')
 * $this->l('Counter')
 * $this->l('Country distribution allows you to analyze which part of the World your customers are shopping from.')
 * $this->l('Country distribution')
 * $this->l('Cross selling')
 * $this->l('Currency distribution')
 * $this->l('Currency range allows you to determine which currency your customers are using.')
 * $this->l('Currency')
 * $this->l('Current online customers')
 * $this->l('Current online visitors')
 * $this->l('Current page')
 * $this->l('Customer ID')
 * $this->l('Customer accounts')
 * $this->l('Customer registrations:')
 * $this->l('Customer')
 * $this->l('Daily')
 * $this->l('Date')
 * $this->l('Defines the average conversion rate for the product page. It is possible to purchase a product without viewing the product page, so this rate can be greater than 1.')
 * $this->l('Defining your target audience is essential when choosing the right tools to win them over.')
 * $this->l('Desc.')
 * $this->l('Descriptions')
 * $this->l('Design and user-friendliness are more important than ever in the world of online sales. An ill-chosen or hard-to-follow graphical theme can keep shoppers at bay. This means that you should aspire to find the right balance between beauty and functionality for your online store.')
 * $this->l('Details')
 * $this->l('Determine the interest of a visit')
 * $this->l('Develop clients\' loyalty')
 * $this->l('Direct link')
 * $this->l('Direct links only')
 * $this->l('Discount')
 * $this->l('Display final level categories only (that have no child categories)')
 * $this->l('Displaying %1$s of %2$s')
 * $this->l('Edit / View')
 * $this->l('Edit')
 * $this->l('Email')
 * $this->l('Empty ALL "pages not found" notices for this period')
 * $this->l('Empty ALL "pages not found" notices')
 * $this->l('Empty database')
 * $this->l('Empty record set returned')
 * $this->l('Empty recordset returned')
 * $this->l('Empty recordset returned.')
 * $this->l('Evaluation of available quantities for sale')
 * $this->l('Female')
 * $this->l('Filter by keyword')
 * $this->l('Filter')
 * $this->l('First Name')
 * $this->l('Forecast')
 * $this->l('Full carts')
 * $this->l('Gender distribution allows you to determine the percentage of men and women shoppers on your store.')
 * $this->l('Gender distribution')
 * $this->l('Global')
 * $this->l('Good')
 * $this->l('Greater than')
 * $this->l('Group')
 * $this->l('Growth')
 * $this->l('Guest ID')
 * $this->l('Guide')
 * $this->l('Here is a summary of what may affect the creation of customer accounts:')
 * $this->l('How does it work?')
 * $this->l('How to act on the registrations\' evolution?')
 * $this->l('How to catch these errors?')
 * $this->l('ID')
 * $this->l('IMPORTANT NOTE: in September 2013, Google chose to encrypt its searches queries using SSL. This means all the referer-based tools in the World (including this one) cannot identify Google keywords anymore.')
 * $this->l('IP')
 * $this->l('Identify external search engine keywords')
 * $this->l('Identifying the most popular keywords entered by your new visitors allows you to see the products you should put in front if you want to achieve better visibility in search engines.')
 * $this->l('If this is the case, congratulations, your website is well planned and pleasing. Glad to see that you\'ve been paying attention.')
 * $this->l('If you let your shop run without changing anything, the number of customer registrations should stay stable or show a slight decline.')
 * $this->l('If you notice that a product is often purchased but viewed infrequently, you should display it more prominently in your Front Office.')
 * $this->l('If your webhost supports .htaccess files, you can create one in the root directory of PrestaShop and insert the following line inside: "%s".')
 * $this->l('Images available:')
 * $this->l('Images')
 * $this->l('In order for each message to have an impact, you need to know who it is being addressed to. ')
 * $this->l('In order to achieve this goal, you can organize:')
 * $this->l('In the tab, we break down the 10 most popular referral websites that bring customers to your online store.')
 * $this->l('In your Back Office, you can modify the following order statuses: Awaiting Check Payment, Payment Accepted, Preparation in Progress, Shipping, Delivered, Canceled, Refund, Payment Error, Out of Stock, and Awaiting Bank Wire Payment.')
 * $this->l('Indicates the percentage of each operating system used by customers.')
 * $this->l('Indicates the percentage of each web browser used by customers.')
 * $this->l('Invoice Date')
 * $this->l('Invoice Number')
 * $this->l('It is best to limit an action to a group -- or to groups -- of clients.')
 * $this->l('Item')
 * $this->l('Items in pack')
 * $this->l('Items total')
 * $this->l('Keeping a client can be more profitable than gaining a new one. That is one of the many reasons it is necessary to cultivate customer loyalty.')
 * $this->l('Keywords')
 * $this->l('Language distribution allows you to analyze the browsing language used by your customers.')
 * $this->l('Language distribution')
 * $this->l('Language')
 * $this->l('Last Name')
 * $this->l('Last activity')
 * $this->l('Launching targeted advertisement campaigns.')
 * $this->l('Less than')
 * $this->l('Maintenance IPs are excluded from the online visitors.')
 * $this->l('Making sure that your website is accessible to as many people as possible')
 * $this->l('Male')
 * $this->l('Members per group')
 * $this->l('Module')
 * $this->l('Money spent')
 * $this->l('Monthly')
 * $this->l('Name')
 * $this->l('Newsletter statistics')
 * $this->l('Newsletter')
 * $this->l('No "page not found" issue registered for now.')
 * $this->l('No customers have registered yet.')
 * $this->l('No keywords')
 * $this->l('No orders for this period.')
 * $this->l('No product was found.')
 * $this->l('No valid orders have been received for this period.')
 * $this->l('None')
 * $this->l('Not enough')
 * $this->l('Notice')
 * $this->l('Number of customer accounts created')
 * $this->l('Number of purchases compared to number of views')
 * $this->l('Number of visitors who placed an order directly after registration:')
 * $this->l('Number of visitors who stopped at the registering step:')
 * $this->l('Number of visits and unique visitors')
 * $this->l('Occurrences')
 * $this->l('On average, each registered visitor places an order for this amount:')
 * $this->l('On average, each visitor places an order for this amount:')
 * $this->l('On the other hand, if a product has many views but is not often purchased, we advise you to check or modify this product\'s information, description and photography again, see if you can find something better.')
 * $this->l('Only valid orders are graphically represented.')
 * $this->l('Operating system used')
 * $this->l('Order ID')
 * $this->l('Order by')
 * $this->l('Order')
 * $this->l('Orders Profit')
 * $this->l('Orders placed')
 * $this->l('Orders placed:')
 * $this->l('Orders')
 * $this->l('Origin')
 * $this->l('Others')
 * $this->l('Otherwise, the conclusion is not so simple. The problem can be aesthetic or ergonomic. It is also possible that many visitors have mistakenly visited your URL without possessing a particular interest in your shop. This strange and ever-confusing phenomenon is most likely cause by search engines. If this is the case, you should consider revising your SEO structure.')
 * $this->l('Page views')
 * $this->l('Page')
 * $this->l('Pages not found')
 * $this->l('Paid')
 * $this->l('Payment distribution')
 * $this->l('Percentage of orders listed by carrier.')
 * $this->l('Percentage of orders per status.')
 * $this->l('Percentage of orders')
 * $this->l('Percentage of products sold')
 * $this->l('Percentage of registrations')
 * $this->l('Percentage of sales')
 * $this->l('Percentage')
 * $this->l('Placed orders')
 * $this->l('Plugins')
 * $this->l('Popularity')
 * $this->l('Price sold')
 * $this->l('Price')
 * $this->l('Price*')
 * $this->l('Product details')
 * $this->l('Product name')
 * $this->l('Product pages viewed:')
 * $this->l('Products available')
 * $this->l('Products available:')
 * $this->l('Products bought')
 * $this->l('Products bought:')
 * $this->l('Products never purchased')
 * $this->l('Products never purchased:')
 * $this->l('Products never viewed:')
 * $this->l('Products sold')
 * $this->l('Products:')
 * $this->l('Profit')
 * $this->l('Punctual operations: commercial rewards (personalized special offers, product or service offered), non commercial rewards (priority handling of an order or a product), pecuniary rewards (bonds, discount coupons, payback).')
 * $this->l('Quantity of products sold')
 * $this->l('Quantity sold in a day')
 * $this->l('Quantity sold')
 * $this->l('Quantity')
 * $this->l('Ref.')
 * $this->l('Reference')
 * $this->l('Referrer')
 * $this->l('Registered customer information')
 * $this->l('Registered visitors')
 * $this->l('Registrations')
 * $this->l('Results')
 * $this->l('Revenue')
 * $this->l('Sales (converted)')
 * $this->l('Sales (tax excluded)')
 * $this->l('Sales and orders')
 * $this->l('Sales currency: %s')
 * $this->l('Sales')
 * $this->l('Sales:')
 * $this->l('Save')
 * $this->l('Search engine keywords')
 * $this->l('Shipping')
 * $this->l('Shop search')
 * $this->l('Someone who posts a link to your shop.')
 * $this->l('Specials, sales, promotions and/or contests typically demand a shoppers\' attentions. Offering such things will not only keep your business lively, it will also increase traffic, build customer loyalty and genuinely change your current e-commerce philosophy.')
 * $this->l('Stats Dashboard')
 * $this->l('Stats by Groups')
 * $this->l('Stock')
 * $this->l('Storing registered customer information allows you to accurately define customer profiles so you can adapt your special deals and promotions.')
 * $this->l('Sustainable operations: loyalty points or cards, which not only justify communication between merchant and client, but also offer advantages to clients (private offers, discounts).')
 * $this->l('Target your audience')
 * $this->l('Tax')
 * $this->l('The "' . $this->newsletter_module_human_readable_name . '" module must be installed.')
 * $this->l('The "pages not found" cache has been deleted.')
 * $this->l('The "pages not found" cache has been emptied.')
 * $this->l('The amounts include taxes, so you can get an estimation of the commission due to the payment method.')
 * $this->l('The following graphs represent the evolution of your shop\'s orders and sales turnover for a selected period.')
 * $this->l('The listed amounts do not include tax.')
 * $this->l('The referrer is the URL of the previous webpage from which a link was followed by the visitor.')
 * $this->l('The total number of accounts created is not in itself important information. However, it is beneficial to analyze the number created over time. This will indicate whether or not things are on the right track. You feel me?')
 * $this->l('The visitors\' evolution graph strongly resembles the visits\' graph, but provides additional information:')
 * $this->l('There are no active customers online right now.')
 * $this->l('There are no visitors online.')
 * $this->l('These operations encourage clients to buy products and visit your online store more regularly.')
 * $this->l('These order statuses cannot be removed from the Back Office; however you have the option to add more.')
 * $this->l('This graph represents the carrier distribution for your orders. You can also narrow the focus of the graph to display distribution for a particular order status.')
 * $this->l('This information is mostly qualitative. It is up to you to determine the interest of a disjointed visit.')
 * $this->l('This is one of the most common ways of finding a website through a search engine.')
 * $this->l('This module can recognize all the search engines listed in PrestaShop\'s Stats/Search Engine page -- and you can add more!')
 * $this->l('This section corresponds to the default wholesale price according to the default supplier for the product. An average price is used when the product has attributes.')
 * $this->l('Time frame')
 * $this->l('Top 10 keywords')
 * $this->l('Top ten referral websites')
 * $this->l('Total Margin')
 * $this->l('Total Price')
 * $this->l('Total Quantity Sold')
 * $this->l('Total Viewed')
 * $this->l('Total bought in pack')
 * $this->l('Total bought')
 * $this->l('Total customer accounts:')
 * $this->l('Total paid')
 * $this->l('Total quantities')
 * $this->l('Total used')
 * $this->l('Total value')
 * $this->l('Total viewed')
 * $this->l('Total visitors:')
 * $this->l('Total visits:')
 * $this->l('Total')
 * $this->l('Total:')
 * $this->l('Undefined')
 * $this->l('Unknown')
 * $this->l('Valid orders')
 * $this->l('Value')
 * $this->l('View customer profile')
 * $this->l('View')
 * $this->l('Visitor registrations: ')
 * $this->l('Visitors online')
 * $this->l('Visitors origin')
 * $this->l('Visitors')
 * $this->l('Visits (x100)')
 * $this->l('Visits and Visitors')
 * $this->l('Visits')
 * $this->l('Web browser used')
 * $this->l('Weekly')
 * $this->l('What is a referral website?')
 * $this->l('When a visitor comes to your website, the web server notes the URL of the site he/she comes from. This module then parses the URL, and if it finds a reference to a known search engine, it finds the keywords in it.')
 * $this->l('When managing a website, it is important to keep track of the software used by visitors so as to be sure that the site displays the same way for everyone. PrestaShop was built to be compatible with the most recent Web browsers and computer operating systems (OS). However, because you may end up adding advanced features to your website or even modifying the core PrestaShop code, these additions may not be accessible to everyone. That is why it is a good idea to keep track of the percentage of users for each type of software before adding or changing something that only a limited number of users will be able to access.')
 * $this->l('Word of mouth is also a means for getting new, satisfied clients. A dissatisfied customer can hurt your e-reputation and obstruct future sales goals.')
 * $this->l('Yearly')
 * $this->l('You can increase your sales by:')
 * $this->l('You can view the distribution of order statuses below.')
 * $this->l('You must activate the "Save page views for each customer" option in the "Data mining for statistics" (StatsData) module in order to see the pages that your visitors are currently viewing.')
 * $this->l('You must use a .htaccess file to redirect 404 errors to the "404.php" page.')
 * $this->l('You should often consult this screen, as it allows you to quickly monitor your shop\'s sustainability. It also allows you to monitor multiple time periods.')
 * $this->l('Your catalog is empty.')
 * $this->l('Zone distribution')
 * $this->l('Zone')
 * $this->l('Zone:')
 * $this->l('chars (without HTML)')
 * $this->l('customers')
 * $this->l('images')
 * $this->l('items')
 * $this->l('orders / month')
 */