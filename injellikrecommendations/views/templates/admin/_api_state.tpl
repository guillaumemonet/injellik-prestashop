{**
 * Injellik Recommendations for PrestaShop.
 * Notice displayed while the API refuses the shop: subscription suspended or licence key invalid.
 * Detailed on the pages of the module, compact on the other back-office pages ($compact).
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{if $api_state.reason == 'suspended'}
  <div class="alert alert-danger injellik-api-state{if $compact} injellik-api-state--compact{/if}" role="alert">
    <strong>{l s='Your Injellik subscription is suspended.' d='Modules.Injellikrecommendations.Admin'}</strong>
    {if !$compact}
      {l s='The recommendation blocks are hidden and the events are kept in your shop: they will be sent as soon as the subscription is active again.' d='Modules.Injellikrecommendations.Admin'}
      {if $api_state.since}<span class="text-muted">({l s='since %date%' sprintf=['%date%' => $api_state.since] d='Modules.Injellikrecommendations.Admin'})</span>{/if}
    {/if}
    <a href="{$links.subscription|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Manage my subscription' d='Modules.Injellikrecommendations.Admin'} <i class="icon-external-link"></i></a>
  </div>
{else}
  <div class="alert alert-warning injellik-api-state{if $compact} injellik-api-state--compact{/if}" role="alert">
    <strong>{if $compact}Injellik — {/if}{l s='Invalid or revoked licence key.' d='Modules.Injellikrecommendations.Admin'}</strong>
    {if !$compact}
      {l s='The recommendation blocks are hidden until a valid key is saved in the Connection tab.' d='Modules.Injellikrecommendations.Admin'}
    {elseif isset($configuration_url)}
      <a href="{$configuration_url|escape:'html':'UTF-8'}">{l s='Configuration' d='Modules.Injellikrecommendations.Admin'}</a> ·
    {/if}
    <a href="{$links.shops|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Find my key' d='Modules.Injellikrecommendations.Admin'} <i class="icon-external-link"></i></a>
  </div>
{/if}
