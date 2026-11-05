{**
 * Injellik Recommendations for PrestaShop.
 * Onboarding wizard: welcome, connection, catalog, order history, blocks, done.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="injellik injellik-onboarding" data-ajax-url="{$injellik.ajax_url|escape:'html':'UTF-8'}">
  {include file="./_header.tpl" subtitle="{l s='Getting started' d='Modules.Injellikrecommendations.Admin'}"}

  <ol class="injellik-stepper">
    {foreach $injellik.steps as $key => $label name=steps}
      <li class="injellik-stepper__step{if $smarty.foreach.steps.index < $injellik.step_index} is-done{elseif $key == $injellik.step} is-current{/if}">
        <span class="injellik-stepper__number">{if $smarty.foreach.steps.index < $injellik.step_index}<i class="icon-check"></i>{else}{$smarty.foreach.steps.iteration|intval}{/if}</span>
        <span class="injellik-stepper__label">{$label|escape:'html':'UTF-8'}</span>
      </li>
    {/foreach}
  </ol>

  <div class="panel injellik-step">
    {if $injellik.step == 'welcome'}
      <h2 class="injellik-step__title">{l s='Recommend the right products to each visitor' d='Modules.Injellikrecommendations.Admin'}</h2>
      <p class="injellik-step__lead">{l s='Injellik learns from the behaviour of your visitors (products viewed, added to cart, ordered) and displays recommendation blocks on your pages. Setup takes about five minutes.' d='Modules.Injellikrecommendations.Admin'}</p>

      <div class="row injellik-features">
        <div class="col-md-4">
          <div class="injellik-feature">
            <i class="icon-eye"></i>
            <h4>{l s='1. Tracking' d='Modules.Injellikrecommendations.Admin'}</h4>
            <p>{l s='Views, carts and orders are recorded in your shop and sent to Injellik in the background. Your pages are never slowed down, even if the API is unavailable.' d='Modules.Injellikrecommendations.Admin'}</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="injellik-feature">
            <i class="icon-cogs"></i>
            <h4>{l s='2. Learning' d='Modules.Injellikrecommendations.Admin'}</h4>
            <p>{l s='Every night, Injellik computes which products are viewed and bought together and which products are similar.' d='Modules.Injellikrecommendations.Admin'}</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="injellik-feature">
            <i class="icon-th-large"></i>
            <h4>{l s='3. Recommendations' d='Modules.Injellikrecommendations.Admin'}</h4>
            <p>{l s='Blocks on the product, cart and confirmation pages, or anywhere in your theme with a widget, using your theme product miniatures.' d='Modules.Injellikrecommendations.Admin'}</p>
          </div>
        </div>
      </div>

      <div class="alert alert-info">
        {l s='You need an Injellik licence key, provided with a subscription on web-nest.fr.' d='Modules.Injellikrecommendations.Admin'}
        <a href="{$injellik.links.pricing|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Get a key' d='Modules.Injellikrecommendations.Admin'} <i class="icon-external-link"></i></a>
      </div>

      <div class="injellik-actions">
        <a href="{$injellik.step_urls.connection|escape:'html':'UTF-8'}" class="btn btn-primary btn-lg">{l s='Start' d='Modules.Injellikrecommendations.Admin'} <i class="icon-arrow-right"></i></a>
      </div>

    {elseif $injellik.step == 'connection'}
      <h2 class="injellik-step__title">{l s='Connect your shop to Injellik' d='Modules.Injellikrecommendations.Admin'}</h2>
      <p class="injellik-step__lead">{l s='Paste the licence key of this shop. It is stored in your shop configuration and only used by the server.' d='Modules.Injellikrecommendations.Admin'}</p>
      {include file="./_connection_form.tpl" onboarding=true}

    {elseif $injellik.step == 'catalog'}
      <h2 class="injellik-step__title">{l s='Synchronise your catalog' d='Modules.Injellikrecommendations.Admin'}</h2>
      <p class="injellik-step__lead">{l s='Your products (name, price, categories, brand) are sent to Injellik so that it can recommend them and compute similar products. Later changes are synchronised automatically.' d='Modules.Injellikrecommendations.Admin'}</p>

      <div class="injellik-job js-injellik-job" data-action="SyncCatalog" data-total="{$injellik.product_count|intval}" data-autostart="{if !$injellik.catalog_synced_at}1{/if}">
        <div class="injellik-job__summary">
          <strong>{$injellik.product_count|intval}</strong> {l s='products in this shop' d='Modules.Injellikrecommendations.Admin'}
          {if $injellik.catalog_synced_at}<span class="text-muted">— {l s='last synchronisation:' d='Modules.Injellikrecommendations.Admin'} {$injellik.catalog_synced_at|escape:'html':'UTF-8'}</span>{/if}
        </div>
        <div class="progress"><div class="progress-bar js-injellik-job-bar" style="width: {if $injellik.catalog_synced_at}100{else}0{/if}%"></div></div>
        <p class="injellik-job__message js-injellik-job-message"></p>
        <button type="button" class="btn btn-default js-injellik-job-start">
          <i class="icon-refresh"></i> {if $injellik.catalog_synced_at}{l s='Synchronise again' d='Modules.Injellikrecommendations.Admin'}{else}{l s='Synchronise' d='Modules.Injellikrecommendations.Admin'}{/if}
        </button>
      </div>

      <div class="injellik-actions">
        <a href="{$injellik.step_urls.connection|escape:'html':'UTF-8'}" class="btn btn-link"><i class="icon-arrow-left"></i> {l s='Back' d='Modules.Injellikrecommendations.Admin'}</a>
        <a href="{$injellik.step_urls.history|escape:'html':'UTF-8'}" class="btn btn-primary js-injellik-next{if !$injellik.catalog_synced_at} disabled{/if}">{l s='Continue' d='Modules.Injellikrecommendations.Admin'} <i class="icon-arrow-right"></i></a>
      </div>

    {elseif $injellik.step == 'history'}
      <h2 class="injellik-step__title">{l s='Import your order history' d='Modules.Injellikrecommendations.Admin'}</h2>
      <p class="injellik-step__lead">{l s='Past orders give Injellik a head start: "bought together" recommendations are available after the next training instead of after weeks of tracking. Customers are identified by an anonymous hash, no personal data is sent.' d='Modules.Injellikrecommendations.Admin'}</p>

      <div class="injellik-job js-injellik-job" data-action="ImportOrders" data-total="{$injellik.order_count|intval}">
        <div class="form-inline injellik-job__options">
          <label for="injellik-since">{l s='Orders placed since' d='Modules.Injellikrecommendations.Admin'}</label>
          <input type="date" id="injellik-since" class="form-control js-injellik-job-param" name="since" value="{$injellik.history_since|escape:'html':'UTF-8'}">
          <span class="text-muted">({$injellik.order_count|intval} {l s='valid orders in total' d='Modules.Injellikrecommendations.Admin'})</span>
        </div>
        <div class="progress"><div class="progress-bar js-injellik-job-bar" style="width: {if $injellik.orders_imported_at}100{else}0{/if}%"></div></div>
        <p class="injellik-job__message js-injellik-job-message">{if $injellik.orders_imported_at}{l s='Last import:' d='Modules.Injellikrecommendations.Admin'} {$injellik.orders_imported_at|escape:'html':'UTF-8'}{/if}</p>
        <button type="button" class="btn btn-default js-injellik-job-start"><i class="icon-download"></i> {l s='Import the orders' d='Modules.Injellikrecommendations.Admin'}</button>
      </div>

      <div class="injellik-actions">
        <a href="{$injellik.step_urls.catalog|escape:'html':'UTF-8'}" class="btn btn-link"><i class="icon-arrow-left"></i> {l s='Back' d='Modules.Injellikrecommendations.Admin'}</a>
        <a href="{$injellik.step_urls.placements|escape:'html':'UTF-8'}" class="btn btn-default">{l s='Skip this step' d='Modules.Injellikrecommendations.Admin'}</a>
        <a href="{$injellik.step_urls.placements|escape:'html':'UTF-8'}" class="btn btn-primary js-injellik-next{if !$injellik.orders_imported_at} disabled{/if}">{l s='Continue' d='Modules.Injellikrecommendations.Admin'} <i class="icon-arrow-right"></i></a>
      </div>

    {elseif $injellik.step == 'placements'}
      <h2 class="injellik-step__title">{l s='Choose where to display recommendations' d='Modules.Injellikrecommendations.Admin'}</h2>
      <p class="injellik-step__lead">{l s='Enable the blocks you want. Each block uses a strategy, and a fallback strategy while Injellik is still learning. You can change everything later.' d='Modules.Injellikrecommendations.Admin'}</p>
      {include file="./_placements_form.tpl" onboarding=true}

    {elseif $injellik.step == 'done'}
      <div class="injellik-done">
        <div class="injellik-done__icon"><i class="icon-check"></i></div>
        <h2 class="injellik-step__title">{l s='Injellik is ready' d='Modules.Injellikrecommendations.Admin'}</h2>
        <p class="injellik-step__lead">{l s='Tracking is active. Recommendations based on behaviour appear after the next nightly training; until then the blocks use the fallback strategy.' d='Modules.Injellikrecommendations.Admin'}</p>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="injellik-tip">
            <h4><i class="icon-time"></i> {l s='Recommended: schedule the cron task' d='Modules.Injellikrecommendations.Admin'}</h4>
            <p>{l s='It retries the events that could not be sent and cleans the old ones. The URL is on the configuration page.' d='Modules.Injellikrecommendations.Admin'}</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="injellik-tip">
            <h4><i class="icon-code"></i> {l s='Add blocks anywhere in your theme' d='Modules.Injellikrecommendations.Admin'}</h4>
            <p><code>{literal}{widget name='injellikrecommendations'}{/literal}</code></p>
          </div>
        </div>
      </div>

      <div class="injellik-actions">
        <a href="{$injellik.configuration_url|escape:'html':'UTF-8'}" class="btn btn-default"><i class="icon-cogs"></i> {l s='Configuration' d='Modules.Injellikrecommendations.Admin'}</a>
        <a href="{$injellik.dashboard_url|escape:'html':'UTF-8'}" class="btn btn-primary btn-lg"><i class="icon-bar-chart"></i> {l s='Open the dashboard' d='Modules.Injellikrecommendations.Admin'}</a>
      </div>
    {/if}
  </div>
</div>
