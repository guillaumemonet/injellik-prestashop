{if isset($messages) && $messages}
    <div class="alert alert-info">
        {$messages}
    </div>
{/if}

<form action="{$link->getAdminLink('AdminModules')}&configure={$module_name}" method="post">
    <div class="form-group">
        <label for="api_url">{$module_name|escape:'html':'UTF-8'|l s='API URL'}</label>
        <input type="text" class="form-control" name="INJELLIK_API_URL" id="api_url" value="{$fields_value.INJELLIK_API_URL|escape:'html':'UTF-8'}" required>
    </div>

    <div class="form-group">
        <label for="api_login">{$module_name|escape:'html':'UTF-8'|l s='API Login'}</label>
        <input type="text" class="form-control" name="INJELLIK_API_LOGIN" id="api_login" value="{$fields_value.INJELLIK_API_LOGIN|escape:'html':'UTF-8'}" required>
    </div>

    <div class="form-group">
        <label for="api_password">{$module_name|escape:'html':'UTF-8'|l s='API Password'}</label>
        <input type="password" class="form-control" name="INJELLIK_API_PASSWORD" id="api_password" required>
    </div>

    <div class="form-group">
        <label for="bulk_import_start_date">{$module_name|escape:'html':'UTF-8'|l s='Bulk Import Start Date'}</label>
        <input type="date" class="form-control" name="BULK_IMPORT_START_DATE" id="bulk_import_start_date" value="{$fields_value.BULK_IMPORT_START_DATE|escape:'html':'UTF-8'}" required>
    </div>

    <div class="form-group">
        <label for="bulk_import_end_date">{$module_name|escape:'html':'UTF-8'|l s='Bulk Import End Date'}</label>
        <input type="date" class="form-control" name="BULK_IMPORT_END_DATE" id="bulk_import_end_date" value="{$fields_value.BULK_IMPORT_END_DATE|escape:'html':'UTF-8'}" required>
    </div>

    <button type="submit" name="submitBulkImport" class="btn btn-primary">{$module_name|escape:'html':'UTF-8'|l s='Save'}</button>
    <button type="submit" name="submitSave" class="btn btn-success">{$module_name|escape:'html':'UTF-8'|l s='Bulk Import'}</button>
</form>

<div class="alert alert-info">
    <strong>{$module_name|escape:'html':'UTF-8'|l s='API Status:'} </strong>
    {$api_status}
</div>
