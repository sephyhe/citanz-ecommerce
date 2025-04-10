<?php

namespace Cita\eCommerce\Admin;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Dev\Debug;
use SilverStripe\Admin\ModelAdmin;
use Cita\eCommerce\Model\Order;
use Cita\eCommerce\Model\SubscriptionOrder;
use SilverStripe\Security\Member;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\TextField;

/**
 * Description
 *
 * @package silverstripe
 * @subpackage mysite
 */
class OrderAdmin extends ModelAdmin
{
    private static $hide_pending = true;
    /**
     * Managed data objects for CMS
     * @var array
     */
    private static $managed_models = [
        Order::class,
        SubscriptionOrder::class,
    ];

    /**
     * URL Path for CMS
     * @var string
     */
    private static $url_segment = 'orders';

    /**
     * Menu title for Left and Main CMS
     * @var string
     */
    private static $menu_title = 'Orders';

    private static $menu_icon = 'cita/ecommerce: client/img/shopping-cart.png';

    public function getList()
    {
        $list = parent::getList();
        $list = $list->filter(['ClassName' => $this->modelClass]);

        if (!empty($this->getRequest()->postVar('filter'))) {
            $params = $this->getRequest()->postVar('filter');

            if (!empty($params['Cita-eCommerce-Model-Order']) && !empty($params['Cita-eCommerce-Model-Order']['NonPendings'])) {
                return $list->exclude(['Status' => 'Pending']);
            }

            if ($this->hasMethod('FilterList')) {
                return $this->FilterList($list, $params);
            }
        }

        return $list;
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        if ($this->modelClass == Order::class) {
            $form->Fields()
                ->fieldByName($this->sanitiseClassName($this->modelClass))
                ->getConfig()
                ->getComponentByType(GridFieldDetailForm::class)
                ->setItemRequestClass(OrderGridFieldDetailForm_ItemRequest::class);

            if ($gridField = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass))) {
                if ($gridField instanceof GridField) {
                    $config = $gridField->getConfig();
                    $dataColumns = $config->getComponentByType(GridFieldDataColumns::class);
                    $dataColumns->setDisplayFields([
                        'ID' => 'Order#',
                        'CustomerReference' => 'Ref#',
                        'ItemCount' => 'Item(s)',
                        'ShippingCustomerFullname' => 'Customer',
                        'CartItemList' => 'Details',
                        'CommentText' => 'Comment',
                        'PayableTotal' => 'Amount',
                        'Status' => 'Status',
                        'TrakeStatus' => 'Tracking Status',
                        'Paidat' => 'Paid at'
                    ])->setFieldCasting([
                        'TotalAmount' => 'Currency->Nice',
                        'PayableTotal' => 'Currency->Nice',
                        'CartItemList' => 'HTMLText->RAW',
                        'Paidat' => 'Datetime->Nice'
                    ]);

                    if($this->modelClass == Order::class) {
                        $filter = $config->getComponentByType(GridFieldFilterHeader::class);
                        $context = $filter->getSearchContext($gridField);
                        $context->getFields()->insertBefore('CustomerReference', TextField::create(
                            'ID',
                            'Order#'
                        ));
                        $context->getFields()->insertBefore('Status', TextField::create(
                            'ProductName',
                            'Product'
                        ));
                        $context->getFields()->insertBefore('Status', CheckboxField::create(
                            'NonPendings',
                            'Non-pendings'
                        ));
                    }
                }
            }
        }

        return $form;
    }
}
