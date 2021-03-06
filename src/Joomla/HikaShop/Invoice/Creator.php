<?php
namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use hikashopConfigClass;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use stdClass;

/**
 * Allows to create arrays in the Acumulus invoice structure from a HikaShop
 * order
 *
 * Notes:
 * - HikaShop knows discounts in the form of coupons or unrestricted discounts.
 *   Coupons can be without vat (to be seen as partial payment, which was
 *   probably not meant, thus incorrect) or with a fixed vat rate, independent
 *   from the products in the cart, thus also incorrect.
 * - When a cart with a coupon contains products with another vat rate, the
 *   shown vat amount breakdown is incorrect. The Acumulus invoice will be
 *   correct, but may differ from the shop invoice, though the overall amount
 *   tends to be equal. It is the meta data in the invoice (as sent to Acumulus)
 *   that shows the differences.
 */
class Creator extends BaseCreator
{
    /**
     * @var object
     */
    protected $order;

    /**
     * Product price precision in WC3: one of the prices is entered by the
     * administrator but rounded to the cent by WC. The computed one is based
     * on the subtraction/addition of 2 amounts rounded to the cent, so has a
     * precision that may be a bit worse than 1 cent.
     *
     * values here.
     *
     * @var float
     */
    protected $precisionPriceEntered  = 0.0001;
    protected $precisionPriceCalculated  = 0.0002;
    protected $precisionVat  = 0.0011;

