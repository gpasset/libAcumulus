<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Shop;

use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\OpenCart\Shop\InvoiceManager as BaseInvoiceManager;

/**
 * This OpenCart 1 override allows you to insert your event handler code using
 * VQMOD.
 */
class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        // VQMOD: insert your 'acumulus.invoice.created' event code here.
        // END VQMOD: insert your 'acumulus.invoice.created' event code here.
    }

    /**
     * {@inheritdoc}
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        // VQMOD: insert your 'acumulus.invoice.completed' event code here.
        // END VQMOD: insert your 'acumulus.invoice.completed' event code here.
    }

    /**
     * {@inheritdoc}
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
    {
        // VQMOD: insert your 'acumulus.invoice.sent' event code here.
        // END VQMOD: insert your 'acumulus.invoice.sent' event code here.
    }
}
