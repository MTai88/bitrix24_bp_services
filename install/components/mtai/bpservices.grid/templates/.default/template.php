<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

/** @var array $arResult */
?>
<?php if ($arResult['ERROR'] !== ''): ?>
	<div class="bps-notice bps-notice--error"><?= htmlspecialcharsbx($arResult['ERROR']) ?></div>
<?php elseif (empty($arResult['ITEMS'])): ?>
	<div class="bps-notice">
		<p>Сервисы пока не добавлены.</p>
		<?php if ($arResult['IS_ADMIN']): ?>
			<p><a class="bps-btn" href="<?= htmlspecialcharsbx($arResult['ADD_LINK']) ?>">Добавить сервис</a></p>
		<?php endif; ?>
	</div>
<?php else: ?>
	<div class="bps-grid">
		<?php foreach ($arResult['ITEMS'] as $index => $item): ?>
			<a class="bps-card" href="<?= htmlspecialcharsbx($item['URL']) ?>"
				title="Запустить: <?= htmlspecialcharsbx($item['PROCESS_NAME']) ?>">
				<span class="bps-card__icon bps-avatar--<?= $index % 6 ?>">
					<?php if ($item['ICON'] && !empty($item['ICON']['SRC'])): ?>
						<img src="<?= htmlspecialcharsbx($item['ICON']['SRC']) ?>" alt="" loading="lazy">
					<?php else: ?>
						<?= htmlspecialcharsbx(mb_strtoupper(mb_substr($item['NAME'], 0, 1))) ?>
					<?php endif; ?>
				</span>
				<span class="bps-card__body">
					<span class="bps-card__name"><?= htmlspecialcharsbx($item['NAME']) ?></span>
					<span class="bps-card__process"><?= htmlspecialcharsbx($item['PROCESS_NAME']) ?></span>
					<?php if ($item['DESCRIPTION'] !== ''): ?>
						<span class="bps-card__desc"><?= htmlspecialcharsbx($item['DESCRIPTION']) ?></span>
					<?php endif; ?>
				</span>
				<span class="bps-card__arrow" aria-hidden="true">&rarr;</span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php if ($arResult['IS_ADMIN']): ?>
		<div class="bps-admin-links">
			<a href="<?= htmlspecialcharsbx($arResult['ADD_LINK']) ?>">+ Добавить сервис</a>
		</div>
	<?php endif; ?>
<?php endif; ?>
