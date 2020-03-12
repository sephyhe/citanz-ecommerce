<?php

namespace Cita\eCommerce\Extension;

use SilverStripe\Forms\CurrencyField;
use SilverStripe\Forms\HTMLEditor\HtmlEditorField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataExtension;
use Cita\eCommerce\Model\Variant;

class ProductVariantCommonFields extends DataExtension
{
    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'SKU'                   =>  'Varchar(64)',
        'OutOfStock'            =>  'Boolean',
        'Price'                 =>  'Currency',
        'UnitWeight'            =>  'Decimal',
        'ShortDesc'             =>  'HTMLText',
        'StockCount'            =>  'Int',
        'StockLowWarningPoint'  =>  'Int',
        'SpecialPrice'          =>  'Currency',
        'SpecialFromDate'       =>  'Datetime',
        'SpecialToDate'         =>  'Datetime',
        'SortingPrice'          =>  'Currency'
    ];

    private static $indexes = [
        'SKU'   =>  [
            'type'      =>  'unique'
        ]
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'Image' =>  Image::class
    ];
/**
     * Relationship version ownership
     * @var array
     */
    private static $owns = [
        'Image'
    ];

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->owner;

        if ($owner->isDigital) {
            $fields->removeByName([
                'UnitWeight'
            ]);
        }

        $fields->addFieldsToTab(
            'Root.Main',
            [
                HtmlEditorField::create(
                    'ShortDesc',
                    'Short Description'
                ),
                HtmlEditorField::create('Content'),
            ],
            'Content'
        );

        $fields->addFieldToTab(
            'Root.Main',
            HtmlEditorField::create(
                'ShortDesc',
                'Short Description'
            ),
            'Content'
        );

        $fields->removeByName([
            'isExempt',
            'isDigital',
            'SortingPrice'
        ]);

        $fields->addFieldsToTab(
            'Root.ProductDetails',
            [
                TextField::create('SKU', 'SKU'),
                UploadField::create(
                    'Image',
                    'Product Image'
                ),
                CheckboxField::create(
                    'isDigital',
                    'is Digital Product'
                )->setDescription('means no freight required'),
                CheckboxField::create(
                    'NoDiscount',
                    'This product does not accept any discout'
                ),
                CheckboxField::create(
                    'isExempt',
                    'This product is not subject to GST'
                ),
                CurrencyField::create('Price'),
                TextField::create('UnitWeight')->setDescription('in KG. If you are not charging the freight cost on weight, leave it 0.00'),
                CheckboxField::create('OutOfStock', 'Out of Stock')
            ]
        );


        $fields->addFieldsToTab(
            'Root.Inventory',
            [
                TextField::create(
                    'StockCount',
                    'Stock Count'
                ),
                TextField::create(
                    'StockLowWarningPoint',
                    'StockLow Warning Point'
                )
            ]
        );

        $fields->addFieldsToTab(
            'Root.Promotion',
            [
                CurrencyField::create(
                    'SpecialPrice',
                    'Special Price'
                ),
                DatetimeField::create(
                    'SpecialFromDate',
                    'From'
                ),
                DatetimeField::create(
                    'SpecialToDate',
                    'To'
                )
            ]
        );

        return $fields;
    }

    public function getBaseData()
    {
        return [
            'id'            =>  $this->owner->ID,
            'sku'           =>  $this->owner->SKU,
            'class'         =>  $this->owner->ClassName,
            'price'         =>  $this->owner->Price,
            'special_price' =>  $this->owner->get_special_price(),
            'special_rate'  =>  $this->owner->calc_special_price_discount_rate(),
            'image'         =>  $this->owner->Image()->exists() ?
                                $this->owner->Image()->getAbsoluteURL() : null
        ];
    }


    public function get_special_price()
    {
        if (!empty($this->owner->SpecialPrice)) {
            if (empty($this->owner->SpecialToDate)) {
                if (strtotime($this->owner->SpecialFromDate) <= time()) {
                    return $this->owner->SpecialPrice;
                }
            } elseif (strtotime($this->owner->SpecialToDate) >= time()) {
                return $this->owner->SpecialPrice;
            }
        }

        return null;
    }

    public function calc_special_price_discount_rate()
    {
        if ($this->owner->get_special_price()) {
            $n      =   (float) $this->owner->get_special_price();
            $price  =   (float) $this->owner->Price;
            return ceil(($price - $n) / $price * -100);
        }

        return null;
    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->owner->isDigital) {
            $this->owner->UnitWeight    =   0;
        }

        $this->owner->SortingPrice =    !empty($this->owner->get_special_price()) ? $this->owner->get_special_price() : $this->owner->Price;
    }
}
