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

    $str = '
[{
           "formula":  "$v - [[skidka]]",
           "switch":   "only",
           "cities":   ["anapa", "moskva"]
}]';

    /**
     * Получаем имя субдомена
     *
     * @return  string
     */
	private function getSubdomain() {
		$tmp = explode('.', $_SERVER['HTTP_HOST']);
	    if($tmp[0]=="www")
	    {
	    	return $tmp[1];
	    }	    
	    return $tmp[0];
	}

    /**
     * Получаем переменные для данного субдомена из таблицы
     *
     * @param   string  $subdomen   Имя судбомена (anapa, omsk, …)
     *
     * @return  array
     */
    private function getResBody($subdomen) {
		$db = JFactory::getDbo();
		$db->setQuery("SELECT * from `#__multidomen_excel` WHERE `subdomain_name` = '$subdomen'");
		$results = $db->loadAssoc();
		return $results;
	}

	private function decodeJson($strJson) {
		return json_decode( htmlspecialchars_decode($strJson) );
	}

	private function getCities() {
		$db = JFactory::getDbo();
		$db->setQuery("SELECT `subdomain_name` from `#__multidomen_excel`");
		$results = $db->loadColumn();
		return $results;
	}

    /**
     * Принимаем данные из админки через пост, переводим в json и сохраняем в базу
     *
     * @param   array   $post       Данные в POST-запросе 
     */
	public function onBeforeSaveCategory($post) {
		$arr = $post['multidomen_price'];
		$json = json_encode($arr);
        $id = $post['category_id'];
		if ($json) {
			$db = JFactory::getDbo();
            $query = "UPDATE `#__jshopping_categories` 
                      SET `multidomen_price` = '$json'
                      WHERE `category_id` = $id";
			$db->setQuery($query);
			$db->query();
		}
	}

    /**
     * Переводим json из бд в объекты, которые потом на странице будут выводиться
     *
     * @param   object  $view       Объект для view отображаемой страницы
     */
	public function onBeforeEditCategories($view) { 
		$view->assign('arrMultidomenPrice', $this->decodeJson( $view->category->multidomen_price ));
		$view->assign('citiesMultidomen', $this->getCities());
	}

    /**
     * ???
     */
	public function onBeforeDisplayCategory($category, $sub_categories) {
		$this->priceJson = $category->multidomen_price;
	}

    /**
     * Меняем цены в категории (!)
     *
     * @param   array   $products   Список всех продуктов, которые будут отображены в категории
     */
	public function onBeforeDisplayProductList($products) { 
        $sub = $this->getSubdomain(); // достали город (поддомен)
		$excelRow = $this->getResBody($sub); // достали соотв. строчку из multidomen.xls
		$v = array(
			'skidka' 	=> isset($excelRow['[[skidka]]']) ? (int)$excelRow['[[skidka]]'] : 0,
			'transp' 	=> isset($excelRow['[[transp]]']) ? (int)$excelRow['[[transp]]'] : 0,
			'pribyl' 	=> isset($excelRow['[[pribyl]]']) ? (int)$excelRow['[[pribyl]]'] : 0,
			'dop_price' => isset($excelRow['[[dop_price]]']) ? (int)$excelRow['[[dop_price]]'] : 0
			// 'price'		=> $product->product_price // не, это не надо. это на второй стадии надо
		);
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
                // формула у нас одна для всех
                $productPrice = $productPrice - ceil($productPrice / 100 * $v['skidka']) + $v['transp'] + $v['pribyl'] + $v['dop_price']; 
				$product->product_price = $productPrice;
			}
		}
	}

    /**
     * Меняем цены в корзине (или карточке товара?)
     *
     * @param   int     $quantity   Количество данного товара в корзине.
     * @param   bool    $enableCur  
     * @param   bool    $enableUs
     * @param   bool    $enablePa
     * @param   obj     $product    Объект продукта.
     * @param   array   $cartProd   
     */
	public function onBeforeCalculatePriceProduct($quantity, $enableCurrency, $enableUserDiscount, $enableParamsTax, $product, $cartProduct) { 
        $productPrice = (int)$product->product_price; // достали цену продукта
		$sub = $this->getSubdomain(); // достали город (поддомен)
		$excelRow = $this->getResBody($sub); // достали соотв. строчку из multidomen.xls
		$v = array(
			'skidka' 	=> isset($excelRow['[[skidka]]']) ? (int)$excelRow['[[skidka]]'] : 0,
			'transp' 	=> isset($excelRow['[[transp]]']) ? (int)$excelRow['[[transp]]'] : 0,
			'pribyl' 	=> isset($excelRow['[[pribyl]]']) ? (int)$excelRow['[[pribyl]]'] : 0,
			'dop_price' => isset($excelRow['[[dop_price]]']) ? (int)$excelRow['[[dop_price]]'] : 0
		);
        // формула у нас одна для всех
        $productPrice = $productPrice - ceil($productPrice / 100 * $v['skidka']) + $v['transp'] + $v['pribyl'] + $v['dop_price']; 
		$product->product_price_wp = "$productPrice";
		$product->product_price_calculate = "$productPrice";
		return $product;
	}
	
}
?>
