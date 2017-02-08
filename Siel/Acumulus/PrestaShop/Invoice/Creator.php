<?php
namespace Siel\Acumulus\PrestaShop\Invoice;

use Address;
use Carrier;
use Configuration;
use Country;
use Customer;
use Order;
use OrderPayment;
use OrderSlip;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use TaxManagerFactory;

/**
 * Allows to create arrays in the Acumulus invoice structure from a PrestaShop
 * order or order slip.
 *
 * Notes:
 * - If needed, PrestaShop allows us to get tax rates by querying the tax table
 *   because as soon as an existing tax rate gets updated it will get a new id,
 *   so old order details still point to a tax record with the tax rate as was
 *   used at the moment the order was placed.
 * - Fixed in 1.6.1.1: bug in partial refund, not executed the hook
 *   actionOrderSlipAdd #PSCSX-6287. So before 1.6.1.1, partial refunds will not
 *   be automatically sent to Acumulus.
 * - Credit notes can get a correction line. They get one if the total amount
 *   does not match the sum of the lines added so far. This can happen if an
 *   amount was entered manually, or if discount(s) applied during the sale were
 *   subtracted from the credit amount but we could not find which discounts
 *   this were. However:
 *   - amount is excl vat if not manually entered.
 *   - amount is incl vat if manually entered (assuming administrators enter
 *     amounts incl tax, and this is what gets listed on the credit PDF.
 *   - shipping_cost_amount is excl vat.
 *   So this is never going  to work in all situations!!!
 *
 * @todo: So, can we get a tax amount/rate over the manually entered refund?
 */
class Creator extends BaseCreator
{
    /** @var Order|OrderSlip The order or refund that is sent to Acumulus. */
    protected $shopSource;

    /** @var Order */
    protected $order;

    /** @var OrderSlip */
    protected $creditSlip;

    /**
     * {@inheritdoc}
     *
     * This override also initializes WooCommerce specific properties related to
     * the source.
     */
    protected function setInvoiceSource($invoiceSource)
    {
        parent::setInvoiceSource($invoiceSource);
        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->invoiceSource->getSource();
                break;
            case Source::CreditNote:
                $this->creditSlip = $this->invoiceSource->getSource();
                $this->order = new Order($this->creditSlip->id_order);
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();
        $this->propertySources['address_invoice'] = new Address($this->order->id_address_invoice);
        $this->propertySources['address_delivery'] = new Address($this->order->id_address_delivery);
        $this->propertySources['customer'] = new Customer($this->invoiceSource->getSource()->id_customer);
    }

    /**
     * {@inheritdoc}
     */
    protected function getCountryCode()
    {
        $invoiceAddress = new Address($this->order->id_address_invoice);
        return !empty($invoiceAddress->id_country) ? Country::getIsoById($invoiceAddress->id_country) : '';
    }

