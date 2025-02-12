<?php

namespace Cita\eCommerce;

use SilverStripe\Core\Convert;
use SilverStripe\Dev\Debug;
use SilverStripe\Security\Security;
use SilverStripe\Control\Session;
use Cita\eCommerce\Model\Customer;
use Cita\eCommerce\Model\Order;
use Cita\eCommerce\Model\SubscriptionOrder;
use Cita\eCommerce\Model\Freight;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Environment;
use SilverStripe\Security\Member;
use Cita\eCommerce\Model\Catalog;
use SilverStripe\Control\Director;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Control\Controller;

class eCommerce
{
    public static function get_subscription_cart($order_id = null)
    {
        if (!empty($order_id)) {
            return Order::get()->byID($order_id);
        }

        $member = Security::getCurrentUser();

        if (!static::can_order($member)) {
            return null;
        }

        if ($member && $member->inGroup('customers')) {
            return $member->Orders()->filter(['ClassName' => SubscriptionOrder::class, 'Status' => 'Pending'])->first();
        }

        $order = static::retrieve_order_by_session(SubscriptionOrder::class, 'subscription_cart_id');

        if ($order) {
            return $order;
        }

        return static::retrieve_subscription_order_by_cookie();
    }

    public static function get_cart($order_id = null)
    {
        if (!empty($order_id)) {
            return Order::get()->byID($order_id);
        }

        $member =   Security::getCurrentUser();

        if ($controller = Controller::curr()) {
            if ($order_id = $controller->request->getVar('order_id')) {
                $order_id = Convert::raw2sql($order_id);
                if ($order = Order::get()->byID($order_id)) {
                    if ($order->canView($member)) {
                        return $order;
                    }
                }
            }
        }

        if (!static::can_order($member)) {
            return null;
        }

        // CartlessProducts is typically used for things like membership and subscription
        $excluding  =   Config::inst()->get(__CLASS__, 'CartlessProducts');

        if ($member && $member->inGroup('customers')) {
            return static::retrieve_order_by_customer($member, $excluding);
        }

        $order  =   static::retrieve_order_by_session();

        if ($order) {
            return $order;
        }

        return static::retrieve_order_by_cookie($excluding);
    }

    public static function get_catalog_url()
    {
        if ($catalog = Catalog::get()->first()) {
            return $catalog->Link();
        }

        return null;
    }

    public static function get_last_processed_cart($order_id = null)
    {
        $cart   =   static::get_cart($order_id);

        if (!$cart) return null;

        if ($customer = Security::getCurrentUser()) {
            if ($cart->CustomerID == $customer->ID || $customer->inGroup('administrators')) {
                return $cart;
            }
        } elseif ($cart->AnonymousCustomer == Cookie::get('eCommerceCookie')) {
            return $cart;
        }

        return null;
    }

    public static function retrieve_order_by_customer(&$member, $excluding)
    {
        return $member->Orders()->exclude(['ClassName' => $excluding])->filter(['Status' => 'Pending'])->first();
    }

    public static function retrieve_subscription_order_by_session()
    {
        $request    =   Injector::inst()->get(HTTPRequest::class);
        $session    =   $request->getSession();
        $cart_id    =   $session->get('subscription_cart_id');

        if (!empty($cart_id)) {
            if ($order = SubscriptionOrder::get()->byID($cart_id)) {
                if ($order->Status == 'Pending') {
                    return $order;
                }
            }
        }

        return null;
    }

    public static function retrieve_order_by_session($class = Order::class, $session_key = 'cart_id')
    {
        $request    =   Injector::inst()->get(HTTPRequest::class);
        $session    =   $request->getSession();
        $cart_id    =   $session->get($session_key);

        if (!empty($cart_id)) {
            if ($order = $class::get()->byID($cart_id)) {
                if ($order->Status == 'Pending') {
                    return $order;
                }
            }
        }

        return null;
    }

    public static function retrieve_subscription_order_by_cookie()
    {
        $cookie = Cookie::get('eCommerceCookie');

        if (!empty($cookie)) {
            if ($order = SubscriptionOrder::get()->filter(['AnonymousCustomer' => $cookie, 'Status' => 'Pending'])->first()) {
                Injector::inst()->get(HTTPRequest::class)->getSession()->set('subscription_cart_id', $order->id);
                return $order;
            }
        }

        return null;
    }

    public static function retrieve_order_by_cookie($excluding)
    {
        $cookie =   Cookie::get('eCommerceCookie');

        if (!empty($cookie)) {
            if ($order  =   Order::get()->exclude(['ClassName' => $excluding])->filter(['ClassName' => Order::class, 'AnonymousCustomer' => $cookie, 'Status' => 'Pending'])->first()) {
                Injector::inst()->get(HTTPRequest::class)->getSession()->set('cart_id', $order->id);
                return $order;
            }
        }

        return null;
    }

    public static function can_order(&$member)
    {
        if (empty($member)) {
            return Config::inst()->get(__CLASS__, 'AllowAnonymousCustomer');
        }

        return $member->ClassName == Customer::class || $member->inGroup('administrators');
    }

    public static function get_available_payment_methods()
    {
        return GatewayInfo::getSupportedGateways();
    }

    public static function get_all_countries()
    {
        $list       =   [];
        $zones      =   Config::inst()->get(Freight::class, 'allowed_countries');

        foreach ($zones as $zone => $countries) {
            foreach ($countries as $code => $country) {
                $list[$code]    =   $country;
            }
        }

        asort($list);

        return $list;
    }

    public static function translate_country($code)
    {
        if ($code) {
            $code = strtolower($code) == 'new zealand' ? 'nz' : $code;
            return static::get_all_countries()[$code];
        }

        return null;
    }

    public static function get_freight_options()
    {
        return Freight::get()->exclude(['Disabled' => true]);
    }
}
