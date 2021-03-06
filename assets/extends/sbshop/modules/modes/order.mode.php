<?php
/**
 * @name SBShop
 * @author Mukharev Maxim
 *
 * @desription
 *
 * SBShop - Интернет-магазин на MODx
 *
 * Экшен модуля: Режим управления заказами
 */

class order_mode {

	protected $sModuleLink;
	protected $sMode;
	protected $sAct;
	protected $aTemplates;
	protected $aStatuses; //  статусы заказов
	/**
	 * Конструктор
	 * @global <type> $modx
	 * @param <type> $sModuleLink
	 * @param <type> $sMode
	 * @param <type> $sAct
	 */
	public function __construct($sModuleLink, $sMode, $sAct = '') {
		global $modx;
		/**
		 * Устанавливаем режим менеджера
		 */
		$modx->sbshop->bManager = true;
		/**
		 * Записываем служебную информацию модуля, чтобы делать разные ссылки
		 */
		$this->sModuleLink = $sModuleLink;
		$this->sMode = $sMode;
		$this->sAct = $sAct;
		/**
		 * Если статус указан active
		 */
		if(!isset($_POST['order_status']) or $_POST['order_status'] === 'active') {
			/**
			 * Задаем набор статусов, которые относятся к активным
			 */
			$this->aStatuses = array(10, 20, 25);
		} else {
			/**
			 * Задаем определенный статус
			 */
			$this->aStatuses = array(intval($_POST['order_status']));
		}
		/**
		 * Получаем шаблон вывода списока заказов
		 */
		$this->aTemplates = $modx->sbshop->getModuleTemplate('orderlist');
		/**
		 * Вызов плагинов до вставки общих данных по товару
		 */
		$PlOut = $modx->sbshop->invokeEvent('OnSBShopOrderTemplateSet', array(
			'aTemplates' => $this->aTemplates,
			'aStatuses' => $this->aStatuses
		));
		/**
		 * Берем результат работы первого плагина, если это массив.
		 */
		if (is_array($PlOut[0])) {
			$this->aTemplates = $PlOut[0];
		}
		/**
		 * Выводим список заказов
		 */
		echo $this->outputList();
	}

