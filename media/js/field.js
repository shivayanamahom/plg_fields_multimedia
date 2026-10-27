/**
 * Загрузка с компьютера для поля «Мультимедиа». Список строк, выбор из медиа, порядок и удаление
 * делает стандартный subform Joomla; здесь только отправка файла на сервер и добавление строки.
 *
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
((window, document, Joomla) => {
  'use strict';

  const text = (key) => (Joomla.Text && Joomla.Text._(key)) || key;
  const format = (template, value) => template.replace('%s', value);
  const extensionOf = (name) => (name.includes('.') ? name.split('.').pop().toLowerCase() : '');

  class Uploader {
    constructor(root) {
      this.root = root;
      this.config = JSON.parse(root.dataset.config || '{}');
      this.status = root.querySelector('[data-mm-status]');
      this.input = root.querySelector('[data-mm-input]');
      this.subform = root.querySelector('joomla-field-subform');

      this.dragDepth = 0;
    }

    announce(message, isError = false) {
      this.status.textContent = message;
      this.status.classList.toggle('mm-status--error', isError);
    }

    /* Файлы отправляются по очереди: сервер проверяет каждый и возвращает итоговый путь. */
    async upload(files) {
      for (const file of files) {
        if (!this.config.extensions.includes(extensionOf(file.name))) {
          this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_BAD_TYPE'), file.name), true);
          continue;
        }

        if (file.size > this.config.maxBytes) {
          this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_TOO_BIG'), file.name), true);
          continue;
        }

        this.input.disabled = true;
        this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_UPLOADING'), file.name));

        try {
          const path = await this.send(file);

          if (!(await this.addRow(path))) {
            this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_LIMIT'), this.config.maxFiles), true);
            break;
          }

          this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_UPLOADED'), file.name));
        } catch (error) {
          this.announce(`${file.name}: ${error.message || text('PLG_FIELDS_MULTIMEDIA_JS_ERROR')}`, true);
        } finally {
          this.input.disabled = false;
        }
      }
    }

    async send(file) {
      const body = new FormData();
      body.append('file', file, file.name);
      body.append('field_id', String(this.config.fieldId));
      body.append(this.config.token, '1');

      const response = await fetch(this.config.uploadUrl, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });

      let json = null;

      try {
        json = await response.json();
      } catch (e) {
        throw new Error(text('PLG_FIELDS_MULTIMEDIA_JS_ERROR'));
      }

      const result = Array.isArray(json.data) ? json.data[0] : json.data;

      if (!json.success || !result || !result.success || !result.file) {
        throw new Error((result && result.message) || json.message || text('PLG_FIELDS_MULTIMEDIA_JS_ERROR'));
      }

      return result.file;
    }

    /* Новая строка стандартного subform с выбранным файлом в штатном поле «Медиа». */
    async addRow(path) {
      // Элементы ядра могут быть ещё не готовы; ждём недолго, чтобы загрузка не зависала молча.
      const wait = (name) => Promise.race([customElements.whenDefined(name), new Promise((resolve) => { window.setTimeout(resolve, 3000); })]);

      await wait('joomla-field-subform');
      await wait('joomla-field-media');

      const rows = this.subform.getRows();
      const row = this.subform.addRow(rows.length ? rows[rows.length - 1] : null);

      if (!row) {
        return false;
      }

      const media = row.querySelector('joomla-field-media');

      try {
        media.setValue(path);
      } catch (e) {
        // Предпросмотр не построился — значение всё равно записываем в поле.
        const input = media.querySelector('input');
        input.value = path;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }

      return true;
    }
  }

  /*
   * Обработчики на документе: работают независимо от момента загрузки скрипта и от того,
   * переносит ли Joomla блоки формы (вкладки) по DOM.
   */
  const uploaderFor = (target) => {
    const root = target instanceof Element ? target.closest('[data-mm-field]') : null;

    if (!root) {
      return null;
    }

    root.mmUploader = root.mmUploader || new Uploader(root);

    return root.mmUploader;
  };
  const hasFiles = (event) => [...(event.dataTransfer?.types || [])].includes('Files');

  // Кнопка — это label вокруг скрытого input[type=file]: выбор файла открывает сам браузер.
  document.addEventListener('change', (event) => {
    const input = event.target;

    if (input instanceof HTMLInputElement && input.matches('[data-mm-input]')) {
      const uploader = uploaderFor(input);
      const files = [...input.files];

      input.value = '';

      if (uploader && files.length) {
        uploader.upload(files);
      }
    }
  });
  document.addEventListener('dragenter', (event) => {
    const uploader = hasFiles(event) ? uploaderFor(event.target) : null;

    if (uploader) {
      uploader.dragDepth += 1;
      uploader.root.classList.add('mm-field--dragover');
    }
  });
  document.addEventListener('dragleave', (event) => {
    const uploader = hasFiles(event) ? uploaderFor(event.target) : null;

    if (uploader) {
      uploader.dragDepth = Math.max(0, uploader.dragDepth - 1);
      uploader.root.classList.toggle('mm-field--dragover', uploader.dragDepth > 0);
    }
  });
  document.addEventListener('dragover', (event) => {
    if (hasFiles(event) && uploaderFor(event.target)) {
      event.preventDefault();
    }
  });
  document.addEventListener('drop', (event) => {
    const uploader = hasFiles(event) ? uploaderFor(event.target) : null;

    if (uploader) {
      event.preventDefault();
      uploader.dragDepth = 0;
      uploader.root.classList.remove('mm-field--dragover');
      uploader.upload([...event.dataTransfer.files]);
    }
  });
})(window, document, window.Joomla);
