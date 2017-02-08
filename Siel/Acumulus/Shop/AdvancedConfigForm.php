<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;

/**
 * Provides advanced config form handling.
 *
 * Shop specific may optionally (have to) override:
 * - systemValidate()
 * - isSubmitted()
 * - setSubmittedValues()
 */
class AdvancedConfigForm extends BaseConfigForm
{
    /**
     * {@inheritdoc}
     *
     * The results are restricted to the known config keys.
     */
    protected function setSubmittedValues()
    {
        $postedValues = $this->getPostedValues();
        // Check if the full form was displayed or only the account details.
        $fullForm = array_key_exists('salutation', $postedValues);
        foreach ($this->acumulusConfig->getKeys() as $key) {
            if (!$this->addIfIsset($this->submittedValues, $key, $postedValues)) {
                // Add unchecked checkboxes, but only if the full form was
                // displayed as all checkboxes on this form appear in the full
                // form only.
                if ($fullForm && $this->isCheckboxKey($key)) {
                    $this->submittedValues[$key] = '';
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validate()
    {
        $this->validateRelationFields();
        $this->validateOptionsFields();
        $this->validateEmailAsPdfFields();
    }

    /**
     * Validates fields in the relation management settings fieldset.
     */
    protected function validateRelationFields()
    {
        $settings = $this->acumulusConfig->getEmailAsPdfSettings();
        if ((!array_key_exists('sendCustomer', $this->submittedValues) || !(bool) $this->submittedValues['sendCustomer']) && $settings['emailAsPdf']) {
            $this->warningMessages['conflicting_options'] = $this->t('message_validate_conflicting_options');
        }
    }

    /**
     * Validates fields in the "Invoice" settings fieldset.
     */
    protected function validateOptionsFields()
    {
        if ($this->submittedValues['optionsAllOn1Line'] == PHP_INT_MAX && $this->submittedValues['optionsAllOnOwnLine'] == 1) {
            $this->errorMessages['optionsAllOnOwnLine'] = $this->t('message_validate_options_0');
        }
        if ($this->submittedValues['optionsAllOn1Line'] > $this->submittedValues['optionsAllOnOwnLine'] && $this->submittedValues['optionsAllOnOwnLine'] > 1) {
            $this->errorMessages['optionsAllOnOwnLine'] = $this->t('message_validate_options_1');
        }

        if (isset($this->submittedValues['optionsMaxLength']) && !ctype_digit($this->submittedValues['optionsMaxLength'])) {
            $this->errorMessages['optionsMaxLength'] = $this->t('message_validate_options_2');
        }
    }

    /**
     * Validates fields in the "Email as pdf" settings fieldset.
     */
    protected function validateEmailAsPdfFields()
    {
        $regexpMultiEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+([,;][^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+)*$/';

        if (!empty($this->submittedValues['emailBcc']) && !preg_match($regexpMultiEmail, $this->submittedValues['emailBcc'])) {
            $this->errorMessages['emailBcc'] = $this->t('message_validate_email_3');
        }
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the config form. At the minimum, this includes the
     * account settings. If these are OK, the other settings are included as
     * well.
     */
    public function getFieldDefinitions()
    {
        $fields = array();

        $message = $this->checkAccountSettings();
        $accountOk = empty($message);

        // Message fieldset: if account settings have not been filled in.
        if (!$accountOk) {
            $fields['accountSettingsHeader'] = array(
              'type' => 'fieldset',
              'legend' => $this->t('message_error_header'),
              'fields' => array(
                'invoiceMessage' => array(
                  'type' => 'markup',
                  'value' => $message,
                ),
              ),
            );
        }

        // 1st fieldset: Link to config form.
        $fields['configHeader'] = array(
            'type' => 'fieldset',
            'legend' => $this->t('config_form_header'),
            'fields' => $this->getConfigLinkFields(),
        );

        if ($accountOk) {
            $fields += array(
                'tokenHelpHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('tokenHelpHeader'),
                    'description' => $this->t('desc_tokens'),
                    'fields' => $this->getTokenFields(),
                ),
                'relationSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('relationSettingsHeader'),
                    'description' => $this->t('desc_relationSettingsHeader'),
                    'fields' => $this->getRelationFields(),
                ),
                'invoiceSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('invoiceSettingsHeader'),
                    'fields' => $this->getInvoiceFields(),
                ),
                'optionsSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('optionsSettingsHeader'),
                    'description' => $this->t('desc_optionsSettingsHeader'),
                    'fields' => $this->getOptionsFields(),
                ),
                'emailAsPdfSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('emailAsPdfSettingsHeader'),
                    'description' => $this->t('desc_emailAsPdfSettings'),
                    'fields' => $this->getEmailAsPdfFields(),
                ),
                'versionInformationHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('versionInformationHeader'),
                    'fields' => $this->getVersionInformation(),
                ),
            );
        }

        return $fields;
    }

