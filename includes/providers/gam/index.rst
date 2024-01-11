#######
Google Ad Manager
#######

API
---

The API communication with GAM is made through `googleads-php-lib`_. Being a SOAP library, `our own API layer on top of it`_ was implemented to cover our use of GAM.

.. _googleads-php-lib: https://github.com/googleads/googleads-php-lib
.. _our own API layer on top of it: classes/Newspack-Ads-Providers-GAM-Api.html

Most importantly, the API establishes the connection so we can fetch and update ad units used for placements. This authentication is made `either through service account credentials or an OAuth connection`_ proxied by Newspack Manager.

.. _either through service account credentials or an OAuth connection: classes/Newspack-Ads-Providers-GAM-Model.html#method_get_api

The API grows on demand, as new development requires simplified access to the SOAP library. It also integrates with other GAM entities, such as targeting key-vals, advertisers, line items, orders, and creatives. These integrations are used by targeting key-vals, header bidding, and the (in progress) self-serve ads project.

Targeting key-values
--------------------

.. image:: /docs-assets/gam-1.webp

Custom targeting key-values allow the publisher to target specific ads according to values defined in the ad slot.

When a GAM connection is established, the `plugin automatically creates`_ the following keys:

.. _plugin automatically creates: classes/Newspack-Ads-Providers-GAM-Api-Targeting-Keys.html

- `id`: ID of the singular content (either post, page, or custom post type)
- `slug`: Slug of the singular content
- `category`: List of post categories or the category archive
- `post_type`: Post type of singular content
- `template`: Page template for the singular content
- `site`: Site URL

`Each key value is obtained while rendering the ad`_ to reflect the context in which the ad is being shown. The newspack_ads_ad_targeting filter allows the targeting to be extended so 3rd party integrations can make use of custom targeting within the plugin’s implementation of GAM.

.. _Each key value is obtained while rendering the ad: classes/Newspack-Ads-Providers-GAM-Model.html#method_get_ad_targeting

Size mapping rules
------------------

Newspack Ads implements `its own definition of rules for size mapping`_. The plugin establishes the default, `but filterable`_, threshold of 600 pixels of width to separate mobile ads from desktop ads and groups them so no size smaller than 30% is part of the same group.

.. _its own definition of rules for size mapping: classes/Newspack-Ads-Providers-GAM-Model.html#method_get_responsive_size_map
.. _but filterable: classes/Newspack-Ads-Providers-GAM-Model.html#method_get_ad_unit_size_map

This threshold means that a GAM ad slot can render creatives from 0px to 600px of width in a 600px viewport. Beyond that, only creatives above 600px of width can be rendered.

Let’s take the following example of an ad unit that has the configured sizes:

- 300×250
- 320×50
- 320×100
- 640×320
- 728×90
- 970×90
- 970×250

This will generate the following rules, mapped by viewport width size:

- 300: 300×250
- 320: 300×250, 320×50, 320×100
- 640: 640×320
- 728: 640×320, 728×90
- 970: 728×90, 970×90, 970×250

As you can see above, the 300 and 320 sizes are not part of the size mapping for the 640, 728, and 970 viewports. The 640×320 size is also not part of the 970 viewport, because it’s more than 30% smaller than the 970.

Bounds containers
^^^^^^^^^^^^^^^^^

The plugin introduces “bounds containers” to automatically detect containers that should restrict the allowed sizes of a creative. This is made through `bounds selectors`_, which are then `processed when determining the size map for the ad unit`_.

.. _bounds selectors: classes/Newspack-Ads-Providers-GAM-Scripts.html#method_insert_gpt_footer_script
.. _processed when determining the size map for the ad unit: classes/Newspack-Ads-Providers-GAM-Scripts.html#method_print_gpt_script

To illustrate, if the ad unit described above renders inside a `.wp-block-column` and the block has 400 pixels of rendered width, only the 300×250, 320×50, and 320×100 creatives will be allowed to render regardless of the viewport size.
