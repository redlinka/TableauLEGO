package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.FactoryClient;
import fr.uge.univ_eiffel.mediators.InventoryManager;

public class Main {
    public static void main(String[] args) {

        InventoryManager inventory = InventoryManager.makeFromProps("config.properties");
        FactoryClient client = FactoryClient.makeFromProps("config.properties");

        try {

        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