    /**
     *
     *
     *
     * @return array
     *   The set of possible tokens per object
     */
    protected function getTokenFields() {
        return $this->tokenInfo2Fields($this->shopCapabilities->getTokenInfo());
    }

    /**
     * Returns a set of token info fields based on the shop specific token info.
     *
     * @param string[][][] $tokenInfo
     *
     * @return array
     *   Form fields.
     */
    protected function tokenInfo2Fields(array $tokenInfo)
    {
        $fields = array();
        foreach ($tokenInfo as $variableName => $variableInfo) {
            $fields["token-$variableName"] = $this->get1TokenField($variableName, $variableInfo);
        }
        return $fields;
    }

    /**
     * Returns a set of token info fields based on the shop specific token info.
     *
     * @param string $variableName
     * @param string[][] $variableInfo
     *
     * @return array Form fields.
     * Form fields.
     */
    protected function get1TokenField($variableName, array $variableInfo)
    {
        $value = "<p class='property-name'><strong>$variableName</strong>";

        if (!empty($variableInfo['more-info'])) {
            $value .= ' ' . $variableInfo['more-info'];
        }
        elseif (!empty($variableInfo['class'])) {
            if (!empty($variableInfo['file'])) {
                $value .= ' (' . sprintf($this->t('see_class_file'), $variableInfo['class'], $variableInfo['file']) . ')';
            }
            else {
                $value .= ' (' . sprintf($this->t('see_class'), $variableInfo['class']) . ')';
            }
        }
        elseif (!empty($variableInfo['table'])) {
            $value .= ' (' . $this->seeTable('see_table', 'see_tables', $variableInfo['table']) . ')';
        }
        elseif (!empty($variableInfo['file'])) {
            $value .= ' (' . sprintf($this->t('see_file'), $variableInfo['file']) . ')';
        }

        $value .= ':</p>';

        if (!empty($variableInfo['properties'])) {
            $value .= '<ul class="property-list">';
            foreach ($variableInfo['properties'] as $property) {
                $value .= "<li>$property</li>";
            }

            if (!empty($variableInfo['properties-more'])) {
                if (!empty($variableInfo['class'])) {
                    $value .= '<li>' . sprintf($this->t('see_class_more'), $variableInfo['class']) . '</li>';
                }
                elseif (!empty($variableInfo['table'])) {
                    $value .= '<li>' . $this->seeTable('see_table_more', 'see_tables_more', $variableInfo['table']) . '</li>';
                }
            }
            $value .= '</ul>';
        }

        return array(
            'type'=> 'markup',
            'value' => $value,
        );
    }

    /**
     * Converts the contents of table to a human readable string.
     *
     * @param string $keySingle
     * @param string $keyPlural
     * @param string|array $table
     *
     * @return string
     */
    protected function seeTable($keySingle, $keyPlural, $table)
    {
        if (is_array($table)) {
            if (count($table) > 1) {
                $tableLast = array_pop($table);
                $tableBeforeLast = array_pop($table);
                array_push($table, $tableBeforeLast . ' ' . $this->t('and') . ' ' . $tableLast);
                $result = sprintf($this->t($keyPlural), implode(', ', $table));
            }
            else {
                $result = sprintf($this->t($keySingle), reset($table));
            }
        }
        else {
            $result = sprintf($this->t($keySingle), $table);
        }
        return $result;
    }

