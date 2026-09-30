<?php
/**
 * Installer of the module mtai.bpservices.
 *
 * On install:
 *   1. infoblock type + infoblock "Сервисы бизнес-процессов" with the
 *      PROCESS_IBLOCK (select of universal lists) and ICON (file) properties;
 *   2. iblock event handlers keeping the select in sync with the lists;
 *   3. public page /bp-services/ and the light bp_services site template
 *      with a b_site_template rule and file access for group 2.
 *
 * On uninstall all of the above are removed, including the infoblock content.
 */

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\SiteTemplateTable;
use Mtai\Bpservices\IblockManager;

// модуль ещё не зарегистрирован — автозагрузка lib/ недоступна
require_once __DIR__ . '/../lib/IblockManager.php';

Loc::loadMessages(__FILE__);

class mtai_bpservices extends CModule
{
	public $MODULE_ID = 'mtai.bpservices';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $MODULE_GROUP_RIGHTS = 'N';

	/** Site template installed by the module for /bp-services/. */
	private const SITE_TEMPLATE = 'bp_services';

	public function __construct()
	{
		$arModuleVersion = [];
		include __DIR__ . '/version.php';

		$this->MODULE_VERSION = (string)($arModuleVersion['VERSION'] ?? '');
		$this->MODULE_VERSION_DATE = (string)($arModuleVersion['VERSION_DATE'] ?? '');

		$this->MODULE_NAME = Loc::getMessage('MTAI_BPS_MODULE_NAME');
		$this->MODULE_DESCRIPTION = Loc::getMessage('MTAI_BPS_MODULE_DESCRIPTION');
		$this->PARTNER_NAME = Loc::getMessage('MTAI_BPS_PARTNER_NAME');
		$this->PARTNER_URI = Loc::getMessage('MTAI_BPS_PARTNER_URI');
	}

	public function InstallDB(): bool
	{
		ModuleManager::registerModule($this->MODULE_ID);

		IblockManager::installInfoblock();

		return true;
	}

	public function UnInstallDB(): bool
	{
		IblockManager::uninstallInfoblock();

		ModuleManager::unRegisterModule($this->MODULE_ID);

		return true;
	}

	public function InstallEvents(): bool
	{
		$manager = EventManager::getInstance();
		foreach (['OnAfterIBlockAdd', 'OnAfterIBlockUpdate', 'OnAfterIBlockDelete'] as $event)
		{
			$manager->registerEventHandlerCompatible(
				'iblock',
				$event,
				$this->MODULE_ID,
				'\Mtai\Bpservices\EventHandler',
				'onAfterIblock' . substr($event, strlen('OnAfterIBlock'))
			);
		}

		// скрытие кнопки «Запустить» на /bizproc/userprocesses/
		$manager->registerEventHandlerCompatible(
			'main',
			'OnEndBufferContent',
			$this->MODULE_ID,
			'\Mtai\Bpservices\EventHandler',
			'onEndBufferContent'
		);

		return true;
	}

	public function UnInstallEvents(): bool
	{
		$manager = EventManager::getInstance();
		foreach (['OnAfterIBlockAdd', 'OnAfterIBlockUpdate', 'OnAfterIBlockDelete'] as $event)
		{
			$manager->unRegisterEventHandler(
				'iblock',
				$event,
				$this->MODULE_ID,
				'\Mtai\Bpservices\EventHandler',
				'onAfterIblock' . substr($event, strlen('OnAfterIBlock'))
			);
		}

		$manager->unRegisterEventHandler(
			'main',
			'OnEndBufferContent',
			$this->MODULE_ID,
			'\Mtai\Bpservices\EventHandler',
			'onEndBufferContent'
		);

		return true;
	}

	public function InstallFiles(): bool
	{
		global $APPLICATION;

		$documentRoot = Application::getDocumentRoot();

		// public page /bp-services/
		CopyDirFiles(__DIR__ . '/public', $documentRoot, true, true);

		// component mtai:bpservices.grid → local/components/mtai/
		$componentsDir = $documentRoot . '/local/components';
		CheckDirPath($componentsDir . '/mtai/');
		CopyDirFiles(__DIR__ . '/components', $componentsDir, true, true);

		// страница рендерится в шаблоне портала по умолчанию;
		// свой шаблон bp_services больше не ставится (с 0.4.1) —
		// при обновлении удаляем его остатки от прежних установок
		$this->removeSiteTemplate();

		// group 2 = all users incl. guests; unauthorized visitors are redirected
		// to /auth/ by the page itself
		$APPLICATION->SetFileAccessPermission('/bp-services/', [2 => 'R']);

		return true;
	}

	/**
	 * Deletes the bp_services site template and its b_site_template rules
	 * (installed by module versions before 0.4.1).
	 */
	private function removeSiteTemplate(): void
	{
		DeleteDirFilesEx('/local/templates/' . self::SITE_TEMPLATE . '/');

		$rows = SiteTemplateTable::getList([
			'filter' => ['=TEMPLATE' => self::SITE_TEMPLATE],
		]);
		while ($row = $rows->fetch())
		{
			SiteTemplateTable::delete($row['ID']);
		}
	}

	public function UnInstallFiles(): bool
	{
		global $APPLICATION;

		DeleteDirFilesEx('/bp-services/');
		DeleteDirFilesEx('/local/components/mtai/bpservices.grid/');
		DeleteDirFilesEx('/local/components/bitrix/lists.element.edit/');
		$this->removeSiteTemplate();

		$APPLICATION->SetFileAccessPermission('/bp-services/', []);

		return true;
	}

	public function DoInstall(): bool
	{
		global $APPLICATION;

		if (!\Bitrix\Main\Loader::includeModule('iblock'))
		{
			$APPLICATION->ThrowException(Loc::getMessage('MTAI_BPS_INSTALL_ERROR_IBLOCK'));

			return false;
		}

		if (empty(IblockManager::getProcessIblocks()))
		{
			// not fatal: the select will be filled as soon as lists appear
			echo Loc::getMessage('MTAI_BPS_INSTALL_WARN_NO_LISTS');
		}

		$this->InstallDB();
		$this->InstallEvents();
		$this->InstallFiles();

		return true;
	}

	public function DoUninstall(): bool
	{
		$this->UnInstallFiles();
		$this->UnInstallEvents();
		$this->UnInstallDB();

		return true;
	}
}
