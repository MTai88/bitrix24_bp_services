<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Web\Uri;

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */

\Bitrix\Main\Loader::includeModule('ui');

CJSCore::Init(array('window', 'lists'));
Bitrix\Main\UI\Extension::load(["ui.buttons", "ui.dialogs.messagebox"]);

// mtai.bpservices: FilePond — загрузчик файлов в полях-файлах списка.
// Подключение инлайн (не через Asset): форма открывается и в слайдере
// (livefeed), где ассеты из Asset в ответ не попадают
?>
<link rel="stylesheet" href="/bitrix/js/mtai.bpservices/filepond/filepond.min.css">
<script src="/bitrix/js/mtai.bpservices/filepond/filepond.min.js" data-skip-moving="true"></script>
<style>
	.bx-field-value .filepond--root { margin-bottom: 0; }
</style>
<?php

$jsClass = 'ListsElementEditClass_'.$arResult['RAND_STRING'];
$urlTabBp = (string)(new Uri($APPLICATION->GetCurPageParam("", array($arResult["FORM_ID"]."_active_tab"))))
	->addParams([$arResult["FORM_ID"]."_active_tab" => "tab_bp"])
;
$socnetGroupId = $arParams["SOCNET_GROUP_ID"] ?: 0;
$sectionId = $arResult["SECTION_ID"] ?: 0;

// mtai.bpservices: форма настраивается под страницу сервисов /bp-services/
global $USER;
$isAdmin = is_object($USER) && $USER->IsAdmin();
// копия элемента — тоже создание
$isAddForm = !((int)$arResult["ELEMENT_ID"] > 0 && !(int)$arResult["COPY_ID"] > 0);
// mtai.bpservices: «Отменить» — при добавлении на страницу сервисов,
// при изменении — к запущенным процессам
$cancelUrl = $isAddForm ? '/bp-services/' : '/bizproc/userprocesses/';

$listAction = array();
if (isset($arResult["LIST_COPY_ELEMENT_URL"]))
{
	if($arResult["CAN_ADD_ELEMENT"])
	{
		$listAction[] = [
			"text" => GetMessage("CT_BLEE_TOOLBAR_COPY_ELEMENT"),
			"href" => $arResult["LIST_COPY_ELEMENT_URL"]
		];
	}
}

if (CLists::isEnabledLockFeature($arResult["IBLOCK_ID"]) &&
	$arResult["ELEMENT_ID"] && ($arResult["CAN_FULL_EDIT"] ||
	!CIBlockElement::WF_IsLocked($arResult["ELEMENT_ID"], $lockedBy, $dateLock)))
{
	$listAction[] = [
		"text" => GetMessage("CT_BLEE_UN_LOCK_ELEMENT"),
		"onclick" => new \Bitrix\UI\Buttons\JsCode(
			"BX.Lists['".$jsClass."'].unLock();"
		),
	];
}

if($arResult["CAN_DELETE_ELEMENT"])
{
	$listAction[] = [
		"text" => $arResult["IBLOCK"]["ELEMENT_DELETE"],
		"onclick" => new \Bitrix\UI\Buttons\JsCode(
			"BX.Lists['".$jsClass."'].elementDelete('form_".$arResult["FORM_ID"]."',
			'".GetMessage("CT_BLEE_TOOLBAR_DELETE_WARNING")."')"
		),
	];
}

if(!IsModuleInstalled("intranet"))
{
	\Bitrix\Main\UI\Extension::load([
		'ui.design-tokens',
		'ui.fonts.opensans',
	]);

	$APPLICATION->SetAdditionalCSS("/bitrix/js/lists/css/intranet-common.css");
}

\Bitrix\UI\Toolbar\Facade\Toolbar::deleteFavoriteStar();
\Bitrix\UI\Toolbar\Facade\Toolbar::addButton([
		// mtai.bpservices: возврат — как у кнопки «Отменить»
		'link' => $isAdmin ? $arResult["LIST_SECTION_URL"] : $cancelUrl,
		'color' => \Bitrix\UI\Buttons\Color::LINK,
		'text' => GetMessage("CT_BLEE_TOOLBAR_RETURN_LIST_ELEMENT_MSGVER_1"),
		'icon' => Bitrix\UI\Buttons\Icon::BACK,
	]
);

