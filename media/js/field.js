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
      this.drop = root.querySelector('[data-mm-drop]');
      this.button = root.querySelector('[data-mm-upload]');
      this.subform = root.querySelector('joomla-field-subform');

      this.dragDepth = 0;
    }

    announce(message, isError = false) {
      this.status.textContent = message;
      this.status.classList.toggle('mm-status--error', isError);
    }

    pick() {
      const input = document.createElement('input');
      input.type = 'file';
      input.multiple = this.config.maxFiles > 1;
      input.accept = this.config.extensions.map((ext) => `.${ext}`).join(',');
      input.addEventListener('change', () => this.upload([...input.files]));
      input.click();
    }

    /* Файлы отправляются по очереди: сервер проверяет каждый и возвращает итоговый путь. */
    async upload(files) {
      await customElements.whenDefined('joomla-field-subform');
      await customElements.whenDefined('joomla-field-media');

      for (const file of files) {
        if (!this.config.extensions.includes(extensionOf(file.name))) {
          this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_BAD_TYPE'), file.name), true);
          continue;
        }

        if (file.size > this.config.maxBytes) {
          this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_TOO_BIG'), file.name), true);
          continue;
        }

        this.button.disabled = true;
        this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_UPLOADING'), file.name));

        try {
          const path = await this.send(file);

          if (!this.addRow(path)) {
            this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_LIMIT'), this.config.maxFiles), true);
            break;
          }

          this.announce(format(text('PLG_FIELDS_MULTIMEDIA_JS_UPLOADED'), file.name));
        } catch (error) {
          this.announce(`${file.name}: ${error.message || text('PLG_FIELDS_MULTIMEDIA_JS_ERROR')}`, true);
        } finally {
          this.button.disabled = false;
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
    addRow(path) {
      const rows = this.subform.getRows();
      const row = this.subform.addRow(rows.length ? rows[rows.length - 1] : null);

      if (!row) {
        return false;
      }

      const media = row.querySelector('joomla-field-media');

      try {
        media.setValue(path);
      } catch (e) {
        // Предпросмотр не построился (нет настроек com_media на странице) — значение всё равно записано.
        media.querySelector('input').value = path;
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

  document.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('[data-mm-upload]')) {
      const uploader = uploaderFor(event.target);

      if (uploader) {
        event.preventDefault();
        uploader.pick();
      }
    }
  });
  document.addEventListener('dragenter', (event) => {
    const uploader = hasFiles(event) ? uploaderFor(event.target) : null;

    if (uploader) {
      uploader.dragDepth += 1;
      uploader.drop.hidden = false;
    }
  });
  document.addEventListener('dragleave', (event) => {
    const uploader = hasFiles(event) ? uploaderFor(event.target) : null;

    if (uploader) {
      uploader.dragDepth = Math.max(0, uploader.dragDepth - 1);
      uploader.drop.hidden = uploader.dragDepth === 0;
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
      uploader.drop.hidden = true;
      uploader.upload([...event.dataTransfer.files]);
    }
  });
})(window, document, window.Joomla);
