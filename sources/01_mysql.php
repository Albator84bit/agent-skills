<?php
/** PHP 5.6 : extension mysql requise. Exemple historique, ne pas exécuter. */
class LegacyDatabase
{
    public function connect($host, $user, $password)
    {
        return mysql_connect($host, $user, $password);
    }

    public function fetchVersion($connection)
    {
        return mysql_query('SELECT VERSION()', $connection);
    }
}

function legacy_database()
{
    return new LegacyDatabase();
}
