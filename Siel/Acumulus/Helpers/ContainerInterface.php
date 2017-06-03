<?php
namespace Siel\Acumulus\Helpers;

/**
 * ContainerInterface defines an interface to retrieve:
 * - Instances of web shop specific overrides of the base classes and interfaces
 *   that are defined in the common package.
 * - Singleton instances from other namespaces.
 * - Instances that require some injection arguments in their constructor, that
 *   the implementing object can pass.
 */
interface ContainerInterface
{
    /**
     * Sets a custom namespace for customisations on top of the current shop.
     *
     * @param string $customNamespace
     */
    public function setCustomNamespace($customNamespace);

    /**
     * @return \Siel\Acumulus\Helpers\Log
     */
    public function getLog();

    /**
     * @return \Siel\Acumulus\Helpers\TranslatorInterface
     */
    public function getTranslator();

    /**
     * @return \Siel\Acumulus\Helpers\Mailer
     */
    public function getMailer();

    /**
     * @return \Siel\Acumulus\Helpers\Token
     */
    public function getToken();

    /**
     * @param string $type
     *   The type of form requested.
     *
     * @return \Siel\Acumulus\Helpers\Form
     */
    public function getForm($type);

    /**
     * @return \Siel\Acumulus\Helpers\FormRenderer
     */
    public function getFormRenderer();

    /**
     * @return \Siel\Acumulus\Web\Service
     */
    public function getService();

    /**
     * @return \Siel\Acumulus\Web\CommunicatorInterface
     */
    public function getCommunicator();

    /**
     * Creates a wrapper object for a source object identified by the given
     * parameters.
     *
     * @param string $invoiceSourceType
     *   The type of the invoice source to create.
     * @param string|object|array $invoiceSourceOrId
     *   The invoice source itself or its id to create a Source wrapper for.
     *
     * @return \Siel\Acumulus\Invoice\Source
     *   A wrapper object around a shop specific invoice source object.
     */
    public function getSource($invoiceSourceType, $invoiceSourceOrId);

    /**
     * @return \Siel\Acumulus\Invoice\Completor
     */
    public function getCompletor();

    /**
     * @return \Siel\Acumulus\Invoice\CompletorInvoiceLines
     */
    public function getCompletorInvoiceLines();

    /**
     * @return \Siel\Acumulus\Invoice\FlattenerInvoiceLines
     */
    public function getFlattenerInvoiceLines();

    /**
     * @return \Siel\Acumulus\Invoice\CompletorStrategyLines
     */
    public function getCompletorStrategyLines();

    /**
     * @return \Siel\Acumulus\Invoice\Creator
     */
    public function getCreator();

    /**
     * @return \Siel\Acumulus\Shop\ConfigStoreInterface
     */
    public function getConfigStore();

    /**
     * @return \Siel\Acumulus\Shop\ShopCapabilitiesInterface
     */
    public function getShopCapabilities();

    /**
     * @return \Siel\Acumulus\Shop\InvoiceManager
     */
    public function getManager();

    /**
     * @return \Siel\Acumulus\Shop\AcumulusEntryModel
     */
    public function getAcumulusEntryModel();
}
