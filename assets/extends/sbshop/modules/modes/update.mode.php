<?php
/**
 * @name SBShop
 * @author Mukharev Maxim
 *
 * @desription
 *
 * SBShop - Интернет-магазин на MODx
 *
 * Экшен модуля: Ручное обновление данных SBShop
 */

class update_mode {

	protected $sModuleLink;
	protected $sMode;
	protected $sAct;
	protected $aTmpParams;

	/**
	 * Конструктор
	 * @param $sModuleLink Ссылка на модуль
	 * @param $sMode Режим работы модуля
	 * @param $sAct Выполняемое действие
	 */
	public function __construct($sModuleLink, $sMode, $sAct = '') {
		/**
		 * Записываем служебную информацию модуля, чтобы делать разные ссылки
		 */
		$this->sModuleLink = $sModuleLink;
		$this->sMode = $sMode;
		$this->sAct = $sAct;

		$this->aProductOptions = array();

		/**
		 * Обновление старых опций
		 * Переход от записи всех опций вручную к интерфейсу
		 */
		//$this->updateOptions();
		/**
		 * Обновление старых параметров
		 * Переход от записи всех опций вручную к интерфейсу
		 */
		//$this->updateAttributes();
		/**
		 * Обновление параметров до версии 2011-08-22 в репозитории на http://github.com
		 * Изменение формата хранения параметров для добавления группировки
		 */
		//$this->groupAttributes();
		/**
		 * Обновление списка товаров хранимых в заказах. Версия 02.01.12
		 */
		//$this->updateOrderProductList();
		/**
		 * Обновление формата хранения изображений и привьюшек для товаров
		 */
		//$this->updateImages();
		/**
		 * Обновление списка заказов 21.08.12
		 */
		//$this->updateOrderlist();
		/**
		 * Обновление списка заказов 19.11.12
		 */
		//$this->updateOrderlist2();

		echo '<p>Если вы ожидали, что здесь скрывается автоматическое обновление, то зря. Для работы необхожимо раскомментировать необходимую строку у режима модуля "update".</p>';

	}

	/**
	 * Обновление информации товаров
	 */
	protected function updateOptions() {
		global $modx;

		$rs = $modx->db->select('product_id, product_options',$modx->getFullTableName('sbshop_products'));
		$aRaws = $modx->db->makeArray($rs);

		echo '<pre>';
		foreach ($aRaws as $aRaw) {
			$aOptions = $this->parseOldOptions($aRaw['product_options']);
			/**
			 * Сериализуем иначе
			 */
			$sOptions = base64_encode(serialize($aOptions));
			/**
			 * Обновляем в базе данных
			 */
			if($modx->db->update(array('product_options' => $sOptions),$modx->getFullTableName('sbshop_products'),'product_id = ' . $aRaw['product_id'])) {
				echo('<pre>Обновлены опции у товара: ' . $aRaw['product_id'] . '</pre>');
			}

		}
		echo '</pre>';
	}

	/**
	 * Десериализация данных по опциям
	 * @param <type> $sOptions
	 * @return <type>
	 */
	public function parseOldOptions($sOptions) {
		/**
		 * Результирующий массив опций
		 */
		$aOptions = array();
		/**
		 * Если пустое поле
		 */
		if($sOptions != '') {
			/**
			 * Разбиваем запись на отдельные параметры
			 */
			$aRows = explode('||', $sOptions);
			/**
			 * Обрабатываем каждую запись
			 */
			foreach ($aRows as $sRow) {
				/**
				 * Массив значений опции
				 */
				$aValues = array();
				/**
				 * Выделяем имя
				 */
				list($sName,$sParams) = explode('[', substr($sRow, 0, -1));
				/**
				 * Разбиваем название опции на ключ и идентификатор
				 */
				list($sNameKey,$sNameId) = explode('#', $sName);
				/**
				 * Разбиваем параметры
				 */
				$aParams = explode(';', $sParams);
				/**
				 * Обрабатываем каждый параметр
				 */
				foreach ($aParams as $sParam) {
					/**
					 * Разбиваем строку на параметр и значение
					 */
					list($sParamKey,$sParamVal) = explode('==', $sParam);
					/**
					 * Разбиваем название параметра для получения ID (если есть)
					 */
					list($sParamKey,$sParamId) = explode('#', $sParamKey);
					/**
					 * Добавляем значение
					 */
					$aValues[$sParamId] = array(
						'id' => $sParamId,
						'title' => $sParamKey,
						'value' => $sParamVal
					);
				}
				/**
				 * Добавляем новую опцию
				 */
				$aOptions[$sNameId] = array(
					'id' => $sNameId,
					'title' => $sNameKey,
					'longtitle' => $sNameKey,
					'values' => $aValues
				);
			}
		}
		/**
		 * Возвращаем результат
		 */
		return $aOptions;
	}

