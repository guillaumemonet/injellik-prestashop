<!-- Recommendations by Injellik by Web-nest.fr -->
<section class="featured-products clearfix">
    <h2>{l s='Ces produits peuvent vous interesser:' d='Modules.Injellik.Shop'}</h2>
    <div class="products">
        {foreach from=$products item="product"}
            {include file="catalog/_partials/miniatures/product.tpl" product=$product}
        {/foreach}
    </div>
    <small style="float:right;color:gray"><i>Recommendations by Injellik</i></small>
</section>

