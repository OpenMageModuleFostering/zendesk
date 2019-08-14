<?php
/**
 * Zendesk Magento integration
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to The MIT License (MIT) that is bundled with
 * this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/mit-license.php
 *
 * @copyright Copyright (c) 2012 Zendesk (www.zendesk.com)
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */

class Zendesk_Zendesk_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function getUrl($object = '', $id = null, $format = 'old')
    {
        $protocol = 'https://';
        $domain = Mage::getStoreConfig('zendesk/general/domain');
        $root = ($format === 'old') ? '' : '/agent/#';

        $base = $protocol . $domain . $root;

        switch($object) {
            case '':
                return $base;
                break;

            case 'ticket':
                return $base . '/tickets/' . $id;
                break;

            case 'user':
                return $base . '/users/' . $id;
                break;

            case 'raw':
                return $protocol . $domain . '/' . $id;
                break;
        }
    }

    public function getApiToken($generate = true)
    {
        // Grab any existing token from the admin scope
        $token = Mage::getStoreConfig('zendesk/api/token', 0);

        if( (!$token || strlen(trim($token)) == 0) && $generate) {
            $token = $this->setApiToken();
        }

        return $token;
    }

    public function setApiToken($token = null)
    {
        if(!$token) {
            $token = md5(time());
        }
        Mage::getModel('core/config')->saveConfig('zendesk/api/token', $token, 'default');

        return $token;
    }

    /**
     * Returns the provisioning endpoint for new setups.
     *
     * This uses the config/zendesk/provision_url XML path to retrieve the setting, with a default value set in
     * the extension config.xml file. This can be overridden in your website's local.xml file.
     * @return null|string URL or null on failure
     */
    public function getProvisionUrl()
    {
        $config = Mage::getConfig();
        $data = $config->getNode('zendesk/provision_url');
        if(!$data) {
            return null;
        }
        return (string)$data;
    }

    public function getProvisionToken($generate = false)
    {
        $token = Mage::getStoreConfig('zendesk/hidden/provision_token', 0);

        if( (!$token || strlen(trim($token)) == 0) && $generate) {
            $token = $this->setProvisionToken();
        }

        return $token;
    }

    public function setProvisionToken($token = null)
    {
        if(!$token) {
            $token = md5(time());
        }

        Mage::getModel('core/config')->saveConfig('zendesk/hidden/provision_token', $token, 'default');

        return $token;
    }

    public function getOrderDetail($order)
    {
        $orderInfo = array(
            'id' => $order->getIncrementId(),
            'status' => $order->getStatus(),
            'created' => $order->getCreatedAt(),
            'updated' => $order->getUpdatedAt(),
            'customer' => array(
                'name' => $order->getCustomerName(),
                'email' => $order->getCustomerEmail(),
                'ip' => $order->getRemoteIp(),
                'guest' => (bool)$order->getCustomerIsGuest(),
            ),
            'store' => $order->getStoreName(),
            'total' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'items' => array(),
            'admin_url' => Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/view', array('order_id' => $order->getId())),
        );

        foreach($order->getItemsCollection(array(), true) as $item) {
            $orderInfo['items'][] = array(
                'sku' => $item->getSku(),
                'name' => $item->getName(),
            );
        }

        return $orderInfo;
    }

    public function getSupportEmail($store = null)
    {
        $domain = Mage::getStoreConfig('zendesk/general/domain', $store);
        $email = 'support@' . $domain;

        return $email;
    }

    public function loadCustomer($email, $website = null)
    {
        $customer = null;

        if(Mage::getModel('customer/customer')->getSharingConfig()->isWebsiteScope()) {
            // Customer email address can be used in multiple websites so we need to
            // explicitly scope it
            if($website) {
                // We've been given a specific website, so try that
                $customer = Mage::getModel('customer/customer')
                    ->setWebsiteId($website)
                    ->loadByEmail($email);
            } else {
                // No particular website, so load all customers with the given email and then return a single object
                $customers = Mage::getModel('customer/customer')
                    ->getCollection()
                    ->addFieldToFilter('email', array('eq' => array($email)));
                if($customers->getSize()) {
                    $id = $customers->getLastItem()->getId();
                    $customer = Mage::getModel('customer/customer')->load($id);
                }
            }

        } else {
            // Customer email is global, so no scoping issues
            $customer = Mage::getModel('customer/customer')->loadByEmail($email);
        }

        return $customer;
    }
}