	/**
	 * Обновление информации товаров
	 */
	protected function updateAttributes() {
		global $modx;

		/**
		 * Очищаем таблицу параметров товаров
		 */
		$modx->db->query('TRUNCATE TABLE ' . $modx->getFullTableName('sbshop_attributes'));
		$modx->db->query('TRUNCATE TABLE ' . $modx->getFullTableName('sbshop_product_attributes'));
		$modx->db->query('TRUNCATE TABLE ' . $modx->getFullTableName('sbshop_category_attributes'));
		/**
		 * Получаем все данные по товарам
		 */
		$rs = $modx->db->select('*',$modx->getFullTableName('sbshop_products'));
		$aRaws = $modx->db->makeArray($rs);
		/**
		 * Массив для параметров раздела
		 */
		$aAttrCategories = array();

		echo '<pre>';
		foreach ($aRaws as $aRaw) {
			/**
			 * Если это старые параметры
			 */
			if(!unserialize(base64_decode($aRaw['product_attributes']))) {
				if($aRaw['product_attributes'] == '') {
					$aAttribites = array();
				} else {
					$aAttribites = $this->parseOldAttributes($aRaw['product_attributes']);
					/**
					 * Товар
					 */
					$aAttrIds = SBAttributeCollection::getAttributeIdsByNames(array_keys($this->aTmpParams));
					/**
					 * Заносим данные агрегации для товара
					 */
					$aAttrSql = array();
					foreach($aAttribites as $aAttribute) {
						/**
						 * Товар
						 */
						$aAttrSql[] = "({$aRaw['product_id']}, {$aAttrIds[$aAttribute['title']]}, '{$aAttribute['value']}', '{$aAttribute['measure']}')";
						/**
						 * Раздел
						 */
						$aAttrCategories[$aRaw['product_category']][$aAttrIds[$aAttribute['title']]] = array(
							'category_id' => $aRaw['product_category'],
							'attribute_id' => $aAttrIds[$aAttribute['title']],
							'attribute_count' => 1 + $aAttrCategories[$aRaw['product_category']][$aAttrIds[$aAttribute['title']]]['attribute_count']
						);
					}
					$sSql = 'INSERT INTO ' . $modx->getFullTableName('sbshop_product_attributes') . ' (`product_id`, `attribute_id`, `attribute_value`, `attribute_measure`) VALUES ' . implode(',',$aAttrSql);
					$modx->db->query($sSql);
				}
				/**
				 * Сериализуем иначе
				 */
				$sAttribites = base64_encode(serialize($aAttribites));
				/**
				 * Обновляем в базе данных
				 */
				if($modx->db->update(array('product_attributes' => $sAttribites),$modx->getFullTableName('sbshop_products'),'product_id = ' . $aRaw['product_id'])) {
					echo('<pre>Обновлены опции у товара: ' . $aRaw['product_id'] . '</pre>');
				}
			}
		}
		/**
		 * Агрегация разделов
		 */
		foreach ($aAttrCategories as $aAttrCategory) {
			/**
			 * Каждый параметр
			 */
			$aAttrSql = array();
			foreach($aAttrCategory as $aAttr) {
				$aAttrSql[] = "({$aAttr['category_id']}, {$aAttr['attribute_id']}, {$aAttr['attribute_count']})";
			}
			$sSql = 'INSERT INTO ' . $modx->getFullTableName('sbshop_category_attributes') . ' (`category_id`, `attribute_id`, `attribute_count`) VALUES ' . implode(',',$aAttrSql);
			$modx->db->query($sSql);
		}
		echo '</pre>';
	}

