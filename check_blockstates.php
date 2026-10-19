<?php
require 'vendor/autoload.php';

use pocketmine\network\mcpe\convert\ItemTypeDictionaryFromDataHelper;
use pocketmine\utils\Filesystem;

// Test old vs new required_item_list
foreach ([
    'vendor/pocketmine/bedrock-data/required_item_list.json',
    'vendor/pocketmine/bedrock-data/required_item_list-1.26.50.json',
] as $path) {
    if (!file_exists($path)) { echo "$path: NOT FOUND\n"; continue; }
    try {
        $dict = ItemTypeDictionaryFromDataHelper::loadFromString(Filesystem::fileGetContents($path));
        $entries = $dict->getEntries();
        echo "$path: " . count($entries) . " entries, first=" . $entries[0]->getStringId() . "\n";
    } catch (Throwable $e) {
        echo "$path: ERROR: " . $e->getMessage() . "\n";
    }
}
