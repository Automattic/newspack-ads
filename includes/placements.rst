##########
Placements
##########

The plugin provides a collection of default placements that are registered through the public method `Placements::register_placement()`_.

.. _Placements::register_placement(): classes/Newspack-Ads-Placements.html#method_register_placement

.. code:: php

    \Newspack_Ads\Placements::register_placement(
        'my_custom_placement',
        [
            'name' => __( 'My Custom Placement', 'textdomain' ),
            'description' => __( 'My Custom Placement Description', 'textdomain' ),
            'default_enabled' => true, // Whether this placement should be enabled by default.
            'default_ad_unit' => 'some_ad_unit', // A default ad unit to be rendered in this placement.

            'show_ui' => true, // Whether the placement should be configurable through the "Placements" UI in the ads wizard.
            'hook_name' => 'my_custom_ad_hook', // Optional name of the WordPress hook to inject an ad unit into.
            'hooks' => [ // Optional list of hooks to inject an ad into.
                'name' => '', // Friendly name of the hook.
                'hook_name' => '', // WordPress hook name.
            ],
            'supports': [ // An array of supported placement features. "stick_to_top" is the only avaiable at the moment.
                'stick_to_top', // Renders an option in the UI to flag the ad to "stick to top", which adds a 'stick-to-top' class to the ad container.
            ],
        ]
    );

In the example above, we would call the hook ``my_custom_ad_hook`` anywhere in a template to render the ad:

.. code:: php

    do_action( 'my_custom_ad_hook' ); // Renders the ad configured in "My Custom Placement".

When configuring a registered placement you will select which provider and which of the provider’s ad units should be rendered in the named hook:

.. image:: /docs-assets/placements-1.webp

If the placement uses multiple hooks, each hook receives a provider and ad unit pairing:

.. image:: /docs-assets/placements-2.webp

Once a registered placement is configured and active, the `Placements::inject_placement_ad()`_ takes care of rendering the ad from the provider’s `get_ad_code()`_

.. _Placements::inject_placement_ad(): classes/Newspack-Ads-Placements.html#method_inject_placement_ad
.. _get_ad_code(): classes/Newspack-Ads-Providers-GAM-Provider.html#method_get_ad_code

Ad Unit Block as a dynamic placement
------------------------------------

In addition to the default global placements, the Ad Unit Block provides the same configuration and leverages the Placements API to implement `a dynamically generated placement`_ that takes advantage of all the features and extensibility of a regular global placement.

.. _a dynamically generated placement: classes/Newspack-Ads-Blocks.html#method_render_block