	protected function parseOldAttributes($sParams = '') {
		global $modx;
		/**
		 * Если ничего не передали, выходим
		 */
		if($sParams == '') {
			return;
		}
		/**
		 * Разбиваем строку на отдельные ключи/значения
		 * @var unknown_type
		 */
		$aParams = explode('||',$sParams);
		/**
		 * Массив параметров
		 */
		$aAttributes = array();
		$aAttrKeys = array();
		/**
		 * Обрабатываем каждую строку
		 */
		foreach ($aParams as $sParam) {
			list($sKey,$sVal) = explode('==',$sParam);
			list($sVal,$sMeasure) = explode(' ',$sVal);

			/**
			 * Проверяем ключ
			 */
			$sKeyType = mb_substr($sKey, 0, 1);
			/**
			 * выбираем действие
			 */
			switch ($sKeyType) {
				/**
				 * Выделенный пункт
				 */
				case '*':
					/**
					 * Устанавливаем режим primary
					 */
					$sType = 'p';
					$sKey = mb_substr($sKey,1);
				break;
				/**
				 * Выделенный пункт
				 */
				case '-':
					/**
					 * Устанавливаем режим hidden
					 */
					$sType = 'h';
					$sKey = mb_substr($sKey,1);
				break;
				default:
					$sType = 'n';
				break;
			}
			$aAttributes[$sKey] = array(
				'title' => $sKey,
				'value' => $sVal,
				'type' => $sType
			);
			if($sMeasure) {
				$aAttributes[$sKey]['measure'] = $sMeasure;
			}
			$this->aTmpParams[$sKey] = $sVal;
			/**
			 * Делаем вставку
			 */
			$aAttrKeys[] = "('$sKey')";
		}
		$sSql = "INSERT IGNORE INTO " . $modx->getFullTableName('sbshop_attributes') . ' (`attribute_name`) VALUES ' . implode(',',$aAttrKeys);
		$modx->db->query($sSql);
		return $aAttributes;
	}

