<?php

namespace Will;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use jojoe77777\FormAPI\SimpleForm;
use onebone\economyapi\EconomyAPI;

class ShopUIWithCommand extends PluginBase implements Listener {

    /** @var Config */
    private $config;
    private $itemsForSale = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveResource("config.yml");
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->loadItemsForSale();
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        if ($cmd->getName() === 'additem') {
            if (!$sender instanceof Player || !$sender->hasPermission('shopui.command.additem')) {
                $sender->sendMessage("You don't have permission to use this command.");
                return true;
            }

            if (count($args) < 4) {
                $sender->sendMessage("Usage: /additem <name> <price> <command>");
                return false;
            }

            $name = $args[0];
            $price = (int)$args[1];
            $command = implode(" ", array_slice($args, 2));

            $itemData = [
                'name' => $name,
                'price' => $price,
                'command' => $command
            ];

            $this->itemsForSale[] = $itemData;
            $this->saveItemsForSale();

            $sender->sendMessage("Item added for sale successfully!");

            return true;
        } elseif ($cmd->getName() === 'shop') {
            if ($sender instanceof Player) {
                $this->buyUI($sender);
                return true;
            } else {
                $sender->sendMessage("This command can only be used in-game.");
            }
        } elseif ($cmd->getName() === 'delitem') {
            if (!$sender instanceof Player || !$sender->hasPermission('shopui.command.delitem')) {
                $sender->sendMessage("You don't have permission to use this command.");
                return true;
            }

            if (count($args) === 0) {
                $sender->sendMessage("Usage: /delitem <button id (0 it is first id)>");
                return false;
            }

            $slotId = (int)$args[0];

            if (!isset($this->itemsForSale[$slotId])) {
                $sender->sendMessage("Button with slot id $slotId not found.");
                return false;
            }

            // Remove the item from the list
            unset($this->itemsForSale[$slotId]);
            $this->itemsForSale = array_values($this->itemsForSale); // Reset array keys

            // Save the updated list
            $this->saveItemsForSale();

            $sender->sendMessage("Button with slot id $slotId has been removed.");

            return true;
        }

        return false;
    }

    private function loadItemsForSale(): void {
        $this->itemsForSale = $this->config->get("itemsForSale", []);
    }

    private function saveItemsForSale(): void {
        $this->config->set("itemsForSale", array_values($this->itemsForSale)); // Reset array keys before saving
        $this->config->save();
    }

    public function buyUI(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data): void {
            if ($data === null) {
                return;
            }

            if (isset($this->itemsForSale[$data])) {
                $itemData = $this->itemsForSale[$data];
                $price = $itemData['price'];
                $command = str_replace("{player}", $player->getName(), $itemData['command']);

                // Check if the player has enough money
                if (EconomyAPI::getInstance()->myMoney($player) >= $price) {
                    // Deduct the money from the player
                    EconomyAPI::getInstance()->reduceMoney($player, $price);

                    // Execute the command as the console
                    $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), $command);

                    // Send a success message to the player
                    $player->sendMessage("§aItem purchased successfully!");
                } else {
                    $player->sendMessage("§cYou don't have enough money to buy this Item");
                }
            }
        });

        $form->setTitle("§0Shop");
        $form->setContent("§eHi, " . $player->getName() . "! \n§aYou have: " . EconomyAPI::getInstance()->myMoney($player) . " Money. \nSelect an item to purchase:");

        foreach ($this->itemsForSale as $itemData) {
            $form->addButton($itemData['name'] . "\n§0Price: §a$" . $itemData['price'] . " §aMoney");
        }

        $form->sendToPlayer($player);
    }
}

