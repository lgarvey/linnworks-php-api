<?php

namespace Linnworks;

require_once('nusoap/nusoap.php');


class LinnworksException extends \Exception { }

class NotImplementedException extends \BadMethodCallException {}


class LinnworksApiBase{

    protected $_apiKey = null;
    protected $_client = null;
    protected $_wsdl = null;

    public function __construct($apiKey){
        $this->_apiKey = $apiKey;
        $this->_client = new \nusoap_client($this->_wsdl, true);
    }

    public function getClient(){
        return $this->_client;
    }

    /*
     * nusoap SOAP request
     *
     * @returns the XML soap request envelope
     * from the last API call
     */
    public function request(){
        return $this->getClient()->request;
    }

    /*
     * nusoap SOAP response
     *
     * @returns the XML soap response envelope
     * from the last API call
     */
    public function response(){
        return $this->getClient()->response;
    }

    /*
     * nusoap debug function.
     *
     * @returns WSDL composition debug information
     */
    public function debug(){
        return $this->getClient()->debug_str;
    }

    /*
     * A convenience method to handle error responses from the Linnworks API
     * NOTE: not all api calls return error data in this format, but most do.
     */
    protected function handleError($result){
        if(is_array($result) && array_key_exists('IsError', $result) && $result['IsError'] == "true"){
            throw new LinnworksException($result['Error']);
        }

        return false;
    }

    /*
     * A convenience function to automatically pass in the Linnworks auth token,
     * return the correct data, and handle any errors.
     */
    protected function _call($method, $params=array()){

        $args = array_merge(array('Token'=>$this->_apiKey), $params);

        $response = $this->getClient()->call($method, $args);

        if(is_array($response)){
            $result = $response[$method.'Result'];

            if(!$this->handleError($result)){
                return $result;
            }
        }
        else{
            throw new LinnworksException('Error - invalid response from newsoap. See echo $api->debug() for more information');
        }
    }
}


class InventoryApi extends LinnworksApiBase{

    public function __construct($apiKey){
        $this->_wsdl = "http://api.linnlive.com/inventory.asmx?wsdl";
        parent::__construct($apiKey);
    }

    public function stockLevelAddDeduct($skuOrBarcode, $diff, $updateSource, $locationId){
        $result = $this->_call('StockLevelAddDeduct', array('SKUorBarcode'=>$skuOrBarcode, 'diff'=>$diff, 'updateSource'=>$updateSource, 'pkLocationId'=>$locationId ));

        return $result;
    }

    /*
     * changeStockLevel
     *
     * http://api.linnlive.com/inventory.asmx?op=ChangeStockLevel
     *
     * @args
     *      $itemId - Linnworks product guid (e.g pkOrderItemId)
     *      $location - string correlates with warehouse location, if in doubt use "Default"
     *      $level - integer field indicating stock level for product at this location
     *
     * @returns
     *      SOAP response
     *
     *      TODO: process the soap response and return true/false
     */
    public function changeStockLevel($itemId, $location, $level){
        $stockLevel = array('Level'=>$level, 'IsSetLevel'=>true, 'Location'=>$location);

        $result = $this->_call('ChangeStockLevel', array('pkStockItemId'=>$itemId, 'stocklevel'=>$stockLevel));

        return $result;
    }

    /*
     * saveStockItem
     *
     * http://api.linnlive.com/inventory.asmx?op=SaveStockItem
     *
     * @args
     *      $item
     * @returns
     *      A linnworks itemId
     */
    public function saveStockItem( $item ){
        $response = $this->_call('SaveStockItem', array('item' => $item));

        return $result['StockItems']['StockItem']['pkStockItemId'];

    }

    public function getStockItem($filter){
        return $this->_call('GetStockItem', array('filter' => $filter));
    }

    /*
     * getStockItemBySku
     *
     * @args
     *      $sku - string containing the product sku
     * @returns
     *      stock item array if found, otherwise returns null
     */
    public function getStockItemBySku($sku){
        $response = $this->getStockItem(array('SKU'=>$sku, 'IsSetSKU'=>'true'));

        if($response['StockItems'] == ""){
            return null;
        }
        else{
            return $response['StockItems']['StockItem'];
        }
    }

    public function getStockItemByBarcode($barcode)
    {

        $response = $this->getStockItem(array('BarcodeNumber' => $barcode, 'IsSetBarcodeNumber' => 'true'));

        if($response['StockItems'] == ""){
            return null;
        }
        else{
            return $response['StockItems']['StockItem'];
        }
    }

    /*
     * deleteStockItem
     *
     * http://api.linnlive.com/inventory.asmx?op=DeleteStockItem
     */
    public function deleteStockItem($pkStockItemId){
        $response = $this->_call('DeleteStockItem', array('pkStockItemId' => $pkStockItemId));
    }

    /*
     * Change a binrack location
     *
     * args:
     * pkStockItemId - the linnworks item id
     * $location - the location assoc array containing:
     *      LocationID - guid
     *      LocationName - string (e.g. Default, Shop etc.)
     *      BinRack - string
     * delete - set to true to remove the binrack location
     */
    public function updateStockItemLocation($pkStockItemId, $location, $delete=false){
        $response = $this->_call('UpdateStockItemLocation', array('pkStockItemId'=>$pkStockItemId, 'location'=>$location, 'Delete'=>$delete));

        return true;
    }
}


