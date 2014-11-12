Инструкция по интеграции платежной системы OOS с интернет-магазином на Joomla – Virtuemart

1. Установить скачанный плагин через установщик Joomla
	* Перейти в административную панель
	* Перейти в Extensions
	* Перейти в Extension Manager
	* Выбрать таб Install, Package File
	* выбрать архив плагина plg_vmpayment_oos.zip
	* Выбрать upload&install
2. Активировать плагин:
	* в административной панели выбрать Extensions
	* перейти в Plugin Manager
	* ввести в фильтре oos
	* статус плагин поменять на Enable
3. В настройках Virtuemart добавить новый способ оплаты Oos:
	* Components -> VirtueMart -> Shop -> Payment System
	* New
	* Укажите PaymentName, например "OOS"
	* Published Yes
	* Payment Method выберите OOS из выпадающего списка
	* Нажмите кнопку сохранить (Save)
4. Перейдите во вкладку конфигурации и укажите следующие параметры:
	* Logos – выбрать oos_logo.jpg (Дополнительно из папки yourshop\plugins\vmpayment\oos\ скопируйте картинку oos_logo.jpg в папку yourshop\images\stories\virtuemart\payment\)
	* Url pay page - Если вы работаете в тестовом режиме, то укажите https://oosdemo.pscb.ru/pay/. Если в рабочем - https://oos.pscb.ru/pay/ 
	* Merchant ID – укажите значение ID Магазина со страницы личного кабинета в системе OOS(https://oos.pscb.ru/)
	* Key API in OOS system - укажите значение Секрутный ключ API со страницы личного кабинета в OOS (https://oos.pscb.ru/)
	* Остальные параметры укажите по своему усмотрению
	* Сохраните новую платежную систему
	* Перейдите на страницу личного кабинета в системе OOS (https://oos.pscb.ru/)
	* Укажите параметр URL уведомления магазина `http://ваш_магазин/plugins/vmpayment/oos/oos_notify.php`
	* Сохраните изменения
