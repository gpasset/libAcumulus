<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for classes in the \Siel\Acumulus\Invoice namespace.
 */
class Translations extends TranslationCollection
{
    protected $nl = array(
        Source::Order => 'bestelling',
        Source::CreditNote => 'creditnota',
        Source::Other => 'overig',

        'plural_Order'  => 'bestellingen',
        'plural_CreditNote' => 'creditnota\'s',
        'plural_Other' => 'overige',

        'for' => 'voor',
        'vat' => 'BTW',
        'inc_vat' => 'incl. BTW',
        'ex_vat' => 'excl. BTW',
        'shipping_costs' => 'Verzendkosten',
        // @todo: try to better distinguish free shipping and pickup: for now only WC, HS PS do so
        'free_shipping' => 'Gratis verzending',
        'pickup' => 'Afhalen',
        'payment_costs' => 'Betalingskosten',
        'discount' => 'Korting',
        'discount_code' => 'Kortingscode',
        'coupon_code' => 'Cadeaubon',
        'used' => 'gebruikt',
        'gift_wrapping' => 'Cadeauverpakking',
        'fee' => 'Behandelkosten',
        'refund' => 'Terugbetaling',
        'refund_adjustment' => 'Aanpassing teruggaafbedrag',
        'fee_adjustment' => 'Kosten (soort onbekend)',
        'discount_adjustment' => 'Handmatige korting',

        'message_warning_no_email' => 'De factuur bevat geen emailadres van de klant. Hierdoor kan er geen relatie in Acumulus aangemaakt of bijgewerkt worden. U kunt zelf in Acumulus een andere relatie aan deze factuur koppelen.',
        'message_warning_no_vat' => 'De factuur bevat geen BTW en er kan niet bepaald worden welk factuurtype (https://wiki.acumulus.nl/index.php?page=127) van toepassing is. Daarom is de factuur als concept opgeslagen. Corrigeer de factuur in Acumulus en controleer uw BTW instellingen.',
        'message_warning_no_vattype' => 'Door een fout in uw instellingen of BTW tarieven, kan het factuurtype (https://wiki.acumulus.nl/index.php?page=127) niet bepaald worden. Daarom is de factuur als concept opgeslagen. Corrigeer de factuur in Acumulus.',
        'message_warning_line_without_vat' => 'Één of meer van de factuurregels hebben geen BTW terwijl is ingesteld dat er "Alleen aan BTW onderhevige producten en/of diensten." aangeboden worden. Daarom is de factuur als concept opgeslagen. Corrigeer de factuur in Acumulus en controleer uw instellingen.',
        'message_warning_strategies_failed' => 'Door een fout in uw instellingen of BTW tarieven, konden niet alle fatuurregels correct gecompleteerd worden. Daarom is de factuur als concept opgeslagen. Corrigeer de factuur in Acumulus.',
        'message_warning_multiple_vattype_must_split' => 'De factuur heeft meerdere factuurtypes (https://wiki.acumulus.nl/index.php?page=127). Daarom is de factuur als concept opgeslagen. Splits de factuur in Acumulus en verdeel de regels over beide facturen gebaseerd op het BTW type waar de regel onder valt.',
        'message_warning_multiple_vattype_may_split' => 'De factuur kan meerdere factuurtypes hebben (https://wiki.acumulus.nl/index.php?page=127). Daarom is de factuur als concept opgeslagen. Controleer het BTW type van de factuur in Acumulus en corrigeer deze indien nodig, of splits de factuur en verdeel de regels over beide facturen gebaseerd op het BTW type waar de regel onder valt.',
        'message_warning_no_pdf' => 'Vanwege deze waarschuwing is er ook geen PDF factuur naar de klant verstuurd. U dient dit handmatig alsnog te doen.',
    );

    protected $en = array(
        Source::Order => 'order',
        Source::CreditNote => 'credit note',
        Source::Other => 'other',

        'plural_Order'  => 'orders',
        'plural_CreditNote' => 'creditnotes',
        'plural_Other' => 'other',

        'for' => 'for',
        'vat' => 'VAT',
        'inc_vat' => 'incl. VAT',
        'ex_vat' => 'ex. VAT',
        'shipping_costs' => 'Shipping costs',
        'free_shipping' => 'Free shipping',
        'pickup' => 'In store pick-up',
        'payment_costs' => 'Payment fee',
        'discount' => 'Discount',
        'discount_code' => 'Coupon code',
        'coupon_code' => 'Voucher',
        'used' => 'used',
        'gift_wrapping' => 'Gift wrapping',
        'fee' => 'Order treatment costs',
        'refund' => 'Refund',
        'refund_adjustment' => 'Refund adjustment',
        'fee_adjustment' => 'Fee (unknown type)',
        'discount_adjustment' => 'Manual discount',

        'message_warning_no_email' => 'The invoice does not have a customer email adrress. Therefore we could not create or update a relation in Acumulus. You can connect a relation to this invoice yourself.',
        'message_warning_no_vat' => 'The invoice does not have VAT and it is not possible to determine the invoice type (https://wiki.acumulus.nl/index.php?page=127). The invoice has been saved as concept. Correct the invoice in acumulus and check your VAT settings.',
        'message_warning_no_vattype' => 'Due to an error in your settings or VAT rates, we could not determine the invoice type for the invoice (https://wiki.acumulus.nl/index.php?page=127). The invoice has been saved as concept. Correct the invoice in Acumulus.',
        'message_warning_line_without_vat' => 'One or more of your invoice lines do not have VAT while you configured that you sell "Only products or services that are VAT liable.". The invoice has been saved as concept. Correct the invoice in Acumulus and check your settings.',
        'message_warning_strategies_failed' => 'Due to an error in your settings or VAT rates, we could not complete all invoice lines correctly. The invoice has been saved as concept. Correct the invoice in Acumulus.',
        'message_warning_multiple_vattype_must_split' => 'The invoice has multiple invoice types (https://wiki.acumulus.nl/index.php?page=127). The invoice has been saved as concept. Split the invoice in Acumulus and divide the invoice lines over both invoices based on the VAT type the line belongs to.',
        'message_warning_multiple_vattype_may_split' => 'The invoice can have multiple invoice types (https://wiki.acumulus.nl/index.php?page=127). The invoice has been saved as concept. Check the VAT type of the invoice in Acumulus and correct if necessary, or split the invoice and divide the invoice lines over both invoices based on the VAT type the line belongs to.',
        'message_warning_no_pdf' => 'Because of this warning no invoice PDF has been sent. You will have to do so manually.',
    );
}
