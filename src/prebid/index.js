import pbjs from 'prebid.js';

// Prebid.js modules.
import 'prebid.js/modules/enrichmentFpdModule';
import 'prebid.js/modules/express';

// Media.net modules.
import 'prebid.js/modules/medianetBidAdapter';
import 'prebid.js/modules/medianetRtdProvider';

window.pbjs = window.pbjs || pbjs;

// Required to process existing pbjs.queue blocks and setup any further pbjs.queue execution.
pbjs.processQueue();
