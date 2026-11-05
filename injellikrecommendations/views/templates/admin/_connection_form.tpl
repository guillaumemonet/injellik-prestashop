{**
 * Injellik Recommendations for PrestaShop.
 * Connection form, used by the onboarding and the configuration page:
 * licence key (default), or login and password of an account created before the subscriptions.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{assign var=injellik_login_mode value=($injellik.connection.mode == 'login')}
{if $onboarding}{assign var=injellik_form_id value='onboarding'}{else}{assign var=injellik_form_id value='configuration'}{/if}
<form method="post" action="{$injellik.form_url|escape:'html':'UTF-8'}" class="form-horizontal js-injellik-connection" autocomplete="off">
  {if $onboarding}<input type="hidden" name="onboarding" value="1">{/if}
  <input type="hidden" name="auth_mode" class="js-injellik-auth-mode" value="{if $injellik_login_mode}login{else}licence{/if}">

  {* --- Licence key (default) --- *}
  <div class="js-injellik-auth" data-mode="licence"{if $injellik_login_mode} hidden{/if}>
    <div class="form-group">
      <label class="control-label col-lg-3{if !$injellik.connection.licence_key_hint} required{/if}" for="injellik-licence-key-{$injellik_form_id}">{l s='Licence key' d='Modules.Injellikrecommendations.Admin'}</label>
      <div class="col-lg-6">
        <input type="text" id="injellik-licence-key-{$injellik_form_id}" name="licence_key" class="form-control injellik-licence-key" maxlength="64" spellcheck="false" autocomplete="off"
               {if $injellik_login_mode}disabled{/if}
               {if !$injellik.connection.licence_key_hint}required{/if}
               placeholder="{if $injellik.connection.licence_key_hint}{$injellik.connection.licence_key_hint|escape:'html':'UTF-8'}{else}ijk_lic_…{/if}"
               value="{$injellik.posted.licence_key|escape:'html':'UTF-8'}">
        <p class="help-block">
          {if $injellik.connection.licence_key_hint}
            {l s='Leave empty to keep the current key.' d='Modules.Injellikrecommendations.Admin'}
          {else}
            {l s='Each shop has its own key, displayed in your web-nest.fr account once you have subscribed.' d='Modules.Injellikrecommendations.Admin'}
          {/if}
        </p>
        <p class="injellik-auth__links">
          <a href="{$injellik.links.pricing|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Get a key' d='Modules.Injellikrecommendations.Admin'} <i class="icon-external-link"></i></a>
          <a href="{$injellik.links.shops|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Find my key' d='Modules.Injellikrecommendations.Admin'} <i class="icon-external-link"></i></a>
        </p>
      </div>
    </div>
  </div>

  {* --- Login and password (accounts created before the subscriptions) --- *}
  <div class="js-injellik-auth" data-mode="login"{if !$injellik_login_mode} hidden{/if}>
    <div class="form-group">
      <label class="control-label col-lg-3 required" for="injellik-api-url-{$injellik_form_id}">{l s='API URL' d='Modules.Injellikrecommendations.Admin'}</label>
      <div class="col-lg-6">
        <input type="url" id="injellik-api-url-{$injellik_form_id}" name="api_url" class="form-control" required
               {if !$injellik_login_mode}disabled{/if}
               placeholder="{$injellik.connection.default_url|escape:'html':'UTF-8'}"
               value="{if $injellik.posted.api_url}{$injellik.posted.api_url|escape:'html':'UTF-8'}{else}{$injellik.connection.url|escape:'html':'UTF-8'}{/if}">
        <p class="help-block">{l s='Address of your Injellik API, without the /api suffix.' d='Modules.Injellikrecommendations.Admin'}</p>
      </div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3 required" for="injellik-api-login-{$injellik_form_id}">{l s='Login (email)' d='Modules.Injellikrecommendations.Admin'}</label>
      <div class="col-lg-6">
        <input type="text" id="injellik-api-login-{$injellik_form_id}" name="api_login" class="form-control" required
               {if !$injellik_login_mode}disabled{/if}
               value="{if $injellik.posted.api_login}{$injellik.posted.api_login|escape:'html':'UTF-8'}{else}{$injellik.connection.login|escape:'html':'UTF-8'}{/if}">
      </div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3{if !$injellik.connection.has_password} required{/if}" for="injellik-api-password-{$injellik_form_id}">{l s='Password' d='Modules.Injellikrecommendations.Admin'}</label>
      <div class="col-lg-6">
        <input type="password" id="injellik-api-password-{$injellik_form_id}" name="api_password" class="form-control" autocomplete="new-password"
               {if !$injellik_login_mode}disabled{/if}
               {if !$injellik.connection.has_password}required{else}placeholder="{l s='Unchanged' d='Modules.Injellikrecommendations.Admin'}"{/if}>
        {if $injellik.connection.has_password}
          <p class="help-block">{l s='Leave empty to keep the current password.' d='Modules.Injellikrecommendations.Admin'}</p>
        {/if}
      </div>
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
      <p class="injellik-auth__switch">
        <a href="#" class="js-injellik-auth-switch" data-mode="login"{if $injellik_login_mode} hidden{/if}>{l s='Use an API login' d='Modules.Injellikrecommendations.Admin'}</a>
        <a href="#" class="js-injellik-auth-switch" data-mode="licence"{if !$injellik_login_mode} hidden{/if}>{l s='Use a licence key' d='Modules.Injellikrecommendations.Admin'}</a>
      </p>
    </div>
  </div>
</form>
