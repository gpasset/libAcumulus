<?php
namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Helpers\ContainerInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\ModuleTranslations;

/**
 * OcHelper contains functionality shared between the OC1, OC2 and OC3
 * controllers and models, for both admin and catalog.
 */
class OcHelper
{
    /** @var \Siel\Acumulus\Helpers\ContainerInterface */
    protected $container = null;

    /** @var array */
    public $data;

    /** @var \Siel\Acumulus\OpenCart\Helpers\Registry */
    protected $registry;

    /**
     * OcHelper constructor.
     *
     * @param \Registry $registry
     * @param \Siel\Acumulus\Helpers\ContainerInterface $container
     *
     * @throws \ReflectionException
     */
    public function __construct(\Registry $registry, ContainerInterface $container)
    {
        $this->container = $container;
        $this->registry = $this->container->getInstance('Registry', 'Helpers', array($registry));
        $this->data = array();

        $languageCode = $this->registry->language->get('code');
        if (empty($languageCode)) {
            $languageCode = 'nl';
        }
        $this->container->setLanguage($languageCode)->getTranslator()->add(new ModuleTranslations());
    }

    protected function addError($message)
    {
        if (is_array($message)) {
            $this->data['error_messages'] = array_merge($this->data['error_messages'], $message);
        } else {
            $this->data['error_messages'][] = $message;
        }
    }

    protected function addWarning($message)
    {
        if (is_array($message)) {
            $this->data['warning_messages'] = array_merge($this->data['warning_messages'], $message);
        } else {
            $this->data['warning_messages'][] = $message;
        }
    }