if ($listAction)
{
	$settingsButton = new Bitrix\UI\Buttons\SettingsButton([
		'menu' => [
			'items' => $listAction,
		],
	]);
	\Bitrix\UI\Toolbar\Facade\Toolbar::addButton($settingsButton);
}

$tabElement = array();
$cuctomHtml = "";
foreach($arResult["FIELDS"] as $fieldId => $field)
{
	$field["LIST_SECTIONS_URL"] = $arParams["~LIST_SECTIONS_URL"] ?? null;
	$field["SOCNET_GROUP_ID"] = $socnetGroupId;
	$field["LIST_ELEMENT_URL"] = $arParams["~LIST_ELEMENT_URL"];
	$field["LIST_FILE_URL"] = $arParams["~LIST_FILE_URL"];
	$field["IBLOCK_ID"] = $arResult["IBLOCK_ID"];
	$field["SECTION_ID"] = intval($arParams["~SECTION_ID"]);
	$field["ELEMENT_ID"] = $arResult["ELEMENT_ID"];
	$field["FIELD_ID"] = $fieldId;
	$field["VALUE"] = $arResult["FORM_DATA"]["~".$fieldId];
	$field["COPY_ID"] = $arResult["COPY_ID"];
	$preparedData = \Bitrix\Lists\Field::prepareFieldDataForEditForm($field);
	if($preparedData)
	{
		$tabElement[] = $preparedData;
		if(!empty($preparedData["customHtml"]))
		{
			$cuctomHtml .= $preparedData["customHtml"];
		}
	}
}

$tabSection = array(
	array(
		"id" => "IBLOCK_SECTION_ID",
		"name" => $arResult["~IBLOCK"]["SECTIONS_NAME"] ?? null,
		"type" => "list",
		"items" => $arResult["LIST_SECTIONS"],
		"params" => array("size" => 15),
	),
);

// mtai.bpservices: вкладка «Раздел» не выводится никому
$arTabs = array(
	array("id" => "tab_el", "name" => $arResult["~IBLOCK"]["ELEMENT_NAME"] ?? null, "icon" => "", "fields" => $tabElement)
);

