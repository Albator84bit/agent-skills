<?php
/** PHP 5.6 : fonctions supprimées en PHP 8.0. */
class LegacyCollection
{
    public function doubleValues($values)
    {
        $callback = create_function('$value', 'return $value * 2;');
        return array_map($callback, $values);
    }

    public function entries($values)
    {
        $result = array();
        reset($values);
        while (($entry = each($values)) !== false) {
            $result[$entry['key']] = $entry['value'];
        }
        return $result;
    }
}

function legacy_double($values)
{
    $collection = new LegacyCollection();
    return $collection->doubleValues($values);
}
