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

class DataFeedWatchResponseModuleFrontController extends ModuleFrontController
{
    const TYPE = array(
        'PRODUCTS',
        'PRODUCTS_COUNT',
        'PRODUCTS_ATTRIBUTES_COUNT',
        'VERSION',
        'LANGUAGES'
    );

    const METHOD_GET = 'GET';

    public function init()
    {
        parent::init();
        if ((string)$_SERVER['REQUEST_METHOD'] === self::METHOD_GET) {
            if ($this->module->validToken(Tools::getValue('token'))) {
                $this->routeByType();
            }
            die($this->unauthorizedResponse());
        }
        die($this->methodNotAllowedResponse());
    }

    protected function routeByType()
    {
        switch (trim(Tools::strtoupper((string)Tools::getValue('type')))) {
            case 'PRODUCTS':
                die($this->productsResponse());
            case 'PRODUCTS_COUNT':
                die($this->productsCountResponse());
            case 'PRODUCTS_ATTRIBUTES_COUNT':
                die($this->productAttributesCountResponse());
            case 'VERSION':
                die($this->versionResponse());
            case 'LANGUAGES':
                die($this->languagesResponse());
            default:
                die($this->badRequestResponse());
        }
    }

    protected function productAttributesCountResponse()
    {
        if ((int)Tools::getValue('product_id') > 0) {
            if ($this->module->checkIfProductExist()) {
                $productAttributesCount = $this->module->getProductAttributesCount();

                return $this->okResponse(array('success' => true, 'count' => $productAttributesCount));
            }

            return $this->notFoundResponse();
        }

        return $this->badRequestResponse();
    }

    protected function productsCountResponse()
    {
        try {
            $productsCount = $this->module->getProductsCount();

            return $this->okResponse(array('success' => true, 'count' => $productsCount));
        } catch (Exception $e) {
            return $this->internalServerErrorResponse($e->getCode());
        }
    }

    protected function productsResponse()
    {
        try {
            $products = $this->module->getProducts();
            return $this->okResponse(array('success' => true, 'items' => $products, 'total' => count($products)));
        } catch (Exception $e) {
            if (400 === $e->getCode()) {
                return $this->badRequestResponse($e->getMessage());
            }
            return $this->internalServerErrorResponse($e->getCode());
        }
    }

    protected function versionResponse()
    {
        return $this->okResponse(array('module_version' => $this->module->version, 'presta_version' =>  _PS_VERSION_));
    }

    protected function languagesResponse()
    {
        try {
            $languages = $this->module->getAllActiveLanguages();
            return $this->okResponse(array('success' => true, 'items' => $languages, 'total' => count($languages)));
        } catch (Exception $e) {
            return $this->internalServerErrorResponse($e->getCode());
        }
    }

    protected function okResponse($body)
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 200 OK");

        return Tools::jsonEncode($body);
    }

    protected function unauthorizedResponse()
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 401 Unauthorized");

        return Tools::jsonEncode(array('success' => false, 'messages' => 'Unauthorized'));
    }

    protected function methodNotAllowedResponse()
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 405 Method Not Allowed");

        return Tools::jsonEncode(array('success' => false, 'messages' => 'Method Not Allowed'));
    }

    protected function badRequestResponse($messages = null)
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 400 Bad Request ");

        return Tools::jsonEncode(array('success' => false, 'messages' => empty($messages) ? 'Bad Request' : $messages));
    }

    protected function internalServerErrorResponse($errorCode)
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 500 Internal Server Error");

        return Tools::jsonEncode(array('success' => false, 'messages' => "Error code: $errorCode"));
    }

    protected function notFoundResponse()
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 404 Not Found");

        return Tools::jsonEncode(array('success' => false, 'messages' => "Not Found"));
    }
}