	/**
	 * Вывод информации о списке заказов
	 * @global <type> $modx
	 * @param <type> $aOrderList
	 */
	public function outputList() {
		global $modx;
		/**
		 * Загружаем список полученных заказов
		 */
		$oOrderList = new SBOrderList($this->aStatuses);
		/**
		 * Получаем массив заказов
		 */
		$aOrderList = $oOrderList->getOrderList();
		/**
		 * Данные товара для JS-скрипта
		 */
		$aJsOrderData = array();
		/**
		 * Данные товаров для использования в редактировании
		 */
		$aJsProductsData = array();
		/**
		 * Вызов плагинов до вставки общих данных по товару
		 */
		$PlOut = $modx->sbshop->invokeEvent('OnSBShopOrderListPrerender', array(
			'aOrderList' => $aOrderList,
			'aStatuses' => $this->aStatuses
		));
		/**
		 * Берем результат работы первого плагина, если это массив.
		 */
		if (is_array($PlOut[0])) {
			$aOrderList = $PlOut[0];
		}
		/**
		 * Если статус изменен
		 */
		if(isset($_POST['sb_set_status'])) {
			/**
			 * Получаем номер заказа и комментарий
			 */
			foreach($_POST['sb_comment'] as $sKey => $sComment) {
				/**
				 * Если статус изменился
				 */
				if($aOrderList[$sKey]->getAttribute('status') != $_POST['sb_status_list'][$sKey]) {
					/**
					 * Устанавливаем новый статус
					 */
					$aOrderList[$sKey]->setAttribute('status', intval($_POST['sb_status_list'][$sKey]));
				}
				/**
				 * Если изменилось время для следущего действия
				 */
				if($_POST['sb_date_next'][$sKey] != '') {
					/**
					 * Разбивает временные данные по значениям
					 */
					$aDateNext = explode(',', $_POST['sb_date_next'][$sKey]);
					/**
					 * Формируем дату
					 */
					$iDateNext = mktime($aDateNext[0], $aDateNext[1], 0, $aDateNext[2], $aDateNext[3], $aDateNext[4]);
					/**
					 * Формируем дату
					 */
					$aOrderList[$sKey]->setAttribute('date_next', date('Y-m-d G:i:s', $iDateNext));
					/**
					 * Устанавливаем новый статус
					 */
					$aOrderList[$sKey]->setAttribute('status', intval($_POST['sb_status_list'][$sKey]));
				}
				/**
				 * Добавляем комментарий
				 */
				$aOrderList[$sKey]->oComments->add($modx->db->escape($sComment));
				/**
				 * Сохраняем
				 */
				$aOrderList[$sKey]->save();
			}
		}
		/**
		 * Составляем список
		 */
		$aOrderRows = array();
		foreach ($aOrderList as $oOrder) {
			/**
			 * Вызов плагина до обработки заказа
			 */
			$PlOut = $modx->sbshop->invokeEvent('OnSBShopOrderPrerender', array(
				'oOrder' => $oOrder,
				'aTemplates' => $this->aTemplates,
				'aStatuses' => $this->aStatuses,
				'sModuleLink' => $this->sModuleLink,
				'sAct' => $this->sAct
			));
			/**
			 * Берем результат работы первого плагина, если это массив.
			 */
			if ($PlOut[0]) {
				$aOrderRows[] = $PlOut[0];
				/**
				 * Пропускаем шаг обработки
				 */
				continue;
			}
			/**
			 * Готовим набор плейсхолдеров
			 */
			$aOrderRepl = $modx->sbshop->arrayToPlaceholders($oOrder->getAttributes());
			/**
			 * Статус товара
			 */
			$aOrderRepl['[+sb.status.txt+]'] = $modx->sbshop->lang['order_status_' . $oOrder->getAttribute('status')];
			/**
			 * Форматируем дату заказа
			 */
			$aOrderRepl['[+sb.date_edit+]'] = date($modx->sbshop->config['order_date_format'], strtotime($oOrder->getAttribute('date_edit')));
			/**
			 * Форматируем дату заказа
			 */
			if($oOrder->getAttribute('date_next')) {
				$aOrderRepl['[+sb.date_next+]'] = date($modx->sbshop->config['order_date_format'], strtotime($oOrder->getAttribute('date_next')));
			} else {
				$aOrderRepl['[+sb.date_next+]'] = '---';
			}
			/**
			 * Идентификатор заказчика
			 */
			$iCustomerId = $oOrder->getAttribute('user');
			/**
			 * Загружаем данные пользователя
			 */
			$oCustomer = new SBCustomer($iCustomerId);
			/**
			 * Добавляем данные заказчика заказчика
			 */
			$aOrderRepl = array_merge($aOrderRepl, $modx->sbshop->arrayToPlaceholders($oCustomer->getAttributes(), 'sb.customer.'));
			/**
			 * Комментарии
			 */
			$aComments = $oOrder->oComments->getAll();
			/**
			 * Комментарии
			 */
			$sComments = '';
			/**
			 * Если комментарии есть
			 */
			if($aComments) {
				/**
				 * Обрабатываем каждый комментарий
				 */
				foreach ($aComments as $iTime => $sVal) {
					/**
					 * Плейсхолдеры ряда
					 */
					$aCommRepl = array(
						'[+sb.time+]' => date($modx->sbshop->config['order_date_format'], $iTime),
						'[+sb.comment+]' => $sVal
					);
					/**
					 * Добавляем ряд
					 */
					$sComments .= str_replace(array_keys($aCommRepl), array_values($aCommRepl), $this->aTemplates['comment_row']);
				}
			}
			/**
			 * Добавляем плейсхолдер комментариев
			 */
			$aOrderRepl['[+sb.comments+]'] = str_replace('[+sb.wrapper+]', $sComments, $this->aTemplates['comment_outer']);
			/**
			 * Инициализируем массив товаров в корзине
			 */
			$aProductRows = array();
			/**
			 * Получаем список позиций
			 */
			$aIds = $oOrder->getProductSetIds();
			if($aIds) {
				/**
				 * Обрабатываем товары
				 */
				foreach ($aIds as $sSetId) {
					/**
					 * Идентификатор товара
					 */
					$iProductId = intval($sSetId);
					/**
					 * Загружаем товар
					 */
					$oProduct = new SBProduct();
					$oProduct->load($iProductId, true);
					/**
					 * Получаем информацию о количестве и прочих условиях заказа товара
					 */
					$aOrderInfo = $oOrder->getOrderSetInfo($sSetId);
					/**
					 * Добавляем данные о товаре для JS-скрипта
					 */
					$aJsOrderData[$sSetId] = $aOrderInfo;
					/**
					 * Получаем список опций товара
					 */
					$aOptions = $oProduct->oOptions->getOptionNames();
					/**
					 * Обрабатываем каждый товар
					 */
					$aJsProductsData[$oProduct->getAttribute('id')] = array(
						'title' => $oProduct->getAttribute('title'),
						'price' => $oProduct->getFullPrice()
					);
					/**
					 * Добавляем комплектацию
					 */
					$aOrderInfo['bundle'] = $aOrderInfo['bundle']['title'];
					/**
					 * Если установлены базовые опции, которые входят в комплектацию
					 */
					if(isset($aOrderInfo['options']['base']) and count($aOrderInfo['options']['base']) > 0) {
						/**
						 * Опции для комплектации
						 */
						$aBundleOptions = array();
						/**
						 * Разбираем каждую дополнительную опцию
						 */
						foreach($aOrderInfo['options']['base'] as $iKey => $aOption) {
							/**
							 * Готовим плейсхолдеры
							 */
							$aRepl = $modx->sbshop->arrayToPlaceholders($aOption);
							/**
							 * Добавляем опцию
							 */
							$aBundleOptions[] = str_replace(array_keys($aRepl), array_values($aRepl), $this->aTemplates['bundleoptions_inner']);
						}
						/**
						 * Вставляем опции в контейнер
						 */
						$aOrderInfo['bundleoptions'] = str_replace('[+sb.wrapper+]', implode('', $aBundleOptions), $this->aTemplates['bundleoptions_outer']);
					} else {
						$aOrderInfo['bundleoptions'] = '';
					}
					/**
					 * Обрабатываем опции
					 */
					foreach($aOptions as $sOptionKey => $aOption) {
						/**
						 * Получаем значения
						 */
						$aValues = $oProduct->oOptions->getValuesByOptionName($sOptionKey);
						/**
						 * Обрабатываем каждое значение
						 */
						foreach($aValues as $aValue) {
							/**
							 * Если значение не равно 'null'
							 */
							if($aValue['value'] !== 'null') {
								/**
								 * Если значение опции скрываемое
								 */
								if(in_array($aValue['id'], $modx->sbshop->config['hide_option_values'])) {
									$sOptionTitle = $aOption['title'];
								} else {
									$sOptionTitle = $aOption['title'] . $modx->sbshop->config['option_separator'] . ' ' . $aValue['title'];
								}
								/**
								 * Получаем информацию по
								 */
								$aJsProductsData[$oProduct->getAttribute('id')]['options'][$aOption['id']][$aValue['id']] = array(
									'title' => $sOptionTitle,
									'price' => $oProduct->getPriceByOptions(array($aOption['id'] => $aValue['id']))
								);
							}
						}
					}
					/**
					 * Если товар есть
					 */
					if($oProduct->getAttribute('url') !== null) {
						$aOrderInfo['url'] = $oProduct->getAttribute('url');
						$aOrderInfo['sku'] = $oProduct->getAttribute('sku');
					} else {
						$aOrderInfo['url'] = '';
						$aOrderInfo['sku'] = '';
					}
					/**
					 * Стоимость одного товара
					 */
					$aOrderInfo['price'] = $aOrderInfo['price_full'];
					/**
					 * Общая сумма за товар
					 */
					$aOrderInfo['summ'] = $oOrder->getProductSummBySetId($sSetId);;
					/**
					 * Массив опций для JS-скрипта
					 */
					$aJsOrderDataOptions = array();
					$aJsOrderDataOptions['ext'] = array();
					/**
					 * Массив опций для товара
					 */
					$aOptions = array();
					/**
					 * Если установлены расширенные опции
					 */
					if(isset($aOrderInfo['options']['ext']) and count($aOrderInfo['options']['ext']) > 0) {
						/**
						 * Разбираем каждую дополнительную опцию
						 */
						foreach($aOrderInfo['options']['ext'] as $iKey => $aOption) {
							/**
							 * Готовим плейсхолдеры
							 */
							$aRepl = $modx->sbshop->arrayToPlaceholders($aOption);
							/**
							 * Добавляем опцию
							 */
							$aOptions[] = str_replace(array_keys($aRepl), array_values($aRepl), $this->aTemplates['product_option_row']);
							/**
							 * Для скрипта
							 */
							$aJsOrderDataOptions['ext'][$iKey] = array(
								'title' => $aOption['title'],
								'price' => $aOption['price'],
								'valuid' => $aOption['value_id']
							);
						}
					}
					/**
					 * Если есть опции добавленные вручную
					 */
					if(isset($aOrderInfo['options']['man'])) {
						/**
						 * Разбираем каждую дополнительную опцию
						 */
						foreach($aOrderInfo['options']['man'] as $iKey => $aOption) {
							/**
							 * Плейсхолдеры
							 */
							$aRepl = array(
								'[+sb.title+]' => $aOption['title'],
							);
							/**
							 * Добавляем опцию
							 */
							$aOptions[] = str_replace(array_keys($aRepl), array_values($aRepl), $this->aTemplates['product_option_row']);
							/**
							 * Для скрипта
							 */
							$aJsOrderDataOptions['man'][$iKey] = array(
								'title' => $aOption['title']
							);
						}
					}
					/**
					 * Если опции есть
					 */
					if(count($aOptions)> 0) {
						$aOrderInfo['options'] = str_replace('[+sb.wrapper+]', implode($this->aTemplates['product_option_separator'], $aOptions), $this->aTemplates['product_option_outer']);
						/**
						 * Добавляем опции к данным для JS-скрипта
						 */
						$aJsOrderData[$sSetId]['options'] = $aJsOrderDataOptions;
					} else {
						$aOrderInfo['options'] = '';
					}
					/**
					 * Плейсхолдеры для товара
					 */
					$aProductRepl = $modx->sbshop->arrayToPlaceholders($aOrderInfo);
					/**
					 * Идентификатор набора товара
					 */
					$aProductRepl['[+sb.set_id+]'] = $sSetId;
					/**
					 * Вставляем данные в шаблон
					 */
					$aProductRows[] = str_replace(array_keys($aProductRepl), array_values($aProductRepl), $this->aTemplates['product_row']);
				}
			}
			/**
			 * Полная информация о заказанных товарах
			 */
			$aOrderRepl['[+sb.products+]'] = str_replace('[+sb.wrapper+]', implode('', $aProductRows), $this->aTemplates['product_outer']);
			/**
			 * Доступные для управления статусы
			 */
			$aStatuses = $modx->sbshop->config['status_manage'];
			/**
			 * Если текущий статус заказа входит в список
			 */
			if(in_array($oOrder->getAttribute('status'), $aStatuses)) {
				/**
				 * Список
				 */
				$aStatRows = array();
				/**
				 * Обрабатываем статусы
				 */
				foreach ($aStatuses as $iStatusId) {
					$aStRepl = array(
						'[+sb.value+]' => $iStatusId,
						'[+sb.title+]' => $modx->sbshop->lang['order_status_' . $iStatusId]
					);
					/**
					 * Если текущий статус выделен
					 */
					if($oOrder->getAttribute('status') == $iStatusId) {
						$aStatRows[] = str_replace(array_keys($aStRepl), array_values($aStRepl), $this->aTemplates['action_option_selected']);
					} else {
						$aStatRows[] = str_replace(array_keys($aStRepl), array_values($aStRepl), $this->aTemplates['action_option']);
					}
				}
				/**
				 * Заносим в контейнер и делаем плейсхолдер
				 */
				$aOrderRepl['[+sb.action+]'] = str_replace('[+sb.wrapper+]', implode('', $aStatRows), $this->aTemplates['action_outer']);
			} else {
				/**
				 * Делаем плейсхолдер управления пустым
				 */
				$aOrderRepl['[+sb.action+]'] = '';
			}
			/**
			 * Делаем замену
			 */
			$aOrderRows[] = str_replace(array_keys($aOrderRepl), array_values($aOrderRepl), $this->aTemplates['order_row']);
		}
		/**
		 * Плейсхолдеры для вставки в контейнер
		 */
		$phModule = array(
			'[+sb.wrapper+]' => implode('', $aOrderRows),
			'[+sb.jsdata+]' => json_encode($aJsOrderData),
			'[+sb.jsdatanew+]' => json_encode($aJsProductsData),
			/**
			 * Служебные плейсхолдеры для модуля
			 */
			'[+site.url+]' => MODX_BASE_URL,
			'[+module.link+]' => $this->sModuleLink,
			'[+module.act+]' => $this->sAct,
		);
		/**
		 * Подготавливаем языковые плейсхолдеры
		 */
		$phLang = $modx->sbshop->arrayToPlaceholders($modx->sbshop->lang, 'lang.');
		/**
		 * Объединяем плейсхолдеры с языковыми
		 */
		$phModule = array_merge($phModule, $phLang);
		/**
		 * Определяем выделенный статус
		 */
		if(is_numeric($_POST['order_status'])) {
			$sChecked = intval($_POST['order_status']);
		} else {
			$sChecked = 'active';
		}
		/**
		 * Задаем текущий статус
		 */
		 $phModule['[+sb.status_' . $sChecked .  '_selected+]'] = 'selected="selected"';
		/**
		 * Вставляем в контейнер
		 */
		$sOutput = str_replace(array_keys($phModule), array_values($phModule),  $this->aTemplates['order_outer']);
		/**
		 * Выводим
		 */
		return $sOutput;
	}

}

?>
