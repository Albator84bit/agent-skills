<?php
/** PHP 5.6 : ces fonctions existent mais renvoient déjà false depuis PHP 5.4. */
class LegacyInputSettings
{
    public function requestEscapingEnabled()
    {
        return get_magic_quotes_gpc();
    }

    public function runtimeEscapingEnabled()
    {
        return get_magic_quotes_runtime();
    }
}

function legacy_input_settings()
{
    $settings = new LegacyInputSettings();
    return array(
        'request' => $settings->requestEscapingEnabled(),
        'runtime' => $settings->runtimeEscapingEnabled()
    );
}
