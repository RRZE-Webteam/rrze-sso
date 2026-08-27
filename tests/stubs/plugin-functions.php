<?php

namespace RRZE\SSO;

/**
 * Returns the plugin instance.
 */
function plugin(): Plugin
{
    return new Plugin('');
}

/**
 * Returns the SimpleSAML integration instance.
 */
function simpleSAML(): SimpleSAML
{
    return new SimpleSAML();
}
