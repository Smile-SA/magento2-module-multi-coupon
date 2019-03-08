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
     * @SuppressWarnings(PHPMD.ElseExpression)
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $couponCode    = $this->getRequest()->getParam('remove') == 1 ? '' : trim($this->getRequest()->getParam('coupon_code'));
        $cartQuote     = $this->cart->getQuote();
        $oldCouponCode = $cartQuote->getCouponCode();

        $codeLength = strlen($couponCode);
        if (!$codeLength && !strlen($oldCouponCode)) {
            return $this->_goBack();
        }

        try {
            $isCodeLengthValid = $codeLength && $codeLength <= \Magento\Checkout\Helper\Cart::COUPON_CODE_MAX_LENGTH;
            $itemsCount        = $cartQuote->getItemsCount();
            if ($itemsCount) {
                $cartQuote->getShippingAddress()->setCollectShippingRates(true);

                if ($oldCouponCode) {
                    $couponRemove   = $this->getRequest()->getParam('removeCouponValue');
                    $oldCouponArray = explode(',', $oldCouponCode);

                    if ($couponRemove != "") {
                        $oldCouponCode = implode(',', array_diff($oldCouponArray, [$couponRemove]));
                        $cartQuote->setCouponCode($oldCouponCode)->collectTotals();
                    } else {
                        $couponUpdate = $oldCouponCode;
                        $coupon       = $this->couponFactory->create();
                        $coupon->load($couponCode, 'code');

                        if ($coupon->getCode()) {
                            if (!in_array($couponCode, $oldCouponArray)) {
                                $couponUpdate = $oldCouponCode . ',' . $couponCode;
                            }
                        }
                        $cartQuote->setCouponCode($isCodeLengthValid ? $couponUpdate : '')->collectTotals();
                    }
                } else {
                    $cartQuote->setCouponCode($isCodeLengthValid ? $couponCode : '')->collectTotals();
                }
                $this->quoteRepository->save($cartQuote);
            }

            if ($codeLength) {
                $escaper = $this->_objectManager->get(Escaper::class);
                $coupon  = $this->couponFactory->create();
                $coupon->load($couponCode, 'code');
                if (!$itemsCount) {
                    if ($isCodeLengthValid && $coupon->getId()) {
                        $this->_checkoutSession->getQuote()->setCouponCode($oldCouponCode)->save();
                        $this->messageManager->addSuccessMessage(
                            __(
                                'You used coupon code "%1".',
                                $escaper->escapeHtml($couponCode)
                            )
                        );
                    } else {
                        $this->messageManager->addErrorMessage(
                            __(
                                'The coupon code "%1" is not valid.',
                                $escaper->escapeHtml($couponCode)
                            )
                        );
                    }
                } else {
                    if ($isCodeLengthValid && $coupon->getId() && in_array($couponCode, explode(",", $cartQuote->getCouponCode()))) {
                        $this->messageManager->addSuccessMessage(
                            __(
                                'You used coupon code "%1".',
                                $escaper->escapeHtml($couponCode)
                            )
                        );
                    } else {
                        $this->messageManager->addError(
                            __(
                                'The coupon code "%1" is not valid.',
                                $escaper->escapeHtml($couponCode)
                            )
                        );
                    }
                }
            } else {
                $this->messageManager->addSuccessMessage(__('You canceled the coupon code.'));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('We cannot apply the coupon code.'));
            $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
        }

        return $this->_goBack();
    }
}