    /**
     * {@inheritdoc}
     *
     * This override also initializes HS specific properties related to the
     * source.
     */
    protected function setInvoiceSource($source)
    {
        parent::setInvoiceSource($source);
        $this->order = $this->invoiceSource->getSource();
    }

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();
        if (!empty($this->order->billing_address)) {
            $this->propertySources['billing_address'] = $this->order->billing_address;
        }
        if (!empty($this->order->shipping_address)) {
            $this->propertySources['shipping_address'] = $this->order->shipping_address;
        }
        $this->propertySources['customer'] = $this->order->customer;
    }

    /**
     * {@inheritdoc}
     */
    protected function getCountryCode()
    {
        return !empty($this->order->billing_address->address_country_code_2) ? $this->order->billing_address->address_country_code_2 : '';
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the name of the payment module.
     */
    protected function getPaymentMethod()
    {
        if (isset($this->order->order_payment_id)) {
            return $this->order->order_payment_id;
        }
        return parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentState()
    {
        /** @var hikashopConfigClass $config */
        $config = hikashop_config();
        $unpaidStatuses = explode(',', $config->get('order_unpaid_statuses', 'created'));
        return in_array($this->order->order_status, $unpaidStatuses)
            ? Api::PaymentStatus_Due
            : Api::PaymentStatus_Paid;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentDate()
    {
        // Scan through the history and look for a non empty history_payment_id.
        // The order of this array is by history_created DESC, we take the one that
        // is furthest away in time.
        $date = null;
        foreach ($this->order->history as $history) {
            if (!empty($history->history_payment_id)) {
                $date = $history->history_created;
            }
        }
        if (!$date) {
            // Scan through the history and look for a non unpaid order status.
            // We take the one that is furthest away in time.
            /** @var hikashopConfigClass $config */
            $config = hikashop_config();
            $unpaidStatuses = explode(',', $config->get('order_unpaid_statuses', 'created'));
            foreach ($this->order->history as $history) {
                if (!empty($history->history_new_status) && !in_array($history->history_new_status, $unpaidStatuses)) {
                    $date = $history->history_created;
                }
            }
        }
        return $date ? date('Y-m-d', $date) : $date;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount.
     */
    protected function getInvoiceTotals()
    {
        $vatAmount = 0.0;
        // No order_taxinfo => no tax (?) => vatamount = 0.
        if (!empty($this->order->order_tax_info)) {
            foreach ($this->order->order_tax_info as $taxInfo) {
                $vatAmount += $taxInfo->tax_amount;
            }
        }
        return array(
            Meta::InvoiceAmountInc => $this->order->order_full_price,
            Meta::InvoiceVatAmount => $vatAmount,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        $result = array_map(array($this, 'getItemLine'), $this->order->products);
        return $result;
    }

    /**
     * Returns 1 item line for 1 product line.
     *
     * @param stdClass $item
     *
     * @return array
     */
    protected function getItemLine(stdClass $item)
    {
        $result = array();
        $this->addPropertySource('item', $item);
        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($result, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($result, Tag::Product, $invoiceSettings['productName']);
        // Remove html with variant info from product name, we'll add that later
        // using children lines.
        if (isset($result[Tag::Product]) && ($pos = strpos($result[Tag::Product], '<span')) !== false) {
            $result[Tag::Product] = substr($result[Tag::Product], 0, $pos);
        }
        $this->addTokenDefault($result,Tag::Nature, $invoiceSettings['nature']);

        $productPriceEx = (float) $item->order_product_price;
        $productVat = (float) $item->order_product_tax;

        $result += array(
                Tag::UnitPrice => $productPriceEx,
                Meta::LineAmount => $item->order_product_total_price_no_vat,
                Meta::LineAmountInc => $item->order_product_total_price,
                Tag::Quantity => $item->order_product_quantity,
                Meta::VatAmount => $productVat,
            );

        // Note that this info remains correct when rates are changed as upon
        // order creation this info is stored in the order_product table.
        if (is_array($item->order_product_tax_info) && count($item->order_product_tax_info) === 1) {
            $productVatInfo = reset($item->order_product_tax_info);
            if (!empty($productVatInfo->tax_rate)) {
                $vatRate = $productVatInfo->tax_rate;
            }
        }
        if (isset($vatRate)) {
            $vatInfo = array(
                Tag::VatRate => 100.0 * $vatRate,
                Meta::VatRateSource => static::VatRateSource_Exact,
            );
        } else {
            $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx, 0.0001, 0.0001);
        }
        $result += $vatInfo;

        // Add variant info.
        if (!empty($item->order_product_options)) {
            $children = $this->getVariantLines($item, $result[Tag::Quantity], $vatInfo);
            if (!empty($children)) {
                $result[Meta::ChildrenLines] = $children;
            }
        }

        $this->removePropertySource('item');

        return $result;
    }

    /**
     * Returns an array of lines that describes this variant.
     *
     * @param stdClass $item
     * @param int $parentQuantity
     * @param array $vatRangeTags
     *
     * @return array[]
     *   An array of lines that describes this variant.
     */
    protected function getVariantLines(stdClass $item, $parentQuantity, $vatRangeTags)
    {
        $result = array();

        foreach ($item->order_product_options as $key => $value) {
            // Skip numeric keys that have a StdClass as value.
            if (!is_numeric($key) && is_string($value)) {
                // Add variant.
                $result[] = array(
                    Tag::Product => $key . ': ' . $value,
                    Tag::UnitPrice => 0,
                    Tag::Quantity => $parentQuantity,
                ) + $vatRangeTags;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        $result = array();
        // Check if there is a shipping id attached to the order.
        if (!empty($this->order->order_shipping_id)) {
            // Check for free shipping on a credit note.
            if (!Number::isZero($this->order->order_shipping_price) || $this->invoiceSource->getType() !== Source::CreditNote) {
                $shippingInc = (float) $this->order->order_shipping_price;
                $shippingVat = (float) $this->order->order_shipping_tax;
                $shippingEx = $shippingInc - $shippingVat;
                $precisionEx = $this->precisionPriceCalculated;
                $precisionInc = $this->precisionPriceEntered;
                $recalculateUnitPrice = true;
                $vatInfo = $this->getVatRangeTags($shippingVat, $shippingEx, $this->precisionVat, $precisionEx);

                $result = array(
                        Tag::Product => $this->getShippingMethodName(),
                        Tag::Quantity => 1,
                        Tag::UnitPrice => $shippingEx,
                        Meta::PrecisionUnitPrice => $precisionEx,
                        Meta::UnitPriceInc => $shippingInc,
                        Meta::PrecisionUnitPriceInc => $precisionInc,
                        Meta::RecalculateUnitPrice => $recalculateUnitPrice,
                        Meta::VatAmount => $shippingVat,
                    ) + $vatInfo;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingMethodName()
    {
        /** @var \hikashopShippingClass $class */
        $class = hikashop_get('class.shipping');
        $shipping = $class->get($this->order->order_shipping_id);
        if (!empty($shipping->shipping_name)) {
            return $shipping->shipping_name;
        }
        return parent::getShippingMethodName();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDiscountLines()
    {
        $result = array();

        if (!Number::isZero($this->order->order_discount_price)) {
            $discountInc = (float) $this->order->order_discount_price;
            $discountVat = (float) $this->order->order_discount_tax;
            $discountEx = $discountInc - $discountVat;
            $vatInfo = $this->getVatRangeTags($discountVat, $discountEx, 0.0001, 0.0002);
            if ($vatInfo[Tag::VatRate] === null) {
                $vatInfo[Meta::StrategySplit] = true;
            }
            $description = empty($this->order->order_discount_code)
                ? $this->t('discount')
                : $this->t('discount_code') . ' ' . $this->order->order_discount_code;

            $result[] = array(
                    Tag::Product => $description,
                    Meta::UnitPriceInc => -$discountInc,
                    Tag::Quantity => 1,
                    Meta::VatAmount => -$discountVat,
                ) + $vatInfo;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentFeeLine()
    {
        // @todo check (return on refund?)
        $result = array();
        if (!Number::isZero($this->order->order_payment_price)) {
            $paymentInc = (float) $this->order->order_payment_price;
            $paymentVat = (float) $this->order->order_payment_tax;
            $paymentEx = $paymentInc - $paymentVat;
            $precisionEx = $this->precisionPriceCalculated;
            $precisionInc = $this->precisionPriceEntered;
            $recalculateUnitPrice = true;
            $vatInfo = $this->getVatRangeTags($paymentVat, $paymentEx, 0.0001, 0.0002);
            $description = $this->t('payment_costs');

            $result = array(
                    Tag::Product => $description,
                    Tag::Quantity => 1,
                    Tag::UnitPrice => $paymentEx,
                    Meta::PrecisionUnitPrice => $precisionEx,
                    Meta::UnitPriceInc => $paymentInc,
                    Meta::PrecisionUnitPriceInc => $precisionInc,
                    Meta::RecalculateUnitPrice => $recalculateUnitPrice,
                    Meta::VatAmount => $paymentVat,
                ) + $vatInfo;
        }
        return $result;
    }
}
