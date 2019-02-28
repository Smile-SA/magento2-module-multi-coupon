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

namespace Smile\MultiCoupon\Controller\Cart;

use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;

/**
 * Multiple Coupons post handler.
 *
 * @category Smile
 * @package  Smile\MultiCoupon
 * @author   Romain Ruaud <romain.ruaud@smile.fr>
 */
class CouponPost extends \Magento\Checkout\Controller\Cart\CouponPost
{
    /**
     * Initialize coupon
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $couponCode = $this->getRequest ()->getParam ('remove') == 1
            ? ''
            : trim ($this->getRequest ()->getParam ('coupon_code'));

        $cartQuote = $this->cart->getQuote ();
        $oldCouponCode = $cartQuote->getCouponCode ();

        $codeLength = strlen ($couponCode);
        if (!$codeLength && !strlen ($oldCouponCode)) {
            return $this->_goBack ();
        }

        try {
            $isCodeLengthValid = $codeLength && $codeLength <= \Magento\Checkout\Helper\Cart::COUPON_CODE_MAX_LENGTH;

            $itemsCount = $cartQuote->getItemsCount ();
            if ($itemsCount) {
                /** if there is item in the cart*/
                $cartQuote->getShippingAddress ()->setCollectShippingRates (true);

                if ($oldCouponCode) {

                    $couponRemove = $this->getRequest ()->getParam ('removeCouponValue');

                    // split the old coupon into any array
                    $oldCouponArray = explode (',', $oldCouponCode);

                    if ($couponRemove != "") {

                        // remove the coupon if it exist
                        $oldCouponArray = array_diff ($oldCouponArray, array($couponRemove));

                        // change it back to string
                        $oldCouponCode = implode (',', $oldCouponArray);


                        $cartQuote->setCouponCode ($oldCouponCode)->save ();
                    } else {

                        $couponUpdate = $oldCouponCode;

                        // VALIDATE THE COUPON BEFORE SAVING IT
                        $coupon = $this->couponFactory->create ();
                        $coupon->load ($couponCode, 'code');

                        if($coupon->getCode ()) {
                            if (!in_array ($couponCode, $oldCouponArray)) {
                                $couponUpdate = $oldCouponCode . ',' . $couponCode;
                            }
                        }
                        // proceed to save
                        $cartQuote->setCouponCode ($isCodeLengthValid ? $couponUpdate : '')->collectTotals ();
                        $cartQuote->setCouponCode ($couponUpdate)->save ();
                    }
                } else {
                    $cartQuote->setCouponCode ($isCodeLengthValid ? $couponCode : '')->collectTotals ();

                }

                $this->quoteRepository->save ($cartQuote);

            }



            if ($codeLength) {
                $escaper = $this->_objectManager->get (Escaper::class);
                $coupon = $this->couponFactory->create ();
                $coupon->load ($couponCode, 'code');
                if (!$itemsCount) {
                    if ($isCodeLengthValid && $coupon->getId ()) {
                        $this->_checkoutSession->getQuote ()->setCouponCode ($oldCouponCode)->save ();
                        $this->messageManager->addSuccess (
                            __ (
                                'You used coupon code "%1".',
                                $escaper->escapeHtml ($couponCode)
                            )
                        );
                    } else {
                        $this->messageManager->addError (
                            __ (
                                'The coupon code "%1" is not valid.',
                                $escaper->escapeHtml ($couponCode)
                            )
                        );
                    }
                } else {
                    /** split the coupon and get the last one */
                    $cSplit = explode (",", $cartQuote->getCouponCode ());

                    if ($isCodeLengthValid && $coupon->getId () && in_array ($couponCode, $cSplit)) {
                        $this->messageManager->addSuccess (
                            __ (
                                'You used coupon code "%1".',
                                $escaper->escapeHtml ($couponCode)
                            )
                        );
                    } else {
                        $this->messageManager->addError (
                            __ (
                                'The coupon code "%1" is not valid.',
                                $escaper->escapeHtml ($couponCode)
                            )
                        );
                    }
                }
            } else {
                $this->messageManager->addSuccess (__ ('You canceled the coupon code.'));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addError ($e->getMessage ());
        } catch (\Exception $e) {
            $this->messageManager->addError (__ ('We cannot apply the coupon code.'));
            $this->_objectManager->get (\Psr\Log\LoggerInterface::class)->critical ($e);
        }

        return $this->_goBack ();
    }
}