	/**
	 * Обновление параметров для добавления возможности группировки
	 * Также происходит обобщение всех параметров и запись в раздел
	 * @return void
	 */
	protected function groupAttributes() {
		global $modx;
		/**
		 * Получаем данные о всех имеющихся разделах
		 */
		$rs = $modx->db->select('*', $modx->getFullTableName('sbshop_categories'));
		$aCategoryRaws = $modx->db->makeArray($rs);
		/**
		 * Очищаем таблицу от старых данных
		 */
		$sSql = 'TRUNCATE TABLE  ' . $modx->getFullTableName('sbshop_category_attributes');
		$modx->db->query($sSql);
		/**
		 * Обрабатываем каждый
		 */
		foreach ($aCategoryRaws as $aCategoryRaw) {
			/**
			 * Массив обобщенных параметров для раздела
			 */
			$aCategoryAttrs = array();
			$aCategoryAgregateAttrs = array();
			/**
			 * Загружаем данные о товарах
			 */
			$rs = $modx->db->select('*', $modx->getFullTableName('sbshop_products'),'product_category = ' . $aCategoryRaw['category_id']);
			$aProductRaws = $modx->db->makeArray($rs);
			/**
			 * Обрабатываем информацию по каждому товару
			 */
			foreach($aProductRaws as $aProductRaw) {
				/**
				 * Десериализуем параметры
				 */
				$aProductAttrs = unserialize(base64_decode($aProductRaw['product_attributes']));
				/**
				 * Получаем идентификаторы
				 */
				$aProductAttrKeys = SBAttributeCollection::getAttributeIdsByNames(array_keys($aProductAttrs));
				/**
				 * Актуализируем информацию о каждом параметре
				 */
				foreach ($aProductAttrs as $aProductAttr) {
					/**
					 * Данные для таблицы
					 */
					$sTitle = $aProductAttr['title'];

					$aCategoryAttrs[$sTitle] = array(
						'category_id' => $aCategoryRaw['category_id'],
						'attribute_id' => $aProductAttrKeys[$sTitle],
						'attribute_count' => $aCategoryAttrs[$sTitle]['attribute_count'] + 1,
						'attribute_measure' => $aProductAttr['measure'],
						'attribute_type' => $aProductAttr['type']
					);
					/**
					 * Данные записываемые в раздел
					 */
					$aCategoryAgregateAttrs[$sTitle] = array(
						'title' => $sTitle,
						'count' => $aCategoryAttrs[$sTitle]['attribute_count'],
						'measure' => $aCategoryAttrs[$sTitle]['attribute_measure'],
						'type' => $aCategoryAttrs[$sTitle]['attribute_type'],
					);
				}
				/**
				 * Готовим локальную информацию об обобщении для товара
				 */
				$aUpd = array(
					'groups' => array(),
					'attributes' => $aProductAttrs
				);
				$sUpd = base64_encode(serialize($aUpd));
				/**
				 * Обновляем
				 */
				if($modx->db->update(array('product_attributes' => $sUpd), $modx->getFullTableName('sbshop_products'), 'product_id = ' . $aProductRaw['product_id'])) {
					echo('<pre> * Товар "' . $aProductRaw['product_id'] . '" успешно обновлен.</pre>');
				}
			}
			/**
			 * Готовим локальную информацию об обобщении для раздела
			 */
			$aUpd = array(
				'groups' => array(),
				'attributes' => $aCategoryAgregateAttrs
			);
			$sUpd = base64_encode(serialize($aUpd));
			/**
			 * Обновляем
			 */
			if($modx->db->update(array('category_attributes' => $sUpd), $modx->getFullTableName('sbshop_categories'), 'category_id = ' . $aCategoryRaw['category_id'])) {
				/**
				 * Готовим данные для вставки в таблицу
				 */
				foreach ($aCategoryAttrs as $aCategoryAttr) {
					$aIns[] = "({$aCategoryAttr['category_id']}, {$aCategoryAttr['attribute_id']}, {$aCategoryAttr['attribute_count']}, '{$aCategoryAttr['attribute_measure']}', '{$aCategoryAttr['attribute_type']}')";
				}
				/**
				 * Вставляем
				 */
				$sIns = implode(',',$aIns);
				$sql = 'INSERT INTO ' . $modx->getFullTableName('sbshop_category_attributes') . ' (`category_id`,`attribute_id`, `attribute_count`,`attribute_measure`,`attribute_type`) VALUES ' . $sIns;
				if($modx->db->query($sql)) {
					echo('<pre>Раздел "' . $aCategoryRaw['category_title'] . '" успешно обновлен.</pre>');
				}
			}
		}
	}

