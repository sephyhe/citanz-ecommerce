<?php

namespace Cita\eCommerce\API;

use SilverStripe\Security\Member;
use SilverStripe\Control\Controller;
use Cita\eCommerce\Model\Product;
use Cita\eCommerce\Model\Variant;
use SilverStripe\Versioned\Versioned;
use Cita\eCommerce\Model\Discount;
use SilverStripe\Security\Security;

class CMSDiscountAPI extends Controller
{
    use APITrait;

    private static $allowed_actions = [
        'search_product',
        'add_product',
        'remove_product',
        'add_variant',
        'remove_variant'
    ];

    protected function handleAction($request, $action)
    {
        $member = Security::getCurrentUser();

        if (!$member || !$member->inGroup('administrators')) {
            return $this->httpError(403, 'You do not have permission!');
        }

        $header = $this->getResponse();

        if (!$request->isAjax()) {
            return $this->httpError(400, 'AJAX access only');
        }

        if (in_array($action, static::$allowed_actions)) {
            return $this->json($this->$action($request));
        }

        return $this->httpError(404, 'not allowed');
    }

    public function search_product(&$request)
    {
        if (!$request->isPost()) {
            return $this->httpError(400, 'Wrong method');
        }

        $discount_id = $request->postVar('discount_id');

        if (empty($discount_id)) {
            return $this->httpError(400, 'missing parameters');
        }

        $discount = Discount::get_by_id($discount_id);

        if (empty($discount)) {
            return $this->httpError(404, 'discount or product not found');
        }

        if ($term = $request->postVar('term')) {
            $filter = ['Title:PartialMatch' => $term];
            if ($discount->Products()->exists()) {
                $filter['ID:not'] = $discount->Products()->column('ID');
            }
            $raw = Versioned::get_by_stage(Product::class, 'Stage')->filter($filter)->limit(5);

            $products = [];

            foreach ($raw as $product) {
                $products[] = [
                    'id' => $product->ID,
                    'title' => $product->Title,
                    'product_class' => $product->ClassName
                ];
            }

            return $products;
        }

        return [];
    }

    public function add_product(&$request)
    {
        if (!$request->isPost()) {
            return $this->httpError(400, 'Wrong method');
        }

        $discount_id = $request->postVar('discount_id');
        $product_id = $request->postVar('product_id');

        if (empty($discount_id) || empty($product_id)) {
            return $this->httpError(400, 'missing parameters');
        }

        $discount = Discount::get_by_id($discount_id);
        $product = Versioned::get_by_stage(Product::class, 'Stage')->byID($product_id);

        if (empty($discount) || empty($product)) {
            return $this->httpError(404, 'discount or product not found');
        }

        $discount->Products()->add($product);

        return [
            'id' => $product->ID,
            'title' => $product->Title,
            'variants' => $product->Variants()->Data
        ];
    }

    public function remove_product(&$request)
    {
        if (!$request->isPost()) {
            return $this->httpError(400, 'Wrong method');
        }

        $discount_id = $request->postVar('discount_id');
        $product_id = $request->postVar('product_id');

        if (empty($discount_id) || empty($product_id)) {
            return $this->httpError(400, 'missing parameters');
        }

        $discount = Discount::get_by_id($discount_id);
        $product = Versioned::get_by_stage(Product::class, 'Stage')->byID($product_id);

        if (empty($discount) || empty($product)) {
            return $this->httpError(404, 'discount or product not found');
        }

        $variants = $product->Variants();

        foreach ($variants as $variant) {
            $discount->Variants()->remove($variant);
        }

        $discount->Products()->remove($product);

        return true;
    }

    public function add_variant(&$request)
    {
        if (!$request->isPost()) {
            return $this->httpError(400, 'Wrong method');
        }

        $discount_id = $request->postVar('discount_id');
        $variant_id = $request->postVar('variant_id');

        if (empty($discount_id) || empty($variant_id)) {
            return $this->httpError(400, 'missing parameters');
        }

        $discount = Discount::get_by_id($discount_id);
        $variant = Variant::get()->byID($variant_id);

        if (empty($discount) || empty($variant)) {
            return $this->httpError(404, 'discount or product not found');
        }

        $discount->Variants()->add($variant);

        return true;
    }

    public function remove_variant(&$request)
    {
        if (!$request->isPost()) {
            return $this->httpError(400, 'Wrong method');
        }

        $discount_id = $request->postVar('discount_id');
        $variant_id = $request->postVar('variant_id');

        if (empty($discount_id) || empty($variant_id)) {
            return $this->httpError(400, 'missing parameters');
        }

        $discount = Discount::get_by_id($discount_id);
        $variant = Variant::get()->byID($variant_id);

        if (empty($discount) || empty($variant)) {
            return $this->httpError(404, 'discount or product not found');
        }

        $discount->Variants()->remove($variant);

        return true;
    }
}
