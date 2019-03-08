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

namespace Smile\MultiCoupon\Model\Quote;

/**
 * Multiple Coupon total collector.
 *
 * @category Smile
 * @package  Smile\MultiCoupon
 * @author   Romain Ruaud <romain.ruaud@smile.fr>
 */
class Discount extends \Magento\SalesRule\Model\Quote\Discount
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) inherited
     * @SuppressWarnings(PHPMD.ElseExpression) inherited
     */
    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        $store = $this->storeManager->getStore($quote->getStoreId());
        $address = $shippingAssignment->getShipping()->getAddress();
        $this->calculator->reset($address);

        $items = $shippingAssignment->getItems();
        if (!count($items)) {
            return $this;
        }

        // Extract potential multiple coupons.
        $multipleCoupons = array_unique(explode(',', $quote->getCouponCode()));

        // Loop on each coupon and apply it. Code inside the loop is a copy-paste of the parent::collect().
        foreach ($multipleCoupons as $couponCodeValue) {
            $eventArgs = [
                'website_id' => $store->getWebsiteId(),
                'customer_group_id' => $quote->getCustomerGroupId(),
                'coupon_code' => $couponCodeValue,
            ];

            $this->calculator->init($store->getWebsiteId(), $quote->getCustomerGroupId(), $couponCodeValue);
            $this->calculator->initTotals($items, $address);

            $address->setDiscountDescription([]);
            $items = $this->calculator->sortItemsByPriority($items, $address);

            /** @var \Magento\Quote\Model\Quote\Item $item */
            foreach ($items as $item) {
                if ($item->getNoDiscount() || !$this->calculator->canApplyDiscount($item)) {
                    $item->setDiscountAmount(0);
                    $item->setBaseDiscountAmount(0);
                    // Ensure my children are zeroed out.
                    if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                        foreach ($item->getChildren() as $child) {
                            $child->setDiscountAmount(0);
                            $child->setBaseDiscountAmount(0);
                        }
                    }
                    continue;
                }
                // To determine the child item discount, we calculate the parent.
                if ($item->getParentItem()) {
                    continue;
                }
                $eventArgs['item'] = $item;
                $this->eventManager->dispatch('sales_quote_address_discount_item', $eventArgs);
                if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                    $this->calculator->process($item);
                    $this->distributeDiscount($item);
                    foreach ($item->getChildren() as $child) {
                        $eventArgs['item'] = $child;
                        $this->eventManager->dispatch('sales_quote_address_discount_item', $eventArgs);
                        $this->aggregateItemDiscount($child, $total);
                    }
                } else {
                    $this->calculator->process($item);
                    $this->aggregateItemDiscount($item, $total);
                }
            }

            /** Process shipping amount discount */
            $address->setShippingDiscountAmount(0);
            $address->setBaseShippingDiscountAmount(0);
            if ($address->getShippingAmount()) {
                $this->calculator->processShippingAmount($address);
                $total->addTotalAmount($this->getCode(), -$address->getShippingDiscountAmount());
                $total->addBaseTotalAmount($this->getCode(), -$address->getBaseShippingDiscountAmount());
                $total->setShippingDiscountAmount($address->getShippingDiscountAmount());
                $total->setBaseShippingDiscountAmount($address->getBaseShippingDiscountAmount());
            }
            $this->calculator->prepareDescription($address);
            $total->setDiscountDescription($address->getDiscountDescription());
            $total->setSubtotalWithDiscount($total->getSubtotal() + $total->getDiscountAmount());
            $total->setBaseSubtotalWithDiscount($total->getBaseSubtotal() + $total->getBaseDiscountAmount());
        }

        return $this;
    }
}
