<?php

namespace Mtai\Bpservices\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use CIBlockProperty;
use CTempFile;

/**
 * AJAX-загрузка файлов для FilePond: файл уходит на сервер сразу при выборе,
 * форма отправляет только id, а копия компонента lists.element.edit перед
 * сохранением элемента восстанавливает файлы в $_FILES (см. мост в component.php).
 *
 * Мета файла хранится в сессии загрузившего: id => [path, name, type, size,
 * iblockId, propertyId, userId]. Сами файлы — во временном каталоге
 * CTempFile (автоочистка агентами), revert удаляет сразу.
 */
class File extends Controller
{
	private const SESSION_KEY = 'MTAI_BPSERVICES_FILES';

	/** Максимальный размер файла, байт (50 МБ). */
	private const MAX_SIZE = 52428800;

	public function configureActions(): array
	{
		$filters = [
			new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
			new ActionFilter\Csrf(),
		];

		return [
			'upload' => ['filters' => $filters],
			'revert' => ['filters' => $filters],
		];
	}

	/**
	 * FilePond process: multipart-поле file. Возвращает id загруженного файла.
	 */
	public function uploadAction(int $iblockId, int $propertyId): array
	{
		if (!Loader::includeModule('iblock'))
		{
			$this->addError(new Error('Модуль iblock не установлен'));

			return [];
		}

		$user = CurrentUser::get();
		if ((int)$user->getId() <= 0)
		{
			$this->addError(new Error('Требуется авторизация'));

			return [];
		}

		// поле должно быть свойством-файлом этого инфоблока
		$property = CIBlockProperty::GetList([], [
			'ID' => $propertyId,
			'IBLOCK_ID' => $iblockId,
			'PROPERTY_TYPE' => 'F',
			'CHECK_PERMISSIONS' => 'N',
		])->Fetch();
		if (!$property)
		{
			$this->addError(new Error('Поле-файл не найдено'));

			return [];
		}

		$file = $this->request->getFileList()->get('file');
		if (
			!is_array($file)
			|| ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| !is_uploaded_file($file['tmp_name'] ?? '')
		)
		{
			$this->addError(new Error('Файл не получен'));

			return [];
		}

		$size = (int)$file['size'];
		if ($size <= 0 || $size > self::MAX_SIZE)
		{
			$this->addError(new Error('Размер файла больше допустимого (50 МБ)'));

			return [];
		}

		// ограничение расширений из настроек свойства (FILE_TYPE: "svg, png, ...")
		$fileType = trim((string)($property['FILE_TYPE'] ?? ''));
		if ($fileType !== '')
		{
			$extension = mb_strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
			$allowed = array_map(
				static fn ($ext) => mb_strtolower(trim($ext)),
				explode(',', $fileType)
			);
			if ($extension === '' || !in_array($extension, $allowed, true))
			{
				$this->addError(new Error('Файл этого типа загружать нельзя'));

				return [];
			}
		}

		$id = bin2hex(random_bytes(16));
		$directory = CTempFile::GetDirectoryName(12, ['mtai.bpservices', 'filepond']);
		CheckDirPath($directory);
		$path = $directory . $id;
		if (!move_uploaded_file($file['tmp_name'], $path))
		{
			$this->addError(new Error('Не удалось сохранить файл'));

			return [];
		}

		if (!is_array($_SESSION[self::SESSION_KEY] ?? null))
		{
			$_SESSION[self::SESSION_KEY] = [];
		}
		$_SESSION[self::SESSION_KEY][$id] = [
			'path' => $path,
			'name' => (string)$file['name'],
			'type' => (string)$file['type'],
			'size' => $size,
			'iblockId' => $iblockId,
			'propertyId' => $propertyId,
			'userId' => (int)$user->getId(),
		];

		return ['id' => $id];
	}

	/**
	 * FilePond revert: файл убрали из пруда до отправки — удаляем сразу.
	 */
	public function revertAction(string $id): array
	{
		$meta = $_SESSION[self::SESSION_KEY][$id] ?? null;
		if (is_array($meta))
		{
			if (is_file($meta['path']))
			{
				unlink($meta['path']);
			}
			unset($_SESSION[self::SESSION_KEY][$id]);
		}

		return ['id' => $id];
	}
}
