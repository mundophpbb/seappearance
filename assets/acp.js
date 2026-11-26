(function () {
	'use strict';

	const form = document.getElementById('sea-form');
	const preview = document.getElementById('sea-preview');
	const hexColor = /^#[\da-f]{6}$/i;
	const previewRules = {
		page: ['html, body', 'background-color'],
		frame: ['#wrap', 'border-color'],
		shadow: ['#wrap', 'box-shadow'],
		surface: ['#wrap', 'background-color'],
		header: ['.sea-example-header', 'background-color'],
		header_text: ['.sea-example-header, .sea-example-header h1', 'color'],
		category: ['.sea-example-category', 'background-color'],
		category_text: ['.sea-example-category .header dt', 'color'],
		nav: ['.sea-example-nav', 'background-color'],
		row: ['.sea-example-row', 'background-color'],
		text: ['.sea-example-post, #wrap', 'color'],
		link: ['.sea-example-link', 'color'],
		body_font: ['#phpbb, .sea-example-post', 'font-family'],
		heading_font: ['.sea-example-header h1, .sea-example-link', 'font-family'],
		base_size: ['#phpbb', 'font-size'],
		post_size: ['.sea-example-post', 'font-size'],
	};
	const shadowValue = function (color) {
		return '0 0 5px 2px ' + color + ', inset 0 3px 18px rgba(100, 100, 100, .25)';
	};
	const colorInputs = form.querySelectorAll('[data-sea-color]');
	const previewUpdates = [];
	let previewDocument;

	function setPreview(key, value) {
		if (!previewDocument) {
			return;
		}

		const [selector, property] = previewRules[key];
		previewDocument.querySelectorAll(selector).forEach(function (element) {
			if (value) {
				element.style.setProperty(property, key === 'shadow' ? shadowValue(value) : value);
			} else {
				element.style.removeProperty(property);
			}
			if (key === 'header' || key === 'category') {
				if (value) {
					element.style.setProperty('background-image', 'none');
				} else {
					element.style.removeProperty('background-image');
				}
			}
		});
	}

	function loadPreview() {
		const doc = preview.contentDocument;
		if (!doc || doc === previewDocument || doc.readyState !== 'complete' || !doc.getElementById('phpbb')) {
			return;
		}

		previewDocument = doc;
		colorInputs.forEach(function (input) {
			const key = input.dataset.seaColor;
			const [selector, property] = previewRules[key];
			let sample = doc.querySelector(key === 'text' ? '.sea-example-post' : selector);
			let color = preview.contentWindow.getComputedStyle(sample).getPropertyValue(property);
			if (key === 'row' && color === 'rgba(0, 0, 0, 0)') {
				sample = sample.closest('.forums');
				color = preview.contentWindow.getComputedStyle(sample).backgroundColor;
			}
			// The computed box-shadow starts with the outer shadow color.
			const rgb = key === 'shadow'
				? /^rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(color)
				: /^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/.exec(color);
			if (rgb) {
				input.placeholder = '#' + rgb.slice(1).map(channel => Number(channel).toString(16).padStart(2, '0')).join('').toUpperCase();
			}
		});
		previewUpdates.forEach(update => update());
	}

	colorInputs.forEach(function (input) {
		const key = input.dataset.seaColor;
		const picker = form.querySelector('[data-sea-picker="' + key + '"]');
		const syncColor = function () {
			const color = hexColor.test(input.value) ? input.value : '';
			picker.value = color || input.placeholder;
			setPreview(key, color);
		};
		input.addEventListener('input', syncColor);
		picker.addEventListener('input', function () {
			input.value = picker.value.toUpperCase();
			syncColor();
		});
		picker.hidden = false;
		previewUpdates.push(syncColor);
	});

	form.querySelectorAll('[data-sea-font]').forEach(function (input) {
		const syncFont = function () {
			setPreview(input.dataset.seaFont, input.options[input.selectedIndex].dataset.stack);
		};
		input.addEventListener('change', syncFont);
		previewUpdates.push(syncFont);
	});

	form.querySelectorAll('[data-sea-size]').forEach(function (input) {
		const syncSize = function () {
			setPreview(input.dataset.seaSize, input.value && input.validity.valid ? input.value + 'px' : '');
		};
		input.addEventListener('input', syncSize);
		previewUpdates.push(syncSize);
	});

	preview.addEventListener('load', loadPreview);
	loadPreview();

	const themes = document.getElementById('sea-themes');
	if (themes) {
		const fields = JSON.parse(themes.dataset.fields);
		const select = document.getElementById('sea_theme');
		const nameInput = document.getElementById('sea_preset_name');
		const status = document.getElementById('sea-theme-status');
		const importFile = document.getElementById('sea-theme-file');
		const nameRule = /^[\p{L}\p{N}][\p{L}\p{M}\p{N} ._-]*$/u;

		const announce = function (text, isError) {
			status.textContent = text;
			status.classList.toggle('sea-theme-error', Boolean(isError));
		};

		const field = function (key) {
			return form.elements.namedItem('sea_' + key);
		};

		// Fill one form field and let its own listeners update the picker and the preview.
		// Returns false when the value cannot be used here (for example, a font missing on this board).
		const setField = function (key, value) {
			const input = field(key);
			if (!input) {
				return true;
			}
			value = value === undefined || value === null ? '' : String(value);
			let usable = true;
			if (input.dataset.seaColor !== undefined) {
				usable = value === '' || hexColor.test(value);
				input.value = usable ? value.toUpperCase() : '';
				input.dispatchEvent(new Event('input'));
			} else if (input.dataset.seaFont !== undefined) {
				usable = value === '' || Array.from(input.options).some(option => option.value === value);
				input.value = usable ? value : '';
				input.dispatchEvent(new Event('change'));
			} else {
				const number = Number(value);
				usable = value === '' || (/^\d+$/.test(value) && number >= Number(input.min) && number <= Number(input.max));
				input.value = usable ? value : '';
				input.dispatchEvent(new Event('input'));
			}
			input.removeAttribute('aria-invalid');
			return usable;
		};

		const applyValues = function (values) {
			const rejected = [];
			fields.forEach(function (key) {
				if (!setField(key, values[key])) {
					rejected.push(key);
				}
			});
			return rejected;
		};

		const currentValues = function () {
			const values = {};
			fields.forEach(function (key) {
				const input = field(key);
				const value = input ? input.value.trim() : '';
				const valid = input.dataset.seaColor !== undefined ? hexColor.test(value) : (input.validity ? input.validity.valid : true);
				values[key] = valid ? (input.dataset.seaColor !== undefined ? value.toUpperCase() : value) : '';
			});
			return values;
		};

		themes.querySelectorAll('[data-sea-js]').forEach(element => element.hidden = false);

		document.getElementById('sea-theme-apply').addEventListener('click', function () {
			const option = select.options[select.selectedIndex];
			if (!option) {
				return;
			}
			applyValues(JSON.parse(option.dataset.values));
			if (option.dataset.name) {
				nameInput.value = option.dataset.name;
			}
			let message = themes.dataset.applied.replace('%s', option.textContent.trim());
			if (option.dataset.missingFont === '1') {
				message += ' ' + themes.dataset.fontMissing;
			}
			announce(message);
		});

		// Enter in the name field saves the theme instead of the forum settings.
		nameInput.addEventListener('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				form.querySelector('[name="save_preset"]').click();
			}
		});

		document.getElementById('sea-theme-export').addEventListener('click', function () {
			const name = nameInput.value.trim();
			const data = {
				format: themes.dataset.format,
				version: 1,
				name: name,
				values: currentValues(),
			};
			const slug = name.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
			const link = document.createElement('a');
			link.href = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2) + '\n'], {type: 'application/json'}));
			link.download = 'seappearance-' + (slug || 'theme') + '.json';
			document.body.appendChild(link);
			link.click();
			link.remove();
			setTimeout(() => URL.revokeObjectURL(link.href), 1000);
			announce(themes.dataset.exported);
		});

		document.getElementById('sea-theme-import').addEventListener('click', function () {
			importFile.value = '';
			importFile.click();
		});

		importFile.addEventListener('change', async function () {
			const file = importFile.files[0];
			if (!file) {
				return;
			}
			let data;
			try {
				data = file.size <= 65536 ? JSON.parse(await file.text()) : null;
			} catch (failure) {
				data = null;
			}
			if (!data || data.format !== themes.dataset.format || typeof data.values !== 'object' || data.values === null) {
				announce(themes.dataset.importError, true);
				return;
			}
			const rejected = applyValues(data.values);
			const name = typeof data.name === 'string' ? data.name.trim().slice(0, Number(nameInput.maxLength) || 60) : '';
			if (name && nameRule.test(name)) {
				nameInput.value = name;
			}
			let message = themes.dataset.imported.replace('%s', name || file.name);
			if (rejected.some(key => key === 'body_font' || key === 'heading_font')) {
				message += ' ' + themes.dataset.fontMissing;
			}
			announce(message);
		});
	}

	const upload = document.getElementById('sea-font-upload');
	const fontFile = document.getElementById('sea_font_file');
	let checkedFile;
	let checking = false;

	fontFile.addEventListener('change', function () {
		checkedFile = null;
		fontFile.setCustomValidity('');
	});

	upload.addEventListener('submit', async function (event) {
		const file = fontFile.files[0];
		if (!file || file === checkedFile || !window.FontFace || !file.arrayBuffer || !upload.requestSubmit) {
			return;
		}

		event.preventDefault();
		if (checking) {
			return;
		}
		const submitter = event.submitter;
		checking = true;
		let error = '';
		if (file.size > Number(upload.dataset.maxBytes)) {
			error = upload.dataset.sizeError;
		} else {
			try {
				await new FontFace('SE Appearance Upload Check', await file.arrayBuffer()).load();
			} catch (failure) {
				error = upload.dataset.formatError;
			}
		}
		checking = false;
		if (fontFile.files[0] !== file) {
			return;
		}
		if (error) {
			fontFile.setCustomValidity(error);
			fontFile.reportValidity();
			return;
		}
		checkedFile = file;
		upload.requestSubmit(submitter || upload.querySelector('[name="upload_font"]'));
	});
}());
