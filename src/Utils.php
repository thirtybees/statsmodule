<?php
/**
 * Copyright (C) 2023-2023 thirty bees
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
 * @copyright 2023-2023 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */
namespace Thirtybees\StatsModule;

use Configuration;
use Context;
use Db;
use DbQuery;
use PrestaShopException;
use Shop;
use StatsModule;

class Utils
{
    const CONFIG_CAT_NAME_WITH_PATH = 'STATSMODULE_CAT_NAME_WITH_PATH';
    /**
     * @var StatsModule $module
     */
    protected $module;

    /**
     * @param StatsModule $module
     */
    public function __construct(StatsModule $module)
    {
        $this->module = $module;
    }

    /**
     * @param bool $includeRoot
     *
     * @return string[]
     *
     * @throws PrestaShopException
     */
    public function getCategoryFilterList($includeRoot = false)
    {
        $langId = (int)Context::getContext()->language->id;

        if ($this->categoryNameIncludesPath()) {
            $nameSubselect = (new DbQuery())
                ->select('GROUP_CONCAT(cl.name ORDER BY c.nleft SEPARATOR " > ")')
                ->from('category', 'c')
                ->innerJoin('category_lang', 'cl', 'cl.id_category=c.id_category AND cl.id_lang=' . $langId . Shop::addSqlRestrictionOnLang('cl'))
                ->where('c.nleft <= ca.nleft')
                ->where('c.nright >= ca.nright');
            if (!$includeRoot) {
                $nameSubselect->where('c.id_parent');
            }
        } else {
            $nameSubselect = (new DbQuery())
                ->select('cl.name')
                ->from('category_lang', 'cl')
                ->where('cl.id_category = ca.id_category')
                ->where('cl.id_lang = '.$langId)
                ->where('cl.id_shop = cas.id_shop');
        }

        $sql = (new DbQuery())
            ->select('ca.id_category')
            ->select("($nameSubselect) AS name")
            ->from('category', 'ca')
            ->innerJoin('category_shop', 'cas', 'cas.id_category = ca.id_category')
            ->addCurrentShopRestriction('cas')
            ->where('ca.active');
        if (! $includeRoot) {
            $sql->where('ca.id_parent');
        }

        $categories = Db::getInstance()->executeS($sql);
        usort($categories, function($a, $b) {
            return strcmp(mb_strtolower($a['name']), mb_strtolower($b['name']));
        });
        $ret = [];
        foreach ($categories as $cat) {
            $categoryId = (int)$cat['id_category'];
            $ret[$categoryId] = $cat['name'];
        }
        return $ret;
    }

    /**
     * @param int $selectedCategory
     * @param bool $includeAllOption
     * @param bool $includeRoot
     *
     * @return string
     * @throws PrestaShopException
     */
    public function getCategoryOptions($selectedCategory = 0, $includeAllOption = true, $includeRoot = false)
    {
        $categories = static::getCategoryFilterList($includeRoot);
        $categoriesOptions = '';
        if ($includeAllOption) {
            $categoriesOptions .= '<option value="0">' . $this->l('All') . '</option>';
        }
        foreach ($categories as $categoryId => $category) {
            $isSelected = (int)$selectedCategory === (int)$categoryId;
            $categoriesOptions .= '<option value="' . $categoryId . '"' . ($isSelected ? ' selected="selected"' : '') . '>' . $category . '</option>';
        }
        return $categoriesOptions;
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function categoryNameIncludesPath()
    {
        return (bool)Configuration::get(static::CONFIG_CAT_NAME_WITH_PATH);
    }

    /**
     * Returns true, if shop collects pageview data
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function trackingPageViews()
    {
        return (bool) Configuration::get('PS_STATSDATA_PAGESVIEWS');
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public function l($str)
    {
        return $this->module->l($str);
    }
}