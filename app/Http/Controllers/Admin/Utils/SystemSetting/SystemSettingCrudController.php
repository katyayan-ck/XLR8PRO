<?php

namespace App\Http\Controllers\Admin\Utils\SystemSetting;

use App\Models\SystemSetting;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Http\Requests\CrudRequest;

class SystemSettingCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    public function setup()
    {
        $this->crud->setModel(\App\Models\Utilities\Settings\SystemSetting::class);
        $this->crud->setRoute(config('backpack.base.route_prefix').'/utils/system-setting');
        $this->crud->setEntityNameStrings('system setting', 'system settings');

        $this->crud->allowAccess(['list', 'create', 'update', 'show']);
    }

    /**
     * Overrides DeleteOperation's default destroy() to add a permission gate.
     * See known-bugs-report.md BUG-063: the trait default was unguarded — setup()'s
     * allowAccess(['list', 'create', 'update', 'show']) deliberately omits 'delete', but
     * DeleteOperation::setupDeleteDefaults() calls $this->crud->allowAccess('delete')
     * unconditionally in its own bootstrap, re-granting it regardless.
     */
    public function destroy($id)
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to delete system settings.');
        }

        $this->crud->hasAccessOrFail('delete');

        $id = $this->crud->getCurrentEntryId() ?? $id;

        return $this->crud->delete($id);
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('UTL_SETTINGS_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view system settings.');
        }

        $this->crud->addColumn([
            'name' => 'topic',
            'label' => 'Topic',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'group',
            'label' => 'Group',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'label',
            'label' => 'Label',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'key',
            'label' => 'Key',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'value',
            'label' => 'Value',
            'type' => 'text',
            'limit' => 100,
        ]);

        $this->crud->addColumn([
            'name' => 'type',
            'label' => 'Type',
            'type' => 'badge',
            'badge_color' => [
                'string' => 'info',
                'integer' => 'warning',
                'boolean' => 'success',
                'json' => 'primary',
                'file' => 'secondary',
                'image' => 'secondary',
            ],
        ]);

        $this->crud->addColumn([
            'name' => 'iseditable',
            'label' => 'Editable',
            'type' => 'boolean',
        ]);

        $this->crud->addColumn([
            'name' => 'is_visible',
            'label' => 'Visible',
            'type' => 'boolean',
        ]);

        $this->crud->addFilter([
            'name' => 'topic',
            'type' => 'select2',
            'label' => 'Topic',
        ], function () {
            return SystemSetting::select('topic')->distinct()->orderBy('topic')->get()->pluck('topic', 'topic')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'topic', $value);
        });

        $this->crud->setDefaultPageLength(50);
    }

    protected function setupCreateOperation()
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create system settings.');
        }

        $this->crud->setValidation([
            'key' => 'required|unique:systemsettings|regex:/^[a-z]+\.[a-z_]+$/',
            'label' => 'required|string|max:255',
            'value' => 'required',
            'topic' => 'required|string',
            'type' => 'required|in:string,integer,float,boolean,json,file,image',
            'input_type' => 'required|string',
            'iseditable' => 'boolean',
            'is_visible' => 'boolean',
        ]);

        $this->addSettingFields();
    }

    protected function setupUpdateOperation()
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit system settings.');
        }

        $this->crud->setValidation([
            'key' => 'required|unique:systemsettings,key,'.$this->crud->getCurrentEntryId(),
            'label' => 'required|string|max:255',
            'value' => 'required',
            'topic' => 'required|string',
            'type' => 'required|in:string,integer,float,boolean,json,file,image',
            'input_type' => 'required|string',
            'iseditable' => 'boolean',
            'is_visible' => 'boolean',
        ]);

        $this->addSettingFields();
    }

    private function addSettingFields()
    {
        $this->crud->addField([
            'name' => 'topic',
            'label' => 'Topic/Category',
            'type' => 'select2',
            'entity' => 'topic',
            'attribute' => 'code',
            'model' => 'App\Models\SystemSettingTopic',
            'options' => [],
            'tab' => 'Basic Information',
        ]);

        $this->crud->addField([
            'name' => 'group',
            'label' => 'Group (Optional)',
            'type' => 'text',
            'hint' => 'Group settings together for UI organization (e.g., "Logo Settings", "Contact Details")',
            'tab' => 'Basic Information',
        ]);

        $this->crud->addField([
            'name' => 'key',
            'label' => 'Setting Key',
            'type' => 'text',
            'attributes' => [
                'placeholder' => 'site.name, dealership.phone, pricing.gst_rate',
            ],
            'hint' => 'Format: topic.setting (lowercase, dots only)',
            'tab' => 'Basic Information',
        ]);

        $this->crud->addField([
            'name' => 'label',
            'label' => 'Label/Display Name',
            'type' => 'text',
            'hint' => 'Human-readable label for admin interface',
            'tab' => 'Basic Information',
        ]);

        $this->crud->addField([
            'name' => 'value',
            'label' => 'Value',
            'type' => 'textarea',
            'tab' => 'Value',
        ]);

        $this->crud->addField([
            'name' => 'default_value',
            'label' => 'Default Value',
            'type' => 'textarea',
            'hint' => 'Value to use if current value is empty',
            'tab' => 'Value',
        ]);

        $this->crud->addField([
            'name' => 'type',
            'label' => 'Data Type',
            'type' => 'select',
            'options' => [
                'string' => 'String (Text)',
                'integer' => 'Integer (Number)',
                'float' => 'Float (Decimal)',
                'boolean' => 'Boolean (Yes/No)',
                'json' => 'JSON (Array/Object)',
                'file' => 'File Path',
                'image' => 'Image Path',
            ],
            'hint' => 'Data type for automatic casting',
            'tab' => 'Configuration',
        ]);

        $this->crud->addField([
            'name' => 'input_type',
            'label' => 'Input Type (Admin UI)',
            'type' => 'select',
            'options' => [
                'text' => 'Text Input',
                'textarea' => 'Text Area',
                'json' => 'JSON Editor',
                'file' => 'File Upload',
                'image' => 'Image Upload',
                'select' => 'Select Dropdown',
                'radio' => 'Radio Buttons',
                'toggle' => 'Toggle Switch',
                'color' => 'Color Picker',
                'number' => 'Number Input',
                'email' => 'Email Input',
                'url' => 'URL Input',
                'date' => 'Date Picker',
                'time' => 'Time Picker',
                'datetime' => 'DateTime Picker',
                'rich_text' => 'Rich Text Editor (WYSIWYG)',
            ],
            'hint' => 'How this field appears in admin UI',
            'tab' => 'Configuration',
        ]);

        $this->crud->addField([
            'name' => 'validation_rules',
            'label' => 'Validation Rules',
            'type' => 'text',
            'hint' => 'Laravel validation rules (e.g., "required|email|max:255")',
            'tab' => 'Configuration',
        ]);

        $this->crud->addField([
            'name' => 'options',
            'label' => 'Options (for select/radio)',
            'type' => 'textarea',
            'hint' => 'JSON format: {"value1": "Label 1", "value2": "Label 2"}',
            'tab' => 'Configuration',
        ]);

        $this->crud->addField([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
            'hint' => 'Internal notes about this setting',
            'tab' => 'Documentation',
        ]);

        $this->crud->addField([
            'name' => 'help_text',
            'label' => 'Help Text',
            'type' => 'textarea',
            'hint' => 'Shown to admin users in the UI',
            'tab' => 'Documentation',
        ]);

        $this->crud->addField([
            'name' => 'sort_order',
            'label' => 'Sort Order',
            'type' => 'number',
            'hint' => 'Order within group (0-999)',
            'tab' => 'Documentation',
        ]);

        $this->crud->addField([
            'name' => 'iseditable',
            'label' => 'Editable in Admin',
            'type' => 'checkbox',
            'tab' => 'Permissions',
        ]);

        $this->crud->addField([
            'name' => 'is_visible',
            'label' => 'Visible in Admin',
            'type' => 'checkbox',
            'tab' => 'Permissions',
        ]);
    }

    public function store(CrudRequest $request)
    {
        return parent::storeCrud($request);
    }

    public function update(CrudRequest $request)
    {
        return parent::updateCrud($request);
    }
}
