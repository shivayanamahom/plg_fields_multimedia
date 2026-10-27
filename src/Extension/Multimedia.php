<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Plugin\Fields\Multimedia\Extension;

use Joomla\CMS\Event\Model\AfterDeleteEvent;
use Joomla\CMS\Event\Model\BeforeDeleteEvent;
use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Session\Session;
use Joomla\Component\Fields\Administrator\Plugin\FieldsPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Fields\Multimedia\Helper\MediaHelper;
use Joomla\Plugin\Fields\Multimedia\Helper\UploadException;
use Joomla\Plugin\Fields\Multimedia\Helper\UploadHandler;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Поле «Мультимедиа»: галерея изображений или список файлов.
 *
 * Режим выбирается при создании поля и после сохранения не меняется. Настройки загрузки
 * сервер берёт из параметров поля по его id — от браузера принимается только файл.
 *
 * @since  2.0.0
 */
final class Multimedia extends FieldsPlugin implements SubscriberInterface
{
    /**
     * Файлы, собранные перед удалением записи, для удаления после успешного удаления.
     *
     * @var array<string, array<int, array{field: int, item: int, file: string, directory: string, mode: string, extensions: string[]}>>
     */
    private array $pendingDeletes = [];

    /**
     * @return  array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return array_merge(parent::getSubscribedEvents(), [
            'onContentBeforeSave'   => 'onFieldBeforeSave',
            'onContentBeforeDelete' => 'onItemBeforeDelete',
            'onContentAfterDelete'  => 'onItemAfterDelete',
            'onAjaxMultimedia'      => 'onAjaxMultimedia',
        ]);
    }

    /**
     * Дополняет узел поля формы: id поля, фильтр значения, проверенные настройки.
     *
     * @param   object       $field   Поле из com_fields.
     * @param   \DOMElement  $parent  Родительский узел формы.
     * @param   Form         $form    Форма.
     *
     * @return  \DOMElement|null
     */
    public function onCustomFieldsPrepareDom($field, \DOMElement $parent, Form $form)
    {
        $node = parent::onCustomFieldsPrepareDom($field, $parent, $form);

        if (!$node instanceof \DOMElement) {
            return $node;
        }

        FormHelper::addFieldPrefix('Joomla\\Plugin\\Fields\\Multimedia\\Field');
        FormHelper::addFilterPrefix('Joomla\\Plugin\\Fields\\Multimedia\\Filter');

        $settings = MediaHelper::settings(array_merge($this->params->toArray(), $field->fieldparams->toArray()));

        foreach ($settings as $key => $value) {
            $node->setAttribute($key, \is_array($value) ? implode(',', $value) : (string) $value);
        }

        $node->setAttribute('field_id', (string) (int) $field->id);
        $node->setAttribute('filter', 'multimedia');

        return $node;
    }

    /**
     * Форма настройки поля: после первого сохранения режим закреплён, лишние для него параметры скрыты.
     *
     * @param   Form          $form  Форма.
     * @param   array|object  $data  Данные формы.
     *
     * @return  void
     */
    public function onContentPrepareForm(Form $form, $data)
    {
        parent::onContentPrepareForm($form, $data);

        if ($this->getFormPath($form, $data) === null) {
            return;
        }

        $id     = (int) (((array) $data)['id'] ?? 0);
        $id     = $id ?: (int) $form->getValue('id');
        $stored = $id ? $this->storedMode($id) : null;

        if ($stored === null) {
            return;
        }

        $form->setFieldAttribute('mode', 'disabled', 'true', 'fieldparams');
        $form->setFieldAttribute('mode', 'default', $stored, 'fieldparams');
        $form->setFieldAttribute('mode', 'description', 'PLG_FIELDS_MULTIMEDIA_MODE_LOCKED_DESC', 'fieldparams');

        $other = $stored === MediaHelper::MODE_FILES ? MediaHelper::galleryOnlyParams() : MediaHelper::filesOnlyParams();

        foreach ($other as $name) {
            $form->removeField($name, 'fieldparams');
        }

        // Условие «mode:…» больше не нужно: режим один и не меняется.
        foreach (array_keys(MediaHelper::defaults()) as $name) {
            $showon = (string) $form->getFieldAttribute($name, 'showon', '', 'fieldparams');

            if ($showon !== '' && $name !== 'mode') {
                $form->setFieldAttribute($name, 'showon', preg_replace('/^mode:\w+(\[AND\])?/', '', $showon), 'fieldparams');
            }
        }

        foreach (['spacer_storage', 'spacer_images', 'spacer_display', 'spacer_files'] as $name) {
            $showon = (string) $form->getFieldAttribute($name, 'showon', '', 'fieldparams');

            if ($showon !== '') {
                $form->setFieldAttribute($name, 'showon', '', 'fieldparams');
            }
        }
    }

