<?php
/**
 * Multidomen Price 
 * 
 * @author github.com/3rdp
 * @version 0.1
 * 
*/
defined('_JEXEC') or die;

// $this->product_price_wp = $this->product_price;
// $this->product_price_calculate = $this->getPriceWithParams();


class PlgJshoppingProductsMultidomen_Price extends JPlugin
{
	private function getSubdomain()
	{
		$tmp = explode('.', $_SERVER['HTTP_HOST']);
	    if($tmp[0]=="www")
	    {
	    	return $tmp[1];
	    }	    

	    return $tmp[0];
	}

    private function getResBody($subdomen)
	{
		$db = JFactory::getDbo();
 
		$db->setQuery("SELECT * from `#__multidomen_excel` WHERE `subdomain_name` = '$subdomen'");
		$results = $db->loadAssoc();
		return $results;
	}

	private function decodeJson($strJson) 
	{
		return json_decode( htmlspecialchars_decode($strJson) );
	}

	private function getCities()
	{
		$db = JFactory::getDbo();
 
		$db->setQuery("SELECT `subdomain_name` from `#__multidomen_excel`");
		$results = $db->loadColumn();
		return $results;
	}

	public function onBeforeSaveCategory($post) 
	{ // принимаем данные из админки через пост, переводим в json и сохраняем в базу
		// var_dump($post);
		$arr = $post['multidomen_price'];
		// var_dump($arr);
		$json = json_encode($arr);
		// var_dump($json);

		if ($json) {
			$db = JFactory::getDbo();
			$query = "UPDATE `#__jshopping_categories` SET `multidomen_price` = '" . $json .
                    "' WHERE `category_id` = " . $post['category_id'];
			$db->setQuery($query);
			$db->query();
		}
	}

	public function onBeforeEditCategories($view)
	{ // переводим json из бд в объекты, которыем потом на странице будут выводиться
		/*
		 + 1. Взять из БД данные по этой категории через $view->category->category_id
			Колонка, видимо, сама через JOIN подгружается. #_jshopping_categories
			$view->category->multidomen_price
		 + 2. Всунуть данные в $view. administrator\components\com_jshopping\views\category\tmpl\edit.php
		 *
		 *
		 + 3. Отобразить их в edit.php
		 + 4. Повесить обработчик на ивент вроде onBeforSaveCategory
		 * 5. Проверить работает ли всё в категории
		 */

//$str = '
   // [
   //     {
   //         "formula":  "$v - [[skidka]]",
   //         "switch":   "only",
   //         "cities":   ["anapa", "moskva"]
   //     }
   // ]
//';
		$view->assign('arrMultidomenPrice', $this->decodeJson( $view->category->multidomen_price ));
		$view->assign('citiesMultidomen', $this->getCities());
	}

	public function onBeforeDisplayCategory($category, $sub_categories) 
	{
		$this->priceJson = $category->multidomen_price;
	}

	public function onBeforeDisplayProductList($products)
	{ // меняем цены в категории
		// array (size=9)
		//   0 => 
		//     object(stdClass)[798]
		//       public 'product_id' => string '19' (length=2)
		//       public 'category_id' => string '10' (length=2)
		//       public 'product_price' => float 19900
		//       public '_original_product_price' => string '19900.000000' (length=12)
		//       public 'min_price' => string '19900.00' (length=8)

		// var_dump($products);
		$sub = $this->getSubdomain(); // достали город (поддомен)
		$excelRow = $this->getResBody($sub); // достали соотв. строчку из multidomen.xls
		// var_dump($excelRow);

		$v = array(
			'skidka' 	=> isset($excelRow['[[skidka]]']) ? (int)$excelRow['[[skidka]]'] : 0,
			'transp' 	=> isset($excelRow['[[transp]]']) ? (int)$excelRow['[[transp]]'] : 0,
			'pribyl' 	=> isset($excelRow['[[pribyl]]']) ? (int)$excelRow['[[pribyl]]'] : 0,
			'dop_price' => isset($excelRow['[[dop_price]]']) ? (int)$excelRow['[[dop_price]]'] : 0
			// 'price'		=> $product->product_price // не, это не надо. это на второй стадии надо
		);

/*		$skidka = $v['skidka'];
		$transp = $v['transp'];
		$pribyl = $v['pribyl'];
		$dop_price = $v['dop_price']; */
		// тут-то и можно сделать импорт файла, в котором будет массив значений, просто с ксатомными индексами

		if ($this->priceJson) {
			// а здесь должна быть проверка по городу
			$formulesArr = $this->decodeJson($this->priceJson);
			$formula = $formulesArr[count( $formulesArr ) - 1]; // достаем последнюю
			$priceText = str_replace(array_keys($v), array_values($v), $formula->formula);
			foreach ($products as $product) {
				$productFormula = str_replace( 'price', (int)$product->product_price, $priceText);
				// здесь ещё будет проверка на лишние буквы
				$productPrice = eval('return ' . $productFormula . ";");
				$product->product_price = $productPrice;
			}
		} else {
			foreach ($products as $product) {
				$productPrice = (int)$product->product_price;
				$productPrice = $productPrice - ceil($productPrice / 100 * $v['skidka']) + $v['transp'] + $v['pribyl'] + $v['dop_price']; // формула у нас одна для всех
		// var_dump($productPrice);

				$product->product_price = $productPrice;
			}
		}
	}

	public function onBeforeCalculatePriceProduct($quantity, $enableCurrency, $enableUserDiscount, $enableParamsTax, $product, $cartProduct)
	{ // меняем цены в корзине (или карточке товара?)
		// echo 'hERE';
		// public 'product_old_price' => string '0.0000' (length=6)
		// public 'product_buy_price' => string '0.0000' (length=6)
		// public 'product_price' => string '21000.000000' (length=12)
		// public 'min_price' => string '21000.00' (length=8)
		// public 'different_prices' => string '0' (length=1)
		// public 'product_is_add_price' => string '0' (length=1)
		// public 'add_price_unit_id' => string '3' (length=1)
		// public 'basic_price_unit_id' => string '0' (length=1)


		$productPrice = (int)$product->product_price; // достали цену продукта
		$sub = $this->getSubdomain(); // достали город (поддомен)
		$excelRow = $this->getResBody($sub); // достали соотв. строчку из multidomen.xls
		$v = array(
			'skidka' 	=> isset($excelRow['[[skidka]]']) ? (int)$excelRow['[[skidka]]'] : 0,
			'transp' 	=> isset($excelRow['[[transp]]']) ? (int)$excelRow['[[transp]]'] : 0,
			'pribyl' 	=> isset($excelRow['[[pribyl]]']) ? (int)$excelRow['[[pribyl]]'] : 0,
			'dop_price' => isset($excelRow['[[dop_price]]']) ? (int)$excelRow['[[dop_price]]'] : 0
		);
		$productPrice = $productPrice - ceil($productPrice / 100 * $v['skidka']) + $v['transp'] + $v['pribyl'] + $v['dop_price']; // формула у нас одна для всех


		$product->product_price_wp = "$productPrice";
		$product->product_price_calculate = "$productPrice";

		return $product;
	}

	
}
?>
