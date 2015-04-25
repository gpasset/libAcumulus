<?php
namespace Siel\Acumulus\VirtueMart;

use DateTimeZone;
use JDate;
use JFactory;
use \Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager {

  /**
   * {@inheritdoc}
   *
   * This override only returns order as supported invoice source type.
   *
   * Note: the VMInvoice extension seems to offer credit notes, but for now we
   *   do not support them.
   */
  public function getSupportedInvoiceSourceTypes() {
    return array(
      Source::Order,
      //Source::CreditNote,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function mailInvoiceAddResult(array $result, array $messages, $invoiceSource) {
    $mailer = $this->getMailer();
    return $mailer->sendInvoiceAddMailResult($result, $messages, $invoiceSource->getType(), $invoiceSource->getReference());
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo) {
    $query = sprintf("select virtuemart_order_id
			from #__virtuemart_orders
			where virtuemart_order_id between %d and %d",
      $InvoiceSourceIdFrom, $InvoiceSourceIdTo);
    return $this->getByQuery($invoiceSourceType, $query);
  }

  /**
   * {@inheritdoc}
   *
   * By default, VirtueMart order numbers are non sequential random strings.
   * So getting a range is not logical. However, extensions exists that do
   * introduce sequential order numbers, E.g:
   * http://extensions.joomla.org/profile/extension/extension-specific/virtuemart-extensions/human-readable-order-numbers
   */
  public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo) {
    $query = sprintf("select virtuemart_order_id
			from #__virtuemart_orders
			where order_number between '%s' and '%s'",
      $this->getDb()->escape($InvoiceSourceReferenceFrom),
      $this->getDb()->escape($InvoiceSourceReferenceTo)
    );
    return $this->getByQuery($invoiceSourceType, $query);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByDateRange($invoiceSourceType, $dateFrom, $dateTo) {
    $query = sprintf("select virtuemart_order_id
			from #__virtuemart_orders
			where modified_on between '%s' and '%s'",
      $this->toSql($dateFrom), $this->toSql($dateTo));
    return $this->getByQuery($invoiceSourceType, $query);
  }

  /**
   * Helper method that executes a query to retrieve a list of invoice source
   * ids and returns a list of invoice sources for these ids.
   *
   * @param string $invoiceSourceType
   * @param string $query
   *
   * @return \Siel\Acumulus\VirtueMart\Source[]
   *   A non keyed array with invoice Sources.
   */
  protected function getByQuery($invoiceSourceType, $query) {
    $sourceIds = $this->loadColumn($query);
    $results = array();
    foreach ($sourceIds as $sourceId) {
      $results[] = new Source($invoiceSourceType, $sourceId);
    }
    return $results;
  }

  /**
   * Helper method to execute a query and return the 1st column from the
   * results.
   *
   * @param string $query
   *
   * @return int[]
   *   A non keyed array with the values of the 1st results of the query result.
   */
  protected function loadColumn($query) {
    return $this->getDb()->setQuery($query)->loadColumn();
  }

  /**
   * Helper method to get the db object.
   *
   * @return \JDatabaseDriver
   */
  protected function getDb() {
    return JFactory::getDBO();
  }

  /**
   * Helper method that returns a date in the correct and escaped sql format.
   *
   * @param string $date
   *   Date in yyyy-mm-dd format.
   *
   * @return string
   */
  protected function toSql($date) {
    $tz = new DateTimeZone(JFactory::getApplication()->get('offset'));
    $date = new JDate($date);
    $date->setTimezone($tz);
    return $date->toSql(TRUE);
  }

}