    /**
     * Сохранение поля: режим берётся из базы (его нельзя подменить запросом), параметры проверяются.
     */
    public function onFieldBeforeSave(BeforeSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_fields.field') {
            return;
        }

        $table = $event->getItem();

        if (($table->type ?? '') !== 'multimedia') {
            return;
        }

        $params = new Registry($table->fieldparams ?? '');
        $stored = !empty($table->id) ? $this->storedMode((int) $table->id) : null;

        if ($stored !== null) {
            $params->set('mode', $stored);
        }

        $settings = MediaHelper::settings($params);
        $drop     = $settings['mode'] === MediaHelper::MODE_FILES ? MediaHelper::galleryOnlyParams() : MediaHelper::filesOnlyParams();
        $clean    = [];

        foreach (array_keys(MediaHelper::defaults()) as $key) {
            if (!\in_array($key, $drop, true)) {
                $clean[$key] = $settings[$key];
            }
        }

        $table->fieldparams = json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Загрузка файла с компьютера (com_ajax). Настройки берутся по id поля на сервере.
     */
    public function onAjaxMultimedia(AjaxEvent $event): void
    {
        $app = $this->getApplication() ?: Factory::getApplication();

        try {
            if (strtoupper($app->getInput()->getMethod()) !== 'POST' || !Session::checkToken('post')) {
                throw new UploadException(Text::_('JINVALID_TOKEN_NOTICE'));
            }

            $user = $app->getIdentity();

            if (!$user || $user->guest) {
                throw new UploadException(Text::_('JERROR_ALERTNOAUTHOR'));
            }

            $field = $this->loadField($app->getInput()->post->getInt('field_id'));

            if ($field === null || !\in_array((int) $field->access, array_map('intval', $user->getAuthorisedViewLevels()), true)) {
                throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_FIELD'));
            }

            $component = explode('.', (string) $field->context)[0];
            $canValue  = $user->authorise('core.edit.value', $component . '.field.' . (int) $field->id);
            $canWrite  = $user->authorise('core.create', $component) || $user->authorise('core.edit', $component) || $user->authorise('core.edit.own', $component);

            if (!$canValue || !$canWrite) {
                throw new UploadException(Text::_('JERROR_ALERTNOAUTHOR'));
            }

            $settings = MediaHelper::settings($field->fieldparams);
            $upload   = $app->getInput()->files->get('file', [], 'raw');

            $result = (new UploadHandler())->handle($settings, \is_array($upload) ? $upload : [], (string) $field->name);

            $event->addResult(['success' => true] + $result);
        } catch (UploadException $e) {
            $event->addResult(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::add($e->getMessage(), Log::ERROR, 'plg_fields_multimedia');
            $event->addResult(['success' => false, 'message' => Text::_('PLG_FIELDS_MULTIMEDIA_ERR_SERVER')]);
        }
    }

    /**
     * Перед удалением записи запоминаем файлы её полей: после удаления значения в базе уже не найти.
     */
    public function onItemBeforeDelete(BeforeDeleteEvent $event): void
    {
        $context = $event->getContext();
        $item    = $event->getItem();
        $id      = (int) ($item->id ?? 0);

        if ($id < 1 || !str_contains($context, '.')) {
            return;
        }

        $db    = $this->database();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('f.id'), $db->quoteName('f.fieldparams'), $db->quoteName('v.value')])
            ->from($db->quoteName('#__fields', 'f'))
            ->join('INNER', $db->quoteName('#__fields_values', 'v'), $db->quoteName('v.field_id') . ' = ' . $db->quoteName('f.id'))
            ->where($db->quoteName('f.type') . ' = ' . $db->quote('multimedia'))
            ->where($db->quoteName('f.context') . ' = :context')
            ->where($db->quoteName('v.item_id') . ' = :item')
            ->bind(':context', $context)
            ->bind(':item', $id);

