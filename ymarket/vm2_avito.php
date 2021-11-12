<?php

include 'functions.php';
//include 'config.php';
//define('NAME', '«СПб-Пикник»'); // название организации (не должно превышать 20 символов)
//define('DESC', 'Большой выбор мангалов, коптилен, казанов, грилей и товаров для туризма и отдыха на природе'); // описание организации
//define('CURRENCY', 'RUB'); // валюта магазина (RUB, USD, EUR, UAH, KZT)
//define('DELIVERY', 'true'); // наличие доставки в магазине (true - есть, false - нет)
//define('STORE', 'true');
//define('PICKUP', 'true');

define('FILE', 0); // cоздать файл vm2_market.xml (define('FILE', 1)) или генерировать данные динамически (define('FILE', 0)), если define('FILE', 0), то в настройках якдеса нужно указать ссылку http://ваш_сайт/market/vm2_market.php, если define('FILE', 1), то http://ваш_сайт/market/vm2_market.xml, также, если define('FILE', 1), то после каждого обновления товаров в магазине, нужно в браузере набрать адрес http://ваш_сайт/market/vm2_market.php и запустить скрипт, чтоб сгенерировать файл vm2_market.xml

define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);
define('JPATH_BASE', $_SERVER['DOCUMENT_ROOT']);
require_once(JPATH_BASE.DS.'includes'.DS.'defines.php');
require_once(JPATH_BASE.DS.'includes'.DS.'framework.php');

$app = JFactory::getApplication('site');
$app->initialise();

require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'helpers'.DS.'config.php');
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'helpers'.DS.'calculationh.php');

VmConfig::loadConfig();
$version = substr(vmVersion::$RELEASE, 0, 1);

if ($version == 3) {
		require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'models'.DS.'product.php');
		$model = new VirtueMartModelProduct();
		$lang = VmConfig::$defaultLang;
		
	} else {	
	$lang = VmConfig::get('vmlang', 'en_gb');
	}
$db = JFactory::getDBO();
$live_site = trim(str_replace('market/', '', JURI::base()), '/').'/';
$calculator = calculationHelper::getInstance();

if (!FILE) {
	ob_start('ob_gzhandler', 9);
	header('Content-Type: application/xml; charset=utf-8');
} else {
	header('Content-Type: text/html; charset=UTF-8');
}

define('EXCLUDE_CAT', getCategoryId()); // id категорий которые нужно исключить из выгрузки, перечислить через запятую, например define('EXCLUDE_CAT', '2,8,54,5')
define('EXCLUDE_PROD', getProductId()); // id товаров которые нужно исключить из выгрузки, перечислить через запятую, например define('EXCLUDE_PROD', '2,8,54,5')  


$xml .= '<Ads formatVersion="3" target="Avito.ru">'."\n";
$query = 'SELECT DISTINCT a.virtuemart_product_id, a.product_parent_id, a.product_sku, a.virtuemart_vendor_id, a.product_in_stock, b.product_name, b.product_desc, d.product_tax_id, d.product_discount_id, d.product_price, d.product_override_price, d.override, d.product_currency, e.mf_name, e.virtuemart_manufacturer_id, g.virtuemart_category_id FROM (#__virtuemart_product_categories g LEFT JOIN (#__virtuemart_product_prices d RIGHT JOIN ((#__virtuemart_product_manufacturers f RIGHT JOIN #__virtuemart_products a ON f.virtuemart_product_id = a.virtuemart_product_id) LEFT JOIN #__virtuemart_manufacturers_'.$lang.' e ON f.virtuemart_manufacturer_id = e.virtuemart_manufacturer_id LEFT JOIN #__virtuemart_products_'.$lang.' b ON b.virtuemart_product_id = a.virtuemart_product_id) ON d.virtuemart_product_id = a.virtuemart_product_id) ON g.virtuemart_product_id = a.virtuemart_product_id) WHERE a.published = 1 AND d.product_price > 0 AND b.product_name <> \'\' AND g.virtuemart_category_id IN ('.EXCLUDE_CAT.') AND a.virtuemart_product_id IN ('.EXCLUDE_PROD.') GROUP BY a.virtuemart_product_id';
$db->setQuery($query);
$rows = $db->loadObjectList();

foreach ($rows as $row) {
		 	
	$product_name = htmlspecialchars(trim(strip_tags($row->product_name)));	

	if ($product_name == '') {
		continue;
	}		

	$product_id = $row->virtuemart_product_id;
	$product_cat_id = $row->virtuemart_category_id;
	$row->categories = array($product_cat_id);
	
	if ($version == 3) {
		$model->getRawProductPrices($row, 0, array(1), 1);
	}
	
	$prices = $calculator->getProductPrices($row);
	
	$type = $row->mf_name ? ' type="vendor.model"' : '';	
	$url = str_replace(array('/market/', '//', 'http:/', 'https:/'), array('', '/', 'http://', 'https://'), $live_site.JRoute::_('index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id='.$product_id.'&virtuemart_category_id='.$product_cat_id));	
	
	$available = $row->product_in_stock > 0 ? 'true' : 'false';
	$xml .= '<Ad>'."\n";
	$xml .= '<id>'.$product_id.'</id>'."\n";
	$xml .= '<ListingFee>Package</ListingFee>'."\n";
	$xml .= '<Address>Россия, Санкт-Петербур, проспект Науки, 21к1</Address>'."\n";
	
	$xml .= '<Category>'.getAvitoCategory($product_cat_id)[0].'</Category>'."\n";
	
	if (getAvitoCategory($product_cat_id)[1] != ''){
		$xml .= '<GoodsType>'. getAvitoCategory($product_cat_id)[1] . '</GoodsType>'."\n";
	}	
	
	$xml .= '<AdType>Товар от производителя</AdType>'."\n";
	$xml .= '<Title>'.$product_name.'</Title>'."\n";
	$xml .= '<Description><![CDATA['.mb_substr(str_replace('tr>','p>',strip_tags($row->product_desc,'<p>,<br>,<strong>,<em>,<ul>,<ol>,<li>,<tr>')), 0, 5000). '<br>' . getCustomFields($product_id) . ']]></Description>'."\n";
	//$xml .= getCustomFields($product_id);
	$xml .= '<Price>'.$prices['salesPrice'].'</Price>'."\n";
	$xml .= '<Condition>Новое</Condition>'."\n"; 
	$xml .= '<Images>'.getImagesAvito($product_id).'</Images>'."\n";		
	$xml .= '</Ad>'."\n";
}

$xml .= '</Ads>';

if (FILE) {
	$xml_file = fopen('vm2_market.xml', 'w+');
	
	if (!$xml_file) {
		echo 'Ошибка открытия файла';
	} else {
		ftruncate($xml_file, 0);
		fputs($xml_file, $xml);
		
		echo 'Файл создан, url - <a href="'.$live_site.'market/vm2_market.xml">'.$live_site.'market/vm2_market.xml</a>';
	}
		
	fclose($xml_file);
} else {
	echo $xml;
}
?>
