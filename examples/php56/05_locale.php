<?php
/** PHP 5.6 : money_format n'est pas disponible sur Windows. */
class LegacyLocaleFormatter
{
    public function currency($amount)
    {
        // Le résultat dépend de LC_MONETARY ; ne pas changer la locale globale ici.
        return money_format('%i', $amount);
    }

    public function windows1251ToKoi8($text)
    {
        return convert_cyr_string($text, 'w', 'k');
    }
}

function legacy_currency($amount)
{
    $formatter = new LegacyLocaleFormatter();
    return $formatter->currency($amount);
}