	/**
	 * Обновление хранения списка товаров в заказах
	 */
	protected function updateOrderProductList() {
		global $modx;
		/**
		 * Загружаем список всех заказов
		 */
		$rs = $modx->db->select('*', $modx->getFullTableName('sbshop_orders'));
		$aRaws = $modx->db->makeArray($rs);
		/**
		 * Обрабатываем каждую запись
		 */
		foreach ($aRaws as $aRaw) {
			/**
			 * Десериализуем
			 */
			$aProducts = json_decode($aRaw['order_products'], true);
			/**
			 * Переработанный массив
			 */
			$aProductsNew = array();
			/**
			 * Обрабатываем список товаров
			 */
			foreach ($aProducts as $sKey => $aProduct) {
				$iProductId = intval($sKey);
				/**
				 * Загружаем товар
				 */
				$oProduct = new SBProduct();
				$oProduct->load($iProductId, true);
				/**
				 * Передаем все старые значения в том же виде
				 */
				$aProductsNew[$sKey] = $aProduct;
				/**
				 * Добавляем информацию о товаре
				 */
				$aProductsNew[$sKey]['product'] = $oProduct;
			}
			/**
			 * Сериализуем результат
			 */
			$aRaw['order_products'] = base64_encode(serialize($aProductsNew));
			/**
			 * Обновляем запись
			 */
			$aUpd = array(
				'order_products' => $aRaw['order_products']
			);
			if($modx->db->update($aUpd, $modx->getFullTableName('sbshop_orders'),'order_id = ' . $aRaw['order_id'])) {
				echo('<pre>Обновлен заказ номер: ' . $aRaw['order_id'] . '</pre>');
			}
		}
	}

	/**
	 * Изменение списка заказов, упрощение хранимой информации
	 */
	protected function updateOrderlist() {
		global $modx;
		/**
		 * Загружаем список всех заказов
		 */
		$rs = $modx->db->select('*', $modx->getFullTableName('sbshop_orders'), '', '', 10000);
		$aRaws = $modx->db->makeArray($rs);
		/**
		 * Обрабатываем каждую запись
		 */
		foreach ($aRaws as $aRaw) {
			/**
			 * Заказ
			 * @var SBOrder $oOrder
			 */
			$oOrder = new SBOrder($aRaw);
			/**
			 * Измененный список товаров в заказе
			 */
			$aProductsNew = array();
			/**
			 * Получаем список товаров
			 */
			$aProducts = $oOrder->getProducts();
			/**
			 * Если товары есть
			 */
			if(is_array($aProducts) and count($aProducts) > 0) {
				/**
				 * Обрабатываем каждый товар
				 */
				foreach($aProducts as $sSetId => $aOrderData) {
					/**
					 * @var SBProduct $oProduct
					 */
					$oProduct = $aOrderData['product'];
					/**
					 * Добавляем товар в список заказа
					 */
					$oOrder->oProductList->addProduct($oProduct);
					/**
					 * Удаляем информацию о заказе, чтобы освободить немного памяти
					 */
					unset($aOrderData['product']);
					/**
					 * Формируем данные нового заказа
					 */
					$aProductsNew[$sSetId] = array(
						'title' => $oProduct->getAttribute('title'),
						'quantity' => $aOrderData['quantity'],
						'price' => $oOrder->getProductPriceBySetId($sSetId)
					);
					/**
					 * Если комплектация установлена
					 */
					if(isset($aOrderData['bundle'])) {
						if($aOrderData['bundle'] !== 'base' and $aOrderData['bundle'] !== 'personal') {
							$aBundle = $oProduct->getBundleById($aOrderData['bundle']);
							/**
							 * XXX Исправить!
							 */
							$aProductsNew[$sSetId]['title'] .= ' - ' . $aBundle['title'];
						}
					}
					/**
					 * Если есть опции
					 */
					if(isset($aOrderData['sboptions'])) {
						$aOptions = array();
						/**
						 * Опции, относящиеся к базовой комплектации
						 */
						if(isset($aOrderData['bundle'])) {
							/**
							 * Комплектация
							 */
							$aBundle = $oProduct->getBundleById($aOrderData['bundle']);
							/**
							 * Получаем базовый список опций
							 */
							$aBaseOptions = $oProduct->getBaseBundle();
							/**
							 * Если дополнительные опции установлены
							 */
							if(isset($aBundle['options'])) {
								/**
								 * Добавляем опции в общий список
								 */
								$aBaseOptions += $aBundle['options'];
							}
						} else {
							/**
							 * Берем базовые опции
							 */
							$aBaseOptions = $oProduct->getBaseBundle();
						}
						/**
						 * Делаем пустой массив, если список базовых значений отсутствует
						 */
						if($aBaseOptions === null) {
							$aBaseOptions = array();
						}
						if($aOrderData['sboptions'] === null) {
							$aOrderData['sboptions'] = array();
						}
						/**
						 * Добавляем базовые значения к опциям
						 */
						$aOrderData['sboptions'] = $aBaseOptions + $aOrderData['sboptions'];
						/**
						 * Обрабатываем каждую
						 */
						foreach($aOrderData['sboptions'] as $sKey => $sVal) {
							/**
							 * Получаем информацию об опции
							 */
							$aOption = $oProduct->oOptions->getNamesByNameIdAndValId($sKey, $sVal);
							/**
							 * Добавляем в массив для заказа
							 */
							$aTmpOption = array(
								'value_id' => $sVal,
								'price' => $oProduct->getPriceByOptions(array($sKey => $sVal))
							);
							/**
							 * Если значение опции скрываемое
							 */
							if(in_array($sVal, $modx->sbshop->config['hide_option_values'])) {
								$aTmpOption['title'] = $aOption['title'];
							} else {
								$aTmpOption['title'] = $aOption['title'] . $modx->sbshop->config['option_separator'] . ' ' . $aOption['value'];
							}
							/**
							 * Если опция входит в базовую комплектацию
							 */
							if($aBaseOptions[$sKey] === $sVal) {
								/**
								 * Добавляем информацию о базовых опциях к заказу
								 */
								unset($aTmpOption['price']);
								$aOptions['base'][$sKey] = $aTmpOption;
							} else {
								/**
								 * Добавляем информацию о базовых опциях к заказу
								 */
								$aOptions['ext'][$sKey] = $aTmpOption;
							}
							$aProductsNew[$sSetId]['options'] = $aOptions;
						}
					}
				}
			} else {
				/**
				 * Товаров нет. Нужно сделать обработку по SetId
				 */
			}
			/**
			 * Делаем обновление
			 */
			$aUpd = array(
				'order_products' => base64_encode(serialize($aProductsNew))
			);
			/**
			 * Обновляем
			 */
			if($modx->db->update($aUpd, $modx->getFullTableName('sbshop_orders'), 'order_id = ' . $aRaw['order_id'])) {
				echo '<p>Обновлен список товаров в заказе № ' . $aRaw['order_id'] . '</p>';
			}
		}
	}

