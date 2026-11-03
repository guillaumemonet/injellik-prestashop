{**
 * Injellik Recommendations for PrestaShop.
 *
 * Override it in themes/<your-theme>/modules/injellikrecommendations/views/templates/hook/recommendations.tpl
 * Keep the data-injellik-placement attribute: it is used to measure the clicks.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<section class="injellik-reco featured-products clearfix" data-injellik-placement="{$injellik.placement|escape:'html':'UTF-8'}">
  <h2 class="h2 products-section-title text-uppercase">{$injellik.title|escape:'html':'UTF-8'}</h2>
  {include file="catalog/_partials/productlist.tpl" products=$injellik.products cssClass="row" productClass="col-xs-12 col-sm-6 col-lg-4 col-xl-3"}
</section>
