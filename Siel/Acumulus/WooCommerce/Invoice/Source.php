<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Invoice\Source as BaseSource;

/**
 * Wraps a WooCommerce order in an invoice source object.
 *
 * Since WC 2.2.0 multiple order types can be defined, @see
 * wc_register_order_type() and wc_get_order_types(). WooCommerce itself defines
 * 'shop_order' and 'shop_order_refund'. The base class for all these types of
 * orders is WC_Abstract_Order
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var \WC_Abstract_Order */
    protected $source;

    /**
     * Loads an Order or refund source for the set id.
     */
    protected function setSource()
    {
        $this->source = WC()->order_factory->get_order($this->id);
    }

    /**
     * Sets the id based on the loaded Order or Order refund.
     */
    protected function setId()
    {
        $this->id = $this->source->get_id();
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        // Method get_order_number() is used for when other plugins are
        // installed that add an order number that differs from the ID. Known
        // plugins that do so: woocommerce-sequential-order-numbers(-pro) and
        // wc-sequential-order-numbers.
        if ($this->getType() === Source::Order) {
            /** @var \WC_Order $order */
            $order = $this->source;
            return $order->get_order_number();
        }
        return parent::getReference();
    }

    /**
     * @inheritDoc
     */
    public function getDate()
    {
        return substr($this->source->get_date_created(), 0, strlen('2000-01-01'));
    }

    /**
     * @inheritDoc
     */
    public function getStatus()
    {
        return $this->source->get_status();
    }

    /**
     * {@inheritdoc}
     */
    protected function getOriginalOrder()
    {
        /** @var \WC_Order_Refund $refund */
        $refund = $this->source;
        return new Source(Source::Order, $refund->get_parent_id());
    }
}
