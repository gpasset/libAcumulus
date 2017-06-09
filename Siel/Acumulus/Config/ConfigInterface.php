<?php
namespace Siel\Acumulus\Config;

/**
 * Defines an interface to retrieve shop specific configuration settings.
 *
 * Configuration is stored in the host environment, normally a web shop.
 * This interface abstracts from how a specific web shop does so.
 */
interface ConfigInterface
{
    // @todo: Move to new message class or to InvoiceManager.
    // Invoice send handling related constants. These can be combined with a
    // send Status_... const (bits 1 to 3).
    // Not sent: bit 4 always set.
    const Invoice_NotSent = 0x8;
    // Reason for not sending: bits 5 to 7.
    const Invoice_NotSent_EventInvoiceCreated = 0x18;
    const Invoice_NotSent_EventInvoiceCompleted = 0x28;
    const Invoice_NotSent_AlreadySent = 0x38;
    const Invoice_NotSent_WrongStatus = 0x48;
    const Invoice_NotSent_EmptyInvoice = 0x58;
    const Invoice_NotSent_TriggerInvoiceCreateNotEnabled = 0x68;
    const Invoice_NotSent_TriggerInvoiceSentNotEnabled = 0x78;
    const Invoice_NotSent_Mask = 0x78;
    // Reason for sending: bits 8 and 9
    const Invoice_Sent_New = 0x80;
    const Invoice_Sent_Forced = 0x100;
    const Invoice_Sent_TestMode = 0x180;
    const Invoice_Sent_Mask = 0x180;

    /**
     * Returns the contract credentials to authenticate with the Acumulus API.
     *
     * @return array
     *   A keyed array with the keys:
     *   - contractcode
     *   - username
     *   - password
     *   - emailonerror
     *   - emailonwarning
     */
    public function getCredentials();

    /**
     * Returns information about the environment of this library.
     *
     * @return array
     *   A keyed array with information about the environment of this library:
     *   - baseUri
     *   - apiVersion
     *   - libraryVersion
     *   - moduleVersion
     *   - shopName
     *   - shopVersion
     *   - hostName
     *   - phpVersion
     *   - os
     *   - curlVersion
     *   - jsonVersion
     */
    public function getEnvironment();

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - debug
     *   - logLevel
     *   - outputFormat
     */
    public function getPluginSettings();

    /**
     * Returns the set of settings related to the customer part of an invoice.
     *
     * @return array
     *   A keyed array with the keys:
     *   - sendCustomer
     *   - overwriteIfExists
     *   - defaultCustomerType
     *   - contactStatus
     *   - contactYourId
     *   - companyName1
     *   - companyName2
     *   - vatNumber
     *   - fullName
     *   - salutation
     *   - address1
     *   - address2
     *   - postalCode
     *   - city
     *   - telephone
     *   - fax
     *   - email
     *   - mark
     *   - genericCustomerEmail
     */
    public function getCustomerSettings();

    /**
     * Returns the set of settings related to the invoice part of an invoice.
     *
     * @return array
     *   A keyed array with the keys:
     *   - defaultAccountNumber
     *   - defaultCostCenter
     *   - defaultInvoiceTemplate
     *   - defaultInvoicePaidTemplate
     *   - paymentMethodAccountNumber
     *   - paymentMethodCostCenter
     *   - sendEmptyInvoice
     *   - sendEmptyShipping
     *   - description
     *   - descriptionText
     *   - invoiceNotes
     *   - useMargin
     *   - optionsShow
     *   - optionsAllOn1Line
     *   - optionsAllOnOwnLine
     *   - optionsMaxLength
     */
    public function getInvoiceSettings();

    /**
     * Returns the set of settings related to the shop characteristics that
     * influence the invoice creation and completion
     *
     * @return array
     *   A keyed array with the keys:
     *   - digitalServices
     *   - vatFreeProducts
     *   - invoiceNrSource
     *   - dateToUse
     */
    public function getShopSettings();

    /**
     * Returns the set of settings related to sending an email.
     *
     * @return array
     *   A keyed array with the keys:
     *   - emailAsPdf
     *   - emailBcc
     *   - emailFrom
     *   - subject
     *   - confirmReading
     */
    public function getEmailAsPdfSettings();

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - triggerOrderStatus
     *   - triggerInvoiceEvent
     *   - sendEmptyInvoice
     */
    public function getShopEventSettings();

    /**
     * Saves the configuration to the actual configuration provider.
     *
     * @param array $values
     *   A keyed array that contains the values to store, this may be a subset
     *   of the possible keys.
     *
     * @return bool
     *   Success.
     */
    public function save(array $values);

    /**
     * Returns a list of keys that are stored in the shop specific config store.
     *
     * @return array
     */
    public function getKeys();

    /**
     * Returns a list of defaults for the config keys.
     *
     * @return array
     */
    public function getDefaults();

    /**
     * Upgrade the datamodel to the given version.
     *
     * This method is only called when the module gets updated.
     *
     * @param string $currentVersion
     *   The current version of the module.
     *
     * @return bool
     *   Success.
     */
    public function upgrade($currentVersion);
}
