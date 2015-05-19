<?php
namespace Siel\Acumulus\Shop\Magento;

use Mage;
use \Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use Varien_Object;

class InvoiceManager extends BaseInvoiceManager {

  /**
   * Returns a Magento model for the given source type.
   *
   * @param $invoiceSourceType
   *
   * @return \Mage_Sales_Model_Abstract
   */
  protected function getInvoiceSourceTypeModel($invoiceSourceType) {
    return $invoiceSourceType == source::Order ? Mage::getModel('sales/order') : Mage::getModel('sales/order_creditmemo');
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo) {
    $field = 'entity_id';
    $condition = array('from' => $InvoiceSourceIdFrom, 'to' => $InvoiceSourceIdTo);
    return $this->getByCondition($invoiceSourceType, $field, $condition);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo) {
    $field = 'increment_id';
    $condition = array('from' => $InvoiceSourceReferenceFrom, 'to' => $InvoiceSourceReferenceTo);
    return $this->getByCondition($invoiceSourceType, $field, $condition);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByDateRange($invoiceSourceType, $dateFrom, $dateTo) {
    $field = 'updated_at';
    $condition = array('from' => $dateFrom, 'to' => $dateTo);
    return $this->getByCondition($invoiceSourceType, $field, $condition);
  }

  /**
   * Helper method that executes a query to retrieve a list of invoice source
   * ids and returns a list of invoice sources for these ids.
   *
   * @param string $invoiceSourceType
   * @param string|string[] $field
   * @param int|string|array $condition
   *
   * @return \Siel\Acumulus\Shop\Magento\Source[]
   *   A non keyed array with invoice Sources.
   */
  protected function getByCondition($invoiceSourceType, $field, $condition) {
    /** @var \Mage_Core_Model_Resource_Db_Collection_Abstract $collection */
    $collection = $this->getInvoiceSourceTypeModel($invoiceSourceType)->getResourceCollection();
    $items = $collection
      ->addFieldToFilter($field, $condition)
      ->getItems();

    $results = array();
    foreach ($items as $item) {
      $results[] = new Source($invoiceSourceType, $item);
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   *
   * This Magento override dispatches the 'acumulus_invoice_created' event.
   */
  protected function triggerInvoiceCreated(&$invoice, $invoiceSource) {
    $transportObject = new Varien_Object(array('invoice' => $invoice));
    Mage::dispatchEvent('acumulus_invoice_created', array('transport_object' => $transportObject, 'source' => $invoiceSource));
  }

  /**
   * {@inheritdoc}
   *
   * This Magento override dispatches the 'acumulus_invoice_completed' event.
   */
  protected function triggerInvoiceCompleted(&$invoice, $invoiceSource) {
    $transportObject = new Varien_Object(array('invoice' => $invoice));
    Mage::dispatchEvent('acumulus_invoice_completed', array('transport_object' => $transportObject, 'source' => $invoiceSource));
  }

}