    /**
     * {@inheritdoc}
     */
    protected function getInvoiceNumber($invoiceNumberSource)
    {
        $result = $this->invoiceSource->getReference();
        if ($invoiceNumberSource === ConfigInterface::InvoiceNrSource_ShopInvoice && $this->invoiceSource->getType() === Source::Order && !empty($this->order->invoice_number)) {
            $result = Configuration::get('PS_INVOICE_PREFIX', (int) $this->order->id_lang, null, $this->order->id_shop) . sprintf('%06d', $this->order->invoice_number);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInvoiceDate($dateToUse)
    {
        $result = substr($this->invoiceSource->getSource()->date_add, 0, strlen('2000-01-01'));
        // Invoice_date is filled with "0000-00-00 00:00:00", so use invoice
        // number instead to check for existence of the invoice.
        if ($dateToUse == ConfigInterface::InvoiceDate_InvoiceCreate && $this->invoiceSource->getType() === Source::Order && !empty($this->order->invoice_number)) {
            $result = substr($this->order->invoice_date, 0, strlen('2000-01-01'));
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the name of the payment module.
     */
    protected function getPaymentMethod()
    {
        if (isset($this->order->module)) {
            return $this->order->module;
        }
        return parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentState()
    {
        // Assumption: credit slips are always in a paid state.
        if (($this->invoiceSource->getType() === Source::Order && $this->order->hasBeenPaid()) || $this->invoiceSource->getType() === Source::CreditNote) {
            $result = ConfigInterface::PaymentStatus_Paid;
        } else {
            $result = ConfigInterface::PaymentStatus_Due;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentDate()
    {
        if ($this->invoiceSource->getType() === Source::Order) {
            $paymentDate = null;
            foreach ($this->order->getOrderPaymentCollection() as $payment) {
                /** @var OrderPayment $payment */
                if ($payment->date_add && (!$paymentDate || $payment->date_add > $paymentDate)) {
                    $paymentDate = $payment->date_add;
                }
            }
        } else {
            // Assumption: last modified date is date of actual reimbursement.
            $paymentDate = $this->creditSlip->date_upd;
        }

        $result = $paymentDate ? substr($paymentDate, 0, strlen('2000-01-01')) : null;
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-amount.
     */
    protected function getInvoiceTotals()
    {
        $sign = $this->getSign();
        if ($this->invoiceSource->getType() === Source::Order) {
            $amount = $this->order->getTotalProductsWithoutTaxes()
                + $this->order->total_shipping_tax_excl
                + $this->order->total_wrapping_tax_excl
                - $this->order->total_discounts_tax_excl;
            $amountInc = $this->order->getTotalProductsWithTaxes()
                + $this->order->total_shipping_tax_incl
                + $this->order->total_wrapping_tax_incl
                - $this->order->total_discounts_tax_incl;
        } else {
            // On credit notes, the amount ex VAT will not have been corrected
            // for discounts that are subtracted from the refund. This will be
            // corrected later in getDiscountLinesCreditNote().
            $amount = $this->creditSlip->total_products_tax_excl
                + $this->creditSlip->total_shipping_tax_excl;
            $amountInc = $this->creditSlip->total_products_tax_incl
                + $this->creditSlip->total_shipping_tax_incl;
        }

        return array(
            'meta-invoice-amountinc' => $sign * $amountInc,
            'meta-invoice-amount' => $sign * $amount,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        $result = array();
        if ($this->invoiceSource->getType() === Source::Order) {
            // Note: getOrderDetailTaxes() is new in 1.6.1.0.
            $lines = method_exists($this->order, 'getOrderDetailTaxes')
                ? $this->mergeProductLines($this->order->getProductsDetail(), $this->order->getOrderDetailTaxes())
                : $this->order->getProductsDetail();
        } else {
            $lines = $this->creditSlip->getOrdersSlipProducts($this->invoiceSource->getId(), $this->order);
        }

        foreach ($lines as $line) {
            $result[] = $this->getItemLine($line);
        }
        return $result;
    }

    /**
     * Merges the product and tax details arrays.
     *
     * @param array $productLines
     * @param array $taxLines
     *
     * @return array
     */
    public function mergeProductLines(array $productLines, array $taxLines)
    {
        $result = array();
        // Key the product lines on id_order_detail, so we can easily add the
        // tax lines in the 2nd loop.
        foreach ($productLines as $productLine) {
            $result[$productLine['id_order_detail']] = $productLine;
        }
        // Add the tax lines without overwriting existing entries (though in a
        // consistent db the same keys should contain the same values).
        foreach ($taxLines as $taxLine) {
            $result[$taxLine['id_order_detail']] += $taxLine;
        }
        return $result;
    }

    /**
     * Returns 1 item line, both for an order or credit slip.
     *
     * @param array $item
     *   An array of an OrderDetail line combined with a tax detail line OR
     *   an array with an OrderSlipDetail line.
     *
     * @return array
     */
    protected function getItemLine(array $item)
    {
        $result = array();
        $sign = $this->getSign();

        $this->addIfNotEmpty($result, 'itemnumber', $item['product_upc']);
        $this->addIfNotEmpty($result, 'itemnumber', $item['product_ean13']);
        $this->addIfNotEmpty($result, 'itemnumber', $item['product_supplier_reference']);
        $this->addIfNotEmpty($result, 'itemnumber', $item['product_reference']);
        $result['product'] = $item['product_name'];

        // Prestashop does not support the margin scheme. So in a standard
        // install this method will always return false. But if this method
        // happens to return true anyway (customisation, hook), the costprice
        // will trigger vattype = 5 for Acumulus.
        if ($this->allowMarginScheme() && !empty($item['purchase_supplier_price'])) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as unitprice.
            // - But still send the VAT rate to Acumulus.
            $result['unitprice'] = $sign * $item['unit_price_tax_incl'];
            // Costprice > 0 triggers the margin scheme in Acumulus.
            $result['costprice'] = $sign * $item['purchase_supplier_price'];
        } else {
            // Unit price is without VAT: use product_price.
            $result['unitprice'] = $sign * $item['unit_price_tax_excl'];
            $result['unitpriceinc'] = $sign * $item['unit_price_tax_incl'];
            $result['meta-line-price'] = $sign * $item['total_price_tax_excl'];
            $result['meta-line-priceinc'] = $sign * $item['total_price_tax_incl'];
        }
        $result['quantity'] = $item['product_quantity'];
        // The field 'rate' comes from order->getOrderDetailTaxes() and is only
        // defined for orders and were not filled in before PS1.6.1.1. So, check
        // if the field is available.
        // The fields 'unit_amount' and 'total_amount' (table order_detail_tax)
        // are based on the discounted product price and thus cannot be used.
        if (isset($item['rate'])) {
            $result['vatrate'] = $item['rate'];
            $result['meta-vatrate-source'] = Creator::VatRateSource_Exact;
            if (!Number::floatsAreEqual($item['unit_amount'], $result['unitpriceinc'] - $result['unitprice'])) {
                $result['meta-line-discount-vatamount'] = $item['unit_amount'] - ($result['unitpriceinc'] - $result['unitprice']);
            }
        } else {
            // Precision: 1 of the amounts, probably the prince incl tax, is
            // entered by the admin and can thus be considered exact. The other
            // is calculated by the system and not rounded and can thus be
            // considered tohave a precision 0f 0.0001
            $result += $this->getVatRangeTags($sign * ($item['unit_price_tax_incl'] - $item['unit_price_tax_excl']), $sign * $item['unit_price_tax_excl'], 0.0001, 0.0001);
        }
        $result['meta-calculated-fields'][] = 'vatamount';

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        $sign = $this->getSign();
        $carrier = new Carrier($this->order->id_carrier);
        // total_shipping_tax_excl is not very precise (rounded to the cent) and
        // often leads to 1 cent off invoices in Acumulus (assuming that the
        // amount entered is based on a nice rounded amount incl tax. So we
        // recalculate this ourselves.
        $vatRate = $this->order->carrier_tax_rate;
        $shippingInc = $sign * $this->invoiceSource->getSource()->total_shipping_tax_incl;
        $shippingEx = $shippingInc / (100 + $vatRate) * 100;
        $shippingVat = $shippingInc - $shippingEx;

        $result = array(
            'product' => $carrier->name,
            'unitprice' => $shippingInc / (100 + $vatRate) * 100,
            'unitpriceinc' => $shippingInc,
            'quantity' => 1,
            'vatrate' => $vatRate,
            'vatamount' => $shippingVat,
            'meta-vatrate-source' => static::VatRateSource_Exact,
            'meta-calculated-fields' => array('unitprice', 'vatamount'),
        );

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns can return an invoice line for orders. Credit slips
     * cannot have a wrapping line.
     */
    protected function getGiftWrappingLine()
    {
        // total_wrapping_tax_excl is not very precise (rounded to the cent) and
        // can easily lead to 1 cent off invoices in Acumulus (assuming that the
        // amount entered is based on a nice rounded amount incl tax. So we
        // recalculate this ourselves by looking up the tax rate.
        $result = array();

        if ($this->invoiceSource->getType() === Source::Order && $this->order->gift && !Number::isZero($this->order->total_wrapping_tax_incl)) {
            /** @var string[] $metaCalculatedFields */
            $metaCalculatedFields = array();
            $wrappingEx = $this->order->total_wrapping_tax_excl;
            $wrappingExLookedUp =  (float) Configuration::get('PS_GIFT_WRAPPING_PRICE');
            if (Number::floatsAreEqual($wrappingEx, $wrappingExLookedUp, 0.005)) {
                $wrappingEx = $wrappingExLookedUp;
                $metaCalculatedFields[] = 'unitprice';
            }
            $wrappingInc = $this->order->total_wrapping_tax_incl;
            $wrappingVat = $wrappingInc - $wrappingEx;
            $metaCalculatedFields[] = 'vatamount';

            $vatLookupTags = $this->getVatRateLookupMetadata($this->order->id_address_invoice, (int) Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP'));
            $result = array(
                    'product' => $this->t('gift_wrapping'),
                    'unitprice' => $wrappingEx,
                    'unitpriceinc' => $wrappingInc,
                    'quantity' => 1,
                ) + $this->getVatRangeTags($wrappingVat, $wrappingEx, 0.02)
                + $vatLookupTags;
            $result['meta-calculated-fields'] = $metaCalculatedFields;
        }
        return $result;
    }

    /**
     * In a Prestashop order the discount lines are specified in Order cart
     * rules.
     *
     * @return array[]
     */
    protected function getDiscountLinesOrder()
    {
        $result = array();

        foreach ($this->order->getCartRules() as $line) {
            $result[] = $this->getDiscountLineOrder($line);
        }

        return $result;
    }

    /**
     * In a Prestashop order the discount lines are specified in Order cart
     * rules that have, a.o, the following fields:
     * - value: total amount inc VAT
     * - value_tax_excl: total amount ex VAT
     *
     * @param array $line
     *
     * @return array
     */
    protected function getDiscountLineOrder(array $line)
    {
        $sign = $this->getSign();
        $discountInc = -$sign * $line['value'];
        $discountEx = -$sign * $line['value_tax_excl'];
        $discountVat = $discountInc - $discountEx;
        $result = array(
                'itemnumber' => $line['id_cart_rule'],
                'product' => $this->t('discount_code') . ' ' . $line['name'],
                'unitprice' => $discountEx,
                'unitpriceinc' => $discountInc,
                'quantity' => 1,
                // If no match is found, this line may be split.
                'meta-strategy-split' => true,
                // Assuming that the fixed discount amount was entered:
                // - including VAT, the precision would be 0.01, 0.01.
                // - excluding VAT, the precision would be 0.01, 0
                // However, for a %, it will be: 0.02, 0.01, so use 0.02.
                // @todo: can we determine so?
            ) + $this->getVatRangeTags($discountVat, $discountEx, 0.02);
        $result['meta-calculated-fields'][] = 'vatamount';

        return $result;
    }

    /**
     * In a Prestashop credit slip, the discounts are not visible anymore, but
     * can be computed by looking at the difference between the value of
     * total_products_tax_incl and the sum of the OrderSlipDetail amounts.
     *
     * @return array[]
     */
    protected function getDiscountLinesCreditNote()
    {
        $result = array();

        // Get total amount credited.
        /** @noinspection PhpUndefinedFieldInspection */
        $creditSlipAmountInc = $this->creditSlip->total_products_tax_incl;

        // Get sum of product lines.
        $lines = $this->creditSlip->getOrdersSlipProducts($this->invoiceSource->getId(), $this->order);
        $detailsAmountInc = array_reduce($lines, function ($sum, $item) {
            $sum += $item['total_price_tax_incl'];
            return $sum;
        }, 0.0);

        // We assume that if total < sum(details), a discount given on the
        // original order has now been subtracted from the amount credited.
        if (!Number::floatsAreEqual($creditSlipAmountInc, $detailsAmountInc, 0.05)
            && $creditSlipAmountInc < $detailsAmountInc
        ) {
            // PS Error: total_products_tax_excl is not adjusted (whereas
            // total_products_tax_incl is) when a discount is subtracted from
            // the amount to be credited.
            // So we cannot calculate the discount ex VAT ourselves.
            // What we can try is the following: Get the order cart rules to see
            // if 1 or all of those match the discount amount here.
            $discountAmountInc = $detailsAmountInc - $creditSlipAmountInc;
            $totalOrderDiscountInc = 0.0;
            // Note: The sign of the entries in $orderDiscounts will be correct.
            $orderDiscounts = $this->getDiscountLinesOrder();

            foreach ($orderDiscounts as $key => $orderDiscount) {
                if (Number::floatsAreEqual($orderDiscount['unitpriceinc'], $discountAmountInc)) {
                    // Return this single line.
                    $from = $to = $key;
                    break;
                }
                $totalOrderDiscountInc += $orderDiscount['unitpriceinc'];
                if (Number::floatsAreEqual($totalOrderDiscountInc, $discountAmountInc)) {
                    // Return all lines up to here.
                    $from = 0;
                    $to = $key;
                    break;
                }
            }

            if (isset($from) && isset($to)) {
                $result = array_slice($orderDiscounts, $from, $to - $from + 1);
                // Correct meta-invoice-amount.
                $totalOrderDiscountEx = array_reduce($result, function ($sum, $item) {
                    $sum += $item['quantity'] * $item['unitprice'];
                    return $sum;
                }, 0.0);
                $this->invoice['customer']['invoice']['meta-invoice-amount'] += $totalOrderDiscountEx;
            }
            //else {
            // We could not match a discount with the difference between the
            // total amount credited and the sum of the products returned. A
            // manual line will correct the invoice.
            //}
        }
        return $result;
    }


    /**
     * Looks up and returns vat rate metadata.
     *
     * @param int $addressId
     * @param int $taxRulesGroupId
     *
     * @return array
     *   Either an array with keys 'meta-lookup-vatrate' and
     *  'meta-lookup-vatrate-label' or an empty array.
     */
    protected function getVatRateLookupMetadata($addressId, $taxRulesGroupId) {
        try {
            $address = new Address($addressId);
            $tax_manager = TaxManagerFactory::getManager($address, $taxRulesGroupId);
            $tax_calculator = $tax_manager->getTaxCalculator();
            $result = array(
                'meta-lookup-vatrate' => $tax_calculator->getTotalRate(),
                'meta-lookup-vatrate-label' => $tax_calculator->getTaxesName(),
            );
        } catch (\Exception $e) {
            $result = array();
        }
        return $result;
    }
}
