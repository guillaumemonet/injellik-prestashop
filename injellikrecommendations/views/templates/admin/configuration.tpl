{**
 * Injellik Recommendations for PrestaShop.
 * Configuration page: connection, blocks, data, integration, advanced settings.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="injellik injellik-configuration" data-ajax-url="{$injellik.ajax_url|escape:'html':'UTF-8'}">
  {include file="./_header.tpl" subtitle="{l s='Configuration' d='Modules.Injellikrecommendations.Admin'}"}

  {if $injellik.multishop_warning}
    <div class="alert alert-warning">{l s='You are editing the settings of all shops. Select a shop in the multistore menu to configure it separately: each shop can use its own Injellik account.' d='Modules.Injellikrecommendations.Admin'}</div>
  {/if}

  <ul class="nav nav-tabs injellik-tabs" role="tablist">
    <li class="active"><a href="#injellik-connection" data-toggle="tab"><i class="icon-plug"></i> {l s='Connection' d='Modules.Injellikrecommendations.Admin'}</a></li>
    <li><a href="#injellik-placements" data-toggle="tab"><i class="icon-th-large"></i> {l s='Blocks' d='Modules.Injellikrecommendations.Admin'}</a></li>
    <li><a href="#injellik-data" data-toggle="tab"><i class="icon-refresh"></i> {l s='Data' d='Modules.Injellikrecommendations.Admin'}</a></li>
    <li><a href="#injellik-integration" data-toggle="tab"><i class="icon-code"></i> {l s='Integration' d='Modules.Injellikrecommendations.Admin'}</a></li>
    <li><a href="#injellik-advanced" data-toggle="tab"><i class="icon-wrench"></i> {l s='Advanced' d='Modules.Injellikrecommendations.Admin'}</a></li>
  </ul>

  <div class="tab-content">
    {* --- Connection --- *}
    <div class="tab-pane active" id="injellik-connection">
      <div class="panel">
        <h3><i class="icon-plug"></i> {l s='Injellik API account' d='Modules.Injellikrecommendations.Admin'}</h3>
        {include file="./_connection_form.tpl" onboarding=false}
        <hr>
        <p class="text-muted">
          {l s='Need help or a new account?' d='Modules.Injellikrecommendations.Admin'}
          <a href="{$injellik.onboarding_url|escape:'html':'UTF-8'}">{l s='Run the setup assistant again' d='Modules.Injellikrecommendations.Admin'}</a>
        </p>
      </div>
    </div>

    {* --- Blocks --- *}
    <div class="tab-pane" id="injellik-placements">
      <div class="panel">
        <h3><i class="icon-th-large"></i> {l s='Recommendation blocks' d='Modules.Injellikrecommendations.Admin'}</h3>
        <p class="help-block">{l s='The source products are the current product on product pages, the cart content on the cart page and the ordered products on the confirmation page. Strategies based on products fall back when there is no product.' d='Modules.Injellikrecommendations.Admin'}</p>
        {include file="./_placements_form.tpl" onboarding=false}
      </div>
    </div>

    {* --- Data --- *}
    <div class="tab-pane" id="injellik-data">
      <div class="row">
        <div class="col-lg-6">
          <div class="panel">
            <h3><i class="icon-archive"></i> {l s='Catalog' d='Modules.Injellikrecommendations.Admin'}</h3>
            <div class="injellik-job js-injellik-job" data-action="SyncCatalog" data-total="{$injellik.product_count|intval}">
              <p>
                {if $injellik.catalog_synced_at}
                  {l s='Last full synchronisation:' d='Modules.Injellikrecommendations.Admin'} <strong>{$injellik.catalog_synced_at|escape:'html':'UTF-8'}</strong> ({$injellik.catalog_count|intval} {l s='products' d='Modules.Injellikrecommendations.Admin'})
                {else}
                  <span class="label label-warning">{l s='Never synchronised' d='Modules.Injellikrecommendations.Admin'}</span>
                {/if}
              </p>
              <p class="help-block">{l s='Products are synchronised automatically when they are saved. Run a full synchronisation after a large import or a price update.' d='Modules.Injellikrecommendations.Admin'}</p>
              <div class="progress"><div class="progress-bar js-injellik-job-bar" style="width: 0%"></div></div>
              <p class="injellik-job__message js-injellik-job-message"></p>
              <button type="button" class="btn btn-default js-injellik-job-start"><i class="icon-refresh"></i> {l s='Synchronise the catalog' d='Modules.Injellikrecommendations.Admin'}</button>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="panel">
            <h3><i class="icon-shopping-cart"></i> {l s='Order history' d='Modules.Injellikrecommendations.Admin'}</h3>
            <div class="injellik-job js-injellik-job" data-action="ImportOrders" data-total="{$injellik.orders_remaining|intval}">
              <p>
                {if $injellik.orders_imported_at}
                  {l s='Last import:' d='Modules.Injellikrecommendations.Admin'} <strong>{$injellik.orders_imported_at|escape:'html':'UTF-8'}</strong> ({$injellik.orders_imported|intval} {l s='orders' d='Modules.Injellikrecommendations.Admin'})
                {else}
                  <span class="label label-default">{l s='Not imported' d='Modules.Injellikrecommendations.Admin'}</span>
                {/if}
                — {$injellik.orders_remaining|intval} {l s='orders not imported yet' d='Modules.Injellikrecommendations.Admin'}
              </p>
              <p class="help-block">{l s='The import resumes where it stopped: new orders are tracked live, so it is only needed once.' d='Modules.Injellikrecommendations.Admin'}</p>
              <div class="form-inline injellik-job__options">
                <label for="injellik-since">{l s='Orders placed since' d='Modules.Injellikrecommendations.Admin'}</label>
                <input type="date" id="injellik-since" class="form-control js-injellik-job-param" name="since" value="{$injellik.history_since|escape:'html':'UTF-8'}">
              </div>
              <div class="progress"><div class="progress-bar js-injellik-job-bar" style="width: 0%"></div></div>
              <p class="injellik-job__message js-injellik-job-message"></p>
              <form method="post" action="{$injellik.form_url|escape:'html':'UTF-8'}" class="injellik-inline-form">
                <button type="button" class="btn btn-default js-injellik-job-start"><i class="icon-download"></i> {l s='Import the orders' d='Modules.Injellikrecommendations.Admin'}</button>
                <button type="submit" name="submitInjellikResetHistory" class="btn btn-link" onclick="return confirm('{l s='The next import will send all the orders again. Continue?' d='Modules.Injellikrecommendations.Admin' js=1}');">{l s='Restart from the first order' d='Modules.Injellikrecommendations.Admin'}</button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="panel">
        <h3><i class="icon-time"></i> {l s='Cron task' d='Modules.Injellikrecommendations.Admin'}</h3>
        <p>{l s='Schedule this URL every 5 to 15 minutes. It sends the events that could not be sent immediately, retries the failures and purges the events older than 30 days.' d='Modules.Injellikrecommendations.Admin'}</p>
        <div class="input-group injellik-copy">
          <input type="text" class="form-control js-injellik-copy-source" readonly value="{$injellik.cron_url|escape:'html':'UTF-8'}">
          <span class="input-group-btn"><button type="button" class="btn btn-default js-injellik-copy"><i class="icon-copy"></i> {l s='Copy' d='Modules.Injellikrecommendations.Admin'}</button></span>
        </div>
        <pre class="injellik-code">*/10 * * * * curl -s "{$injellik.cron_url|escape:'html':'UTF-8'}" &gt; /dev/null</pre>
        <p>{l s='Optional, once a night: full catalog synchronisation.' d='Modules.Injellikrecommendations.Admin'}</p>
        <pre class="injellik-code">30 2 * * * curl -s "{$injellik.cron_catalog_url|escape:'html':'UTF-8'}" &gt; /dev/null</pre>
        <p class="text-muted">
          {if $injellik.last_cron}{l s='Last run:' d='Modules.Injellikrecommendations.Admin'} {$injellik.last_cron|escape:'html':'UTF-8'}{else}{l s='The cron task has never run.' d='Modules.Injellikrecommendations.Admin'}{/if}
        </p>
        <form method="post" action="{$injellik.form_url|escape:'html':'UTF-8'}">
          <button type="submit" name="submitInjellikRegenerateCronToken" class="btn btn-default btn-sm" onclick="return confirm('{l s='The current cron URL will stop working. Continue?' d='Modules.Injellikrecommendations.Admin' js=1}');"><i class="icon-key"></i> {l s='Generate a new token' d='Modules.Injellikrecommendations.Admin'}</button>
        </form>
      </div>
    </div>

    {* --- Integration --- *}
    <div class="tab-pane" id="injellik-integration">
      <div class="panel">
        <h3><i class="icon-code"></i> {l s='Display blocks in your theme' d='Modules.Injellikrecommendations.Admin'}</h3>
        <p>{l s='The blocks of the "Blocks" tab are displayed on the standard hooks of your theme. To place a block elsewhere, use one of these methods.' d='Modules.Injellikrecommendations.Admin'}</p>

        <h4>{l s='1. Hook the module on another position' d='Modules.Injellikrecommendations.Admin'}</h4>
        <p>{l s='Design > Positions > Transplant a module, then choose the hook. The block uses the settings of "Widget and other hooks".' d='Modules.Injellikrecommendations.Admin'}</p>

        <h4>{l s='2. Insert a widget in a template' d='Modules.Injellikrecommendations.Admin'}</h4>
        <pre class="injellik-code">{literal}{* Settings of "Widget and other hooks" *}
{widget name='injellikrecommendations'}

{* Same settings as a block of the "Blocks" tab *}
{widget name='injellikrecommendations' placement='product'}

{* Custom block *}
{widget name='injellikrecommendations' strategy='bought' fallback='most_bought' limit=4 title='Complete the look'}

{* Recommendations for given products *}
{widget name='injellikrecommendations' strategy='similar' product_id=$product.id}{/literal}</pre>

        <table class="table">
          <thead><tr><th>{l s='Parameter' d='Modules.Injellikrecommendations.Admin'}</th><th>{l s='Values' d='Modules.Injellikrecommendations.Admin'}</th></tr></thead>
          <tbody>
            <tr><td><code>placement</code></td><td><code>product</code>, <code>cart</code>, <code>order_confirmation</code>, <code>home</code>, <code>category</code>, <code>custom</code></td></tr>
            <tr>
              <td><code>strategy</code>, <code>fallback</code></td>
              <td>
                {foreach $injellik.strategies as $strategy => $info}
                  <code>{$strategy|escape:'html':'UTF-8'}</code> {$info.label|escape:'html':'UTF-8'}{if !$info@last}, {/if}
                {/foreach}
              </td>
            </tr>
            <tr><td><code>limit</code></td><td>1 – 24</td></tr>
            <tr><td><code>title</code></td><td>{l s='Text displayed above the products' d='Modules.Injellikrecommendations.Admin'}</td></tr>
            <tr><td><code>product_id</code></td><td>{l s='Source product id, or several ids separated by commas (default: current product or cart)' d='Modules.Injellikrecommendations.Admin'}</td></tr>
          </tbody>
        </table>

        <h4>{l s='3. Customise the template' d='Modules.Injellikrecommendations.Admin'}</h4>
        <p>{l s='Copy the template into your theme to change the markup; the product miniatures come from your theme.' d='Modules.Injellikrecommendations.Admin'}</p>
        <pre class="injellik-code">modules/injellikrecommendations/views/templates/hook/recommendations.tpl
→ themes/YOUR_THEME/modules/injellikrecommendations/views/templates/hook/recommendations.tpl</pre>
        <p class="help-block">{l s='The blocks are loaded after the page (compatible with full page caches). The JavaScript event "injellik:loaded" is dispatched on document when a block is inserted.' d='Modules.Injellikrecommendations.Admin'}</p>
      </div>
    </div>

    {* --- Advanced --- *}
    <div class="tab-pane" id="injellik-advanced">
      <form method="post" action="{$injellik.form_url|escape:'html':'UTF-8'}" class="form-horizontal panel">
        <h3><i class="icon-wrench"></i> {l s='Advanced settings' d='Modules.Injellikrecommendations.Admin'}</h3>

        <div class="form-group">
          <label class="control-label col-lg-3" for="injellik-advanced-api-url">{l s='API URL' d='Modules.Injellikrecommendations.Admin'}</label>
          <div class="col-lg-6">
            <input type="url" id="injellik-advanced-api-url" name="api_url" class="form-control"
                   placeholder="{$injellik.connection.default_url|escape:'html':'UTF-8'}"
                   value="{$injellik.connection.url|escape:'html':'UTF-8'}">
            <p class="help-block">{l s='Keep the default address (%url%) unless Web-Nest asks you to change it.' sprintf=['%url%' => $injellik.connection.default_url] d='Modules.Injellikrecommendations.Admin'}</p>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-3">{l s='Block rendering' d='Modules.Injellikrecommendations.Admin'}</label>
          <div class="col-lg-6">
            <div class="radio"><label><input type="radio" name="render_mode" value="ajax"{if $injellik.advanced.render_mode != 'server'} checked{/if}> {l s='Asynchronous (recommended)' d='Modules.Injellikrecommendations.Admin'}</label></div>
            <div class="radio"><label><input type="radio" name="render_mode" value="server"{if $injellik.advanced.render_mode == 'server'} checked{/if}> {l s='In the page' d='Modules.Injellikrecommendations.Admin'}</label></div>
            <p class="help-block">{l s='Asynchronous: the page is displayed immediately and the blocks are loaded when they come into view. In the page: the API is called while the page is generated (slower pages, blocks visible without JavaScript).' d='Modules.Injellikrecommendations.Admin'}</p>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-3">{l s='Sending of events' d='Modules.Injellikrecommendations.Admin'}</label>
          <div class="col-lg-6">
            <div class="radio"><label><input type="radio" name="send_mode" value="realtime"{if $injellik.advanced.send_mode != 'cron'} checked{/if}> {l s='At the end of each request (recommended)' d='Modules.Injellikrecommendations.Admin'}</label></div>
            <div class="radio"><label><input type="radio" name="send_mode" value="cron"{if $injellik.advanced.send_mode == 'cron'} checked{/if}> {l s='By the cron task only' d='Modules.Injellikrecommendations.Admin'}</label></div>
            <p class="help-block">{l s='Events are always stored in your shop first. With PHP-FPM they are sent after the page has been delivered to the visitor.' d='Modules.Injellikrecommendations.Admin'}</p>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-3" for="injellik-timeout">{l s='API timeout' d='Modules.Injellikrecommendations.Admin'}</label>
          <div class="col-lg-2">
            <div class="input-group"><input type="number" min="1" max="10" id="injellik-timeout" name="timeout" class="form-control" value="{$injellik.advanced.timeout|intval}"><span class="input-group-addon">s</span></div>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-3" for="injellik-cache">{l s='Recommendation cache' d='Modules.Injellikrecommendations.Admin'}</label>
          <div class="col-lg-2">
            <div class="input-group"><input type="number" min="0" max="86400" id="injellik-cache" name="cache_ttl" class="form-control" value="{$injellik.advanced.cache_ttl|intval}"><span class="input-group-addon">s</span></div>
          </div>
          <div class="col-lg-4"><p class="help-block">{l s='Personalised recommendations are cached for 60 seconds at most.' d='Modules.Injellikrecommendations.Admin'}</p></div>
        </div>

        <div class="form-group">
          <div class="col-lg-6 col-lg-offset-3">
            <div class="checkbox"><label><input type="checkbox" name="hide_out_of_stock" value="1"{if $injellik.advanced.hide_out_of_stock} checked{/if}> {l s='Hide out-of-stock products that cannot be ordered' d='Modules.Injellikrecommendations.Admin'}</label></div>
          </div>
        </div>
        <div class="form-group">
          <div class="col-lg-6 col-lg-offset-3">
            <div class="checkbox"><label><input type="checkbox" name="exclude_bots" value="1"{if $injellik.advanced.exclude_bots} checked{/if}> {l s='Ignore the visits of bots and crawlers' d='Modules.Injellikrecommendations.Admin'}</label></div>
          </div>
        </div>
        <div class="form-group">
          <div class="col-lg-6 col-lg-offset-3">
            <div class="checkbox"><label><input type="checkbox" name="auto_sync" value="1"{if $injellik.advanced.auto_sync} checked{/if}> {l s='Synchronise products automatically when they are saved' d='Modules.Injellikrecommendations.Admin'}</label></div>
          </div>
        </div>

        <div class="panel-footer">
          <button type="submit" name="submitInjellikAdvanced" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save' d='Modules.Injellikrecommendations.Admin'}</button>
        </div>
      </form>
    </div>
  </div>
</div>
