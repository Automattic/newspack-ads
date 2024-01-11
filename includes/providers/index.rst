.. toctree::
    :hidden:

    gam/index

#########
Providers
#########

The `Providers`_ API provides the tools so an ad server can integrate with the plugin’s placements system. They must be registered extending the `Provider`_ class, which implements the `Provider interface`_.


.. _Providers: classes/Newspack-Ads-Providers.html
.. _Provider: classes/Newspack-Ads-Providers-Provider.html
.. _Provider interface: classes/Newspack-Ads-Providers-Provider-Interface.html

We currently support `Google Ad Manager`_ and Broadstreet natively. The `Broadstreet implementation`_ provides a great example of a very straightforward and simple provider, taking advantage of `the service’s WP plugin`_ API to integrate their ads with our placements.

.. _Google Ad Manager: classes/Newspack-Ads-Providers-GAM-Provider.html
.. _Broadstreet implementation: classes/Newspack-Ads-Providers-Broadstreet-Provider.html
.. _the service’s WP plugin: https://wordpress.org/plugins/broadstreet/

For Google Ad Manager, on the other hand, we have a much more advanced implementation. It uses the same Provider class to register and render ads in placements but because of the sophistication around what’s possible with GAM, we have a lot of different features around it.