// mtai.bpservices: «Бизнес-процессы» и «Доступ» — только администратору
if (
	$isAdmin
	&& CModule::IncludeModule("bizproc")
	&& CLists::isBpFeatureEnabled($arParams["IBLOCK_TYPE_ID"])
	&& ($arResult["IBLOCK"]["BIZPROC"] ?? null) != "N"
)
{
	if ($arResult["ELEMENT_ID"] > 0)
	{
		$complexDocumentId = BizProcDocument::getDocumentComplexId($arParams["IBLOCK_TYPE_ID"],
			$arResult["ELEMENT_ID"]);

		ob_start();
		$APPLICATION->IncludeComponent(
			"bitrix:bizproc.document",
			"frame",
			[
				'MODULE_ID' => 'lists',
				'ENTITY' => $complexDocumentId[1],
				'DOCUMENT_TYPE' => 'iblock_' . $arResult["IBLOCK_ID"],
				'DOCUMENT_ID' => $complexDocumentId[2],
				'LAZYLOAD' => 'Y',
			],
			$component, ["HIDE_ICONS" => "Y"]
		);

		$arTabs[] = [
			"id" => "tab_bp",
			"name" => GetMessage("CT_BLEE_BIZPROC_TAB"),
			"icon" => "",
			"fields" => [
				[
					"id" => "BIZPROC",
					"type" => "custom",
					"colspan" => true,
					"value" => ob_get_clean(),
				],
			],
		];
	}
	else
	{
		$bizprocTabFields = [];

		$bizProcIndex = 0;
		$arDocumentStates = CBPWorkflowTemplateLoader::GetDocumentTypeStates(
			BizProcDocument::generateDocumentComplexType($arParams["IBLOCK_TYPE_ID"], $arResult["IBLOCK_ID"]),
			CBPDocumentEventType::Create
		);

		$runtime = CBPRuntime::GetRuntime();
		$runtime->StartRuntime();
		$documentService = $runtime->GetService("DocumentService");

		foreach ($arDocumentStates as $arDocumentState)
		{
			$templateId = (int)$arDocumentState["TEMPLATE_ID"];
			$templateConstants = CBPWorkflowTemplateLoader::getTemplateConstants($templateId);

			if (
				empty($arDocumentState["TEMPLATE_PARAMETERS"])
				&& empty($arDocumentState["ID"])
				&& empty($templateConstants)
				&& !CIBlockRights::UserHasRightTo($arResult["IBLOCK_ID"], $arResult["IBLOCK_ID"], 'iblock_edit')
			)
			{
				continue;
			}

			$bizProcIndex++;

			$canViewWorkflow = CBPDocument::CanUserOperateDocumentType(
				CBPCanUserOperateOperation::StartWorkflow,
				$GLOBALS["USER"]->GetID(),
				BizProcDocument::generateDocumentComplexType($arParams["IBLOCK_TYPE_ID"], $arResult["IBLOCK_ID"]),
				[
					"sectionId" => (int)$arResult["SECTION_ID"],
					"DocumentStates" => $arDocumentStates,
				]
			);

			if ($canViewWorkflow)
			{
				$bizprocTabFields[] = [
					"id" => "BIZPROC_TITLE" . $bizProcIndex,
					"name" => $arDocumentState["TEMPLATE_NAME"],
					"type" => "section",
				];

				$bizprocTabFields[] = [
					"id" => "BIZPROC_NAME" . $bizProcIndex,
					"name" => GetMessage("CT_BLEE_BIZPROC_NAME"),
					"type" => "label",
					"value" => htmlspecialcharsbx($arDocumentState["TEMPLATE_NAME"]),
				];

				if ($arDocumentState["TEMPLATE_DESCRIPTION"] != '')
				{
					$bizprocTabFields[] = [
						"id" => "BIZPROC_DESC" . $bizProcIndex,
						"name" => GetMessage("CT_BLEE_BIZPROC_DESC"),
						"type" => "label",
						"value" => htmlspecialcharsbx($arDocumentState["TEMPLATE_DESCRIPTION"]),
					];
				}

				$arWorkflowParameters = $arDocumentState["TEMPLATE_PARAMETERS"];
				if (!is_array($arWorkflowParameters))
				{
					$arWorkflowParameters = [];
				}
				$bVarsFromForm = $arResult["VARS_FROM_FORM"];
				if ($templateId > 0)
				{
					$parametersValues = [];
					$keys = array_keys($arWorkflowParameters);
					foreach ($keys as $key)
					{
						$v = $bVarsFromForm
							? ($_REQUEST['bizproc' . $templateId . '_' . $key] ?? null)
							: $arWorkflowParameters[$key]['Default']
						;
						if (!is_array($v))
						{
							$parametersValues[$key] = $v;
						}
						else
						{
							foreach (array_keys($v) as $subKey)
							{
								$parametersValues[$key][$subKey] = $v[$subKey];
							}
						}
					}

					foreach ($arWorkflowParameters as $parameterKey => $arParameter)
					{
						$parameterKeyExt = "bizproc" . $templateId . "_" . $parameterKey;

						$html = $documentService->GetFieldInputControl(
							BizProcDocument::generateDocumentComplexType($arParams["IBLOCK_TYPE_ID"],
								$arResult["IBLOCK_ID"]),
							$arParameter,
							["Form" => "start_workflow_form1", "Field" => $parameterKeyExt],
							$parametersValues[$parameterKey] ?? null,
							false,
							true
						);

						$bizprocTabFields[] = [
							"id" => $parameterKeyExt . $bizProcIndex,
							"required" => $arParameter["Required"],
							"name" => $arParameter["Name"],
							"title" => $arParameter["Description"],
							"type" => "label",
							"value" => '<div>' . $html . '</div>',
						];
					}

					if (
						!empty($templateConstants)
						&& CIBlockRights::UserHasRightTo($arResult["IBLOCK_ID"], $arResult["IBLOCK_ID"], 'iblock_edit')
					)
					{
						$listTemplateId = [];
						$listTemplateId[$templateId]['ID'] = $templateId;
						$listTemplateId[$templateId]['NAME'] = htmlspecialcharsbx($arDocumentState["TEMPLATE_NAME"]);
						$bizprocTabFields[] = [
							"id" => "BIZPROC_CONSTANTS" . $bizProcIndex,
							"name" => GetMessage("CT_BLEE_BIZPROC_CONSTANTS_LABLE"),
							"type" => "label",
							"value" => '<a href="javascript:void(0)" id="lists-fill-constants-'
								. $bizProcIndex
								. '"
							onclick="BX.Lists[\''
								. $jsClass
								. '\'].fillConstants('
								. CUtil::PhpToJSObject($listTemplateId)
								. ');">'
								. GetMessage("CT_BLEE_BIZPROC_CONSTANTS_FILL")
								. '</a>',
						];
					}
				}
			}
		}

		if (!$bizProcIndex)
		{
			$bizprocTabFields[] = [
				"id" => "BIZPROC_NO",
				"name" => GetMessage("CT_BLEE_BIZPROC_NA_LABEL"),
				"type" => "label",
				"value" => GetMessage("CT_BLEE_BIZPROC_NA"),
			];
		}

		$arTabs[] = [
			"id" => "tab_bp",
			"name" => GetMessage("CT_BLEE_BIZPROC_TAB"),
			"icon" => "",
			"fields" => $bizprocTabFields,
		];
	}
}

