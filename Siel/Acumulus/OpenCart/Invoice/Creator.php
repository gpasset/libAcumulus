<?php
namespace Siel\Acumulus\OpenCart\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Allows to create arrays in the Acumulus invoice structure from an OpenCart
 * order.
 */
class Creator extends BaseCreator
{
    // More specifically typed property.
    /** @var array */
    protected $order;

    /** @var array[] List of OpenCart order total records. */
    protected $orderTotalLines;

    /**
     * {@inheritdoc}
     *
     * This override also initializes WooCommerce specific properties related to
     * the source.
     */
    protected function setInvoiceSource($invoiceSource)
    {
        parent::setInvoiceSource($invoiceSource);

        // Load some models and properties we are going to use.
        Registry::getInstance()->load->model('catalog/product');
        $this->orderTotalLines = null;

        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->invoiceSource->getSource();
                break;
            case Source::CreditNote:
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getCountryCode()
    {
        return !empty($this->order['payment_iso_code_2']) ? $this->order['payment_iso_code_2'] : '';
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the code of the selected payment method.
     */
    protected function getPaymentMethod()
    {
        if (isset($this->order['payment_code'])) {
            return $this->order['payment_code'];
        }
        return parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentState()
    {
        // @todo: Can we determine this based on payment_code?
        $result = ConfigInterface::PaymentStatus_Paid;
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentDate()
    {
        // @todo: Can we determine this based on history (and optionally payment_code)?
        // Will default to the issue date.
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc,
     * meta-invoice-vatamount and a vat breakdown in meta-invoice-vat.
     */
    protected function getInvoiceTotals()
    {
        $result = array(
            'meta-invoice-amountinc' => $this->order['total'],
            'meta-invoice-vatamount' => 0.0,
            'meta-invoice-vat' => array(),
        );

        $orderTotals = $this->getOrderTotalLines();
        foreach ($orderTotals as $totalLine) {
            if ($totalLine['code'] === 'tax') {
                $result['meta-invoice-vat'][] = $totalLine['title'] . ': ' . $totalLine['value'];
                $result['meta-invoice-vatamount'] += $totalLine['value'];
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInvoiceLines()
    {
        $itemLines = $this->getItemLines();
        $itemLines = $this->addLineType($itemLines, static::LineType_Order);

        $totalLines = $this->getTotalLines();

        $result = array_merge($itemLines, $totalLines);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        $result = array();

        $orderProducts = $this->getOrderModel()->getOrderProducts($this->invoiceSource->getId());
        foreach ($orderProducts as $line) {
            $result[] = $this->getItemLine($line);
        }

        return $result;
    }

    /**
     * Returns the item line for 1 product line.
     *
     * This method may return child lines if there are options/variants.
     * These lines will be informative, their price will be 0.
     *
     * @param array $item
     *
     * @return array
     */
    protected function getItemLine(array $item)
    {
        $result = array();

        // $product can be empty if the product has been deleted.
        $product = Registry::getInstance()->model_catalog_product->getProduct($item['product_id']);
        if (!empty($product)) {
            $this->addPropertySource('product', $product);
        }
        $this->addPropertySource('item', $item);

        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($result, 'itemnumber', $invoiceSettings['itemNumber']);
        $this->addTokenDefault($result, 'product', $invoiceSettings['productName']);
        $this->addTokenDefault($result, 'nature', $invoiceSettings['nature']);

        // Get vat range info from item line.
        $productPriceEx = $item['price'];
        $productVat = $item['tax'];
        $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx);

        // Try to look up the vat rate via product.
        $vatInfo += $this->getVatRateLookupMetadata($product['tax_class_id']);

        $result['unitprice'] = $productPriceEx;
        $result['quantity'] = $item['quantity'];
        $result += $vatInfo;
        $result['vatamount'] = $productVat;

        // Options (variants).
        $options = $this->getOrderModel()->getOrderOptions($item['order_id'], $item['order_product_id']);
        if (!empty($options)) {
            // Add options as children.
            $result[Creator::Line_Children] = array();
            $optionsVatInfo = $vatInfo;
            $optionsVatInfo['vatamount'] = 0;
            foreach ($options as $option) {
                $result[Creator::Line_Children][] = array(
                    'product' => "{$option['name']}: {$option['value']}",
                    'unitprice' => 0,
                      // Table order_option does not have a quantity field, so
                      // composite products with multiple same sub product
                      // are apparently not covered. Take quantity from parent.
                    'quantity' => $item['quantity'],
                  ) + $optionsVatInfo;
            }
        }
        $this->removePropertySource('product');
        $this->removePropertySource('item');

        return $result;
    }

    /**
     * Looks up and returns, if only 1 rate was found, vat rate metadata.
     *
     * @param int $taxClassId
     *   The tax class to look up the vat rate for.
     *
     * @return array
     *   Either an array with keys 'meta-lookup-vatrate' and
     *  'meta-lookup-vatrate-label' or an empty array.
     */
    protected function getVatRateLookupMetadata($taxClassId) {
        $result = array();
        $taxRules = $this->getTaxRules($taxClassId);
        // We are not going to drill down geo zones, so if we got only 1 rate,
        // or all rates are the same, we use that, otherwise we don't use it.
        $vatRates = array();
        $label = '';
        foreach ($taxRules as $taxRule) {
            $taxRate = $this->getTaxRate($taxRule['tax_rate_id']);
            if (!empty($taxRate)) {
                $vatRates[$taxRate['rate']] = $taxRate['rate'];
                if (empty($label)) {
                    $label = $taxRate['name'];
                }
            }
        }
        if (count($vatRates) === 1) {
            $result['meta-lookup-vatrate'] = reset($vatRates);
            // Take the first name (if there were more tax rates).
            $result['meta-lookup-vatrate-label'] = $label;
        }
        return $result;
    }

    /**
     * Copy of ModelLocalisationTaxClass::getTaxRules().
     *
     * The above mentioned model cannot be used on the catalog side, so I just
     * copied the code.
     *
     * @param int $tax_class_id
     *
     * @return array[]
     *
     */
    protected function getTaxRules($tax_class_id) {
        $query = Registry::getInstance()->db->query("SELECT * FROM " . DB_PREFIX . "tax_rule WHERE tax_class_id = '" . (int) $tax_class_id . "'");
        return $query->rows;
    }

    /**
     * Copy of ModelLocalisationTaxRate::getTaxRate().
     *
     * The above mentioned model cannot be used on the catalog side, so I just
     * copied the code.
     *
     * @param int $tax_rate_id
     *
     * @return array
     *
     */
    protected function getTaxRate($tax_rate_id) {
        $query = Registry::getInstance()->db->query("SELECT tr.tax_rate_id, tr.name AS name, tr.rate, tr.type, tr.geo_zone_id, gz.name AS geo_zone, tr.date_added, tr.date_modified FROM " . DB_PREFIX . "tax_rate tr LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr.geo_zone_id = gz.geo_zone_id) WHERE tr.tax_rate_id = '" . (int) $tax_rate_id . "'");
        return $query->row;
    }


    /**
     * Returns all total lines: shipping,handling, discount, ...
     *
     * @return array[]
     *   An array of invoice lines.
     */
    protected function getTotalLines()
    {
        $result = array();

        $totalLines = $this->getOrderTotalLines();
        foreach ($totalLines as $totalLine) {
            switch ($totalLine['code']) {
                case 'sub_total':
                    // Sub total of all product lines: ignore.
                    $line = null;
                    break;
                case 'shipping':
                    $line = $this->getTotalLine($totalLine);
                    $line['meta-line-type'] = static::LineType_Shipping;
                    break;
                case 'coupon':
                    $line = $this->getTotalLine($totalLine);
                    $line['meta-line-type'] = static::LineType_Discount;
                    break;
                case 'tax':
                    // Tax line: added to invoice level
                    $line = null;
                    break;
                case 'voucher':
                    $line = $this->getTotalLine($totalLine);
                    $line['meta-line-type'] = static::LineType_Voucher;
                    break;
                case 'total':
                    // Overall total: ignore.
                    $line = null;
                    break;
                default:
                    $line = $this->getTotalLine($totalLine);
                    $line['meta-line-type'] = static::LineType_Other;
                    break;
            }
            if ($line) {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * Returns a line based on a "order total line".
     *
     * @param array $line
     *
     * @return array
     */
    protected function getTotalLine(array $line)
    {
        $result = array(
            'product' => $line['title'],
            // Let's hope that this is the value ex vat...
            'unitprice' => $line['value'],
            'quantity' => 1,
        );

        if ($line['code'] === 'voucher') {
            // A voucher is to be seen as a partial payment, thus no tax.
            $result += array(
                'vatrate' => -1,
                'meta-vatrate-source' => Creator::VatRateSource_Exact0,
            );
        } elseif ($line['code'] === 'coupon') {
            // Coupons may have to be split over various taxes.
            $result += array(
                'vatrate' => null,
                'meta-vatrate-source' => Creator::VatRateSource_Strategy,
                'meta-strategy-split' => $line['code'] === 'coupon',
            );
        } else {
            // Try to get a vat rate
            $vatRateLookupMetaData = $this->getVatRateLookupByTotalLineType($line['code']);
            if (Number::isZero($line['value'])) {
                // 0-cost lines - e.g. free shipping - also don't have a tax amount,
                // let the completor add the highest appearing vat rate.
                $result += array(
                    'vatrate' => null,
                    'meta-vatrate-source' => Creator::VatRateSource_Completor,
                ) + $vatRateLookupMetaData;
            } else {
                // Other lines do not have a discoverable vatrate, let a strategy
                // try to compute it.
                $result += array(
                    'vatrate' => null,
                    'meta-vatrate-source' => Creator::VatRateSource_Strategy,
                    'meta-strategy-split' => false,
                ) + $vatRateLookupMetaData;
            }
        }

        return $result;
    }

    /**
     * Returns a list of OpenCart order total records. These are shipment,
     * other fee, tax, and discount lines.
     *
     * @return array[]
     */
    protected function getOrderTotalLines()
    {
        if (!$this->orderTotalLines) {
            $orderModel = $this->getOrderModel();
            $this->orderTotalLines = $orderModel->getOrderTotals($this->order['order_id']);
        }
        return $this->orderTotalLines;
    }

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @return \ModelAccountOrder|\ModelSaleOrder
     */
    protected function getOrderModel()
    {
        return Registry::getInstance()->getOrderModel();
    }

    /**
     * Tries to lookup and return vat rate meta data for the given line type.
     *
     * This is quite hard. The total line (table order_total) contains a code
     * (= line type) and title field, the latter being a translated and possibly
     * formatted descriptive string of the shipping or handling method applied,
     * e.g. Europa  (Weight: 3.00kg). It is (almost) impossible to trace this
     * back to a shipping or handling method. So instead we retrieve all tax
     * class ids for the given type, collect all tax rates for those, and hope
     * that this results in only 1 tax rate.
     *
     * @param string $code
     *   The total line type: shipping, handling, ... (no other known types).
     *
     * @return array
     *   A, possibly empty, array with vat rate lookup meta data. Empty if no or
     *   multiple tax rates were found.
     */
    protected function getVatRateLookupByTotalLineType($code)
    {
        $result = array();
        $prefix = DB_PREFIX;
        $query = "SELECT distinct `value` FROM {$prefix}setting, {$prefix}extension where `type` = '$code' and `key` = concat(`{$prefix}extension`.`code`, '_tax_class_id')";
        $records = Registry::getInstance()->db->query($query);
        foreach ($records->rows as $row) {
            $taxClassId = reset($row);
            $vatRateMetadata = $this->getVatRateLookupMetadata($taxClassId);
            if (empty($vatRateMetadata)) {
                // Different vat rates within same tax class: return no result.
                return array();
            } elseif (empty($result)) {
                // First row: set result
                $result = $vatRateMetadata;
            } elseif (!Number::floatsAreEqual($vatRateMetadata['meta-lookup-vatrate'], $result['meta-lookup-vatrate'])) {
                // Different rates between tax classes: return no result.
                return array();
            } // else: rates are the same: continue.
        }
        return $result;
    }
}