	/**
	 * Изменение списка заказов, доработка формата хранения
	 */
	protected function updateOrderlist2() {
		global $modx;
		/**
		 * Массив товаров
		 */
		$aProductsData = array();
		/**
		 * Загружаем список всех заказов
		 */
		$rs = $modx->db->select('*', $modx->getFullTableName('sbshop_orders'), '', '', 10000);
		$aRaws = $modx->db->makeArray($rs);
		/**
		 * Обрабатываем каждую запись
		 */
		foreach ($aRaws as $aRaw) {
			/**
			 * Заказ
			 * @var SBOrder $oOrder
			 */
			$oOrder = new SBOrder($aRaw);
			/**
			 * Получаем список товаров
			 */
			$aProducts = $oOrder->getProducts();

			foreach($aProducts as $sSetId => $aProduct) {
				/**
				 * Получаем товар
				 */
				$iProductId = $oOrder->getProductIdBySetId($sSetId);
				if(isset($aProductsData[$iProductId])) {
					$oProduct = $aProductsData[$iProductId];
				} else {
					$oProduct = new SBProduct();
					$oProduct->load($iProductId);
					$aProductsData[$iProductId] = $oProduct;
				}
				$aProductEdit = array();
				/**
				 * Если установлена комплектация
				 */
				if(isset($aProduct['bundle'])) {
					/**
					 * Базовая
					 */
					if($aProduct['bundle'] === 'base') {
						$aProductEdit['bundle'] = array(
								'id' => 'base',
								'title' => 'Базовая комплектация'
						);
					} elseif($aProduct['bundle'] === 'personal') {
						$aProductEdit['bundle'] = array(
							'id' => 'personal',
							'title' => 'Индивидуальная комплектация'
						);
					} else {
						$aBundle = $oProduct->getBundleById($aProduct['bundle']);
						$aProductEdit['bundle'] = array(
							'id' => $aProduct['bundle'],
							'title' => $aBundle['title']
						);
					}
				} else {
					$aProductEdit['bundle'] = array(
						'id' => 'base',
						'title' => 'Базовая комплектация'
					);
				}
				/**
				 * Стоимость
				 */
				if($aProduct['price'] > $aProduct['price_full']) {
					$aProductEdit['price_full'] = $aProduct['price'];
				}
				/**
				 * Редактируем
				 */
				$oOrder->editProduct($sSetId, $aProductEdit);
			}
			/**
			 * Информация об обновляемом заказе
			 */
			echo '<p>Обновлен список товаров в заказе № ' . $oOrder->getAttribute('id') . '</p>';
			/**
			 * Сохраняем заказ
			 */
			$oOrder->save();
		}
	}