if ($isAdmin && isset($arResult["RIGHTS"]))
{
	ob_start();
	IBlockShowRights(
		/*$entity_type=*/'element',
		/*$iblock_id=*/$arResult["IBLOCK_ID"],
		/*$id=*/$arResult["ELEMENT_ID"],
		/*$section_title=*/"",
		/*$variable_name=*/"RIGHTS",
		/*$arPossibleRights=*/$arResult["TASKS"],
		/*$arActualRights=*/$arResult["RIGHTS"],
		/*$bDefault=*/true,
		/*$bForceInherited=*/$arResult["ELEMENT_ID"] <= 0
	);
	$rights_html = ob_get_clean();

	$rights_fields = array(
		array(
			"id"=>"RIGHTS",
			"name"=>GetMessage("CT_BLEE_ACCESS_RIGHTS"),
			"type"=>"custom",
			"colspan"=>true,
			"value"=>$rights_html,
		),
	);
	$arTabs[] = array(
		"id"=>"tab_rights",
		"name"=>GetMessage("CT_BLEE_TAB_ACCESS"),
		"icon"=>"",
		"fields"=>$rights_fields,
	);
}

$cuctomHtml .= '<input type="hidden" name="action" id="action" value="">';

// mtai.bpservices: свои кнопки вместо стандартных «Сохранить / Применить / Отменить» —
// «Добавить|Изменить» и «Отменить», отмена всегда ведёт на страницу сервисов
$customButtons = '';
if ($arParams["CAN_EDIT"])
{
	$customButtons .= '<input type="submit" name="save" value="'
		. GetMessage($isAddForm ? "MTAI_BPS_FORM_BUTTON_ADD" : "MTAI_BPS_FORM_BUTTON_EDIT") . '" />';
}
$customButtons .= '<input type="button" value="' . GetMessage("CT_BLEE_FORM_CANCEL")
	. '" name="cancel" onclick="window.location=\'' . CUtil::addslashes($cancelUrl) . '\'"'
	. ' title="' . GetMessage("CT_BLEE_FORM_CANCEL_TITLE") . '" />';

$lockStatus = CLists::isEnabledLockFeature($arResult["IBLOCK_ID"]) && $arResult["ELEMENT_ID"];
if ($lockStatus)
{
	$APPLICATION->IncludeComponent(
		"bitrix:lists.lock.status.widget",
		"",
		[
			"ELEMENT_ID" => $arResult["ELEMENT_ID"],
			"ELEMENT_NAME" => $arResult["IBLOCK"]["ELEMENT_NAME"]
		],
		$component, ["HIDE_ICONS" => "Y"]
	);
}

