{**
 * Injellik Recommendations for PrestaShop.
 * Recommendation blocks form, used by the onboarding and the configuration page.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<form method="post" action="{$injellik.form_url|escape:'html':'UTF-8'}" class="injellik-placements">
  {if $onboarding}<input type="hidden" name="onboarding" value="1">{/if}
  {foreach $injellik.placements as $key => $placement}
    <div class="injellik-placement{if $placement.enabled} is-enabled{/if} js-injellik-placement">
      <label class="injellik-placement__toggle">
        <input type="checkbox" name="placements[{$key|escape:'html':'UTF-8'}][enabled]" value="1" class="js-injellik-placement-toggle"{if $placement.enabled} checked{/if}>
        <span class="injellik-placement__name">{$placement.label|escape:'html':'UTF-8'}</span>
        {if $placement.hook}<code class="injellik-placement__hook">{$placement.hook|escape:'html':'UTF-8'}</code>{/if}
      </label>
      <p class="injellik-placement__help">{$placement.help|escape:'html':'UTF-8'}</p>

      <div class="injellik-placement__settings">
        <div class="row">
          <div class="col-md-4">
            <label>{l s='Strategy' d='Modules.Injellikrecommendations.Admin'}</label>
            <select name="placements[{$key|escape:'html':'UTF-8'}][strategy]" class="form-control">
              {foreach $injellik.strategies as $strategy => $info}
                <option value="{$strategy|escape:'html':'UTF-8'}" title="{$info.help|escape:'html':'UTF-8'}"{if $placement.strategy == $strategy} selected{/if}>{$info.label|escape:'html':'UTF-8'}</option>
              {/foreach}
            </select>
          </div>
          <div class="col-md-4">
            <label>{l s='If there are not enough results' d='Modules.Injellikrecommendations.Admin'}</label>
            <select name="placements[{$key|escape:'html':'UTF-8'}][fallback]" class="form-control">
              <option value=""{if $placement.fallback == ''} selected{/if}>{l s='Show nothing more' d='Modules.Injellikrecommendations.Admin'}</option>
              {foreach $injellik.strategies as $strategy => $info}
                <option value="{$strategy|escape:'html':'UTF-8'}"{if $placement.fallback == $strategy} selected{/if}>{$info.label|escape:'html':'UTF-8'}</option>
              {/foreach}
            </select>
          </div>
          <div class="col-md-2">
            <label>{l s='Products' d='Modules.Injellikrecommendations.Admin'}</label>
            <input type="number" min="1" max="24" name="placements[{$key|escape:'html':'UTF-8'}][limit]" value="{$placement.limit|intval}" class="form-control">
          </div>
        </div>
        <div class="row injellik-placement__titles">
          <div class="col-md-10">
            <label>{l s='Title' d='Modules.Injellikrecommendations.Admin'}</label>
            {foreach $injellik.languages as $language}
              <div class="input-group">
                {if $injellik.languages|count > 1}<span class="input-group-addon">{$language.iso_code|upper|escape:'html':'UTF-8'}</span>{/if}
                <input type="text" maxlength="128" class="form-control"
                       name="placements[{$key|escape:'html':'UTF-8'}][title][{$language.id_lang|intval}]"
                       value="{if isset($placement.title[$language.id_lang])}{$placement.title[$language.id_lang]|escape:'html':'UTF-8'}{/if}"
                       placeholder="{$placement.default_title|escape:'html':'UTF-8'}">
              </div>
            {/foreach}
          </div>
        </div>
      </div>
    </div>
  {/foreach}

  <div class="injellik-actions">
    <button type="submit" name="submitInjellikPlacements" class="btn btn-primary">
      {if $onboarding}{l s='Save and finish' d='Modules.Injellikrecommendations.Admin'} <i class="icon-arrow-right"></i>{else}<i class="icon-save"></i> {l s='Save' d='Modules.Injellikrecommendations.Admin'}{/if}
    </button>
  </div>
</form>
