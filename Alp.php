<?php
/**
* Copyright : Synapse India. All rights reserve.
* Author : Puneet Kumar
*/

class Synapse_Productimport_Helper_Alp extends Mage_Core_Helper_Abstract
{
	
	
	
	
	protected $_resource;
	protected $_readConnection;
	protected $media_dir;
	private	$_writeConnection;
	
	
	public function __construct(){
		$this->_resource = Mage::getSingleton('core/resource');
		$this->_readConnection = $this->_resource->getConnection('core_read');
		$this->_writeConnection = $this->_resource->getConnection('core_write');
		$this->media_dir = Mage::getBaseDir('media').DS."pk".DS;
	}
	
	/*
	*	creaeproduct function would create configurable product direct use csv data
	*/ 
	public function createproduct()
	{
			 
		$query = 'SELECT * FROM items_R044';
		$product_data = $this->_readConnection->fetchAll($query);

		foreach ($product_data as $key => $value){
			try{
				$this->createsimpleproduct($value);
			}catch(Exception $e){
				Mage::log($e->getMessage(),null,"CLI_hatco_product_import.log");
			}
		}
		
	}
	
	/*
	*	creaeproduct function would create configurable product direct use csv data
	*/ 
	public function createproductconfigurable()
	{
			 
		$query = 'SELECT A.*, B.`Retail_Price`, B.`Color_Code` FROM `styles` A LEFT JOIN (SELECT `Style_Code`, MIN(`Retail_Price`) as `Retail_Price`, ANY_VALUE(`Color_Code`) as Color_Code FROM `items_R044` GROUP BY `Style_Code`) B ON A.`Style_code` = B.`Style_Code` WHERE A.`Style_Code` = "NE704W"';
		$product_data = $this->_readConnection->fetchAll($query);

		foreach ($product_data as $key => $value){
			try{
				$this->createconfigurableproduct($value);
			}catch(Exception $e){
				Mage::log($e->getMessage(),null,"CLI_hatco_product_import.log");
			}
		}
		
	}
	
	
	/**
	* Create configurable product
	*/	
	private function createconfigurableproduct($value)
	{
		
			Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
			$media_dir = $this->media_dir;
			$attributeColorCode = 'color';
			$attributeSizeCode = 'size';

		try{
			
				$simpleProducts = Mage::getModel('catalog/product')->getCollection();
				$simpleProducts->addAttributeToSelect('price');
				$simpleProducts->addAttributeToSelect('color');
				$simpleProducts->addAttributeToSelect('size');
				$simpleProducts->addAttributeToFilter("type_id", 'simple');
				$simpleProducts->addAttributeToFilter("style", 'CD100Y');
				
				// Get attribute id by attribute name
				$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attributeColorCode);
				$attribute = $attribute_details->getData();
				$attribute_colorid = $attribute['attribute_id'];
				
				
				// Get attribute id by attribute name
				$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attributeSizeCode);
				$attribute = $attribute_details->getData();
				$attribute_sizeid = $attribute['attribute_id'];
				
				
				if(count($simpleProducts) > 0)
				{
					
					if($id = Mage::getModel('catalog/product')->getIdBySku($value["Style_Code"])){
						$existingproduct = 1;
						$configProduct = Mage::getModel('catalog/product')->load($id);
					}else{
						$existingproduct = 0;
						$configProduct = Mage::getModel('catalog/product');
					}
					
						$set_value["description"] = utf8_encode($value['Features']);
						$set_value["short_description"] = utf8_encode($value['Description']);
						
						$categories = $value['Category_Name'];
												
						if($categories == 'Fleece Jackets'){
							$categories = 'Hoodies & Sweatshirts';
						}elseif($categories == 'Pants' || $categories == 'Shorts'){
							$categories = 'Pants & Shorts';
						}
						
						$cats = $this->getcategory($categories,$value['Description']);
						
						$configProduct
						->setWebsiteIds(array(1)) //website ID the product is assigned to, as an array
						->setAttributeSetId(10) //ID of a attribute set named 'default'
						->setTypeId("configurable") //product type
						->setCreatedAt(strtotime('now')) //product creation time
						->setSku($value["Style_Code"]) //SKU
						->setName($value["Description"]) //product name
						->setWeight(0)
						->setStatus(1) //product status (1 - enabled, 2 - disabled)
						->setTaxClassId(0) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
						->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH) //catalog and search visibility
						->setNewsFromDate('') //product set as new from
						->setNewsToDate('') //product set as new to
						->setPrice($value['Retail_Price']) //price in form 11.22
						->setSpecialPrice('') //special price in form 11.22
						->setSpecialFromDate('') //special price from (MM-DD-YYYY)
						->setSpecialToDate('') //special price to (MM-DD-YYYY)
						->addData($set_value)
						->setCategoryIds($cats)
						;
						
