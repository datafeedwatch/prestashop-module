<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to a commercial license from DataFeedWatch
 * Use, copy, modification or distribution of this source file without written
 * license agreement from the DataFeedWatch is strictly forbidden.
 * In order to obtain a license, please contact us: DataFeedWatch.com
 *
 * @author    DataFeedWatch
 * @copyright Copyright (c) 2017-2020 DataFeedWatch
 * @license   Commercial license
 * @package   DataFeedWatchResponseModule
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class DataFeedWatch extends Module
{
    const CONFIG_TOKEN = 'DFW_TOKEN';
    const CONFIG_ONLY_ACTIVE = 'DFW_ONLY_ACTIVE';
    const CONFIG_ONLY_STOCK = 'DFW_ONLY_STOCK';
    const SUBMIT_GENERATE = 'submitGenerate';
    const SUBMIT_CONFIGURATION = 'submitConfiguration';
    const MIN_PHP_VERSION = "5.6";
    const WEIGHT_NAME = 'kg';
    const LENGTH_NAME = 'cm';

    private $languages;
    private $defaultLanguageId;

    public function __construct()
    {
        $this->name = 'datafeedwatch';
        $this->tab = 'market_place';
        $this->version = '1.1.1';
        $this->author = 'PrestaPros.com';
        $this->need_instance = 0;
        $this->languages = Language::getLanguages(false);
        $this->defaultLanguageId = Configuration::get('PS_LANG_DEFAULT');
        $this->ps_versions_compliancy = array(
            'min' => '1.6.0.5',
            'max' => _PS_VERSION_
        );
        $this->bootstrap = true;
        $this->module_key = 'a8879c71db57cb8a5f3968b1d8655a10';
        $this->controllers = array('response');
        parent::__construct();

        $this->displayName = $this->l('DataFeedWatch');
        $this->description = $this->l(
            'Easily generate and optimize feeds for 1000+ global shopping channels and marketplaces'
        );

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (!parent::install()
            || !$this->checkIsValidPhpVersion()
            || !$this->registerHook('moduleRoutes')
            || !$this->registerHook('actionAdminSaveAfter')
        ) {
            return false;
        }
        $this->createTokenForNewShops();

        return true;
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function hookModuleRoutes($params)
    {
        return array(
            'module-datafeedwatch-response' => array(
                'controller' => 'response',
                'rule' => 'datafeedwatch',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => 'datafeedwatch',
                    'controller' => 'response'
                )
            )
        );
    }

    public function hookActionAdminSaveAfter($params)
    {
        if (Tools::isSubmit('submitAddshopAndStay') || Tools::isSubmit('submitAddshop')) {
            $this->createTokenForNewShops();
        }
    }

    public function getContent()
    {
        $this->postProcess();
        $token = $this->getConfigurationValue(self::CONFIG_TOKEN, $this->context->shop->id);

        return $this->renderForm(self::SUBMIT_GENERATE, array(self::CONFIG_TOKEN => $token), $this->getForm())
            . $this->renderForm(
                self::SUBMIT_CONFIGURATION,
                array(
                    self::CONFIG_ONLY_ACTIVE => $this->getConfigurationValue(
                        self::CONFIG_ONLY_ACTIVE,
                        $this->context->shop->id,
                        false
                    ),
                    self::CONFIG_ONLY_STOCK => $this->getConfigurationValue(
                        self::CONFIG_ONLY_STOCK,
                        $this->context->shop->id,
                        false
                    )
                ),
                $this->getConfigurationForm()
            )
            . $this->renderFeedBar($token);
    }

    public function validToken($token)
    {
        return (string)$this->getConfigurationValue(self::CONFIG_TOKEN, $this->getShopIdFromToken()) === (string)$token;
    }

    public function getProducts()
    {
        return $this->fetchProductList($this->prepareParams(), (int)$this->getShopIdFromToken());
    }

    public function getShops()
    {
        return $this->fetchActiveShopList();
    }

    public function getProductsCount()
    {
        $shopId = $this->getShopIdFromToken();
        $products = $this->fetchProductsCount($shopId);
        if (count($products) > 0) {
            foreach ($products as $index => $product) {
                if (Configuration::get(self::CONFIG_ONLY_STOCK, null, null, $shopId)
                    && Product::getRealQuantity($product['id_product'], 0, 0, $shopId) <= 0
                ) {
                    unset($products[$index]);
                }
            }
        }

        return count($products);
    }

    public function checkIfProductExist()
    {
        return Product::getProductName((int)Tools::getValue('product_id'));
    }

    public function getProductAttributesCount()
    {
        return $this->fetchProductAttributesCount((int)Tools::getValue('product_id'));
    }

    public function getAllActiveLanguages()
    {
        return $this->fetchAllActiveLanguages(
            Language::getLanguages(true, $this->getShopIdFromToken(), true)
        );
    }

    protected function postProcess()
    {
        if (((bool)Tools::isSubmit(self::SUBMIT_GENERATE)) == true) {
            if ($this->createConfigToken($this->context->shop->id)) {
                $this->context->controller->confirmations[] = $this->l('Token generated correctly');
            }
        }

        if (((bool)Tools::isSubmit(self::SUBMIT_CONFIGURATION)) == true) {
            if ($this->updateConfigurationValues()) {
                $this->context->controller->confirmations[] = $this->l('Form saved correctly');
            }
        }
    }

    protected function createTokenForNewShops()
    {
        foreach (Shop::getCompleteListOfShopsID() as $shopId) {
            if (!$this->checkShopHaveToken($shopId)) {
                $this->createConfigToken($shopId);
            }
        }
    }

    protected function renderFeedBar($token)
    {
        $baseUrl = Configuration::get('PS_SSL_ENABLED') ?
            str_replace('http', 'https', _PS_BASE_URL_) :
            str_replace('https', 'http', _PS_BASE_URL_);

        $this->context->smarty->assign(
            array(
                'example_url' => $baseUrl . __PS_BASE_URI__ . 'index.php?fc=module&module=datafeedwatch'
                    . '&controller=response'
                    . '&token=' . $token
                    . '&type=PRODUCTS&with_attributes=1&limit=10&offset=0',
                'button_url' => 'https://app.datafeedwatch.com/',
            )
        );

        return $this->display(__FILE__, 'views/templates/admin/feed_bar.tpl');
    }


    protected function updateConfigurationValues()
    {
        try {
            $this->updateConfiguration(self::CONFIG_ONLY_ACTIVE, Tools::getValue(self::CONFIG_ONLY_ACTIVE));
            $this->updateConfiguration(self::CONFIG_ONLY_STOCK, Tools::getValue(self::CONFIG_ONLY_STOCK));
        } catch (Exception $exception) {
            $this->context->controller->errors[] = $exception->getMessage();

            return false;
        }

        return true;
    }

    protected function updateConfiguration($key, $value)
    {
        Configuration::updateValue($key, $value, false, $this->context->shop->id_shop_group, $this->context->shop->id);
    }

    protected function getConfigurationValue($key, $shopId, $default = false)
    {
        return Configuration::get(
            $key,
            null,
            $this->context->shop->id_shop_group,
            $shopId,
            $default
        );
    }

    protected function checkIsValidPhpVersion()
    {
        $phpVersionArr = explode('.', PHP_VERSION);
        $minPhpVersionArr = explode('.', self::MIN_PHP_VERSION);
        if ($phpVersionArr[0] <= $minPhpVersionArr[0]) {
            return $phpVersionArr[1] >= $minPhpVersionArr[1];
        }

        return true;
    }

    protected function getShopIdFromToken()
    {
        $data = $this->fetchConfigurationIdShopByKeyAndValue((string)Tools::getValue('token'));

        return isset($data['id_shop']) ? $data['id_shop'] : null;
    }

    protected function prepareParams()
    {
        $params = array();
        $params['limit'] = Tools::getValue('limit') ? (int)Tools::getValue('limit') : 10;
        $params['offset'] = Tools::getValue('offset') ? (int)Tools::getValue('offset') : 0;
        if (Tools::getValue('with_attributes') && (int)Tools::getValue('with_attributes') === 1) {
            $params['with_attributes'] = true;
        }
        if (Tools::getValue('lang') && Validate::isLanguageIsoCode(Tools::getValue('lang'))) {
            $params['id_language'] = $this->getActiveIdLanguageByIsoCode(Tools::getValue('lang'));
            if (!$params['id_language']) {
                throw new ErrorException('The language does not exist', 400);
            }
        }

        return $params;
    }

    protected function renderForm($submitAction, $fieldsValue, $form)
    {
        $helper = new HelperForm();
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->submit_action = $submitAction;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $fieldsValue,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($form));
    }

    protected function getForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Token')
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Authorization Token'),
                        'desc' => $this->l('Token needed for authorization'),
                        'name' => self::CONFIG_TOKEN,
                        'class' => 'input fixed-width-lg',
                        'readonly' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Generate'),
                    'class' => 'left-block',
                ),
            ),
        );
    }

    protected function getConfigurationForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration')
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Disable inactive products'),
                        'name' => self::CONFIG_ONLY_ACTIVE,
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => self::CONFIG_ONLY_ACTIVE . '_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => self::CONFIG_ONLY_ACTIVE . '_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Disable out of stock products'),
                        'name' => self::CONFIG_ONLY_STOCK,
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => self::CONFIG_ONLY_STOCK . '_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => self::CONFIG_ONLY_STOCK . '_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'left-block',
                ),
            ),
        );
    }

    protected function createConfigToken($shopId)
    {
        try {
            do {
                $token = Tools::passwdGen(16);
            } while ($this->checkTokenUniq($token));
            Configuration::updateValue(
                self::CONFIG_TOKEN,
                $token,
                false,
                null,
                $shopId
            );
        } catch (Exception $exception) {
            $this->context->controller->errors[] = $exception->getMessage();

            return false;
        }

        return true;
    }

    protected function prepareProductListData($result, $shopId, $params)
    {
        $langId = isset($params['id_language']) && !empty($params['id_language']) ?
            $params['id_language'] :
            $this->defaultLanguageId;
        $productDataList = array();
        if (count($result) === 0) {
            return $productDataList;
        }

        foreach ($result as $item) {
            $quantity = Product::getRealQuantity((int)$item['id_product'], 0, 0, $shopId);
            if (Configuration::get(self::CONFIG_ONLY_STOCK, null, null, $shopId) && 0 >= $quantity) {
                continue;
            }
            $deliveryData = $this->prepareProductDeliveryData($item['id_product'], $shopId, $quantity);
            $productData = $this->prepareStandardProductData($item, Currency::getDefaultCurrency());
            $productData['quantity'] = $quantity;
            $productData['availability'] = $quantity > 0 ? 'in stock' : 'out of stock';
            $productData['deliveryDate'] = $deliveryData['deliveryTime'];
            $productData['additionalDeliveryTimes'] = $deliveryData['additionalDeliveryTimes'];

            $this->prepareProductCategoryDate($productData, $shopId, $item['id_product'], $langId);
            if (isset($params['with_attributes'])
                && $params['with_attributes']
                && count(Product::getProductAttributesIds($item['id_product'])) > 0
            ) {
                foreach (Product::getProductAttributesIds($item['id_product']) as $attributeProduct) {
                    $productDataList[] = $this->prepareProductAttributeData(
                        $attributeProduct['id_product_attribute'],
                        $item,
                        $productData,
                        $shopId,
                        $langId
                    );
                }
            }
            $this->prepareProductImagesDate(
                $productData,
                Image::getImages(
                    $langId,
                    $item['id_product']
                )
            );
            $productDataList[] = $productData;
        }

        return $productDataList;
    }

    protected function prepareProductAttributeData($attributeProductId, $item, $productData, $shopId, $langId)
    {
        $productId = $item['id_product'];
        $product = new Product($productId);
        $attributeProductData = $product->getAttributeCombinationsById(
            $attributeProductId,
            $langId
        )[0];
        $quantity = Product::getRealQuantity((int)$item['id_product'], $attributeProductId, 0, $shopId);

        $productData['quantity'] = $quantity;
        $productData['id'] = $productId . '_' . $attributeProductId;
        $productData['parent_id'] = (int)$productId;
        $productData['combination_reference'] = $attributeProductData['reference'];
        $productData['product_type'] = 'combination';
        $productData['price'] = Product::getPriceStatic(
            $productId,
            false,
            $attributeProductId,
            2,
            null,
            false,
            false
        );
        $productData['price_with_vat'] = Product::getPriceStatic(
            $productId,
            true,
            $attributeProductId,
            2,
            null,
            false,
            false
        );
        $productData['sale_price'] = Product::getPriceStatic(
            $productId,
            false,
            $attributeProductId,
            2
        );
        $productData['sale_price_with_tax'] = Product::getPriceStatic(
            $productId,
            true,
            $attributeProductId,
            2
        );
        $productData['ean13'] = $attributeProductData['ean13'];
        $productData['isbn'] = $attributeProductData['isbn'];
        $productData['upc'] = $attributeProductData['upc'];
        $productData['shipping_weight'] = Tools::ps_round($item['weight'] + $attributeProductData['weight'], 2)
            . self::WEIGHT_NAME;
        $productData['link'] = $this->prepareProductAttributeLink($attributeProductId, $item, $shopId, $langId);
        count(Image::getImages($langId, $productId, $attributeProductId)) > 0 ?
            $this->prepareProductImagesDate(
                $productData,
                Image::getImages($langId, $productId, $attributeProductId)
            ) :
            $this->prepareProductImagesDate($productData, Image::getImages($langId, $productId));
        $productData['attributes'] = $this->fetchAttributesByProductAttributeId($attributeProductId, $langId);
        $productData['suppliers_all'] = $this->prepareProductSuppliersAll(
            (int)$item['id_product'],
            $attributeProductId
        );

        return $productData;
    }

    protected function prepareProductAttributeLink($attributesId, $productData, $shopId, $langId)
    {
        $link = new Link();

        return $link->getProductLink(
            $productData['id_product'],
            null,
            $productData['category'],
            null,
            $langId,
            $shopId,
            $attributesId
        );
    }

    protected function prepareProductDeliveryData($productId, $shopId, $quantity)
    {
        $product = new Product($productId, false, null, $shopId);
        $deliveryTime = 0;
        if ($product->additional_delivery_times == 2) {
            $deliveryTime = $quantity > 0 ? $product->delivery_in_stock[1] : $product->delivery_out_stock[1];
        }
        if ($product->additional_delivery_times == 1) {
            $deliveryTime = $quantity > 0 ?
                Configuration::get('PS_LABEL_DELIVERY_TIME_AVAILABLE', $this->defaultLanguageId, null, $shopId) :
                Configuration::get('PS_LABEL_DELIVERY_TIME_OOSBOA', $this->defaultLanguageId, null, $shopId);
        }

        return array(
            'deliveryTime' => (int)$deliveryTime,
            'additionalDeliveryTimes' => $product->additional_delivery_times
        );
    }

    protected function prepareProductImagesDate(&$productData, $images)
    {
        if (count($images) > 0) {
            foreach ($images as $index => $image) {
                if ($index === 0) {
                    $productData['image_link'] = $this->getImagePath($image);
                    continue;
                }
                $productData['additional_image_link_' . $index] = $this->getImagePath($image);
            }
        }
    }

    protected function prepareProductCategoryDate(&$productData, $shopId, $productId, $langId)
    {
        $product = new Product($productId);
        $categories = Product::getProductCategoriesFull($product->id, $langId);
        $index = 0;
        foreach ($categories as $category) {
            if ((int)$product->id_category_default === (int)$category['id_category']) {
                $productData['product_main_category'] = $category['name'];
                $productData['product_main_category_path'] =
                    $this->getCategoryPathName($category['id_category'], $shopId, $langId);
                continue;
            }
            if ($index === 0) {
                $productData['product_category'] = $category['name'];
                $productData['product_category_path'] =
                    $this->getCategoryPathName($category['id_category'], $shopId, $langId);
                $index++;
                continue;
            }
            $productData['product_category'. $index] = $category['name'];
            $productData['product_category' . $index . '_path'] =
                $this->getCategoryPathName($category['id_category'], $shopId, $langId);
            $index++;
        }
    }

    protected function prepareStandardProductData($item, $currency)
    {
        $productData = array();
        $productData['id'] = (int)$item['id_product'];
        $productData['title'] = $item['name'];
        $productData['brand'] = $item['brand'];
        $productData['parent_id'] = null;
        $productData['short_description_html'] = $item['description_short'];
        $productData['description_html'] = $item['description'];
        $productData['link'] = $item['link'];
        $productData['visibility'] = $item['visibility'];
        $productData['active'] = (int)$item['active'] === 1;
        $productData['condition'] = $item['condition'];
        $productData['online_only'] = (int)$item['online_only'] === 0 ? 'n' : 'y';
        $productData['ean13'] = $item['ean13'];
        $productData['isbn'] = $item['isbn'];
        $productData['upc'] = $item['upc'];
        $productData['shipping_weight'] = Tools::ps_round($item['weight'], 2) . self::WEIGHT_NAME;
        $productData['shipping_height'] = Tools::ps_round($item['height'], 2) . self::LENGTH_NAME;
        $productData['shipping_width'] = Tools::ps_round($item['width'], 2) . self::LENGTH_NAME;
        $productData['shipping_depth'] = Tools::ps_round($item['depth'], 2) . self::LENGTH_NAME;
        $productData['currency'] = $currency->iso_code;
        $productData['tax_rate'] = $item['rate'];
        $productData['meta_title'] = $item['meta_title'];
        $productData['meta_description'] = $item['meta_description'];
        $productData['onSale'] = (int)$item['on_sale'] === 0 ? 'n' : 'y';
        $productData['ecotax'] = Tools::ps_round($item['ecotax'], 2);
        $productData['minimalQuantity'] = (int)$item['minimal_quantity'];
        $productData['lowStockThreshold'] = $item['low_stock_threshold'];
        $productData['lowStockAlert'] = (int)$item['low_stock_alert'] === 1;
        $productData['wholesalePrice'] = Tools::ps_round($item['wholesale_price'], 2);
        $productData['unity'] = $item['unity'];
        $productData['unitPriceRatio'] = Tools::ps_round($item['unit_price_ratio'], 2);
        $productData['additionalShippingCost'] = Tools::ps_round($item['additional_shipping_cost'], 2);
        $productData['reference'] = $item['reference'];
        $productData['supplierReference'] = $item['supplier_reference'];
        $productData['location'] = $item['location'];
        $productData['outOfStock'] = (int)Product::isAvailableWhenOutOfStock($item['out_of_stock']) === 1;
        $productData['quantityDiscount'] = (int)$item['quantity_discount'] === 1;
        $productData['customizable'] = (int)$item['customizable'] === 1;
        $productData['uploadableFiles'] = (int)$item['uploadable_files'] === 0 ? 'n' : 'y';
        $productData['textFields'] = (int)$item['text_fields'];
        $productData['redirectType'] = (int)$item['redirect_type'];
        $productData['availableForOrder'] = (int)$item['available_for_order'] === 1;
        $productData['availableDate'] = $item['available_date'];
        $productData['showCondition'] = (int)$item['show_condition'] === 1;
        $productData['condition'] = $item['condition'];
        $productData['showPrice'] = (int)$item['show_price'] === 1;
        $productData['indexed'] = (int)$item['indexed'];
        $productData['isVirtual'] = (int)$item['is_virtual'] === 1;
        $productData['dateAdd'] = $item['date_add'];
        $productData['dateUpd'] = $item['date_upd'];
        $productData['advancedStockManagement'] = (int)$item['advanced_stock_management'] === 1;
        $productData['packStockType'] = (int)$item['pack_stock_type'];
        $productData['state'] = (int)$item['state'];
        $productData['allowOosp'] = (int)$item['allow_oosp'] === 1;
        $productData['attributePrice'] = Tools::ps_round($item['attribute_price'], 2);
        $productData['priceTaxExc'] = Tools::ps_round($item['price_tax_exc'], 2);
        $productData['reduction'] = Tools::ps_round($item['reduction'], 2);
        $productData['reductionWithoutTax'] = Tools::ps_round($item['reduction_without_tax'], 2);
        $productData['quantityAllVersions'] = $item['quantity_all_versions'];
        $productData['features'] = $this->getFeatures($item['features']);
        $productData['virtual'] = $item['virtual'];
        $productData['pack'] = $item['pack'];
        $productData['customizationRequired'] = $item['customization_required'];
        $productData['taxName'] = $item['tax_name'];
        $productData['ecotaxRate'] = $item['ecotax_rate'];
        $productData['product_type'] = empty(Product::getAttributesInformationsByProduct((int)$item['id_product'])) ?
            'simple' :
            'with_combination';
        $productData['price'] = Product::getPriceStatic(
            $item['id_product'],
            false,
            null,
            2,
            null,
            false,
            false
        );
        $productData['price_with_vat'] = Product::getPriceStatic(
            $item['id_product'],
            true,
            null,
            2,
            null,
            false,
            false
        );
        $productData['sale_price'] = Product::getPriceStatic(
            $item['id_product'],
            false,
            null,
            2
        );
        $productData['sale_price_with_tax'] = Product::getPriceStatic(
            $item['id_product'],
            true,
            null,
            2
        );
        $productData['supplier'] = !empty($item['id_supplier']) ? Supplier::getNameById((int)$item['id_supplier']) : '';
        $productData['suppliers_all'] = $this->prepareProductSuppliersAll($item['id_product']);

        return $productData;
    }

    protected function prepareProductSuppliersAll($productId, $attributeProductId = false)
    {
        $productSuppliersName = $this->getProductSuppliersName((int)$productId, $attributeProductId);
        $suppliersName = array();
        if (count($productSuppliersName) > 0) {
            foreach ($productSuppliersName as $item) {
                $suppliersName[] = $item['name'];
            }
        }

        return implode(',', $suppliersName);
    }

    protected function getFeatures($features)
    {
        $data = array();
        if (count($features) > 0) {
            foreach ($features as $feature) {
                $featureData = array();
                $featureData['name'] = $feature['name'];
                $featureData['value'] = $feature['value'];
                $featureData['idFeature'] = $feature['id_feature'];
                $featureData['position'] = $feature['position'];
                $data[] = $featureData;
            }
        }

        return $data;
    }

    protected function getImagePath($imageData)
    {
        $image = new Image($imageData['id_image']);

        return _PS_BASE_URL_ . _PS_PROD_IMG_ . Image::getImgFolderStatic($image->id)
            . $image->id . '.' . $image->image_format;
    }

    protected function getCategoryPathName($categoryId, $shopId, $langId)
    {

        $pathName = '';
        $parentId = $categoryId;
        do {
            $category = $this->fetchParentCategory($parentId, $shopId, $langId);
            $parentId = $category['id_parent'];
            if ($parentId > 0) {
                $pathName = Tools::strlen($pathName) === 0 ? $category['name'] : $category['name'] . ' > ' . $pathName;
            }
        } while ((int)$parentId !== 0);

        return $pathName;
    }

    protected function checkTokenUniq($token)
    {
        $dbquery = new DbQuery();
        $dbquery->select('c.`id_configuration`');
        $dbquery->from('configuration', 'c');
        $dbquery->where('c.name = ' . "'" . pSQL(self::CONFIG_TOKEN) . "'");
        $dbquery->where('c.value LIKE ' . "'%" . pSQL($token) . "%'");

        return count(Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery)) !== 0;
    }

    protected function fetchParentCategory($categoryId, $shopId, $langId)
    {
        $dbquery = new DbQuery();
        $dbquery->select('c.`id_category`, c.`id_parent`, cl.`name`');
        $dbquery->from('category', 'c');
        (int)$shopId > 0 ?
            $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON '
                . 'cl.`id_category` = c.`id_category` AND cl.`id_lang` = '
                . (int)$langId . ' AND cl.`id_shop` = ' . (int)$shopId) :
            $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON '
                . 'cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . (int)$langId);
        $dbquery->where('c.id_category = ' . (int)$categoryId);


        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($dbquery);
    }

    protected function fetchActiveShopList()
    {
        $dbquery = new DbQuery();
        $dbquery->select('s.`id_shop`, s.`name`');
        $dbquery->from('shop', 's');
        $dbquery->where('s.active = 1');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery);
    }

    protected function fetchProductsCount($shopId)
    {
        $dbquery = new DbQuery();
        $dbquery->select('p.`id_product`');
        $dbquery->from('product', 'p');
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = p.`id_product`');
        if (Configuration::get(self::CONFIG_ONLY_ACTIVE, null, null, $shopId)) {
            $dbquery->where('p.`active` = 1');
        }
        if (!empty($shopId)) {
            $dbquery->where('ps.id_shop = ' . (int)$shopId);
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery);
    }

    protected function fetchAttributesByProductAttributeId($productAttributeId, $langId)
    {
        $dbquery = new DbQuery();
        $dbquery->select('a.`id_attribute`, a.`id_attribute_group`, a.`color`, '
            . 'al.`name`, agl.`name` as `group_name`, agl.`public_name` as group_public_name');
        $dbquery->from('attribute', 'a');
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al '
            . 'ON al.`id_attribute` = a.`id_attribute`');
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag '
            . 'ON ag.`id_attribute_group` = a.`id_attribute_group`');
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl '
            . 'ON agl.`id_attribute_group` = ag.`id_attribute_group`');
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac '
            . 'ON pac.`id_attribute` = a.`id_attribute`');
        $dbquery->where('al.id_lang = ' . (int)$langId);
        $dbquery->where('agl.id_lang = ' . (int)$langId);
        $dbquery->where('pac.id_product_attribute = ' . (int)$productAttributeId);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery);
    }

    protected function fetchProductAttributesCount($productId)
    {
        $dbquery = new DbQuery();
        $dbquery->select('pa.`id_product_attribute`');
        $dbquery->from('product_attribute', 'pa');
        $dbquery->where('pa.id_product = ' . (int)$productId);

        return count(Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery));
    }

    protected function fetchConfigurationIdShopByKeyAndValue($token)
    {
        $dbquery = new DbQuery();
        $dbquery->select('c.`id_shop`');
        $dbquery->from('configuration', 'c');
        $dbquery->where('c.name = ' . "'" . pSQL(self::CONFIG_TOKEN) . "'");
        $dbquery->where('c.value LIKE ' . "'%" . pSQL($token) . "%'");

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($dbquery);
    }

    protected function fetchProductList($params, $shopId)
    {
        $shopId = (int)$shopId === 0 ? $this->context->shop->id : $shopId;
        $langId = isset($params['id_language']) && !empty($params['id_language']) ?
            $params['id_language'] :
            $this->defaultLanguageId;

        $dbquery = new DbQuery();
        $dbquery->select(
            'p.*,'
            .' product_shop.*,'
            .' pl.`description`,'
            .' pl.`description_short`,'
            .' pl.`meta_description`,'
            .' pl.`meta_title`,'
            .' pl.`link_rewrite`,'
            .' pl.`name`,'
            .' stock.`out_of_stock`,'
            .' m.`name` as brand'
        );
        $dbquery->from('product', 'p');
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'stock_available`'
            . ' stock ON stock.`id_product` = p.`id_product` AND stock.`id_shop` = ' . (int)$shopId);
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'product_shop` product_shop'
            . ' ON product_shop.`id_product` = p.`id_product` AND product_shop.`id_shop` = ' . (int)$shopId);
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m '
            . 'ON m.`id_manufacturer` = p.`id_manufacturer`');
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON '
            . 'pl.`id_product` = p.`id_product` AND pl.`id_lang` = '
            . (int)$langId . ' AND pl.`id_shop` = ' . (int)$shopId);

        if (Configuration::get(self::CONFIG_ONLY_ACTIVE, null, null, $shopId)) {
            $dbquery->where('p.`active` = 1');
        }

        $dbquery->limit((int)$params['limit'], (int)$params['offset']);
        $dbquery->groupBy('p.id_product');

        $sqlResults = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery);

        return !$sqlResults ? array() : $this->prepareProductListData(
            Product::getProductsProperties($langId, $sqlResults),
            $shopId,
            $params
        );
    }

    protected function checkShopHaveToken($shopId)
    {
        $dbquery = new DbQuery();
        $dbquery->select('c.`id_configuration`');
        $dbquery->from('configuration', 'c');
        $dbquery->where("c.name = '" . pSQL(self::CONFIG_TOKEN) . "'");
        $dbquery->where('c.id_shop = ' . (int)$shopId);

        return !empty(Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($dbquery));
    }

    protected function getProductSuppliersName($productId, $attributeProductId = null)
    {
        $dbquery = new DbQuery();
        $dbquery->select('s.`name`');
        $dbquery->from('product_supplier', 'ps');
        $dbquery->join('LEFT JOIN `' . _DB_PREFIX_ . 'supplier` s ON s.`id_supplier` = ps.`id_supplier`');
        $dbquery->where('ps.id_product = ' . (int)$productId);
        if (!empty($attributeProductId)) {
            $dbquery->where('ps.id_product_attribute = ' . (int)$attributeProductId);
        }
        $dbquery->groupBy('s.`id_supplier`');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery);
    }

    protected function fetchAllActiveLanguages($languages)
    {
        $dbquery = new DbQuery();
        $dbquery->select('l.`name`, l.`iso_code`');
        $dbquery->from('lang', 'l');
        $dbquery->where('l.id_lang IN (' . implode(',', $languages) . ')');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery);
    }

    protected function getActiveIdLanguageByIsoCode($isoCode)
    {
        $dbquery = new DbQuery();
        $dbquery->select('l.`id_lang`');
        $dbquery->from('lang', 'l');
        $dbquery->where('l.iso_code = "' . pSQL(Tools::strtolower($isoCode)) . '"');

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($dbquery);

        return empty($result) ? $result : $result['id_lang'];
    }
}
