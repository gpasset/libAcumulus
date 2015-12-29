<?php
namespace Siel\Acumulus\Joomla\HikaShop\Shop;

use JText;
use Siel\Acumulus\Joomla\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Shop\ConfigInterface;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * HikaShop Acumulus module.
 */
class ConfigForm extends BaseConfigForm {

  /**
   * {@inheritdoc}
   */
  protected function getShopOrderStatuses() {
    /** @var \hikashopCategoryClass $class */
    $class = hikashop_get('class.category');
    $statuses = $class->loadAllWithTrans('status');

    $orderStatuses = array();
    foreach ($statuses as $state) {
      $orderStatuses[$state->category_namekey] = $state->translation;
    }
    return $orderStatuses;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTriggerInvoiceSendEventOptions() {
    $result = parent::getTriggerInvoiceSendEventOptions();
    // @todo: find out if there's something like an invoice create event.
    unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
    return $result;
  }

}
