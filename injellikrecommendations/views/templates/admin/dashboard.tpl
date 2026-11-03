{**
 * Injellik Recommendations for PrestaShop.
 * Monitoring page.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="injellik injellik-dashboard" data-ajax-url="{$injellik.ajax_url|escape:'html':'UTF-8'}">
  {include file="./_header.tpl" subtitle="{l s='Dashboard' d='Modules.Injellikrecommendations.Admin'}"}

  {* --- Health --- *}
  <div class="row injellik-kpis">
    <div class="col-sm-6 col-lg-3">
      <div class="injellik-kpi {if $injellik.api.ok}is-ok{else}is-error{/if}">
        <span class="injellik-kpi__label">{l s='Injellik API' d='Modules.Injellikrecommendations.Admin'}</span>
        <span class="injellik-kpi__value">
          {if $injellik.api.ok}<i class="icon-check-circle"></i> {l s='Connected' d='Modules.Injellikrecommendations.Admin'}{else}<i class="icon-warning-sign"></i> {l s='Unavailable' d='Modules.Injellikrecommendations.Admin'}{/if}
        </span>
        <span class="injellik-kpi__detail">
          {if $injellik.api.ok}{$injellik.api.latency|intval} ms{else}{$injellik.api.message|escape:'html':'UTF-8'|truncate:90}{/if}
        </span>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="injellik-kpi {if $injellik.queue.pending > 100 || $injellik.queue.error > 0}is-warning{else}is-ok{/if}">
        <span class="injellik-kpi__label">{l s='Events (24 h)' d='Modules.Injellikrecommendations.Admin'}</span>
        <span class="injellik-kpi__value">{$injellik.queue.last24h|intval}</span>
        <span class="injellik-kpi__detail">
          {$injellik.queue.pending|intval} {l s='pending' d='Modules.Injellikrecommendations.Admin'} · {$injellik.queue.error|intval} {l s='in error' d='Modules.Injellikrecommendations.Admin'}
        </span>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="injellik-kpi {if $injellik.catalog.synced_at}is-ok{else}is-warning{/if}">
        <span class="injellik-kpi__label">{l s='Catalog' d='Modules.Injellikrecommendations.Admin'}</span>
        <span class="injellik-kpi__value">{$injellik.catalog.count|intval} / {$injellik.catalog.products|intval}</span>
        <span class="injellik-kpi__detail">{if $injellik.catalog.synced_at}{l s='Synchronised on' d='Modules.Injellikrecommendations.Admin'} {$injellik.catalog.synced_at|escape:'html':'UTF-8'}{else}{l s='Never synchronised' d='Modules.Injellikrecommendations.Admin'}{/if}</span>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="injellik-kpi {if $injellik.send_mode == 'cron' && !$injellik.last_cron}is-warning{else}is-ok{/if}">
        <span class="injellik-kpi__label">{l s='Cron task' d='Modules.Injellikrecommendations.Admin'}</span>
        <span class="injellik-kpi__value">{if $injellik.last_cron}{$injellik.last_cron|escape:'html':'UTF-8'|date_format:'%d/%m %H:%M'}{else}—{/if}</span>
        <span class="injellik-kpi__detail">{if $injellik.last_cron}{l s='Last run' d='Modules.Injellikrecommendations.Admin'}{else}{l s='Never run' d='Modules.Injellikrecommendations.Admin'}{/if}</span>
      </div>
    </div>
  </div>

  {if $injellik.last_error}
    <div class="alert alert-warning injellik-last-error">
      <form method="post" action="{$injellik.form_url|escape:'html':'UTF-8'}" class="pull-right">
        <button type="submit" name="submitInjellikClearError" class="btn btn-default btn-xs">{l s='Dismiss' d='Modules.Injellikrecommendations.Admin'}</button>
      </form>
      <strong>{l s='Last error' d='Modules.Injellikrecommendations.Admin'}</strong> ({$injellik.last_error.date|escape:'html':'UTF-8'})<br>
      <code>{$injellik.last_error.message|escape:'html':'UTF-8'}</code>
    </div>
  {/if}

  {* --- Activity --- *}
  <div class="panel">
    <h3><i class="icon-bar-chart"></i> {l s='Tracked activity' d='Modules.Injellikrecommendations.Admin'} <span class="badge">{$injellik.days|intval} {l s='days' d='Modules.Injellikrecommendations.Admin'}</span></h3>
    {if $injellik.chart.total > 0}
      <div class="injellik-legend">
        {foreach $injellik.chart.by_type as $type => $count}
          <span class="injellik-legend__item"><i style="background: {if isset($injellik.colors[$type])}{$injellik.colors[$type]|escape:'html':'UTF-8'}{else}#bbcdd2{/if}"></i>{$type|escape:'html':'UTF-8'} <strong>{$count|intval}</strong></span>
        {/foreach}
      </div>
      <div class="injellik-chart" role="img" aria-label="{l s='Events per day' d='Modules.Injellikrecommendations.Admin'}">
        {foreach $injellik.chart.bars as $bar}
          <div class="injellik-chart__col" title="{$bar.label|escape:'html':'UTF-8'} — {$bar.total|intval}">
            <div class="injellik-chart__bar">
              {foreach $bar.segments as $segment}
                <span style="height: {$segment.height|floatval}%; background: {$segment.color|escape:'html':'UTF-8'}" title="{$segment.type|escape:'html':'UTF-8'}: {$segment.count|intval}"></span>
              {/foreach}
            </div>
            <span class="injellik-chart__label">{$bar.short|escape:'html':'UTF-8'}</span>
          </div>
        {/foreach}
      </div>
    {else}
      <div class="injellik-empty">
        <i class="icon-eye-close"></i>
        <p>{l s='No activity tracked yet. Visit a product page of your shop: the first events appear here.' d='Modules.Injellikrecommendations.Admin'}</p>
      </div>
    {/if}
  </div>

  <div class="row">
    {* --- Blocks --- *}
    <div class="col-lg-6">
      <div class="panel">
        <h3><i class="icon-th-large"></i> {l s='Recommendation blocks' d='Modules.Injellikrecommendations.Admin'} <span class="badge">{$injellik.days|intval} {l s='days' d='Modules.Injellikrecommendations.Admin'}</span></h3>
        <table class="table">
          <thead>
            <tr>
              <th>{l s='Block' d='Modules.Injellikrecommendations.Admin'}</th>
              <th>{l s='Strategy' d='Modules.Injellikrecommendations.Admin'}</th>
              <th class="text-right">{l s='Displays' d='Modules.Injellikrecommendations.Admin'}</th>
              <th class="text-right">{l s='Clicks' d='Modules.Injellikrecommendations.Admin'}</th>
              <th class="text-right">{l s='CTR' d='Modules.Injellikrecommendations.Admin'}</th>
            </tr>
          </thead>
          <tbody>
            {foreach $injellik.blocks as $block}
              <tr{if !$block.enabled && !$block.impressions} class="text-muted"{/if}>
                <td>
                  <code>{$block.key|escape:'html':'UTF-8'}</code>
                  {if !$block.enabled}<span class="label label-default">{l s='disabled' d='Modules.Injellikrecommendations.Admin'}</span>{/if}
                </td>
                <td>{$block.strategy|escape:'html':'UTF-8'}</td>
                <td class="text-right">{$block.impressions|intval}</td>
                <td class="text-right">{$block.clicks|intval}</td>
                <td class="text-right"><strong>{$block.ctr|string_format:'%.1f'} %</strong></td>
              </tr>
            {/foreach}
          </tbody>
        </table>
        <p class="help-block">{l s='A display is counted when a block with at least one product is shown; a click when a visitor opens a recommended product.' d='Modules.Injellikrecommendations.Admin'}</p>
      </div>
    </div>

    {* --- Queue --- *}
    <div class="col-lg-6">
      <div class="panel">
        <h3><i class="icon-exchange"></i> {l s='Event queue' d='Modules.Injellikrecommendations.Admin'}</h3>
        <div class="row injellik-queue">
          <div class="col-xs-4"><span class="injellik-queue__value">{$injellik.queue.sent|intval}</span><span class="injellik-queue__label">{l s='sent' d='Modules.Injellikrecommendations.Admin'}</span></div>
          <div class="col-xs-4"><span class="injellik-queue__value">{$injellik.queue.pending|intval}</span><span class="injellik-queue__label">{l s='pending' d='Modules.Injellikrecommendations.Admin'}</span></div>
          <div class="col-xs-4"><span class="injellik-queue__value{if $injellik.queue.error} text-danger{/if}">{$injellik.queue.error|intval}</span><span class="injellik-queue__label">{l s='in error' d='Modules.Injellikrecommendations.Admin'}</span></div>
        </div>
        <p class="text-muted">
          {if $injellik.queue.last_sent}{l s='Last event sent:' d='Modules.Injellikrecommendations.Admin'} {$injellik.queue.last_sent|escape:'html':'UTF-8'}{/if}
          · {if $injellik.send_mode == 'cron'}{l s='Sent by the cron task' d='Modules.Injellikrecommendations.Admin'}{else}{l s='Sent at the end of each request' d='Modules.Injellikrecommendations.Admin'}{/if}
        </p>
        <div class="injellik-job js-injellik-job" data-action="FlushQueue" data-total="{$injellik.queue.pending|intval}" data-reload="1">
          <p class="injellik-job__message js-injellik-job-message"></p>
          <form method="post" action="{$injellik.form_url|escape:'html':'UTF-8'}" class="injellik-inline-form">
            <button type="button" class="btn btn-default js-injellik-job-start"{if !$injellik.queue.pending} disabled{/if}><i class="icon-upload"></i> {l s='Send the pending events' d='Modules.Injellikrecommendations.Admin'}</button>
            {if $injellik.queue.error}
              <button type="submit" name="submitInjellikRetryErrors" class="btn btn-default"><i class="icon-repeat"></i> {l s='Retry the events in error' d='Modules.Injellikrecommendations.Admin'}</button>
            {/if}
          </form>
        </div>
        {if $injellik.problems}
          <h4>{l s='Recent problems' d='Modules.Injellikrecommendations.Admin'}</h4>
          <table class="table table-condensed">
            <tbody>
              {foreach $injellik.problems as $event}
                <tr>
                  <td class="text-nowrap">{$event.date_add|escape:'html':'UTF-8'}</td>
                  <td><code>{$event.action_type|escape:'html':'UTF-8'}</code> #{$event.item_sku|escape:'html':'UTF-8'}</td>
                  <td><small>{$event.last_error|escape:'html':'UTF-8'}</small></td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        {/if}
      </div>
    </div>
  </div>

  {* --- Rankings --- *}
  <div class="row">
    {foreach [
      'most_viewed_items' => 'count',
      'most_bought_items' => 'count'
    ] as $ranking => $column}
      <div class="col-lg-6">
        <div class="panel">
          <h3>
            {if $ranking == 'most_viewed_items'}<i class="icon-eye"></i> {l s='Most viewed products' d='Modules.Injellikrecommendations.Admin'}{else}<i class="icon-trophy"></i> {l s='Most ordered products' d='Modules.Injellikrecommendations.Admin'}{/if}
            <span class="badge">{l s='30 days, from Injellik' d='Modules.Injellikrecommendations.Admin'}</span>
          </h3>
          {if $injellik.rankings[$ranking]}
            <table class="table">
              <tbody>
                {foreach $injellik.rankings[$ranking] as $row}
                  <tr>
                    <td class="injellik-rank">{$row@iteration|intval}</td>
                    <td>
                      {if $row.url}<a href="{$row.url|escape:'html':'UTF-8'}">{$row.label|escape:'html':'UTF-8'}</a>{else}{$row.label|escape:'html':'UTF-8'}{/if}
                      <small class="text-muted">#{$row.sku|escape:'html':'UTF-8'}</small>
                    </td>
                    <td class="text-right"><strong>{$row.count|intval}</strong></td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          {else}
            <p class="text-muted">{l s='No data yet.' d='Modules.Injellikrecommendations.Admin'}</p>
          {/if}
        </div>
      </div>
    {/foreach}
  </div>

  {* --- Recent events --- *}
  <div class="panel">
    <h3><i class="icon-list"></i> {l s='Latest events' d='Modules.Injellikrecommendations.Admin'}</h3>
    {if $injellik.recent}
      <div class="table-responsive">
        <table class="table table-condensed">
          <thead>
            <tr>
              <th>{l s='Date' d='Modules.Injellikrecommendations.Admin'}</th>
              <th>{l s='Action' d='Modules.Injellikrecommendations.Admin'}</th>
              <th>{l s='Product' d='Modules.Injellikrecommendations.Admin'}</th>
              <th>{l s='Visitor' d='Modules.Injellikrecommendations.Admin'}</th>
              <th>{l s='Status' d='Modules.Injellikrecommendations.Admin'}</th>
            </tr>
          </thead>
          <tbody>
            {foreach $injellik.recent as $event}
              <tr>
                <td class="text-nowrap">{$event.date_add|escape:'html':'UTF-8'}</td>
                <td><span class="injellik-action" style="border-color: {if isset($injellik.colors[$event.action_type])}{$injellik.colors[$event.action_type]|escape:'html':'UTF-8'}{else}#bbcdd2{/if}">{$event.action_type|escape:'html':'UTF-8'}</span></td>
                <td>#{$event.item_sku|escape:'html':'UTF-8'}</td>
                <td><small class="text-muted">{$event.user_uuid|escape:'html':'UTF-8'|truncate:14:'…'}</small></td>
                <td>
                  {if $event.status == 'sent'}<span class="label label-success">{l s='sent' d='Modules.Injellikrecommendations.Admin'}</span>
                  {elseif $event.status == 'error'}<span class="label label-danger" title="{$event.last_error|escape:'html':'UTF-8'}">{l s='error' d='Modules.Injellikrecommendations.Admin'}</span>
                  {else}<span class="label label-warning"{if $event.last_error} title="{$event.last_error|escape:'html':'UTF-8'}"{/if}>{l s='pending' d='Modules.Injellikrecommendations.Admin'}{if $event.attempts > 0} ({$event.attempts|intval}){/if}</span>{/if}
                </td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    {else}
      <p class="text-muted">{l s='No events yet.' d='Modules.Injellikrecommendations.Admin'}</p>
    {/if}
  </div>
</div>
