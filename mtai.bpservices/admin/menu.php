<?php
/**
 * Пункт меню «Контент → Сервисы бизнес-процессов» админки
 * (модуль mtai.bpservices).
 */

use Bitrix\Main\Loader;

$iblockId = 0;
if (Loader::includeModule('iblock'))
{
	$row = CIBlock::GetList(
		[],
		[
			'TYPE' => 'mtai_bpservices',
			'CODE' => 'mtai_bpservices',
			'CHECK_PERMISSIONS' => 'N',
		]
	)->Fetch();
	if ($row)
	{
		$iblockId = (int)$row['ID'];
	}
}

if ($iblockId > 0)
{
	$url = 'iblock_element_admin.php?IBLOCK_ID=' . $iblockId . '&type=mtai_bpservices&lang=' . LANGUAGE_ID;
}
else
{
	$url = 'iblock_admin.php?type=mtai_bpservices&lang=' . LANGUAGE_ID;
}

$aMenu = [
	'parent_menu' => 'global_content',
	'sort' => 360,
	'url' => $url,
	'text' => 'Сервисы бизнес-процессов',
	'title' => 'Каталог сервисов универсальных списков (публичная страница /bp-services/)',
	'icon' => 'iblock_menu_icon_types',
	'page_id' => 'mtai_bpservices',
];

return $aMenu;