    /**
     * Returns the set of relation management fields.
     *
     * The fields returned:
     * - defaultCustomerType
     * - contactStatus
     * - salutation
     * - clientData
     *
     * @return array[]
     *   The set of relation management fields.
     */
    protected function getRelationFields()
    {
        $fields = array(
            'clientData' => array(
                'type' => 'checkbox',
                'label' => $this->t('field_clientData'),
                'description' => $this->t('desc_clientData'),
                'options' => array(
                    'sendCustomer' => $this->t('option_sendCustomer'),
                    'overwriteIfExists' => $this->t('option_overwriteIfExists'),
                ),
            ),
            'defaultCustomerType' => array(
                'type' => 'select',
                'label' => $this->t('field_defaultCustomerType'),
                'options' => $this->picklistToOptions($this->contactTypes, 'contacttypes', 0, $this->t('option_empty')),
            ),
            'contactStatus' => array(
                'type' => 'radio',
                'label' => $this->t('field_contactStatus'),
                'description' => $this->t('desc_contactStatus'),
                'options' => $this->getContactStatusOptions(),
            ),
            'contactYourId' => array(
                'type' => 'text',
                'label' => $this->t('field_contactYourId'),
                'description' => $this->t('desc_contactYourId') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'companyName1' => array(
                'type' => 'text',
                'label' => $this->t('field_companyName1'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'companyName2' => array(
                'type' => 'text',
                'label' => $this->t('field_companyName2'),
                'description' => $this->t('msg_tokens'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'vatNumber' => array(
                'type' => 'text',
                'label' => $this->t('field_vatNumber'),
                'description' => $this->t('desc_vatNumber') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'fullName' => array(
                'type' => 'text',
                'label' => $this->t('field_fullName'),
                'description' => $this->t('desc_fullName') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'salutation' => array(
                'type' => 'text',
                'label' => $this->t('field_salutation'),
                'description' => $this->t('desc_salutation') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'address1' => array(
                'type' => 'text',
                'label' => $this->t('field_address1'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'address2' => array(
                'type' => 'text',
                'label' => $this->t('field_address2'),
                'description' => $this->t('desc_address') . ' ' . $this->t('msg_tokens'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'postalCode' => array(
                'type' => 'text',
                'label' => $this->t('field_postalCode'),
                'description' => $this->t('msg_token'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'city' => array(
                'type' => 'text',
                'label' => $this->t('field_city'),
                'description' => $this->t('msg_token'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'telephone' => array(
                'type' => 'text',
                'label' => $this->t('field_telephone'),
                'description' => $this->t('desc_telephone') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'fax' => array(
                'type' => 'text',
                'label' => $this->t('field_fax'),
                'description' => $this->t('desc_fax') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'email' => array(
                'type' => 'email',
                'label' => $this->t('field_email'),
                'description' => $this->t('msg_token'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
        );
        return $fields;
    }

    /**
     * Returns the set of invoice related fields.
     *
     * The fields returned:
     * - sendEmptyInvoice
     * - sendEmptyShipping
     *
     * @return array[]
     *   The set of invoice related fields.
     */
    protected function getInvoiceFields()
    {
        $fields = array(
            'sendWhat' => array(
                'type' => 'checkbox',
                'label' => $this->t('field_sendWhat'),
                'description' => $this->t('desc_sendWhat'),
                'options' => array(
                    'sendEmptyInvoice' => $this->t('option_sendEmptyInvoice'),
                    'sendEmptyShipping' => $this->t('option_sendEmptyShipping'),
                ),
            ),
        );
        return $fields;
    }

    /**
     * Returns the set of options related fields.
     *
     * The fields returned:
     * - optionsAllOn1Line
     * - optionsAllOnOwnLine
     * - optionsMaxLength
     *
     * @return array[]
     *   The set of options related fields.
     */
    protected function getOptionsFields()
    {
        $fields = array(
            'optionsAllOn1Line' => array(
                'type' => 'select',
                'label' => $this->t('field_optionsAllOn1Line'),
                'options' => array(
                    0 => $this->t('option_do_not_use'),
                    PHP_INT_MAX => $this->t('option_always'),
                ) + array_combine(range(1, 10), range(1, 10)),
            ),
            'optionsAllOnOwnLine' => array(
                'type' => 'select',
                'label' => $this->t('field_optionsAllOnOwnLine'),
                'options' => array(
                    PHP_INT_MAX => $this->t('option_do_not_use'),
                    1 => $this->t('option_always'),
                ) + array_combine(range(2, 10), range(2, 10)),
            ),
            'optionsMaxLength' => array(
                'type' => 'number',
                'label' => $this->t('field_optionsMaxLength'),
                'description' => $this->t('desc_optionsMaxLength'),
                'attributes' => array(
                    'min' => 1,
                ),
            ),
        );
        return $fields;
    }

    /**
     * Returns the set of 'email invoice as PDF' related fields.
     *
     * The fields returned:
     * - emailAsPdf
     * - emailFrom
     * - emailBcc
     * - subject
     *
     * @return array[]
     *   The set of 'email invoice as PDF' related fields.
     */
    protected function getEmailAsPdfFields()
    {
        return array(
            'emailBcc' => array(
                'type' => 'email',
                'label' => $this->t('field_emailBcc'),
                'description' => $this->t('desc_emailBcc'),
                'attributes' => array(
                    'multiple' => true,
                    'size' => 30,
                ),
            ),
        );
    }

    /**
     * Returns the set of fields introducing the advanced config forms.
     *
     * The fields returned:
     * - tellAboutAdvancedSettings
     * - advancedSettingsLink
     *
     * @return array[]
     *   The set of fields introducing the advanced config form.
     */
    protected function getConfigLinkFields()
    {
        return array(
            'tellAboutBasicSettings' => array(
                'type' => 'markup',
                'value' => sprintf($this->t('desc_basicSettings'), $this->t('config_form_link_text'), $this->t('menu_basicSettings')),
            ),
            'basicSettingsLink' => array(
                'type' => 'markup',
                'value' => sprintf($this->t('button_link'), $this->t('config_form_link_text') , $this->shopCapabilities->getLink('config')),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getCheckboxKeys()
    {
        return array(
            'sendCustomer' => 'clientData',
            'overwriteIfExists' => 'clientData',
            'sendEmptyInvoice' => 'sendWhat',
            'sendEmptyShipping' => 'sendWhat',
        );
    }

    protected function getContactStatusOptions()
    {
        return array(
            InvoiceConfigInterface::ContactStatus_Active => $this->t('option_contactStatus_Active'),
            InvoiceConfigInterface::ContactStatus_Disabled => $this->t('option_contactStatus_Disabled'),
        );
    }
}