	/**
	 * Обновление формата хранения изображений и создание привьюшек
	 */
	protected function updateImages() {
		global $modx;
		/**
		 * Получаем данные о всех имеющихся товарах
		 */
		$rs = $modx->db->select('product_id, product_images', $modx->getFullTableName('sbshop_products'));
		$aProductsRaws = $modx->db->makeArray($rs);
		/**
		 * Конфигурация для ресайза
		 */
		$aResizeConfigs = $modx->sbshop->config['image_resizes'];
		/**
		 * Конфигурация для миниатюры
		 */
		$aResizePreviewConfig = array(array(
			'mode' => 'xy',
			'w' => 96,
			'h' => 96,
			'quality' => 85,
			'key' => '-prv'
		));
		/**
		 * Максимальные параметры в конфиге
		 */
		$iWidthMax = 0;
		$sKeyMax = '';
		/**
		 * Пробегаем все конфигурации, чтобы определить конфиг с самой большой картинкой
		 */
		foreach ($aResizeConfigs as $aConfig) {
			/**
			 * Если текущий конфиг имеет картинку большего размера, чем заданный
			 */
			if($aConfig['w'] > $iWidthMax) {
				/**
				 * Записываем новые значения в переменные
				 */
				$iWidthMax = $aConfig['w'];
				$sKeyMax = $aConfig['key'];
			}
		}
		/**
		 * Обрабатываем каждый товар
		 */
		foreach ($aProductsRaws as $aProductRaw) {
			/**
			 * Разбиваем список ключей
			 */
			$aImgIds = explode('||', $aProductRaw['product_images']);
			/**
			 * Измененный массив изображений
			 */
			$aImgIdsNew = array();
			/**
			 * Обрабатываем каждый идентификатор изображения
			 */
			foreach ($aImgIds as $sImageId) {
				/**
				 * Формируем путь до самого большого изображения в наборе
				 */
				$sImagePath = $modx->sbshop->config['image_base_dir'] . $aProductRaw['product_id'] . '/' . $sImageId . $sKeyMax . '.jpg';
				$sBasePath = $modx->sbshop->config['image_base_dir'] . $aProductRaw['product_id'] . '/';
				/**
				 * Если файл существует
				 */
				if (is_file($sImagePath)) {
					/**
					 * Делаем привьюшку
					 */
					SBImage::imageResize($sImagePath, $sBasePath, $aResizePreviewConfig, $sImageId);
					/**
					 * Добавляем изображение в массив
					 */
					$aImgIdsNew[$sImageId] = array('id' => $sImageId);
				}
			}
			/**
			 * Массив обновляемых значений
			 */
			$aUpd = array(
				'product_images' => serialize($aImgIdsNew)
			);
			if ($modx->db->update($aUpd, $modx->getFullTableName('sbshop_products'), 'product_id = ' . $aProductRaw['product_id'])) {
				echo('<p>Обновлены изображения для товара: ' . $aProductRaw['product_id'] . '</p>');
			}
		}
	}

}

?>