{**
 * Injellik Recommendations for PrestaShop.
 * API credentials form, used by the onboarding and the configuration page.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<form method="post" action="{$injellik.form_url|escape:'html':'UTF-8'}" class="form-horizontal js-injellik-connection" autocomplete="off">
  {if $onboarding}<input type="hidden" name="onboarding" value="1">{/if}
  <div class="form-group">
    <label class="control-label col-lg-3 required" for="injellik-api-url">{l s='API URL' d='Modules.Injellikrecommendations.Admin'}</label>
    <div class="col-lg-6">
      <input type="url" id="injellik-api-url" name="api_url" class="form-control" required
             placeholder="https://api.injellik.com"
             value="{if $injellik.posted.api_url}{$injellik.posted.api_url|escape:'html':'UTF-8'}{else}{$injellik.connection.url|escape:'html':'UTF-8'}{/if}">
      <p class="help-block">{l s='Address of your Injellik API, without the /api suffix.' d='Modules.Injellikrecommendations.Admin'}</p>
    </div>
  </div>
  <div class="form-group">
    <label class="control-label col-lg-3 required" for="injellik-api-login">{l s='Login (email)' d='Modules.Injellikrecommendations.Admin'}</label>
    <div class="col-lg-6">
      <input type="text" id="injellik-api-login" name="api_login" class="form-control" required
             value="{if $injellik.posted.api_login}{$injellik.posted.api_login|escape:'html':'UTF-8'}{else}{$injellik.connection.login|escape:'html':'UTF-8'}{/if}">
    </div>
  </div>
  <div class="form-group">
    <label class="control-label col-lg-3{if !$injellik.connection.has_password} required{/if}" for="injellik-api-password">{l s='Password' d='Modules.Injellikrecommendations.Admin'}</label>
    <div class="col-lg-6">
      <input type="password" id="injellik-api-password" name="api_password" class="form-control" autocomplete="new-password"
             {if !$injellik.connection.has_password}required{else}placeholder="{l s='Unchanged' d='Modules.Injellikrecommendations.Admin'}"{/if}>
      {if $injellik.connection.has_password}
        <p class="help-block">{l s='Leave empty to keep the current password.' d='Modules.Injellikrecommendations.Admin'}</p>
      {/if}
    </div>
  </div>
  <div class="form-group">
    <div class="col-lg-6 col-lg-offset-3">
      <div class="injellik-test-result js-injellik-test-result" hidden></div>
      <button type="button" class="btn btn-default js-injellik-test" data-loading="{l s='Testing...' d='Modules.Injellikrecommendations.Admin'}">
        <i class="icon-plug"></i> {l s='Test the connection' d='Modules.Injellikrecommendations.Admin'}
      </button>
      <button type="submit" name="submitInjellikConnection" class="btn btn-primary">
        {if $onboarding}{l s='Connect and continue' d='Modules.Injellikrecommendations.Admin'} <i class="icon-arrow-right"></i>{else}<i class="icon-save"></i> {l s='Save' d='Modules.Injellikrecommendations.Admin'}{/if}
      </button>
    </div>
  </div>
</form>
