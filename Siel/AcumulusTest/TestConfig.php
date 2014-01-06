<?php
/**
 * @file Contains Siel\Acumulus\Test\TestConfig.
 */

namespace Siel\AcumulusTest;

use Siel\Acumulus\BaseConfig;

require_once(dirname(__FILE__) . '/../Acumulus/ConfigInterface.php');
require_once(dirname(__FILE__) . '/../Acumulus/BaseConfig.php');

/**
 * TestConfig defines a configuration object for test purposes.
 *
 * @package Siel\AcumulusTest
 */
class TestConfig extends BaseConfig {
  public function __construct() {
    parent::__construct();
    $this->values = array_merge($this->values, array(
      'moduleVersion' => $this->values['libraryVersion'],
      'shopName' => 'Test',
      'shopVersion' => $this->values['libraryVersion'],
      'debug' => true,
    ));
  }

  /**
   * @inheritdoc
   */
  public function load() {
    $this->values = array_merge($this->values, array(
      'contractcode' => '288252',
      'username' => 'erwind',
      'password' => 'yLWT8PFz',
      'emailonerror' => 'erwin@burorader.com',
      'emailonwarning' => 'erwin@burorader.com',
    ));
    $this->values['defaultCustomerType'] = 3;
    $this->values['defaultAccountNumber'] = 70582;
    $this->values['useAcumulusInvoiceNumber'] = true;
    $this->values['useOrderDate'] = true;
    $this->values['defaultCostHeading'] = 48663;
    $this->values['defaultInvoiceTemplate'] = 39851;
    $this->values['triggerOrderStatus'] = 'paid';
    $this->values['useMargin'] = false;
    //@todo: useCostprice wordt niet gebruikt.
    //$this->values['useCostPrice'] = false;
    $this->values['overwriteIfExists'] = 1;
    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function save(array $values) {
    return TRUE;
  }
}