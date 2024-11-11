
<div class="panel">
    <h3>{$title}</h3>
    <form method="post" action="{$link->getAdminLink('AdminInjellikRecommendations')}">
        <div class="form-group">
            <label>{$l s='API URL'}</label>
            <input type="text" name="INJELLIK_API_URL" value="{$fields_value.INJELLIK_API_URL|escape:'htmlall':'UTF-8'}" />
        </div>
        <div class="form-group">
            <label>{$l s='API Login'}</label>
            <input type="text" name="INJELLIK_API_LOGIN" value="{$fields_value.INJELLIK_API_LOGIN|escape:'htmlall':'UTF-8'}" />
        </div>
        <div class="form-group">
            <label>{$l s='API Password'}</label>
            <input type="password" name="INJELLIK_API_PASSWORD" value="{$fields_value.INJELLIK_API_PASSWORD|escape:'htmlall':'UTF-8'}" />
        </div>
        <button type="submit" name="saveApiSettings" class="btn btn-default">{$l s='Save'}</button>
    </form>
</div>
