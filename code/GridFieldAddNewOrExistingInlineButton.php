<?php

/**
 * Milkyway Multimedia
 * GridFieldAddNewOrExistingInlineButton.php
 *
 * @package milkyway-multimedia/ss-mwm-autocomplete
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

if (class_exists('GridFieldAddNewInlineButton')) {
	class GridFieldAddNewOrExistingInlineButton extends GridFieldAddNewInlineButton implements
		GridField_ColumnProvider {

		public $refField = 'Title';

		public $valField = 'ID';

		public $valFieldAfterSave = 'Title';

		public function __construct($fragment = 'buttons-before-left', $refField = 'Title', $valField = 'ID', $valFieldAfterSave = 'Title') {
			parent::__construct($fragment);

			$this->refField          = $refField;
			$this->valField          = $valField;
			$this->valFieldAfterSave = $valFieldAfterSave;
		}

		public function handleSave(GridField $grid, DataObjectInterface $record) {
			$list  = $grid->getList();
			$value = $grid->Value();

			$editable = $grid->getConfig()->getComponentByType('GridFieldEditableColumns');

			if ($editable) {
				$this->addFallbackValueToDisplayFields($grid, $editable);
			}

			$form = $editable->getForm($grid, $record);

			if (!isset($value['GridFieldAddNewInlineButton']) || !is_array($value['GridFieldAddNewInlineButton'])) {
				return;
			}

			$class = $grid->getModelClass();

			if (!singleton($class)->canCreate()) {
				return;
			}

			foreach ($value['GridFieldAddNewInlineButton'] as $fields) {
				$item = null;

				if (isset($fields['_AddOrExistingID']) && !$list->byID($fields['_AddOrExistingID'])) {
					if ($item = DataList::create($class)->byID($fields['_AddOrExistingID'])) {
						unset($fields['_AddOrExistingID']);
					} elseif (!isset($fields[$this->valFieldAfterSave])) {
						$fields[$this->valFieldAfterSave] = $fields['_AddOrExistingID'];
					}
				}

				if (!$item) {
					$item = $class::create();
				}

				$extra = [];

				$form->loadDataFrom($fields, Form::MERGE_CLEAR_MISSING);
				$form->saveInto($item);

				if ($list instanceof ManyManyList) {
					$extra = array_intersect_key($form->getData(), (array) $list->getExtraFields());
				}

				$item->write();
				$list->add($item, $extra);
			}
		}

		/**
		 * Modify the list of columns displayed in the table.
		 *
		 * @see {@link GridFieldDataColumns->getDisplayFields()}
		 * @see {@link GridFieldDataColumns}.
		 *
		 * @param GridField $gridField
		 * @param           array - List reference of all column names.
		 */
		public function augmentColumns($gridField, &$columns) {
			if (!in_array($this->valFieldAfterSave, $columns)) {
				$columns[] = $this->valFieldAfterSave;
			}
		}

		/**
		 * Attributes for the element containing the content returned by {@link getColumnContent()}.
		 *
		 * @param  GridField  $gridField
		 * @param  DataObject $record displayed in this row
		 * @param  string     $columnName
		 *
		 * @return array
		 */
		public function getColumnAttributes($gridField, $record, $columnName) {
			return [
				'class' => 'col-addOrExistingId'
			];
		}

		/**
		 * @param GridField  $gridField
		 * @param DataObject $record
		 * @param string     $columnName
		 *
		 * @return string
		 * @throws Exception
		 */
		public function getColumnContent($gridField, $record, $columnName) {
			if (!$editable = $gridField->getConfig()->getComponentByType('GridFieldEditableColumns')) {
				throw new Exception('Inline adding requires the editable columns component');
			}

			$field = $this->getColumnField($gridField, $record, $columnName)->setForm($editable->getForm($gridField, $record));

			$field->setValue($record->{$field->Name})->setName(sprintf(
				'%s[%s][%s][%s]', $gridField->getName(), get_class($editable), $record->ID, $field->Name
			));

			return $field->Field();
		}

		public function getColumnField($gridField, $record, $columnName) {
			if ($record->ID) {
				$field = $record->scaffoldFormFields(['restrictFields' => [$this->valFieldAfterSave]])->pop();
			} else {
				$field = Select2Field::create('_AddOrExistingID', $columnName, '', DataList::create($gridField->List->dataClass())->subtract($gridField->List), '', $this->refField, $this->valField)->setEmptyString(_t('GridFieldAddNewOrExistingInlineButton.AddOrSelectExisting', 'Add or select existing'))->setMinimumSearchLength(0);
			}

			return $field;
		}

		/**
		 * Additional metadata about the column which can be used by other components,
		 * e.g. to set a title for a search column header.
		 *
		 * @param GridField $gridField
		 * @param string    $columnName
		 *
		 * @return array - Map of arbitrary metadata identifiers to their values.
		 */
		public function getColumnMetadata($gridField, $columnName) {
			if ($columnName == $this->valFieldAfterSave) {
				return ['title' => $this->valFieldAfterSave];
			}
		}

		/**
		 * Names of all columns which are affected by this component.
		 *
		 * @param GridField $gridField
		 *
		 * @return array
		 */
		public function getColumnsHandled($gridField) {
			return [$this->valFieldAfterSave];
		}

		public function getHTMLFragments($grid) {
			$return = parent::getHTMLFragments($grid);

			if (isset($return['after'])) {
				$return['after'] = $this->getRowTemplate($grid, $return['after']);
			}

			return $return;
		}

		private function getRowTemplate(GridField $grid, $after) {
			$attrs = '';

			if ($grid->getList()) {
				$record = Object::create($grid->getModelClass());
			} else {
				$record = null;
			}

			foreach ($grid->getColumnAttributes($record, $this->valFieldAfterSave) as $attr => $val) {
				$attrs .= sprintf(' %s="%s"', $attr, Convert::raw2att($val));
			}

			$field = $this->getColumnField($grid, $record, $this->valFieldAfterSave);
			$field->setName(sprintf(
				'%s[%s][{%%=o.num%%}][%s]', $grid->getName(), 'GridFieldAddNewInlineButton', $field->getName()
			));

			return str_replace('<td class="col-addOrExistingId">', '<td class="col-addOrExistingId">' . $field->Field(), $after);
		}

		private function addFallbackValueToDisplayFields(GridField $grid, GridFieldDataColumns $editable) {
			$fields = $editable->getDisplayFields($grid);

			if (!isset($fields[$this->valFieldAfterSave])) {
				$editable->setDisplayFields([$this->valFieldAfterSave => $this->valFieldAfterSave] + $fields);
			}
		}
	}
}