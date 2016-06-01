<?php
namespace Siel\Acumulus\Joomla\HikaShop\Shop;

use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\ConfigInterface;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the HikaShop webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     *
     * HikaShop does not know refunds.
     */
    public function getSupportedInvoiceSourceTypes()
    {
        $result = parent::getSupportedInvoiceSourceTypes();
        unset($result[Source::CreditNote]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        /** @var \hikashopCategoryClass $class */
        $class = hikashop_get('class.category');
        $statuses = $class->loadAllWithTrans('status');

        $orderStatuses = array();
        foreach ($statuses as $state) {
            $orderStatuses[$state->category_name] = $state->translation;
        }
        return $orderStatuses;
    }

    /**
     * {@inheritdoc}
     *
     * HikaShop does not have separate invoice entities. (Though an HS order
     * does have an order_invoice_number property).
     */
    public function getTriggerInvoiceSendEventOptions()
    {
        $result = parent::getTriggerInvoiceSendEventOptions();
        unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use shop invoice number' option as HikaShop
     * does not have invoices.
     */
    public function getDateToUseOptions()
    {
        $result = parent::getDateToUseOptions();
        unset($result[InvoiceConfigInterface::InvoiceDate_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $result = array();
        /** @var \hikashopPluginsClass $pluginClass */
        $pluginClass = hikashop_get('class.plugins');
        $paymentPlugins = $pluginClass->getMethods('payment');
        foreach ($paymentPlugins as $paymentPlugin) {
            if (!empty($paymentPlugin->enabled)) {
                $result[$paymentPlugin->payment_type] = $paymentPlugin->payment_name;
            }
        }
        return $result;
    }
}
