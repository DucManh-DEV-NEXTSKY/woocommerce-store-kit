/* global jQuery, wp */
(function ($) {
	'use strict';

	$(function () {
		var $form = $('.sk-settings-form');
		if (!$form.length) {
			return;
		}
		var dirty = $form.attr('data-unsaved') === '1';
		var serial = 0;
		var $live = $('.sk-live');

		function announce(message) {
			$live.text('');
			window.setTimeout(function () { $live.text(message); }, 20);
		}

		function reindex($repeater) {
			var kind = $repeater.attr('data-repeater');
			var $rows = $repeater.children('.sk-rows').children('.sk-row');
			$rows.each(function (index) {
				var $row = $(this);
				$row.find('[name]').each(function () {
					var suffix = this.name.match(/\[[^\]]+\]$/);
					if (suffix) {
						this.name = 'data[' + kind + '][' + index + ']' + suffix[0];
					}
				});
				$row.find('.sk-up').prop('disabled', index === 0);
				$row.find('.sk-down').prop('disabled', index === $rows.length - 1);
			});
			$repeater.children('.sk-empty').prop('hidden', $rows.length !== 0);
		}

		function updateTitle($row) {
			var kind = $row.attr('data-kind');
			var title;
			if (kind === 'featured_products') {
				var $select = $row.find('select').first();
				title = $select.val() && $select.val() !== '0' ? $select.find('option:selected').text() : 'Chọn sản phẩm';
			} else {
				title = $row.find('[name$="[title]"]').val();
			}
			$row.find('.sk-row-title').text(title || 'Dòng mới');
		}

		$('.sk-repeater').each(function () {
			var $repeater = $(this);
			reindex($repeater);
			$repeater.children('.sk-rows').children('.sk-row').each(function () {
				updateTitle($(this));
			});
			$repeater.children('.sk-rows').sortable({
				items: '> .sk-row',
				handle: '.sk-drag',
				placeholder: 'sk-sort-placeholder',
				forcePlaceholderSize: true,
				axis: 'y',
				distance: 8,
				update: function () {
					dirty = true;
					reindex($repeater);
					announce('Đã đổi thứ tự. Chọn Lưu để áp dụng.');
				}
			});
		});

		$form.on('input change', 'input, textarea, select', function () {
			dirty = true;
			var $row = $(this).closest('.sk-row');
			if ($row.length) {
				updateTitle($row);
			}
		});

		$form.on('click', '.sk-add', function () {
			var $repeater = $(this).closest('.sk-repeater');
			var template = $repeater.children('template').html();
			// Keep label IDs unique even after removing or reordering existing rows.
			var $row = $(template.replace(/__INDEX__/g, 'new' + (++serial)));
			$repeater.children('.sk-rows').append($row);
			reindex($repeater);
			updateTitle($row);
			dirty = true;
			$row.find('input:not([type="hidden"]), select, textarea').first().trigger('focus');
			announce('Đã thêm dòng. Chọn Lưu để áp dụng.');
		});

		$form.on('click', '.sk-remove', function () {
			var $row = $(this).closest('.sk-row');
			var $repeater = $row.closest('.sk-repeater');
			if (!window.confirm('Xóa dòng này khỏi danh sách? Thay đổi chỉ áp dụng sau khi lưu.')) {
				return;
			}
			var $focus = $row.next('.sk-row').find('.sk-remove');
			if (!$focus.length) {
				$focus = $row.prev('.sk-row').find('.sk-remove');
			}
			$row.remove();
			reindex($repeater);
			dirty = true;
			($focus.length ? $focus : $repeater.children('.sk-add')).trigger('focus');
			announce('Đã xóa dòng. Chọn Lưu để áp dụng.');
		});

		$form.on('click', '.sk-up, .sk-down', function () {
			var $button = $(this);
			var $row = $button.closest('.sk-row');
			var $other = $button.hasClass('sk-up') ? $row.prev('.sk-row') : $row.next('.sk-row');
			if (!$other.length) {
				return;
			}
			if ($button.hasClass('sk-up')) {
				$row.insertBefore($other);
			} else {
				$row.insertAfter($other);
			}
			reindex($row.closest('.sk-repeater'));
			dirty = true;
			// Moving a row to an edge disables that direction; keep focus on its other control.
			if ($button.prop('disabled')) {
				$row.find($button.hasClass('sk-up') ? '.sk-down' : '.sk-up').trigger('focus');
			} else {
				$button.trigger('focus');
			}
			announce('Đã chuyển dòng. Chọn Lưu để áp dụng.');
		});

		$form.on('click', '.sk-media', function () {
			var $cover = $(this).closest('.sk-cover');
			var picker = wp.media({
				title: 'Chọn ảnh',
				button: { text: 'Dùng ảnh này' },
				library: { type: 'image' },
				multiple: false
			});
			picker.on('select', function () {
				var image = picker.state().get('selection').first().toJSON();
				var preview = image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url;
				$cover.find('.sk-cover-id').val(image.id).trigger('change');
				$cover.find('.sk-cover-preview').attr('src', preview).prop('hidden', false);
				$cover.find('.sk-cover-empty').prop('hidden', true);
				announce('Đã chọn ảnh bìa. Chọn Lưu để áp dụng.');
			});
			picker.open();
		});

		$form.on('click', '.sk-clear-media', function () {
			var $cover = $(this).closest('.sk-cover');
			$cover.find('.sk-cover-id').val('0').trigger('change');
			$cover.find('.sk-cover-preview').removeAttr('src').prop('hidden', true);
			$cover.find('.sk-cover-empty').prop('hidden', false);
			announce('Đã bỏ ảnh bìa khỏi video. Chọn Lưu để áp dụng.');
		});

		$form.on('submit', function () {
			$('.sk-repeater').each(function () { reindex($(this)); });
			dirty = false;
		});
		$(window).on('beforeunload', function (event) {
			if (dirty) {
				event.preventDefault();
				event.originalEvent.returnValue = '';
				return '';
			}
		});
	});
})(jQuery);