class OrderApi extends LinnworksApiBase{

    public function __construct($apiKey){
        $this->_wsdl = "http://api.linnlive.com/order.asmx?wsdl";
        parent::__construct($apiKey);
    }

    /*
     * addNewOrder
     *
     * See: http://api.linnlive.com/order.asmx?op=AddNewOrder
     */
    public function addNewOrder($order){
        $result = $this->_call('AddNewOrder', array('order'=>$order));

        $result['Orders']['OrderLite'];
    }

    /*
     * addOrderAudit
     *
     * See: http://api.linnlive.com/order.asmx?op=AddOrderAudit
     *
     * @args:
     *      $itemId - Linnworks pkOrderItemId
     *      $audit - $audit associative array (see the above link for details)
     */
    public function addOrderAudit($itemId, $audit){
        /*
         * Sample "object":
         * $audit = array('HistoryNote'=>'',
         *                'fkOrderHistoryTypeId'=>'',  // See generic API for types
         *                'Datestamp'=>'',
         *                'Tag'=>'',
         *                'updatedBy'=>'');
         */
        $response = $this->_call('AddOrderAudit', array('pkOrderItemId'=>$itemId, 'audit'=> $audit));
    }

    public function addOrderAuditBatch($orderAuditSingletonItems){
        throw new NotImplementedException;
    }

    public function addBatchReturns($returnRequests){
        throw new NotImplementedException;
    }

    public function deleteOrder($returnRequests){
        throw new NotImplementedException;
    }

    /*
     * getFilteredOrders
     *
     * See http://api.linnlive.com/order.asmx?op=GetFilteredOrders
     */
    public function getFilteredOrders($filter){
        $result = $this->_call('GetFilteredOrders', array('Filter'=>$filter));

        return $result;
    }

    public function getLiteOpenOrders(){
        throw new NotImplementedException;
    }

    public function partShipOrders(){
        throw new NotImplementedException;
    }

    public function getOrderById($orderId){
        $filter = array('OrderIdIsSet'=>true, 'OrderId'=>$orderId);

        $result = $this->getFilteredOrders($filter);

        return $result['Orders']['Order'];
    }

    /*
     * updateOrder
     *
     * See: http://api.linnlive.com/order.asmx?op=UpdateOrder
     */
    public function updateOrder($order){
        $response = $this->_call('UpdateOrder', array('order'=>$order));

        return true;
    }

    public function processOrder(){
        throw new NotImplementedException;
    }
}

/*
 * Linnworks generic API - currently no methods implemented
 *
 * Reference docs: http://api.linnlive.com/generic.asmx
 */
class GenericApi extends LinnworksApiBase{
    public function __construct($apiKey){
        $this->_wsdl = "http://api.linnlive.com/generic.asmx?wsdl";
        parent::__construct($apiKey);
    }

    public function addCountry(){
        throw new NotImplementedException;
    }

    public function addProductCountry(){
        throw new NotImplementedException;
    }

    public function checkToken(){
        throw new NotImplementedException;
    }

    public function deleteCountry(){
        throw new NotImplementedException;
    }

    public function deleteProductCategory(){
        throw new NotImplementedException;
    }

    public function generateToken(){
        throw new NotImplementedException;
    }

    public function getAllAppSettings(){
        throw new NotImplementedException;
    }

    public function getAppSettings(){
        throw new NotImplementedException;
    }

    public function getAuditTypes(){
        throw new NotImplementedException;
    }

    public function getCategories(){
        throw new NotImplementedException;
    }

    public function getCountryList(){
        throw new NotImplementedException;
    }

    public function getExtendedPropertyTypes(){
        throw new NotImplementedException;
    }

    public function getLocations(){
        $result = $this->_call('GetLocations', array());

        if(!$this->handleError($result)){
            return $result['DataObj']['StockItemLocation'];
        }
    }

    public function getOrderStatusTypes(){
        throw new NotImplementedException;
    }

    public function getPackagingGroups(){
        throw new NotImplementedException;
    }

    public function getPaymentMethods(){
        throw new NotImplementedException;
    }

    public function getPostalServices(){
        throw new NotImplementedException;
    }

    public function getPurchaseOrderAuditTypes(){
        throw new NotImplementedException;
    }

    public function getSuppliers(){
        throw new NotImplementedException;
    }

    public function updateCountry(){
        throw new NotImplementedException;
    }
}


/*
 * Purchase Order API - currently no methods are implemented
 *
 * See: http://api.linnlive.com/PurchaseOrder.asmx
 */
class PurchaseOrderApi extends LinnworksApiBase{
    public function __construct($apiKey){
        $this->_wsdl = "http://api.linnlive.com/order.asmx?wsdl";
        parent::__construct($apiKey);
    }

    public function actionBatchPurchaseOrderItemDelivered(){
        throw new NotImplementedException;
    }

    public function actionPurchaseOrderItemDelivered(){
        throw new NotImplementedException;
    }

    public function addPurchaseOrderAuditTrail(){
        throw new NotImplementedException;
    }

    public function getFilteredPoList(){
        throw new NotImplementedException;
    }

    public function getPoList(){
        throw new NotImplementedException;
    }

    public function getPurchaseOrder(){
        throw new NotImplementedException;
    }

    public function lockPurchaseOrder(){
        throw new NotImplementedException;
    }
}