        $pending = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $settings = MediaHelper::settings($row->fieldparams);

            if (!$settings['delete_files']) {
                continue;
            }

            foreach (MediaHelper::parseValue($row->value) as $entry) {
                $pending[] = [
                    'field'      => (int) $row->id,
                    'item'       => $id,
                    'file'       => $entry['file'],
                    'directory'  => $settings['directory'],
                    'mode'       => $settings['mode'],
                    'extensions' => $settings['extensions'],
                ];
            }
        }

        if ($pending) {
            $this->pendingDeletes[$context . '|' . $id] = $pending;
        }
    }

    /**
     * После успешного удаления записи убираем её файлы — только из папки самого поля и только если на файл никто не ссылается.
     */
    public function onItemAfterDelete(AfterDeleteEvent $event): void
    {
        $key = $event->getContext() . '|' . (int) ($event->getItem()->id ?? 0);

        if (empty($this->pendingDeletes[$key])) {
            return;
        }

        foreach ($this->pendingDeletes[$key] as $entry) {
            $this->deleteFile($entry);
        }

        unset($this->pendingDeletes[$key]);
    }

    /**
     * @param   array{field: int, item: int, file: string, directory: string, mode: string, extensions: string[]}  $entry
     */
    private function deleteFile(array $entry): void
    {
        $absolute = MediaHelper::absolutePath($entry['file']);
        $root     = realpath(JPATH_ROOT . '/images/' . $entry['directory']);

        if ($absolute === null || $root === false || is_link(JPATH_ROOT . '/' . $entry['file'])) {
            return;
        }

        if (!str_starts_with($absolute, $root . DIRECTORY_SEPARATOR) || !\in_array(MediaHelper::extension($entry['file']), $entry['extensions'], true)) {
            return;
        }

        if ($this->isReferenced($entry['file'], $entry['field'], $entry['item'])) {
            return;
        }

        MediaHelper::purgeThumbnails($entry['file']);
        @unlink($absolute);
    }

    /**
     * Есть ли в значениях других записей ссылка на файл (в обычной и экранированной записи слэшей).
     */
    private function isReferenced(string $file, int $fieldId, int $itemId): bool
    {
        $db = $this->database();

        foreach ([$file, str_replace('/', '\\/', $file)] as $needle) {
            $like  = '%"' . $db->escape($needle, true) . '"%';
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__fields_values'))
                ->where($db->quoteName('value') . ' LIKE :like')
                ->where('NOT (' . $db->quoteName('field_id') . ' = :field AND ' . $db->quoteName('item_id') . ' = :item)')
                ->bind(':like', $like)
                ->bind(':field', $fieldId, ParameterType::INTEGER)
                ->bind(':item', $itemId, ParameterType::INTEGER);

            if ((int) $db->setQuery($query)->loadResult() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Режим уже сохранённого поля или null, если поле новое или режим ещё не выбирался.
     */
    private function storedMode(int $fieldId): ?string
    {
        $db    = $this->database();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('type'), $db->quoteName('fieldparams')])
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $fieldId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadObject();

        if (!$row || $row->type !== 'multimedia') {
            return null;
        }

        $mode = (new Registry($row->fieldparams))->get('mode');

        return \in_array($mode, [MediaHelper::MODE_GALLERY, MediaHelper::MODE_FILES], true) ? $mode : null;
    }

    /**
     * Опубликованное поле этого типа вместе с параметрами или null.
     */
    private function loadField(int $fieldId): ?object
    {
        if ($fieldId < 1) {
            return null;
        }

        $db    = $this->database();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'name', 'context', 'access', 'fieldparams']))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('id') . ' = :id')
            ->where($db->quoteName('type') . ' = ' . $db->quote('multimedia'))
            ->where($db->quoteName('state') . ' = 1')
            ->bind(':id', $fieldId, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    private function database(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
