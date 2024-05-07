<?php
/**
 * Copyright (C) 2017-2024 thirty bees
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
 * @copyright 2017-2024 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */
namespace Thirtybees\StatsModule;

use DbQuery;
use PrestaShopException;
use Shop;

class ProductSalesView
{
    /**
     * @var array
     */
    private $orderConditions = [];

    /**
     * @param string $dateBetween
     */
    public function __construct(string $dateBetween)
    {
        $this->orderConditions['valid'] = '= 1';
        $this->orderConditions['invoice_date'] = 'BETWEEN ' . $dateBetween;
    }


    /**
     * returns sales view is a table
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    public function getAsTable()
    {
        $details = (new DbQuery())
            ->select('0 as pack_item')
            ->select('psv_od1.id_order as id_order')
            ->select('psv_od1.product_id as id_product')
            ->select('psv_od1.product_attribute_id as id_product_attribute')
            ->select('psv_od1.product_quantity as quantity')
            ->select('(psv_od1.product_price / psv_o1.conversion_rate) as price')
            ->from('order_detail', 'psv_od1')
            ->innerJoin('orders', 'psv_o1', 'psv_od1.id_order = psv_o1.id_order')
            ->addCurrentShopRestriction('psv_o1', false);
        foreach ($this->orderConditions as $orderColumn => $condition) {
            $details->where('psv_o1.`' . $orderColumn.'` ' . $condition);
        }

        // TODO: we could spread pack total sales price across pack items (maybe using wholesale price ratio)
        $pack = (new DbQuery())
            ->select('1')
            ->select('psv_od2.id_order')
            ->select('psv_odp.id_product')
            ->select('psv_odp.id_product_attribute')
            ->select('(psv_odp.quantity * psv_od2.product_quantity) as quantity')
            ->select('0.0')
            ->from('order_detail_pack', 'psv_odp')
            ->innerJoin('order_detail', 'psv_od2', 'psv_odp.id_order_detail = psv_od2.id_order_detail')
            ->innerJoin('orders', 'psv_o2', 'psv_od2.id_order = psv_o2.id_order')
            ->addCurrentShopRestriction('psv_o2', false);
        foreach ($this->orderConditions as $orderColumn => $condition) {
            $pack->where('psv_o2.`' . $orderColumn.'` ' . $condition);
        }


        return "($details UNION ALL $pack)";
    }
}