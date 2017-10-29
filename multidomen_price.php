<?php
/**
 * Multidomen Price
 *
 * @author github.com/3rdp
 * @version 0.2
 *
 */
defined('_JEXEC') or die;

class PlgJshoppingProductsMultidomen_Price extends JPlugin {

    /**
     * Меняем цены в категории
     *
     * @param   array   $products   Список всех продуктов, которые будут отображены в категории
     */
    public function onBeforeDisplayProductList($products) {
        $v = $this->getPricesArrValues($products);
        foreach ($products as $product) {
            $productPrice = (int)$v["price"][$product
                ->product_id]->price;
            if (!$productPrice) {
                $productPrice = (int)$product->product_price;
            }
            $productPrice = $productPrice - ceil($productPrice / 100 * $v['skidka']) + $v['transp'] + $v['pribyl'] + $v['dop_price'];
            $product->product_price = $v["price"][$product->product_id]->price;
        }
    }

    /**
     * Меняем цены в корзине и карточке товара
     *
     * @param   int     $quantity   Количество данного товара в корзине.
     * @param   bool    $enableCur
     * @param   bool    $enableUs
     * @param   bool    $enablePa
     * @param   obj     $product    Объект продукта.
     * @param   array   $cartProd
     */
    public function onBeforeCalculatePriceProduct($quantity, $enableCurrency, $enableUserDiscount, $enableParamsTax, $product, $cartProduct) {
        $productPrice = $this->getPrice($product);
        if (!$productPrice) {
            $productPrice = (int)$product->product_price; // достали цену продукта
            
        }
        // $productPrice = $productPrice - ceil($productPrice / 100 * $v['skidka']) + $v['transp'] + $v['pribyl'] + $v['dop_price'];
        $product->product_price_wp = "$productPrice";
        $product->product_price_calculate = "$productPrice";
    }

    public function onComplectProduct($product) {
        $product->product_price = $this->getPrice($product);
    }

    private function getFactory($sub) {
        $db = JFactory::getDbo();
        $dbname = $db->quoteName("#__multifactories_city");
        $query = "SELECT factory_id FROM $dbname
            WHERE subdomain_name = '$sub'";
        $db->setQuery($query);
        $result = $db->loadResult();
        if (!$result) $result = 2; // default factory (on capmex)
        return $result;
    }

    private $defaultSub = 'capmex';

    /**
     * Получаем имя субдомена
     *
     * @return  string
     */
    private function getSubdomain() {
        $tmp = explode('.', $_SERVER['HTTP_HOST']);
        if ($tmp[0] == "www") {
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
        return json_decode(htmlspecialchars_decode($strJson));
    }

    private function getCities() {
        $db = JFactory::getDbo();
        $db->setQuery("SELECT `subdomain_name` from `#__multidomen_excel`");
        $results = $db->loadColumn();
        return $results;
    }

    /**
     * Принимаю настройки мультидомена из админки категории.
     * Перевожу в json и сохраняю в базу.
     *
     * @param   array   $post       Данные в POST-запросе
     */
    private function onBeforeSaveCategory($post) {
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
    private function onBeforeEditCategories($view) {
        $view->assign('arrMultidomenPrice', $this->decodeJson($view
            ->category
            ->multidomen_price));
        $view->assign('citiesMultidomen', $this->getCities());
    }

    /**
     * ???
     */
    private function onBeforeDisplayCategory($category, $sub_categories) {
        $this->priceJson = $category->multidomen_price;
    }

    /**
     * Достаю из бд настройки цен для данного мультидомена.
     * И данного продукта.
     */
    private function getPrice($product = null) {
        if (!$product) return '0';
        return $this->getPricesArrValues(array(
            $product
        )) ["price"][$product
            ->product_id]->price;
    }

    /**
     * Достаю цены, только для массива продуктов.
     *
     */
    private function getPricesArrValues($products = null) {
        if (!$products || !count($products)) return false;
        $arrServerName =  array_reverse(explode('.', $_SERVER["SERVER_NAME"]));
        $siteName = $arrServerName[1];
        $sub = $this->getSubdomain() == $siteName ? $this->defaultSub : $this->getSubdomain();
        $db = JFactory::getDbo();
        $dbname = $db->quoteName("#__multifactories_prices_excel");
        $query = "SELECT price, product_id FROM $dbname
            WHERE subdomain = '$sub' AND product_id IN (";
        foreach ($products as $product) // could've been replaced with array_map if I wasn't too lazy
        {
            $query .= $product->product_id . ', ';
        }
        $query = substr($query, 0, -2) . ")";
        $db->setQuery($query);
        $price = $db->loadObjectList("product_id");
        $this->excelRow = $this->getResBody($sub);
        return array(
            'price' => $price
        );
    }

    private function getPricesArrValues_OLD($products = null) {
        if (!$products) return false;
        $sub = $this->getSubdomain();
        $factory = $this->getFactory($sub);
        $db = JFactory::getDbo();
        $dbname = $db->quoteName("#__multifactories_prices");
        $query = "SELECT price, product_id FROM $dbname
            WHERE factory_id = $factory AND product_id IN (";
        foreach ($products as $product) {
            $query .= $product->product_id . ', ';
        }
        $query = substr($query, 0, -2) . ")";
        $db->setQuery($query);
        $price = $db->loadObjectList("product_id");
        $this->excelRow = $this->getResBody($sub);
        return array(
            'price' => $price
        );
    }

    private function _isset($key) {
        return isset($this->excelRow["[[$key]]"]) ? (int)$this->excelRow["[[$key]]"] : 0;

    }

}
?>
