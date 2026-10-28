<?php
/** PHP 5.6 : fonctions de l'extension historique POSIX regex. */
class LegacyTextParser
{
    public function isReference($value)
    {
        return ereg('^[A-Z]+[0-9]+$', $value) !== false;
    }

    public function words($value)
    {
        return split('[[:space:]]+', $value);
    }
}

function legacy_words($text)
{
    $parser = new LegacyTextParser();
    return $parser->words($text);
}