    protected function addSuccess($message)
    {
        $this->data['success_messages'][] = $message;
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t($key)
    {
        return $this->container->getTranslator()->get($key);
    }

    /**
     * Returns the location of the module.
     *
     * @return string
     *   The location of the module.
     */
    public function getLocation()
    {
        return $this->registry->getLocation();
    }

    /**
     * Install controller action, called when the module is installed.
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function install()
    {
        // Call the actual install method.
        $this->doInstall();

        return empty($this->data['error_messages']);
    }

    /**
     * Uninstall function, called when the module is uninstalled by an admin.
     *
     * @throws \Exception
     */
    public function uninstall()
    {
        // "Disable" (delete) events, regardless the confirmation answer.
        $this->uninstallEvents();
        $this->registry->response->redirect($this->registry->getLink($this->getLocation() . '/confirmUninstall'));
    }

    /**
     * Controller action: show/process the settings form for this module.
     *
     * @throws \Exception
     */
    public function config()
    {
        $this->displayFormCommon('config');

        // Are we posting? If not so, handle this as a trigger to update.
        if ($this->registry->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->doUpgrade();
        }

        // Add an intermediate level to the breadcrumb.
        $this->data['breadcrumbs'][] = array(
            'text' => $this->t('modules'),
            'href' => Registry::getInstance()->getLink('extension/module'),
            'separator' => ' :: '
        );

        $this->renderFormCommon('config', 'button_save');
    }

    /**
     * Controller action: show/process the settings form for this module.
     */
    public function advancedConfig()
    {
        $this->displayFormCommon('advanced');
        $this->renderFormCommon('advanced', 'button_save');
    }

    /**
     * Controller action: show/process the settings form for this module.
     */
    public function batch()
    {
        $this->displayFormCommon('batch');
        $this->renderFormCommon('batch', 'button_send');
    }

    /**
     * Explicit confirmation step to allow to retain the settings.
     *
     * The normal uninstall action will unconditionally delete all settings.
     *
     * @throws \Exception
     */
    public function confirmUninstall()
    {
        // @todo: implement uninstall form
//        $this->displayFormCommon('uninstall');
//
//        // Are we confirming, or should we show the confirm message?
//        if ($this->registry->request->server['REQUEST_METHOD'] === 'POST') {
            $this->doUninstall();
            $this->registry->response->redirect($this->registry->getLink($this->getRedirectUrl()));
//        }
//
//        // Add an intermediate level to the breadcrumb.
//        $this->data['breadcrumbs'][] = array(
//            'text' => $this->t('modules'),
//            'href' => $this->registry->getLink('extension/module',),
//            'separator' => ' :: '
//        );
//
//        $this->renderFormCommon('confirmUninstall', 'button_confirm_uninstall');
    }

    /**
     * Returns the url to redirect to after the uninstall action completes.
     *
     * @return string
     *   The url to redirect to after uninstall.
     */
    protected function getRedirectUrl()
    {
        return 'marketplace/extension';
    }

    /**
     * Event handler that executes on the creation or update of an order.
     *
     * @param int $order_id
     */
    public function eventOrderUpdate($order_id) {
        $source = $this->container->getSource(Source::Order, $order_id);
        $this->container->getManager()->sourceStatusChange($source);
    }

    /**
     * Adds our menu-items to the admin menu.
     *
     * @param array $menus
     *   The menus part of the data as will be passed to the view.
     */
    public function eventViewColumnLeft(&$menus) {
        foreach ($menus as &$menu) {
            if ($menu['id'] === 'menu-sale') {
                $menu['children'][] = array(
                    'name' => 'Acumulus',
                    'href' => '',
                    'children' => array(
                        array(
                            'name' => $this->t('batch_form_link_text'),
                            'href' => $this->container->getShopCapabilities()->getLink('batch'),
                            'children' => array(),
                        ),
                        array(
                            'name' => $this->t('advanced_form_link_text'),
                            'href' => $this->container->getShopCapabilities()->getLink('advanced'),
                            'children' => array(),
                        ),
                    ),
                );
            }
        }
    }

    /**
     * Performs the common tasks when displaying a form.
     *
     * @param string $task
     */
    protected function displayFormCommon($task)
    {
        // This will initialize the form translations.
        $this->container->getForm($task);

        $this->registry->document->addStyle('view/stylesheet/acumulus.css');

        $this->data['success_messages'] = array();
        $this->data['warning_messages'] = array();
        $this->data['error_messages'] = array();

        // Set the page title.
        $this->registry->document->setTitle($this->t("{$task}_form_title"));
        $this->data["page_title"] = $this->t("{$task}_form_title");
        $this->data["heading_title"] = $this->t("{$task}_form_header");
        $this->data["text_edit"] = $this->t("{$task}_form_header");

        // Set up breadcrumb.
        $this->data['breadcrumbs'] = array();
        $this->data['breadcrumbs'][] = array(
            'text' => $this->t('text_home'),
            'href' => $this->registry->getLink('common/dashboard'),
            'separator' => false
        );

        $this->displayCommonParts();
    }

    /**
     * Adds the common parts (header, footer, column(s) to the display.
     */
    protected function displayCommonParts()
    {
        $this->data['header'] = $this->registry->load->controller('common/header');
        $this->data['column_left'] = $this->registry->load->controller('common/column_left');
        $this->data['footer'] = $this->registry->load->controller('common/footer');
    }

    /**
     * Performs the common tasks when processing and rendering a form.
     *
     * @param string $task
     * @param string $button
     */
    protected function renderFormCommon($task, $button)
    {
        // Process the form if it was submitted and render it again.
        $form = $this->container->getForm($task);
        $form->process();

        // Show messages.
        foreach ($form->getSuccessMessages() as $message) {
            $this->addSuccess($message);
        }
        foreach ($form->getWarningMessages() as $message) {
            $this->addWarning($this->t($message));
        }
        foreach ($form->getErrorMessages() as $message) {
            $this->addError($this->t($message));
        }

        $this->data['form'] = $form;
        $this->data['formRenderer'] = $this->container->getFormRenderer();

        // Complete the breadcrumb with the current path.
        $link = $this->getLocation();
        if ($task !== 'config') {
            $link .= "/$task";
        }
        $this->data['breadcrumbs'][] = array(
            'text' => $this->t("{$task}_form_header"),
            'href' => $this->registry->getLink($link),
            'separator' => ' :: '
        );

        // Set the action buttons (action + text).
        $this->data['action'] = $this->registry->getLink($link);
        $this->data['button_icon'] = $task === 'batch' ? 'fa-envelope-o' : ($task === 'uninstall' ? 'fa-delete' : 'fa-save');
        $this->data['button_save'] = $this->t($button);
        $this->data['cancel'] = $this->registry->getLink('common/dashboard');
        $this->data['button_cancel'] = $task === 'uninstall' ? $this->t('button_cancel_uninstall') : $this->t('button_cancel');

        $this->setOutput();
    }

    /**
     * Outputs the form.
     */
    protected function setOutput()
    {
        // Send the output.
        $this->registry->response->setOutput($this->registry->load->view($this->getLocation() . '_form', $this->data));
    }

    /**
     * Checks requirements and installs tables for this module.
     *
     * @return bool
     *   Success.
     *
     * @throws \Exception
     */
    protected function doInstall()
    {
        $result = true;
        $this->registry->load->model('setting/setting');
        $setting = $this->registry->model_setting_setting->getSetting('acumulus_siel');
        $currentDataModelVersion = isset($setting['acumulus_siel_datamodel_version']) ? $setting['acumulus_siel_datamodel_version'] : '';

        $this->container->getLog()->info('%s: current version = %s', __METHOD__, $currentDataModelVersion);

        if ($currentDataModelVersion === '' || version_compare($currentDataModelVersion, '4.0', '<')) {
            // Check requirements (we assume this has been done successfully
            // before if the data model is at the latest version).
            $requirements = $this->container->getRequirements();
            $messages = $requirements->check();
            foreach ($messages as $message) {
                $this->addError($message['message']);
                $this->container->getLog()->error($message['message']);
            }
            if (!empty($messages)) {
                return false;
            }

            // Install tables.
            if ($result = $this->container->getAcumulusEntryModel()->install()) {
                $setting['acumulus_siel_datamodel_version'] = '4.0';
                $this->registry->model_setting_setting->editSetting('acumulus_siel', $setting);
            }
        } elseif (version_compare($currentDataModelVersion, '4.4', '<')) {
            // Update table columns.
            if ($result = $this->container->getAcumulusEntryModel()->upgrade('4.4.0')) {
                $setting['acumulus_siel_datamodel_version'] = '4.4';
                $this->registry->model_setting_setting->editSetting('acumulus_siel', $setting);
            }
        }

        // Install events
        if (empty($this->data['error_messages'])) {
            $this->installEvents();
        }

        return $result;
    }

    /**
     * Uninstalls data and settings from this module.
     *
     * @return bool
     *   Whether the uninstall was successful.
     *
     * @throws \Exception
     */
    protected function doUninstall()
    {
        $this->container->getAcumulusEntryModel()->uninstall();

        // Delete all config values.
        $this->registry->load->model('setting/setting');
        $this->registry->model_setting_setting->deleteSetting('acumulus_siel');

        return true;
    }

    /**
     * Upgrades the data and settings for this module if needed.
     *
     * The install now checks for the data model and can do an upgrade instead
     * of a clean install.
     *
     * @return bool
     *   Whether the upgrade was successful.
     *
     * @throws \Exception
     */
    protected function doUpgrade()
    {
        //Install/update datamodel first.
        $result = $this->doInstall();

        $this->registry->load->model('setting/setting');
        $setting = $this->registry->model_setting_setting->getSetting('acumulus_siel');
        $currentDataModelVersion = isset($setting['acumulus_siel_datamodel_version']) ? $setting['acumulus_siel_datamodel_version'] : '';
        $apiVersion = PluginConfig::Version;

        $this->container->getLog()->info('%s: installed version = %s, API = %s', __METHOD__, $currentDataModelVersion, $apiVersion);

        if (version_compare($currentDataModelVersion, $apiVersion, '<')) {
            // Update config settings.
            if ($result = $this->container->getConfig()->upgrade($currentDataModelVersion)) {
                // Refresh settings.
                $setting = $this->registry->model_setting_setting->getSetting('acumulus_siel');
                $setting['acumulus_siel_datamodel_version'] = $apiVersion;
                $this->registry->model_setting_setting->editSetting('acumulus_siel', $setting);
            }
        }

        return $result;
    }

    /**
     * Installs our events.
     *
     * This will add them to the table 'event' from where they are registered on
     * the start of each request. The controller actions can be found in the
     * catalog controller for the catalog events and the admin controller for
     * the admin events.
     *
     * To support updating, this will also be called by the index function.
     * Therefore we will first remove any existing events from our module.
     *
     * @throws \Exception
     */
    protected function installEvents()
    {
        $this->uninstallEvents();
        $location = $this->getLocation();
        $model = $this->registry->getEventModel();
        $model->addEvent('acumulus','catalog/model/checkout/order/addOrder/after',$location . '/eventOrderUpdate');
        $model->addEvent('acumulus','catalog/model/checkout/order/addOrderHistory/after',$location . '/eventOrderUpdate');
        $model->addEvent('acumulus','admin/view/common/column_left/before',$location . '/eventViewColumnLeft');
    }

    /**
     * Removes the Acumulus event handlers from the event table.
     *
     * @throws \Exception
     */
    protected function uninstallEvents()
    {
        $this->registry->getEventModel()->deleteEventByCode('acumulus');
    }
}
