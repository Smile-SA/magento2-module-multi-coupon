<?php
/**
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\MultiCoupon
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2019 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\MultiCoupon\Block\Cart;

/**
 * Multiple Coupon cart block.
 *
 * @category Smile
 * @package  Smile\MultiCoupon
 * @author   Romain Ruaud <romain.ruaud@smile.fr>
 */
class Coupon extends \Magento\Checkout\Block\Cart\AbstractCart
{
    /**
     * @return array
     */
    public function getCouponCodes()
    {
        return array_filter(explode(",", $this->getQuote()->getCouponCode()));
    }
}
