<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Shop\ConfigInterface;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the WooCommerce webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        $result = array();
        $orderStatuses = wc_get_order_statuses();
        foreach ($orderStatuses as $key => $label) {
            $result[substr($key, strlen('wc-'))] = $label;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice #' option as WC does not have
     * separate invoices.
     */
    public function getInvoiceNrSourceOptions()
    {
        $result = parent::getInvoiceNrSourceOptions();
        unset($result[InvoiceConfigInterface::InvoiceNrSource_ShopInvoice]);
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice date' option as WC does not have
     * separate invoices.
     */
    public function getDateToUseOptions()
    {
        $result = parent::getDateToUseOptions();
        unset($result[InvoiceConfigInterface::InvoiceDate_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice sent' option as WC does not have
     * separate invoices, let alone an event on sending it.
     */
    public function getTriggerInvoiceSendEventOptions()
    {
        $result = parent::getTriggerInvoiceSendEventOptions();
        unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $result = array();
        $paymentGateways = WC()->payment_gateways->payment_gateways();
        foreach ($paymentGateways as $id => $paymentGateway) {
            if (isset($paymentGateway->enabled) && $paymentGateway->enabled === 'yes') {
                $result[$id] = $paymentGateway->title;
            }
        }
        return $result;
    }
}