						if(isset($value['Company']))
						if($value['Company'] == 'ALP')
							$value['Company'] = 'Alphabroder';
						$vendor_id = Mage::helper('productimport/attroptions')->getOptionId("vendor_id", $value["Company"]);
						$configProduct->setVendorId($vendor_id);
									
						if($value["Mill_Name"] != "")
						$manufacturer = Mage::helper('productimport/attroptions')->getOptionId("manufacturer", $value["Mill_Name"]);
						$configProduct->setManufacturer($manufacturer); // size = options2
						
						$features = $this->features($value["Style_Code"]);
						foreach($features as $feature){
							$attr = $this->getstrtolower($feature['attribute']);
							if(strtolower($feature['value']) == 'yes' || strtolower($feature['value']) == 'no'){
								$multioptionSizeId[] = Mage::helper('productimport/attroptions')->getOptionId('features', $feature['attribute']);
							}elseif($feature['feature_code'] == 170 && strtolower($feature['value']) != 'yes' && strtolower($feature['value']) != 'no' ){
								$multioptionSizeId[] = Mage::helper('productimport/attroptions')->getOptionId('features', $feature['value']);
							}else{
								
								if($feature['feature_code']==160){
									$optionSizeId = Mage::helper('productimport/attroptions')->getOptionId("material", $feature['value']);
									$configProduct->setData("material",$optionSizeId);
								}elseif($feature['feature_code']==10 || $feature['feature_code']==200){
									$gender[] = Mage::helper('productimport/attroptions')->getOptionId("demographic", $feature['value']);
								}else{
									$attribute_exist = $this->getattributeidbyname($this->getstrtolower($feature['attribute']));
									if($attribute_exist){
										$optionSizeId = Mage::helper('productimport/attroptions')->getOptionId($this->getstrtolower($feature['attribute']), $feature['value']);
										$configProduct->setData($attr,$optionSizeId);
									}else{
										echo "attr not exist .. $attr";
									}
								
								}
							}
						}
						
						// adding features
						if(count($multioptionSizeId)>0){
							$multy = implode(",",$multioptionSizeId);
							$configProduct->setFeatures($multy);
						}
						
						// addming genders
						if(count($gender)>0){
							$gender = implode(",",$gender);
							$configProduct->setDemographic($gender);
						}
						
						if($existingproduct){
							$imagescount = count($configProduct->getMediaGalleryImages()->getItems());
						}else{
							$imagescount = 0;
						}
						
						$galleryData = $this->downloadimg($value['Style_Code'],$value['Color_Code']);
						// images upload front and backend only
						if($imagescount == 0 && count($galleryData) > 0)
						{
						
							$configProduct->setMediaGallery (array('images'=>array (), 'values'=>array ()));
							krsort($galleryData);
							foreach($galleryData as $gallery_img) 
							{		
									$imageselection = array ('image','small_image','thumbnail');
								
								if ($gallery_img && file_exists($this->media_dir . $gallery_img)){
									$configProduct->addImageToMediaGallery($this->media_dir . $gallery_img, $imageselection, false, false);
								}else{
									echo $media_dir . $gallery_img;
								}
							}
						}
						
						
						$configProduct->setStockData(array(
						'use_config_manage_stock' => 0, //'Use config settings' checkbox
						'manage_stock' => 0, //manage stock
						'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
						'max_sale_qty' => 0, //Maximum Qty Allowed in Shopping Cart
						'is_in_stock' => 1, //Stock Availability
						'qty' => 1 //qty
						));
						
