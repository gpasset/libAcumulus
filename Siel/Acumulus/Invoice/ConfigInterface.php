<?php
namespace Siel\Acumulus\Invoice;

/**
 * InvoiceConfigInterface defines an interface to store and retrieve invoice
 * specific configuration values.
 *
 * Configuration is stored in the host environment (normally a web shop), this
 * interface abstracts from how a specific web shop does so.
 */
interface ConfigInterface {
  // Invoice related constants.
  const PaymentStatus_Due = 1;
  const PaymentStatus_Paid = 2;

  const OverwriteIfExists_No = 0;
  const OverwriteIfExists_Yes = 1;

  const Concept_No = 0;
  const Concept_Yes = 1;

  const LocationCode_None = 0;
  const LocationCode_NL = 1;
  const LocationCode_EU = 2;
  const LocationCode_RestOfWorld = 3;

  const VatType_National = 1;
  const VatType_NationalReversed = 2;
  const VatType_EuReversed = 3;
  const VatType_RestOfWorld = 4;
  const VatType_MarginScheme = 5;
  const VatType_ForeignVat = 6;

  const ConfirmReading_No = 0;
  const ConfirmReading_Yes = 1;

  /**
   * Returns the set of settings related to adding an invoice.
   *
   * @return array
   *   A keyed array with the keys:
   *   - defaultCustomerType
   *   - sendCustomer
   *   - genericCustomerEmail
   *   - overwriteIfExists
   *   - defaultAccountNumber
   *   - defaultCostCenter
   *   - defaultInvoiceTemplate
   *   - defaultInvoicePaidTemplate
   *   - useMargin
   */
  public function getInvoiceSettings();

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
}