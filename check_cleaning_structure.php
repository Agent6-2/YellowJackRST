<?php
require_once __DIR__ . '/config/database.php';

$db = getDB();

try {
    echo "Structure de la table cleaning_services:\n";
    $result = $db->query('DESCRIBE cleaning_services');
    while($row = $result->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\n\nExemple de données (première ligne):\n";
    $sample = $db->query('SELECT * FROM cleaning_services LIMIT 1');
    $sampleRow = $sample->fetch();
    if ($sampleRow) {
        foreach ($sampleRow as $key => $value) {
            if (!is_numeric($key)) {
                echo "$key: $value\n";
            }
        }
    } else {
        echo "Aucune donnée trouvée\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>