// mtai.bpservices: не-администратор видит форму без полосы вкладок
if (!$isAdmin)
{
	echo '<style>.bx-edit-tabs{display:none !important;}</style>';
}

$APPLICATION->IncludeComponent(
	"bitrix:main.interface.form",
	"",
	array(
		"FORM_ID"=>$arResult["FORM_ID"],
		"TABS"=>$arTabs,
		"BUTTONS"=>array(
			"standard_buttons" => false,
			"back_url"=>$arResult["BACK_URL"],
			"custom_html"=>$cuctomHtml . $customButtons,
		),
		"DATA"=>$arResult["FORM_DATA"],
		"SHOW_SETTINGS"=>"N",
		"THEME_GRID_ID"=>$arResult["GRID_ID"],
	),
	$component, array("HIDE_ICONS" => "Y")
);
?>

<div id="lists-notify-admin-popup" style="display:none;">
	<div id="lists-notify-admin-popup-content" class="lists-notify-admin-popup-content">
	</div>
</div>

<script data-skip-moving="true">
	// mtai.bpservices: FilePond на файловых полях формы, загрузка сразу (AJAX)
	// в контроллер mtai:bpservices.file.upload. Форма отправляет только id —
	// (mtai_bpservices_files[PROPERTY_X][KEY]); копия компонента перед
	// сохранением восстанавливает файлы в $_FILES.
	// Одиночное поле: пруд привязан к ключу слота значения (n0 — новый файл,
	// 123 — замена существующего), т.е. семантика как у нативного input.
	// Множественное поле: ОДИН пруд с мультизагрузкой на свойство; ряды
	// существующих файлов (галочки «Удалить файл») остаются штатными,
	// прячутся только слоты-замены и кнопка «Добавить».
	(function () {
		var initFilePond = function () {
			if (!window.FilePond)
			{
				return;
			}
			var form = document.getElementById('form_<?= CUtil::JSEscape(htmlspecialcharsbx($arResult['FORM_ID'])) ?>');
			if (!form || form.getAttribute('data-filepond'))
			{
				return;
			}
			form.setAttribute('data-filepond', 'Y');

			var sessidInput = form.querySelector('[name="sessid"]');
			var sessid = sessidInput ? sessidInput.value : (window.BX && BX.message('bitrix_sessid'));
			var ajaxUrl = '/bitrix/services/main/ajax.php';
			var iblockId = '<?= (int)$arResult["IBLOCK_ID"] ?>';

			var labels = {
				labelInvalidField: 'Поле содержит файлы неподходящего типа',
				labelFileTypeNotAllowed: 'Файл этого типа загрузить нельзя',
				labelFileWaitingForSize: 'Определяем размер',
				labelFileSizeNotAvailable: 'Размер недоступен',
				labelFileLoading: 'Чтение файла',
				labelFileLoadError: 'Не удалось прочитать файл',
				labelFileProcessing: 'Загрузка…',
				labelFileProcessingComplete: 'Загружен',
				labelFileProcessingAborted: 'Отменена',
				// функция: FilePond показывает эту подпись вместо сообщения
				// из error(), передаём в неё реальную причину с сервера
				labelFileProcessingError: function (err) {
					return (err && err.body) || 'Не удалось загрузить файл';
				}
			};

			// process строго функцией: конфиг-объект с onload в этой версии
			// FilePond получает XHR, а не текст ответа — промис не завершался
			// и файл «висел» на 100%
			var makeProcess = function (propertyId) {
				return function (fieldName, file, metadata, load, error, progress) {
					var formData = new FormData();
					// fieldName у программного пруда пуст (опция name не задана) —
					// часть должна называться ровно 'file', как ждёт контроллер
					formData.append('file', file, file.name || 'file');
					formData.append('sessid', sessid);
					formData.append('iblockId', iblockId);
					formData.append('propertyId', String(propertyId));

					var xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxUrl + '?action=mtai:bpservices.file.upload');
					xhr.upload.addEventListener('progress', function (e) {
						if (e.lengthComputable)
						{
							progress(e.loaded, e.total);
						}
					});
					xhr.addEventListener('load', function () {
						try { console.warn('mtai.bpservices upload:', xhr.status, xhr.responseText.substring(0, 500)); } catch (e) {}
						if (xhr.status < 200 || xhr.status >= 300)
						{
							error('HTTP ' + xhr.status + ': ' + xhr.responseText.substring(0, 200));
							return;
						}
						var data = null;
						try { data = JSON.parse(xhr.responseText); } catch (e) {}
						if (!data || data.status !== 'success' || !data.data || !data.data.id)
						{
							var serverMessage = data && data.errors && data.errors.length ? data.errors[0].message : null;
							error(serverMessage || ('Ответ: ' + xhr.responseText.substring(0, 200)));
							return;
						}
						load(data.data.id);
					});
					xhr.addEventListener('error', function () {
						error('Ошибка сети');
					});
					xhr.send(formData);

					return {
						abort: function () {
							xhr.abort();
						}
					};
				};
			};

			var revert = function (uniqueFileId, load, error) {
				var body = 'id=' + encodeURIComponent(uniqueFileId) + '&sessid=' + encodeURIComponent(sessid);
				fetch(ajaxUrl + '?action=mtai:bpservices.file.revert', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body
				}).then(load).catch(error);
			};

			var attachPond = function (anchor, propName, propertyId, valueKey, multiple) {
				var idInputs = {};
				var nextIndex = 0;

				var pond = FilePond.create(Object.assign({}, labels, {
					allowMultiple: multiple,
					labelIdle: (multiple ? 'Перетащите файлы или ' : 'Перетащите файл или ')
						+ '<span class="filepond--label-action">выберите</span>',
					server: {
						process: makeProcess(propertyId),
						revert: revert
					}
				}));

				// скрытый input с id появляется сразу после загрузки файла,
				// снимается при удалении из пруда (submit не участвует)
				pond.on('processfile', function (err, file) {
					if (err || !file.serverId || idInputs[file.id])
					{
						return;
					}
					var key = valueKey !== null ? valueKey : ('fp' + (nextIndex++));
					var input = document.createElement('input');
					input.type = 'hidden';
					input.name = 'mtai_bpservices_files[' + propName + '][' + key + ']';
					input.value = file.serverId;
					form.appendChild(input);
					idInputs[file.id] = input;
				});

				pond.on('removefile', function (file) {
					if (idInputs[file.id])
					{
						idInputs[file.id].parentNode.removeChild(idInputs[file.id]);
						delete idInputs[file.id];
					}
				});

				anchor.parentNode.insertBefore(pond.element, anchor);
			};

			// одиночные файловые поля
			form.querySelectorAll('input[type=file][name^="PROPERTY_"]').forEach(function (input) {
				var m = input.name.match(/^(PROPERTY_(\d+))\[(n?\d+)\]\[VALUE\]$/);
				if (!m || input.closest('table[id^="tblPROPERTY_"]'))
				{
					return;
				}
				// не disabled: пустая отправка слота нужна штатной механике
				// «Удалить файл» (del=Y применяется к записям $_FILES)
				input.style.display = 'none';
				attachPond(input, m[1], m[2], m[3], false);
			});

			// множественные файловые поля: один пруд на таблицу значений
			form.querySelectorAll('table[id^="tblPROPERTY_"]').forEach(function (tbl) {
				var inputs = tbl.querySelectorAll('input[type=file]');
				if (!inputs.length)
				{
					return;
				}
				var m = inputs[0].name.match(/^(PROPERTY_(\d+))\[/);
				if (!m)
				{
					return;
				}
				inputs.forEach(function (input) {
					// слот отправляется пустым: без записи $_FILES галочка
					// «Удалить файл» компонентом не применяется
					input.style.display = 'none';
				});
				var addButton = tbl.parentNode.querySelector('input[type=button][onclick*="' + tbl.id + '"]');
				if (addButton)
				{
					addButton.style.display = 'none';
				}
				attachPond(tbl, m[1], m[2], null, true);
			});
		};

		if (document.readyState === 'loading')
		{
			document.addEventListener('DOMContentLoaded', initFilePond);
		}
		else
		{
			initFilePond();
		}
	})();
