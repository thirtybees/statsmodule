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
