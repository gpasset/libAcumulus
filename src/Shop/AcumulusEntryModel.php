<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Log;

/**
 * Represents acumulus entry records.
 *
 * These records tie orders or credit notes from the web shop to entries in
 * Acumulus.
 *
 * Acumulus identifies entries by their entry id (boekstuknummer in het
 * Nederlands). To access an entry via the API, one must also supply a token
 * that is generated based on the contents of the entry. The entry id and token
 * are stored together with an id for the order or credit note from the web
 * shop.
 *
 * Usages (not (all of them are) yet implemented):
 * - Prevent that an invoice for a given order or credit note is sent twice.
 * - Show additional information on order list screens
 * - Update payment status
 * - Resend Acumulus invoice PDF.
 */
abstract class AcumulusEntryModel
{
    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /**
     * AcumulusEntryModel constructor.
     *
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Returns the Acumulus entry record for the given entry id.
     *
     * @param int|null $entryId
     *   The entry id to look up. If $entryId === null, multiple records may be
     *   found, in which case a numerically indexed array will be returned.
     *
     * @return array|object|null|array[]|object[]
     *   Acumulus entry record for the given entry id or null if the entry id is
     *   unknown.
     */
    abstract public function getByEntryId($entryId);

    /**
     * Returns the Acumulus entry record for the given invoice source.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object for which the invoice was created.
     *
     * @return array|object|null
     *   Acumulus entry record for the given invoice source or null if no
     *   invoice has yet been created in Acumulus for this invoice source.
     */
    public function getByInvoiceSource($invoiceSource)
    {
        return $this->getByInvoiceSourceId($invoiceSource->getType(), $invoiceSource->getId());
    }

    /**
     * Returns the Acumulus entry record for the given invoice source.
     *
     * @param string $invoiceSourceType
     *   The type of the invoice source
     * @param string $invoiceSourceId
     *   The id of the invoice source for which the invoice was created.
     *
     * @return array|object|null
     *   Acumulus entry record for the given invoice source or null if no
     *   invoice has yet been created in Acumulus for this invoice source.
     */
    abstract public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId);

    /**
     * Saves the Acumulus entry for the given order in the web shop's database.
     *
     * This default implementation calls getByInvoiceSource() to determine
     * whether to subsequently call insert() or update().
     *
     * So normally, a child class should implement insert() and update() and not
     * override this method.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object for which the invoice was created.
     * @param int|null $entryId
     *   The Acumulus entry Id assigned to the invoice for this order.
     * @param string|null $token
     *   The Acumulus token to be used to access the invoice for this order via
     *   the Acumulus API.
     *
     * @return bool
     *   Success.
     */
    public function save($invoiceSource, $entryId, $token)
    {
        $now = $this->sqlNow();
        $record = $this->getByInvoiceSource($invoiceSource);
        if ($record === null) {
            return $this->insert($invoiceSource, $entryId, $token, $now);
        } else {
            return $this->update($record, $entryId, $token, $now);
        }
    }

    /**
     * Returns the current time in a format accepted by the actual db layer.
     *
     * @return int|string
     *   Timestamp
     */
    abstract protected function sqlNow();

    /**
     * Inserts an Acumulus entry for the given order in the web shop's database.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object for which the invoice was created.
     * @param int|null $entryId
     *   The Acumulus entry Id assigned to the invoice for this order.
     * @param string|null $token
     *   The Acumulus token to be used to access the invoice for this order via
     *   the Acumulus API.
     * @param int|string $created
     *   The creation time (= current time), in the format as the actual
     *   database layer expects for a timestamp.
     *
     * @return bool
     *   Success.
     */
    abstract protected function insert($invoiceSource, $entryId, $token, $created);

    /**
     * Updates the Acumulus entry for the given invoice source.
     *
     * @param array|object $record
     *   The existing record for the invoice source to be updated.
     * @param int|null $entryId
     *   The new Acumulus entry id for the invoice source.
     * @param string|null $token
     *   The new Acumulus token for the invoice source.
     * @param int|string $updated
     *   The update time (= current time), in the format as the actual database
     *   layer expects for a timestamp.
     *
     * @return bool
     *   Success.
     */
    abstract protected function update($record, $entryId, $token, $updated);

    /**
     * @return bool
     */
    abstract public function install();

    /**
     * Upgrade the datamodel to the given version. Only called when the module
     * got updated.
     *
     * @param string $version
     *
     * @return bool
     *   Success.
     */
    public function upgrade(/** @noinspection PhpUnusedParameterInspection */ $version)
    {
        return true;
    }

    /**
     * @return bool
     *   Success.
     */
    abstract public function uninstall();
}
