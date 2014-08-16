# Linnworks PHP API starter code

Linnworks is a multichannel order processing system (www.linnworks.com).

Here's some code to get started using the Linnworks SOAP API from PHP.

NOTE: Only a subset of API calls are included in this code. Feel free to extend the code as you see fit.

## Getting started

### Step 1: generate a linnworks API key

Log into your linnworks account via http://acc.linnworks.com.  Under your 'My Account' section you'll see a link to a page that allows you to generate keys.  You can generate either a simple or secure key - both types can be used to make API calls.

### Step 2: write some code

#### Creating a product in Linnworks:

```
<?php

require_once('path/to/linnworks/Api.php')

$api = \Linnworks\InventoryApi('YOUR-API-KEY');

// see: http://api.linnlive.com/inventory.asmx?op=SaveStockItem

$item = array('SKU'=>"test-sku',
              'ItemTitle'=>'test product',
              'IsSetItemTitle'=>true,
              'IsSetRetailPrice'=>true,
              'RetailPrice'=>100,
              'BarcodeNumber'=>'12345678',
              'IsSetBarcodeNumber'=>true);

$itemId = $api->saveStockItem($item);

```

### Creating a product with extended properties

You need to follow the nusoap format when adding a sequence of complex types. To get the resulting xml looking like this:

```
<item>
    ...
    <ExtendedProperties>
        <StockItemExtendedProperty>
            <PropertyName>test</PropertyName>
            <PropertyValue>test</PropertyValue>
            <PropertyType>attribute</PropertyType>
        </StockItemExtendedProperty>
        <StockItemExtendedProperty>
            <PropertyName>test2</PropertyName>
            <PropertyValue>test2</PropertyValue>
            <PropertyType>attribute</PropertyType>
   	</StockItemExtendedProperty>
   </ExtendedProperties>
   ...
</item>
```

You want to pass in an array of extended properties like so: 

```
$properties = array(
	array(
		'PropertyName'=>'test',
		'PropetyValue'=>'test',
		'PropertyType'=>'attribute'
	),
	array(
		'PropertyName'=>'test2',
		'PropetyValue'=>'test2',
		'PropertyType'=>'attribute'
	));

$item = array(
	...
	'ExtendedProperties'=>array('StockItemExtendedProperty'=>$properties),
	...
);

$itemId = $api->saveStockItem($item);
```

#### Adding or subtracking stock:

```
<?php

require_once('path/to/linnworks/Api.php')

$api = \Linnworks\InventoryApi('YOUR-API-KEY');

// see: http://api.linnlive.com/inventory.asmx?op=StockLevelAddDeduct

$result = $api->stockLevelAddDeduct("a-barcode-or-a-sku", 100, "Warehouse", "location GUID");

echo "New level: " . $result['Level'];
```

Unfortunately the linnworks documentation is not the greatest and they only provide sample API code in C#, which does not help when doing a PHP integration. 

Linnworks are apparently working on a new API interface which hopefully will provide much more flexiblity. I've discovered that the current API has the following limitations:

*	You can't do stock tranfers via the API
*	You can't record the user that has performed a specific action - though in audits, e.g a product audit, it will list the API key that performed a specific action
*	There's a couple of bugs around order notes - I've found it difficult to add an order note to an open order.
*	You can't control product inventory linking via the API
*	You can't control order or order item extended properties via the API
*	You can't change a product's SKU via SaveStockItem.  It causes a new product to be generated leaving the old product in place.

Whilst this code is provided as is, and is by no means a completed project, hopefully it will speed up the development process for anyone needing to do a bespoke integration with linnworks via PHP.

