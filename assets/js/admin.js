/**
 * Elementor to Bricks Media Migrator - Admin Script
 *
 * @package Elementor_To_Bricks_Media_Migrator
 * @author  Abid KP (https://abidkp.com)
 */

(function ($) {
	'use strict';

	$(function () {
		const config = window.ebmmAdmin || window.gmmAdmin || {};
		const nonce = config.nonce || '';
		const ajaxUrl = config.ajaxUrl || window.ajaxurl;
		const i18n = config.i18n || {
			loadingMetadata: 'Loading target metadata...',
			couldNotLoadMetadata: 'Could not load target metadata.',
			targetSelected: 'Target image selected. Ready to copy or edit.',
			confirmCopy: 'Copy source Title, Alt, Caption and Description to this target image? You can edit them before or after saving.',
			copying: 'Copying...',
			copyButton: 'Copy Source → Target',
			copyFailed: 'Copy failed.',
			copied: 'Copied ✓',
			saving: 'Saving...',
			saveButton: 'Save Target Changes',
			saveFailed: 'Save failed.',
			saved: 'Saved ✓',
			selectPage: '— Select a page —',
		};

		// ---------------------------------------------------------------------
		// Tab Switching
		// ---------------------------------------------------------------------
		function switchTab(tabId) {
			const $tabLink = $('.gmm-tab[href="' + tabId + '"]');
			const $targetPanel = $(tabId);

			if ($tabLink.length && $targetPanel.length) {
				$('.gmm-tab').removeClass('active');
				$('.gmm-tab-panel').removeClass('active');

				$tabLink.addClass('active');
				$targetPanel.addClass('active');
			}
		}

		$('.gmm-tab').on('click', function (e) {
			e.preventDefault();
			const targetId = $(this).attr('href');
			switchTab(targetId);

			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, null, targetId);
			}
		});

		// Check URL hash on page load
		if (window.location.hash && $(window.location.hash).hasClass('gmm-tab-panel')) {
			switchTab(window.location.hash);
		}

		// ---------------------------------------------------------------------
		// Unified Filter: Post Type & Search Input
		// ---------------------------------------------------------------------
		function filterSourcePages() {
			const selectedPt = $('#ebmm-pt-filter').val();
			const query = ($('#ebmm-search-input').val() || '').toLowerCase().trim();
			const $sourceSelect = $('#ebmm-source-select');
			let matchCount = 0;
			let totalCount = 0;

			$sourceSelect.find('option').each(function () {
				const $opt = $(this);
				if (!$opt.val()) {
					$opt.show().prop('disabled', false);
					return;
				}

				totalCount++;
				const optPt = $opt.data('post-type') || '';
				const optSlug = ($opt.data('slug') || '').toLowerCase();
				const optText = $opt.text().toLowerCase();

				const matchesPt = !selectedPt || optPt === selectedPt;
				const matchesQuery = !query || optText.indexOf(query) !== -1 || optSlug.indexOf(query) !== -1;

				if (matchesPt && matchesQuery) {
					$opt.show().prop('disabled', false);
					matchCount++;
				} else {
					$opt.hide().prop('disabled', true);
				}
			});

			const $counter = $('#ebmm-page-match-count');
			if (query || selectedPt) {
				$counter.text('(' + matchCount + ' of ' + totalCount + ')');
			} else {
				$counter.text('');
			}
		}

		$('#ebmm-pt-filter').on('change', filterSourcePages);
		$('#ebmm-search-input').on('input', filterSourcePages);

		// ---------------------------------------------------------------------
		// Progress & Row State Manager
		// ---------------------------------------------------------------------
		function updateRowSyncedState($row, targetId) {
			$row.attr('data-is-synced', '1');
			$row.removeClass('ebmm-row-pending').addClass('ebmm-row-synced');

			// Update row badge
			$row.find('.ebmm-badge')
				.removeClass('ebmm-badge-pending')
				.addClass('ebmm-badge-synced')
				.text('✓ Synced');

			// Update target thumbnail badge
			if (targetId) {
				const $targetBtn = $row.find('.gmm-target-option[data-id="' + targetId + '"]');
				$row.find('.gmm-target-option').removeClass('ebmm-option-synced');
				$row.find('.ebmm-hint-synced').remove();

				$targetBtn.addClass('ebmm-option-synced');
				if (!$targetBtn.find('.ebmm-hint-synced').length) {
					$('<span class="ebmm-hint-tag ebmm-hint-synced">✓ Mapped</span>').appendTo($targetBtn);
				}
			}

			// Update page progress stats
			const total = $('.gmm-image-row').length;
			const synced = $('.gmm-image-row[data-is-synced="1"]').length;

			$('.ebmm-synced-counter strong').text(synced);
			const pct = total > 0 ? Math.round((synced / total) * 100) : 0;
			$('.ebmm-progress-fill').css('width', pct + '%');
		}

		// ---------------------------------------------------------------------
		// Target Option Selection
		// ---------------------------------------------------------------------
		$('.gmm-target-option').on('click', function () {
			const $option = $(this);
			const $row = $option.closest('.gmm-image-row');
			const attachmentId = parseInt($option.data('id'), 10);
			const imageUrl = $option.data('url');
			const imageTitle = $option.attr('title') || '';

			if (!attachmentId) {
				return;
			}

			// Update selection highlight
			$row.find('.gmm-target-option').removeClass('selected');
			$option.addClass('selected');
			$row.find('.gmm-target-id').val(attachmentId);

			// Safely update preview image
			const $previewContainer = $row.find('.gmm-target-preview');
			$previewContainer.empty();
			$('<img>', {
				src: imageUrl,
				alt: imageTitle,
			}).appendTo($previewContainer);

			// Reset buttons and status
			const $copyBtn = $row.find('.gmm-copy');
			const $saveBtn = $row.find('.ebmm-save-meta');
			$copyBtn.prop('disabled', true).text(i18n.copyButton);
			$saveBtn.prop('disabled', true).text(i18n.saveButton);

			const $status = $row.find('.gmm-status');
			$status.removeClass('success error').text(i18n.loadingMetadata);

			// Fetch target attachment current metadata
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ebmm_get_attachment',
					nonce: nonce,
					attachment_id: attachmentId,
				},
				success: function (response) {
					if (!response || !response.success) {
						const errorMsg =
							response && response.data && response.data.message
								? response.data.message
								: i18n.couldNotLoadMetadata;
						$status.addClass('error').text(errorMsg);
						return;
					}

					const d = response.data;
					$row.find('.ebmm-target-title').val(d.title || '');
					$row.find('.ebmm-target-alt').val(d.alt || '');
					$row.find('.ebmm-target-caption').val(d.caption || '');
					$row.find('.ebmm-target-description').val(d.description || '');

					$copyBtn.prop('disabled', false);
					$saveBtn.prop('disabled', false);
					$status.removeClass('error').text(i18n.targetSelected);
				},
				error: function () {
					$status.addClass('error').text(i18n.couldNotLoadMetadata);
				},
			});
		});

		// ---------------------------------------------------------------------
		// Copy Metadata Action (Source → Target)
		// ---------------------------------------------------------------------
		$('.gmm-copy').on('click', function () {
			const $button = $(this);
			const $row = $button.closest('.gmm-image-row');
			const sourceId = parseInt($row.data('source-id'), 10);
			const targetId = parseInt($row.find('.gmm-target-id').val(), 10);
			const $status = $row.find('.gmm-status');

			if (!sourceId || !targetId) {
				return;
			}

			if (!window.confirm(i18n.confirmCopy)) {
				return;
			}

			$button.prop('disabled', true).text(i18n.copying);
			$status.removeClass('success error').text(i18n.copying);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ebmm_copy_metadata',
					nonce: nonce,
					source_id: sourceId,
					target_id: targetId,
				},
				success: function (response) {
					if (!response || !response.success) {
						const errorMsg =
							response && response.data && response.data.message
								? response.data.message
								: i18n.copyFailed;
						$status.addClass('error').text(errorMsg);
						$button.prop('disabled', false).text(i18n.copyButton);
						return;
					}

					const d = response.data;
					$row.find('.ebmm-target-title').val(d.title || '');
					$row.find('.ebmm-target-alt').val(d.alt || '');
					$row.find('.ebmm-target-caption').val(d.caption || '');
					$row.find('.ebmm-target-description').val(d.description || '');

					$status.removeClass('error').addClass('success').text(d.message);
					$button.prop('disabled', false).text(i18n.copied);
					$row.find('.ebmm-save-meta').prop('disabled', false).text(i18n.saveButton);

					updateRowSyncedState($row, targetId);
				},
				error: function () {
					$status.addClass('error').text(i18n.copyFailed);
					$button.prop('disabled', false).text(i18n.copyButton);
				},
			});
		});

		// ---------------------------------------------------------------------
		// Save Edited Target Metadata Action
		// ---------------------------------------------------------------------
		$('.ebmm-save-meta').on('click', function () {
			const $button = $(this);
			const $row = $button.closest('.gmm-image-row');
			const targetId = parseInt($row.find('.gmm-target-id').val(), 10);
			const $status = $row.find('.gmm-status');

			if (!targetId) {
				return;
			}

			const title = $row.find('.ebmm-target-title').val();
			const alt = $row.find('.ebmm-target-alt').val();
			const caption = $row.find('.ebmm-target-caption').val();
			const description = $row.find('.ebmm-target-description').val();

			$button.prop('disabled', true).text(i18n.saving);
			$status.removeClass('success error').text(i18n.saving);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ebmm_save_metadata',
					nonce: nonce,
					target_id: targetId,
					title: title,
					alt: alt,
					caption: caption,
					description: description,
				},
				success: function (response) {
					if (!response || !response.success) {
						const errorMsg =
							response && response.data && response.data.message
								? response.data.message
								: i18n.saveFailed;
						$status.addClass('error').text(errorMsg);
						$button.prop('disabled', false).text(i18n.saveButton);
						return;
					}

					const d = response.data;
					$status.removeClass('error').addClass('success').text(d.message);
					$button.prop('disabled', false).text(i18n.saved);

					updateRowSyncedState($row, targetId);
				},
				error: function () {
					$status.addClass('error').text(i18n.saveFailed);
					$button.prop('disabled', false).text(i18n.saveButton);
				},
			});
		});
	});
})(jQuery);