						$data = $configProduct->getTypeInstance()->getUsedProductAttributeIds();
						if(count($data) == 0){
							$configProduct->getTypeInstance()->setUsedProductAttributeIds(array( 0 => $attribute_colorid, 1 => $attribute_sizeid));
						}
						
						$configurableAttributesData = $configProduct->getTypeInstance()->getConfigurableAttributesAsArray();
						
						
						$configurableProductsData = array();
						
						foreach ($simpleProducts as $simple) 
						{
								
								if($simple->getColor() && $simple->getSize()){
								
								$productData = array(
								'label' => $simple->getAttributeText('color'),
								'attribute_id' => $attribute_colorid,
								'value_index' => (int) $simple->getColor(),
								'is_percent' => 0
								);
								$configurableProductsData[$simple->getId()] = $productData;
								$configurableAttributesData[0]['values'][] = $productData;
							
							
								$productData = array(
								'label' => $simple->getAttributeText('size'),
								'attribute_id' => $attribute_sizeid,
								'value_index' => (int) $simple->getSize(),
								'is_percent' => 0
								);
								$configurableProductsData[$simple->getId()] = $productData;
								$configurableAttributesData[1]['values'][] = $productData;
								}
						}
						
						$configProduct->setConfigurableProductsData($configurableProductsData);
						$configProduct->setConfigurableAttributesData($configurableAttributesData);
						$configProduct->save();
				}
			}catch(Exception $e){
				echo $e->getMessage();
				Mage::log($e->getMessage(),null,"CLI_hatco_product_import.log");
			}
	}
	
	/**
	*	function createsimpleproduct would create simple products as per object
	*/
	private function createsimpleproduct($value,$visiblity=1)
	{
		echo "start..." . $value["Item_Number"] . "\n";
		$attributeColorCode = 'color';
		$attributeSizeCode = 'size';
		$media_dir = $this->media_dir;
		
		try {
		
		$id = Mage::getModel('catalog/product')->getIdBySku($value["Item_Number"]);
		
		if($id){
			//return $id;
			$simpleProduct = Mage::getModel('catalog/product')->load($id);
			$existingproduct = 1;
		}else{
			$existingproduct = 0;
			$simpleProduct = Mage::getModel('catalog/product');
		}
		
		
		
		
		// Synapse_Productimport_Helper_Data object using
		if($value["Color_Name"] != "")
		$optionColorId = Mage::helper('productimport')->getOptionId($attributeColorCode, $value["Color_Name"]);
		
		if($value["Size_Name"] != "")
		$optionSizeId = Mage::helper('productimport')->getOptionId($attributeSizeCode, $value["Size_Name"]);
		
		//$cat = Mage::helper('productimport')->getCategoryNameById($value[3]);/* Configurable Product Insert Section */
		//$price = $value["item_price"] == 0? $value["price"] : $value["item_price"];
		
		$set_value["description"] = utf8_encode($value['Features']);
		$set_value["short_description"] = utf8_encode($value['Description']);

		$simpleProduct
		->setWebsiteIds(array(1)) //website ID the product is assigned to, as an array
		->setAttributeSetId(10) //ID of a attribute set named 'default'
		->setTypeId("simple") //product type
		->setCreatedAt(strtotime('now')) //product creation time
		->setSku($value["Item_Number"]) //SKU = catalog_id
		->setName($value["Description"]) //product name = product_name
		->setWeight($value["Weight"]) //weight = weight
		->setStatus(1) //product status (1 - enabled, 2 - disabled)
		->setTaxClassId(0) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
		->setVisibility($visiblity) //catalog and search visibility
		->setNewsFromDate('') //product set as new from
		->setNewsToDate('') //product set as new to
		->setPrice($value['Retail_Price']) //price in form 11.22
		->setSpecialPrice('') //special price in form 11.22
		->setSpecialFromDate('') //special price from (MM-DD-YYYY)
		->setSpecialToDate('') //special price to (MM-DD-YYYY)
		->setStyle($value['Style_Code']) //special price to (MM-DD-YYYY)
		//->setMetaTitle($value["seo_title"]) //MetaTitle = seo_title
		//->setMetaKeyword($value["keywords"]) // keywords = keywords
		//->setMetaDescription($value["description"]) //metaDescription = seo_blurb
		->addData($set_value)
		;
				
				
		 if($value["Color_Name"] != "")
		 $simpleProduct->setColor($optionColorId); // color = options1
		 if($value["size"] != "")
		 $simpleProduct->setSize($optionSizeId); // size = options2
		if($value["Mill_Name"] != "")
		$manufacturer = Mage::helper('productimport/attroptions')->getOptionId("manufacturer", $value["Mill_Name"]);
		$simpleProduct->setManufacturer($manufacturer); // size = options2
		
		if(isset($value['Company']))
		if($value['Company'] == 'ALP')
			$value['Company'] = 'Alphabroder';
		$vendor_id = Mage::helper('productimport/attroptions')->getOptionId("vendor_id", $value["Company"]);
		$simpleProduct->setVendorId($vendor_id);
		
		$features = $this->features($value["Style_Code"]);
		foreach($features as $feature){
			$attr = $this->getstrtolower($feature['attribute']);
			if(strtolower($feature['value']) == 'yes' || strtolower($feature['value']) == 'no'){
				$multioptionSizeId[] = Mage::helper('productimport/attroptions')->getOptionId('features', $feature['attribute']);
			}elseif($feature['feature_code'] == 170 && strtolower($feature['value']) != 'yes' && strtolower($feature['value']) != 'no' ){
				$multioptionSizeId[] = Mage::helper('productimport/attroptions')->getOptionId('features', $feature['value']);
			}else{
				
				if($feature['feature_code']==160){
					$optionSizeId = Mage::helper('productimport/attroptions')->getOptionId("material", $feature['value']);
					$simpleProduct->setData("material",$optionSizeId);
				}elseif($feature['feature_code']==10 || $feature['feature_code']==200){
					$gender[] = Mage::helper('productimport/attroptions')->getOptionId("demographic", $feature['value']);
				}else{
					$attribute_exist = $this->getattributeidbyname($this->getstrtolower($feature['attribute']));
					if($attribute_exist){
						$optionSizeId = Mage::helper('productimport/attroptions')->getOptionId($this->getstrtolower($feature['attribute']), $feature['value']);
						$simpleProduct->setData($attr,$optionSizeId);
					}else{
						echo "attr not exist .. $attr";
					}
				
				}
			}
		}
		
		if(count($multioptionSizeId)>0){
			$multy = implode(",",$multioptionSizeId);
			$simpleProduct->setFeatures($multy);
		}
		
		if(count($gender)>0){
			$gender = implode(",",$gender);
			$simpleProduct->setDemographic($gender);
		}
		
		if($existingproduct){
			$imagescount = count($simpleProduct->getMediaGalleryImages()->getItems());
		}else{
			$imagescount = 0;
		}
		
		$galleryData = $this->downloadimg($value['Style_Code'],$value['Color_Code']);
		// images upload front and backend only
		if($imagescount == 0 && count($galleryData) > 0)
		{
		
			$simpleProduct->setMediaGallery (array('images'=>array (), 'values'=>array ()));
			krsort($galleryData);
			foreach($galleryData as $gallery_img) 
			{		
					$imageselection = array ('image','small_image','thumbnail');
				
				if ($gallery_img && file_exists($this->media_dir . $gallery_img)){
					$simpleProduct->addImageToMediaGallery($this->media_dir . $gallery_img, $imageselection, false, false);
				}else{
					echo $media_dir . $gallery_img;
				}
			}
		}
				
		$simpleProduct->setStockData(
			array
			(
				"qty" => 1000000,
				"min_qty" => 0,
				"use_config_min_qty" => 1,
				"is_qty_decimal" => 0,
				"backorders" => 0,
				"use_config_backorders" => 1,
				"min_sale_qty" => 1,
				"use_config_min_sale_qty" => 1,
				"max_sale_qty" => 0,
				"use_config_max_sale_qty" => 1,
				"is_in_stock" => 1,
				"use_config_notify_stock_qty" => 1,
				"manage_stock" => 1,
				"use_config_manage_stock" => 0,
				"stock_status_changed_auto" => 0,
				"use_config_qty_increments" => 1,
				"qty_increments" => 0,
				"use_config_enable_qty_inc" => 1,
				"enable_qty_increments" => 0,
				"is_decimal_divided" => 0,
				"stock_status_changed_automatically" => 0,
				"use_config_enable_qty_increments" => 1,
			)
		);
		
		
		
		$simpleProduct->save();
		echo "Saved..." . $simpleProduct->getId() . "\n";		
		Mage::log($value["Item_Number"], null, "ALP_created.log");
		}catch (Exception $e)
		{
			echo "Saved..." . $e->getMessage() . "\n";
			Mage::log($e, null, "ALP_createsimple.log");
		}
	}
	
	
	/**
	* Create configurable product
	*/	
	private function assigncat($value)
	{
				
					
		if($id = Mage::getModel('catalog/product')->getIdBySku($value["catalog_id"])){
			$configProduct = Mage::getModel('catalog/product')->load($id);
		}else{
			return;
		}
		
		try {
			$configProduct
			// $cat = $this->getCategoryNameById($value[3]);
			->setCategoryIds($value['main_cat_id']); //assign product to categories 
			$configProduct->save();
		}catch(Exception $e){
			Mage::throwException($e->getMessage());
		}
				
				
				
	}
	
	/**
	* Price update
	**/
	
	public function updatepriceajaxAction(){
		
		$param = Mage::app()->getRequest()->getParam("upload_pr");
		$setcsv = Mage::getSingleton("adminhtml/session");
		$data = $setcsv->getCustomCsv();
		if($param >= count($data)-1){
			try{
				$this->priceupdate($data[$param]);
				$returnData["message"] = "done";
				$returnData["product_id"] = $data[$param]["catalog_id"];
				$returnData["price"] = $data[$param]["item_cost"];
			}catch(Exception $e){
				$returnData["product_id"] = $data[$param]["catalog_id"];
				$returnData["price"] = $data[$param]["item_cost"];
				$returnData['error'] = $e->getMessage();
				$returnData["message"] = "done";
			}
			
		}else{
			try{
				$this->priceupdate($data[$param]);
				$returnData["message"] = "continue";
				$returnData["product_id"] = $data[$param]["catalog_id"];
				$returnData["price"] = $data[$param]["item_cost"];
			}catch(Exception $e){
				$returnData["product_id"] = $data[$param]["catalog_id"];
				$returnData["price"] = $data[$param]["item_cost"];
				$returnData['error'] = $e->getMessage();
				$returnData["message"] = "continue";
			}
			
		}
		
		
		echo json_encode($returnData);
		
	}
	
	public function priceupdate($value){
			
		Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
		
		if($id = Mage::getModel('catalog/product')->getIdBySku($value["catalog_id"])){
			$configProduct = Mage::getModel('catalog/product')->load($id);
		}else{
			Mage::throwException($this->__("Product %s not exist", $id));						
		}

		$price = $value["item_cost"];
		try {
			$configProduct
			// ->setStoreId(1) //you can set data in store scope
			->setPrice($price) //price in form 11.22
			;

			$configProduct->save();

		}catch(Exception $e){
			Mage::throwException($e->getMessage());
		}
	}
	
	private function _replacestr($str){
		$rep = array(" ","/","__");
		return str_replace($rep,"_",$str);
	}
	
	private function features($style){
		$query = "SELECT A.*, B.description as attribute , A.description as value FROM `style_features` A, `features` B WHERE A.product_code = B.product_code and A.feature_code = B.feature_code and style_code = '$style'";
		$product_data = $this->_readConnection->fetchAll($query);
		return $product_data;
	}
	
	private function getattributeidbyname($attribute_name){
		//return function to getting the attribute color id
		$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attribute_name);
		$attribute = $attribute_details->getData();
		$attribute_colorid = $attribute['attribute_id'];
		
		if($attribute_colorid){
			return $attribute_colorid;
		}else{
			Mage::log($attribute_name, null, "not_exist_attribute.log");
		}
		return null;
	}
	
	private function getstrtolower($str){
		$str = strtolower($str);
		$rep = array(" ","/","__");
		return  str_replace($rep,"_",$str);
	}
	
	private function downloadimg($style,$color_code){
		$downloaded = array();
		$ftp_server = 'images.alphabroder.com';
		$conn_id = @ftp_connect($ftp_server) or die("Couldn't connect to $ftp_server");
		$login_result = @ftp_login($conn_id, 'BBimages', 'brodimgs');
		@ftp_pasv($conn_id,true);
		
		//$style = "01928E1";
		//$color_code = "43";
		
		$image['1_front_main_image'] = "{$style}_{$color_code}.jpg";   
		$image['2_back_image'] = "{$style}_bk_{$color_code}.jpg"; 
		$image['3_right_side_image'] = "{$style}_sd_{$color_code}.jpg";
		$image['4_left_side_image'] = "{$style}_lsd_{$color_code}.jpg"; 
		$image['5_quarter_view_image'] = "{$style}_qrt_{$color_code}.jpg";
		$image['6_off_figure'] = "{$style}_of1_{$color_code}_vp.jpg"; 
		$image['7_flat_front_off_figure'] = "{$style}_ff_{$color_code}.jpg";
		$image['8_flat_back_off_figure'] = "{$style}_fb_{$color_code}.jpg"; 
		
		foreach($image as $key => $file){		
			if(ftp_get($conn_id, $this->media_dir.$file, '/Hi-Res Web Images/'.$file,FTP_BINARY)){
				$downloaded[$key] = $file;
			}elseif(ftp_get($conn_id, $this->media_dir.$file, '/Lo-Res Web Images/'.$file,FTP_BINARY)){
				$downloaded[$key] = $file;
			}
		}

		ftp_close($conn_id);
		
		return $downloaded;
	}
	
	public function getcategory($cat,$name){
		$subcats_arr = array('T-Shirts'=>array('Long Sleeve','Short Sleeve','V-Neck','Moisture Wicking','Ladies','Youth','Raglan'),
		'Sweatshirts'=>array('Full-zip','1/4 Zip','1/2 Zip','Crewneck','Pullover','Zippered','Sweatpants','Youth','Ladies'),
		'Polos'=>array('Mocks','Long Sleeve','Short Sleeve','Pocket','Easy Care','Color Block','Stripe','Texture','Youth','Ladies'),
		'Knits and Layering'=>array('Lightweight','Performance','Midweight','Dress','V-Neck'),
		'Hoodies & Sweatshirts'=>array('Midweight','Full-Zip','Quarter-Zip','Half-Zip','Pullover','Youth','Performance','Lightweight','Heavyweight','Vest'),
		'Woven'=>array('Broadcloth','Chambray','Camp','Denim','Dobby','Fishing','Oxford','Poplin','Twill','Wrinkle Resistant','Stain Resistant'),
		'Outerwear'=>array('Hi-Visibility','Workwear','Soft Shell','Rainwear','Wind Shirts','Athletic','Golf','Lightweight','Midweight','Heavyweight','3-in-1','Polyfil','Poly Fleece'),
		'Pants & Shorts'=>array('Workwear','Yoga-Fitness','Athletic','Lounge-Sleepwear','Leggings','Capri-Crop','Coverall','Lingerie-Sleepwear'),
		'Infants | Toddlers'=>array('Short Sleeve','Long Sleeve','Sweatpant','Full-Zip','Hats','Creeper','Romper','Bib','Blanket'),
		'Headwear'=>array('Flexfit','5 Panel','Structured','Unstructured','Beanies','Visors','Pigment-Dyed','Camo','Military','Bucket','Runners'),
		'Bags and Accessories'=>array('Backpack','Drawstring','Duffel','Laptop Case','Tablet Case','Messenger','Briefcase','Tote','Blanket','Towel','Apron','Scarf','Socks'));	

		$subcats = array();
		$lowername = strtolower($name);
		$lowername = str_replace('-',' ',$lowername);
		$lowercat = strtolower($cat);
		$lowercat = str_replace('-',' ',$lowercat);
		foreach($subcats_arr as $key => $index){
			$lowerkey = strtolower($key);
			$lowerkey = str_replace('-',' ',$lowerkey);
			if($lowerkey == $lowercat){
				foreach($index as $sub){
					$lowersub = strtolower($sub);
					$lowersub = str_replace('-',' ',$lowersub);
					if (strpos($lowername, $lowersub) !== false) {
						$subcats[] = $sub;
					}
					if ((strpos($lowername, 'wick') !== false) && ($lowercat == 't shirts')) {
						$subcats[] = 'Moisture Wicking';
					}
					if (((strpos($lowername, 'tank') !== false) || (strpos($lowername, 'sleeveless') !== false)) && ($lowercat == 't shirts')) {
						$subcats[] = 'Tanks & Sleeveless';
					}
					if ((strpos($lowername, 'usa') !== false) && ($lowercat == 't shirts')) {
						$subcats[] = 'Made in the USA';
					}
					if (((strpos($lowername, 'flex') !== false) || (strpos($lowername, 'fitted') !== false)) && ($lowercat == 'headwear')) {
						$subcats[] = 'Flexfit & Fitted';
					}
					if (((strpos($lowername, 'baseball') !== false) || (strpos($lowername, '6 panel') !== false)) && ($lowercat == 'headwear')) {
						$subcats[] = '6 Panel Baseball';
					}
					if ((strpos($lowername, 'unstructured') === false) && (strpos($lowername, 'structured') !== false) && ($lowercat == 'headwear')) {
						$subcats[] = '6 Panel Baseball';
					}
					if (((strpos($lowername, 'fashion') !== false) || (strpos($lowername, 'unstructured') !== false)) && ($lowercat == 'headwear')) {
						$subcats[] = 'Fashion & Unstructured';
					}
					if ((strpos($lowername, 'trucker') !== false) && ($lowercat == 'headwear')) {
						$subcats[] = 'Trucker Mesh';
					}
					if ((strpos($lowername, 'beanie') !== false) && ($lowercat == 'headwear')) {
						$subcats[] = 'Beanies';
					}
					if ((strpos($lowername, 'visor') !== false) && ($lowercat == 'headwear')) {
						$subcats[] = 'Visors';
					}
					if (((strpos($lowername, 'camo') !== false) || (strpos($lowername, 'military') !== false)) && ($lowercat == 'headwear')) {
						$subcats[] = 'Camo & Military';
					}
					if (((strpos($lowername, 'performance') !== false) || (strpos($lowername, 'wick') !== false) || (strpos($lowername, 'runner') !== false)) && ($lowercat == 'headwear')) {
						$subcats[] = 'Performance & Runners';
					}
				}
			}
			
		}
		
		$ids = array();
		$p_category = Mage::getResourceModel('catalog/category_collection');
		$p_category->addFieldToFilter('name', array('in',array($cat)));
		
		foreach($p_category as $item){
			$ids[] = $item->getId();
		}		
		
		if(count($subcats) > 0){
			$category = Mage::getResourceModel('catalog/category_collection')
			->addFieldToFilter('name', array('in',$subcats))
			->addFieldtoFilter('parent_id',$item->getId());
			
			foreach($category as $c){
				$ids[] = $c->getId();	
			}
		}
		
		return $ids;


	}
	
}
	 