</script>

<script>
	BX.ready(function () {
		BX.Lists['<?=$jsClass?>'] = new BX.Lists.ListsElementEditClass({
			formId: '<?= CUtil::JSEscape(htmlspecialcharsbx($arResult['FORM_ID'])) ?>',
			randomString: '<?=$arResult['RAND_STRING']?>',
			urlTabBp: '<?=$urlTabBp?>',
			iblockTypeId: '<?=$arParams["IBLOCK_TYPE_ID"]?>',
			iblockId: '<?=$arResult["IBLOCK_ID"]?>',
			elementId: '<?=$arResult["ELEMENT_ID"]?>',
			socnetGroupId: '<?=$socnetGroupId?>',
			sectionId: '<?= $sectionId ?>',
			isConstantsTuned: <?= !empty($arResult["isConstantsTuned"]) ? 'true' : 'false' ?>,
			elementUrl: '<?= $arResult["ELEMENT_URL"] ?>',
			sectionUrl: '<?= $arResult["LIST_SECTION_URL"] ?>',
			lockStatus: <?=($lockStatus ? 'true' : 'false')?>
		});

		BX.message({
			CT_BLEE_BIZPROC_SAVE_BUTTON: '<?=GetMessageJS("CT_BLEE_BIZPROC_SAVE_BUTTON")?>',
			CT_BLEE_BIZPROC_CANCEL_BUTTON: '<?=GetMessageJS("CT_BLEE_BIZPROC_CANCEL_BUTTON")?>',
			CT_BLEE_BIZPROC_CONSTANTS_FILL_TITLE: '<?=GetMessageJS("CT_BLEE_BIZPROC_CONSTANTS_FILL_TITLE")?>',
			CT_BLEE_BIZPROC_NOTIFY_TITLE: '<?=GetMessageJS("CT_BLEE_BIZPROC_NOTIFY_TITLE")?>',
			CT_BLEE_BIZPROC_SELECT_STAFF_SET_RESPONSIBLE: '<?=GetMessageJS("CT_BLEE_BIZPROC_SELECT_STAFF_SET_RESPONSIBLE")?>',
			CT_BLEE_BIZPROC_NOTIFY_ADMIN_TEXT_ONE: '<?=GetMessageJS("CT_BLEE_BIZPROC_NOTIFY_ADMIN_TEXT_ONE")?>',
			CT_BLEE_BIZPROC_NOTIFY_ADMIN_TEXT_TWO: '<?=GetMessageJS("CT_BLEE_BIZPROC_NOTIFY_ADMIN_TEXT_TWO")?>',
			CT_BLEE_BIZPROC_NOTIFY_ADMIN_MESSAGE: '<?=GetMessageJS("CT_BLEE_BIZPROC_NOTIFY_ADMIN_MESSAGE")?>',
			CT_BLEE_BIZPROC_NOTIFY_ADMIN_MESSAGE_BUTTON: '<?=GetMessageJS("CT_BLEE_BIZPROC_NOTIFY_ADMIN_MESSAGE_BUTTON")?>',
			CT_BLEE_BIZPROC_NOTIFY_ADMIN_BUTTON_CLOSE: '<?=GetMessageJS("CT_BLEE_BIZPROC_NOTIFY_ADMIN_BUTTON_CLOSE")?>',
			CT_BLEE_DELETE_POPUP_TITLE: '<?=GetMessageJS("CT_BLEE_DELETE_POPUP_TITLE")?>',
			CT_BLEE_DELETE_POPUP_ACCEPT_BUTTON: '<?=GetMessageJS("CT_BLEE_DELETE_POPUP_ACCEPT_BUTTON")?>',
			CT_BLEE_DELETE_POPUP_CANCEL_BUTTON: '<?=GetMessageJS("CT_BLEE_DELETE_POPUP_CANCEL_BUTTON")?>'
		});

		if(BX["viewElementBind"])
		{
			BX.viewElementBind(
				'form_<?=$arResult["FORM_ID"]?>',
				{showTitle: true},
				{attr: 'data-bx-viewer'}
			);
		}
	});
</script>
